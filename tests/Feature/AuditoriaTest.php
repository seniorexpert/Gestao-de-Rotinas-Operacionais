<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registra_entrada_consulta_exportacao_e_saida_com_o_autor(): void
    {
        $usuario = $this->usuario('lara-auditoria');

        $this->post(route('login.autenticar'), ['username' => $usuario->username, 'password' => 'senha-de-teste'])
            ->assertRedirectToRoute('home');
        $this->get(route('home', ['token' => 'parametro-secreto']))->assertOk();
        $this->get(route('relatorios.excel.exportar'))->assertDownload();
        $this->post(route('logout'))->assertRedirectToRoute('login');

        $eventos = DB::table('logs_auditoria')->orderBy('id_log')->get();
        $this->assertSame(['LOGIN', 'CONSULTAR', 'EXPORTAR', 'LOGOUT'], $eventos->pluck('acao')->all());
        $this->assertSame([$usuario->getKey()], $eventos->pluck('id_usuario')->unique()->values()->all());
        $this->assertSame([$usuario->username], $eventos->pluck('usuario_login')->unique()->values()->all());
        $this->assertSame(['web'], $eventos->pluck('origem')->unique()->values()->all());
        $this->assertStringNotContainsString('parametro-secreto', $eventos->toJson());
        $this->assertStringNotContainsString('senha-de-teste', $eventos->toJson());
        $this->assertGuest();
    }

    public function test_registra_tentativa_de_login_recusada_sem_identificar_o_usuario_alvo_como_autor(): void
    {
        $usuario = $this->usuario('lara-auditoria');

        $this->post(route('login.autenticar'), ['username' => $usuario->username, 'password' => 'senha-incorreta'])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'LOGIN', 'tabela' => 'sessoes', 'resultado' => 'recusado',
            'id_usuario' => null, 'dados_novos' => null, 'dados_anteriores' => null,
            'descricao' => 'Tentativa de entrada no sistema',
        ]);
    }

    public function test_resultado_distingue_validacao_em_redirecionamento_e_acesso_proibido(): void
    {
        $usuario = $this->usuario('lara-auditoria');

        $this->actingAs($usuario)->post(route('locais.store'), [])->assertSessionHasErrors('nome_local');
        $this->get(route('usuarios.index'))->assertForbidden();

        $this->assertDatabaseHas('logs_auditoria', [
            'rota' => 'locais.store', 'resultado' => 'invalido', 'status_http' => 302, 'id_usuario' => $usuario->getKey(),
        ]);
        $this->assertDatabaseHas('logs_auditoria', [
            'rota' => 'usuarios.index', 'resultado' => 'recusado', 'status_http' => 403, 'id_usuario' => $usuario->getKey(),
        ]);
    }

    public function test_alteracao_de_senha_registra_evento_sem_credenciais(): void
    {
        $usuario = $this->usuario('lara-auditoria');

        $this->actingAs($usuario)->post(route('password.update.custom'), [
            'password' => 'nova-senha-de-teste',
            'password_confirmation' => 'nova-senha-de-teste',
        ])->assertRedirectToRoute('login');

        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'ALTERAR_SENHA', 'id_usuario' => $usuario->getKey(), 'rota' => 'password.update.custom',
            'dados_novos' => null, 'dados_anteriores' => null,
        ]);
        $this->assertGuest();
    }

    public function test_catalogos_registram_antes_e_depois_sem_duplicar_a_operacao(): void
    {
        $usuario = $this->usuario('lara-auditoria');
        $id = DB::table('locais')->insertGetId(['local' => 'Sala de estar', 'status' => true], 'id_local');

        $this->actingAs($usuario)->post(route('locais.store'), ['id' => $id, 'nome_local' => 'Sala de convivência'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $registros = DB::table('logs_auditoria')->where('rota', 'locais.store')->get();
        $this->assertCount(1, $registros);
        $this->assertSame('UPDATE', $registros->first()->acao);
        $this->assertSame((string) $id, $registros->first()->registro_id);
        $this->assertSame(['local' => 'Sala de estar'], json_decode($registros->first()->dados_anteriores, true));
        $this->assertSame(['local' => 'Sala de convivência'], json_decode($registros->first()->dados_novos, true));
    }

    public function test_execucao_registra_inicio_conclusao_e_repeticao_sem_alteracao(): void
    {
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $usuario = $this->usuario('lara-auditoria');
        $usuario->empresas()->attach($unidade->getKey(), ['empresa_nome' => $unidade->empresa]);
        $tarefa = DB::table('tarefas')->insertGetId([
            'tarefa' => 'Abrir a sala', 'tempo_realizar_tarefa' => '00:05:00', 'status' => true,
        ], 'id_tarefa');
        $rotina = DB::table('rotinas')->insertGetId([
            'id_empresa' => $unidade->getKey(), 'id_usuario' => $usuario->getKey(), 'id_tarefa' => $tarefa,
            'dias_semanas' => 'Qui', 'horario' => '09:00:00', 'status' => true, 'auxilio' => false,
        ], 'id_rotina');
        $sla = DB::table('slas')->insertGetId(['sla' => 'SLA de teste', 'tempo_limite_minutos' => 5], 'id_sla');
        $id = DB::table('rotinas_execucoes')->insertGetId([
            'id_rotina' => $rotina, 'id_sla' => $sla, 'id_empresa' => $unidade->getKey(),
            'id_usuario_responsavel' => $usuario->getKey(), 'previsto_em' => now(), 'prazo_em' => now()->addMinutes(5),
            'tempo_limite_minutos' => 5, 'status' => 'pendente',
        ], 'id_execucao');

        $this->actingAs($usuario)->post(route('execucoes.iniciar', $id))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('execucoes.iniciar', $id))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('execucoes.concluir', $id), ['comentario' => 'Sala aberta e organizada.'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $eventos = DB::table('logs_auditoria')->where('tabela', 'rotinas_execucoes')->where('registro_id', (string) $id)->orderBy('id_log')->get();
        $this->assertSame(['INICIAR', 'INICIAR', 'CONCLUIR'], $eventos->pluck('acao')->all());
        $this->assertSame(['sucesso', 'sem_alteracao', 'sucesso'], $eventos->pluck('resultado')->all());
        $this->assertSame($unidade->getKey(), $eventos->last()->id_empresa);
        $this->assertSame($rotina, $eventos->last()->id_rotina);
        $this->assertSame('em_andamento', json_decode($eventos->last()->dados_anteriores, true)['status']);
        $this->assertSame('concluida', json_decode($eventos->last()->dados_novos, true)['status']);
    }

    public function test_consulta_detalhes_e_exportacao_respeitam_o_escopo_da_unidade(): void
    {
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $outra = Empresa::create(['empresa' => 'Unidade Norte']);
        $usuario = $this->usuario('lara-auditoria');
        $usuario->empresas()->attach($unidade->getKey(), ['empresa_nome' => $unidade->empresa]);
        $permitido = $this->log('Execução da unidade registrada', [
            'id_empresa' => $unidade->getKey(), 'tabela' => 'rotinas_execucoes',
            'ip' => '192.0.2.10', 'user_agent' => 'Navegador de teste',
        ]);
        $restrito = $this->log('Execução de outra unidade registrada', ['id_empresa' => $outra->getKey(), 'tabela' => 'rotinas_execucoes']);
        $this->log('Evento global de sessão reservado', ['tabela' => 'sessoes']);

        $this->actingAs($usuario)->get(route('relatorios.logs-rotinas'))->assertOk()
            ->assertSeeText('Execução da unidade registrada')
            ->assertDontSeeText(['Execução de outra unidade registrada', 'Evento global de sessão reservado']);
        $this->get(route('relatorios.logs.detalhes', $permitido))->assertOk()
            ->assertDontSeeText(['192.0.2.10', 'Navegador de teste']);
        $this->get(route('relatorios.logs.detalhes', $restrito))->assertNotFound();
        $this->get(route('relatorios.logs.detalhes.legado', $restrito))->assertNotFound();
        $planilha = $this->get(route('relatorios.logs.excel.exportar'))->assertDownload()->streamedContent();

        $this->assertStringContainsString('Execução da unidade registrada', $planilha);
        $this->assertStringNotContainsString('Execução de outra unidade registrada', $planilha);
        $this->assertStringNotContainsString('Evento global de sessão reservado', $planilha);
    }

    public function test_gerente_filtra_pelo_nome_historico_e_pelo_dia_de_brasilia(): void
    {
        $gerente = $this->usuario('gerente-auditora', 'Gerente Geral');
        $this->log('Evento do dia anterior em Brasília', [
            'usuario_nome' => 'Nome anterior', 'created_at' => '2026-11-19 02:59:59',
        ]);
        $id = $this->log('Evento na virada do dia em Brasília', [
            'usuario_nome' => 'Nome anterior', 'created_at' => '2026-11-19 03:00:00',
            'dados_novos' => '{"perfil":{"password":"valor-antigo-sigiloso","usuario":"Nome atualizado"}}',
        ]);

        $this->actingAs($gerente)->get(route('relatorios.logs-rotinas', [
            'data_inicio' => '2026-11-19', 'data_fim' => '2026-11-19', 'nome_executor' => 'Nome anterior',
        ]))->assertOk()->assertSeeText(['Logs e Auditoria', 'Evento na virada do dia em Brasília', '19/11/2026 00:00:00'])
            ->assertDontSeeText('Evento do dia anterior em Brasília');

        $this->get(route('relatorios.logs.detalhes', $id))->assertOk()
            ->assertSeeText(['Nome anterior', 'Nome atualizado'])
            ->assertDontSeeText('valor-antigo-sigiloso');
    }

    private function usuario(string $login, string $grupo = 'Cuidador'): User
    {
        return User::create([
            'usuario' => 'Usuária de exemplo',
            'username' => $login,
            'password' => 'senha-de-teste',
            'status' => true,
            'id_grupo_acesso' => GrupoAcesso::where('grupo_acesso', $grupo)->firstOrFail()->getKey(),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function log(string $descricao, array $dados): int
    {
        return DB::table('logs_auditoria')->insertGetId([
            'acao' => 'UPDATE',
            'tabela' => 'usuarios',
            'registro_id' => '1',
            'descricao' => $descricao,
            'origem' => 'web',
            'created_at' => now(),
            ...$dados,
        ], 'id_log');
    }
}
