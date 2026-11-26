<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExecucoesDiariasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cadastro_gera_execucao_e_estatisticas_exibem_pendentes_junto_com_finalizadas_filtradas(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 11:59:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $tarefa = $this->tarefa('Organizar o salão', '00:01:30');
        $local = DB::table('locais')->insertGetId(['local' => 'Salão', 'status' => true], 'id_local');

        DB::table('tarefas_empresas')->insert(['id_tarefa' => $tarefa, 'id_empresa' => $unidade->getKey()]);
        DB::table('locais_empresas')->insert(['id_local' => $local, 'id_empresa' => $unidade->getKey()]);

        $this->actingAs($usuario)->post(route('rotinas.store'), [
            'id_empresa' => $unidade->getKey(),
            'colaborador_id' => $usuario->getKey(),
            'tarefas_json' => json_encode([[
                'dias' => 'Quinta-feira, Domingo',
                'horario' => '09:00',
                'id_tarefa' => $tarefa,
                'id_local' => $local,
                'auxilio' => 'Não',
            ]], JSON_THROW_ON_ERROR),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_empresa' => $unidade->getKey(),
            'id_usuario_responsavel' => $usuario->getKey(),
            'previsto_em' => '2026-11-19 12:00:00',
            'prazo_em' => '2026-11-19 12:01:00',
            'duracao_prevista_segundos' => 90,
            'tempo_limite_minutos' => 2,
            'status' => 'pendente',
        ]);
        $this->get(route('relatorios.estatisticas', [
            'detalhe' => 'usuario-'.$usuario->getKey(),
            'finalizados' => 1,
            'periodo_fim' => 'personalizado',
            'fim_de' => '2026-11-19',
        ]))->assertOk()->assertSeeText('Organizar o salão')
            ->assertViewHas('rotinasDetalhe', fn ($registros) => $registros->sole()->situacao === 'expirando');
        $this->assertDatabaseCount('rotinas_execucoes', 1);
    }

    public function test_consulta_agenda_horarios_em_dias_civis_distintos_sem_duplicar_e_respeita_a_unidade(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 02:59:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $outraUnidade = Empresa::create(['empresa' => 'Unidade Norte']);
        $usuario = $this->usuario('ana', $unidade);
        $outroUsuario = $this->usuario('bia', $outraUnidade);
        $tarefa = $this->tarefa('Fechar o salão');
        $quinta = $this->rotina($usuario, $unidade, $tarefa, '23:55:00', 'Qui');
        $sexta = $this->rotina($usuario, $unidade, $tarefa, '00:05:00', 'Sexta-feira');
        $this->rotina($outroUsuario, $outraUnidade, $tarefa, '23:55:00', 'Quinta-feira');
        $this->rotina($usuario, $unidade, $tarefa, '23:55:00', 'Quinta-feira', ativa: false);

        $this->actingAs($usuario)->get(route('relatorios.estatisticas'))->assertOk();

        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $quinta, 'previsto_em' => '2026-11-20 02:55:00']);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $sexta, 'previsto_em' => '2026-11-20 03:05:00']);
        $this->assertDatabaseCount('rotinas_execucoes', 2);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->get(route('monitoramento.index'))->assertOk();

        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $sexta, 'previsto_em' => '2026-11-20 03:05:00']);
        $this->assertDatabaseCount('rotinas_execucoes', 2);
    }

    public function test_gera_toda_a_agenda_uma_hora_antes_da_primeira_tarefa_do_colaborador(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 06:59:59', 'America/Sao_Paulo'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $maria = $this->usuario('maria', $unidade);
        $colega = $this->usuario('bianca', $unidade);
        $this->rotina($maria, $unidade, $this->tarefa('Banho'), '12:00:00');
        $this->rotina($maria, $unidade, $this->tarefa('Fim de plantão'), '16:00:00');
        $this->rotina($maria, $unidade, $this->tarefa('Medicação'), '08:00:00');
        $this->rotina($colega, $unidade, $this->tarefa('Conferência'), '09:00:00');

        $this->actingAs($maria)->get(route('home'))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->isEmpty());
        $this->assertDatabaseEmpty('rotinas_execucoes');

        $this->travel(1)->second();
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $this->get(route('home'))->assertOk()
            ->assertSeeTextInOrder(['Medicação', '08:00', 'Banho', '12:00', 'Fim de plantão', '16:00']);
        $this->get(route('home'), ['X-Painel-Rotinas-Diarias' => '1', 'X-Atualizacao-Automatica' => '1'])
            ->assertOk()->assertSee('listaRotinas')->assertDontSee('<!DOCTYPE html>', false)
            ->assertSeeTextInOrder(['Medicação', 'Banho', 'Fim de plantão']);
        $this->assertDatabaseCount('rotinas_execucoes', 3);
        $this->assertDatabaseMissing('rotinas_execucoes', ['id_usuario_responsavel' => $colega->getKey()]);
    }

    public function test_plantao_noturno_entra_inteiro_na_agenda_e_continua_apos_a_meia_noite(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 20:59:59', 'America/Sao_Paulo'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $joao = $this->usuario('joao', $unidade);
        $receber = $this->tarefa('Receber plantão');
        $ronda = $this->tarefa('Ronda');
        $medicacao = $this->tarefa('Medicação');
        $fim = $this->tarefa('Fim de plantão');

        foreach ([[$ronda, '00:00:00'], [$fim, '06:00:00'], [$receber, '22:00:00'], [$medicacao, '02:00:00'], [$medicacao, '23:00:00']] as [$tarefa, $horario]) {
            $this->rotina($joao, $unidade, $tarefa, $horario, 'Qui, Sex');
        }

        $this->actingAs($joao)->get(route('home'))->assertOk();
        $this->assertDatabaseEmpty('rotinas_execucoes');

        $this->travel(1)->second();
        $this->get(route('home'))->assertOk()->assertSeeTextInOrder([
            'Receber plantão', '22:00', 'Medicação', '23:00', 'Ronda', '00:00 · 20/11',
            'Medicação', '02:00 · 20/11', 'Fim de plantão', '06:00 · 20/11',
        ]);
        $this->assertSame([
            '2026-11-20 01:00:00', '2026-11-20 02:00:00', '2026-11-20 03:00:00', '2026-11-20 05:00:00', '2026-11-20 09:00:00',
        ], DB::table('rotinas_execucoes')->orderBy('previsto_em')->pluck('previsto_em')->all());
        $this->assertDatabaseHas('rotinas_execucoes', ['previsto_em' => '2026-11-20 09:00:00', 'prazo_em' => '2026-11-20 09:01:00']);

        $this->travelTo(CarbonImmutable::parse('2026-11-19 22:00:00', 'America/Sao_Paulo'));
        $recepcao = DB::table('rotinas_execucoes')->where('previsto_em', '2026-11-20 01:00:00')->value('id_execucao');
        $this->post(route('execucoes.iniciar', $recepcao))->assertSessionHasNoErrors();
        $this->post(route('execucoes.concluir', $recepcao), ['comentario' => 'Plantão recebido.'])->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 02:00:00', 'America/Sao_Paulo'));
        $this->get(route('monitoramento.index'))->assertOk()->assertViewHas('execucoes', fn ($execucoes) => $execucoes->count() === 4
            && ! $execucoes->contains('id_execucao', $recepcao));
        $concluidas = route('home', ['aba' => 'concluida']);
        $this->get($concluidas)->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 5
                && $rotinas->where('status', 'concluida')->sole()->id_execucao === $recepcao)
            ->assertSeeTextInOrder(['22:00 · 19/11/2026', 'Concluída em', '19/11/2026 22:00']);
        $this->assertDatabaseCount('rotinas_execucoes', 5);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00:00', 'America/Sao_Paulo'));
        $this->get($concluidas)->assertOk()->assertViewHas('rotinas', fn ($rotinas) => $rotinas->where('status', 'pendente')->count() === 9
            && $rotinas->where('status', 'concluida')->isEmpty());
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $recepcao, 'status' => 'concluida']);
        $this->assertDatabaseCount('rotinas_execucoes', 10);

        $rondaAtrasada = DB::table('rotinas_execucoes')->where('previsto_em', '2026-11-20 03:00:00')->value('id_execucao');
        $this->post(route('execucoes.iniciar', $rondaAtrasada))->assertSessionHasNoErrors();
        $this->post(route('execucoes.concluir', $rondaAtrasada), ['comentario' => 'Ronda concluída neste plantão.'])->assertSessionHasNoErrors();

        DB::table('rotinas_execucoes')->where('previsto_em', '2026-11-21 02:00:00')->update([
            'status' => 'concluida', 'iniciado_em' => '2026-11-20 23:59:00', 'concluido_em' => '2026-11-20 23:59:59',
        ]);

        $this->get($concluidas)->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->where('status', 'concluida')->sole()->id_execucao === $rondaAtrasada)
            ->assertSeeTextInOrder(['00:00 · 20/11/2026', 'Concluída em', '20/11/2026 21:00']);
    }

    public function test_home_preserva_rotinas_abertas_apos_a_virada_do_dia_ate_o_encerramento(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-23 23:59:59', ExecucoesDiarias::FUSO));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $colega = $this->usuario('bia', $unidade);
        $pendente = $this->rotina($usuario, $unidade, $this->tarefa('Organizar o salão'), '09:00:00', 'Seg');
        $emAndamento = $this->rotina($usuario, $unidade, $this->tarefa('Conferir as chaves'), '10:00:00', 'Seg');
        $this->rotina($colega, $unidade, $this->tarefa('Tarefa de outro colaborador'), '11:00:00', 'Seg');
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $ids = DB::table('rotinas_execucoes')->pluck('id_execucao', 'id_rotina');

        $this->actingAs($usuario)->post(route('execucoes.iniciar', $ids[$emAndamento]))->assertSessionHasNoErrors();
        DB::table('rotinas_execucoes')->where('id_execucao', $ids[$emAndamento])->update(['agenda_ate' => null]);
        $this->travel(1)->second();

        $this->get(route('home', ['aba' => 'todas']))->assertOk()
            ->assertSeeTextInOrder(['Organizar o salão', '09:00 · 23/11/2026', 'Em atraso', 'Conferir as chaves'])
            ->assertDontSeeText('Tarefa de outro colaborador')
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->pluck('status', 'id_execucao')->all() === [
                $ids[$pendente] => 'pendente',
                $ids[$emAndamento] => 'em_andamento',
            ]);
        $this->get(route('home'), ['X-Painel-Rotinas-Diarias' => '1'])->assertOk()
            ->assertSeeText('Organizar o salão');

        $this->post(route('execucoes.iniciar', $ids[$pendente]), ['fluxo_diario' => '1'])
            ->assertRedirectToRoute('execucoes.realizando', $ids[$pendente]);
        $this->post(route('execucoes.concluir', $ids[$pendente]), ['comentario' => 'Salão organizado.'])->assertSessionHasNoErrors();

        $this->get(route('home'))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->whereIn('status', ['pendente', 'em_andamento'])
                ->sole()->id_execucao === $ids[$emAndamento]);
    }

    public function test_concluidas_inclui_agenda_liberada_antes_do_previsto_e_emergencial_anterior_a_liberacao(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-23 23:50:00', ExecucoesDiarias::FUSO));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $rotina = $this->rotina($usuario, $unidade, $this->tarefa('Medicação diária'), '02:50:00', 'Seg, Ter');
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $anterior = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->first();
        $this->actingAs($usuario)->post(route('execucoes.iniciar', $anterior->id_execucao))->assertSessionHasNoErrors();
        $this->post(route('execucoes.concluir', $anterior->id_execucao), ['comentario' => 'Concluída na jornada anterior.'])
            ->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-24 00:03:00', ExecucoesDiarias::FUSO));
        $emergencial = $this->rotina($usuario, $unidade, $this->tarefa('Atendimento urgente'), '00:03:00', 'Ter');
        DB::table('rotinas')->where('id_rotina', $emergencial)->update([
            'tipo_rotina' => ExecucoesDiarias::TIPO_EMERGENCIAL, 'agendada_em' => '2026-11-24 03:03:00',
        ]);
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $idEmergencial = DB::table('rotinas_execucoes')->where('id_rotina', $emergencial)->value('id_execucao');
        $this->post(route('execucoes.iniciar', $idEmergencial))->assertSessionHasNoErrors();
        $this->post(route('execucoes.concluir', $idEmergencial), ['comentario' => 'Atendimento realizado.'])
            ->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-24 00:10:00', ExecucoesDiarias::FUSO));
        $idAtual = DB::table('rotinas_execucoes')->insertGetId([
            'id_rotina' => $rotina,
            'id_sla' => $anterior->id_sla,
            'id_empresa' => $unidade->getKey(),
            'id_usuario_responsavel' => $usuario->getKey(),
            'previsto_em' => '2026-11-24 05:50:00',
            'prazo_em' => '2026-11-24 05:51:00',
            'agenda_ate' => '2026-11-25 03:00:00',
            'tempo_limite_minutos' => 30,
            'duracao_prevista_segundos' => 1800,
            'status' => 'pendente',
            'created_at' => '2026-11-24 03:08:00',
        ], 'id_execucao');
        $this->post(route('execucoes.iniciar', $idAtual))->assertSessionHasNoErrors();
        $this->post(route('execucoes.concluir', $idAtual), ['comentario' => 'Medicação administrada.'])
            ->assertSessionHasNoErrors();

        $this->get(route('home', ['aba' => 'concluida']))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->where('status', 'concluida')->pluck('id_execucao')->all() === [$idEmergencial, $idAtual])
            ->assertSeeTextInOrder(['Atendimento urgente', '24/11/2026 00:03', 'Medicação diária', '24/11/2026 00:10']);
    }

    public function test_agenda_com_primeira_tarefa_a_meia_noite_e_liberada_no_dia_anterior(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 23:00:00', 'America/Sao_Paulo'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $this->rotina($usuario, $unidade, $this->tarefa('Ronda'), '00:00:00', 'Sex');
        $this->rotina($usuario, $unidade, $this->tarefa('Hidratação'), '06:00:00', 'Sex');

        $this->actingAs($usuario)->get(route('home'))->assertOk()
            ->assertSeeTextInOrder(['Ronda', '00:00 · 20/11', 'Hidratação', '06:00 · 20/11']);
        $this->assertDatabaseCount('rotinas_execucoes', 2);
    }

    public function test_detalhes_das_rotinas_diarias_mostram_os_colaboradores_auxiliares_da_tarefa(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:00:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $responsavel = $this->usuario('ana', $unidade);
        $rita = $this->usuario('rita', $unidade);
        $beatriz = $this->usuario('beatriz', $unidade);
        $rotina = $this->rotina($responsavel, $unidade, $this->tarefa('Acompanhar a refeição'), '09:00:00');
        $outraRotina = $this->rotina($responsavel, $unidade, $this->tarefa('Organizar a sala'), '10:00:00');
        DB::table('rotinas')->where('id_rotina', $rotina)->update(['auxilio' => true]);
        DB::table('rotina_auxiliares')->insert([
            ['id_rotina' => $rotina, 'id_usuario' => $rita->getKey()],
            ['id_rotina' => $rotina, 'id_usuario' => $beatriz->getKey()],
        ]);

        $this->actingAs($responsavel)->get(route('home'))->assertOk()
            ->assertSeeText('Auxílio previsto para esta tarefa de:')
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->firstWhere('id_rotina', $rotina)->auxiliares === ['Beatriz', 'Rita']
                && $rotinas->firstWhere('id_rotina', $outraRotina)->auxiliares === []);
        $this->get(route('home'), ['X-Painel-Rotinas-Diarias' => '1'])->assertOk()
            ->assertSeeTextInOrder(['Acompanhar a refeição', 'Auxílio:', 'Sim - Beatriz; Rita', 'Organizar a sala', 'Auxílio:', 'Não']);

        $execucao = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->value('id_execucao');
        $this->post(route('execucoes.iniciar', $execucao), ['fluxo_diario' => '1'])->assertRedirectToRoute('execucoes.realizando', $execucao);
        $this->get(route('execucoes.realizando', $execucao))->assertOk()
            ->assertSeeTextInOrder(['Auxílio previsto para esta tarefa de:', 'Beatriz, Rita']);
    }

    public function test_central_calcula_no_prazo_expirando_e_atrasada_com_as_execucoes_do_dia(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $tarefa = $this->tarefa('Verificar o salão');
        foreach (['08:58:00', '09:02:00', '10:00:00'] as $horario) {
            $this->rotina($usuario, $unidade, $tarefa, $horario);
        }

        $this->actingAs($usuario)->get(route('monitoramento.index'))->assertOk()
            ->assertViewHas('situacoes', fn ($situacoes) => $situacoes->sortKeys()->all() === [
                'atrasado' => 1, 'expirando' => 1, 'noPrazo' => 1,
            ])
            ->assertSeeText('Verificar o salão')
            ->assertSeeTextInOrder(['Produtividade', 'Ana', 'Atrasada', '1', 'Expirando', '1', 'No prazo', '1', '0 de 3 concluídas']);
    }

    public function test_monitoramento_conta_abertas_anteriores_e_filtra_pelo_status_e_pelo_sla(): void
    {
        $noite = CarbonImmutable::parse('2026-11-19 22:00:00', ExecucoesDiarias::FUSO);
        $this->travelTo($noite);
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $ana = $this->usuario('ana', $unidade);
        $beatriz = $this->usuario('beatriz', $unidade);
        $rita = $this->usuario('rita', $unidade);
        $pendente = $this->rotina($ana, $unidade, $this->tarefa('Pendente de ontem'), '22:00:00');
        $andamento = $this->rotina($beatriz, $unidade, $this->tarefa('Em execução desde ontem'), '22:00:00');
        $encerradaOntem = $this->rotina($ana, $unidade, $this->tarefa('Concluída ontem'), '22:00:00');
        $encerradaHoje = $this->rotina($ana, $unidade, $this->tarefa('Concluída depois da virada'), '22:00:00');
        $naoRealizada = $this->rotina($rita, $unidade, $this->tarefa('Impedida depois da virada'), '22:00:00');

        $this->actingAs($ana)->get(route('monitoramento.index'))->assertOk();
        DB::table('rotinas_execucoes')->whereIn('id_rotina', [$andamento, $encerradaOntem, $encerradaHoje, $naoRealizada])
            ->update(['status' => 'em_andamento', 'iniciado_em' => $noite->utc(), 'prazo_em' => $noite->addMinutes(30)->utc()]);
        DB::table('rotinas_execucoes')->where('id_rotina', $encerradaOntem)
            ->update(['status' => 'concluida', 'concluido_em' => $noite->addMinutes(10)->utc()]);
        $hoje = $noite->addHours(4);
        $this->travelTo($hoje);
        DB::table('rotinas_execucoes')->where('id_rotina', $encerradaHoje)->update(['status' => 'concluida', 'concluido_em' => $hoje->utc()]);
        DB::table('rotinas_execucoes')->where('id_rotina', $naoRealizada)->update(['status' => 'nao_realizada', 'concluido_em' => $hoje->utc()]);

        $this->get(route('monitoramento.index'))->assertOk()
            ->assertViewHas('processos', fn ($processos) => $processos->get('diaria')->sortKeys()->all() === [
                'concluida' => 1, 'em_andamento' => 1, 'nao_realizada' => 1, 'pendente' => 1, 'todas' => 4,
            ])->assertViewHas('execucoes', fn ($itens) => ! $itens->contains('id_rotina', $encerradaOntem));
        $this->get(route('monitoramento.index', ['processo' => 'diaria', 'status' => 'pendente']))->assertOk()
            ->assertViewHas('execucoes', fn ($itens) => $itens->sole()->id_rotina === $pendente)
            ->assertViewHas('situacoes', fn ($situacoes) => $situacoes->all() === ['atrasado' => 1])
            ->assertViewHas('produtividades', fn ($produtividades) => $produtividades->sole()->colaborador_id === $ana->getKey());
        $this->get(route('monitoramento.index', ['sla' => 'atrasado']))->assertOk()
            ->assertViewHas('processos', fn ($processos) => $processos->keys()->all() === ['diaria'] && $processos->get('diaria')->get('todas') === 3)
            ->assertViewHas('produtividades', fn ($produtividades) => $produtividades->map(fn ($prod) => $prod->situacoes_sla->all())->all() === [
                $ana->getKey() => ['atrasado' => 2], $beatriz->getKey() => ['atrasado' => 1],
            ])
            ->assertViewHas('execucoes', fn ($itens) => ! $itens->contains('id_rotina', $naoRealizada));
        $this->get(route('monitoramento.index', ['processo' => 'diaria', 'status' => 'concluida', 'sla' => 'atrasado']))->assertOk()
            ->assertSeeText(['Filtros ativos', '1 de 1 concluídas', 'Concluída depois da virada'])
            ->assertSee(route('monitoramento.index', ['processo' => 'diaria', 'sla' => 'atrasado']))
            ->assertViewHas('execucoes', fn ($itens) => $itens->sole()->id_rotina === $encerradaHoje);
        $this->get(route('monitoramento.index', ['processo' => 'diaria', 'sla' => 'atrasado']))->assertOk()
            ->assertViewHas('execucoes', fn ($itens) => $itens->pluck('id_rotina')->sort()->values()->all() === [$pendente, $andamento, $encerradaHoje]);
    }

    public function test_rotinas_diarias_usam_a_duracao_cadastrada_para_prazo_de_conclusao(): void
    {
        $agora = CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC');
        $this->travelTo($agora);
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $tarefa = $this->tarefa('Preparar o salão', '00:01:30');
        DB::table('tarefas')->where('id_tarefa', $tarefa)->update(['icone' => '🧭', 'cor_icone' => '#f26716']);
        $rotina = $this->rotina($usuario, $unidade, $tarefa, '09:00:00');

        $this->actingAs($usuario)->get(route('home'))->assertOk();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->value('id_execucao');
        $this->from(route('home'))->post(route('execucoes.iniciar', $id), ['fluxo_diario' => '1'])->assertRedirectToRoute('execucoes.realizando', $id);

        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_execucao' => $id, 'status' => 'em_andamento',
            'iniciado_em' => '2026-11-19 12:00:00', 'prazo_em' => '2026-11-19 12:01:30',
        ]);
        $this->get(route('execucoes.realizando', $id))->assertOk()
            ->assertSee('background-color: #f26716;', false)
            ->assertSee(['🧭', 'mix-blend-mode: color;'], false)
            ->assertSeeTextInOrder(['Realizando', 'Quando', '09:00'])
            ->assertDontSee('09:00 –')
            ->assertSeeTextInOrder(['Finalizar Tarefa', 'Não Pode Ser Realizada', 'Solicitar Auxílio']);

        $this->travelTo($agora->addSeconds(45));
        $this->get(route('monitoramento.index'))->assertOk()
            ->assertViewHas('situacoes', fn ($situacoes) => $situacoes->get('expirando') === 1);

        $this->travelTo($agora->addSeconds(95));
        $estatisticas = route('relatorios.estatisticas', ['detalhe' => 'usuario-'.$usuario->getKey()]);
        $this->get($estatisticas)->assertOk()->assertSeeTextInOrder(['Preparar o salão', 'Iniciada'])
            ->assertDontSeeText(['Ver SLA', 'Comentário (obrigatório)'])
            ->assertDontSee('action="'.route('execucoes.concluir', $id).'"', false);

        $formulario = route('execucoes.formulario', [$id, 'finalizar']);
        $this->get($formulario)->assertOk()->assertSeeText('Comentário');
        $this->from($formulario)->post(route('execucoes.concluir', $id))
            ->assertRedirect($formulario)->assertSessionHasErrors(['comentario' => 'Informe um comentário sobre a execução.']);
        $this->from($formulario)->post(route('execucoes.concluir', $id), ['comentario' => 'Salão preparado.', 'fluxo_diario' => 1])
            ->assertRedirectToRoute('home', ['aba' => 'concluida'])->assertSessionHasNoErrors();
        $this->get(route('monitoramento.index'))->assertOk()->assertSeeText('1 de 1 concluídas')
            ->assertViewHas('execucoes', fn ($registros) => $registros->sole()->situacao_sla === 'atrasado');
    }

    public function test_finalizacao_guarda_comentario_e_foto_privada_para_o_responsavel(): void
    {
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $colega = $this->usuario('bia', $unidade);
        $rotina = $this->rotina($usuario, $unidade, $this->tarefa('Higiene pessoal'), '08:55:00');
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->value('id_execucao');
        $formulario = route('execucoes.formulario', [$id, 'finalizar']);

        $this->actingAs($usuario)->get($formulario)->assertNotFound();
        $this->post(route('execucoes.iniciar', $id))->assertSessionHasNoErrors();
        $this->get($formulario)->assertOk()->assertSeeTextInOrder(['Finalizar Tarefa', 'Comentário', '(obrigatório)', 'Confirmar Envio'])
            ->assertSee('name="comentario" rows="5" maxlength="2000" required', false);
        $this->from($formulario)->post(route('execucoes.concluir', $id), ['comentario' => '   '])
            ->assertRedirect($formulario)->assertSessionHasErrors(['comentario' => 'Informe um comentário sobre a execução.']);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $id, 'status' => 'em_andamento', 'comentario_conclusao' => null]);
        $this->actingAs($colega)->get($formulario)->assertNotFound();

        $foto = UploadedFile::fake()->createWithContent('registro.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7ckAAAAASUVORK5CYII='));
        $this->actingAs($usuario)->from($formulario)->post(route('execucoes.concluir', $id), [
            'fluxo_diario' => '1', 'comentario' => 'Assistência registrada.', 'foto' => $foto,
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('home', ['aba' => 'concluida']);

        $caminho = DB::table('rotinas_execucoes')->where('id_execucao', $id)->value('foto_conclusao');
        Storage::disk('local')->assertExists($caminho);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $id, 'status' => 'concluida', 'comentario_conclusao' => 'Assistência Registrada.']);
        $this->get(route('execucoes.foto', $id))->assertDownload();
        $this->actingAs($colega)->get(route('execucoes.foto', $id))->assertNotFound();
    }

    public function test_auxilio_preserva_execucao_e_nao_realizada_encerra_com_justificativa(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $tarefa = $this->tarefa('Acompanhar morador');
        DB::table('tarefas')->where('id_tarefa', $tarefa)->update(['icone' => '🧭', 'cor_icone' => '#8b5cf6']);
        $rotina = $this->rotina($usuario, $unidade, $tarefa, '09:00:00');
        $local = DB::table('locais')->insertGetId(['local' => 'Sala de Estar', 'status' => true], 'id_local');
        DB::table('rotinas')->where('id_rotina', $rotina)->update(['id_local' => $local, 'morador' => 'Morador Exemplo']);
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->value('id_execucao');
        $dadosDaTarefa = ['Acompanhar morador', '09:00', 'Local', 'Sala de Estar', 'Quem', 'Morador Exemplo'];
        $this->actingAs($usuario)->post(route('execucoes.iniciar', $id), ['fluxo_diario' => '1'])
            ->assertRedirectToRoute('execucoes.realizando', $id);
        $this->get(route('execucoes.formulario', [$id, 'auxilio']))->assertOk()
            ->assertSee('background-color: #8b5cf6;', false)
            ->assertSee(['🧭', 'mix-blend-mode: color;'], false)
            ->assertSeeTextInOrder([...$dadosDaTarefa, 'Enviar Solicitação']);
        $this->post(route('execucoes.auxilio', $id))->assertSessionHasErrors(['justificativa' => 'Informe a justificativa.']);
        $this->post(route('execucoes.auxilio', $id), ['justificativa' => 'Preciso de outro colaborador.'])
            ->assertRedirectToRoute('execucoes.realizando', $id);
        $this->assertDatabaseHas('logs_auditoria', ['registro_id' => (string) $id, 'acao' => 'SOLICITAR_AUXILIO', 'id_empresa' => $unidade->getKey()]);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $id, 'status' => 'em_andamento', 'concluido_em' => null]);

        $formulario = route('execucoes.formulario', [$id, 'nao-realizada']);
        $this->get($formulario)->assertOk()
            ->assertSee('background-color: #8b5cf6;', false)
            ->assertSee(['🧭', 'mix-blend-mode: color;'], false)
            ->assertSeeTextInOrder([...$dadosDaTarefa, 'Confirmar Motivo']);
        $this->from($formulario)->post(route('execucoes.nao-realizada', $id))->assertSessionHasErrors('justificativa');
        $this->post(route('execucoes.nao-realizada', $id), ['justificativa' => 'Morador ausente.'])
            ->assertRedirectToRoute('home', ['aba' => 'todas']);
        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_execucao' => $id, 'status' => 'nao_realizada', 'justificativa_nao_realizada' => 'Morador Ausente.',
        ]);
        $this->get(route('home', ['aba' => 'todas']))->assertOk()->assertSeeText('Não realizada');
        $this->get(route('home', ['aba' => 'concluida']))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->where('status', 'concluida')->isEmpty());
        $this->get(route('monitoramento.index'))->assertOk()->assertSeeText(['Não realizadas', '0 de 1 concluídas']);
        $this->get($formulario)->assertNotFound();
    }

    public function test_reatribuicao_atualiza_o_responsavel_pendente_e_preserva_a_execucao_iniciada(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $novo = $this->usuario('bia', $unidade);
        $tarefa = $this->tarefa('Preparar o salão');
        $pendente = $this->rotina($usuario, $unidade, $tarefa, '09:00:00');
        $emAndamento = $this->rotina($usuario, $unidade, $tarefa, '09:10:00');
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $emAndamento)->value('id_execucao');

        $this->actingAs($usuario)->post(route('execucoes.iniciar', $id))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('rotinas.atribuir'), [
            'rotinas_ids' => [$pendente, $emAndamento],
            'novo_colaborador_id' => $novo->getKey(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $pendente, 'id_usuario_responsavel' => $novo->getKey()]);
        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_rotina' => $emAndamento, 'id_usuario_responsavel' => $usuario->getKey(), 'status' => 'em_andamento',
        ]);
    }

    public function test_popup_do_monitoramento_lista_e_atribui_somente_tarefas_do_processo_selecionado(): void
    {
        $agora = CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC');
        $this->travelTo($agora);
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $responsavel = $this->usuario('ana', $unidade);
        $destino = $this->usuario('bia', $unidade);
        $externo = $this->usuario('carlos', Empresa::create(['empresa' => 'Outra Unidade']));
        $diaria = $this->rotina($responsavel, $unidade, $this->tarefa('Ronda da Manhã'), '09:00:00');
        $outra = $this->rotina($responsavel, $unidade, $this->tarefa('Tarefa Encerrada'), '09:10:00');
        $emergencial = $this->rotina($responsavel, $unidade, $this->tarefa('Atendimento Urgente'), '09:00:00');
        DB::table('rotinas')->where('id_rotina', $emergencial)->update([
            'tipo_rotina' => ExecucoesDiarias::TIPO_EMERGENCIAL, 'agendada_em' => $agora,
        ]);
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $execucoes = DB::table('rotinas_execucoes')->get()->keyBy('id_rotina');
        $idDiaria = $execucoes[$diaria]->id_execucao;
        $idEncerrada = $execucoes[$outra]->id_execucao;
        $idEmergencial = $execucoes[$emergencial]->id_execucao;
        $ocorrenciaAnterior = (array) $execucoes[$diaria];
        unset($ocorrenciaAnterior['id_execucao']);
        $idAnterior = DB::table('rotinas_execucoes')->insertGetId([
            ...$ocorrenciaAnterior,
            'previsto_em' => $agora->subDay(), 'agenda_ate' => $agora->subHours(3), 'prazo_em' => $agora->subHours(23),
        ], 'id_execucao');
        DB::table('rotinas_execucoes')->where('id_execucao', $idEncerrada)->update([
            'status' => 'concluida', 'iniciado_em' => $agora->subMinutes(5), 'concluido_em' => $agora,
        ]);
        $this->actingAs($responsavel)->post(route('execucoes.iniciar', $idDiaria))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('execucoes.iniciar', $idEmergencial))->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('monitoramento.processo', 'diaria'))
            ->assertOk()->assertSeeText(['Ronda da Manhã', 'Selecionar todas'])->assertDontSeeText(['Atendimento Urgente', 'Tarefa Encerrada'])
            ->assertViewHas('tarefas', fn ($tarefas) => $tarefas->pluck('status')->all() === ['pendente', 'em_andamento']);
        $this->get(route('monitoramento.processo', 'emergencial'))
            ->assertOk()->assertSeeText(['Atendimento Urgente', 'Em andamento'])->assertDontSeeText('Ronda da Manhã');
        $this->actingAs($externo)->get(route('monitoramento.processo', 'diaria'))
            ->assertOk()->assertDontSeeText('Ronda da Manhã');

        $dados = ['processo' => 'diaria', 'novo_colaborador_id' => $destino->getKey()];
        $this->postJson(route('rotinas.atribuir'), [...$dados, 'execucoes_ids' => [$idAnterior]])
            ->assertUnprocessable()->assertJsonValidationErrors('execucoes_ids');
        $this->actingAs($responsavel)->postJson(route('rotinas.atribuir'), [...$dados, 'execucoes_ids' => [$idDiaria, $idEmergencial]])
            ->assertUnprocessable()->assertJsonValidationErrors('execucoes_ids');
        $this->postJson(route('rotinas.atribuir'), [...$dados, 'execucoes_ids' => [$idAnterior, $idEncerrada]])
            ->assertUnprocessable()->assertJsonValidationErrors('execucoes_ids');
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $idAnterior, 'id_usuario_responsavel' => $responsavel->getKey()]);

        $this->postJson(route('rotinas.atribuir'), [...$dados, 'execucoes_ids' => [$idAnterior]])->assertOk();
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $idAnterior, 'id_usuario_responsavel' => $destino->getKey(), 'status' => 'pendente']);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $idDiaria, 'id_usuario_responsavel' => $responsavel->getKey()]);
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $diaria, 'id_usuario' => $responsavel->getKey()]);

        $this->postJson(route('rotinas.atribuir'), [...$dados, 'execucoes_ids' => [$idAnterior, $idDiaria]])->assertOk();
        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_execucao' => $idDiaria, 'id_usuario_responsavel' => $destino->getKey(),
            'status' => 'em_andamento', 'iniciado_em' => $agora->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'rotinas_execucoes', 'registro_id' => (string) $idDiaria, 'acao' => 'UPDATE']);
        $this->postJson(route('rotinas.atribuir'), [...$dados, 'processo' => 'emergencial', 'execucoes_ids' => [$idEmergencial]])->assertOk();
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $idEmergencial, 'id_usuario_responsavel' => $destino->getKey(), 'status' => 'em_andamento']);
        $this->actingAs($destino)->post(route('execucoes.concluir', $idDiaria), ['comentario' => 'Ronda finalizada'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_apenas_admin_conclui_as_execucoes_selecionadas_do_processo_com_comentario_e_auditoria(): void
    {
        $agora = CarbonImmutable::parse('2026-11-19 12:00:00', 'UTC');
        $this->travelTo($agora);
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $colaborador = $this->usuario('ana', $unidade);
        $admin = $this->usuario('admin', Empresa::create(['empresa' => 'Outra Unidade']));
        $rotinaPendente = $this->rotina($colaborador, $unidade, $this->tarefa('Ronda Pendente'), '08:50:00');
        $rotinaIniciada = $this->rotina($colaborador, $unidade, $this->tarefa('Ronda Iniciada'), '09:00:00');
        $rotinaNaoSelecionada = $this->rotina($colaborador, $unidade, $this->tarefa('Outra Ronda'), '09:10:00');
        $rotinaEmergencial = $this->rotina($colaborador, $unidade, $this->tarefa('Atendimento Urgente'), '09:00:00');
        DB::table('rotinas')->where('id_rotina', $rotinaEmergencial)->update([
            'tipo_rotina' => ExecucoesDiarias::TIPO_EMERGENCIAL, 'agendada_em' => $agora,
        ]);
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $execucoes = DB::table('rotinas_execucoes')->pluck('id_execucao', 'id_rotina');
        $pendente = $execucoes[$rotinaPendente];
        $iniciada = $execucoes[$rotinaIniciada];
        $emergencial = $execucoes[$rotinaEmergencial];
        $this->actingAs($colaborador)->post(route('execucoes.iniciar', $iniciada))->assertSessionHasNoErrors();
        $fim = $agora->addMinutes(5);
        $this->travelTo($fim);
        $popup = route('monitoramento.processo', 'diaria');
        $rota = route('monitoramento.concluir-selecionadas');
        $dados = ['processo' => 'diaria', 'execucoes_ids' => [$pendente, $iniciada], 'comentario' => 'encerramento pela administração'];

        $this->get($popup)->assertOk()->assertSeeText('Selecionar todas')->assertDontSeeText('Concluir selecionadas');
        $this->postJson($rota, $dados)->assertForbidden();
        $this->actingAs($admin)->get($popup)->assertOk()->assertSeeText('Concluir selecionadas');
        $this->postJson($rota, [...$dados, 'comentario' => ' '])->assertUnprocessable()->assertJsonValidationErrors('comentario');
        $this->postJson($rota, [...$dados, 'execucoes_ids' => [$pendente, $emergencial]])
            ->assertUnprocessable()->assertJsonValidationErrors('execucoes_ids');
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $pendente, 'status' => 'pendente', 'concluido_em' => null]);

        $this->postJson($rota, $dados)->assertOk()->assertJsonPath('message', '2 tarefas concluídas com sucesso.');
        foreach ([$pendente => null, $iniciada => $agora->toDateTimeString()] as $id => $inicio) {
            $this->assertDatabaseHas('rotinas_execucoes', [
                'id_execucao' => $id, 'status' => 'concluida', 'id_usuario_responsavel' => $colaborador->getKey(),
                'id_usuario_conclusao' => $admin->getKey(), 'iniciado_em' => $inicio, 'concluido_em' => $fim->toDateTimeString(),
                'comentario_conclusao' => 'Encerramento pela Administração',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'tabela' => 'rotinas_execucoes', 'registro_id' => (string) $id, 'acao' => 'CONCLUIR', 'id_usuario' => $admin->getKey(),
            ]);
        }
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $execucoes[$rotinaNaoSelecionada], 'status' => 'pendente']);
        $this->postJson($rota, [...$dados, 'execucoes_ids' => [$pendente, $execucoes[$rotinaNaoSelecionada]]])
            ->assertUnprocessable()->assertJsonValidationErrors('execucoes_ids');
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $execucoes[$rotinaNaoSelecionada], 'status' => 'pendente']);
        $this->get($popup)->assertOk()->assertDontSeeText(['Ronda Pendente', 'Ronda Iniciada'])->assertSeeText('Outra Ronda');
        $this->get(route('relatorios.rotinas-finalizadas'))->assertOk()->assertSeeText(['Ronda Pendente', 'Ronda Iniciada', 'Encerramento pela Administração']);
        $this->get(route('monitoramento.index', ['processo' => 'diaria', 'status' => 'concluida']))
            ->assertOk()->assertViewHas('situacoes', fn ($situacoes) => $situacoes->sortKeys()->all() === ['atrasado' => 1, 'noPrazo' => 1]);

        $this->postJson($rota, [...$dados, 'processo' => 'emergencial', 'execucoes_ids' => [$emergencial]])->assertOk();
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $emergencial, 'status' => 'concluida', 'iniciado_em' => null]);
        $this->get(route('monitoramento.index', ['processo' => 'emergencial', 'status' => 'concluida']))
            ->assertOk()->assertViewHas('situacoes', fn ($situacoes) => $situacoes->all() === ['noPrazo' => 1]);
    }

    public function test_tarefa_emergencial_entra_no_horario_uma_vez_e_escalona_pelo_sla_da_caixa(): void
    {
        $inicio = CarbonImmutable::parse('2026-11-24 09:00:00', ExecucoesDiarias::FUSO);
        $this->travelTo($inicio->subMinutes(20));
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $colaborador = $this->usuario('ana', $unidade);
        $superior = $this->usuario('bia', $unidade);
        foreach ([[$colaborador, 'Cuidador'], [$superior, 'Enfermagem']] as [$usuario, $grupo]) {
            $idGrupo = DB::table('grupos_acesso')->where('grupo_acesso', $grupo)->value('id_grupo_acesso');
            $usuario->forceFill(['id_grupo_acesso' => $idGrupo])->save();
        }
        $local = DB::table('locais')->insertGetId(['local' => 'Enfermaria', 'status' => true], 'id_local');
        $tarefa = $this->tarefa('Atendimento urgente', '00:02:00');
        DB::table('tarefas_empresas')->insert(['id_tarefa' => $tarefa, 'id_empresa' => $unidade->getKey()]);
        DB::table('locais_empresas')->insert(['id_local' => $local, 'id_empresa' => $unidade->getKey()]);
        $recorrente = $this->rotina($colaborador, $unidade, $this->tarefa('Ronda recorrente'), '16:00:00', 'Terça-feira');

        $this->actingAs($colaborador)->get(route('processos.index'))->assertOk()
            ->assertSeeText('Cadastro de Rotina Emergencial')
            ->assertSee('id="inputDataEmergencial" required', false)
            ->assertSee('value="2026-11-24"', false)
            ->assertSee('value="08:40"', false)
            ->assertSeeText('terça-feira')
            ->assertDontSeeText('Situação da rotina');
        $cadastro = route('rotinas.emergenciais.store');
        $dados = [
            'status_rotina' => 'Inativa',
            'tarefas_json' => json_encode([[
                'data' => '2026-11-24',
                'horario' => '09:00',
                'id_tarefa' => $tarefa,
                'id_local' => $local,
                'auxilio' => 'Não',
            ]], JSON_THROW_ON_ERROR),
        ];
        $this->post($cadastro, $dados)->assertSessionHasErrors(['id_empresa', 'colaborador_id']);
        $this->post($cadastro, $dados + [
            'id_empresa' => $unidade->getKey(),
            'colaborador_id' => $colaborador->getKey(),
        ])->assertSessionHas('success', 'Tarefas emergenciais salvas com sucesso!');

        $idRotina = DB::table('rotinas')->where('id_tarefa', $tarefa)->value('id_rotina');
        $this->assertDatabaseHas('rotinas', [
            'id_rotina' => $idRotina, 'status' => true, 'tipo_rotina' => 'emergencial', 'agendada_em' => '2026-11-24 12:00:00',
            'dias_semanas' => 'Terça-feira',
        ]);
        $this->get(route('relatorios.rotinas'))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => (int) $rotinas->sole()->id_rotina_principal === $recorrente
                && (int) $rotinas->sole()->total_tarefas === 1);
        $this->get(route('relatorios.rotinas-finalizadas'))->assertOk()->assertSeeText('Nenhum registro encontrado');
        $exportacao = $this->get(route('relatorios.excel.exportar'))->assertDownload()->streamedContent();
        $this->assertStringContainsString('Ronda recorrente', $exportacao);
        $this->assertStringNotContainsString('Atendimento urgente', $exportacao);

        $this->travelTo($inicio->subSecond());
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $this->get(route('home'))->assertOk()->assertViewHas('rotinas', fn ($rotinas) => $rotinas->isEmpty());
        $this->assertDatabaseMissing('rotinas_execucoes', ['id_rotina' => $idRotina]);

        $this->travelTo($inicio);
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $idRotina)->value('id_execucao');
        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_execucao' => $id,
            'previsto_em' => '2026-11-24 12:00:00',
            'created_at' => '2026-11-24 12:00:00',
            'prazo_em' => '2026-11-24 12:30:00',
            'status' => 'pendente',
        ]);
        $this->actingAs($superior)->get(route('home'))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->isEmpty());

        $this->actingAs($colaborador);
        foreach ([899 => 'noPrazo', 900 => 'expirando'] as $segundos => $situacao) {
            $this->travelTo($inicio->addSeconds($segundos));
            $this->get(route('home'))->assertOk()->assertSeeText('Tarefa emergencial')
                ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->sole()->situacao_sla === $situacao);
        }
        $this->post(route('execucoes.iniciar', $id), ['fluxo_diario' => '1'])
            ->assertRedirectToRoute('execucoes.realizando', $id);
        $this->get(route('execucoes.realizando', $id))->assertOk()->assertSeeText('Tarefa emergencial');
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $id, 'status' => 'em_andamento', 'prazo_em' => '2026-11-24 12:30:00']);
        $this->get(route('monitoramento.index', ['processo' => 'emergencial', 'status' => 'pendente']))->assertOk()
            ->assertViewHas('processos', fn ($processos) => $processos->get('emergencial')->all() === ['pendente' => 1, 'todas' => 1])
            ->assertViewHas('execucoes', fn ($itens) => $itens->sole()->id_execucao === $id && $itens->sole()->status === 'em_andamento');

        $acompanhamento = route('acompanhamento.index', ['aba' => 'pendentes']);
        $this->travelTo($inicio->addMinutes(30)->subSecond());
        $this->actingAs($superior)->get($acompanhamento)->assertOk()->assertDontSeeText('Atendimento urgente');
        $this->travelTo($inicio->addMinutes(30));
        $this->get($acompanhamento)->assertOk()->assertSeeText(['Atendimento urgente', 'Tarefa emergencial']);
        $this->actingAs($colaborador)->get(route('home', ['aba' => 'em_andamento']))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->sole()->situacao_sla === 'atrasado');
        $this->post(route('execucoes.concluir', $id), ['comentario' => 'Atendimento realizado.'])->assertSessionHasNoErrors();
        $this->get(route('home', ['aba' => 'concluida']))->assertOk()->assertSeeText('Atendimento urgente');
        $this->get(route('relatorios.rotinas-finalizadas'))->assertOk()->assertDontSeeText('Atendimento urgente');
        $this->get(route('monitoramento.index', ['processo' => 'emergencial', 'status' => 'concluida']))->assertOk()
            ->assertViewHas('processos', fn ($processos) => $processos->get('emergencial')->all() === ['concluida' => 1, 'todas' => 1])
            ->assertViewHas('execucoes', fn ($itens) => $itens->sole()->id_execucao === $id);

        $this->travelTo($inicio->addWeek()->addHour());
        $this->artisan('rotinas:gerar-execucoes')->assertSuccessful();
        $this->assertSame(1, DB::table('rotinas_execucoes')->where('id_rotina', $idRotina)->count());
        $this->get(route('home', ['aba' => 'concluida']))->assertOk()->assertDontSeeText('Atendimento urgente');
    }

    public function test_emergenciais_pendentes_entram_no_topo_com_borda_avermelhada(): void
    {
        $horario = CarbonImmutable::parse('2026-11-24 09:00:00', ExecucoesDiarias::FUSO);
        $this->travelTo($horario->subMinute());
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $this->rotina($usuario, $unidade, $this->tarefa('Ronda da manhã'), '08:00:00', 'Terça-feira');
        $this->rotina($usuario, $unidade, $this->tarefa('Verificar o salão'), '10:00:00', 'Terça-feira');

        foreach (['Primeira emergência' => $horario, 'Nova emergência' => $horario->addMinute()] as $nome => $agendada) {
            $id = $this->rotina($usuario, $unidade, $this->tarefa($nome), $agendada->format('H:i:s'), 'Terça-feira');
            DB::table('rotinas')->where('id_rotina', $id)->update([
                'tipo_rotina' => ExecucoesDiarias::TIPO_EMERGENCIAL, 'agendada_em' => $agendada->utc(),
            ]);
        }

        $this->actingAs($usuario)->get(route('home'))->assertOk()->assertDontSeeText('Primeira emergência');
        $this->travelTo($horario);
        $this->get(route('home'))->assertOk()->assertSeeTextInOrder(['Primeira emergência', 'Ronda da manhã', 'Verificar o salão']);

        $this->travelTo($horario->addMinute());
        $this->get(route('home', ['aba' => 'todas']), ['X-Painel-Rotinas-Diarias' => '1'])->assertOk()
            ->assertSeeInOrder([
                'border-rose-400', 'title="Nova emergência"',
                'border-rose-400', 'title="Primeira emergência"',
                'border-[#d3ae74]/35', 'title="Ronda da manhã"',
                'border-[#d3ae74]/35', 'title="Verificar o salão"',
            ], false);
    }

    public function test_emergencial_encerrada_sem_realizacao_aparece_apenas_em_todas_as_tarefas_do_processo(): void
    {
        $inicio = CarbonImmutable::parse('2026-11-24 09:00:00', ExecucoesDiarias::FUSO);
        $this->travelTo($inicio);
        $unidade = Empresa::create(['empresa' => 'Unidade Santana']);
        $usuario = $this->usuario('ana', $unidade);
        $rotina = $this->rotina($usuario, $unidade, $this->tarefa('Atendimento emergencial'), '09:00:00', 'Terça-feira');
        DB::table('rotinas')->where('id_rotina', $rotina)->update([
            'tipo_rotina' => ExecucoesDiarias::TIPO_EMERGENCIAL, 'agendada_em' => $inicio->utc(),
        ]);

        $this->actingAs($usuario)->get(route('home'))->assertOk();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->value('id_execucao');
        $this->post(route('execucoes.iniciar', $id))->assertSessionHasNoErrors();
        $this->post(route('execucoes.nao-realizada', $id), ['justificativa' => 'Material indisponível.'])->assertSessionHasNoErrors();

        $this->get(route('monitoramento.index', ['processo' => 'emergencial']))->assertOk()
            ->assertViewHas('processos', fn ($processos) => $processos->get('emergencial')->all() === ['todas' => 1])
            ->assertViewHas('execucoes', fn ($itens) => $itens->sole()->status === 'nao_realizada');
        $this->get(route('monitoramento.index', ['processo' => 'emergencial', 'status' => 'concluida']))->assertOk()
            ->assertViewHas('execucoes', fn ($itens) => $itens->isEmpty());
    }

    private function usuario(string $login, Empresa $empresa): User
    {
        $usuario = User::create(['usuario' => ucfirst($login), 'username' => $login, 'password' => 'senha-de-teste', 'status' => true]);
        $usuario->empresas()->attach($empresa->getKey(), ['empresa_nome' => $empresa->empresa]);

        return $usuario;
    }

    private function tarefa(string $nome, string $duracao = '00:30:00'): int
    {
        return DB::table('tarefas')->insertGetId(['tarefa' => $nome, 'tempo_realizar_tarefa' => $duracao, 'status' => true], 'id_tarefa');
    }

    private function rotina(User $usuario, Empresa $unidade, int $tarefa, string $horario, string $dias = 'Quinta-feira', bool $ativa = true): int
    {
        return DB::table('rotinas')->insertGetId([
            'id_empresa' => $unidade->getKey(),
            'id_usuario' => $usuario->getKey(),
            'id_tarefa' => $tarefa,
            'dias_semanas' => $dias,
            'horario' => $horario,
            'status' => $ativa,
            'auxilio' => false,
        ], 'id_rotina');
    }
}
