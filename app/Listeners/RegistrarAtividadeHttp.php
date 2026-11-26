<?php

namespace App\Listeners;

use App\Services\AuditoriaService;
use App\Services\VisibilidadeGrupos;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrarAtividadeHttp
{
    private const OPERACOES = [
        'inicio' => ['ACESSAR', 'requisicoes', 'Acesso à página inicial'],
        'dashboard' => ['ACESSAR', 'requisicoes', 'Acesso ao painel'],
        'home' => ['CONSULTAR', 'requisicoes', 'Consulta de rotinas diárias'],
        'login' => ['ACESSAR', 'sessoes', 'Acesso à página de entrada'],
        'login.autenticar' => ['LOGIN', 'sessoes', 'Entrada no sistema'],
        'logout' => ['LOGOUT', 'sessoes', 'Saída do sistema'],
        'password.edit.custom' => ['ACESSAR', 'sessoes', 'Acesso à alteração de senha'],
        'password.update.custom' => ['ALTERAR_SENHA', 'usuarios', 'Alteração de senha'],
        'usuarios.index' => ['CONSULTAR', 'usuarios', 'Consulta da gestão de acessos'],
        'users.store' => ['CREATE', 'usuarios', 'Cadastro de usuário'],
        'users.update' => ['UPDATE', 'usuarios', 'Edição de usuário'],
        'visibilidade-grupos.index' => ['CONSULTAR', VisibilidadeGrupos::TABELA, 'Consulta da visibilidade entre grupos'],
        'visibilidade-grupos.update' => ['UPDATE', VisibilidadeGrupos::TABELA, 'Gravação da visibilidade entre grupos'],
        'processos.index' => ['ACESSAR', 'requisicoes', 'Acesso a Iniciar Processos'],
        'processos.gestao-rotinas' => ['CONSULTAR', 'rotinas', 'Consulta dos cadastros de gestão de rotinas'],
        'processos.usuarios-por-unidade' => ['CONSULTAR', 'usuarios', 'Consulta de colaboradores da unidade'],
        'monitoramento.index' => ['CONSULTAR', 'rotinas', 'Consulta do Centro de Monitoramento'],
        'rotinas.store' => ['CREATE', 'rotinas', 'Cadastro de rotina'],
        'rotinas.update' => ['UPDATE', 'rotinas', 'Edição de rotina'],
        'rotinas.destroy' => ['DELETE', 'rotinas', 'Exclusão de rotina'],
        'rotinas.tarefas.destroy' => ['DELETE', 'rotinas', 'Remoção de tarefa da rotina'],
        'rotinas.tarefas.update' => ['UPDATE', 'rotinas', 'Edição de tarefa da rotina'],
        'rotinas.tarefas.store' => ['CREATE', 'rotinas', 'Inclusão de tarefa na rotina'],
        'rotinas.emergenciais.store' => ['CREATE', 'rotinas', 'Cadastro de tarefa emergencial'],
        'rotinas.atribuir' => ['UPDATE', 'rotinas', 'Atribuição de rotinas'],
        'execucoes.iniciar' => ['INICIAR', 'rotinas_execucoes', 'Início de execução'],
        'execucoes.concluir' => ['CONCLUIR', 'rotinas_execucoes', 'Conclusão de execução'],
        'execucoes.nao-realizada' => ['NAO_REALIZAR', 'rotinas_execucoes', 'Tarefa não realizada'],
        'execucoes.auxilio' => ['SOLICITAR_AUXILIO', 'rotinas_execucoes', 'Solicitação de auxílio'],
        'acompanhamento.index' => ['CONSULTAR', 'rotinas_execucoes', 'Consulta do acompanhamento de rotinas'],
        'acompanhamento.store' => ['VERIFICAR', 'rotinas_verificacoes', 'Verificação no acompanhamento'],
        'tarefas.store' => ['SALVAR', 'tarefas', 'Gravação de tarefa'],
        'locais.store' => ['SALVAR', 'locais', 'Gravação de local'],
        'ferramentas.store' => ['SALVAR', 'ferramentas', 'Gravação de ferramenta'],
        'relatorios.rotinas' => ['CONSULTAR', 'rotinas', 'Consulta do relatório de rotinas'],
        'relatorios.excel' => ['CONSULTAR', 'rotinas', 'Consulta da grade de rotinas'],
        'relatorios.excel.exportar' => ['EXPORTAR', 'rotinas', 'Solicitação de exportação da grade de rotinas'],
        'relatorios.tarefas' => ['CONSULTAR', 'tarefas', 'Consulta do relatório de tarefas'],
        'relatorios.ferramentas' => ['CONSULTAR', 'ferramentas', 'Consulta do relatório de ferramentas'],
        'relatorios.locais' => ['CONSULTAR', 'locais', 'Consulta do relatório de locais'],
        'relatorios.estatisticas' => ['CONSULTAR', 'rotinas_execucoes', 'Consulta de estatísticas'],
        'relatorios.rotinas-finalizadas' => ['CONSULTAR', 'rotinas_execucoes', 'Consulta de rotinas finalizadas'],
        'relatorios.rotinas-finalizadas.detalhes' => ['CONSULTAR', 'rotinas_execucoes', 'Consulta de detalhes da rotina finalizada'],
        'relatorios.rotinas-finalizadas.foto' => ['CONSULTAR', 'rotinas_execucoes', 'Consulta de foto da rotina finalizada'],
        'relatorios.rotinas-finalizadas.exportar' => ['EXPORTAR', 'rotinas_execucoes', 'Exportação de rotinas finalizadas'],
        'relatorios.logs-rotinas' => ['CONSULTAR', 'logs_auditoria', 'Consulta dos logs e auditoria'],
        'relatorios.logs.detalhes' => ['CONSULTAR', 'logs_auditoria', 'Consulta de detalhes de um log'],
        'relatorios.logs.detalhes.legado' => ['CONSULTAR', 'logs_auditoria', 'Consulta de detalhes de um log'],
        'relatorios.logs.excel.exportar' => ['EXPORTAR', 'logs_auditoria', 'Solicitação de exportação dos logs'],
    ];

    public function handle(RequestHandled $event): void
    {
        $request = $event->request;
        $rota = $request->route()?->getName();
        if ($rota === null || ! $request->hasSession() || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return;
        }

        try {
            $this->registrar($request, $rota, $event->response->getStatusCode());
        } catch (Throwable $erro) {
            Log::error('Falha na gravação da atividade HTTP na auditoria.', ['rota' => $rota, 'erro' => $erro::class]);
        }
    }

    private function registrar(Request $request, string $rota, int $status): void
    {
        $autor = $request->attributes->get(AuditoriaService::AUTOR_HTTP) ?? $request->user();
        if ($autor === null && $rota !== 'login.autenticar') {
            return;
        }

        $resultado = $request->attributes->get(AuditoriaService::RESULTADO_HTTP) ?? match (true) {
            $status >= 500 => 'erro',
            in_array($status, [401, 403, 419], true) => 'recusado',
            $status === 422 => 'invalido',
            $status >= 400 => 'falha',
            default => 'sucesso',
        };

        if ($resultado === 'sucesso' && $request->attributes->get(AuditoriaService::ALTERACAO_REGISTRADA)) {
            return;
        }

        if ($resultado === 'sucesso' && in_array($rota, [
            'execucoes.iniciar', 'execucoes.concluir', 'execucoes.nao-realizada', 'execucoes.auxilio',
            'acompanhamento.store', 'users.update', 'rotinas.atribuir', 'rotinas.update', 'rotinas.tarefas.update', 'visibilidade-grupos.update',
        ], true)) {
            $resultado = 'sem_alteracao';
        }

        [$acao, $tabela, $descricao] = self::OPERACOES[$rota] ?? [
            $request->isMethod('GET') ? 'CONSULTAR' : 'OPERACAO',
            'requisicoes',
            'Atividade em '.$rota,
        ];

        if ($rota === 'login.autenticar' && $resultado !== 'sucesso') {
            $descricao = 'Tentativa de entrada no sistema';
        }
        if (in_array($rota, ['acompanhamento.index', 'home', 'monitoramento.index'], true) && $request->header('X-Atualizacao-Automatica') === '1') {
            $acao = 'ATUALIZAR_PAINEL';
            $descricao = match ($rota) {
                'monitoramento.index' => 'Atualização automática do monitoramento',
                'home' => 'Atualização automática das Rotinas Diárias',
                default => 'Atualização automática do acompanhamento',
            };
        }

        $id = $rota === 'acompanhamento.index' ? ($request->query()['item'] ?? null) : $request->route('id');
        $registro = is_scalar($id) && ctype_digit((string) $id) && strlen((string) $id) <= 19 ? (string) $id : $rota;

        AuditoriaService::registrar(
            $acao, $request->attributes->get(AuditoriaService::TABELA_HTTP, $tabela), $registro,
            descricao: $descricao,
            idEmpresa: $request->attributes->get(AuditoriaService::UNIDADE_HTTP),
            idRotina: $request->attributes->get(AuditoriaService::ROTINA_HTTP),
            requisicao: $request,
            autor: $autor,
            statusHttp: $status,
            resultado: $resultado,
        );
    }
}
