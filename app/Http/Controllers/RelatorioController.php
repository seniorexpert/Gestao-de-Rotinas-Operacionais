<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\RotinasExport;
use Maatwebsite\Excel\Facades\Excel;

class RelatorioController extends Controller
{

    public function exportarExcel(Request $request)
    {
        $colaboradorFiltro = $request->input('colaborador');

        $colaboradoresQuery = DB::table('gr_usuarios')->orderBy('name', 'asc');
        if ($colaboradorFiltro && $colaboradorFiltro !== 'Todas') {
            $colaboradoresQuery->where('name', $colaboradorFiltro);
        }
        $colaboradores = $colaboradoresQuery->get();

        $colaboradorIds = $colaboradores->pluck('id');

        $rotinas = DB::table('gr_rotinas')
            ->join('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
            ->select('gr_rotinas.*', 'gr_usuarios.name as nome_colaborador', 'gr_usuarios.unit as unidade_colaborador')
            ->whereIn('gr_rotinas.id_usuario', $colaboradorIds)
            ->where('gr_rotinas.status', 1)
            ->orderBy('gr_rotinas.horario', 'asc')
            ->get();

        $horarios = $rotinas->pluck('horario')->unique()->sort()->values();

        $filename = "relatorio-rotinas-grade-" . date('Y-m-d') . ".xls";

        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");

        echo '<table border="1">';
        echo '<thead>';
        echo '<tr style="background-color: #fcfbf9; font-weight: bold;">';
        echo '<th>HORA</th>';
        foreach ($colaboradores as $c) {
            echo '<th>' . htmlspecialchars($c->name) . '</th>';
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($horarios as $horario) {
            echo '<tr>';
            echo '<td style="font-weight: bold; background-color: #fcfbf9;">' . $horario . '</td>';

            foreach ($colaboradores as $c) {
                $tarefasDoHorario = $rotinas->where('id_usuario', $c->id)->where('horario', $horario);
                
                echo '<td style="vertical-align: top;">';
                foreach ($tarefasDoHorario as $tarefa) {
                    $dias = strtolower($tarefa->dias_semanas ?? '');
                    $todosDias = str_contains($dias, 'segunda') && str_contains($dias, 'terça') && str_contains($dias, 'quarta') && str_contains($dias, 'quinta') && str_contains($dias, 'sexta');
                    $diasFormatados = $todosDias ? 'Todos os dias' : ($tarefa->dias_semanas ?? 'Todos os dias');
                    
                    $descricao = htmlspecialchars($tarefa->descricao ?? 'Tarefa de Rotina');
                    
                    echo '<div style="margin-bottom: 8px; border-bottom: 1px dashed #ccc; padding-bottom: 5px;">';
                    echo '<b>' . $descricao . '</b><br>';
                    echo '<span style="font-size: 10px; color: #555;">' . $diasFormatados . '</span>';
                    echo '</div>';
                }
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        exit;
    }

    public function tarefas(){
        $tarefas = DB::table('gr_tarefas')->orderBy('id', 'asc')->get();
        return view('relatorios.tarefas', compact('tarefas'));
    }

    public function locais(){
        $locais = DB::table('gr_locais')->orderBy('id', 'asc')->get();
        return view('relatorios.locais', compact('locais'));
    }

    public function ferramentas(){
        $ferramentas = DB::table('gr_ferramentas')->orderBy('id', 'asc')->get();
        return view('relatorios.ferramentas', compact('ferramentas'));
    }

    public function rotinas(Request $request){
        $colaboradorFiltro = $request->input('colaborador');

        $query = DB::table('gr_rotinas')
            ->join('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
            ->select(
                'gr_usuarios.id as id_usuario',
                'gr_usuarios.name as nome_colaborador',
                'gr_usuarios.unit as unidade_colaborador',
                'gr_usuarios.group as group_colaborador',
                DB::Raw('MIN(gr_rotinas.id_rotina) as id_rotina_principal'),
                DB::Raw('MIN(gr_rotinas.horario) as horario_inicio'),
                DB::Raw('MAX(gr_rotinas.horario) as horario_fim'),
                DB::Raw('COUNT(gr_rotinas.id_rotina) as total_tarefas'),
                DB::Raw('MAX(CASE WHEN gr_rotinas.status = 1 THEN 1 ELSE 0 END) as status_rotina')
            )
            ->groupBy('gr_usuarios.id', 'gr_usuarios.name', 'gr_usuarios.unit', 'gr_usuarios.group');

        if ($colaboradorFiltro && $colaboradorFiltro !== 'Todas') {
            $query->where('gr_usuarios.name', $colaboradorFiltro);
        }

        $rotinas = $query->orderBy('gr_usuarios.name', 'asc')->get();

        $colaboradores = DB::table('gr_usuarios')->orderBy('name')->pluck('name');

        return view('relatorios.rotinas', compact('rotinas', 'colaboradores', 'colaboradorFiltro'));
    }

    public function logsRotinas(Request $request){
        $dataCriacao = $request->input('data_criacao');
        $dataEvento = $request->input('data_evento');
        $nomeExecutor = $request->input('nome_executor');
        $tarefaFiltro = $request->input('tarefa');
        $descricaoEvento = $request->input('descricao_evento');
        $grupoAcesso = $request->input('grupo_acesso');
        $colaboradorOriginal = $request->input('colaborador_original');

        $query = DB::table('gr_logs_rotinas')->orderBy('id', 'desc');

        if ($dataCriacao) {
            $query->whereDate('created_at', $dataCriacao);
        }
        if ($dataEvento) {
            $query->whereDate('data_evento', $dataEvento);
        }
        if ($nomeExecutor) {
            $query->where('executor', 'like', "%{$nomeExecutor}%");
        }
        if ($tarefaFiltro) {
            $query->where('tarefa', 'like', "%{$tarefaFiltro}%");
        }
        if ($descricaoEvento && $descricaoEvento !== 'Todas') {
            $query->where('descricao_evento', $descricaoEvento);
        }
        if ($grupoAcesso && $grupoAcesso !== 'Todos') {
            $query->where('grupo_acesso', $grupoAcesso);
        }
        if ($colaboradorOriginal && $colaboradorOriginal !== 'Todos') {
            $query->where('colaborador_original', $colaboradorOriginal);
        }

        $logs = $query->paginate(10)->appends($request->query());

        $gruposAcesso = DB::table('gr_logs_rotinas')->select('grupo_acesso')->distinct()->pluck('grupo_acesso');
        $colaboradores = DB::table('gr_usuarios')->orderBy('name')->pluck('name');
        $descricoesEventos = DB::table('gr_logs_rotinas')->select('descricao_evento')->distinct()->pluck('descricao_evento');

        return view('relatorios.logs-rotinas', compact(
            'logs', 'gruposAcesso', 'colaboradores', 'descricoesEventos', 
            'dataCriacao', 'dataEvento', 'nomeExecutor', 'tarefaFiltro', 
            'descricaoEvento', 'grupoAcesso', 'colaboradorOriginal'
        ));
    }

    public function rotinasExcel(Request $request){
        $colaboradorFiltro = $request->input('colaborador');

        $colaboradoresQuery = DB::table('gr_usuarios')->orderBy('name', 'asc');
        if ($colaboradorFiltro && $colaboradorFiltro !== 'Todas') {
            $colaboradoresQuery->where('name', $colaboradorFiltro);
        }
        $colaboradores = $colaboradoresQuery->get();

        $colaboradorIds = $colaboradores->pluck('id');

        $rotinas = DB::table('gr_rotinas')
            ->join('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
            ->select('gr_rotinas.*', 'gr_usuarios.name as nome_colaborador', 'gr_usuarios.unit as unidade_colaborador', 'gr_usuarios.group as group_colaborador')
            ->whereIn('gr_rotinas.id_usuario', $colaboradorIds)
            ->where('gr_rotinas.status', 1)
            ->orderBy('gr_rotinas.horario', 'asc')
            ->get();

        $horarios = $rotinas->pluck('horario')->unique()->sort()->values();

        $todosColaboradoresFiltro = DB::table('gr_usuarios')->orderBy('name')->pluck('name');

        return view('relatorios.rotinas-excel', compact('colaboradores', 'rotinas', 'horarios', 'todosColaboradoresFiltro', 'colaboradorFiltro'));
    }

    public function apiLogDetalhes($id)
    {
        $log = DB::table('gr_logs_rotinas')->where('id', $id)->first();
        
        if (!$log) {
            return response()->json(['error' => 'Log não encontrado'], 404);
        }

        return response()->json($log);
    }

    public function exportarExcelLogs(Request $request)
    {
        $dataCriacao = $request->input('data_criacao');
        $dataEvento = $request->input('data_evento');
        $nomeExecutor = $request->input('nome_executor');
        $tarefaFiltro = $request->input('tarefa');
        $descricaoEvento = $request->input('descricao_evento');
        $grupoAcesso = $request->input('grupo_acesso');
        $colaboradorOriginal = $request->input('colaborador_original');

        $query = DB::table('gr_logs_rotinas')->orderBy('id', 'desc');

        if ($dataCriacao) {
            $query->whereDate('created_at', $dataCriacao);
        }
        if ($dataEvento) {
            $query->whereDate('data_evento', $dataEvento);
        }
        if ($nomeExecutor) {
            $query->where('executor', 'like', "%{$nomeExecutor}%");
        }
        if ($tarefaFiltro) {
            $query->where('tarefa', 'like', "%{$tarefaFiltro}%");
        }
        if ($descricaoEvento && $descricaoEvento !== 'Todas') {
            $query->where('descricao_evento', $descricaoEvento);
        }
        if ($grupoAcesso && $grupoAcesso !== 'Todos') {
            $query->where('grupo_acesso', $grupoAcesso);
        }
        if ($colaboradorOriginal && $colaboradorOriginal !== 'Todos') {
            $query->where('colaborador_original', $colaboradorOriginal);
        }

        $logs = $query->get();

        $filename = "relatorio-logs-rotinas-" . date('Y-m-d') . ".xls";

        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");

        echo '<table border="1">';
        echo '<thead>';
        echo '<tr style="background-color: #fcfbf9; font-weight: bold;">';
        echo '<th>ID</th>';
        echo '<th>SITUAÇÃO</th>';
        echo '<th>EVENTO</th>';
        echo '<th>DATA DO EVENTO</th>';
        echo '<th>TAREFA</th>';
        echo '<th>DETALHE TAREFA</th>';
        echo '<th>COLABORADOR(A)</th>';
        echo '<th>GRUPO DE ACESSO</th>';
        echo '<th>EXECUTOR</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>#' . $log->id . '</td>';
            echo '<td>' . htmlspecialchars($log->situacao ?? 'Aberto') . '</td>';
            echo '<td>' . htmlspecialchars($log->descricao_evento ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log->data_evento ?? $log->created_at ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log->tarefa ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log->detalhe_tarefa ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log->colaborador_original ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log->grupo_acesso ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log->executor ?? '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        exit;
    }
    
    public function rotinasFinalizadas() { return view('relatorios.rotinas-finalizadas'); }
    public function estatisticas() { return view('relatorios.estatisticas'); }


}