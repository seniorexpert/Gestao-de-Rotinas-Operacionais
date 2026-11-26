<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RotinasController extends Controller
{
    public function __construct(private ExecucoesDiarias $execucoes) {}

    public function store(Request $request, ConsultaRotinas $catalogo): RedirectResponse
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
            ...($emergencial ? [] : ['status_rotina' => ['nullable', 'in:1,0,Ativa,Inativa']]),
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
            'tarefas.*.id_tarefa' => ['required', 'integer', 'min:1'],
            'tarefas.*.id_local' => ['exclude_with:tarefas.*.local_morador', 'required', 'integer', 'min:1'],
            'tarefas.*.local_morador' => ['nullable', 'string', 'max:255'],
            'tarefas.*.morador' => ['nullable', 'string', 'max:255'],
            'tarefas.*.auxilio' => ['required', 'in:Sim,Não'],
            'tarefas.*.cuidador' => ['nullable', 'integer', Rule::notIn([$dados['colaborador_id']]),
                Rule::exists('empresa_usuario', 'id_usuario')->where('id_empresa', $dados['id_empresa'])],
            'tarefas.*.observacao' => ['nullable', 'string'],
        ], [
            'tarefas.*.data.date_format' => 'Informe uma data válida de execução.',
            'tarefas.*.data.after_or_equal' => 'Agende a tarefa emergencial para hoje ou uma data futura.',
        ])->validate()['tarefas'];
        $descricao = $emergencial ? 'Tarefa emergencial cadastrada' : 'Rotina cadastrada';

        DB::transaction(function () use ($dados, $tarefas, $catalogo, $descricao, $emergencial, $agora) {
            $unidade = (int) $dados['id_empresa'];
            $tarefasDisponiveis = $catalogo->catalogoNaUnidade('tarefas', $unidade)
                ->whereIntegerInRaw('c.id_tarefa', array_column($tarefas, 'id_tarefa'))
                ->orderBy('c.id_tarefa')->lockForUpdate()->pluck('c.id_tarefa')->flip();
            $locais = $catalogo->catalogoNaUnidade('locais', $unidade)
                ->whereIntegerInRaw('c.id_local', array_filter(array_column($tarefas, 'id_local')))
                ->orderBy('c.id_local')->lockForUpdate()->pluck('c.local', 'c.id_local');

            $idsRotinas = [];
            $grupoRotina = $emergencial ? null : (string) Str::uuid();

            foreach ($tarefas as $indice => $tarefa) {
                if (! $tarefasDisponiveis->has($tarefa['id_tarefa'])) {
                    throw ValidationException::withMessages(["tarefas.$indice.id_tarefa" => 'Selecione uma tarefa ativa da unidade escolhida.']);
                }
                $localMorador = $this->localDoMorador($tarefa, "tarefas.$indice");
                if ($localMorador === null && ! $locais->has($tarefa['id_local'])) {
                    throw ValidationException::withMessages(["tarefas.$indice.id_local" => 'Selecione um local ativo da unidade escolhida.']);
                }
                $auxilio = $tarefa['auxilio'] === 'Sim';
                $horario = strlen($tarefa['horario']) === 5 ? $tarefa['horario'].':00' : $tarefa['horario'];
                $agendada = $emergencial
                    ? CarbonImmutable::parse($tarefa['data'] ?? $agora->toDateString(), ExecucoesDiarias::FUSO)
                        ->setTimeFromTimeString($horario)
                    : null;
                $registro = [
                    'status' => $emergencial || in_array((string) ($dados['status_rotina'] ?? '1'), ['1', 'Ativa'], true),
                    'id_empresa' => $dados['id_empresa'],
                    'id_usuario' => $dados['colaborador_id'],
                    'grupo_rotina' => $grupoRotina,
                    'tipo_rotina' => $emergencial ? ExecucoesDiarias::TIPO_EMERGENCIAL : ExecucoesDiarias::TIPO_DIARIA,
                    'agendada_em' => $agendada?->utc(),
                    'dias_semanas' => $agendada ? Str::ucfirst($agendada->locale('pt_BR')->dayName) : $tarefa['dias'],
                    'horario' => $horario,
                    'id_tarefa' => $tarefa['id_tarefa'],
                    'morador' => $tarefa['morador'] ?? null,
                    'id_local' => $localMorador === null ? $tarefa['id_local'] : null,
                    'local' => $localMorador ?? $locais->get($tarefa['id_local']),
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
        }, 3);

        return back()->with('success', $emergencial ? 'Tarefas emergenciais salvas com sucesso!' : 'Todas as rotinas foram salvas com sucesso!');
    }

    public function update(Request $request, ConsultaRotinas $consultaRotinas, string $grupo): RedirectResponse
    {
        DB::transaction(function () use ($request, $consultaRotinas, $grupo): void {
            $consulta = $consultaRotinas->grupoDaRotina($consultaRotinas->cadastrosDeRotinas($request->user()), $grupo);
            $tarefas = (clone $consulta)->orderBy('id_rotina')->lockForUpdate()->get(['id_rotina', 'id_empresa', 'id_usuario', 'status']);
            abort_if($tarefas->isEmpty(), 404);

            $dados = $request->validate([
                'colaborador_id' => ['bail', 'required', 'integer', Rule::exists('empresa_usuario', 'id_usuario')
                    ->where('id_empresa', $tarefas->first()->id_empresa)],
                'status' => ['required', 'in:0,1'],
            ], [
                'colaborador_id.required' => 'Selecione um colaborador.',
                'colaborador_id.exists' => 'Selecione um colaborador vinculado à unidade.',
                'status.required' => 'Selecione a situação da rotina.',
            ]);

            $responsavel = (int) $dados['colaborador_id'];
            $ativa = $request->boolean('status');
            $alteradas = $tarefas->filter(fn (object $tarefa): bool => (int) $tarefa->id_usuario !== $responsavel || (bool) $tarefa->status !== $ativa);
            if ($alteradas->isEmpty()) {
                return;
            }

            $agora = now('UTC')->toImmutable();
            DB::table('rotinas')->whereIntegerInRaw('id_rotina', $alteradas->pluck('id_rotina')->all())
                ->update(['id_usuario' => $responsavel, 'status' => $ativa, 'updated_at' => $agora]);

            $reatribuidas = $alteradas->filter(fn (object $tarefa): bool => (int) $tarefa->id_usuario !== $responsavel);
            if ($reatribuidas->isNotEmpty()) {
                $this->execucoes->reatribuirPendentes($reatribuidas->pluck('id_rotina')->all(), $responsavel, $agora);
            }

            foreach ($alteradas as $tarefa) {
                AuditoriaService::registrar(
                    'UPDATE', 'rotinas', (string) $tarefa->id_rotina,
                    ['id_usuario' => $tarefa->id_usuario, 'status' => (bool) $tarefa->status],
                    ['id_usuario' => $responsavel, 'status' => $ativa],
                    'Colaborador ou situação da rotina atualizados',
                    idEmpresa: (int) $tarefa->id_empresa,
                    idRotina: (int) $tarefa->id_rotina,
                );
            }
        }, 3);

        return to_route('relatorios.rotinas')->with('success', 'Rotina atualizada com sucesso.');
    }

    public function atualizarTarefa(Request $request, ConsultaRotinas $catalogo, string $grupo, int $tarefa): RedirectResponse
    {
        DB::transaction(function () use ($request, $catalogo, $grupo, $tarefa): void {
            $registro = $catalogo->grupoDaRotina($catalogo->cadastrosDeRotinas($request->user()), $grupo)
                ->where('r.id_rotina', $tarefa)->lockForUpdate()->first();
            abort_unless($registro, 404);
            $unidade = (int) $registro->id_empresa;
            [$alteracoes, $auxiliares] = $this->dadosDaTarefa($request, $catalogo, $unidade, (int) $registro->id_usuario, $registro);
            $vinculos = DB::table('rotina_auxiliares')->where('id_rotina', $tarefa);
            $auxiliaresAnteriores = $vinculos->orderBy('id_usuario')->pluck('id_usuario')->map(fn ($id): int => (int) $id)->all();
            $anteriores = array_intersect_key((array) $registro, $alteracoes);
            $anteriores['horario'] = substr($registro->horario, 0, 8);
            $anteriores['id_tarefa'] = (int) $registro->id_tarefa;
            $anteriores['id_local'] = $registro->id_local === null ? null : (int) $registro->id_local;
            $anteriores['auxilio'] = (bool) $registro->auxilio;

            if ($alteracoes == $anteriores && $auxiliares === $auxiliaresAnteriores) {
                return;
            }
            DB::table('rotinas')->where('id_rotina', $tarefa)->update([...$alteracoes, 'updated_at' => now('UTC')]);
            if ($auxiliares !== $auxiliaresAnteriores) {
                DB::table('rotina_auxiliares')->where('id_rotina', $tarefa)->whereIntegerInRaw('id_usuario', array_diff($auxiliaresAnteriores, $auxiliares))->delete();
                foreach (array_diff($auxiliares, $auxiliaresAnteriores) as $auxiliar) {
                    DB::table('rotina_auxiliares')->insert(['id_rotina' => $tarefa, 'id_usuario' => $auxiliar]);
                }
            }

            AuditoriaService::registrar(
                'UPDATE', 'rotinas', (string) $tarefa,
                [...$anteriores, 'auxiliares' => $auxiliaresAnteriores], [...$alteracoes, 'auxiliares' => $auxiliares],
                'Tarefa da rotina editada', idEmpresa: $unidade, idRotina: $tarefa,
            );
        }, 3);

        return to_route('relatorios.rotinas', ['editar' => $grupo])->with('success', 'Tarefa da rotina atualizada com sucesso.');
    }

    public function adicionarTarefa(Request $request, ConsultaRotinas $catalogo, string $grupo): RedirectResponse
    {
        $grupoRotina = DB::transaction(function () use ($request, $catalogo, $grupo): string {
            $tarefasDaRotina = $catalogo->grupoDaRotina($catalogo->cadastrosDeRotinas($request->user()), $grupo)
                ->orderBy('r.id_rotina')->lockForUpdate()->get(['r.id_rotina', 'r.id_empresa', 'r.id_usuario', 'r.grupo_rotina', 'r.status']);
            abort_if($tarefasDaRotina->isEmpty(), 404);

            $rotina = $tarefasDaRotina->first();
            $unidade = (int) $rotina->id_empresa;
            $responsavel = (int) $rotina->id_usuario;
            [$dados, $auxiliares] = $this->dadosDaTarefa($request, $catalogo, $unidade, $responsavel);
            $agora = now('UTC')->toImmutable();
            $grupoRotina = $rotina->grupo_rotina ?? (string) Str::uuid();
            $ativa = $tarefasDaRotina->every(fn (object $tarefa): bool => (bool) $tarefa->status);

            if ($rotina->grupo_rotina === null) {
                DB::table('rotinas')->where('id_rotina', $rotina->id_rotina)->update(['grupo_rotina' => $grupoRotina]);
            }

            $novaTarefa = [
                ...$dados,
                'id_empresa' => $unidade,
                'id_usuario' => $responsavel,
                'grupo_rotina' => $grupoRotina,
                'tipo_rotina' => ExecucoesDiarias::TIPO_DIARIA,
                'status' => $ativa,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
            $id = (int) DB::table('rotinas')->insertGetId($novaTarefa, 'id_rotina');
            if ($auxiliares !== []) {
                DB::table('rotina_auxiliares')->insert(array_map(fn (int $auxiliar): array => [
                    'id_rotina' => $id,
                    'id_usuario' => $auxiliar,
                ], $auxiliares));
            }

            AuditoriaService::registrar(
                'CREATE', 'rotinas', (string) $id,
                dadosNovos: [...Arr::except($novaTarefa, ['created_at', 'updated_at']), 'auxiliares' => $auxiliares],
                descricao: 'Tarefa adicionada à rotina', idEmpresa: $unidade, idRotina: $id,
            );

            if ($ativa) {
                $this->execucoes->gerar($agora, idsRotinas: [$id]);
            }

            return $grupoRotina;
        }, 3);

        return to_route('relatorios.rotinas', ['editar' => $grupoRotina])->with('success', 'Tarefa adicionada à rotina com sucesso.');
    }

    public function excluir(Request $request, ConsultaRotinas $consultaRotinas, string $grupo, ?int $tarefa = null): RedirectResponse
    {
        $grupoRestante = DB::transaction(function () use ($request, $consultaRotinas, $grupo, $tarefa): bool {
            $rotina = $consultaRotinas->grupoDaRotina($consultaRotinas->cadastrosDeRotinas($request->user()), $grupo);
            $consulta = (clone $rotina)->when($tarefa !== null, fn ($rotinas) => $rotinas->where('r.id_rotina', $tarefa));
            $tarefas = (clone $consulta)->orderBy('id_rotina')->lockForUpdate()->get(['id_rotina', 'id_empresa']);
            abort_if($tarefas->isEmpty(), 404);

            $agora = now('UTC');
            DB::table('rotinas')->whereIntegerInRaw('id_rotina', $tarefas->pluck('id_rotina')->all())
                ->update(['excluida_em' => $agora, 'updated_at' => $agora]);

            foreach ($tarefas as $registro) {
                AuditoriaService::registrar(
                    'DELETE', 'rotinas', (string) $registro->id_rotina,
                    ['excluida_em' => null], ['excluida_em' => $agora],
                    $tarefa === null ? 'Rotina excluída do relatório' : 'Tarefa removida da rotina',
                    idEmpresa: (int) $registro->id_empresa,
                    idRotina: (int) $registro->id_rotina,
                );
            }

            return $tarefa !== null && $rotina->exists();
        }, 3);

        return to_route('relatorios.rotinas', $grupoRestante ? ['editar' => $grupo] : [])
            ->with('success', $tarefa === null ? 'Rotina excluída com sucesso.' : 'Tarefa removida da rotina.');
    }

    /** @return array{array{dias_semanas: string, horario: string, id_tarefa: int, id_local: ?int, local: ?string, morador: ?string, auxilio: bool, obs: ?string}, list<int>} */
    private function dadosDaTarefa(Request $request, ConsultaRotinas $catalogo, int $unidade, int $responsavel, ?object $registro = null): array
    {
        $dados = $request->validate([
            'dias' => ['required', 'array', 'list', 'max:7'],
            'dias.*' => ['required', 'integer', 'distinct', 'between:0,6'],
            'horario' => ['required', 'date_format:H:i,H:i:s'],
            'id_tarefa' => ['required', 'integer', 'min:1'],
            'id_local' => ['exclude_with:local_morador', Rule::requiredIf($registro === null || $registro->id_local !== null), 'nullable', 'integer', 'min:1'],
            'local_morador' => ['nullable', 'string', 'max:255'],
            'morador' => ['nullable', 'string', 'max:255'],
            'auxilio' => ['required', 'in:0,1'],
            'auxiliares' => ['exclude_unless:auxilio,1', 'nullable', 'array', 'list'],
            'auxiliares.*' => ['exclude_unless:auxilio,1', 'integer', 'distinct', Rule::notIn([$responsavel]),
                Rule::exists('empresa_usuario', 'id_usuario')->where('id_empresa', $unidade)],
            'observacao' => ['nullable', 'string'],
        ], [
            'dias.required' => 'Selecione pelo menos um dia da semana.',
            'horario.required' => 'Informe o horário da tarefa.',
            'horario.date_format' => 'Informe um horário válido.',
            'id_tarefa.required' => 'Selecione uma tarefa.',
            'id_local.required' => 'Selecione um local.',
            'auxiliares.*.exists' => 'Selecione auxiliares vinculados à unidade.',
            'auxiliares.*.not_in' => 'O responsável da rotina já está vinculado à tarefa.',
        ]);

        $idTarefa = (int) $dados['id_tarefa'];
        if ($idTarefa !== (int) ($registro?->id_tarefa ?? 0)
            && ! $catalogo->catalogoNaUnidade('tarefas', $unidade)->where('c.id_tarefa', $idTarefa)->lockForUpdate()->value('c.id_tarefa')) {
            throw ValidationException::withMessages(['id_tarefa' => 'Selecione uma tarefa ativa da unidade da rotina.']);
        }

        $idLocal = isset($dados['id_local']) ? (int) $dados['id_local'] : null;
        $localAtual = isset($registro->id_local) ? (int) $registro->id_local : null;
        $local = $registro?->local;
        $localMorador = $this->localDoMorador($dados);
        if ($localMorador !== null) {
            $local = $localMorador;
        } elseif ($idLocal !== $localAtual) {
            $local = $catalogo->catalogoNaUnidade('locais', $unidade)->where('c.id_local', $idLocal)->lockForUpdate()->value('c.local');
            if ($local === null) {
                throw ValidationException::withMessages(['id_local' => 'Selecione um local ativo da unidade da rotina.']);
            }
        }

        $dias = collect($dados['dias'])->map(fn ($dia): int => (int) $dia)->sort()->values()->all();
        $auxiliares = collect($dados['auxiliares'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all();

        return [[
            'dias_semanas' => $registro && $dias === $this->execucoes->diasDaSemana($registro->dias_semanas)
                ? $registro->dias_semanas : implode(', ', array_map(fn (int $dia): string => ExecucoesDiarias::DIAS_DA_SEMANA[$dia], $dias)),
            'horario' => strlen($dados['horario']) === 5 ? $dados['horario'].':00' : $dados['horario'],
            'id_tarefa' => $idTarefa,
            'id_local' => $idLocal,
            'local' => $local,
            'morador' => $dados['morador'] ?? null,
            'auxilio' => $request->boolean('auxilio'),
            'obs' => $dados['observacao'] ?? null,
        ], $auxiliares];
    }

    /**
     * Apartamento do morador escolhido como local, que não possui cadastro em locais.
     *
     * @param  array{local_morador?: ?string, morador?: ?string}  $dados
     */
    private function localDoMorador(array $dados, string $prefixo = ''): ?string
    {
        $local = trim((string) ($dados['local_morador'] ?? ''));
        if ($local === '') {
            return null;
        }

        $morador = trim((string) ($dados['morador'] ?? ''));
        if ($morador === '' || $morador === 'Nenhum (Tarefa Geral)') {
            $campo = $prefixo === '' ? 'id_local' : "$prefixo.id_local";
            throw ValidationException::withMessages([$campo => 'Selecione o(a) morador(a) para usar o apartamento como local.']);
        }

        return $local;
    }
}
