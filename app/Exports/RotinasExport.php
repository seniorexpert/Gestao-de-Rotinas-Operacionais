<?php

namespace App\Exports;

use App\Services\ConsultaRotinas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RotinasExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        $usuario = Auth::user();
        if ($usuario === null) {
            return collect();
        }

        return app(ConsultaRotinas::class)->rotinasDiarias($usuario)->where('r.status', true)->get([
            'r.id_rotina', 'r.horario', 'r.dias_semanas', 'u.usuario as nome_colaborador',
            'e.empresa as unidade_colaborador', 't.tarefa', 't.descricao',
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Colaborador',
            'Unidade',
            'Horário',
            'Dias da Semana',
            'Descrição da Tarefa',
        ];
    }

    public function map(mixed $rotina): array
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
            $rotina->descricao ?? $rotina->tarefa ?? 'Tarefa de Rotina',
        ];
    }
}
