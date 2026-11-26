<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EsquemaRotinasTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['processos.index', 'Organizar sala'])]
    #[TestWith(['processos.gestao-rotinas', 'Organizar sala'])]
    #[TestWith(['monitoramento.index', 'Organizar sala'])]
    #[TestWith(['relatorios.tarefas', 'Organizar sala'])]
    #[TestWith(['relatorios.locais', 'Sala de estar'])]
    #[TestWith(['relatorios.ferramentas', 'Kit de limpeza'])]
    #[TestWith(['relatorios.rotinas', 'Unidade Centro'])]
    #[TestWith(['relatorios.excel', 'Organizar sala'])]
    #[TestWith(['relatorios.logs-rotinas', 'Rotina cadastrada'])]
    public function test_telas_carregam_os_registros_do_esquema_atual(string $rota, string $texto): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $rotina = $this->rotina($empresa, $usuario, $catalogo);
        DB::table('logs_auditoria')->insert([
            'id_usuario' => $usuario->getKey(),
            'tabela' => 'rotinas',
            'registro_id' => (string) $rotina,
            'acao' => 'CREATE',
            'descricao' => 'Rotina cadastrada',
            'origem' => 'web',
            'created_at' => now(),
        ]);

        $this->actingAs($usuario)->get(route($rota))->assertOk()->assertSeeText($texto);
    }

    public function test_api_retorna_os_ids_e_nomes_dos_colaboradores_da_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $this->usuario('Usuário de outra unidade', $this->empresa('Unidade Sul'));

        $this->actingAs($usuario)->getJson(route('processos.usuarios-por-unidade', ['id_empresa' => $empresa]))
            ->assertOk()
            ->assertExactJson([
                ['id_usuario' => $responsavel->getKey(), 'usuario' => 'Ana Exemplo'],
                ['id_usuario' => $usuario->getKey(), 'usuario' => 'Joana Exemplo'],
            ]);
    }

    public function test_api_rejeita_unidade_sem_vinculo_com_o_usuario(): void
    {
        $usuario = $this->usuario('Joana Exemplo', $this->empresa('Unidade Centro'));
        $outraEmpresa = $this->empresa('Unidade Sul');

        $this->actingAs($usuario)->getJson(route('processos.usuarios-por-unidade', ['id_empresa' => $outraEmpresa]))
            ->assertNotFound();
    }

    #[TestWith(['ferramentas', 'id_ferramenta', 'ferramenta'])]
    #[TestWith(['locais', 'id_local', 'local'])]
    public function test_cadastros_e_edicoes_usam_as_colunas_do_catalogo(string $tabela, string $chave, string $coluna): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $campo = 'nome_'.$coluna;
        $unidades = $tabela === 'locais' ? ['id_empresa' => $empresa] : ['id_empresas' => [$empresa]];

        $this->actingAs($usuario)->post(route($tabela.'.store'), [$campo => 'Registro inicial', ...$unidades])->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table($tabela)->value($chave);
        $this->post(route($tabela.'.store'), ['id' => $id, $campo => 'Registro revisado', ...$unidades])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount($tabela, 1);
        $this->assertDatabaseHas($tabela, [$chave => $id, $coluna => 'Registro Revisado', 'status' => true]);
    }

    public function test_tarefa_grava_duracao_como_time_e_vincula_a_ferramenta_por_id(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $ferramenta = DB::table('ferramentas')->insertGetId(['ferramenta' => 'Kit de limpeza', 'status' => true], 'id_ferramenta');
        DB::table('ferramentas_empresas')->insert(['id_ferramenta' => $ferramenta, 'id_empresa' => $empresa]);

        $this->actingAs($usuario)->post(route('tarefas.store'), [
            'nome_tarefa' => 'a ORGANIZAÇÃO DOS quartos e AS áreas comuns',
            'tempo_tarefa' => '1.5',
            'status_tarefa' => 'Ativa',
            'id_empresas' => [$empresa],
            'id_ferramenta' => $ferramenta,
            'descricao_tarefa' => "recolher OS materiais DE limpeza.\nos responsáveis devem consultar https://exemplo.test/Guia/AbC E avisar ÀS equipes por plantao@example.test",
        ])->assertSessionHas('success', 'Tarefa salva com sucesso.');

        $this->assertDatabaseHas('tarefas', [
            'tarefa' => 'A Organização dos Quartos e as Áreas Comuns',
            'tempo_realizar_tarefa' => '00:01:30',
            'id_ferramenta' => $ferramenta,
            'descricao' => "Recolher os Materiais de Limpeza.\nOs Responsáveis Devem Consultar https://exemplo.test/Guia/AbC e Avisar às Equipes por plantao@example.test",
            'status' => true,
            'icone' => '📋',
            'cor_icone' => 'original',
        ]);
    }

    #[TestWith(['rotinas.store'])]
    #[TestWith(['rotinas.emergenciais.store'])]
    public function test_formulario_grava_rotina_na_unidade_selecionada_e_registra_auxiliar(string $rota): void
    {
        $emergencial = $rota === 'rotinas.emergenciais.store';
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $auxiliar = $this->usuario('Rita Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);

        $this->actingAs($usuario)->post(route($rota), [
            'id_empresa' => $empresa,
            'colaborador_id' => $responsavel->getKey(),
            'status_rotina' => 'Inativa',
            'tarefas_json' => json_encode([[
                'dias' => 'Segunda-feira',
                'horario' => '08:30',
                'id_tarefa' => $catalogo['tarefa'],
                'id_local' => $catalogo['local'],
                'auxilio' => 'Sim',
                'cuidador' => $auxiliar->getKey(),
                'observacao' => 'checar A iluminação',
            ]], JSON_THROW_ON_ERROR),
        ])->assertSessionHas('success', $emergencial ? 'Tarefas emergenciais salvas com sucesso!' : 'Todas as rotinas foram salvas com sucesso!');

        $idRotina = DB::table('rotinas')->value('id_rotina');
        $this->assertDatabaseHas('rotinas', [
            'id_rotina' => $idRotina, 'id_empresa' => $empresa, 'id_usuario' => $responsavel->getKey(),
            'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'local' => 'Sala de estar', 'horario' => '08:30:00', 'status' => $emergencial, 'auxilio' => true,
            'obs' => 'Checar a Iluminação',
            'tipo_rotina' => $emergencial ? ExecucoesDiarias::TIPO_EMERGENCIAL : ExecucoesDiarias::TIPO_DIARIA,
        ]);
        $this->assertDatabaseHas('rotina_auxiliares', ['id_rotina' => $idRotina, 'id_usuario' => $auxiliar->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'rotinas', 'registro_id' => (string) $idRotina]);
    }

    #[TestWith(['rotinas.store'])]
    #[TestWith(['rotinas.emergenciais.store'])]
    public function test_cadastro_grava_todos_os_colaboradores_auxiliares_de_cada_tarefa(string $rota): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26 07:00:00', ExecucoesDiarias::FUSO));
        $empresa = $this->empresa('Unidade Centro');
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $primeiroAuxiliar = $this->usuario('Joana Exemplo', $empresa);
        $segundoAuxiliar = $this->usuario('Rita Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $auxiliares = [$primeiroAuxiliar->getKey(), $segundoAuxiliar->getKey()];
        $dadosDaTarefa = [
            'dias' => 'Quinta-feira', 'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'auxilio' => 'Sim', 'auxiliares' => $auxiliares,
        ];

        $this->actingAs($responsavel)->post(route($rota), [
            'id_empresa' => $empresa, 'colaborador_id' => $responsavel->getKey(),
            'tarefas_json' => json_encode([
                $dadosDaTarefa + ['horario' => '08:00'],
                $dadosDaTarefa + ['horario' => '09:00'],
            ], JSON_THROW_ON_ERROR),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseCount('rotina_auxiliares', 4);
        foreach (DB::table('rotinas')->pluck('id_rotina') as $id) {
            $this->assertSame($auxiliares, DB::table('rotina_auxiliares')->where('id_rotina', $id)->orderBy('id_usuario')->pluck('id_usuario')->all());
        }
    }

    public function test_cadastro_rejeita_auxiliares_de_outra_unidade_e_o_proprio_responsavel(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $externo = $this->usuario('Rita Exemplo', $this->empresa('Unidade Sul'));
        $catalogo = $this->catalogo($empresa);

        $this->actingAs($responsavel)->post(route('rotinas.store'), [
            'id_empresa' => $empresa, 'colaborador_id' => $responsavel->getKey(),
            'tarefas_json' => json_encode([[
                'dias' => 'Quinta-feira', 'horario' => '08:00',
                'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
                'auxilio' => 'Sim', 'auxiliares' => [$externo->getKey(), $responsavel->getKey()],
            ]], JSON_THROW_ON_ERROR),
        ])->assertSessionHasErrors(['tarefas.0.auxiliares.0', 'tarefas.0.auxiliares.1']);

        $this->assertDatabaseEmpty('rotinas');
    }

    public function test_atribuicao_altera_o_responsavel_na_tabela_rotinas(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $rotina = $this->rotina($empresa, $usuario, $this->catalogo($empresa));

        $this->actingAs($usuario)->post(route('rotinas.atribuir'), [
            'rotinas_ids' => [$rotina],
            'novo_colaborador_id' => $responsavel->getKey(),
        ])->assertSessionHas('success', 'Tarefas atribuídas com sucesso!');

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $rotina, 'id_usuario' => $responsavel->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'rotinas', 'registro_id' => (string) $rotina, 'acao' => 'UPDATE']);
    }

    public function test_relatorio_troca_o_colaborador_da_rotina_sem_mesclar_outro_cadastro(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:15:00', ExecucoesDiarias::FUSO));
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $outraUnidade = $this->usuario('Usuário de outra unidade', $this->empresa('Unidade Sul'));
        $catalogo = $this->catalogo($empresa);
        $this->actingAs($usuario);
        $grupo = $this->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $outroGrupo = $this->cadastrarRotinaComDuasTarefas($empresa, $responsavel, $catalogo);
        [$primeira, $segunda] = DB::table('rotinas')->where('grupo_rotina', $grupo)->orderBy('horario')->pluck('id_rotina');
        $emAndamento = DB::table('rotinas_execucoes')->where('id_rotina', $primeira)->sole();
        $this->post(route('execucoes.iniciar', $emAndamento->id_execucao))->assertSessionHasNoErrors();

        $edicao = route('relatorios.rotinas', ['editar' => $grupo]);
        $this->get($edicao)->assertOk()->assertSeeTextInOrder(['Editar rotina', 'Colaborador(a)', 'Situação da rotina', 'Organizar sala'])
            ->assertDontSeeText('Usuário de outra unidade');
        $this->from($edicao)->put(route('rotinas.update', $grupo), ['colaborador_id' => $outraUnidade->getKey(), 'status' => '1'])
            ->assertRedirect($edicao)->assertSessionHasErrors('colaborador_id');
        $this->put(route('rotinas.update', $grupo), ['colaborador_id' => $responsavel->getKey(), 'status' => '1'])
            ->assertRedirect(route('relatorios.rotinas'))->assertSessionHasNoErrors();

        foreach ([$primeira, $segunda] as $id) {
            $this->assertDatabaseHas('rotinas', ['id_rotina' => $id, 'grupo_rotina' => $grupo, 'id_usuario' => $responsavel->getKey()]);
        }
        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $primeira, 'status' => 'em_andamento', 'id_usuario_responsavel' => $usuario->getKey()]);
        $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $segunda, 'status' => 'pendente', 'id_usuario_responsavel' => $responsavel->getKey()]);
        $this->get(route('relatorios.rotinas'))->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 2
            && $rotinas->pluck('grupo_rotina')->sort()->values()->all() === collect([$grupo, $outroGrupo])->sort()->values()->all());
    }

    public function test_filtro_de_morador_combina_com_colaborador_e_mostra_a_rotina_inteira(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $outroColaborador = $this->usuario('Rita Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $outraRotina = $this->rotina($empresa, $outroColaborador, $catalogo);
        $this->rotina($empresa, $usuario, $catalogo);
        DB::table('rotinas')->where('grupo_rotina', $grupo)->where('horario', '09:00:00')
            ->update(['morador' => 'Maria Exemplo, Helena Souza', 'status' => false]);
        DB::table('rotinas')->where('id_rotina', $outraRotina)->update(['morador' => 'Helena Souza']);
        $filtros = ['colaborador' => 'Joana Exemplo', 'morador' => 'hELENA'];
        $relatorio = route('relatorios.rotinas', $filtros);
        $edicao = route('relatorios.rotinas', [...$filtros, 'editar' => $grupo]);

        $this->get($relatorio)->assertOk()->assertSee($edicao)->assertSee('value="hELENA"', false)
            ->assertSeeTextInOrder(['08:00 – 09:00', '2 tarefas', 'Parcialmente ativa', '1 rotina encontrada'])
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 1 && $rotinas->first()->grupo_rotina === $grupo);
        $this->get($edicao)->assertOk()->assertSee($relatorio)
            ->assertSee(route('rotinas.update', ['grupo' => $grupo, ...$filtros]))
            ->assertViewHas('tarefasEdicao', fn ($tarefas) => $tarefas->count() === 2);
        $this->put(route('rotinas.update', ['grupo' => $grupo, ...$filtros]), [
            'colaborador_id' => $usuario->getKey(), 'status' => '1',
        ])->assertRedirect($relatorio);
    }

    public function test_filtro_de_morador_respeita_a_unidade_e_ignora_tarefas_removidas(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $outraEmpresa = $this->empresa('Unidade Sul');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $foraDaUnidade = $this->usuario('Rita Exemplo', $outraEmpresa);
        $catalogo = $this->catalogo($empresa);
        $rotinaEncontrada = $this->rotina($empresa, $usuario, $catalogo);
        $rotinaComTarefaRemovida = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $rotinaDeOutraUnidade = $this->rotina($outraEmpresa, $foraDaUnidade, $this->catalogo($outraEmpresa));
        DB::table('rotinas')->whereIn('id_rotina', [$rotinaEncontrada, $rotinaDeOutraUnidade])->update(['morador' => 'Sofia Exemplo']);
        DB::table('rotinas')->where('grupo_rotina', $rotinaComTarefaRemovida)->where('horario', '09:00:00')
            ->update(['morador' => 'Sofia Exemplo', 'excluida_em' => now()]);

        $this->get(route('relatorios.rotinas', ['morador' => 'Sofia']))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->pluck('id_rotina_principal')->all() === [$rotinaEncontrada]);
    }

    public function test_filtro_de_morador_trata_caracteres_de_busca_como_parte_do_nome(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $rotina = $this->rotina($empresa, $usuario, $catalogo);
        $outraRotina = $this->rotina($empresa, $usuario, $catalogo);
        DB::table('rotinas')->where('id_rotina', $rotina)->update(['morador' => 'Ana_Exemplo']);
        DB::table('rotinas')->where('id_rotina', $outraRotina)->update(['morador' => 'Ana Exemplo']);

        $this->actingAs($usuario)->get(route('relatorios.rotinas', ['morador' => 'Ana_']))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->pluck('id_rotina_principal')->all() === [$rotina]);
    }

    public function test_rotina_inativa_permanece_na_agenda_mas_bloqueia_execucao_e_geracao(): void
    {
        $agora = CarbonImmutable::parse('2026-11-24 07:15:00', ExecucoesDiarias::FUSO);
        $this->travelTo($agora);
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $this->catalogo($empresa));
        [$pendente, $emAndamento] = DB::table('rotinas_execucoes')->orderBy('id_execucao')->pluck('id_execucao');
        $atualizar = route('rotinas.update', $grupo);
        $this->post(route('execucoes.iniciar', $emAndamento))->assertSessionHasNoErrors();

        $this->put($atualizar, ['colaborador_id' => $usuario->getKey(), 'status' => '0'])->assertSessionHas('success');

        $this->assertDatabaseMissing('rotinas', ['grupo_rotina' => $grupo, 'status' => true]);
        $this->get(route('relatorios.rotinas'))->assertSeeText('Inativa');
        $this->get(route('home', ['aba' => 'todas']))->assertSeeText('Organizar sala')->assertSeeText('Rotina inativa')
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 2
                && $rotinas->every(fn ($execucao) => $execucao->execucao_bloqueada && $execucao->acao_execucao === null));
        $this->post(route('execucoes.iniciar', $pendente))->assertNotFound();
        $this->get(route('execucoes.realizando', $emAndamento))->assertNotFound();
        $this->post(route('execucoes.concluir', $emAndamento), ['comentario' => 'Tentei concluir'])->assertNotFound();
        $this->travelTo($agora->addDay());
        $this->get(route('home'))->assertOk();
        $this->assertDatabaseCount('rotinas_execucoes', 2);

        $this->put($atualizar, ['colaborador_id' => $usuario->getKey(), 'status' => '1'])->assertSessionHas('success');

        $this->assertDatabaseMissing('rotinas', ['grupo_rotina' => $grupo, 'status' => false]);
        $this->get(route('execucoes.realizando', $emAndamento))->assertOk();
        $this->get(route('home'))->assertSeeText('Organizar sala');
        $this->assertDatabaseCount('rotinas_execucoes', 4);
    }

    public function test_relatorio_edita_uma_tarefa_e_aplica_a_nova_programacao_as_proximas_execucoes(): void
    {
        $agora = CarbonImmutable::parse('2026-11-24 07:15:00', ExecucoesDiarias::FUSO);
        $this->travelTo($agora);
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $auxiliar = $this->usuario('Rita Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        [$primeira, $segunda] = DB::table('rotinas')->where('grupo_rotina', $grupo)->orderBy('horario')->pluck('id_rotina');
        $edicao = route('relatorios.rotinas', ['editar' => $grupo, 'tarefa' => $primeira]);
        $url = route('rotinas.tarefas.update', [$grupo, $primeira]);
        $this->get(route('relatorios.rotinas', ['editar' => $grupo]))->assertSee($edicao);
        $this->get($edicao)->assertOk()->assertSee('id="tarefaRotinaModal"', false)->assertSee('step="60" value="08:00"', false)
            ->assertSeeTextInOrder(['Dados da tarefa', 'Dia(s) da semana', 'Observação', 'Salvar tarefa']);

        $this->from($edicao)->put($url, [
            'dias' => ['2', '3'], 'horario' => '10:30', 'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'morador' => 'Moradora Exemplo', 'auxilio' => '1', 'auxiliares' => [$auxiliar->getKey()], 'observacao' => 'Verificar material',
        ])->assertRedirect(route('relatorios.rotinas', ['editar' => $grupo]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $primeira, 'horario' => '10:30:00', 'dias_semanas' => 'Terça-feira, Quarta-feira',
            'morador' => 'Moradora Exemplo', 'auxilio' => true, 'obs' => 'Verificar Material']);
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $segunda, 'horario' => '09:00:00', 'dias_semanas' => 'Todos os dias']);
        $this->assertDatabaseHas('rotina_auxiliares', ['id_rotina' => $primeira, 'id_usuario' => $auxiliar->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['id_rotina' => $primeira, 'acao' => 'UPDATE', 'descricao' => 'Tarefa da rotina editada']);
        $this->get(route('home'))->assertViewHas('rotinas', fn ($rotinas) => $rotinas->firstWhere('id_rotina', $primeira)->horario === '08:00 · 24/11/2026');

        $this->travelTo($agora->addDay()->setTime(8, 0));
        $this->get(route('home'))->assertViewHas('rotinas', fn ($rotinas) => $rotinas->where('id_rotina', $primeira)->pluck('horario')->all() === [
            '08:00 · 24/11/2026',
            '10:30 · 25/11/2026',
        ]);
    }

    public function test_edicao_exibe_horas_e_minutos_e_preserva_o_horario_quando_o_minuto_nao_muda(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $tarefa = $this->rotina($empresa, $usuario, $catalogo);
        DB::table('rotinas')->where('id_rotina', $tarefa)->update(['horario' => '08:15:30']);
        $edicao = route('relatorios.rotinas', ['editar' => $tarefa, 'tarefa' => $tarefa]);

        $this->actingAs($usuario)->get($edicao)->assertOk()->assertSee('step="60" value="08:15"', false);
        $this->from($edicao)->put(route('rotinas.tarefas.update', [$tarefa, $tarefa]), [
            'dias' => ['1'], 'horario' => '08:15', 'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'auxilio' => '0', 'observacao' => 'Conferir material',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $tarefa, 'horario' => '08:15:30', 'obs' => 'Conferir Material']);
    }

    public function test_edicao_de_tarefa_da_rotina_valida_o_grupo_e_o_catalogo_da_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $outroCatalogo = $this->catalogo($this->empresa('Unidade Sul'));
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $outroGrupo = $this->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $tarefa = DB::table('rotinas')->where('grupo_rotina', $grupo)->orderBy('id_rotina')->value('id_rotina');
        $edicao = route('relatorios.rotinas', ['editar' => $grupo, 'tarefa' => $tarefa]);

        $this->put(route('rotinas.tarefas.update', [$outroGrupo, $tarefa]))->assertNotFound();
        $this->from($edicao)->put(route('rotinas.tarefas.update', [$grupo, $tarefa]), [
            'dias' => ['2'], 'horario' => '10:30', 'id_tarefa' => $outroCatalogo['tarefa'], 'id_local' => $catalogo['local'], 'auxilio' => '0',
        ])->assertRedirect($edicao)->assertSessionHasErrors(['id_tarefa' => 'Selecione uma tarefa ativa da unidade da rotina.']);
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $tarefa, 'id_tarefa' => $catalogo['tarefa'], 'horario' => '08:00:00']);
    }

    public function test_cartoes_de_edicao_exibem_icone_do_cadastro_e_dados_da_tarefa(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $ana = $this->usuario('Ana Exemplo', $empresa);
        $paula = $this->usuario('Paula Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        DB::table('tarefas')->where('id_tarefa', $catalogo['tarefa'])->update(['icone' => '🩺', 'cor_icone' => 'original']);
        $rotina = $this->rotina($empresa, $usuario, $catalogo);
        DB::table('rotinas')->where('id_rotina', $rotina)->update([
            'morador' => 'Maria Exemplo, Helena Exemplo', 'auxilio' => true, 'dias_semanas' => implode(', ', ExecucoesDiarias::DIAS_DA_SEMANA),
        ]);
        DB::table('rotina_auxiliares')->insert([
            ['id_rotina' => $rotina, 'id_usuario' => $paula->getKey()],
            ['id_rotina' => $rotina, 'id_usuario' => $ana->getKey()],
        ]);

        $this->actingAs($usuario)->get(route('relatorios.rotinas', ['editar' => $rotina]))->assertOk()
            ->assertSeeTextInOrder(['🩺', 'Organizar sala', 'Horário:', '08:00', 'Dias:', 'Todos os dias',
                'Moradores:', 'Maria Exemplo, Helena Exemplo', 'Local:', 'Sala de estar', 'Auxílio:', 'Sim - Ana Exemplo; Paula Exemplo',
                'Editar', 'Remover', 'Adicionar nova tarefa']);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_adicionar_tarefa_preserva_o_grupo_o_colaborador_e_a_situacao_da_rotina(bool $ativa): void
    {
        $agora = CarbonImmutable::parse('2026-11-24 07:15:00', ExecucoesDiarias::FUSO);
        $this->travelTo($agora);
        $empresa = $this->empresa('Unidade Centro');
        $editor = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Rita Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $grupo = $this->actingAs($editor)->cadastrarRotinaComDuasTarefas($empresa, $responsavel, $catalogo);
        if (! $ativa) {
            $this->put(route('rotinas.update', $grupo), ['colaborador_id' => $responsavel->getKey(), 'status' => '0'])->assertSessionHasNoErrors();
        }
        $edicao = route('relatorios.rotinas', ['editar' => $grupo]);
        $adicionar = route('relatorios.rotinas', ['editar' => $grupo, 'adicionar_tarefa' => '1']);
        $salvar = route('rotinas.tarefas.store', $grupo);
        $this->get($edicao)->assertSee($adicionar);
        $this->get($adicionar)->assertOk()->assertSee('id="tarefaRotinaModal"', false)->assertSee('step="60" value=""', false)->assertSee($salvar)
            ->assertSeeTextInOrder(['Dados da tarefa', 'Dia(s) da semana', 'Observação', 'Adicionar à rotina']);

        $this->from($adicionar)->post($salvar, [
            'dias' => ['2'], 'horario' => '08:30', 'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'morador' => 'Moradora Exemplo', 'auxilio' => '1', 'auxiliares' => [$editor->getKey()], 'observacao' => 'Verificar material',
            'colaborador_id' => $editor->getKey(), 'status' => '1',
        ])->assertRedirect($edicao)->assertSessionHasNoErrors();

        $id = DB::table('rotinas')->latest('id_rotina')->value('id_rotina');
        $this->assertDatabaseHas('rotinas', [
            'id_rotina' => $id, 'grupo_rotina' => $grupo, 'id_empresa' => $empresa, 'id_usuario' => $responsavel->getKey(),
            'status' => $ativa, 'horario' => '08:30:00', 'dias_semanas' => 'Terça-feira', 'id_tarefa' => $catalogo['tarefa'],
            'id_local' => $catalogo['local'], 'morador' => 'Moradora Exemplo', 'obs' => 'Verificar Material', 'auxilio' => true,
        ]);
        $this->assertDatabaseHas('rotina_auxiliares', ['id_rotina' => $id, 'id_usuario' => $editor->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['id_rotina' => $id, 'acao' => 'CREATE', 'descricao' => 'Tarefa adicionada à rotina']);
        if ($ativa) {
            $this->assertDatabaseHas('rotinas_execucoes', ['id_rotina' => $id, 'id_usuario_responsavel' => $responsavel->getKey()]);
        } else {
            $this->assertDatabaseMissing('rotinas_execucoes', ['id_rotina' => $id]);
        }
        $this->get($edicao)->assertOk()->assertViewHas('tarefasEdicao', fn ($tarefas) => $tarefas->count() === 3);
    }

    public function test_adicionar_tarefa_rejeita_catalogo_de_outra_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $outroCatalogo = $this->catalogo($this->empresa('Unidade Sul'));
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $adicionar = route('relatorios.rotinas', ['editar' => $grupo, 'adicionar_tarefa' => '1']);

        $this->from($adicionar)->post(route('rotinas.tarefas.store', $grupo), [
            'dias' => ['2'], 'horario' => '08:30', 'id_tarefa' => $outroCatalogo['tarefa'], 'id_local' => $catalogo['local'], 'auxilio' => '0',
        ])->assertRedirect($adicionar)->assertSessionHasErrors(['id_tarefa' => 'Selecione uma tarefa ativa da unidade da rotina.']);

        $this->assertDatabaseCount('rotinas', 2);
    }

    public function test_remocao_de_tarefa_e_exclusao_de_rotina_preservam_os_registros(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:15:00', ExecucoesDiarias::FUSO));
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $this->catalogo($empresa));
        [$primeira, $segunda] = DB::table('rotinas')->where('grupo_rotina', $grupo)->orderBy('horario')->pluck('id_rotina');
        $outroGrupo = $this->cadastrarRotinaComDuasTarefas($empresa, $usuario, $this->catalogo($empresa));
        $edicao = route('relatorios.rotinas', ['editar' => $grupo]);

        $this->delete(route('rotinas.tarefas.destroy', [$outroGrupo, $primeira]))->assertNotFound();
        $this->delete(route('rotinas.tarefas.destroy', [$grupo, $primeira]))->assertRedirect($edicao);

        $this->assertSoftDeleted('rotinas', ['id_rotina' => $primeira], deletedAtColumn: 'excluida_em');
        $this->get($edicao)->assertOk()->assertDontSee(route('rotinas.tarefas.destroy', [$grupo, $primeira]), false)
            ->assertSee(route('rotinas.tarefas.destroy', [$grupo, $segunda]), false);

        $this->delete(route('rotinas.destroy', $grupo))->assertRedirect(route('relatorios.rotinas'));

        $this->get($edicao)->assertNotFound();
        $this->assertSoftDeleted('rotinas', ['id_rotina' => $segunda], deletedAtColumn: 'excluida_em');
        $this->assertDatabaseCount('rotinas_execucoes', 4);
        $this->assertDatabaseHas('logs_auditoria', ['id_rotina' => $primeira, 'acao' => 'DELETE', 'descricao' => 'Tarefa removida da rotina']);
        $this->assertDatabaseHas('logs_auditoria', ['id_rotina' => $segunda, 'acao' => 'DELETE', 'descricao' => 'Rotina excluída do relatório']);
        $this->get(route('home'))->assertViewHas('rotinas', fn ($rotinas) => ! $rotinas->whereIn('id_rotina', [$primeira, $segunda])->count());
    }

    public function test_relatorio_protege_rotina_de_outra_unidade_em_leituras_e_alteracoes(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $foraDaUnidade = $this->usuario('Rita Exemplo', $this->empresa('Unidade Sul'));
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $this->catalogo($empresa));

        $this->actingAs($foraDaUnidade)->get(route('relatorios.rotinas', ['editar' => $grupo]))->assertNotFound();
        $this->put(route('rotinas.update', $grupo), ['colaborador_id' => $foraDaUnidade->getKey(), 'status' => '0'])->assertNotFound();
        $this->post(route('rotinas.tarefas.store', $grupo))->assertNotFound();
        $this->delete(route('rotinas.destroy', $grupo))->assertNotFound();

        $this->assertDatabaseMissing('rotinas', ['grupo_rotina' => $grupo, 'status' => false]);
        $this->assertDatabaseCount('rotinas', 2);
    }

    public function test_relatorio_respeita_permissao_apenas_de_visualizacao(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $editor = $this->usuario('Joana Exemplo', $empresa);
        $leitor = $this->usuario('Rita Exemplo', $empresa);
        DB::table('permissoes_usuarios')->insert(['id_usuario' => $leitor->getKey(), 'modulo' => 'rotinas', 'nivel' => 1]);
        $grupo = $this->actingAs($editor)->cadastrarRotinaComDuasTarefas($empresa, $editor, $this->catalogo($empresa));

        $this->actingAs($leitor)->get(route('relatorios.rotinas'))->assertOk()->assertDontSeeText('Editar');
        $this->get(route('relatorios.rotinas', ['editar' => $grupo]))->assertForbidden();
        $this->put(route('rotinas.update', $grupo))->assertForbidden();
        $this->post(route('rotinas.tarefas.store', $grupo))->assertForbidden();
        $this->delete(route('rotinas.destroy', $grupo))->assertForbidden();
    }

    public function test_atribuicao_de_uma_tarefa_no_monitoramento_preserva_o_restante_da_rotina(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Rita Exemplo', $empresa);
        $grupo = $this->actingAs($usuario)->cadastrarRotinaComDuasTarefas($empresa, $usuario, $this->catalogo($empresa));
        [$primeira, $segunda] = DB::table('rotinas')->where('grupo_rotina', $grupo)->orderBy('horario')->pluck('id_rotina');

        $this->post(route('rotinas.atribuir'), ['rotinas_ids' => [$primeira], 'novo_colaborador_id' => $responsavel->getKey()])
            ->assertSessionHas('success');

        $this->get(route('relatorios.rotinas', ['editar' => $grupo]))->assertOk()
            ->assertViewHas('tarefasEdicao', fn ($tarefas) => (int) $tarefas->sole()->id_rotina === (int) $segunda);
        $this->get(route('relatorios.rotinas'))->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 2);
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $primeira, 'id_usuario' => $responsavel->getKey()]);
    }

    public function test_atribuicao_rejeita_um_responsavel_sem_vinculo_na_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $this->empresa('Unidade Sul'));
        $rotina = $this->rotina($empresa, $usuario, $this->catalogo($empresa));

        $this->actingAs($usuario)->post(route('rotinas.atribuir'), [
            'rotinas_ids' => [$rotina],
            'novo_colaborador_id' => $responsavel->getKey(),
        ])->assertSessionHasErrors('novo_colaborador_id');

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $rotina, 'id_usuario' => $usuario->getKey()]);
    }

    public function test_grade_excel_exporta_os_mesmos_horarios_e_detalhes_filtrados_por_morador_e_grupo(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $outraEmpresa = $this->empresa('Unidade Sul');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $outroGrupo = $this->usuario('Rita Exemplo', $empresa);
        $foraDaUnidade = $this->usuario('Pessoa de outra unidade', $outraEmpresa);
        $cuidador = DB::table('grupos_acesso')->where('grupo_acesso', 'Cuidador')->value('id_grupo_acesso');
        $enfermagem = DB::table('grupos_acesso')->where('grupo_acesso', 'Enfermagem')->value('id_grupo_acesso');
        DB::table('usuarios')->whereIn('id_usuario', [$usuario->getKey(), $foraDaUnidade->getKey()])->update(['id_grupo_acesso' => $cuidador]);
        DB::table('usuarios')->where('id_usuario', $outroGrupo->getKey())->update(['id_grupo_acesso' => $enfermagem]);

        $catalogo = $this->catalogo($empresa);
        DB::table('tarefas')->where('id_tarefa', $catalogo['tarefa'])->update(['icone' => '💊', 'cor_icone' => 'original']);
        $recepcao = $this->rotina($empresa, $usuario, $catalogo);
        $madrugada = $this->rotina($empresa, $usuario, $catalogo);
        $outroMorador = $this->rotina($empresa, $usuario, $catalogo);
        $rotinaEnfermagem = $this->rotina($empresa, $outroGrupo, $catalogo);
        $rotinaExterna = $this->rotina($outraEmpresa, $foraDaUnidade, $this->catalogo($outraEmpresa));
        DB::table('rotinas')->whereIn('id_rotina', [$recepcao, $madrugada, $rotinaEnfermagem, $rotinaExterna])->update(['morador' => 'Maria Exemplo, Helena Souza']);
        DB::table('rotinas')->where('id_rotina', $recepcao)->update(['horario' => '22:00:00', 'local' => 'Quarto 02', 'dias_semanas' => 'Terça-feira, Quinta-feira']);
        DB::table('rotinas')->where('id_rotina', $madrugada)->update(['horario' => '02:00:00', 'dias_semanas' => 'Todos']);
        DB::table('rotinas')->where('id_rotina', $outroMorador)->update(['morador' => 'Pedro Exemplo']);
        $filtros = ['morador' => 'hELENA', 'grupo' => $cuidador];
        $exportacao = route('relatorios.excel.exportar', $filtros);

        $this->actingAs($usuario)->get(route('relatorios.excel', $filtros))->assertOk()->assertSee($exportacao)
            ->assertSeeText('Joana Exemplo')->assertSeeText('💊')->assertSeeText('2 tarefas encontradas')
            ->assertSeeText('Maria Exemplo, Helena Souza · Quarto 02')->assertSeeText('Terça-feira, Quinta-feira')
            ->assertViewHas('horarios', fn ($horarios) => $horarios->all() === ['22:00', '02:00'])
            ->assertViewHas('colaboradores', fn ($colaboradores) => $colaboradores->pluck('id_usuario')->all() === [$usuario->getKey()]);
        $planilha = $this->get($exportacao)->assertDownload()->streamedContent();

        foreach (['Joana Exemplo', 'Cuidador', '2 tarefas encontradas', '💊', 'Maria Exemplo, Helena Souza · Quarto 02', 'Terça-feira, Quinta-feira', 'Todos os dias'] as $texto) {
            $this->assertStringContainsString($texto, $planilha);
        }
        $this->assertLessThan(strpos($planilha, '02:00'), strpos($planilha, '22:00'));
        foreach (['Pedro Exemplo', 'Rita Exemplo', 'Pessoa de outra unidade'] as $texto) {
            $this->assertStringNotContainsString($texto, $planilha);
        }
    }

    public function test_filtro_de_colaborador_se_combina_com_morador_e_grupo_na_grade_excel(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $outroColaborador = $this->usuario('Rita Exemplo', $empresa);
        $grupo = DB::table('grupos_acesso')->where('grupo_acesso', 'Cuidador')->value('id_grupo_acesso');
        DB::table('usuarios')->whereIn('id_usuario', [$usuario->getKey(), $outroColaborador->getKey()])->update(['id_grupo_acesso' => $grupo]);
        $catalogo = $this->catalogo($empresa);
        $rotinas = [$this->rotina($empresa, $usuario, $catalogo), $this->rotina($empresa, $outroColaborador, $catalogo)];
        DB::table('rotinas')->whereIn('id_rotina', $rotinas)->update(['morador' => 'Helena Souza']);
        $filtros = ['colaborador' => 'Rita Exemplo', 'morador' => 'Helena', 'grupo' => $grupo];

        $this->actingAs($usuario)->get(route('relatorios.excel', $filtros))->assertOk()
            ->assertViewHas('colaboradores', fn ($colaboradores) => $colaboradores->pluck('id_usuario')->all() === [$outroColaborador->getKey()]);
        $planilha = $this->get(route('relatorios.excel.exportar', $filtros))->assertDownload()->streamedContent();

        $this->assertStringContainsString('Rita Exemplo', $planilha);
        $this->assertStringNotContainsString('Joana Exemplo', $planilha);
    }

    public function test_grade_e_planilha_ordenam_horarios_a_partir_das_seis_da_manha(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);

        foreach (['23:00', '05:59', '06:00', '00:00', '03:00', '09:30'] as $horario) {
            $id = $this->rotina($empresa, $usuario, $catalogo);
            DB::table('rotinas')->where('id_rotina', $id)->update(['horario' => $horario.':00']);
        }

        $horariosEsperados = ['06:00', '09:30', '23:00', '00:00', '03:00', '05:59'];
        $pagina = $this->actingAs($usuario)->get(route('relatorios.excel'))->assertOk();
        $this->assertSame($horariosEsperados, $pagina->viewData('horarios')->all());

        $planilha = $this->get(route('relatorios.excel.exportar'))->assertDownload()->streamedContent();
        preg_match_all('/<tr\b[^>]*>\s*<td\b[^>]*>(\d{2}:\d{2})<\/td>/', $planilha, $horariosNaPlanilha);
        $this->assertSame($horariosEsperados, $horariosNaPlanilha[1]);
    }

    #[TestWith(['relatorios.excel.exportar', 'Organizar sala'])]
    #[TestWith(['relatorios.logs.excel.exportar', 'Rotina cadastrada'])]
    public function test_exportacoes_usam_registros_do_banco_e_retornam_download(string $rota, string $texto): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $rotina = $this->rotina($empresa, $usuario, $this->catalogo($empresa));
        DB::table('logs_auditoria')->insert([
            'id_usuario' => $usuario->getKey(),
            'tabela' => 'rotinas',
            'registro_id' => (string) $rotina,
            'acao' => 'CREATE',
            'descricao' => 'Rotina cadastrada',
            'origem' => 'web',
            'created_at' => now(),
        ]);

        $resposta = $this->actingAs($usuario)->get(route($rota))->assertDownload();
        $this->assertStringContainsString($texto, $resposta->streamedContent());
    }

    private function empresa(string $nome): int
    {
        return DB::table('empresas')->insertGetId(['empresa' => $nome], 'id_empresa');
    }

    /** @param array{tarefa: int, local: int} $catalogo */
    private function cadastrarRotinaComDuasTarefas(int $empresa, User $responsavel, array $catalogo): string
    {
        $tarefas = array_map(fn (string $horario): array => [
            'dias' => 'Todos os dias', 'horario' => $horario, 'id_tarefa' => $catalogo['tarefa'],
            'id_local' => $catalogo['local'], 'auxilio' => 'Não',
        ], ['08:00', '09:00']);
        $this->post(route('rotinas.store'), [
            'id_empresa' => $empresa, 'colaborador_id' => $responsavel->getKey(),
            'tarefas_json' => json_encode($tarefas, JSON_THROW_ON_ERROR),
        ])->assertSessionHas('success');

        return DB::table('rotinas')->latest('id_rotina')->value('grupo_rotina');
    }

    private function usuario(string $nome, int $empresa): User
    {
        $id = DB::table('usuarios')->insertGetId([
            'usuario' => $nome,
            'username' => str($nome)->slug(),
            'password' => Hash::make('senha-de-teste'),
        ], 'id_usuario');
        DB::table('empresa_usuario')->insert([
            'id_empresa' => $empresa, 'id_usuario' => $id,
            'empresa_nome' => DB::table('empresas')->where('id_empresa', $empresa)->value('empresa'),
        ]);

        return User::query()->findOrFail($id);
    }

    /** @return array{tarefa: int, local: int} */
    private function catalogo(int $empresa): array
    {
        $ferramenta = DB::table('ferramentas')->insertGetId(['ferramenta' => 'Kit de limpeza', 'status' => true], 'id_ferramenta');
        $tarefa = DB::table('tarefas')->insertGetId([
            'tarefa' => 'Organizar sala',
            'tempo_realizar_tarefa' => '00:10:00',
            'id_ferramenta' => $ferramenta,
            'status' => true,
        ], 'id_tarefa');
        $local = DB::table('locais')->insertGetId(['local' => 'Sala de estar', 'status' => true], 'id_local');
        DB::table('ferramentas_empresas')->insert(['id_ferramenta' => $ferramenta, 'id_empresa' => $empresa]);
        DB::table('tarefas_empresas')->insert(['id_tarefa' => $tarefa, 'id_empresa' => $empresa]);
        DB::table('locais_empresas')->insert(['id_local' => $local, 'id_empresa' => $empresa]);

        return compact('tarefa', 'local');
    }

    /** @param array{tarefa: int, local: int} $catalogo */
    private function rotina(int $empresa, User $usuario, array $catalogo): int
    {
        return DB::table('rotinas')->insertGetId([
            'id_empresa' => $empresa, 'id_usuario' => $usuario->getKey(),
            'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'dias_semanas' => 'Segunda-feira', 'horario' => '08:00:00',
            'auxilio' => false, 'status' => true,
        ], 'id_rotina');
    }
}
