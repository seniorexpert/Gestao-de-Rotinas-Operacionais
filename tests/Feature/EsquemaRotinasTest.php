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
        $this->assertDatabaseHas($tabela, [$chave => $id, $coluna => 'Registro revisado', 'status' => true]);
    }

    public function test_tarefa_grava_duracao_como_time_e_vincula_a_ferramenta_por_id(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $ferramenta = DB::table('ferramentas')->insertGetId(['ferramenta' => 'Kit de limpeza', 'status' => true], 'id_ferramenta');
        DB::table('ferramentas_empresas')->insert(['id_ferramenta' => $ferramenta, 'id_empresa' => $empresa]);

        $this->actingAs($usuario)->post(route('tarefas.store'), [
            'nome_tarefa' => 'Organizar sala',
            'tempo_tarefa' => '1.5',
            'status_tarefa' => 'Ativa',
            'id_empresas' => [$empresa],
            'id_ferramenta' => $ferramenta,
            'descricao_tarefa' => 'Recolher os materiais.',
        ])->assertSessionHas('success', 'Tarefa salva com sucesso.');

        $this->assertDatabaseHas('tarefas', [
            'tarefa' => 'Organizar sala',
            'tempo_realizar_tarefa' => '00:01:30',
            'id_ferramenta' => $ferramenta,
            'descricao' => 'Recolher os materiais.',
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
            ]], JSON_THROW_ON_ERROR),
        ])->assertSessionHas('success', $emergencial ? 'Tarefas emergenciais salvas com sucesso!' : 'Todas as rotinas foram salvas com sucesso!');

        $idRotina = DB::table('rotinas')->value('id_rotina');
        $this->assertDatabaseHas('rotinas', [
            'id_rotina' => $idRotina, 'id_empresa' => $empresa, 'id_usuario' => $responsavel->getKey(),
            'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'local' => 'Sala de estar', 'horario' => '08:30:00', 'status' => $emergencial, 'auxilio' => true,
            'tipo_rotina' => $emergencial ? ExecucoesDiarias::TIPO_EMERGENCIAL : ExecucoesDiarias::TIPO_DIARIA,
        ]);
        $this->assertDatabaseHas('rotina_auxiliares', ['id_rotina' => $idRotina, 'id_usuario' => $auxiliar->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'rotinas', 'registro_id' => (string) $idRotina]);
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
        $this->get($edicao)->assertOk()->assertSee('id="tarefaRotinaModal"', false)
            ->assertSeeTextInOrder(['Dados da tarefa', 'Dia(s) da semana', 'Observação', 'Salvar tarefa'])->assertSee('08:00:00');

        $this->from($edicao)->put($url, [
            'dias' => ['2', '3'], 'horario' => '10:30', 'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'morador' => 'Moradora Exemplo', 'auxilio' => '1', 'auxiliares' => [$auxiliar->getKey()], 'observacao' => 'Verificar material',
        ])->assertRedirect(route('relatorios.rotinas', ['editar' => $grupo]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $primeira, 'horario' => '10:30:00', 'dias_semanas' => 'Terça-feira, Quarta-feira',
            'morador' => 'Moradora Exemplo', 'auxilio' => true, 'obs' => 'Verificar material']);
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $segunda, 'horario' => '09:00:00', 'dias_semanas' => 'Todos os dias']);
        $this->assertDatabaseHas('rotina_auxiliares', ['id_rotina' => $primeira, 'id_usuario' => $auxiliar->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['id_rotina' => $primeira, 'acao' => 'UPDATE', 'descricao' => 'Tarefa da rotina editada']);
        $this->get(route('home'))->assertViewHas('rotinas', fn ($rotinas) => $rotinas->firstWhere('id_rotina', $primeira)->horario === '08:00 · 24/11/2026');

        $this->travelTo($agora->addDay()->setTime(8, 0));
        $this->get(route('home'))->assertViewHas('rotinas', fn ($rotinas) => $rotinas->firstWhere('id_rotina', $primeira)->horario === '10:30 · 25/11/2026');
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

    public function test_apartamento_do_morador_pode_ser_o_local_da_tarefa(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:15:00', ExecucoesDiarias::FUSO));
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        $tarefa = [
            'dias' => 'Todos os dias', 'horario' => '08:00', 'id_tarefa' => $catalogo['tarefa'],
            'id_local' => null, 'local_morador' => 'Bloco A - 102', 'auxilio' => 'Não',
        ];
        $rotina = fn (array $tarefa): array => [
            'id_empresa' => $empresa, 'colaborador_id' => $usuario->getKey(),
            'tarefas_json' => json_encode([$tarefa], JSON_THROW_ON_ERROR),
        ];

        $this->actingAs($usuario)->post(route('rotinas.store'), $rotina([...$tarefa, 'morador' => 'Nenhum (Tarefa Geral)']))
            ->assertSessionHasErrors('tarefas.0.id_local');
        $this->assertDatabaseEmpty('rotinas');

        $this->post(route('rotinas.store'), $rotina([...$tarefa, 'morador' => 'Maria Exemplo']))->assertSessionHas('success');
        $this->assertDatabaseHas('rotinas', ['morador' => 'Maria Exemplo', 'id_local' => null, 'local' => 'Bloco A - 102']);

        $grupo = $this->cadastrarRotinaComDuasTarefas($empresa, $usuario, $catalogo);
        $primeira = DB::table('rotinas')->where('grupo_rotina', $grupo)->orderBy('horario')->value('id_rotina');
        $edicao = route('relatorios.rotinas', ['editar' => $grupo, 'tarefa' => $primeira]);
        $dados = ['dias' => ['1'], 'horario' => '08:00', 'id_tarefa' => $catalogo['tarefa'], 'auxilio' => '0'];

        $this->from($edicao)->put(route('rotinas.tarefas.update', [$grupo, $primeira]), [
            ...$dados, 'id_local' => 'local-morador', 'local_morador' => 'Bloco B - 201', 'morador' => 'Maria Exemplo',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $primeira, 'id_local' => null, 'local' => 'Bloco B - 201']);

        $this->from($edicao)->put(route('rotinas.tarefas.update', [$grupo, $primeira]), [
            ...$dados, 'id_local' => $catalogo['local'], 'morador' => 'Maria Exemplo',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rotinas', ['id_rotina' => $primeira, 'id_local' => $catalogo['local'], 'local' => 'Sala de estar']);
    }

    public function test_cartoes_de_edicao_exibem_icone_do_cadastro_e_dados_da_tarefa(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $catalogo = $this->catalogo($empresa);
        DB::table('tarefas')->where('id_tarefa', $catalogo['tarefa'])->update(['icone' => '🩺', 'cor_icone' => 'original']);
        $rotina = $this->rotina($empresa, $usuario, $catalogo);
        DB::table('rotinas')->where('id_rotina', $rotina)->update([
            'morador' => 'Maria Exemplo, Helena Exemplo', 'auxilio' => true, 'dias_semanas' => implode(', ', ExecucoesDiarias::DIAS_DA_SEMANA),
        ]);

        $this->actingAs($usuario)->get(route('relatorios.rotinas', ['editar' => $rotina]))->assertOk()
            ->assertSeeTextInOrder(['🩺', 'Organizar sala', 'Horário:', '08:00', 'Dias:', 'Todos os dias',
                'Moradores:', 'Maria Exemplo, Helena Exemplo', 'Local:', 'Sala de estar', 'Auxílio:', 'Sim', 'Editar', 'Remover', 'Adicionar nova tarefa']);
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
        $this->get($adicionar)->assertOk()->assertSee('id="tarefaRotinaModal"', false)->assertSee($salvar)
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
            'id_local' => $catalogo['local'], 'morador' => 'Moradora Exemplo', 'obs' => 'Verificar material', 'auxilio' => true,
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
