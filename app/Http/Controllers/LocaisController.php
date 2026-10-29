<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocaisController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'nome_local' => 'required|string|max:100',
        ]);

        DB::table('gr_locais')->insert([
            'nome_local' => $request->nome_local,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back();
    }
}