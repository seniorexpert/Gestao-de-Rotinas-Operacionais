<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RotinasExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return DB::table('gr_rotinas')
            ->join('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
            ->select('gr_rotinas.*', 'gr_usuarios.name as nome_colaborador', 'gr_usuarios.unit as unidade_colaborador')
            ->where('gr_rotinas.status', 1)
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Colaborador',
            'Unidade',
            'Horário',
            'Dias da Semana',
            'Descrição da Tarefa'
        ];
    }

    public function map($rotina): array
    {
        $dias = strtolower($rotina->dias_semanas ?? '');
        $todosDias = str_contains($dias, 'segunda') && str_contains($dias, 'terça') && str_contains($dias, 'quarta') && str_contains($dias, 'quinta') && str_contains($dias, 'sexta');
        $diasFormatados = $todosDias ? 'Todos os dias' : ($rotina->dias_semanas ?? 'Todos os dias');

        return [
            $rotina->id_rotina,
            $rotina->nome_colaborador,
            $rotina->unidade_colaborador ?? '—',
            $rotina->horario,
            $diasFormatados,
            $rotina->descricao ?? 'Tarefa de Rotina'
        ];
    }
}