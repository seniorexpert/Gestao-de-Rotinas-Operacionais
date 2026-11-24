<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RotinasController extends Controller
{
    public function __construct(private ExecucoesDiarias $execucoes) {}

    public function store(Request $request): RedirectResponse
    {
        $emergencial = $request->routeIs('rotinas.emergenciais.store');
        $agora = now(ExecucoesDiarias::FUSO)->toImmutable();
        $usuario = $request->user();
        $empresa = $usuario->isAdmin()
            ? Rule::exists('empresas', 'id_empresa')
            : Rule::exists('empresa_usuario', 'id_empresa')->where('id_usuario', $usuario->getKey());
        $dados = $request->validate([
            'id_empresa' => ['bail', 'required', 'integer', $empresa],
            'colaborador_id' => ['bail', 'required', 'integer', Rule::exists('empresa_usuario', 'id_usuario')
                ->where('id_empresa', $request->integer('id_empresa'))],
            ...($emergencial ? [] : ['status_rotina' => ['nullable', 'in:Ativa,Inativa']]),
            'tarefas_json' => ['required', 'json'],
        ], [
            'id_empresa.required' => 'Selecione uma unidade.',
            'id_empresa.exists' => 'Selecione uma unidade vinculada à sua conta.',
            'colaborador_id.required' => 'Selecione um colaborador.',
            'colaborador_id.exists' => 'Selecione um colaborador vinculado à unidade.',
        ]);
        $tarefas = Validator::make(['tarefas' => json_decode($dados['tarefas_json'], true)], [
            'tarefas' => ['required', 'array', 'list', 'min:1'],
            ...($emergencial
                ? ['tarefas.*.data' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$agora->toDateString()]]
                : ['tarefas.*.dias' => ['required', 'string', 'max:255']]),
            'tarefas.*.horario' => ['required', 'date_format:H:i,H:i:s'],
            'tarefas.*.id_tarefa' => ['required', 'integer', 'exists:tarefas,id_tarefa'],
            'tarefas.*.id_local' => ['required', 'integer', 'exists:locais,id_local'],
            'tarefas.*.morador' => ['nullable', 'string', 'max:255'],
            'tarefas.*.auxilio' => ['required', 'in:Sim,Não'],
            'tarefas.*.cuidador' => ['nullable', 'integer', Rule::notIn([$dados['colaborador_id']]),
                Rule::exists('empresa_usuario', 'id_usuario')->where('id_empresa', $dados['id_empresa'])],
            'tarefas.*.observacao' => ['nullable', 'string'],
        ], [
            'tarefas.*.data.date_format' => 'Informe uma data válida de execução.',
            'tarefas.*.data.after_or_equal' => 'Agende a tarefa emergencial para hoje ou uma data futura.',
        ])->validate()['tarefas'];
        $locais = DB::table('locais')->whereIn('id_local', array_column($tarefas, 'id_local'))
            ->pluck('local', 'id_local');

        $descricao = $emergencial ? 'Tarefa emergencial cadastrada' : 'Rotina cadastrada';

        DB::transaction(function () use ($dados, $tarefas, $locais, $descricao, $emergencial, $agora) {
            $idsRotinas = [];

            foreach ($tarefas as $tarefa) {
                $auxilio = $tarefa['auxilio'] === 'Sim';
                $horario = strlen($tarefa['horario']) === 5 ? $tarefa['horario'].':00' : $tarefa['horario'];
                $agendada = $emergencial
                    ? CarbonImmutable::parse($tarefa['data'] ?? $agora->toDateString(), ExecucoesDiarias::FUSO)
                        ->setTimeFromTimeString($horario)
                    : null;
                $registro = [
                    'status' => $emergencial || ($dados['status_rotina'] ?? 'Ativa') === 'Ativa',
                    'id_empresa' => $dados['id_empresa'],
                    'id_usuario' => $dados['colaborador_id'],
                    'tipo_rotina' => $emergencial ? ExecucoesDiarias::TIPO_EMERGENCIAL : ExecucoesDiarias::TIPO_DIARIA,
                    'agendada_em' => $agendada?->utc(),
                    'dias_semanas' => $agendada ? Str::ucfirst($agendada->locale('pt_BR')->dayName) : $tarefa['dias'],
                    'horario' => $horario,
                    'id_tarefa' => $tarefa['id_tarefa'],
                    'morador' => $tarefa['morador'] ?? null,
                    'id_local' => $tarefa['id_local'],
                    'local' => $locais->get($tarefa['id_local']),
                    'auxilio' => $auxilio,
                    'obs' => $tarefa['observacao'] ?? null,
                    'created_at' => $agora->utc(),
                    'updated_at' => $agora->utc(),
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
                        ...Arr::only($registro, ['status', 'tipo_rotina', 'agendada_em', 'id_empresa', 'id_usuario', 'dias_semanas', 'horario', 'id_tarefa', 'id_local', 'auxilio']),
                        'id_usuario_auxiliar' => $auxilio ? ($tarefa['cuidador'] ?? null) : null,
                    ],
                    descricao: $descricao,
                    idEmpresa: (int) $dados['id_empresa'],
                    idRotina: (int) $idRotina,
                );
            }

            $this->execucoes->gerar($agora, idsRotinas: $idsRotinas);
        });

        return back()->with('success', $emergencial ? 'Tarefas emergenciais salvas com sucesso!' : 'Todas as rotinas foram salvas com sucesso!');
    }
}
