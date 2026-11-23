<?php

namespace App\Http\Controllers;

use App\Models\GrupoAcesso;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\PermissoesAcesso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PermissaoAcessoController extends Controller
{
    public function __construct(private PermissoesAcesso $permissoes) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(PermissoesAcesso::ORIGENS))],
            'id' => ['nullable', 'integer', 'min:1'],
        ]);
        $tipo = $filtros['tipo'] ?? 'grupo';
        $opcoes = $tipo === 'grupo'
            ? GrupoAcesso::query()->orderBy('grupo_acesso')->get(['id_grupo_acesso', 'grupo_acesso', 'status'])
            : User::query()->with('grupoAcesso:id_grupo_acesso,grupo_acesso,status')
                ->orderBy('usuario')->get(['id_usuario', 'usuario', 'username', 'id_grupo_acesso', 'status']);
        $alvo = isset($filtros['id']) ? $opcoes->find((int) $filtros['id']) : $opcoes->first();
        abort_if(isset($filtros['id']) && ! $alvo, 404);
        $admin = $alvo instanceof User && $alvo->isAdmin();
        $regras = $alvo && ! $admin ? $this->permissoes->configuradas($tipo, (int) $alvo->getKey()) : [];

        $modulos = PermissoesAcesso::MODULOS;
        foreach ($modulos as $chave => &$modulo) {
            $modulo['herdado'] = $tipo === 'grupo'
                ? $this->permissoes->nivelPadrao($alvo, $chave)
                : $this->permissoes->nivelGrupo($alvo?->grupoAcesso, $chave);
        }
        unset($modulo);

        return view('auth.permissoes-acesso', [
            'tipo' => $tipo, 'opcoes' => $opcoes, 'alvo' => $alvo, 'admin' => $admin, 'regras' => $regras,
            'modulos' => $modulos, 'niveis' => PermissoesAcesso::NIVEIS,
        ]);
    }

    public function update(Request $request, string $tipo, int $id): RedirectResponse
    {
        $validacao = ['regras' => ['required', 'array:'.implode(',', array_keys(PermissoesAcesso::MODULOS))]];
        foreach (PermissoesAcesso::MODULOS as $chave => $modulo) {
            $niveis = ['herdar', '0', '1'];
            if ($modulo['editavel']) {
                $niveis[] = '2';
            }
            $validacao['regras.'.$chave] = ['sometimes', 'required', 'string', Rule::in($niveis)];
        }
        $dados = $request->validate($validacao)['regras'];
        [$tabela, $chave] = PermissoesAcesso::ORIGENS[$tipo];

        DB::transaction(function () use ($tipo, $id, $dados, $tabela, $chave): void {
            $alvo = $tipo === 'grupo'
                ? GrupoAcesso::query()->lockForUpdate()->findOrFail($id)
                : User::query()->lockForUpdate()->findOrFail($id);
            abort_if($alvo instanceof User && $alvo->isAdmin(), 403);

            $this->permissoes->recarregar($tipo, $id);
            $antes = $this->permissoes->configuradas($tipo, $id);
            $depois = $antes;
            foreach ($dados as $modulo => $nivel) {
                if ($nivel === 'herdar') {
                    unset($depois[$modulo]);
                } else {
                    $depois[$modulo] = (int) $nivel;
                }
            }
            ksort($depois);
            if ($antes === $depois) {
                return;
            }

            DB::table($tabela)->where($chave, $id)->delete();
            $registros = [];
            foreach ($depois as $modulo => $nivel) {
                $registros[] = [$chave => $id, 'modulo' => $modulo, 'nivel' => $nivel];
            }
            if ($registros !== []) {
                DB::table($tabela)->insert($registros);
            }

            AuditoriaService::registrar(
                'UPDATE', $tabela, (string) $id,
                dadosAnteriores: $antes, dadosNovos: $depois,
                descricao: 'Permissões de acesso atualizadas',
            );
        }, 3);
        $this->permissoes->recarregar($tipo, $id);

        return to_route('permissoes.index', ['tipo' => $tipo, 'id' => $id])->with('success', 'Permissões atualizadas.');
    }
}
