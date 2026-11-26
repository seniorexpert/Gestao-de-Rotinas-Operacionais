<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermissaoAcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_menu_de_permissoes_fica_no_perfil_do_admin_e_central_de_ajuda_sai_da_navegacao(): void
    {
        $admin = $this->usuario('admin');
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Gerente Geral', 'status' => true]);
        $gerente = $this->usuario('gestor', $grupo);
        $linkPermissoes = 'href="'.route('permissoes.index').'"';

        $paginaDoAdmin = $this->actingAs($admin)->get(route('home'))->assertOk()
            ->assertSeeInOrder(['</aside>', 'id="profileDropdownMenu"', $linkPermissoes], false)
            ->assertDontSeeText('Central de Ajuda');
        $this->assertSame(1, substr_count($paginaDoAdmin->getContent(), $linkPermissoes));

        $this->actingAs($gerente)->get(route('home'))->assertOk()
            ->assertSeeInOrder(['id="profileDropdownMenu"', 'href="'.route('usuarios.index').'"'], false)
            ->assertDontSee($linkPermissoes, false)
            ->assertDontSeeText('Central de Ajuda');
        $this->get(route('permissoes.index'))->assertForbidden();
    }

    public function test_admin_configura_permissoes_de_grupo_e_excecoes_individuais(): void
    {
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Equipe operacional', 'status' => true]);
        $admin = $this->usuario('admin');
        $colaborador = $this->usuario('ana', $grupo);
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $colaborador->empresas()->attach($unidade, ['empresa_nome' => $unidade->empresa]);
        $regrasDoGrupo = route('permissoes.update', ['grupo', $grupo->getKey()]);
        $regrasIndividuais = route('permissoes.update', ['usuario', $colaborador->getKey()]);

        $this->actingAs($admin)->get(route('permissoes.index'))->assertOk()
            ->assertSeeTextInOrder(['Permissões de Acesso', 'Por grupo', 'Por usuário'])
            ->assertSee('name="regras[rotinas_diarias]"', false);
        $this->put($regrasDoGrupo, ['regras' => ['tarefas' => '1', 'monitoramento' => '0']])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'permissoes_grupos_acesso', 'acao' => 'UPDATE', 'id_usuario' => $admin->getKey()]);

        $this->actingAs($colaborador)->get(route('relatorios.tarefas'))->assertOk()->assertDontSeeText('Novo registro')
            ->assertDontSee('href="'.route('monitoramento.index').'"', false);
        $this->post(route('tarefas.store'))->assertForbidden();
        $this->get(route('monitoramento.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('permissoes.index', ['tipo' => 'usuario', 'id' => $colaborador->getKey()]))
            ->assertOk()->assertSeeText('Herdar do grupo · Visualizar');
        $this->put($regrasIndividuais, ['regras' => ['tarefas' => '2', 'locais' => '0', 'monitoramento' => '1']])->assertSessionHasNoErrors();
        $this->actingAs($colaborador)->get(route('relatorios.tarefas'))->assertOk()->assertSeeText('Novo registro');
        $this->post(route('tarefas.store'), [
            'nome_tarefa' => 'Conferir materiais', 'tempo_tarefa' => '5', 'status_tarefa' => 'Ativa', 'id_empresas' => [$unidade->getKey()],
        ])->assertSessionHas('success');
        $this->get(route('relatorios.locais'))->assertForbidden();
        $this->get(route('monitoramento.index'))->assertOk()->assertDontSeeText('Atribuir tarefa');
        $this->post(route('rotinas.atribuir'))->assertForbidden();

        $this->actingAs($admin)->put($regrasIndividuais, ['regras' => ['tarefas' => 'herdar']])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('permissoes_usuarios', ['id_usuario' => $colaborador->getKey(), 'modulo' => 'tarefas']);
        $this->actingAs($colaborador)->post(route('tarefas.store'))->assertForbidden();
    }

    public function test_conta_admin_tem_protecao_contra_regras_e_alteracoes_por_outros_usuarios(): void
    {
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Gerente Geral', 'status' => true]);
        $admin = $this->usuario('admin', $grupo);
        $gerente = $this->usuario('gestor', $grupo);
        $urlAdmin = route('permissoes.index', ['tipo' => 'usuario', 'id' => $admin->getKey()]);
        $regraAdmin = route('permissoes.update', ['usuario', $admin->getKey()]);

        $this->actingAs($gerente)->get(route('usuarios.index'))->assertOk()->assertDontSee('data-usuario-id="'.$admin->getKey().'"', false);
        $this->get($urlAdmin)->assertForbidden();
        $this->put($regraAdmin, ['regras' => ['usuarios' => '0']])->assertForbidden();
        $this->put(route('users.update', $admin->getKey()), ['usuario' => 'Admin', 'password' => 'nova-senha'])->assertForbidden();
        $this->post(route('users.store'), ['usuario' => 'Outra conta', 'username' => 'ADMIN', 'password' => 'nova-senha'])
            ->assertSessionHasErrors('username');
        $this->assertTrue(Hash::check('senha-de-teste', $admin->fresh()->password));

        $this->actingAs($admin)->get($urlAdmin)->assertOk()->assertSeeText('tem acesso integral')->assertDontSeeText('Salvar permissões');
        $this->put($regraAdmin, ['regras' => ['usuarios' => '0']])->assertForbidden();
        $this->put(route('users.update', $admin->getKey()), ['usuario' => 'Admin', 'status' => '0'])->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->status);
    }

    public function test_admin_atende_execucao_e_acompanhamento_de_unidade_sem_vinculo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-23 13:00:00', 'UTC'));
        $empresa = Empresa::create(['empresa' => 'Unidade Sul']);
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Cuidador', 'status' => true]);
        $admin = $this->usuario('admin', $grupo);
        $colaborador = $this->usuario('ana', $grupo);
        $colaborador->empresas()->attach($empresa->getKey(), ['empresa_nome' => $empresa->empresa]);
        $tarefa = DB::table('tarefas')->insertGetId([
            'tarefa' => 'Revisão da agenda', 'tempo_realizar_tarefa' => '00:15:00', 'status' => true,
        ], 'id_tarefa');
        DB::table('rotinas')->insert([
            'id_empresa' => $empresa->getKey(), 'id_usuario' => $colaborador->getKey(), 'id_tarefa' => $tarefa,
            'horario' => '10:00:00', 'dias_semanas' => 'Todos os dias', 'status' => true, 'auxilio' => false,
        ]);

        $this->actingAs($admin)->put(route('permissoes.update', ['grupo', $grupo->getKey()]), [
            'regras' => ['rotinas_diarias' => '0', 'acompanhamento' => '0', 'usuarios' => '0'],
        ])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSeeText('Revisão da agenda');
        $this->get(route('usuarios.index'))->assertOk();
        $id = DB::table('rotinas_execucoes')->value('id_execucao');
        $this->post(route('execucoes.iniciar', $id))->assertSessionHasNoErrors();
        $this->post(route('execucoes.auxilio', $id), ['justificativa' => 'Apoio para revisar a agenda.'])->assertSessionHasNoErrors();

        $pedido = DB::table('rotinas_acomp_auxilios')->sole();
        $this->assertSame('Enfermagem', $pedido->caixa);
        $this->get(route('acompanhamento.index'))->assertOk()
            ->assertSeeText(['Todas as caixas', 'Apoio para Revisar a Agenda.']);
        $this->post(route('acompanhamento.store', $pedido->id_solicitacao), [
            'tipo' => 'auxilio', 'acao' => 'Apoio providenciado',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rotinas_acomp_auxilios', [
            'id_solicitacao' => $pedido->id_solicitacao, 'id_usuario_verificacao' => $admin->getKey(),
        ]);

        $this->actingAs($colaborador)->get(route('home'))->assertForbidden();
        $this->get(route('execucoes.foto', $id))->assertForbidden();
        $this->post(route('execucoes.concluir', $id))->assertForbidden();
        $this->get(route('dashboard'))->assertRedirectToRoute('monitoramento.index');
    }

    public function test_permissao_de_usuarios_respeita_unidades_e_impede_aumento_de_privilegios(): void
    {
        $gerencia = GrupoAcesso::create(['grupo_acesso' => 'Gerente Geral', 'status' => true]);
        $admin = $this->usuario('admin');
        $editor = $this->usuario('editor');
        $colega = $this->usuario('colega');
        $externo = $this->usuario('externo');
        $sul = Empresa::create(['empresa' => 'Unidade Sul']);
        $norte = Empresa::create(['empresa' => 'Unidade Norte']);

        foreach ([$editor, $colega] as $usuario) {
            $usuario->empresas()->attach($sul->getKey(), ['empresa_nome' => $sul->empresa]);
        }
        $externo->empresas()->attach($norte->getKey(), ['empresa_nome' => $norte->empresa]);

        $this->actingAs($admin)->put(route('permissoes.update', ['usuario', $editor->getKey()]), ['regras' => ['usuarios' => '2']])
            ->assertSessionHasNoErrors();
        $this->actingAs($editor)->get(route('usuarios.index'))->assertOk()->assertSeeText('colega')->assertDontSeeText('externo');
        $this->put(route('users.update', $colega->getKey()), ['usuario' => 'Colega revisado'])->assertSessionHasNoErrors();
        $this->put(route('users.update', $externo->getKey()), ['usuario' => 'Nome alterado'])->assertForbidden();
        $this->put(route('users.update', $editor->getKey()), ['usuario' => 'editor', 'empresas' => [$norte->getKey()]])->assertForbidden();
        $this->put(route('users.update', $editor->getKey()), ['usuario' => 'editor', 'id_grupo_acesso' => $gerencia->getKey()])->assertForbidden();
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $colega->getKey(), 'usuario' => 'Colega Revisado']);
    }

    private function usuario(string $login, ?GrupoAcesso $grupo = null): User
    {
        return User::create([
            'usuario' => ucfirst($login), 'username' => $login, 'password' => 'senha-de-teste',
            'id_grupo_acesso' => $grupo?->getKey(), 'status' => true,
        ]);
    }
}
