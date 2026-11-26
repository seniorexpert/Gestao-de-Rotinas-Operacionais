<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RotinasFinalizadasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const FOTO_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7ckAAAAASUVORK5CYII=';

    public function test_relatorio_exibe_a_execucao_real_e_detalha_responsavel_finalizador_e_foto(): void
    {
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 01:05:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $responsavel = $this->usuario('Joana Responsável', $unidade);
        $novoResponsavel = $this->usuario('Marcos Atual', $unidade);
        $admin = $this->usuario('Gestora da Unidade', $unidade, 'Gerente da Unidade', 'admin');
        $ana = $this->usuario('Ana Auxiliar', $unidade);
        $paula = $this->usuario('Paula Auxiliar', $unidade);
        $id = $this->execucao($unidade, $responsavel, 'Acompanhamento de pressão', [
            'status' => 'em_andamento', 'concluido_em' => null, 'id_usuario_conclusao' => null,
        ]);
        $lista = route('relatorios.rotinas-finalizadas', ['data_inicio' => '2026-11-24']);
        $detalhes = route('relatorios.rotinas-finalizadas.detalhes', ['id' => $id, 'data_inicio' => '2026-11-24']);
        $justificativa = 'Aferição Concluída com Acompanhamento.';
        $foto = UploadedFile::fake()->createWithContent('registro.png', base64_decode(self::FOTO_PNG));

        $this->actingAs($admin)->post(route('execucoes.concluir', $id), ['comentario' => 'aferição concluída com acompanhamento.', 'foto' => $foto])
            ->assertSessionHasNoErrors();
        $execucao = DB::table('rotinas_execucoes')->where('id_execucao', $id)->first();
        DB::table('rotinas')->where('id_rotina', $execucao->id_rotina)->update([
            'id_usuario' => $novoResponsavel->getKey(), 'status' => false, 'auxilio' => true,
        ]);
        DB::table('rotina_auxiliares')->insert([
            ['id_rotina' => $execucao->id_rotina, 'id_usuario' => $ana->getKey()],
            ['id_rotina' => $execucao->id_rotina, 'id_usuario' => $paula->getKey()],
        ]);

        $this->get($lista)->assertOk()->assertSee($detalhes)
            ->assertSeeText(['Finalizado em', '24/11/2026', 'Terça-feira', '22:05', 'Gestora da Unidade',
                'Acompanhamento de pressão', '🩺', 'Helena Exemplo', 'Quarto 04', '00:18:25', '00:15:00', $justificativa])
            ->assertDontSeeText('Joana Responsável');
        $this->get($detalhes)->assertOk()->assertSee($lista)->assertSee(route('relatorios.rotinas-finalizadas.foto', $id))
            ->assertSeeText(['Joana Responsável', 'Gestora da Unidade', 'Foto de comprovação', 'Instrução de trabalho', $justificativa])
            ->assertSeeTextInOrder(['Auxílio previsto para esta tarefa de:', 'Ana Auxiliar; Paula Auxiliar'])
            ->assertDontSeeText('Marcos Atual');
        $this->get(route('relatorios.rotinas-finalizadas.foto', $id))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent(base64_decode(self::FOTO_PNG));
        $this->assertDatabaseHas('logs_auditoria', [
            'rota' => 'relatorios.rotinas-finalizadas.detalhes', 'id_empresa' => $unidade->getKey(), 'registro_id' => (string) $id,
        ]);
    }

    public function test_filtros_da_lista_e_exportacao_usam_o_finalizador_e_a_data_local(): void
    {
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $ana = $this->usuario('Ana Enfermagem', $unidade, 'Enfermagem');
        $bia = $this->usuario('Bia Cuidadora', $unidade);
        $caio = $this->usuario('Caio Enfermagem', $unidade, 'Enfermagem');
        $filtros = [
            'data_inicio' => '2026-11-24', 'data_fim' => '2026-11-24',
            'grupo' => $ana->id_grupo_acesso, 'colaborador' => $ana->usuario,
        ];
        $this->execucao($unidade, $bia, 'Aferição na data filtrada', ['id_usuario_conclusao' => $ana->getKey()]);
        $this->execucao($unidade, $ana, 'Aferição antes do dia', ['concluido_em' => '2026-11-24 02:59:59']);
        $this->execucao($unidade, $ana, 'Aferição no dia seguinte', ['concluido_em' => '2026-11-25 03:00:00']);
        $this->execucao($unidade, $ana, 'Aferição de outro grupo', ['id_usuario_conclusao' => $bia->getKey()]);
        $this->execucao($unidade, $ana, 'Aferição de outro finalizador', ['id_usuario_conclusao' => $caio->getKey()]);
        $this->execucao($unidade, $ana, 'Aferição não realizada', ['status' => 'nao_realizada']);

        $lista = route('relatorios.rotinas-finalizadas', $filtros);
        $exportacao = route('relatorios.rotinas-finalizadas.exportar', $filtros);
        $this->actingAs($ana)->get($lista)->assertOk()
            ->assertSee($exportacao)->assertSeeText(['1 registro encontrado', 'Aferição na data filtrada'])
            ->assertDontSeeText(['Aferição antes do dia', 'Aferição no dia seguinte', 'Aferição de outro grupo',
                'Aferição de outro finalizador', 'Aferição não realizada']);

        $planilha = $this->get($exportacao)->assertDownload()->streamedContent();
        $this->assertStringContainsString('Aferição na data filtrada', $planilha);
        $this->assertStringNotContainsString('Aferição no dia seguinte', $planilha);
        $this->assertStringNotContainsString('Aferição de outro grupo', $planilha);
        $this->assertStringNotContainsString('Aferição de outro finalizador', $planilha);
        $this->from($lista)->get(route('relatorios.rotinas-finalizadas', ['data_inicio' => '2026-11-25', 'data_fim' => '2026-11-24']))
            ->assertRedirect($lista)->assertSessionHasErrors(['data_fim' => 'A data final deve ser igual ou posterior à inicial.']);
    }

    public function test_foto_e_detalhes_exigem_permissao_ao_relatorio_e_vinculo_com_a_unidade(): void
    {
        $foto = 'fotos-execucoes/registro.png';
        Storage::fake('local')->put($foto, base64_decode(self::FOTO_PNG));
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $sul = Empresa::create(['empresa' => 'Unidade Sul']);
        $responsavel = $this->usuario('Ana Centro', $centro);
        $visualizador = $this->usuario('Joana Centro', $centro);
        $bloqueado = $this->usuario('Usuário sem relatório', $centro);
        $externo = $this->usuario('Bia Sul', $sul);
        DB::table('permissoes_usuarios')->insert([
            ['id_usuario' => $visualizador->getKey(), 'modulo' => 'rotinas_diarias', 'nivel' => 0],
            ['id_usuario' => $bloqueado->getKey(), 'modulo' => 'rotinas_finalizadas', 'nivel' => 0],
        ]);
        $permitida = $this->execucao($centro, $responsavel, 'Tarefa da unidade centro', ['foto_conclusao' => $foto]);
        $foraDaUnidade = $this->execucao($sul, $externo, 'Tarefa da unidade sul', ['foto_conclusao' => $foto]);
        $naoConcluida = $this->execucao($centro, $responsavel, 'Tarefa não realizada', ['foto_conclusao' => $foto, 'status' => 'nao_realizada']);

        $this->actingAs($visualizador)->get(route('relatorios.rotinas-finalizadas'))->assertOk()
            ->assertSeeText('Tarefa da unidade centro')->assertDontSeeText(['Tarefa da unidade sul', 'Bia Sul', 'Tarefa não realizada']);
        $this->get(route('execucoes.foto', $permitida))->assertForbidden();
        $this->get(route('relatorios.rotinas-finalizadas.foto', $permitida))->assertOk();

        foreach (['relatorios.rotinas-finalizadas.detalhes', 'relatorios.rotinas-finalizadas.foto'] as $rota) {
            $this->get(route($rota, $foraDaUnidade))->assertNotFound();
            $this->get(route($rota, $naoConcluida))->assertNotFound();
        }
        $this->actingAs($bloqueado)->get(route('relatorios.rotinas-finalizadas.detalhes', $permitida))->assertForbidden();
        $this->get(route('relatorios.rotinas-finalizadas.foto', $permitida))->assertForbidden();
        $this->get(route('relatorios.rotinas-finalizadas.exportar'))->assertForbidden();
    }

    public function test_registro_antigo_sem_inicio_ou_autor_exibe_somente_os_dados_disponiveis(): void
    {
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $colaborador = $this->usuario('Joana Exemplo', $unidade);
        $id = $this->execucao($unidade, $colaborador, 'Aferição antiga', [
            'id_usuario_conclusao' => null, 'iniciado_em' => null, 'duracao_prevista_segundos' => null,
            'comentario_conclusao' => null, 'justificativa' => 'Medição registrada no prontuário.',
        ]);

        $this->actingAs($colaborador)->get(route('relatorios.rotinas-finalizadas.detalhes', $id))->assertOk()
            ->assertSeeText(['Não registrado', '00:15:00', 'Medição registrada no prontuário.', 'Nenhuma foto de comprovação disponível.'])
            ->assertViewHas('registro', fn ($registro) => $registro->tempo_execucao === null);
        $this->get(route('relatorios.rotinas-finalizadas.foto', $id))->assertNotFound();
    }

    public function test_relatorio_comeca_nas_diarias_e_filtra_emergenciais_e_nao_realizadas_verificadas(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 01:15:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $colaborador = $this->usuario('Ana Centro', $unidade);
        $enfermeira = $this->usuario('Rita Enfermagem', $unidade, 'Enfermagem');
        $foto = 'fotos-execucoes/atendimento.png';
        Storage::fake('local')->put($foto, base64_decode(self::FOTO_PNG));
        $this->execucao($unidade, $colaborador, 'Conferência do plantão');
        $urgente = $this->execucao($unidade, $colaborador, 'Atendimento urgente', ['foto_conclusao' => $foto]);
        DB::table('rotinas')->where('id_rotina', DB::table('rotinas_execucoes')->where('id_execucao', $urgente)->value('id_rotina'))
            ->update(['tipo_rotina' => ExecucoesDiarias::TIPO_EMERGENCIAL, 'agendada_em' => '2026-11-25 00:45:00']);
        $impedida = $this->execucao($unidade, $colaborador, 'Entrega interrompida', [
            'status' => 'em_andamento', 'concluido_em' => null, 'id_usuario_conclusao' => null,
        ]);
        $this->actingAs($colaborador)->post(route('execucoes.nao-realizada', $impedida), ['justificativa' => 'Morador ausente.'])
            ->assertSessionHasNoErrors();

        $this->get(route('relatorios.rotinas-finalizadas'))->assertOk()
            ->assertSee('value="diaria" selected', false)->assertSeeText('Conferência do plantão')
            ->assertDontSeeText(['Atendimento urgente', 'Entrega interrompida']);

        $emergenciais = ['processo' => 'emergencial'];
        $detalhe = route('relatorios.rotinas-finalizadas.detalhes', ['id' => $urgente] + $emergenciais);
        $urlFoto = route('relatorios.rotinas-finalizadas.foto', ['id' => $urgente] + $emergenciais);
        $this->get(route('relatorios.rotinas-finalizadas', $emergenciais))->assertOk()
            ->assertSee($detalhe)->assertSeeText(['1 registro encontrado', 'Atendimento urgente'])
            ->assertDontSeeText('Conferência do plantão');
        $this->get($detalhe)->assertOk()->assertSee($urlFoto)->assertSeeText(['Concluída', 'Tarefas Emergenciais']);
        $this->get($urlFoto)->assertOk()->assertStreamedContent(base64_decode(self::FOTO_PNG));

        $naoRealizadas = ['processo' => 'nao_realizada'];
        $lista = route('relatorios.rotinas-finalizadas', $naoRealizadas);
        $detalhe = route('relatorios.rotinas-finalizadas.detalhes', ['id' => $impedida] + $naoRealizadas);
        $this->actingAs($enfermeira)->get($lista)->assertOk()
            ->assertSeeText('Nenhum registro encontrado')->assertDontSeeText('Entrega interrompida');
        $this->get($detalhe)->assertNotFound();

        $this->travelTo(CarbonImmutable::parse('2026-11-25 03:05:00', 'UTC'));
        $this->post(route('acompanhamento.store', $impedida), ['tipo' => 'nao_realizada', 'acao' => 'Entrega reagendada.'])
            ->assertSessionHasNoErrors();
        $filtros = $naoRealizadas + [
            'data_inicio' => '2026-11-25', 'data_fim' => '2026-11-25', 'colaborador' => $enfermeira->usuario,
        ];
        $this->get(route('relatorios.rotinas-finalizadas', $filtros))->assertOk()
            ->assertSeeText(['1 registro encontrado', 'Entrega interrompida', '00:05', 'Rita Enfermagem', 'Ação realizada', 'Entrega Reagendada.'])
            ->assertDontSeeText(['Atendimento urgente', 'Morador Ausente.']);
        $this->get($detalhe)->assertOk()->assertSeeText([
            'Verificada', '25/11/2026 · 00:05', 'Ana Centro', 'Rita Enfermagem', 'Colaborador(a) que verificou',
            'Ação realizada', 'Entrega Reagendada.', 'Justificativa da não realização', 'Morador Ausente.', '01:50:00', '00:30:00',
        ]);

        $planilha = $this->get(route('relatorios.rotinas-finalizadas.exportar', $filtros))->assertDownload()->streamedContent();
        $this->assertStringContainsString('Entrega Reagendada.', $planilha);
        $this->assertStringContainsString('Rita Enfermagem', $planilha);
        $this->actingAs($colaborador)->get($detalhe)->assertForbidden();
    }

    public function test_relatorio_usa_verificacoes_e_preserva_a_identidade_de_cada_solicitacao_de_auxilio(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 02:55:00', 'UTC'));
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $colaborador = $this->usuario('Ana Centro', $unidade);
        $enfermeira = $this->usuario('Rita Enfermagem', $unidade, 'Enfermagem');
        $visualizadora = $this->usuario('Bia Enfermagem', $unidade, 'Enfermagem');
        $coordenador = $this->usuario('Caio Coordenador', $unidade, 'Coordenador');
        $externa = $this->usuario('Joana Sul', Empresa::create(['empresa' => 'Unidade Sul']), 'Enfermagem');
        $id = $this->execucao($unidade, $colaborador, 'Hidratação do morador', [
            'status' => 'em_andamento', 'concluido_em' => null, 'id_usuario_conclusao' => null,
        ]);
        $this->actingAs($colaborador);
        foreach (['Materiais', 'Equipe', 'Pendência'] as $motivo) {
            $this->post(route('execucoes.auxilio', $id), ['justificativa' => $motivo])->assertSessionHasNoErrors();
        }
        $pedidos = DB::table('rotinas_acomp_auxilios')->pluck('id_solicitacao', 'justificativa');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 03:02:00', 'UTC'));
        $this->actingAs($enfermeira)->post(route('acompanhamento.store', $id), ['acao' => 'Priorizei o atendimento.'])
            ->assertSessionHasNoErrors();
        $this->post(route('acompanhamento.store', $pedidos['Materiais']), ['tipo' => 'auxilio', 'acao' => 'Materiais entregues.'])
            ->assertSessionHasNoErrors();
        $this->post(route('acompanhamento.store', $pedidos['Equipe']), ['tipo' => 'auxilio', 'acao' => 'Equipe mobilizada.'])
            ->assertSessionHasNoErrors();

        $filtros = ['processo' => 'auxilio', 'data_inicio' => '2026-11-25', 'data_fim' => '2026-11-25', 'colaborador' => $enfermeira->usuario];
        $lista = route('relatorios.rotinas-finalizadas', $filtros);
        $detalhe = route('relatorios.rotinas-finalizadas.detalhes', ['id' => $pedidos['Equipe']] + $filtros);
        $this->actingAs($visualizadora)->get($lista)->assertOk()->assertSee($detalhe)
            ->assertSeeText(['2 registros encontrados', 'Hidratação do morador', '00:02', 'Rita Enfermagem', 'Materiais Entregues.', 'Equipe Mobilizada.'])
            ->assertDontSeeText('Priorizei o Atendimento.');
        $this->get($detalhe)->assertOk()->assertSee($lista)
            ->assertSeeText(['Verificada', 'Solicitações de Auxílio', 'Equipe', 'Equipe Mobilizada.', '00:07:00', '00:10:00'])
            ->assertDontSeeText('Materiais Entregues.');
        $this->get(route('relatorios.rotinas-finalizadas.detalhes', ['id' => $pedidos['Pendência'], 'processo' => 'auxilio']))->assertNotFound();

        $planilha = $this->get(route('relatorios.rotinas-finalizadas.exportar', $filtros))->assertDownload()->streamedContent();
        $this->assertStringContainsString('Solicitações de Auxílio', $planilha);
        $this->assertStringContainsString('Equipe Mobilizada.', $planilha);
        $this->assertStringNotContainsString('Priorizei o Atendimento.', $planilha);
        $this->get(route('relatorios.rotinas-finalizadas', ['processo' => 'acompanhamento']))->assertOk()
            ->assertSeeText(['1 registro encontrado', 'Priorizei o Atendimento.'])->assertDontSeeText('Equipe Mobilizada.');
        $this->assertDatabaseHas('logs_auditoria', [
            'rota' => 'relatorios.rotinas-finalizadas.detalhes', 'tabela' => 'rotinas_acomp_auxilios',
            'registro_id' => (string) $pedidos['Equipe'], 'id_empresa' => $unidade->getKey(),
        ]);

        $this->actingAs($coordenador)->get($detalhe)->assertNotFound();
        $this->actingAs($externa)->get($detalhe)->assertNotFound();
        $this->actingAs($colaborador)->get($lista)->assertForbidden();
    }

    private function usuario(string $nome, Empresa $unidade, string $grupo = 'Cuidador', ?string $login = null): User
    {
        $acesso = GrupoAcesso::firstOrCreate(['grupo_acesso' => $grupo], ['status' => true]);
        $usuario = User::create([
            'usuario' => $nome, 'username' => $login ?? Str::slug($nome), 'password' => 'senha-de-teste',
            'status' => true, 'id_grupo_acesso' => $acesso->getKey(),
        ]);
        $usuario->empresas()->attach($unidade->getKey(), ['empresa_nome' => $unidade->empresa]);

        return $usuario;
    }

    /** @param array<string, mixed> $alteracoes */
    private function execucao(Empresa $unidade, User $responsavel, string $nome, array $alteracoes = []): int
    {
        $sla = DB::table('slas')->where('sla', 'SLA de teste')->value('id_sla')
            ?? DB::table('slas')->insertGetId(['sla' => 'SLA de teste', 'tempo_limite_minutos' => 15], 'id_sla');
        $tarefa = DB::table('tarefas')->insertGetId([
            'tarefa' => $nome, 'descricao' => 'Conferir o registro.', 'tempo_realizar_tarefa' => '00:45:00', 'icone' => '🩺', 'status' => true,
        ], 'id_tarefa');
        $rotina = DB::table('rotinas')->insertGetId([
            'id_usuario' => $responsavel->getKey(), 'id_empresa' => $unidade->getKey(), 'id_tarefa' => $tarefa,
            'status' => true, 'horario' => '21:45:00', 'morador' => 'Helena Exemplo', 'local' => 'Quarto 04',
            'dias_semanas' => 'Terça-feira', 'auxilio' => false,
        ], 'id_rotina');

        return DB::table('rotinas_execucoes')->insertGetId(array_replace([
            'id_rotina' => $rotina, 'id_empresa' => $unidade->getKey(), 'id_sla' => $sla,
            'id_usuario_responsavel' => $responsavel->getKey(), 'id_usuario_conclusao' => $responsavel->getKey(),
            'previsto_em' => '2026-11-25 00:45:00', 'iniciado_em' => '2026-11-25 00:46:35', 'prazo_em' => '2026-11-25 01:01:35',
            'concluido_em' => '2026-11-25 01:05:00', 'status' => 'concluida', 'duracao_prevista_segundos' => 900,
            'tempo_limite_minutos' => 15, 'comentario_conclusao' => 'Conferência concluída.',
        ], $alteracoes), 'id_execucao');
    }
}
