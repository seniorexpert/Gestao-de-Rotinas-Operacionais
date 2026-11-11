<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    
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
        
    public function rotinas() { return view('relatorios.rotinas'); }
    public function rotinasFinalizadas() { return view('relatorios.rotinas-finalizadas'); }
    public function rotinasExcel() { return view('relatorios.rotinas-excel'); }
    public function logsRotinas() { return view('relatorios.logs-rotinas'); }
    public function estatisticas() { return view('relatorios.estatisticas'); }
}