<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RotinaAcompanhamentoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:00:00', 'UTC'));
    }

    public function test_convidado_e_redirecionado_para_o_login(): void
    {
        $this->get(route('acompanhamento.index'))->assertRedirectToRoute('login');
    }

    public function test_iniciar_processos_exibe_um_unico_link_de_acompanhamento(): void
    {
        $gerente = $this->usuario('Gerente Exemplo', 'Gerente Geral', $this->empresa('Unidade Centro'));
        $link = 'href="'.route('acompanhamento.index').'"';

        $resposta = $this->actingAs($gerente)->get(route('processos.index'));

        $resposta->assertOk();
        $this->assertSame(1, substr_count($resposta->getContent(), $link));
    }

    public function test_colaborador_nao_pode_concluir_verificacoes(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);

        $this->actingAs($colaborador)
            ->post(route('acompanhamento.store', $id), ['acao' => 'Ação registrada'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('rotinas_verificacoes');
    }

    public function test_painel_das_rotinas_diarias_exige_acesso_ao_acompanhamento(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $consulta = $this->usuario('Ana Silva', 'Consulta', $empresa);
        DB::table('permissoes_usuarios')->insert([
            'id_usuario' => $consulta->getKey(), 'modulo' => 'acompanhamento', 'nivel' => 1,
        ]);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);
        $fila = route('acompanhamento.index', ['origem' => 'diarias', 'aba' => 'pendentes']);
        $cabecalhos = ['X-Painel-Acompanhamento' => '1', 'Accept' => 'application/json'];

        $this->actingAs($colaborador)->get(route('home'))
            ->assertOk()->assertDontSee('data-url-acompanhamento=', false);
        $this->get($fila, $cabecalhos)->assertForbidden();

        $this->actingAs($consulta)->get(route('home'))
            ->assertOk()->assertSeeInOrder(['data-url-acompanhamento=', 'data-caixa-rotinas'], false);
        $this->get($fila, $cabecalhos)->assertOk()->assertSeeText('Hidratação');
        $this->get(route('acompanhamento.index', ['origem' => 'diarias', 'item' => $id]))
            ->assertOk()->assertDontSeeText('Concluir verificação');
        $this->post(route('acompanhamento.store', $id), ['origem' => 'diarias', 'acao' => 'Ação registrada'])
            ->assertForbidden();
    }

    public function test_verificacao_pelo_painel_retorna_as_rotinas_diarias_com_os_filtros(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $enfermagem = $this->usuario('Enfermeira', 'Enfermagem', $empresa);
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);
        $foraDoEscopo = $this->empresa('Outra unidade');
        $this->execucao('Hidratação de outra unidade', $colaborador, $foraDoEscopo);
        $filtros = ['origem' => 'diarias', 'aba' => 'pendentes', 'busca' => 'hidratacao'];
        $retorno = ['aba' => 'em_andamento', 'acomp_aba' => 'pendentes', 'acomp_busca' => 'hidratacao'];

        $this->actingAs($enfermagem)->get(route('acompanhamento.index', $filtros), ['X-Painel-Acompanhamento' => '1'])
            ->assertOk()->assertSee('data-lista-acompanhamento')->assertDontSee('<!DOCTYPE html>', false)
            ->assertSeeTextInOrder(['Solicitação de Auxílio', 'Tarefas Não Realizadas', 'Tarefas em Atraso', 'Verificadas', 'Hidratação'])
            ->assertDontSeeText('Hidratação de outra unidade');

        $filtros['aba_diaria'] = 'em_andamento';
        $this->get(route('acompanhamento.index', [...$filtros, 'item' => $id]))
            ->assertOk()->assertSee(route('home', $retorno))->assertSeeText('Concluir verificação');

        $this->post(route('acompanhamento.store', ['id' => $id, ...$filtros]), ['acao' => 'Conversei com a equipe.'])
            ->assertRedirectToRoute('home', $retorno)
            ->assertSessionHas('success', 'Verificação concluída.');
        $this->assertDatabaseHas('rotinas_verificacoes', [
            'id_execucao' => $id, 'id_usuario_verificacao' => $enfermagem->getKey(), 'acao' => 'Conversei com a equipe.',
        ]);
    }

    public function test_lista_atrasos_de_inicio_e_conclusao_da_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);

        $this->execucao('Hidratação atrasada', $colaborador, $empresa);
        $this->execucao('Apoio em andamento', $colaborador, $empresa, [
            'status' => 'em_andamento',
            'iniciado_em' => '2026-11-18 14:50:00',
            'duracao_prevista_segundos' => 300,
        ]);
        $this->execucao('Refeição concluída com atraso', $colaborador, $empresa, [
            'status' => 'concluida',
            'iniciado_em' => '2026-11-18 14:50:00',
            'concluido_em' => '2026-11-18 14:58:00',
            'duracao_prevista_segundos' => 300,
        ]);
        $this->execucao('Atividade iniciada tarde', $colaborador, $empresa, [
            'status' => 'concluida',
            'iniciado_em' => '2026-11-18 14:52:00',
            'concluido_em' => '2026-11-18 14:53:00',
            'duracao_prevista_segundos' => 300,
        ]);
        $this->execucao('Execução antiga vencida', $colaborador, $empresa, [
            'status' => 'concluida',
            'iniciado_em' => '2026-11-18 14:50:00',
            'concluido_em' => '2026-11-18 14:58:00',
        ]);
        $this->execucao('Rotina no prazo', $colaborador, $empresa, [
            'status' => 'concluida',
            'iniciado_em' => '2026-11-18 14:50:00',
            'concluido_em' => '2026-11-18 14:51:00',
            'duracao_prevista_segundos' => 300,
        ]);
        $this->execucao('Rotina futura', $colaborador, $empresa, [
            'previsto_em' => '2026-11-18 15:05:00',
        ]);
        $this->execucao('Rotina cancelada', $colaborador, $empresa, ['status' => 'cancelada']);

        $outraEmpresa = $this->empresa('Unidade Norte');
        $outroColaborador = $this->usuario('José Lima', 'Enfermagem', $outraEmpresa);
        $this->execucao('Rotina de outra unidade', $outroColaborador, $outraEmpresa);

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['aba' => 'pendentes']))
            ->assertOk()
            ->assertSeeText(['Hidratação atrasada', 'Apoio em andamento', 'Refeição concluída com atraso',
                'Atividade iniciada tarde', 'Execução antiga vencida'])
            ->assertDontSeeText('Rotina no prazo')
            ->assertDontSeeText('Rotina futura')
            ->assertDontSeeText('Rotina cancelada')
            ->assertDontSeeText('Rotina de outra unidade');
    }

    #[TestWith(['joao'])]
    #[TestWith(['HIDRATACAO'])]
    #[TestWith(['beatriz'])]
    #[TestWith(['sala de estar'])]
    #[TestWith(['auxilio previsto'])]
    public function test_busca_ignora_caixa_e_acentos_nos_campos_do_item(string $termo): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $this->execucao('Hidratação', $colaborador, $empresa, rotina: ['morador' => 'Beatriz', 'auxilio' => true]);

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['aba' => 'pendentes', 'busca' => $termo]))
            ->assertOk()
            ->assertSeeText('Hidratação');
    }

    public function test_link_direto_abre_a_tarefa_com_horario_local_e_auxiliar(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $auxiliar = $this->usuario('Bianca Rocha', 'Cuidador', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa, rotina: ['auxilio' => true]);
        DB::table('rotina_auxiliares')->insert([
            'id_rotina' => DB::table('rotinas_execucoes')->where('id_execucao', $id)->value('id_rotina'),
            'id_usuario' => $auxiliar->getKey(),
        ]);

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['item' => $id]))
            ->assertOk()
            ->assertSeeText(['Verificação da Tarefa', '11:50 · quarta-feira', '18/11/2026', 'Sala de Estar',
                'João Silva', 'Bianca Rocha', 'Auxílio previsto', 'Qual ação foi realizada?']);
    }

    public static function acoesInvalidas(): array
    {
        return [
            'em branco' => ['   ', 'Descreva a ação realizada para concluir.'],
            'outro tipo' => [['Texto inválido'], 'A ação realizada deve ser informada em texto.'],
            'acima do limite' => [str_repeat('a', 2001), 'A ação realizada deve ter até 2.000 caracteres.'],
        ];
    }

    #[DataProvider('acoesInvalidas')]
    public function test_acao_invalida_retorna_ao_detalhe_sem_gravar(string|array $acao, string $mensagem): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);
        $detalhe = route('acompanhamento.index', ['item' => $id]);

        $this->actingAs($gerente)->from($detalhe)->followingRedirects()
            ->post(route('acompanhamento.store', $id), ['acao' => $acao])
            ->assertOk()
            ->assertSeeText(['Verificação da Tarefa', $mensagem]);

        $this->assertDatabaseEmpty('rotinas_verificacoes');
    }

    public function test_verificacao_registra_acao_data_e_gerente_da_sessao(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);

        $this->actingAs($gerente)
            ->post(route('acompanhamento.store', ['id' => $id, 'busca' => 'hidratação']), [
                'acao' => '  Conversei com a equipe.  ',
                'id_usuario_verificacao' => $colaborador->getKey(),
            ])
            ->assertRedirectToRoute('acompanhamento.index', ['aba' => 'pendentes', 'busca' => 'hidratação'])
            ->assertSessionHas('success', 'Verificação concluída.');

        $this->assertDatabaseHas('rotinas_verificacoes', [
            'id_execucao' => $id,
            'id_usuario_verificacao' => $gerente->getKey(),
            'acao' => 'Conversei com a equipe.',
            'verificado_em' => '2026-11-18 15:00:00',
            'encaminhamentos' => '{"Gerente da Unidade":"2026-11-18T14:51:00.000000Z"}',
        ]);
        $this->assertDatabaseHas('logs_auditoria', [
            'tabela' => 'rotinas_verificacoes',
            'acao' => 'VERIFICAR',
            'registro_id' => (string) $id,
            'id_usuario' => $gerente->getKey(),
            'id_empresa' => $empresa,
            'resultado' => 'sucesso',
        ]);
        $this->assertDatabaseHas('rotinas_execucoes', [
            'id_execucao' => $id,
            'status' => 'pendente',
            'concluido_em' => null,
        ]);
    }

    public function test_envio_repetido_preserva_a_verificacao_original(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $id = $this->execucao('Hidratação', $gerente, $empresa);
        $this->verificacao($id, $gerente, 'Ação original');

        $this->actingAs($gerente)
            ->post(route('acompanhamento.store', $id), ['acao' => 'Outro texto'])
            ->assertRedirectToRoute('acompanhamento.index', ['aba' => 'pendentes']);

        $this->assertDatabaseCount('rotinas_verificacoes', 1);
        $this->assertDatabaseHas('rotinas_verificacoes', ['id_execucao' => $id, 'acao' => 'Ação original']);
    }

    #[TestWith(['pendentes', 'Hidratação pendente', 'Hidratação verificada'])]
    #[TestWith(['verificadas', 'Hidratação verificada', 'Hidratação pendente'])]
    public function test_abas_separam_os_itens_conforme_o_registro_de_verificacao(string $aba, string $visivel, string $oculto): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $pendente = $this->execucao('Hidratação pendente', $colaborador, $empresa);
        $id = $this->execucao('Hidratação verificada', $colaborador, $empresa);
        $this->verificacao($id, $gerente, 'Orientei a equipe.');
        DB::table('tarefas')->where('tarefa', $visivel)->update(['icone' => '🧭', 'cor_icone' => '#126abc']);

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['aba' => $aba]))
            ->assertOk()
            ->assertSeeText($visivel)
            ->assertSee('background-color: #126abc;', false)
            ->assertSee(rawurlencode('🧭'), false)
            ->assertDontSeeText($oculto);
        $this->get(route('acompanhamento.index', ['aba' => $aba, 'item' => $aba === 'verificadas' ? $id : $pendente]))
            ->assertOk()
            ->assertSee('background-color: #126abc;', false)
            ->assertSee(rawurlencode('🧭'), false);
    }

    public function test_verificadas_listam_e_contam_apenas_as_verificacoes_do_dia_em_brasilia(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 05:00:00', 'UTC'));
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $vigente = $this->execucao('Ronda verificada ontem', $colaborador, $empresa, [
            'previsto_em' => '2026-11-19 01:00:00',
            'agenda_ate' => '2026-11-20 00:00:00',
        ]);
        $encerrada = $this->execucao('Ronda antiga verificada hoje', $colaborador, $empresa, [
            'agenda_ate' => '2026-11-19 03:00:00',
        ]);
        $legada = $this->execucao('Ronda sem agenda verificada hoje', $colaborador, $empresa);
        $this->execucao('Ronda antiga aguardando verificação', $colaborador, $empresa, [
            'agenda_ate' => '2026-11-19 03:00:00',
        ]);
        $this->verificacao($vigente, $gerente, 'Ronda verificada ontem.', '2026-11-19 01:10:00');
        $this->verificacao($encerrada, $gerente, 'Ronda verificada à meia-noite.', '2026-11-19 03:00:00');
        $this->verificacao($legada, $gerente, 'Ronda antiga verificada de madrugada.', '2026-11-19 04:10:00');

        $verificadas = route('acompanhamento.index', ['aba' => 'verificadas', 'busca' => 'ronda']);
        $this->actingAs($gerente)->get($verificadas)
            ->assertOk()->assertSeeText(['Ronda antiga verificada hoje', 'Ronda sem agenda verificada hoje'])
            ->assertDontSeeText('Ronda verificada ontem')
            ->assertViewHas('totais', ['auxilio' => 0, 'nao_realizadas' => 0, 'pendentes' => 1, 'verificadas' => 2]);

        $this->get($verificadas, ['X-Painel-Acompanhamento' => '1'])
            ->assertOk()->assertSeeText(['Ronda antiga verificada hoje', 'Ronda sem agenda verificada hoje'])
            ->assertDontSeeText('Ronda verificada ontem');
        $this->get(route('acompanhamento.index', ['aba' => 'pendentes']))
            ->assertOk()->assertSeeText('Ronda antiga aguardando verificação');

        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->get($verificadas)->assertOk()->assertDontSeeText(['Ronda antiga verificada hoje', 'Ronda sem agenda verificada hoje'])
            ->assertViewHas('totais', ['auxilio' => 0, 'nao_realizadas' => 0, 'pendentes' => 1, 'verificadas' => 0]);
        $this->assertDatabaseCount('rotinas_verificacoes', 3);
    }

    public function test_historico_mostra_autor_data_e_acao_como_texto(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $id = $this->execucao('Hidratação', $gerente, $empresa, ['status' => 'cancelada']);
        $acao = 'Ação <b>registrada</b>';
        $this->verificacao($id, $gerente, $acao);

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['item' => $id]))
            ->assertOk()
            ->assertSeeText(['Verificação concluída', '18/11/2026 às 11:55', 'Rita Souza', $acao])
            ->assertDontSee($acao, false)
            ->assertDontSeeText('Concluir verificação');
    }

    #[TestWith(['detalhe'])]
    #[TestWith(['verificacao'])]
    public function test_execucao_de_outra_unidade_e_indisponivel(string $operacao): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $outraEmpresa = $this->empresa('Unidade Norte');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $outraEmpresa);
        $id = $this->execucao('Hidratação', $colaborador, $outraEmpresa);
        $this->actingAs($gerente);

        $resposta = $operacao === 'detalhe'
            ? $this->get(route('acompanhamento.index', ['item' => $id]))
            : $this->post(route('acompanhamento.store', $id), ['acao' => 'Tentei registrar']);

        $resposta->assertNotFound();
        $this->assertDatabaseEmpty('rotinas_verificacoes');
    }

    public function test_execucao_dentro_da_tolerancia_nao_pode_ser_verificada(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa, ['previsto_em' => '2026-11-18 14:59:30']);

        $this->actingAs($gerente)
            ->post(route('acompanhamento.store', $id), ['acao' => 'Tentei registrar'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('rotinas_verificacoes');
    }

    public function test_paginacao_mantem_contadores_e_filtros_da_lista(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerente = $this->usuario('Rita Souza', 'Gerente', $empresa);
        $colaborador = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $this->execucao('Hidratação mais antiga', $colaborador, $empresa, ['previsto_em' => '2026-11-18 14:45:00']);
        foreach (range(1, 24) as $numero) {
            $this->execucao("Hidratação $numero", $colaborador, $empresa);
        }
        $this->execucao('Refeição que não corresponde à busca', $colaborador, $empresa);

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['aba' => 'pendentes', 'busca' => 'hidratacao', 'page' => 2]))
            ->assertOk()
            ->assertSeeText('Hidratação mais antiga')
            ->assertDontSeeText('Hidratação 24')
            ->assertViewHas('totais', ['auxilio' => 0, 'nao_realizadas' => 0, 'pendentes' => 26, 'verificadas' => 0]);
    }

    #[TestWith(['Cuidadores', 'Enfermagem'])]
    #[TestWith(['Farmácia', 'Enfermagem'])]
    #[TestWith(['Estoque', 'Coordenador'])]
    #[TestWith(['Manutenção', 'Coordenador'])]
    #[TestWith(['Auxiliar de Limpeza', 'Coordenador'])]
    #[TestWith(['Auxiliar de Cozinha', 'Coordenação'])]
    #[TestWith(['Coordenação', 'Coordenador'])]
    #[TestWith(['Coordenação', 'Gerente da Unidade'])]
    #[TestWith(['Enfermagem', 'Gerente da Unidade'])]
    #[TestWith(['Gerente da Unidade', 'Gerente Geral'])]
    public function test_rotina_diaria_entra_na_caixa_destinada_ao_grupo_de_origem(string $origem, string $destino): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', $origem, $empresa);
        $responsavel = $this->usuario('Rita Souza', $destino, $empresa);
        $this->execucao('Tarefa fora do prazo', $colaborador, $empresa);

        $this->actingAs($responsavel)->get(route('acompanhamento.index', ['aba' => 'pendentes']))
            ->assertOk()
            ->assertSeeText('Tarefa fora do prazo');
    }

    #[TestWith(['Enfermagem', '15:05:59', 'No prazo'])]
    #[TestWith(['Enfermagem', '15:06:00', 'Expirando'])]
    #[TestWith(['Enfermagem', '15:20:59', 'Expirando'])]
    #[TestWith(['Enfermagem', '15:21:00', 'Atrasada'])]
    #[TestWith(['Gerente da Unidade', '15:21:00', 'No prazo'])]
    #[TestWith(['Gerente da Unidade', '15:36:00', 'Expirando'])]
    #[TestWith(['Gerente Geral', '15:51:00', 'No prazo'])]
    #[TestWith(['Gerente Geral', '16:21:00', 'Atrasada'])]
    #[TestWith(['Gerente da Unidade', '15:21:00', 'No prazo', 'Estoque'])]
    #[TestWith(['Gerente Geral', '15:21:00', 'No prazo', 'Coordenação'])]
    public function test_cada_caixa_conta_o_sla_desde_o_proprio_recebimento(string $destino, string $hora, string $situacao, string $origem = 'Cuidadores'): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', $origem, $empresa);
        $responsavel = $this->usuario('Rita Souza', $destino, $empresa);
        $this->execucao('Hidratação', $colaborador, $empresa);
        $this->travelTo(CarbonImmutable::parse("2026-11-18 $hora", 'UTC'));

        $this->actingAs($responsavel)->get(route('acompanhamento.index', ['aba' => 'pendentes']))
            ->assertOk()
            ->assertSeeText(['Hidratação', "Acompanhamento · $situacao"]);
    }

    #[TestWith(['Gerente da Unidade', '15:20:59'])]
    #[TestWith(['Gerente Geral', '15:50:59'])]
    #[TestWith(['Coordenador', '16:30:00'])]
    public function test_verificacao_exige_encaminhamento_para_a_caixa_do_usuario(string $destino, string $hora): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Farmácia', $empresa);
        $responsavel = $this->usuario('Rita Souza', $destino, $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);
        $this->travelTo(CarbonImmutable::parse("2026-11-18 $hora", 'UTC'));

        $this->actingAs($responsavel)
            ->post(route('acompanhamento.store', $id), ['acao' => 'Ação registrada'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('rotinas_verificacoes');
    }

    public function test_gerente_geral_ve_apenas_as_execucoes_encaminhadas_a_sua_caixa(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $gerenteGeral = $this->usuario('Rita Souza', 'Gerente Geral', $empresa);
        $enfermagem = $this->usuario('João Silva', 'Enfermagem', $empresa);
        $gerenteDaUnidade = $this->usuario('Bianca Lima', 'Gerente da Unidade', $empresa);
        $this->execucao('Rotina de enfermagem', $enfermagem, $empresa);
        $this->execucao('Rotina de gestão da unidade', $gerenteDaUnidade, $empresa);
        $this->execucao('Rotina da gestão geral', $gerenteGeral, $empresa, ['previsto_em' => '2026-11-18 10:00:00']);

        $this->actingAs($gerenteGeral)->get(route('acompanhamento.index', ['aba' => 'pendentes']))
            ->assertOk()
            ->assertSeeText('Rotina de gestão da unidade')
            ->assertDontSeeText('Rotina de enfermagem')
            ->assertDontSeeText('Rotina da gestão geral');
    }

    public function test_escalonamento_da_coordenacao_preserva_o_primeiro_recebimento_da_gerencia(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $coordenacao = $this->usuario('João Silva', 'Coordenação', $empresa);
        $gerente = $this->usuario('Rita Souza', 'Gerente da Unidade', $empresa);
        $id = $this->execucao('Conferência da unidade', $coordenacao, $empresa);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:21:00', 'UTC'));

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['item' => $id]))
            ->assertOk()
            ->assertSeeText(['18/11/2026 11:51', '18/11/2026 12:21', 'Acompanhamento · Atrasada']);
    }

    public function test_fim_atrasado_preserva_o_vencimento_anterior_do_inicio(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidadores', $empresa);
        $gerente = $this->usuario('Rita Souza', 'Gerente da Unidade', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa, [
            'status' => 'concluida',
            'previsto_em' => '2026-11-18 14:00:00',
            'iniciado_em' => '2026-11-18 14:25:00',
            'concluido_em' => '2026-11-18 14:31:00',
            'duracao_prevista_segundos' => 300,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:31:00', 'UTC'));

        $this->actingAs($gerente)->get(route('acompanhamento.index', ['item' => $id]))
            ->assertOk()
            ->assertSeeText(['18/11/2026 11:31', 'Acompanhamento · No prazo']);
    }

    public function test_verificacao_encerra_o_encaminhamento_para_novas_caixas(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $enfermagem = $this->usuario('Rita Souza', 'Enfermagem', $empresa);
        $colaborador = $this->usuario('João Silva', 'Cuidadores', $empresa);
        $gerenteGeral = $this->usuario('Bianca Lima', 'Gerente Geral', $empresa);
        $id = $this->execucao('Hidratação', $colaborador, $empresa);
        $this->actingAs($enfermagem)->post(route('acompanhamento.store', $id), ['acao' => 'Orientei a equipe.'])
            ->assertSessionHas('success', 'Verificação concluída.');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 16:30:00', 'UTC'));

        $this->actingAs($gerenteGeral)->get(route('acompanhamento.index', ['item' => $id]))
            ->assertNotFound();
    }

    #[TestWith(['Cuidador', 'Enfermagem'])]
    #[TestWith(['Farmácia', 'Enfermagem'])]
    #[TestWith(['Estoque', 'Coordenador'])]
    #[TestWith(['Manutenção', 'Coordenador'])]
    #[TestWith(['Auxiliar de Limpeza', 'Coordenador'])]
    #[TestWith(['Auxiliar de Cozinha', 'Coordenador'])]
    #[TestWith(['Enfermagem', 'Gerente da Unidade'])]
    #[TestWith(['Coordenador', 'Gerente da Unidade'])]
    #[TestWith(['Gerente da Unidade', 'Gerente Geral'])]
    public function test_nao_realizacao_entra_imediatamente_na_caixa_do_superior(string $origem, string $destino): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', $origem, $empresa);
        $superior = $this->usuario('Rita Souza', $destino, $empresa);
        $id = $this->execucaoEmAndamento($colaborador, $empresa);
        DB::table('tarefas')->where('tarefa', 'Hidratação')->update(['icone' => '🥛', 'cor_icone' => 'original']);
        $justificativa = 'Material indisponível <verificar estoque>';

        $this->actingAs($colaborador)->post(route('execucoes.nao-realizada', $id), ['justificativa' => $justificativa])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('home', ['aba' => 'todas']);
        $this->post(route('execucoes.nao-realizada', $id), ['justificativa' => 'Não sobrescrever'])->assertRedirect();
        $this->assertDatabaseCount('rotinas_acomp_nao_realizadas', 1);
        $this->assertDatabaseHas('rotinas_acomp_nao_realizadas', [
            'id_execucao' => $id, 'caixa' => $destino, 'recebido_em' => '2026-11-18 15:00:00', 'verificado_em' => null,
        ]);

        $this->actingAs($superior)->get(route('acompanhamento.index', ['aba' => 'nao_realizadas']))
            ->assertOk()
            ->assertSeeTextInOrder(['Solicitação de Auxílio', 'Tarefas Não Realizadas', 'Tarefas em Atraso', 'Verificadas'])
            ->assertSeeText(['🥛', 'Hidratação', 'Acompanhamento · No prazo']);
        $this->get(route('acompanhamento.index', ['aba' => 'nao_realizadas', 'item' => $id]))
            ->assertOk()
            ->assertSeeText('🥛')
            ->assertSeeTextInOrder(['O Colaborador João Silva não conseguiu realizar a tarefa Hidratação. Justificativa:', $justificativa, 'Sala de Estar'])
            ->assertDontSee($justificativa, false);
    }

    public function test_nao_realizada_conta_o_sla_da_entrada_e_respeita_caixa_e_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $enfermagem = $this->usuario('Rita Souza', 'Enfermagem', $empresa);
        $gerente = $this->usuario('Marcos Reis', 'Gerente da Unidade', $empresa);
        $outraUnidade = $this->usuario('Ana Prado', 'Enfermagem', $this->empresa('Unidade Norte'));
        $id = $this->execucaoEmAndamento($colaborador, $empresa);
        $this->actingAs($colaborador)->post(route('execucoes.nao-realizada', $id), ['justificativa' => 'Morador ausente'])->assertRedirect();
        $detalhe = route('acompanhamento.index', ['aba' => 'nao_realizadas', 'item' => $id]);

        $this->actingAs($outraUnidade)->get($detalhe)->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:14:59', 'UTC'));
        $this->actingAs($enfermagem)->get($detalhe)->assertOk()->assertSeeText(['Acompanhamento · No prazo', '18/11/2026 12:30']);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:15:00', 'UTC'));
        $this->get($detalhe)->assertOk()->assertSeeText('Acompanhamento · Expirando');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:29:59', 'UTC'));
        $this->actingAs($gerente)->get($detalhe)->assertNotFound();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:30:00', 'UTC'));
        $this->actingAs($enfermagem)->get($detalhe)->assertOk()->assertSeeText('Acompanhamento · Atrasada');
        $this->actingAs($gerente)->get($detalhe)->assertOk()->assertSeeText(['Acompanhamento · No prazo', '18/11/2026 13:00']);
    }

    public function test_verificacao_de_nao_realizada_preserva_o_historico_de_atraso_da_mesma_execucao(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $enfermagem = $this->usuario('Rita Souza', 'Enfermagem', $empresa);
        $gerente = $this->usuario('Marcos Reis', 'Gerente da Unidade', $empresa);
        $id = $this->execucaoEmAndamento($colaborador, $empresa);
        $this->actingAs($enfermagem)->post(route('acompanhamento.store', $id), ['acao' => 'Cobrei o início da rotina'])->assertSessionHasNoErrors();
        $this->actingAs($colaborador)->post(route('execucoes.nao-realizada', $id), ['justificativa' => 'Morador ausente'])->assertSessionHasNoErrors();
        $this->actingAs($enfermagem)->post(route('acompanhamento.store', $id), [
            'aba' => 'nao_realizadas', 'tipo' => 'nao_realizada', 'acao' => 'Combinei um novo atendimento',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rotinas_verificacoes', ['id_execucao' => $id, 'acao' => 'Cobrei o início da rotina']);
        $this->assertDatabaseHas('rotinas_acomp_nao_realizadas', ['id_execucao' => $id, 'acao' => 'Combinei um novo atendimento']);
        $this->get(route('acompanhamento.index', ['aba' => 'nao_realizadas']))->assertOk()->assertDontSeeText('Hidratação');
        $this->get(route('acompanhamento.index', ['aba' => 'verificadas', 'item' => $id, 'tipo' => 'nao_realizada']))
            ->assertOk()->assertSeeText(['Morador ausente', 'Combinei um novo atendimento']);

        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:30:00', 'UTC'));
        $this->actingAs($gerente)->get(route('acompanhamento.index', ['aba' => 'nao_realizadas', 'item' => $id]))->assertNotFound();
    }

    #[TestWith(['Cuidador', 'Enfermagem'])]
    #[TestWith(['Farmácia', 'Enfermagem'])]
    #[TestWith(['Estoque', 'Coordenador'])]
    #[TestWith(['Manutenção', 'Coordenador'])]
    #[TestWith(['Auxiliar de Limpeza', 'Coordenador'])]
    #[TestWith(['Auxiliar de Cozinha', 'Coordenador'])]
    #[TestWith(['Enfermagem', 'Gerente da Unidade'])]
    #[TestWith(['Coordenador', 'Gerente da Unidade'])]
    #[TestWith(['Gerente da Unidade', 'Gerente Geral'])]
    public function test_solicitacao_de_auxilio_abre_tarefa_para_o_superior(string $origem, string $destino): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', $origem, $empresa);
        $superior = $this->usuario('Rita Souza', $destino, $empresa);
        $execucao = $this->execucaoEmAndamento($colaborador, $empresa);
        DB::table('tarefas')->where('tarefa', 'Hidratação')->update(['icone' => '💧', 'cor_icone' => '#8b5cf6']);
        $justificativa = 'Preciso de <apoio da equipe>';

        $this->actingAs($colaborador)->post(route('execucoes.auxilio', $execucao), ['justificativa' => $justificativa])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('execucoes.realizando', $execucao)
            ->assertSessionHas('success', "Solicitação de auxílio enviada à caixa de {$destino} na Rotina de Acompanhamento.");

        $solicitacao = DB::table('rotinas_acomp_auxilios')->sole();
        $this->assertDatabaseHas('rotinas_acomp_auxilios', [
            'id_execucao' => $execucao, 'caixa' => $destino, 'justificativa' => $justificativa, 'recebido_em' => '2026-11-18 15:00:00',
        ]);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_execucao' => $execucao, 'status' => 'em_andamento']);

        $this->actingAs($superior)->get(route('acompanhamento.index'))->assertOk()
            ->assertViewHas('aba', 'auxilio')
            ->assertSee('background-color: #8b5cf6;', false)
            ->assertSee(rawurlencode('💧'), false)
            ->assertSeeTextInOrder(['Solicitação de Auxílio', 'Tarefas Não Realizadas', 'Tarefas em Atraso', 'Verificadas'])
            ->assertSeeText(['Hidratação', 'Acompanhamento · No prazo']);
        $this->get(route('acompanhamento.index', ['aba' => 'auxilio', 'item' => $solicitacao->id_solicitacao]))
            ->assertOk()
            ->assertSee('background-color: #8b5cf6;', false)
            ->assertSee(rawurlencode('💧'), false)
            ->assertSeeTextInOrder(['O Colaborador João Silva Solicita Auxílio para realizar a tarefa Hidratação! Justificativa:', $justificativa, 'Sala de Estar'])
            ->assertDontSee($justificativa, false);
    }

    public function test_sla_de_auxilio_e_encaminhamento_usam_cinco_e_dez_minutos_por_caixa(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $enfermagem = $this->usuario('Rita Souza', 'Enfermagem', $empresa);
        $gerente = $this->usuario('Marcos Reis', 'Gerente da Unidade', $empresa);
        $outraUnidade = $this->usuario('Ana Prado', 'Enfermagem', $this->empresa('Unidade Norte'));
        $execucao = $this->execucaoEmAndamento($colaborador, $empresa);
        $this->actingAs($colaborador)->post(route('execucoes.auxilio', $execucao), ['justificativa' => 'Apoio no atendimento'])->assertSessionHasNoErrors();
        $id = DB::table('rotinas_acomp_auxilios')->value('id_solicitacao');
        $detalhe = route('acompanhamento.index', ['aba' => 'auxilio', 'item' => $id]);

        $this->actingAs($outraUnidade)->get($detalhe)->assertNotFound();
        $this->post(route('acompanhamento.store', $id), ['tipo' => 'auxilio', 'acao' => 'Atendimento'])->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:04:59', 'UTC'));
        $this->actingAs($enfermagem)->get($detalhe)->assertOk()->assertSeeText(['Acompanhamento · No prazo', '18/11/2026 12:10']);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:05:00', 'UTC'));
        $this->get($detalhe)->assertOk()->assertSeeText('Acompanhamento · Expirando');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:09:59', 'UTC'));
        $this->actingAs($gerente)->get($detalhe)->assertNotFound();

        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:10:00', 'UTC'));
        $this->actingAs($enfermagem)->get($detalhe)->assertOk()->assertSeeText('Acompanhamento · Atrasada');
        $this->actingAs($gerente)->get($detalhe)->assertOk()->assertSeeText(['Acompanhamento · No prazo', '18/11/2026 12:20']);
    }

    public function test_auxilios_da_mesma_execucao_sao_verificados_individualmente_sem_duplicar_reenvios(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $colaborador = $this->usuario('João Silva', 'Cuidador', $empresa);
        $superior = $this->usuario('Rita Souza', 'Enfermagem', $empresa);
        $execucao = $this->execucaoEmAndamento($colaborador, $empresa);
        $rota = route('execucoes.auxilio', $execucao);
        $protocolo = (string) Str::uuid();

        $this->actingAs($colaborador)->post($rota, ['justificativa' => 'Auxílio com materiais', 'protocolo' => $protocolo])->assertSessionHasNoErrors();
        $this->post($rota, ['justificativa' => 'Reenvio', 'protocolo' => $protocolo])->assertSessionHasNoErrors();
        $this->post($rota, ['justificativa' => 'Apoio no atendimento'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rotinas_acomp_auxilios', 2);
        $id = DB::table('rotinas_acomp_auxilios')->where('protocolo', $protocolo)->value('id_solicitacao');

        $this->actingAs($superior)->post(route('acompanhamento.store', $id), ['tipo' => 'auxilio', 'acao' => 'Materiais entregues'])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('acompanhamento.index', ['aba' => 'auxilio']);
        $this->assertDatabaseHas('rotinas_acomp_auxilios', ['id_solicitacao' => $id, 'acao' => 'Materiais entregues', 'justificativa' => 'Auxílio com materiais']);
        $this->assertDatabaseHas('logs_auditoria', ['registro_id' => (string) $id, 'tabela' => 'rotinas_acomp_auxilios', 'acao' => 'VERIFICAR']);
        $this->get(route('acompanhamento.index'))->assertOk()->assertSeeText('Apoio no atendimento')->assertDontSeeText('Auxílio com materiais');
        $this->get(route('acompanhamento.index', ['aba' => 'verificadas', 'item' => $id, 'tipo' => 'auxilio']))
            ->assertOk()->assertSeeText(['Materiais entregues', 'Auxílio com materiais']);
    }

    private function execucaoEmAndamento(User $colaborador, int $empresa): int
    {
        return $this->execucao('Hidratação', $colaborador, $empresa, [
            'status' => 'em_andamento',
            'iniciado_em' => '2026-11-18 14:54:00',
            'prazo_em' => '2026-11-18 14:59:00',
            'duracao_prevista_segundos' => 300,
        ]);
    }

    private function empresa(string $nome): int
    {
        return DB::table('empresas')->insertGetId(['empresa' => $nome], 'id_empresa');
    }

    private function usuario(string $nome, string $perfil, int $empresa): User
    {
        $grupo = DB::table('grupos_acesso')->insertGetId(['grupo_acesso' => $perfil], 'id_grupo_acesso');
        $id = DB::table('usuarios')->insertGetId([
            'usuario' => $nome,
            'username' => Str::slug($nome),
            'id_grupo_acesso' => $grupo,
            'password' => Hash::make('senha-de-teste'),
        ], 'id_usuario');
        DB::table('empresa_usuario')->insert([
            'id_empresa' => $empresa,
            'id_usuario' => $id,
            'empresa_nome' => DB::table('empresas')->where('id_empresa', $empresa)->value('empresa'),
        ]);

        return User::query()->findOrFail($id);
    }

    private function execucao(string $nome, User $colaborador, int $empresa, array $dados = [], array $rotina = []): int
    {
        $tarefa = DB::table('tarefas')->insertGetId(['tarefa' => $nome, 'status' => true], 'id_tarefa');
        $local = DB::table('locais')->insertGetId(['local' => 'Sala de Estar', 'status' => true], 'id_local');
        $sla = DB::table('slas')->insertGetId(['sla' => 'SLA padrão', 'tempo_limite_minutos' => 5], 'id_sla');
        $idRotina = DB::table('rotinas')->insertGetId([
            'status' => true,
            'id_empresa' => $empresa,
            'id_usuario' => $colaborador->getKey(),
            'dias_semanas' => 'Qua',
            'horario' => '11:50:00',
            'id_tarefa' => $tarefa,
            'id_local' => $local,
            'auxilio' => false,
            ...$rotina,
        ], 'id_rotina');

        return DB::table('rotinas_execucoes')->insertGetId([
            'id_rotina' => $idRotina,
            'id_empresa' => $empresa,
            'id_usuario_responsavel' => $colaborador->getKey(),
            'id_sla' => $sla,
            'previsto_em' => '2026-11-18 14:50:00',
            'prazo_em' => '2026-11-18 14:55:00',
            'tempo_limite_minutos' => 5,
            ...$dados,
        ], 'id_execucao');
    }

    private function verificacao(int $id, User $gerente, string $acao, string $verificadoEm = '2026-11-18 14:55:00'): void
    {
        DB::table('rotinas_verificacoes')->insert([
            'id_execucao' => $id,
            'id_usuario_verificacao' => $gerente->getKey(),
            'acao' => $acao,
            'verificado_em' => $verificadoEm,
        ]);
    }
}
