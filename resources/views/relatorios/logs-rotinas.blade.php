@extends('layouts.app')

@section('title', $titulo)

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $estiloCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm placeholder:text-[#64748b] focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $estiloBotao = 'inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#17384b] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
    $estiloRotulo = 'mb-1 block text-[0.8125rem] font-semibold';
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">{{ $titulo }}</h1>
        <a href="{{ route('relatorios.logs.excel.exportar', $filtros) }}" class="{{ $estiloBotao }}">
            <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
            Exportar Excel
        </a>
    </div>

    <form method="GET" action="{{ route('relatorios.logs-rotinas') }}" class="mb-6 rounded-2xl border border-[#e8d6bf] bg-white p-4 sm:p-6">
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
                    <span class="{{ $estiloRotulo }}">{{ $rotulo }}</span>
                    <input type="date" name="{{ $campo }}" value="{{ $filtros[$campo] ?? $filtros['data_evento'] ?? $filtros['data_criacao'] ?? '' }}" class="{{ $estiloCampo }}">
                </label>
            @endforeach

            <label class="block">
                <span class="{{ $estiloRotulo }}">Usuário</span>
                <input type="search" name="nome_executor" maxlength="255" value="{{ $filtros['nome_executor'] ?? '' }}"
                    placeholder="Nome ou login" class="{{ $estiloCampo }}">
            </label>
            <label class="block">
                <span class="{{ $estiloRotulo }}">Registro</span>
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
                    <span class="{{ $estiloRotulo }}">{{ $rotulo }}</span>
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
            <summary class="cursor-pointer text-sm font-semibold">Filtros de rotinas</summary>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                @foreach (['tarefa' => 'Tarefa', 'colaborador_original' => 'Colaborador responsável', 'grupo_acesso' => 'Grupo do responsável'] as $campo => $rotulo)
                    <label class="block">
                        <span class="{{ $estiloRotulo }}">{{ $rotulo }}</span>
                        <input type="search" name="{{ $campo }}" maxlength="255" value="{{ $filtros[$campo] ?? '' }}" class="{{ $estiloCampo }}">
                    </label>
                @endforeach
            </div>
        </details>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="{{ $estiloBotao }}">Pesquisar</button>
            <a href="{{ route('relatorios.logs-rotinas') }}" class="inline-flex h-11 items-center rounded-xl px-4 text-sm font-semibold text-[#536b7b] hover:bg-[#f9f7f2]">Limpar filtros</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#e4ceb2] bg-white">
        <div class="border-b border-[#e8d6bf] px-4 py-3 text-sm text-[#536b7b] sm:px-6">
            {{ number_format($logs->total(), 0, ',', '.') }} {{ $logs->total() === 1 ? 'evento encontrado' : 'eventos encontrados' }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-left text-sm">
                <thead class="border-b border-[#e8d6bf] bg-[#f9f7f2] text-xs font-semibold uppercase text-[#536b7b]">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-6">Data e hora (Brasília)</th>
                        <th scope="col" class="px-4 py-3">Usuário</th>
                        <th scope="col" class="px-4 py-3">Evento</th>
                        <th scope="col" class="px-4 py-3">Recurso</th>
                        <th scope="col" class="px-4 py-3">Resultado</th>
                        <th scope="col" aria-label="Detalhes" class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f0e9df]">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4 align-top tabular-nums sm:px-6">
                                {{ $log->data_local }}
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="block font-semibold">{{ $log->autor }}</span>
                                @if ($log->login)
                                    <span class="text-xs text-[#536b7b]">{{ $log->login }}</span>
                                @endif
                            </td>
                            <td class="min-w-56 px-4 py-4 align-top">
                                <span class="block font-semibold">{{ $log->descricao_evento }}</span>
                                <span class="text-xs text-[#536b7b]">{{ $log->acao_label }}</span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="block">{{ $log->recurso }}</span>
                                @if ($log->empresa || $log->tarefa)
                                    <span class="text-xs text-[#536b7b]">{{ implode(' · ', array_filter([$log->empresa, $log->tarefa])) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span @class([
                                    'inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-[#e2f1e6] text-[#176b39]' => $log->resultado === 'sucesso',
                                    'bg-rose-50 text-rose-800' => in_array($log->resultado, ['falha', 'erro', 'recusado', 'invalido'], true),
                                    'bg-[#f0ebe2] text-[#536b7b]' => in_array($log->resultado, [null, 'historico', 'sem_alteracao'], true),
                                ])>{{ $log->resultado_label }}</span>
                            </td>
                            <td class="px-4 py-4 text-right align-top">
                                <a href="{{ route('relatorios.logs.detalhes', ['id' => $log->id, ...$filtros, 'page' => $logs->currentPage()]) }}"
                                    class="whitespace-nowrap font-semibold underline-offset-4 hover:text-[#8b6b43] hover:underline"
                                    aria-label="Ver detalhes do evento {{ $log->id }}">Ver detalhes</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-[#536b7b]">Nenhum evento encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-[#e8d6bf] p-4 sm:px-6 [&_a]:border-[#e8d6bf] [&_a]:text-[#17384b] [&_a:hover]:bg-[#f9f7f2] [&_p]:text-[#536b7b] [&_span]:border-[#e8d6bf] [&_span]:text-[#536b7b] [&_[aria-current=page]>span]:bg-[#f9f7f2]">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
