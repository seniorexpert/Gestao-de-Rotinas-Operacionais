<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use App\Services\VisibilidadeGrupos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisibilidadeGruposTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_so_o_admin_configura_a_visibilidade_e_a_mudanca_fica_na_auditoria(): void
    {
        $origem = $this->grupo('Enfermagem');
        $admin = $this->usuario('Administrador', 'Gerente Geral', login: 'admin');
        $gerente = $this->usuario('Gestor de Teste', 'Gerente Geral');
        $lista = route('visibilidade-grupos.index', ['grupo' => $origem]);
        $edicao = route('visibilidade-grupos.update', $origem);
        $dados = ['modo' => 'selecionados', 'grupos_visiveis' => [$this->grupo('Cuidador')->getKey(), $this->grupo('Farmácia')->getKey()]];

        $this->get($lista)->assertRedirectToRoute('login');
        $this->actingAs($gerente)->get($lista)->assertForbidden();
        $this->put($edicao, $dados)->assertForbidden();
        $this->get(route('home'))->assertOk()->assertDontSee(route('visibilidade-grupos.index'));
        $this->assertDatabaseEmpty(VisibilidadeGrupos::TABELA);

        $this->actingAs($admin)->get($lista)->assertOk()
            ->assertSeeText(['Visibilidade entre Grupos', 'Grupo que consulta', 'Enfermagem', 'Somente grupos selecionados', 'Salvar visibilidade']);
        $this->from($lista)->put($edicao, ['modo' => 'selecionados', 'grupos_visiveis' => [999999]])
            ->assertRedirect($lista)->assertSessionHasErrors('grupos_visiveis.0');
        $this->assertDatabaseEmpty(VisibilidadeGrupos::TABELA);

        $this->put($edicao, $dados)->assertRedirect($lista)->assertSessionHasNoErrors();
        $this->assertDatabaseCount(VisibilidadeGrupos::TABELA, 3);
        foreach ([$origem->getKey(), ...$dados['grupos_visiveis']] as $grupoVisivel) {
            $this->assertDatabaseHas(VisibilidadeGrupos::TABELA, ['id_grupo_origem' => $origem->getKey(), 'id_grupo_visivel' => $grupoVisivel]);
        }
        $this->assertDatabaseHas('logs_auditoria', [
            'tabela' => VisibilidadeGrupos::TABELA, 'acao' => 'UPDATE', 'registro_id' => (string) $origem->getKey(), 'id_usuario' => $admin->getKey(),
        ]);
        $this->get($lista)->assertOk()->assertViewHas('visiveis', fn (array $ids): bool => count($ids) === 3);
    }

    public function test_a_selecao_restringe_colaboradores_indicadores_relatorios_e_enderecos_diretos(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:30:00', 'UTC'));
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $norte = Empresa::create(['empresa' => 'Unidade Norte']);
        $enfermeira = $this->usuario('Nara Enfermagem', 'Enfermagem', $centro);
        $cuidador = $this->usuario('Caio Cuidador', 'Cuidador', $centro);
        $farmacia = $this->usuario('Fábio Farmácia', 'Farmácia', $centro);
        $estoque = $this->usuario('Eva Estoque', 'Estoque', $centro);
        $outroCuidador = $this->usuario('Otávio Outra Unidade', 'Cuidador', $norte);
        $admin = $this->usuario('Administrador', 'Enfermagem', login: 'admin');
        $grupo = $enfermeira->id_grupo_acesso;
        $this->actingAs($admin)->put(route('visibilidade-grupos.update', $grupo), [
            'modo' => 'selecionados', 'grupos_visiveis' => [$cuidador->id_grupo_acesso, $farmacia->id_grupo_acesso],
        ])->assertSessionHasNoErrors();
        $this->put(route('permissoes.update', ['grupo', $grupo]), ['regras' => ['usuarios' => '2']])->assertSessionHasNoErrors();

        [$rotina, $execucao] = $this->rotinaExecutada('Higiene Visível', $centro, $cuidador);
        [$rotinaOculta, $execucaoOculta] = $this->rotinaExecutada('Estoque Oculto', $centro, $estoque);
        [, $execucaoOutraUnidade] = $this->rotinaExecutada('Rotina de Outra Unidade', $norte, $outroCuidador);
        [, $execucaoComFinalizadorOculto] = $this->rotinaExecutada('Encerrada pelo Estoque', $centro, $farmacia, $estoque);
        $logVisivel = $this->logDaRotina('Auditoria Visível', $centro, $rotina, $cuidador);
        $logOculto = $this->logDaRotina('Auditoria Oculta', $centro, $rotinaOculta, $estoque);

        $this->actingAs($enfermeira)->getJson(route('processos.usuarios-por-unidade', ['id_empresa' => $centro]))
            ->assertOk()->assertJsonCount(3)->assertJsonFragment(['id_usuario' => $enfermeira->getKey()])
            ->assertJsonFragment(['id_usuario' => $cuidador->getKey()])->assertJsonFragment(['id_usuario' => $farmacia->getKey()]);
        $this->getJson(route('processos.usuarios-por-unidade', ['id_empresa' => $norte]))->assertNotFound();
        $this->get(route('usuarios.index'))->assertOk()->assertSeeText('Caio Cuidador')->assertDontSeeText(['Eva Estoque', 'Otávio Outra Unidade']);
        $this->get(route('monitoramento.index'))->assertOk()->assertSeeText('Higiene Visível')
            ->assertDontSeeText(['Estoque Oculto', 'Eva Estoque', 'Rotina de Outra Unidade']);
        $this->get(route('relatorios.estatisticas', ['aba' => 'usuarios', 'finalizados' => 1]))->assertOk()
            ->assertSeeText('Caio Cuidador')->assertDontSeeText(['Eva Estoque', 'Otávio Outra Unidade']);
        $this->get(route('relatorios.estatisticas', ['aba' => 'grupos', 'finalizados' => 1]))->assertOk()
            ->assertSeeText('Farmácia')->assertDontSeeText('Estoque');
        $this->get(route('relatorios.estatisticas', ['aba' => 'processos', 'finalizados' => 1, 'detalhe' => 'processo-diarias']))->assertOk()
            ->assertSeeText('Higiene Visível')->assertDontSeeText(['Estoque Oculto', 'Rotina de Outra Unidade']);
        $this->get(route('relatorios.rotinas'))->assertOk()->assertSeeText('Caio Cuidador')->assertDontSeeText(['Eva Estoque', 'Otávio Outra Unidade']);
        $this->get(route('relatorios.rotinas', ['editar' => $rotinaOculta]))->assertNotFound();
        $this->get(route('relatorios.excel'))->assertOk()->assertSeeText('Higiene Visível')->assertDontSeeText('Estoque Oculto');
        $planilha = $this->get(route('relatorios.excel.exportar'))->assertDownload()->streamedContent();
        $this->assertStringContainsString('Higiene Visível', $planilha);
        $this->assertStringNotContainsString('Estoque Oculto', $planilha);
        $this->get(route('relatorios.rotinas-finalizadas'))->assertOk()->assertSeeText('Higiene Visível')
            ->assertDontSeeText(['Estoque Oculto', 'Rotina de Outra Unidade', 'Encerrada pelo Estoque']);
        $this->get(route('relatorios.rotinas-finalizadas.detalhes', $execucao))->assertOk();
        foreach ([$execucaoOculta, $execucaoOutraUnidade, $execucaoComFinalizadorOculto] as $oculta) {
            $this->get(route('relatorios.rotinas-finalizadas.detalhes', $oculta))->assertNotFound();
            $this->get(route('relatorios.rotinas-finalizadas.foto', $oculta))->assertNotFound();
        }
        $this->get(route('relatorios.logs-rotinas'))->assertOk()->assertSeeText('Auditoria Visível')->assertDontSeeText('Auditoria Oculta');
        $this->get(route('relatorios.logs.detalhes', $logVisivel))->assertOk();
        $this->get(route('relatorios.logs.detalhes', $logOculto))->assertNotFound();
        $this->getJson(route('relatorios.logs.detalhes.legado', $logOculto))->assertNotFound();

        $this->actingAs($cuidador)->getJson(route('processos.usuarios-por-unidade', ['id_empresa' => $centro]))
            ->assertOk()->assertJsonFragment(['id_usuario' => $estoque->getKey()]);
        $this->actingAs($admin)->get(route('monitoramento.index'))->assertOk()->assertSeeText(['Estoque Oculto', 'Rotina de Outra Unidade']);
    }

    public function test_roteiro_de_proprio_grupo_e_restauracao_do_padrao_recalculam_as_consultas(): void
    {
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $enfermeira = $this->usuario('Nara Enfermagem', 'Enfermagem', $unidade);
        $farmacia = $this->usuario('Fábio Farmácia', 'Farmácia', $unidade);
        $admin = $this->usuario('Administrador', 'Gerente Geral', login: 'admin');
        $edicao = route('visibilidade-grupos.update', $enfermeira->id_grupo_acesso);
        $consulta = route('processos.usuarios-por-unidade', ['id_empresa' => $unidade]);

        $this->actingAs($enfermeira)->getJson($consulta)->assertOk()->assertJsonCount(2);
        $this->actingAs($admin)->put($edicao, ['modo' => 'selecionados'])->assertSessionHasNoErrors();
        $this->actingAs($enfermeira)->getJson($consulta)->assertOk()->assertJsonCount(1)
            ->assertJsonFragment(['id_usuario' => $enfermeira->getKey()]);

        $this->actingAs($admin)->put($edicao, ['modo' => 'todos'])->assertSessionHasNoErrors();
        $this->actingAs($enfermeira)->getJson($consulta)->assertOk()->assertJsonCount(2)
            ->assertJsonFragment(['id_usuario' => $farmacia->getKey()]);
    }

    public function test_grupos_ocultos_nao_podem_receber_rotinas_por_envio_direto_ou_ampliar_a_propria_visibilidade(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:30:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $enfermeira = $this->usuario('Nara Enfermagem', 'Enfermagem', $unidade);
        $cuidador = $this->usuario('Caio Cuidador', 'Cuidador', $unidade);
        $estoque = $this->usuario('Eva Estoque', 'Estoque', $unidade);
        $admin = $this->usuario('Administrador', 'Gerente Geral', login: 'admin');
        $this->actingAs($admin)->put(route('visibilidade-grupos.update', $enfermeira->id_grupo_acesso), [
            'modo' => 'selecionados', 'grupos_visiveis' => [$cuidador->id_grupo_acesso],
        ])->assertSessionHasNoErrors();
        $this->put(route('permissoes.update', ['grupo', $enfermeira->id_grupo_acesso]), ['regras' => ['usuarios' => '2']]);
        [$rotina] = $this->rotinaExecutada('Higiene Visível', $unidade, $cuidador);
        [$outra] = $this->rotinaExecutada('Estoque Oculto', $unidade, $estoque);

        $this->actingAs($enfermeira)->postJson(route('rotinas.store'), [
            'id_empresa' => $unidade->getKey(), 'colaborador_id' => $estoque->getKey(), 'tarefas_json' => '[]',
        ])->assertUnprocessable()->assertJsonValidationErrors('colaborador_id');
        $this->postJson(route('rotinas.atribuir'), ['rotinas_ids' => [$rotina], 'novo_colaborador_id' => $estoque->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('novo_colaborador_id');
        $this->delete(route('rotinas.destroy', $outra))->assertNotFound();
        $this->put(route('users.update', $estoque->getKey()), ['usuario' => 'Nome Oculto'])->assertForbidden();
        $this->put(route('users.update', $enfermeira->getKey()), [
            'usuario' => $enfermeira->usuario, 'id_grupo_acesso' => $cuidador->id_grupo_acesso,
        ])->assertForbidden();

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $rotina, 'id_usuario' => $cuidador->getKey()]);
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $outra, 'excluida_em' => null]);
        $this->assertSame($enfermeira->id_grupo_acesso, $enfermeira->fresh()->id_grupo_acesso);
    }

    private function grupo(string $nome): GrupoAcesso
    {
        return GrupoAcesso::query()->where('grupo_acesso', $nome)->firstOrFail();
    }

    private function usuario(string $nome, string $grupo, ?Empresa $unidade = null, ?string $login = null): User
    {
        $usuario = User::create([
            'usuario' => $nome, 'username' => $login ?? str($nome)->slug()->toString(), 'password' => 'senha-de-teste',
            'status' => true, 'id_grupo_acesso' => $this->grupo($grupo)->getKey(),
        ]);
        if ($unidade) {
            $usuario->empresas()->attach($unidade->getKey(), ['empresa_nome' => $unidade->empresa]);
        }

        return $usuario;
    }

    /** @return array{int, int} */
    private function rotinaExecutada(string $nome, Empresa $unidade, User $responsavel, ?User $finalizador = null): array
    {
        $sla = DB::table('slas')->insertGetId(['sla' => 'SLA de teste', 'tempo_limite_minutos' => 15], 'id_sla');
        $tarefa = DB::table('tarefas')->insertGetId(['tarefa' => $nome, 'tempo_realizar_tarefa' => '00:15:00', 'status' => true], 'id_tarefa');
        $rotina = DB::table('rotinas')->insertGetId([
            'id_empresa' => $unidade->getKey(), 'id_usuario' => $responsavel->getKey(), 'id_tarefa' => $tarefa,
            'horario' => '09:00:00', 'dias_semanas' => 'Quinta-feira', 'status' => true, 'auxilio' => false,
        ], 'id_rotina');
        $execucao = DB::table('rotinas_execucoes')->insertGetId([
            'id_empresa' => $unidade->getKey(), 'id_rotina' => $rotina, 'id_sla' => $sla,
            'id_usuario_responsavel' => $responsavel->getKey(), 'id_usuario_conclusao' => ($finalizador ?? $responsavel)->getKey(),
            'status' => 'concluida', 'previsto_em' => '2026-11-26 12:00:00', 'iniciado_em' => '2026-11-26 12:00:00',
            'concluido_em' => '2026-11-26 12:12:00', 'prazo_em' => '2026-11-26 12:15:00', 'tempo_limite_minutos' => 15,
            'duracao_prevista_segundos' => 900, 'agenda_ate' => '2026-11-26 18:00:00',
        ], 'id_execucao');

        return [(int) $rotina, (int) $execucao];
    }

    private function logDaRotina(string $descricao, Empresa $unidade, int $rotina, User $autor): int
    {
        return (int) DB::table('logs_auditoria')->insertGetId([
            'acao' => 'UPDATE', 'tabela' => 'rotinas', 'registro_id' => (string) $rotina, 'descricao' => $descricao,
            'id_empresa' => $unidade->getKey(), 'id_rotina' => $rotina, 'id_usuario' => $autor->getKey(),
            'created_at' => now(),
        ], 'id_log');

    }
}
