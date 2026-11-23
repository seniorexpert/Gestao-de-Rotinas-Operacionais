<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
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

    public function test_login_abre_as_rotinas_do_responsavel_e_exibe_o_atalho_de_acompanhamento(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:00:00', 'UTC'));
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

        $colega = DB::table('usuarios')->insertGetId([
            'usuario' => 'Ana Exemplo',
            'username' => 'ana-exemplo',
            'password' => Hash::make('senha-de-teste'),
        ], 'id_usuario');
        DB::table('empresa_usuario')->insert([
            'id_empresa' => $unidade,
            'id_usuario' => $colega,
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
            [...$rotina, 'id_tarefa' => $this->tarefa('Tarefa de outro colaborador'), 'horario' => '10:00:00', 'status' => true,
                'id_usuario' => $colega],
        ]);

        $this->followingRedirects()->post('/login', [
            'username' => 'maria-exemplo',
            'password' => 'senha-de-teste',
        ])
            ->assertOk()
            ->assertViewIs('home')
            ->assertSeeTextInOrder(['Rotinas Diárias', 'Rotina de Acompanhamento', 'Centro de Monitoramento'])
            ->assertSee('href="'.route('acompanhamento.index').'"', false)
            ->assertSeeTextInOrder(['Pendentes', 'Em andamento', 'Concluídas', 'Todas'])
            ->assertViewHas('aba', 'pendente')
            ->assertSeeTextInOrder(['Acompanhar o lanche', 'Sala de Estar', '15:30', 'No prazo'])
            ->assertDontSee('15:30 – 16:00')
            ->assertDontSee('Organizar o quarto')
            ->assertDontSee('Tarefa de outra unidade')
            ->assertDontSee('Tarefa de outro colaborador');

        $execucao = DB::table('rotinas_execucoes')->where('id_usuario_responsavel', $gerente)->sole();
        DB::table('rotinas')->where('id_rotina', $execucao->id_rotina)->update(['id_usuario' => $colega]);
        DB::table('rotinas_execucoes')->where('id_execucao', $execucao->id_execucao)->update([
            'status' => 'em_andamento', 'iniciado_em' => '2026-11-18 13:50:00', 'prazo_em' => '2026-11-18 14:20:00',
        ]);

        $this->get(route('home', ['aba' => 'todas', 'id_usuario' => $colega]))
            ->assertOk()
            ->assertViewHas('aba', 'todas')
            ->assertViewHas('rotinas', fn ($rotinas) => (int) $rotinas->sole()->id_execucao === (int) $execucao->id_execucao)
            ->assertSeeText('Realizando')
            ->assertDontSee('Tarefa de outro colaborador');
    }

    private function tarefa(string $nome): int
    {
        return DB::table('tarefas')->insertGetId([
            'tarefa' => $nome, 'status' => true, 'tempo_realizar_tarefa' => '00:30:00',
        ], 'id_tarefa');
    }
}
