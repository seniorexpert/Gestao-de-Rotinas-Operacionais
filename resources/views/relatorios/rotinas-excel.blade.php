@extends('layouts.app')

@section('title', 'Relatório de Rotinas - Excel')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    @php
        $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
        $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-bold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
    @endphp

    <div class="flex min-w-0 flex-col text-[#17384b]">
        <div class="mb-4 lg:mb-5">
            <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight lg:text-[2rem]">Relatório de Rotinas - Excel</h1>
            <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Consulte as rotinas por colaborador(a) e exporte para o Excel</p>
        </div>

        <div class="relative z-30 mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" action="{{ route('relatorios.excel') }}"
                  class="grid min-w-0 flex-1 gap-2.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                <div class="min-w-0">
                    <label for="filtro-excel-colaborador" class="mb-1 block text-[0.8125rem] font-semibold">Colaborador(a)</label>
                    <div class="relative">
                        <select id="filtro-excel-colaborador" name="colaborador" class="{{ $classeCampo }} appearance-none pr-9">
                            <option value="Todas">Todas</option>
                            @foreach ($opcoesColaboradores as $nome)
                                <option value="{{ $nome }}" @selected(($filtros['colaborador'] ?? null) === $nome)>{{ $nome }}</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="min-w-0">
                    <label for="filtro-excel-grupo" class="mb-1 block text-[0.8125rem] font-semibold">Grupo de acesso</label>
                    <div class="relative">
                        <select id="filtro-excel-grupo" name="grupo" class="{{ $classeCampo }} appearance-none pr-9">
                            <option value="">Todos</option>
                            @foreach ($grupos as $grupo)
                                <option value="{{ $grupo->id_grupo_acesso }}" @selected(($filtros['grupo'] ?? null) === (int) $grupo->id_grupo_acesso)>{{ $grupo->grupo_acesso }}</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="min-w-0">
                    <label for="filtro-excel-morador" class="mb-1 block text-[0.8125rem] font-semibold">Morador(a)</label>
                    <input id="filtro-excel-morador" type="search" name="morador" maxlength="255" value="{{ $filtros['morador'] ?? '' }}"
                           placeholder="Buscar por nome" class="{{ $classeCampo }} placeholder:text-[#64748b]">
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" aria-label="Buscar rotinas" title="Buscar rotinas" class="{{ $classeBotao }} w-11">
                        <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
                    </button>
                    @if ($filtros !== [])
                        <a href="{{ route('relatorios.excel') }}" class="inline-flex h-11 items-center px-2 text-sm font-semibold hover:underline">Limpar</a>
                    @endif
                </div>
            </form>

            @if ($totalTarefas > 0)
                <a href="{{ route('relatorios.excel.exportar', $filtros) }}" class="{{ $classeBotao }} w-full px-5 sm:w-auto sm:self-end">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i> Exportar Excel
                </a>
            @else
                <button type="button" disabled class="{{ $classeBotao }} w-full cursor-default px-5 opacity-50 sm:w-auto sm:self-end">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i> Exportar Excel
                </button>
            @endif
        </div>

        <section aria-label="Resultado do relatório" class="flex min-h-0 min-w-0 flex-col pb-2">
            @if ($totalTarefas === 0)
                <div class="rounded-xl border border-[#e4ceb2] bg-[#f9f7f2] px-4 py-10 text-center">
                    <p class="text-sm font-bold">Nenhuma tarefa encontrada</p>
                    <p class="mt-1 text-xs text-[#536b7b]">Ajuste a busca ou cadastre rotinas para os colaboradores.</p>
                    @if ($filtros !== [])
                        <a href="{{ route('relatorios.excel') }}" class="mt-3 inline-block text-xs font-bold underline underline-offset-4 hover:text-[#855f33]">Limpar busca</a>
                    @endif
                </div>
            @else
                <ul class="space-y-3 md:hidden" aria-label="Tarefas por colaborador">
                    @foreach ($colaboradores as $colaborador)
                        <li class="overflow-hidden rounded-[1.25rem] border-2 border-[#e8d8c4] bg-white shadow-sm">
                            <div class="flex items-start justify-between gap-3 border-b border-[#eee2d3] bg-[#f9f7f2] px-4 py-3">
                                <div class="min-w-0">
                                    <p class="break-words text-base font-bold leading-snug">{{ $colaborador->usuario }}</p>
                                    @if ($colaborador->grupo_acesso)
                                        <p class="mt-0.5 text-xs font-medium text-[#536b7b]">{{ $colaborador->grupo_acesso }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 pt-0.5 text-xs font-bold text-[#536b7b]">{{ $colaborador->texto_quantidade }}</span>
                            </div>

                            <ul class="divide-y divide-[#f0e9df]">
                                @foreach ($colaborador->tarefas as $tarefa)
                                    <li class="flex items-start gap-2.5 px-4 py-3">
                                        <time datetime="{{ $tarefa->horario }}" class="shrink-0 rounded-full bg-[#e5f0f4] px-2.5 py-1 text-xs font-bold tabular-nums">{{ $tarefa->horario }}</time>
                                        @include('relatorios.partials.tarefa-rotinas-excel', ['tarefa' => $tarefa])
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>

                <div class="hidden overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm md:block">
                    <div class="max-h-[68vh] overflow-auto focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#17384b]"
                         role="region" aria-label="Grade de horários e colaboradores" tabindex="0">
                        <table class="w-full table-fixed border-separate border-spacing-0 text-left" style="min-width: {{ 6 + $colaboradores->count() * 15 }}rem">
                            <colgroup>
                                <col class="w-24">
                                @foreach ($colaboradores as $colaborador)
                                    <col>
                                @endforeach
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="sticky left-0 top-0 z-20 border-b border-r border-[#e8d6bf] bg-[#f9f7f2] px-3 py-3 text-center text-xs font-bold uppercase tracking-wide text-[#536b7b]">Hora</th>
                                    @foreach ($colaboradores as $colaborador)
                                        <th scope="col" class="sticky top-0 z-10 border-b border-r border-[#e8d6bf] bg-[#f9f7f2] px-4 py-3 align-top last:border-r-0">
                                            <span class="block break-words text-sm font-bold">{{ $colaborador->usuario }}</span>
                                            <span class="mt-0.5 block break-words text-xs font-medium text-[#536b7b]">{{ $colaborador->resumo }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($horarios as $horario)
                                    <tr class="group even:bg-[#fbf8f3]">
                                        <th scope="row" class="sticky left-0 z-10 border-b border-r border-[#eee2d3] bg-white px-3 py-3 text-center align-middle text-sm font-bold tabular-nums group-even:bg-[#f9f7f2] group-last:border-b-0">{{ $horario }}</th>
                                        @foreach ($colaboradores as $colaborador)
                                            <td class="space-y-2 border-b border-r border-[#eee2d3] p-2 align-top last:border-r-0 group-last:border-b-0">
                                                @foreach ($tarefasPorHorario->get($horario)->get($colaborador->id_usuario, []) as $tarefa)
                                                    <div class="flex min-w-0 items-start gap-2.5 rounded-lg border border-[#eadcc9] bg-white px-3 py-2">
                                                        @include('relatorios.partials.tarefa-rotinas-excel', ['tarefa' => $tarefa])
                                                    </div>
                                                @endforeach
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-3 text-xs font-semibold text-[#536b7b]">{{ $textoTotal }}</p>
            @endif
        </section>
    </div>
@endsection
