<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogosPorUnidadeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_vinculos_de_ferramentas_e_locais_controlam_cadastro_consulta_e_edicao(): void
    {
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $sul = Empresa::create(['empresa' => 'Unidade Sul']);
        $norte = Empresa::create(['empresa' => 'Unidade Norte']);
        $responsavel = $this->usuario('responsavel', $centro, $sul);
        $colaborador = $this->usuario('sul', $sul);
        $externo = $this->usuario('norte', $norte);
        $unidades = [$centro->getKey(), $sul->getKey()];

        $this->actingAs($responsavel)->get(route('processos.gestao-rotinas'))->assertOk()
            ->assertViewHas('unidadePadrao', $centro->getKey())
            ->assertSeeText(['Unidade Centro', 'Unidade Sul'])
            ->assertDontSeeText('Unidade Norte');
        $this->post(route('ferramentas.store'), ['nome_ferramenta' => 'Kit compartilhado', 'id_empresas' => $unidades])
            ->assertSessionHas('success');
        $ferramenta = DB::table('ferramentas')->sole();
        $this->assertDatabaseHas('ferramentas', ['id_ferramenta' => $ferramenta->id_ferramenta, 'status' => 1]);
        $this->assertSame($unidades, DB::table('ferramentas_empresas')->orderBy('id_empresa')->pluck('id_empresa')->all());

        $this->post(route('locais.store'), ['nome_local' => 'Sala do Centro', 'id_empresa' => $centro->getKey(), 'status' => '0'])
            ->assertSessionHas('success');
        $local = DB::table('locais')->sole();
        $this->assertDatabaseHas('locais', ['id_local' => $local->id_local, 'status' => 0]);
        $this->assertDatabaseHas('locais_empresas', ['id_local' => $local->id_local, 'id_empresa' => $centro->getKey()]);

        $this->post(route('locais.store'), ['nome_local' => 'Local indevido', 'id_empresa' => $norte->getKey()])
            ->assertSessionHasErrors('id_empresa');
        $this->post(route('ferramentas.store'), ['nome_ferramenta' => 'Kit indevido', 'id_empresas' => [$centro->getKey(), $norte->getKey()]])
            ->assertSessionHasErrors('id_empresas');

        $this->actingAs($colaborador)->get(route('relatorios.ferramentas'))->assertOk()
            ->assertViewHas('ferramentas', fn ($itens) => $itens->sole()->ids_empresas === [$sul->getKey()] && ! $itens->sole()->editavel)
            ->assertSeeText('Kit Compartilhado')->assertDontSeeText('Unidade Centro');
        $this->get(route('relatorios.locais'))->assertOk()->assertDontSeeText('Sala do Centro');
        $this->post(route('ferramentas.store'), [
            'id' => $ferramenta->id_ferramenta, 'nome_ferramenta' => 'Nome indevido', 'id_empresas' => [$sul->getKey()],
        ])->assertNotFound();
        $this->actingAs($externo)->get(route('relatorios.ferramentas'))->assertOk()->assertDontSeeText('Kit Compartilhado');

        $this->actingAs($responsavel)->post(route('ferramentas.store'), [
            'id' => $ferramenta->id_ferramenta, 'nome_ferramenta' => 'Kit compartilhado', 'id_empresas' => $unidades, 'status' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ferramentas', ['id_ferramenta' => $ferramenta->id_ferramenta, 'status' => 0]);
        $this->post(route('locais.store'), ['id' => $local->id_local, 'nome_local' => 'Sala do Centro', 'id_empresa' => $centro->getKey(), 'status' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('locais', ['id_local' => $local->id_local, 'status' => 1]);
    }

    public function test_tarefa_compartilhada_aceita_ferramenta_de_qualquer_unidade_selecionada(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 11:10:00', 'UTC'));
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $sul = Empresa::create(['empresa' => 'Unidade Sul']);
        $norte = Empresa::create(['empresa' => 'Unidade Norte']);
        $responsavel = $this->usuario('responsavel', $centro, $sul, $norte);
        $externo = $this->usuario('norte', $norte);
        $this->actingAs($responsavel);
        foreach ([[$centro, 'Kit do Centro'], [$sul, 'Kit do Sul'], [$norte, 'Kit do Norte']] as [$unidade, $nome]) {
            $this->post(route('ferramentas.store'), ['nome_ferramenta' => $nome, 'id_empresas' => [$unidade->getKey()]])
                ->assertSessionHasNoErrors();
        }
        $kits = DB::table('ferramentas')->pluck('id_ferramenta', 'ferramenta');
        $dados = [
            'nome_tarefa' => 'Organizar materiais', 'tempo_tarefa' => 5, 'status_tarefa' => 'Ativa',
            'id_empresas' => [$centro->getKey(), $sul->getKey()], 'id_ferramenta' => $kits['Kit do Centro'],
        ];
        $this->post(route('tarefas.store'), [...$dados, 'id_ferramenta' => $kits['Kit do Norte']])->assertSessionHasErrors('id_ferramenta');
        $this->assertDatabaseEmpty('tarefas');

        $this->post(route('tarefas.store'), $dados)->assertSessionHas('success');
        $tarefa = DB::table('tarefas')->sole();
        $this->assertSame($kits['Kit do Centro'], $tarefa->id_ferramenta);
        $this->assertSame($dados['id_empresas'], DB::table('tarefas_empresas')->where('id_tarefa', $tarefa->id_tarefa)->orderBy('id_empresa')->pluck('id_empresa')->all());
        $this->post(route('ferramentas.store'), [
            'id' => $kits['Kit do Centro'], 'nome_ferramenta' => 'Kit do Centro', 'id_empresas' => [$norte->getKey()],
        ])->assertSessionHasErrors('id_empresas');

        foreach ($dados['id_empresas'] as $unidade) {
            DB::table('rotinas')->insert([
                'id_empresa' => $unidade, 'id_usuario' => $responsavel->getKey(),
                'id_tarefa' => $tarefa->id_tarefa, 'dias_semanas' => 'Terça-feira', 'horario' => '08:00:00',
                'status' => true, 'auxilio' => false,
            ]);
        }
        $this->get(route('home'))->assertOk()
            ->assertViewHas('rotinas', fn ($rotinas) => $rotinas->count() === 2
                && $rotinas->where('id_empresa', $centro->getKey())->sole()->ferramenta === 'Kit do Centro'
                && $rotinas->where('id_empresa', $sul->getKey())->sole()->ferramenta === null);

        $this->post(route('tarefas.store'), [...$dados, 'id' => $tarefa->id_tarefa, 'id_ferramenta' => $kits['Kit do Sul']])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('tarefas', ['id_tarefa' => $tarefa->id_tarefa, 'id_ferramenta' => $kits['Kit do Sul']]);
        $this->actingAs($externo)->get(route('relatorios.tarefas'))->assertOk()
            ->assertDontSeeText(['Organizar Materiais', 'Kit do Centro', 'Kit do Sul']);
        $this->post(route('tarefas.store'), [...$dados, 'id' => $tarefa->id_tarefa, 'id_empresas' => [$norte->getKey()]])->assertNotFound();
    }

    public function test_rotina_diaria_usa_catalogo_ativo_da_unidade_e_grava_situacao(): void
    {
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $sul = Empresa::create(['empresa' => 'Unidade Sul']);
        $usuario = $this->usuario('responsavel', $centro, $sul);
        $this->actingAs($usuario)->post(route('tarefas.store'), [
            'nome_tarefa' => 'Organizar sala', 'tempo_tarefa' => 5, 'status_tarefa' => 'Ativa', 'id_empresas' => [$sul->getKey()],
        ])->assertSessionHasNoErrors();
        $this->post(route('locais.store'), ['nome_local' => 'Sala', 'id_empresa' => $centro->getKey(), 'status' => '0'])
            ->assertSessionHasNoErrors();
        $idTarefa = DB::table('tarefas')->value('id_tarefa');
        $idLocal = DB::table('locais')->value('id_local');
        $rotina = [
            'id_empresa' => $centro->getKey(), 'colaborador_id' => $usuario->getKey(), 'status_rotina' => '0',
            'tarefas_json' => json_encode([[
                'dias' => 'Segunda-feira', 'horario' => '08:00', 'id_tarefa' => $idTarefa, 'id_local' => $idLocal, 'auxilio' => 'Não',
            ]], JSON_THROW_ON_ERROR),
        ];
        $this->post(route('rotinas.store'), $rotina)->assertSessionHasErrors('tarefas.0.id_tarefa');
        DB::table('tarefas_empresas')->insert(['id_tarefa' => $idTarefa, 'id_empresa' => $centro->getKey()]);
        $this->post(route('rotinas.store'), $rotina)->assertSessionHasErrors('tarefas.0.id_local');
        $this->assertDatabaseEmpty('rotinas');

        $this->post(route('locais.store'), ['id' => $idLocal, 'nome_local' => 'Sala', 'id_empresa' => $centro->getKey(), 'status' => '1'])
            ->assertSessionHasNoErrors();
        $this->post(route('rotinas.store'), $rotina)->assertSessionHas('success');
        $this->assertDatabaseHas('rotinas', ['id_tarefa' => $idTarefa, 'id_local' => $idLocal, 'status' => 0]);
        $this->assertDatabaseEmpty('rotinas_execucoes');
    }

    private function usuario(string $login, Empresa ...$empresas): User
    {
        $usuario = User::create(['usuario' => ucfirst($login), 'username' => $login, 'password' => 'senha-de-teste', 'status' => true]);
        foreach ($empresas as $empresa) {
            $usuario->empresas()->attach($empresa, ['empresa_nome' => $empresa->empresa]);
        }

        return $usuario;
    }
}
