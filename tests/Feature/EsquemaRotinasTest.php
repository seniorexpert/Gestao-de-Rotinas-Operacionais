<?php

namespace Tests\Feature;

use App\Models\User;
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
        $catalogo = $this->catalogo();
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
        $usuario = $this->usuario('Joana Exemplo', $this->empresa('Unidade Centro'));
        $campo = 'nome_'.$coluna;

        $this->actingAs($usuario)->post(route($tabela.'.store'), [$campo => 'Registro inicial'])->assertRedirect();
        $id = DB::table($tabela)->value($chave);
        $this->post(route($tabela.'.store'), ['id' => $id, $campo => 'Registro revisado'])->assertRedirect();

        $this->assertDatabaseCount($tabela, 1);
        $this->assertDatabaseHas($tabela, [$chave => $id, $coluna => 'Registro revisado', 'status' => true]);
    }

    public function test_tarefa_grava_duracao_como_time_e_vincula_a_ferramenta_por_id(): void
    {
        $usuario = $this->usuario('Joana Exemplo', $this->empresa('Unidade Centro'));
        $ferramenta = DB::table('ferramentas')->insertGetId(['ferramenta' => 'Kit de limpeza'], 'id_ferramenta');

        $this->actingAs($usuario)->post(route('tarefas.store'), [
            'nome_tarefa' => 'Organizar sala',
            'tempo_tarefa' => '1.5',
            'status_tarefa' => 'Ativa',
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
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $auxiliar = $this->usuario('Rita Exemplo', $empresa);
        $catalogo = $this->catalogo();

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
        ])->assertSessionHas('success', 'Todas as rotinas foram salvas com sucesso!');

        $idRotina = DB::table('rotinas')->value('id_rotina');
        $this->assertDatabaseHas('rotinas', [
            'id_rotina' => $idRotina, 'id_empresa' => $empresa, 'id_usuario' => $responsavel->getKey(),
            'id_tarefa' => $catalogo['tarefa'], 'id_local' => $catalogo['local'],
            'local' => 'Sala de estar', 'horario' => '08:30:00', 'status' => false, 'auxilio' => true,
        ]);
        $this->assertDatabaseHas('rotina_auxiliares', ['id_rotina' => $idRotina, 'id_usuario' => $auxiliar->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'rotinas', 'registro_id' => (string) $idRotina]);
    }

    public function test_atribuicao_altera_o_responsavel_na_tabela_rotinas(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $empresa);
        $rotina = $this->rotina($empresa, $usuario, $this->catalogo());

        $this->actingAs($usuario)->post(route('rotinas.atribuir'), [
            'rotinas_ids' => [$rotina],
            'novo_colaborador_id' => $responsavel->getKey(),
        ])->assertSessionHas('success', 'Tarefas atribuídas com sucesso!');

        $this->assertDatabaseHas('rotinas', ['id_rotina' => $rotina, 'id_usuario' => $responsavel->getKey()]);
        $this->assertDatabaseHas('logs_auditoria', ['tabela' => 'rotinas', 'registro_id' => (string) $rotina, 'acao' => 'UPDATE']);
    }

    public function test_atribuicao_rejeita_um_responsavel_sem_vinculo_na_unidade(): void
    {
        $empresa = $this->empresa('Unidade Centro');
        $usuario = $this->usuario('Joana Exemplo', $empresa);
        $responsavel = $this->usuario('Ana Exemplo', $this->empresa('Unidade Sul'));
        $rotina = $this->rotina($empresa, $usuario, $this->catalogo());

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
        $rotina = $this->rotina($empresa, $usuario, $this->catalogo());
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
    private function catalogo(): array
    {
        $ferramenta = DB::table('ferramentas')->insertGetId(['ferramenta' => 'Kit de limpeza'], 'id_ferramenta');

        return [
            'tarefa' => DB::table('tarefas')->insertGetId([
                'tarefa' => 'Organizar sala',
                'tempo_realizar_tarefa' => '00:10:00',
                'id_ferramenta' => $ferramenta,
                'status' => true,
            ], 'id_tarefa'),
            'local' => DB::table('locais')->insertGetId(['local' => 'Sala de estar', 'status' => true], 'id_local'),
        ];
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
