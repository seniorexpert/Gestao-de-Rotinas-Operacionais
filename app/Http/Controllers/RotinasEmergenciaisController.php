<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class RotinasEmergenciaisController extends Controller
{
    public function store(Request $request)
    {
        Log::error('REQUEST RECEBIDO:', $request->all());

        $colaboradorId = $request->input('colaborador_id');
        $tarefasJson = $request->input('tarefas_json');
        $tarefas = json_decode($tarefasJson, true);

        if (empty($tarefas)) {
            Log::error('ERRO: O array de tarefas veio vazio ou inválido.');
            return redirect()->back()->with('error', 'Nenhuma tarefa foi adicionada à lista.');
        }

        try {
            foreach ($tarefas as $index => $t) {
                DB::table('gr_rotinas')->insert([
                    'status' => 1,
                    'id_empresa' => session('id_empresa', 1),
                    'id_usuario' => $colaboradorId,
                    'dias_semanas' => $t['dias'] ?? '',
                    'horario' => $t['horario'] ?? '',
                    'id_tarefa' => $t['id_tarefa'] ?? null,
                    'morador' => $t['morador'] ?? '',
                    'id_local' => $t['local_id'] ?? null,
                    'local' => $t['local'] ?? '',
                    'auxilio' => (isset($t['auxilio']) && $t['auxilio'] === 'Sim') ? 1 : 0,
                    'id_usuarios_auxiliares' => null,
                    'obs' => $t['observacao'] ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ERRO NO SQL / INSERT:', ['message' => $e->getMessage()]);
            dd($e->getMessage());
        }

        return redirect()->back()->with('success', 'Todas as rotinas foram salvas com sucesso!');
    }
}