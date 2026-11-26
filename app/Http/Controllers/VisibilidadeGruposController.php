<?php

namespace App\Http\Controllers;

use App\Models\GrupoAcesso;
use App\Services\AuditoriaService;
use App\Services\VisibilidadeGrupos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VisibilidadeGruposController extends Controller
{
    public function __construct(private VisibilidadeGrupos $visibilidade) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate(['grupo' => ['nullable', 'integer', 'min:1']]);
        $grupos = GrupoAcesso::query()->orderBy('grupo_acesso')->get(['id_grupo_acesso', 'grupo_acesso', 'status']);
        $origem = isset($filtros['grupo']) ? $grupos->find((int) $filtros['grupo']) : $grupos->first();
        abort_if(isset($filtros['grupo']) && $origem === null, 404);
        $visiveis = $origem ? $this->visibilidade->configurados((int) $origem->getKey()) : [];

        return view('auth.visibilidade-grupos', compact('grupos', 'origem', 'visiveis'));
    }

    public function update(Request $request, int $grupo): RedirectResponse
    {
        $dados = $request->validate([
            'modo' => ['required', Rule::in(['todos', 'selecionados'])],
            'grupos_visiveis' => ['exclude_unless:modo,selecionados', 'sometimes', 'array', 'list'],
            'grupos_visiveis.*' => ['bail', 'required', 'integer', 'distinct', Rule::exists(GrupoAcesso::class, 'id_grupo_acesso')],
        ], [
            'modo.required' => 'Selecione quais grupos poderão ser consultados.',
            'grupos_visiveis.*.exists' => 'Selecione apenas grupos de acesso cadastrados.',
        ]);

        $depois = $dados['modo'] === 'todos' ? [] : array_unique([$grupo, ...array_map('intval', $dados['grupos_visiveis'] ?? [])]);
        sort($depois, SORT_NUMERIC);

        DB::transaction(function () use ($grupo, $depois): void {
            GrupoAcesso::query()->lockForUpdate()->findOrFail($grupo);
            $this->visibilidade->recarregar($grupo);
            $antes = $this->visibilidade->configurados($grupo);

            if ($antes === $depois) {
                return;
            }

            DB::table(VisibilidadeGrupos::TABELA)->where('id_grupo_origem', $grupo)->delete();
            foreach (array_chunk($depois, 200) as $lote) {
                DB::table(VisibilidadeGrupos::TABELA)->insert(array_map(fn (int $visivel): array => [
                    'id_grupo_origem' => $grupo,
                    'id_grupo_visivel' => $visivel,
                ], $lote));
            }

            AuditoriaService::registrar('UPDATE', VisibilidadeGrupos::TABELA, (string) $grupo,
                dadosAnteriores: ['grupos_visiveis' => $antes], dadosNovos: ['grupos_visiveis' => $depois],
                descricao: 'Visibilidade entre grupos de acesso atualizada');
        }, 3);
        $this->visibilidade->recarregar($grupo);

        return to_route('visibilidade-grupos.index', ['grupo' => $grupo])->with('success', 'Visibilidade do grupo atualizada.');
    }
}
