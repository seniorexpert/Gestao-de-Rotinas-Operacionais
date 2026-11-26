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
        $this->get(route('monitoramento.index'))->assertOk()->assertViewHas('execucoes', fn ($execucoes) => $execucoes->count() === 5);
        $concluidas = route('home', ['aba' => 'concluida']);
        $this->get($concluidas)->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 5
                && $rotinas->where('status', 'concluida')->sole()->id_execucao === $recepcao)
            ->assertSeeTextInOrder(['22:00 · 19/11/2026', 'Concluída em', '19/11/2026 22:00']);
        $this->assertDatabaseCount('rotinas_execucoes', 5);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00:00', 'America/Sao_Paulo'));
        $this->get($concluidas)->assertOk()->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 5
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
            ->assertSeeText(['Verificar o salão', 'Atrasada', '0 de 3 concluídas']);
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
        $this->get($estatisticas)->assertOk()->assertSeeText('Comentário (obrigatório)')
            ->assertSee('name="comentario" rows="3" maxlength="2000" required', false);
        $this->from($estatisticas)->followingRedirects()
            ->post(route('execucoes.concluir', $id), ['id_execucao' => (string) $id])
            ->assertOk()->assertSeeText('Informe um comentário sobre a execução.')
            ->assertSee('aria-describedby="erros-estatisticas"', false);
        $this->from($estatisticas)->post(route('execucoes.concluir', $id), ['comentario' => 'Salão preparado.'])
            ->assertRedirect($estatisticas)->assertSessionHasNoErrors();
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
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $id, 'status' => 'concluida', 'comentario_conclusao' => 'Assistência registrada.']);
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
            'id_execucao' => $id, 'status' => 'nao_realizada', 'justificativa_nao_realizada' => 'Morador ausente.',
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
        foreach (['relatorios.rotinas', 'relatorios.rotinas-finalizadas'] as $relatorio) {
            $this->get(route($relatorio))->assertOk()
                ->assertViewHas('rotinas', fn ($rotinas) => (int) $rotinas->sole()->id_rotina_principal === $recorrente
                    && (int) $rotinas->sole()->total_tarefas === 1);
        }
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

        $acompanhamento = route('acompanhamento.index', ['aba' => 'pendentes']);
        $this->travelTo($inicio->addMinutes(30)->subSecond());
        $this->actingAs($superior)->get($acompanhamento)->assertOk()->assertDontSeeText('Atendimento urgente');
        $this->travelTo($inicio->addMinutes(30));
        $this->get($acompanhamento)->assertOk()->assertSeeText(['Atendimento urgente', 'Tarefa emergencial']);
        $this->actingAs($colaborador)->get(route('home', ['aba' => 'em_andamento']))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->sole()->situacao_sla === 'atrasado');
        $this->post(route('execucoes.concluir', $id), ['comentario' => 'Atendimento realizado.'])->assertSessionHasNoErrors();
        $this->get(route('home', ['aba' => 'concluida']))->assertOk()->assertSeeText('Atendimento urgente');

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
