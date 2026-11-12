@extends('layouts.app')

@section('title', 'Relatório de Rotinas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Relatório de Rotinas</h1>
            <p class="text-sm text-gray-500 mt-1">Visualize, busque e gerencie as rotinas de colaboradores cadastradas</p>
        </div>
        <a href="#" onclick="abrirModal('rotinaModal')" class="inline-flex items-center justify-center space-x-2 bg-[#1b2a38] hover:bg-[#111c26] text-white px-5 py-3 rounded-xl font-medium text-sm transition shrink-0">
            <i class="fa-solid fa-plus"></i>
            <span>Novo registro</span>
        </a>
    </div>

    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-200 mb-6">
        <form method="GET" action="{{ route('relatorios.rotinas') }}" class="flex flex-col sm:flex-row items-end gap-3">
            <div class="w-full">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Colaborador(a)</label>
                <div class="relative w-full">
                    <select name="colaborador" class="w-full pl-4 pr-10 py-3 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition">
                        <option value="Todas">Todas</option>
                        @foreach($colaboradores as $colab)
                            <option value="{{ $colab }}" {{ isset($colaboradorFiltro) && $colaboradorFiltro == $colab ? 'selected' : '' }}>
                                {{ $colab }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="submit" class="w-full sm:w-auto bg-[#1b2a38] hover:bg-[#111c26] text-white px-5 py-3 rounded-xl font-medium text-sm transition">
                    Filtrar
                </button>
                <a href="{{ route('relatorios.rotinas') }}" class="w-full sm:w-auto bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-3 rounded-xl font-medium text-sm transition text-center">
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tabelaRotinas" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#fcfbf9] border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-4 px-6 w-20">ID</th>
                        <th class="py-4 px-6">Colaborador(a)</th>
                        <th class="py-4 px-6 w-44">Unidade</th>
                        <th class="py-4 px-6 w-44">Horários</th>
                        <th class="py-4 px-6 w-32">Tarefas</th>
                        <th class="py-4 px-6 w-32">Situação</th>
                        <th class="py-4 px-6 w-28 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($rotinas as $rotina)
                        <tr class="linha-rotina hover:bg-gray-50/60 transition">
                            <td class="py-4 px-6 font-semibold text-gray-400 tabular-nums">#{{ $rotina->id_rotina_principal }}</td>
                            <td class="py-4 px-6">
                                <span class="block leading-snug font-bold text-[#1b2a38]">
                                    {{ $rotina->nome_colaborador ?? 'Desconhecido' }}
                                </span>
                                <span class="mt-0.5 block text-xs font-medium text-gray-400">
                                    {{ $rotina->cargo_colaborador ?? '' }}
                                </span>
                            </td>
                            <td class="py-4 px-6 font-medium text-[#1b2a38]">
                                {{ $rotina->unidade_colaborador ?? '—' }}
                            </td>
                            <td class="py-4 px-6 font-semibold text-[#1b2a38] tabular-nums">
                                @if($rotina->horario_inicio && $rotina->horario_fim && $rotina->horario_inicio != $rotina->horario_fim)
                                    {{ $rotina->horario_inicio }} – {{ $rotina->horario_fim }}
                                @else
                                    {{ $rotina->horario_inicio ?? '—' }}
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center rounded-full bg-[#f4f1ea] px-3 py-1 text-xs font-bold text-[#1b2a38]">
                                    {{ $rotina->total_tarefas == 1 ? '1 tarefa' : "{$rotina->total_tarefas} tarefas" }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-3 py-1 rounded-full text-xs font-bold {{ isset($rotina->status_rotina) && $rotina->status_rotina == 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ isset($rotina->status_rotina) && $rotina->status_rotina == 1 ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button type="button" onclick="openModal('modalDetalhesRotina')" class="inline-flex items-center space-x-1 text-sm font-semibold text-gray-700 hover:text-gray-900 transition">
                                    <span>Editar</span>
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400">
                                Nenhuma rotina encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@include('processos.partials.modal-rotina')
@include('processos.partials.modal-tarefa-rotina')
@include('processos.partials.modal-detalhes-rotinas')

@endsection

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }
</script>