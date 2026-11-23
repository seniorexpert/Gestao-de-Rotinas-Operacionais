<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use App\Services\PermissoesAcesso;
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

    public function __construct(private PermissoesAcesso $permissoes, private ConsultaRotinas $rotinas) {}

    public function index(Request $request): View
    {
        $operador = $request->user();
        $visaoGeral = $this->permissoes->gerenciaGeral($operador);
        $nasUnidades = fn ($consulta) => $consulta->whereIn('empresas.id_empresa', $this->rotinas->empresas($operador));

        return view('auth.user-management', [
            'usuarios' => User::query()
                ->with(['grupoAcesso:id_grupo_acesso,grupo_acesso', 'empresas:id_empresa,empresa'])
                ->when(! $visaoGeral, fn ($consulta) => $consulta->whereHas('empresas', $nasUnidades))
                ->orderBy('usuario')
                ->get(['id_usuario', 'usuario', 'username', 'id_grupo_acesso', 'grupo_acesso_nome', 'photo', 'status']),
            'gruposAcesso' => GrupoAcesso::withCount(['usuarios' => fn ($consulta) => $consulta
                ->when(! $visaoGeral, fn ($usuarios) => $usuarios->whereHas('empresas', $nasUnidades))])->orderBy('grupo_acesso')->get(),
            'empresas' => Empresa::query()->when(! $visaoGeral, fn ($consulta) => $consulta->whereIn('id_empresa', $this->rotinas->empresas($operador)))
                ->orderBy('empresa')->get(['id_empresa', 'empresa', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request, cadastro: true);

        try {
            $this->persistir($request->user(), $dados);
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

        $this->persistir($request->user(), $dados, $id);

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
            $regras['username'] = ['bail', 'required', 'string', 'max:100', function (string $campo, string $valor, \Closure $falhar): void {
                if (strcasecmp(trim($valor), User::LOGIN_ADMIN) === 0) {
                    $falhar('Este login está reservado para a conta administradora.');
                }
            }, Rule::unique(User::class, 'username')];
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
    private function persistir(User $operador, array $dados, ?int $id = null): void
    {
        $novaFoto = ($dados['photo'] ?? null)?->store('users', 'public');
        if ($novaFoto === false) {
            throw ValidationException::withMessages(['photo' => 'A foto não pôde ser salva.']);
        }

        try {
            $fotoSubstituida = DB::transaction(function () use ($operador, $dados, $id, $novaFoto): ?string {
                $usuario = $id === null
                    ? new User(['status' => true])
                    : User::query()->lockForUpdate()->findOrFail($id);
                $fotoAnterior = $usuario->photo;

                $this->autorizarCadastro($operador, $usuario, $dados);
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

    private function autorizarCadastro(User $operador, User $usuario, array $dados): void
    {
        if ($usuario->isAdmin()) {
            abort_unless($operador->isAdmin(), 403);
            if (isset($dados['status']) && ! $dados['status']) {
                throw ValidationException::withMessages(['status' => 'A conta admin deve permanecer ativa.']);
            }
        }

        if ($operador->isAdmin()) {
            return;
        }

        if (! $this->permissoes->gerenciaGeral($operador)) {
            $unidades = $operador->empresas()->pluck('empresas.id_empresa');
            $atuais = $usuario->exists ? $usuario->empresas()->pluck('empresas.id_empresa') : collect();
            abort_if($usuario->exists && ! $usuario->is($operador) && $atuais->isEmpty(), 403);
            abort_if($atuais->merge($dados['empresas'] ?? [])->diff($unidades)->isNotEmpty(), 403);
        }

        $alvo = clone $usuario;
        $alvo->status = true;
        if ($usuario->exists || ! array_key_exists('id_grupo_acesso', $dados)) {
            $this->autorizarNiveisDoUsuario($operador, $alvo);
        }

        if (array_key_exists('id_grupo_acesso', $dados)) {
            $grupo = $dados['id_grupo_acesso'] === null ? null : GrupoAcesso::findOrFail($dados['id_grupo_acesso']);
            $alvo->setRelation('grupoAcesso', $grupo);
            $this->autorizarNiveisDoUsuario($operador, $alvo);
        }
    }

    private function autorizarNiveisDoUsuario(User $operador, User $alvo): void
    {
        abort_if($this->permissoes->gerenciaGeral($alvo) && ! $this->permissoes->gerenciaGeral($operador), 403);

        foreach (array_keys(PermissoesAcesso::MODULOS) as $modulo) {
            abort_if($this->permissoes->nivel($alvo, $modulo) > $this->permissoes->nivel($operador, $modulo), 403);
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
