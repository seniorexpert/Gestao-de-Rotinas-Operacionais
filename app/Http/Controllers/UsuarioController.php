<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UsuarioController extends Controller
{
    private const LOGIN_EXISTENTE = 'Já existe um usuário com esse login.';

    public function index(): View
    {
        return view('auth.user-management', [
            'usuarios' => User::query()
                ->with(['grupoAcesso:id_grupo_acesso,grupo_acesso', 'empresas:id_empresa,empresa'])
                ->orderBy('usuario')
                ->get(['id_usuario', 'usuario', 'username', 'id_grupo_acesso', 'grupo_acesso_nome', 'photo', 'status']),
            'gruposAcesso' => GrupoAcesso::withCount('usuarios')->orderBy('grupo_acesso')->get(),
            'empresas' => Empresa::orderBy('empresa')->get(['id_empresa', 'empresa', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request, cadastro: true);

        try {
            $this->persistir($dados);
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::where('username', $dados['username'])->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages(['username' => self::LOGIN_EXISTENTE]);
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuário cadastrado com sucesso.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $dados = $this->validar($request, cadastro: false);

        $this->persistir($dados, $id);

        return redirect()->route('usuarios.index')->with('status', 'Usuário atualizado com sucesso.');
    }

    /** @return array{usuario: string, username?: string, status?: bool|int|string, password?: ?string, id_grupo_acesso?: int|string|null, empresas?: list<int|string>|null, photo?: ?UploadedFile, remove_photo?: bool|int|string} */
    private function validar(Request $request, bool $cadastro): array
    {
        $regras = [
            'usuario' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
            'id_grupo_acesso' => ['bail', 'nullable', 'integer', Rule::exists(GrupoAcesso::class, 'id_grupo_acesso')],
            'empresas' => ['nullable', 'array', 'list'],
            'empresas.*' => ['bail', 'required', 'integer', 'distinct', Rule::exists(Empresa::class, 'id_empresa')],
            'password' => [$cadastro ? 'required' : 'nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];

        if ($cadastro) {
            $regras['username'] = ['bail', 'required', 'string', 'max:100', Rule::unique(User::class, 'username')];
        }

        return $request->validate($regras, [
            'usuario.required' => 'Informe o nome completo.',
            'status.boolean' => 'Selecione a situação Ativo ou Inativo.',
            'username.required' => 'Informe o nome de usuário.',
            'username.unique' => self::LOGIN_EXISTENTE,
            'id_grupo_acesso.exists' => 'Selecione um grupo de acesso cadastrado.',
            'empresas.*.exists' => 'Selecione unidades cadastradas.',
            'password.required' => 'Informe a senha.',
            'photo.image' => 'Selecione um arquivo de imagem para a foto.',
            'photo.max' => 'A foto deve ter até 2 MB.',
        ]);
    }

    /** @param array{usuario: string, username?: string, status?: bool|int|string, password?: ?string, id_grupo_acesso?: int|string|null, empresas?: list<int|string>|null, photo?: ?UploadedFile, remove_photo?: bool|int|string} $dados */
    private function persistir(array $dados, ?int $id = null): void
    {
        $novaFoto = ($dados['photo'] ?? null)?->store('users', 'public');
        if ($novaFoto === false) {
            throw ValidationException::withMessages(['photo' => 'A foto não pôde ser salva.']);
        }

        try {
            $fotoSubstituida = DB::transaction(function () use ($dados, $id, $novaFoto): ?string {
                $usuario = $id === null
                    ? new User(['status' => true])
                    : User::query()->lockForUpdate()->findOrFail($id);
                $fotoAnterior = $usuario->photo;

                $this->salvar($usuario, $dados, $novaFoto);

                return $fotoAnterior !== $usuario->photo ? $fotoAnterior : null;
            });
        } catch (Throwable $erro) {
            if ($novaFoto !== null) {
                Storage::disk('public')->delete($novaFoto);
            }

            throw $erro;
        }

        if ($fotoSubstituida !== null) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($fotoSubstituida));
        }
    }

    /** @param array{usuario: string, username?: string, status?: bool|int|string, password?: ?string, id_grupo_acesso?: int|string|null, empresas?: list<int|string>|null, remove_photo?: bool|int|string} $dados */
    private function salvar(User $usuario, array $dados, ?string $novaFoto): void
    {
        $novo = ! $usuario->exists;
        $anteriores = $novo ? null : $usuario->getAttributes();
        $atributos = Arr::only($dados, ['usuario', 'username']);

        if (isset($dados['status'])) {
            $atributos['status'] = (bool) $dados['status'];
        }

        if (array_key_exists('id_grupo_acesso', $dados)) {
            $grupo = $dados['id_grupo_acesso'] === null ? null : GrupoAcesso::findOrFail($dados['id_grupo_acesso']);
            $atributos['id_grupo_acesso'] = $grupo?->getKey();
            $atributos['grupo_acesso_nome'] = $grupo?->grupo_acesso;
        }

        if (isset($dados['password'])) {
            $atributos['password'] = Hash::make($dados['password']);
        }

        if ($novaFoto !== null || ($dados['remove_photo'] ?? false)) {
            $atributos['photo'] = $novaFoto;
        }

        $usuario->fill($atributos)->save();

        if ($novo || $usuario->wasChanged()) {
            $descricao = match (true) {
                $novo => 'Cadastro de usuário.',
                $usuario->wasChanged('status') => $usuario->status ? 'Usuário reativado.' : 'Usuário inativado.',
                default => 'Atualização de usuário.',
            };
            if (! $novo && $usuario->wasChanged('password')) {
                $descricao .= ' Senha redefinida.';
            }

            AuditoriaService::registrar(
                acao: $novo ? 'criado' : 'atualizado',
                tabela: $usuario->getTable(),
                registroId: (string) $usuario->getKey(),
                dadosAnteriores: $anteriores,
                dadosNovos: $usuario->getAttributes(),
                descricao: $descricao
            );
        }

        if (array_key_exists('empresas', $dados)) {
            $this->sincronizarEmpresas($usuario, $dados['empresas'] ?? []);
        }
    }

    /** @param list<int|string> $idsEmpresas */
    private function sincronizarEmpresas(User $usuario, array $idsEmpresas): void
    {
        $empresas = Empresa::query()->whereIn('id_empresa', $idsEmpresas)->pluck('empresa', 'id_empresa');

        if ($empresas->count() !== count($idsEmpresas)) {
            throw ValidationException::withMessages(['empresas' => 'Selecione unidades cadastradas.']);
        }

        $mudancas = $usuario->empresas()->sync($empresas->map(fn (string $nome) => ['empresa_nome' => $nome])->all());

        foreach ($mudancas['attached'] as $idEmpresa) {
            AuditoriaService::registrar(
                acao: 'criado',
                tabela: 'empresa_usuario',
                registroId: $usuario->getKey().':'.$idEmpresa,
                dadosNovos: [
                    'id_usuario' => $usuario->getKey(),
                    'id_empresa' => $idEmpresa,
                    'empresa_nome' => $empresas[$idEmpresa],
                ],
                descricao: 'Vínculo do usuário com a empresa.',
                idEmpresa: (int) $idEmpresa,
            );
        }

        foreach ($mudancas['detached'] as $idEmpresa) {
            AuditoriaService::registrar(
                acao: 'removido',
                tabela: 'empresa_usuario',
                registroId: $usuario->getKey().':'.$idEmpresa,
                dadosAnteriores: ['id_usuario' => $usuario->getKey(), 'id_empresa' => $idEmpresa],
                descricao: 'Remoção do vínculo do usuário com a empresa.',
                idEmpresa: (int) $idEmpresa,
            );
        }
    }
}
