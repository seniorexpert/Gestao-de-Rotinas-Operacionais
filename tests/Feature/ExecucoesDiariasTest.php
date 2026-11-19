<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_consulta_gera_rotinas_existentes_no_dia_local_e_respeita_a_unidade(): void
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
        $this->assertDatabaseCount('rotinas_execucoes', 1);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->get(route('monitoramento.index'))->assertOk();

        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $sexta, 'previsto_em' => '2026-11-20 03:05:00']);
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
        $rotina = $this->rotina($usuario, $unidade, $this->tarefa('Preparar o salão', '00:01:30'), '09:00:00');

        $this->actingAs($usuario)->get(route('home'))->assertOk();
        $id = DB::table('rotinas_execucoes')->where('id_rotina', $rotina)->value('id_execucao');
        $this->from(route('home'))->post(route('execucoes.iniciar', $id))->assertRedirectToRoute('home');

        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_execucao' => $id, 'status' => 'em_andamento',
            'iniciado_em' => '2026-11-19 12:00:00', 'prazo_em' => '2026-11-19 12:01:30',
        ]);
        $this->get(route('home'))->assertOk()->assertViewHas('rotinas', fn ($registros) => $registros->sole()->acao_execucao['url'] === route('execucoes.concluir', $id));

        $this->travelTo($agora->addSeconds(45));
        $this->get(route('monitoramento.index'))->assertOk()
            ->assertViewHas('situacoes', fn ($situacoes) => $situacoes->get('expirando') === 1);

        $this->travelTo($agora->addSeconds(95));
        $this->from(route('home'))->post(route('execucoes.concluir', $id))->assertRedirectToRoute('home');
        $this->get(route('monitoramento.index'))->assertOk()->assertSeeText('1 de 1 concluídas')
            ->assertViewHas('execucoes', fn ($registros) => $registros->sole()->situacao_sla === 'atrasado');
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
