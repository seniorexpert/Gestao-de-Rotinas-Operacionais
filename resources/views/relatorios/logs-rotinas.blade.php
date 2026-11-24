@extends('layouts.app')

@section('title', $titulo)

@section('content')
<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-serif text-3xl font-bold text-[#1e2d3d]">{{ $titulo }}</h1>
        <a href="{{ route('relatorios.logs.excel.exportar', $filtros) }}"
            class="inline-flex items-center gap-2 rounded-xl border border-[#1e2d3d] px-4 py-2 text-sm font-medium text-[#1e2d3d] hover:bg-slate-50">
            <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
            Exportar Excel
        </a>
    </div>

    <form method="GET" action="{{ route('relatorios.logs-rotinas') }}" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
        @php
            $estiloCampo = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#b39359]';
        @endphp
        @if ($errors->any())
            <ul role="alert" class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['data_inicio' => 'Data inicial', 'data_fim' => 'Data final'] as $campo => $rotulo)
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">{{ $rotulo }}</span>
                    <input type="date" name="{{ $campo }}" value="{{ $filtros[$campo] ?? $filtros['data_evento'] ?? $filtros['data_criacao'] ?? '' }}" class="{{ $estiloCampo }}">
                </label>
            @endforeach

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Usuário</span>
                <input type="search" name="nome_executor" maxlength="255" value="{{ $filtros['nome_executor'] ?? '' }}"
                    placeholder="Nome ou login" class="{{ $estiloCampo }}">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Registro</span>
                <input type="search" name="registro_id" maxlength="255" value="{{ $filtros['registro_id'] ?? '' }}"
                    placeholder="Identificador" class="{{ $estiloCampo }}">
            </label>

            @foreach ([
                'tabela' => ['Recurso', $recursos],
                'acao' => ['Ação', $acoes],
                'resultado' => ['Resultado', $resultados],
                'id_empresa' => ['Unidade', $unidades],
            ] as $campo => [$rotulo, $opcoes])
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">{{ $rotulo }}</span>
                    <select name="{{ $campo }}" class="{{ $estiloCampo }}">
                        <option value="">Todos</option>
                        @foreach ($opcoes as $valor => $opcao)
                            <option value="{{ $valor }}" @selected((string) ($filtros[$campo] ?? '') === (string) $valor)>{{ $opcao }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>

        <details class="mt-4" @if (filled($filtros['tarefa'] ?? null) || filled($filtros['grupo_acesso'] ?? null) || filled($filtros['colaborador_original'] ?? null)) open @endif>
            <summary class="cursor-pointer text-sm font-medium text-[#1e2d3d]">Filtros de rotinas</summary>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                @foreach (['tarefa' => 'Tarefa', 'colaborador_original' => 'Colaborador responsável', 'grupo_acesso' => 'Grupo do responsável'] as $campo => $rotulo)
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">{{ $rotulo }}</span>
                        <input type="search" name="{{ $campo }}" maxlength="255" value="{{ $filtros[$campo] ?? '' }}" class="{{ $estiloCampo }}">
                    </label>
                @endforeach
            </div>
        </details>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-xl bg-[#1e2d3d] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#142434]">Pesquisar</button>
            <a href="{{ route('relatorios.logs-rotinas') }}" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Limpar filtros</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3 text-sm text-slate-600 sm:px-6">
            {{ number_format($logs->total(), 0, ',', '.') }} {{ $logs->total() === 1 ? 'evento encontrado' : 'eventos encontrados' }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-left text-sm">
                <thead class="bg-[#fbfaf7] text-xs font-semibold text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">Data e hora (Brasília)</th>
                        <th scope="col" class="px-4 py-3">Usuário</th>
                        <th scope="col" class="px-4 py-3">Evento</th>
                        <th scope="col" class="px-4 py-3">Recurso</th>
                        <th scope="col" class="px-4 py-3">Resultado</th>
                        <th scope="col" aria-label="Detalhes" class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-4 align-top text-slate-700 tabular-nums sm:px-6">
                                {{ $log->data_local }}
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="block font-medium text-slate-900">{{ $log->autor }}</span>
                                @if ($log->login)
                                    <span class="text-xs text-slate-600">{{ $log->login }}</span>
                                @endif
                            </td>
                            <td class="min-w-56 px-4 py-4 align-top">
                                <span class="block font-semibold text-[#1e2d3d]">{{ $log->descricao_evento }}</span>
                                <span class="text-xs text-slate-600">{{ $log->acao_label }}</span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="block text-slate-800">{{ $log->recurso }}</span>
                                @if ($log->empresa || $log->tarefa)
                                    <span class="text-xs text-slate-600">{{ implode(' · ', array_filter([$log->empresa, $log->tarefa])) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span @class([
                                    'inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-800' => $log->resultado === 'sucesso',
                                    'bg-rose-50 text-rose-800' => in_array($log->resultado, ['falha', 'erro', 'recusado', 'invalido'], true),
                                    'bg-slate-100 text-slate-700' => in_array($log->resultado, [null, 'sem_alteracao'], true),
                                ])>{{ $log->resultado_label }}</span>
                            </td>
                            <td class="px-4 py-4 text-right align-top">
                                <a href="{{ route('relatorios.logs.detalhes', ['id' => $log->id, ...$filtros, 'page' => $logs->currentPage()]) }}"
                                    class="whitespace-nowrap font-medium text-[#1e2d3d] underline-offset-4 hover:underline"
                                    aria-label="Ver detalhes do evento {{ $log->id }}">Ver detalhes</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-600">Nenhum evento encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-slate-200 p-4 sm:px-6">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
