@extends('layouts.app')

@section('title', 'Relatório de Log\'s - Rotinas')

@section('content')
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Relatório de Log's - Rotinas</h1>
        <p class="text-sm text-gray-500 mt-1">Visualize todas as ações e eventos registrados no sistema usando os filtros de pesquisa</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 mb-6">
        <form method="GET" action="{{ route('relatorios.logs-rotinas') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Data de Criação</label>
                <input type="date" name="data_criacao" value="{{ $dataCriacao ?? '' }}" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Data do Evento</label>
                <input type="date" name="data_evento" value="{{ $dataEvento ?? '' }}" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Nome do Executor</label>
                <input type="text" name="nome_executor" value="{{ $nomeExecutor ?? '' }}" placeholder="Parte do nome" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tarefa</label>
                <input type="text" name="tarefa" value="{{ $tarefaFiltro ?? '' }}" placeholder="Parte do nome da tarefa" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Descrição do Evento</label>
                <select name="descricao_evento" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
                    <option value="Todas">Todas</option>
                    @foreach($descricoesEventos ?? [] as $desc)
                        <option value="{{ $desc }}" {{ isset($descricaoEvento) && $descricaoEvento == $desc ? 'selected' : '' }}>{{ $desc }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Grupo de Acesso</label>
                <select name="grupo_acesso" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
                    <option value="Todos">Todos</option>
                    @foreach($gruposAcesso ?? [] as $grupo)
                        <option value="{{ $grupo }}" {{ isset($grupoAcesso) && $grupoAcesso == $grupo ? 'selected' : '' }}>{{ $grupo }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Colaborador(a) Original da Tarefa</label>
                <select name="colaborador_original" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900">
                    <option value="Todos">Todos</option>
                    @foreach($colaboradores ?? [] as $colab)
                        <option value="{{ $colab }}" {{ isset($colaboradorOriginal) && $colaboradorOriginal == $colab ? 'selected' : '' }}>{{ $colab }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="bg-[#1b2a38] hover:bg-[#111c26] text-white p-3 rounded-xl transition flex items-center justify-center w-12 h-11 shrink-0" title="Pesquisar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                <a href="{{ route('relatorios.logs-rotinas') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 p-3 rounded-xl transition flex items-center justify-center w-12 h-11 shrink-0" title="Limpar Filtros">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
                <a href="{{ route('relatorios.logs.excel.exportar') }}?{{ request()->getQueryString() }}" class="inline-flex items-center justify-center space-x-2 bg-[#1b2a38] hover:bg-[#111c26] text-white px-5 py-2.5 rounded-xl font-medium text-sm transition h-11 w-full">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Exportar Excel</span>
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#fcfbf9] border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-4 px-6 w-20">ID</th>
                        <th class="py-4 px-6 w-36">Situação</th>
                        <th class="py-4 px-6">Evento</th>
                        <th class="py-4 px-6">Tarefa</th>
                        <th class="py-4 px-6 w-52">Colaborador(a)</th>
                        <th class="py-4 px-6 w-40">Executor</th>
                        <th class="py-4 px-6 w-16 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($logs ?? [] as $log)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-4 px-6 font-semibold text-gray-400 tabular-nums">#{{ $log->id }}</td>
                            <td class="py-4 px-6">
                                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $log->situacao == 'Finalizado' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $log->situacao ?? 'Aberto' }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="block font-bold text-[#1b2a38]">{{ $log->descricao_evento ?? 'Ação registrada' }}</span>
                                <span class="block text-xs text-gray-400 mt-0.5 tabular-nums">{{ $log->data_evento ?? $log->created_at }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="block font-bold text-[#1b2a38] uppercase text-xs">{{ $log->tarefa ?? '—' }}</span>
                                <span class="block text-xs text-gray-400 mt-0.5">{{ $log->detalhe_tarefa ?? '' }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="block font-bold text-[#1b2a38]">{{ $log->colaborador_original ?? '—' }}</span>
                                <span class="block text-xs text-gray-400 mt-0.5">{{ $log->grupo_acesso ?? '' }}</span>
                            </td>
                            <td class="py-4 px-6 font-medium text-[#1b2a38]">
                                {{ $log->executor ?? 'Sistema' }}
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button type="button" 
                                    onclick="abrirModalDetalhesLog(this)" 
                                    data-id="{{ $log->id }}"
                                    data-situacao="{{ $log->situacao ?? 'Aberto' }}"
                                    data-descricao="{{ $log->descricao_evento ?? '—' }}"
                                    data-data="{{ $log->data_evento ?? $log->created_at ?? '—' }}"
                                    data-tarefa="{{ $log->tarefa ?? '—' }}"
                                    data-detalhe="{{ $log->detalhe_tarefa ?? '' }}"
                                    data-colaborador="{{ $log->colaborador_original ?? '—' }}"
                                    data-grupo="{{ $log->grupo_acesso ?? '' }}"
                                    data-executor="{{ $log->executor ?? 'Sistema' }}"
                                    data-criado="{{ $log->created_at ?? '—' }}"
                                    class="text-gray-400 hover:text-gray-700 p-2 transition">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400">
                                Nenhum evento registrado encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(isset($logs) && method_exists($logs, 'links'))
            <div class="p-4 border-t border-gray-200 flex items-center justify-between">
                <span class="text-xs text-gray-500">{{ $logs->total() }} eventos encontrados</span>
                <div>{{ $logs->links() }}</div>
            </div>
        @endif
    </div>

</div>

<div id="modalDetalhesLog" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-3xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="fecharModalLog()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 class="text-3xl font-bold text-[#1e2d3d] mb-3">Detalhes do Registro de Log</h2>
            <div class="flex items-center gap-2">
                <span id="modalLogSituacao" class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Aberto</span>
                <span id="modalLogId" class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 tabular-nums">#101</span>
            </div>
        </div>

        <div class="space-y-6 overflow-y-auto flex-1 pr-1">
            <div class="border border-gray-200 rounded-3xl p-6 bg-white shadow-sm space-y-6">
                <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-2xl bg-[#f4f1ea] flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-file-lines text-[#1e2d3d] text-lg"></i>
                    </div>
                    <div>
                        <h3 id="modalLogDescricaoEvento" class="font-bold text-[#1e2d3d] text-base mb-0.5">—</h3>
                        <p class="text-xs text-gray-500 font-medium">Origem: Rotinas Diárias</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pb-6 border-b border-gray-100 text-sm">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Data do Evento</span>
                        <span id="modalLogDataEvento" class="font-bold text-[#1e2d3d] tabular-nums">—</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Executor</span>
                        <span id="modalLogExecutor" class="font-bold text-[#1e2d3d]">—</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pb-6 border-b border-gray-100 text-sm">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Tarefa</span>
                        <span id="modalLogTarefa" class="block font-bold text-[#1e2d3d] uppercase text-xs leading-snug mb-1">—</span>
                        <span id="modalLogHoraProgramada" class="text-xs text-gray-500 font-medium"></span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Colaborador(a) Original</span>
                        <span id="modalLogColaborador" class="block font-bold text-[#1e2d3d]">—</span>
                        <span id="modalLogGrupo" class="text-xs text-gray-500 font-medium"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Criado em</span>
                        <span id="modalLogCriadoEm" class="font-bold text-[#1e2d3d] tabular-nums">—</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Finalizado em</span>
                        <span id="modalLogFinalizadoEm" class="font-bold text-[#1e2d3d] tabular-nums">Em aberto</span>
                    </div>
                </div>
            </div>

            <div class="w-full py-4 bg-[#f4f1ea] rounded-2xl text-center text-xs font-bold text-[#1e2d3d]">
                Evento registrado no sistema
            </div>
        </div>
    </div>
</div>

@include('relatorios.partials.modal-detalhes-log')

@endsection