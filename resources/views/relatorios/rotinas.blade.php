@extends('layouts.app')

@section('title', 'Relatório de Rotinas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $podeCadastrar = !$edicaoTarefa && !$rotinaParaAdicionarTarefa && auth()->user()->can('editar-modulo', 'rotinas');
    $totalRotinas = $rotinas->count();
    $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-semibold text-white transition hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-5 sm:mb-6">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Rotinas</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie as rotinas de colaboradores cadastradas</p>
    </div>

    <form method="GET" action="{{ route('relatorios.rotinas') }}" class="mb-4 grid gap-3 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] md:items-end md:gap-2">
        <div class="min-w-0">
            <label for="filtroColaboradorRotina" class="mb-1 block text-[0.8125rem] font-semibold">Colaborador(a)</label>
            <div class="relative">
                <select id="filtroColaboradorRotina" name="colaborador" class="{{ $classeCampo }} appearance-none pr-10">
                    <option value="Todas">Todas</option>
                    @foreach ($colaboradores as $colaborador)
                        <option value="{{ $colaborador }}" @selected($colaboradorFiltro === $colaborador)>{{ $colaborador }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
            </div>
        </div>

        <div class="min-w-0">
            <label for="filtroMoradorRotina" class="mb-1 block text-[0.8125rem] font-semibold">Morador(a)</label>
            <input id="filtroMoradorRotina" type="search" name="morador" value="{{ $moradorFiltro }}" maxlength="255" placeholder="Buscar por nome"
                   class="{{ $classeCampo }} placeholder:text-[#64748b]">
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="submit" aria-label="Buscar rotinas" title="Buscar rotinas" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
            @if ($filtrosRelatorio !== [])
                <a href="{{ route('relatorios.rotinas') }}" class="inline-flex h-11 items-center px-2 text-sm font-semibold hover:underline">Limpar</a>
            @endif
            @if ($podeCadastrar)
                <button type="button" onclick="abrirModal('rotinaModal')" aria-haspopup="dialog" class="{{ $classeBotao }} px-4">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
                </button>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm">
        <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#17384b]" role="region" aria-label="Rotinas encontradas" tabindex="0">
            <table id="tabelaRotinas" class="w-full min-w-[48rem] border-collapse text-left text-sm">
                <thead class="border-b border-[#e8d6bf] bg-[#f9f7f2] text-xs font-semibold uppercase text-[#536b7b]">
                    <tr>
                        <th scope="col" class="w-20 px-4 py-3.5">ID</th>
                        <th scope="col" class="px-4 py-3.5">Colaborador(a)</th>
                        <th scope="col" class="w-44 px-4 py-3.5">Unidade</th>
                        <th scope="col" class="w-40 px-4 py-3.5">Horários</th>
                        <th scope="col" class="w-28 px-4 py-3.5">Tarefas</th>
                        <th scope="col" class="w-36 px-4 py-3.5">Situação</th>
                        <th scope="col" class="relative w-24 px-4 py-3.5"><span class="sr-only">Ação</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f0e9df]">
                    @forelse ($rotinas as $rotina)
                        @php
                            $horarioInicio = $rotina->horario_inicio ? substr($rotina->horario_inicio, 0, 5) : null;
                            $horarioFim = $rotina->horario_fim ? substr($rotina->horario_fim, 0, 5) : null;
                        @endphp
                        <tr class="linha-rotina transition-colors hover:bg-[#fcfaf6]">
                            <td class="px-4 py-4 font-semibold tabular-nums text-[#536b7b]">#{{ $rotina->id_rotina_principal }}</td>
                            <td class="px-4 py-4">
                                <span class="block font-semibold leading-snug">{{ $rotina->nome_colaborador ?? 'Desconhecido' }}</span>
                                @if ($rotina->cargo_colaborador)
                                    <span class="mt-1 block text-xs text-[#536b7b]">{{ $rotina->cargo_colaborador }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 font-medium">{{ $rotina->unidade_colaborador ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold tabular-nums">
                                @if ($horarioInicio && $horarioFim && $horarioInicio !== $horarioFim)
                                    {{ $horarioInicio }} – {{ $horarioFim }}
                                @else
                                    {{ $horarioInicio ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex whitespace-nowrap rounded-full bg-[#f9f7f2] px-3 py-1 text-xs font-semibold">
                                    {{ $rotina->total_tarefas == 1 ? '1 tarefa' : "{$rotina->total_tarefas} tarefas" }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-[#e2f1e6] text-[#176b39]' => $rotina->todas_ativas,
                                    'bg-[#fdf1d9] text-[#80521f]' => $rotina->status_rotina && !$rotina->todas_ativas,
                                    'bg-[#f0ebe2] text-[#536b7b]' => !$rotina->status_rotina,
                                ])>
                                    <span class="h-2 w-2 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                                    {{ $rotina->todas_ativas ? 'Ativa' : ($rotina->status_rotina ? 'Parcialmente ativa' : 'Inativa') }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                @can('editar-modulo', 'rotinas')
                                    <a href="{{ route('relatorios.rotinas', [...$filtrosRelatorio, 'editar' => $rotina->grupo_rotina ?? $rotina->id_rotina_principal]) }}"
                                       class="inline-flex items-center gap-2 whitespace-nowrap text-xs font-semibold hover:underline">
                                        Editar <i class="fa-solid fa-chevron-right text-[0.625rem] text-[#a36f39]" aria-hidden="true"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-[#536b7b]">
                                Nenhuma rotina encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-3 text-xs font-semibold text-[#536b7b]">{{ $totalRotinas }} {{ $totalRotinas === 1 ? 'rotina encontrada' : 'rotinas encontradas' }}</p>
</div>

@can('editar-modulo', 'rotinas')
    @unless ($edicaoTarefa || $rotinaParaAdicionarTarefa)
        @include('processos.partials.modal-rotina')
        @if ($grupoEdicao !== null)
            @include('processos.partials.modal-detalhes-rotinas')
        @endif
    @endunless
    @include('processos.partials.modal-tarefa-rotina', ['edicaoTarefa' => $edicaoTarefa, 'rotinaParaAdicionarTarefa' => $rotinaParaAdicionarTarefa])
@endcan

@endsection
