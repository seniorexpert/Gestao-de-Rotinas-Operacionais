@extends('layouts.app')

@section('title', 'Relatório de Rotinas - Excel')

@section('content')
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Relatório de Rotinas - Excel</h1>
            <p class="text-sm text-gray-500 mt-1">Consulte as rotinas por colaborador(a) e exporte para o Excel</p>
        </div>
        <a href="{{ route('relatorios.excel.exportar') }}?colaborador={{ request('colaborador') }}" class="inline-flex items-center justify-center space-x-2 bg-[#1b2a38] hover:bg-[#111c26] text-white px-5 py-3 rounded-xl font-medium text-sm transition shrink-0">
            <i class="fa-solid fa-file-excel"></i>
            <span>Exportar Excel</span>
        </a>
    </div>

    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-200 mb-6">
        <form method="GET" action="{{ route('relatorios.excel') }}" class="flex flex-col sm:flex-row items-end gap-3">
            <div class="w-full sm:w-80">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Colaborador(a)</label>
                <div class="relative w-full">
                    <select name="colaborador" class="w-full pl-4 pr-10 py-3 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition">
                        <option value="Todas">Todas</option>
                        @foreach($todosColaboradoresFiltro as $colab)
                            <option value="{{ $colab }}" {{ isset($colaboradorFiltro) && $colaboradorFiltro == $colab ? 'selected' : '' }}>
                                {{ $colab }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="submit" class="bg-[#1b2a38] hover:bg-[#111c26] text-white p-3 rounded-xl transition">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                <a href="{{ route('relatorios.excel') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-3 rounded-xl font-medium text-sm transition text-center">
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-[#fcfbf9] border-b border-gray-200 text-xs font-bold text-gray-500">
                        <th class="py-4 px-6 w-28 border-r border-gray-200 uppercase tracking-wider">HORA</th>
                        @foreach($colaboradores as $c)
                            <th class="py-4 px-6 border-r border-gray-200 min-w-[280px]">
                                <span class="block font-bold text-[#1b2a38] text-sm">{{ $c->name }}</span>
                                <span class="block font-normal text-xs text-gray-400 mt-0.5">
                                    {{ $c->group ? $c->group . ' · ' : '' }} 
                                    {{ $rotinas->where('id_usuario', $c->id)->count() }} tarefas
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse($horarios as $horario)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="py-4 px-6 border-r border-gray-200 font-bold text-[#1b2a38] tabular-nums align-top bg-[#fcfbf9]">
                                {{ $horario }}
                            </td>
                            @foreach($colaboradores as $c)
                                <td class="py-4 px-6 border-r border-gray-200 align-top">
                                    @php
                                        $tarefasDoHorario = $rotinas->where('id_usuario', $c->id)->where('horario', $horario);
                                    @endphp
                                    @foreach($tarefasDoHorario as $tarefa)
                                        <div class="bg-white border border-gray-200 p-4 rounded-2xl shadow-sm mb-3 hover:border-gray-300 transition">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full bg-[#f4f1ea] flex items-center justify-center shrink-0 mt-0.5">
                                                    <i class="fa-solid fa-stethoscope text-xs text-[#1b2a38]"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-[#1b2a38] text-xs uppercase leading-snug mb-1">
                                                        {{ $tarefa->descricao ?? 'Tarefa de Rotina' }}
                                                    </h4>
                                                    <p class="text-xs text-gray-500 font-medium">
                                                        @php
                                                            $dias = strtolower($tarefa->dias_semanas ?? '');
                                                            $todosDias = str_contains($dias, 'segunda') && str_contains($dias, 'terça') && str_contains($dias, 'quarta') && str_contains($dias, 'quinta') && str_contains($dias, 'sexta');
                                                        @endphp
                                                        {{ $todosDias ? 'Todos os dias' : ($tarefa->dias_semanas ?? 'Todos os dias') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($colaboradores) + 1 }}" class="py-8 text-center text-gray-400">
                                Nenhuma rotina encontrada para este filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 text-xs text-gray-400 font-medium">
        {{ $rotinas->count() }} tarefas encontradas
    </div>
</div>
@endsection