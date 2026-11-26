@extends('layouts.app')

@section('title', 'Relatório de Rotinas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $podeEditar = auth()->user()->can('editar-modulo', 'rotinas');
    $podeCadastrar = !$edicaoTarefa && !$rotinaParaAdicionarTarefa && $podeEditar;
    $totalRotinas = $rotinas->count();
    $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-semibold text-white transition hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
    $classeEdicao = 'inline-flex min-h-9 shrink-0 items-center gap-2 whitespace-nowrap text-xs font-semibold hover:underline';
    $classeContagem = 'inline-flex rounded-full bg-[#f9f7f2] px-2.5 py-1 text-xs font-semibold';

    $registros = $rotinas->map(function (stdClass $rotina) use ($filtrosRelatorio, $podeEditar): array {
        $inicio = $rotina->horario_inicio ? substr($rotina->horario_inicio, 0, 5) : null;
        $fim = $rotina->horario_fim ? substr($rotina->horario_fim, 0, 5) : null;
        $ativa = (bool) $rotina->status_rotina;
        $parcial = $ativa && ! $rotina->todas_ativas;

        return [
            'id' => $rotina->id_rotina_principal,
            'nome' => $rotina->nome_colaborador ?? 'Desconhecido',
            'cargo' => $rotina->cargo_colaborador,
            'unidade' => $rotina->unidade_colaborador ?? '—',
            'horarios' => $inicio && $fim && $inicio !== $fim ? "$inicio – $fim" : ($inicio ?? '—'),
            'tarefas' => $rotina->total_tarefas == 1 ? '1 tarefa' : "{$rotina->total_tarefas} tarefas",
            'ativa' => $ativa,
            'parcial' => $parcial,
            'situacao' => $parcial ? 'Parcialmente ativa' : ($ativa ? 'Ativa' : 'Inativa'),
            'edicao' => $podeEditar
                ? route('relatorios.rotinas', [...$filtrosRelatorio, 'editar' => $rotina->grupo_rotina ?? $rotina->id_rotina_principal])
                : null,
        ];
    });
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-5 sm:mb-6">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Rotinas</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie as rotinas de colaboradores cadastradas</p>
    </div>

    <form method="GET" action="{{ route('relatorios.rotinas') }}" role="search" aria-label="Filtros de rotinas"
          class="mb-4 grid min-w-0 items-end gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_auto]">
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

        <div class="flex min-w-0 items-end gap-2 lg:contents">
            <div class="min-w-0 flex-1">
                <label for="filtroMoradorRotina" class="mb-1 block text-[0.8125rem] font-semibold">Morador(a)</label>
                <input id="filtroMoradorRotina" type="search" name="morador" value="{{ $moradorFiltro }}" maxlength="255" placeholder="Buscar por nome"
                       class="{{ $classeCampo }} min-w-0 placeholder:text-[#64748b]">
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="submit" aria-label="Buscar rotinas" title="Buscar rotinas" class="{{ $classeBotao }} w-11">
                    <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
                </button>
                @if ($filtrosRelatorio !== [])
                    <a href="{{ route('relatorios.rotinas') }}" class="inline-flex h-11 items-center px-2 text-sm font-semibold hover:underline">Limpar</a>
                @endif
            </div>
        </div>
        @if ($podeCadastrar)
            <button type="button" onclick="abrirModal('rotinaModal')" aria-haspopup="dialog" class="{{ $classeBotao }} w-full px-4 sm:col-span-2 lg:col-span-1 lg:w-auto">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
            </button>
        @endif
    </form>

    <ul id="cartoesRotinas" class="space-y-3 lg:hidden" aria-label="Rotinas encontradas">
        @forelse ($registros as $registro)
            <x-cartao-registro-relatorio :identificador="$registro['id']" :titulo="$registro['nome']" :subtitulo="$registro['cargo']"
                                        :campos="['Unidade' => $registro['unidade'], 'Horários' => $registro['horarios']]">
                <div class="flex min-w-0 flex-wrap items-center gap-x-1.5">
                    <dt class="font-semibold text-[#536b7b]">Tarefas:</dt>
                    <dd class="{{ $classeContagem }}">{{ $registro['tarefas'] }}</dd>
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-x-1.5">
                    <dt class="font-semibold text-[#536b7b]">Situação:</dt>
                    <dd class="min-w-0">
                        <x-selo-situacao-relatorio :ativo="$registro['ativa']" :parcial="$registro['parcial']" :rotulo="$registro['situacao']" />
                    </dd>
                </div>
                @if ($registro['edicao'])
                    <x-slot:acao>
                        <a href="{{ $registro['edicao'] }}" class="{{ $classeEdicao }}">
                            Editar <i class="fa-solid fa-chevron-right text-[0.625rem] text-[#a36f39]" aria-hidden="true"></i>
                        </a>
                    </x-slot:acao>
                @endif
            </x-cartao-registro-relatorio>
        @empty
            <li class="rounded-xl border border-[#e8d6bf] bg-[#f9f7f2] px-4 py-8 text-center text-sm text-[#536b7b]">Nenhuma rotina encontrada.</li>
        @endforelse
    </ul>

    <div class="hidden overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm lg:block">
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
                    @forelse ($registros as $registro)
                        <tr class="linha-rotina transition-colors hover:bg-[#fcfaf6]">
                            <td class="px-4 py-4 font-semibold tabular-nums text-[#536b7b]">#{{ $registro['id'] }}</td>
                            <td class="px-4 py-4">
                                <span class="block break-words font-semibold leading-snug">{{ $registro['nome'] }}</span>
                                @if ($registro['cargo'])
                                    <span class="mt-1 block break-words text-xs text-[#536b7b]">{{ $registro['cargo'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 font-medium">{{ $registro['unidade'] }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold tabular-nums">{{ $registro['horarios'] }}</td>
                            <td class="px-4 py-4">
                                <span class="{{ $classeContagem }} whitespace-nowrap">{{ $registro['tarefas'] }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <x-selo-situacao-relatorio :ativo="$registro['ativa']" :parcial="$registro['parcial']" :rotulo="$registro['situacao']" />
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if ($registro['edicao'])
                                    <a href="{{ $registro['edicao'] }}" class="{{ $classeEdicao }}">
                                        Editar <i class="fa-solid fa-chevron-right text-[0.625rem] text-[#a36f39]" aria-hidden="true"></i>
                                    </a>
                                @endif
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
