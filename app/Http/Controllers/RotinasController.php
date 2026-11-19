<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\ExecucoesDiarias;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RotinasController extends Controller
{
    public function __construct(private ExecucoesDiarias $execucoes) {}

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id_empresa' => ['bail', 'required', 'integer', Rule::exists('empresa_usuario', 'id_empresa')
                ->where('id_usuario', $request->user()->getAuthIdentifier())],
            'colaborador_id' => ['bail', 'required', 'integer', Rule::exists('empresa_usuario', 'id_usuario')
                ->where('id_empresa', $request->integer('id_empresa'))],
            'status_rotina' => ['nullable', 'in:Ativa,Inativa'],
            'tarefas_json' => ['required', 'json'],
        ], [
            'id_empresa.required' => 'Selecione uma unidade.',
            'id_empresa.exists' => 'Selecione uma unidade vinculada à sua conta.',
            'colaborador_id.exists' => 'Selecione um colaborador vinculado à unidade.',
        ]);
        $tarefas = Validator::make(['tarefas' => json_decode($dados['tarefas_json'], true)], [
            'tarefas' => ['required', 'array', 'list', 'min:1'],
            'tarefas.*.dias' => ['required', 'string', 'max:255'],
            'tarefas.*.horario' => ['required', 'date_format:H:i,H:i:s'],
            'tarefas.*.id_tarefa' => ['required', 'integer', 'exists:tarefas,id_tarefa'],
            'tarefas.*.id_local' => ['required', 'integer', 'exists:locais,id_local'],
            'tarefas.*.morador' => ['nullable', 'string', 'max:255'],
            'tarefas.*.auxilio' => ['required', 'in:Sim,Não'],
            'tarefas.*.cuidador' => ['nullable', 'integer', Rule::notIn([$dados['colaborador_id']]),
                Rule::exists('empresa_usuario', 'id_usuario')->where('id_empresa', $dados['id_empresa'])],
            'tarefas.*.observacao' => ['nullable', 'string'],
        ])->validate()['tarefas'];
        $locais = DB::table('locais')->whereIn('id_local', array_column($tarefas, 'id_local'))
            ->pluck('local', 'id_local');

        $descricao = $request->routeIs('rotinas.emergenciais.store') ? 'Tarefa emergencial cadastrada' : 'Rotina cadastrada';

        DB::transaction(function () use ($dados, $tarefas, $locais, $descricao) {
            $agora = now()->toImmutable();
            $idsRotinas = [];

            foreach ($tarefas as $tarefa) {
                $auxilio = $tarefa['auxilio'] === 'Sim';
                $registro = [
                    'status' => ($dados['status_rotina'] ?? 'Ativa') === 'Ativa',
                    'id_empresa' => $dados['id_empresa'],
                    'id_usuario' => $dados['colaborador_id'],
                    'dias_semanas' => $tarefa['dias'],
                    'horario' => strlen($tarefa['horario']) === 5 ? $tarefa['horario'].':00' : $tarefa['horario'],
                    'id_tarefa' => $tarefa['id_tarefa'],
                    'morador' => $tarefa['morador'] ?? null,
                    'id_local' => $tarefa['id_local'],
                    'local' => $locais->get($tarefa['id_local']),
                    'auxilio' => $auxilio,
                    'obs' => $tarefa['observacao'] ?? null,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
                $idRotina = DB::table('rotinas')->insertGetId($registro, 'id_rotina');
                $idsRotinas[] = (int) $idRotina;

                if ($auxilio && filled($tarefa['cuidador'] ?? null)) {
                    DB::table('rotina_auxiliares')->insert([
                        'id_rotina' => $idRotina,
                        'id_usuario' => $tarefa['cuidador'],
                    ]);
                }

                AuditoriaService::registrar(
                    'CREATE', 'rotinas', (string) $idRotina,
                    dadosNovos: [
                        ...Arr::only($registro, ['status', 'id_empresa', 'id_usuario', 'dias_semanas', 'horario', 'id_tarefa', 'id_local', 'auxilio']),
                        'id_usuario_auxiliar' => $auxilio ? ($tarefa['cuidador'] ?? null) : null,
                    ],
                    descricao: $descricao,
                    idEmpresa: (int) $dados['id_empresa'],
                    idRotina: (int) $idRotina,
                );
            }

            $this->execucoes->gerar($agora, idsRotinas: $idsRotinas);
        });

        return back()->with('success', 'Todas as rotinas foram salvas com sucesso!');
    }
}
