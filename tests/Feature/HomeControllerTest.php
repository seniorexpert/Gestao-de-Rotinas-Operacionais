<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get(route('home'))->assertRedirectToRoute('login');
    }

    public function test_login_abre_as_rotinas_da_unidade_e_exibe_o_atalho_de_acompanhamento(): void
    {
        $unidade = DB::table('empresas')->insertGetId(['empresa' => 'Unidade Centro'], 'id_empresa');
        $outraUnidade = DB::table('empresas')->insertGetId(['empresa' => 'Unidade Norte'], 'id_empresa');
        $grupo = DB::table('grupos_acesso')->insertGetId(['grupo_acesso' => 'Gerente Geral'], 'id_grupo_acesso');
        $gerente = DB::table('usuarios')->insertGetId([
            'usuario' => 'Maria Exemplo',
            'username' => 'maria-exemplo',
            'password' => Hash::make('senha-de-teste'),
            'id_grupo_acesso' => $grupo,
        ], 'id_usuario');

        DB::table('empresa_usuario')->insert([
            'id_empresa' => $unidade,
            'id_usuario' => $gerente,
            'empresa_nome' => 'Unidade Centro',
        ]);

        $local = DB::table('locais')->insertGetId(['local' => 'Sala de Estar', 'status' => true], 'id_local');
        $rotina = [
            'id_empresa' => $unidade,
            'id_usuario' => $gerente,
            'dias_semanas' => 'Qua',
            'id_local' => $local,
            'auxilio' => false,
        ];

        DB::table('rotinas')->insert([
            [...$rotina, 'id_tarefa' => $this->tarefa('Acompanhar o lanche'), 'horario' => '15:30:00', 'status' => true],
            [...$rotina, 'id_tarefa' => $this->tarefa('Organizar o quarto'), 'horario' => '09:15:00', 'status' => false],
            [...$rotina, 'id_tarefa' => $this->tarefa('Tarefa de outra unidade'), 'horario' => '08:00:00', 'status' => true,
                'id_empresa' => $outraUnidade],
        ]);

        $this->followingRedirects()->post('/login', [
            'username' => 'maria-exemplo',
            'password' => 'senha-de-teste',
        ])
            ->assertOk()
            ->assertViewIs('home')
            ->assertSeeTextInOrder(['Rotinas Diárias', 'Rotina de Acompanhamento', 'Centro de Monitoramento'])
            ->assertSee('href="'.route('acompanhamento.index').'"', false)
            ->assertSeeTextInOrder(['Organizar o quarto', 'Sala de Estar', '09:15', 'Inativa', 'Acompanhar o lanche'])
            ->assertDontSeeText('Tarefa de outra unidade');
    }

    private function tarefa(string $nome): int
    {
        return DB::table('tarefas')->insertGetId(['tarefa' => $nome, 'status' => true], 'id_tarefa');
    }
}
