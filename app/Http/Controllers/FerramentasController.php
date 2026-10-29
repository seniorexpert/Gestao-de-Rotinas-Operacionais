<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FerramentasController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'nome_ferramenta' => 'required|string|max:100',
        ]);

        DB::table('gr_ferramentas')->insert([
            'nome_ferramenta' => $request->nome_ferramenta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back();
    }
}