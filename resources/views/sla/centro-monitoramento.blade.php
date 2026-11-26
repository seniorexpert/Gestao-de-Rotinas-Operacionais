@extends('layouts.app')

@section('title', 'Centro de Monitoramento')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $iconesProcessos = [
        'diaria' => 'fa-calendar-check',
        'acompanhamento' => 'fa-clipboard-check',
        'auxilio' => 'fa-handshake-angle',
        'emergencial' => 'fa-bell',
        'nao_realizada' => 'fa-clipboard-list',
    ];
    $faixasSla = [
        'noPrazo' => ['No prazo', 'border-emerald-100 bg-emerald-50 text-emerald-900 hover:border-emerald-500'],
        'expirando' => ['Expirando', 'border-amber-100 bg-amber-50 text-amber-900 hover:border-amber-500'],
        'atrasado' => ['Atrasada', 'border-rose-100 bg-rose-50 text-rose-900 hover:border-rose-500'],
        'semDados' => ['Sem dados de SLA', 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-500'],
    ];
    $classePainel = 'min-w-0 rounded-2xl border border-[#e8d6bf] bg-white p-4 shadow-sm sm:p-5';
    $classeFoco = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
@endphp

<div class="min-w-0 text-[#17384b]">
    <header class="mb-5 flex flex-col gap-3 sm:mb-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Centro de Monitoramento</h1>
            <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Processos, prazos e produtividade da equipe</p>
        </div>
        @can('editar-modulo', 'monitoramento')
            <div class="flex shrink-0 flex-col gap-1 lg:items-end">
                <button type="button" aria-haspopup="dialog" aria-controls="modalAtribuirTarefa"
                        onclick="abrirModalAtribuir(@js($filtros['processo'] ?? null))" @disabled(!isset($filtros['processo']))
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#17384b] px-5 py-2.5 text-sm font-semibold text-white transition-colors enabled:hover:bg-[#244d64] disabled:cursor-not-allowed disabled:opacity-50 {{ $classeFoco }}">
                    <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                    Atribuir tarefa
                </button>
                @unless (isset($filtros['processo']))
                    <p class="text-xs text-[#536b7b]">Selecione um processo para abrir as tarefas.</p>
                @endunless
            </div>
        @endcan
    </header>

    <div id="painelMonitoramento">
        <section aria-labelledby="titulo-processos" class="mb-4 min-w-0 sm:mb-5">
            <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="titulo-processos" class="text-base font-bold">Processos</h2>
                <p class="text-xs tabular-nums text-[#536b7b] sm:text-sm">{{ $data }} · Horário de Brasília</p>
            </div>
            @if ($filtros !== [])
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[#e8d6bf] bg-[#f9f7f2] px-3 py-2 text-sm">
                    <p>
                        <span class="font-semibold">Filtros ativos</span>
                        @if (isset($filtros['processo'])) · {{ $nomesProcessos[$filtros['processo']] }} @endif
                        @if (isset($filtros['status'])) · {{ $rotulosStatus[$filtros['status']] }} @endif
                        @if (isset($filtros['sla'])) · {{ $faixasSla[$filtros['sla']][0] }} @endif
                    </p>
                    <a href="{{ route('monitoramento.index') }}" class="rounded px-1 text-sm font-semibold underline underline-offset-2 {{ $classeFoco }}">Limpar filtros</a>
                </div>
            @endif

            <div class="grid min-w-0 auto-rows-fr gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($processos as $processo => $totais)
                    <article data-processo="{{ $processo }}" aria-labelledby="processo-{{ $processo }}"
                             @class(['relative isolate flex min-w-0 flex-col rounded-2xl border bg-white p-3.5 shadow-sm transition-colors hover:border-[#b39359]', 'border-[#17384b]/55' => ($filtros['processo'] ?? null) === $processo, 'border-[#e8d6bf]' => ($filtros['processo'] ?? null) !== $processo])>
                        <header class="mb-2 flex min-h-9 items-center gap-2">
                            <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#f9f7f2] min-[375px]:flex" aria-hidden="true">
                                <i class="fa-solid {{ $iconesProcessos[$processo] }} text-base"></i>
                            </span>
                            <h3 id="processo-{{ $processo }}" class="min-w-0 truncate text-[0.8125rem] font-bold leading-snug sm:text-[0.95rem]">
                                <a href="{{ route('monitoramento.index', [...$filtros, 'processo' => $processo, 'status' => null]) }}"
                                   aria-label="Filtrar pelo processo {{ $nomesProcessos[$processo] }}"
                                   title="{{ $nomesProcessos[$processo] }}"
                                   class="after:absolute after:inset-0 after:rounded-2xl after:content-[''] focus-visible:outline-none focus-visible:after:outline focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-[#17384b]">
                                    {{ $nomesProcessos[$processo] }}
                                </a>
                            </h3>
                        </header>
                        <div @class(['grid gap-1.5', 'grid-cols-2' => $processo === 'diaria'])>
                            @foreach ($statusPorProcesso[$processo] as $status => $rotulo)
                                @php
                                    $ativo = ($filtros['processo'] ?? null) === $processo && ($filtros['status'] ?? 'todas') === $status;
                                    $destino = $ativo ? ['processo' => null, 'status' => null] : ['processo' => $processo, 'status' => $status];
                                @endphp
                                <a href="{{ route('monitoramento.index', [...$filtros, ...$destino]) }}" @if ($ativo) aria-current="true" @endif
                                   @class([
                                      'relative z-10 flex min-h-9 min-w-0 items-center justify-between gap-1 rounded-lg border px-2 py-1.5 text-xs font-medium leading-snug transition-colors', $classeFoco,
                                      'col-span-2' => $processo === 'diaria' && $status === 'todas',
                                      'border-[#17384b] bg-[#17384b] text-white hover:bg-[#244d64]' => $ativo,
                                      'border-[#efe7da] bg-[#f9f7f2] hover:border-[#b39359] hover:bg-white' => !$ativo,
                                   ])>
                                    <span class="whitespace-nowrap">{{ $rotulo }}</span>
                                    <strong class="shrink-0 text-base leading-none tabular-nums">{{ $totais->get($status, 0) }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="col-span-full rounded-xl border border-dashed border-[#e8d6bf] px-4 py-6 text-center text-sm text-[#536b7b]">Nenhum processo tem tarefas nesta faixa de SLA.</p>
                @endforelse
            </div>
        </section>

        <div class="grid min-w-0 items-stretch gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:gap-4">
            <section aria-labelledby="titulo-sla" class="{{ $classePainel }} flex flex-col">
                <h2 id="titulo-sla" class="mb-3 text-base font-bold">SLA</h2>
                <div class="grid flex-1 auto-rows-fr gap-2">
                    @foreach ($faixasSla as $situacao => [$rotulo, $estilo])
                        @php
                            $quantidade = $situacoes->get($situacao, 0);
                            $ativo = ($filtros['sla'] ?? null) === $situacao;
                        @endphp
                        @continue($situacao === 'semDados' && $quantidade === 0 && !$ativo)
                        <a href="{{ route('monitoramento.index', [...$filtros, 'sla' => $ativo ? null : $situacao]) }}"
                           data-sla="{{ $situacao }}" @if ($ativo) aria-current="true" @endif
                           @class(['flex min-h-14 items-center justify-between gap-3 rounded-xl border px-3 py-2.5 transition-colors lg:[container-type:size]', $classeFoco, $estilo, 'ring-2 ring-inset ring-current' => $ativo])>
                            <span class="text-base font-semibold leading-tight lg:text-[length:clamp(1rem,48cqh,1.5rem)]">{{ $rotulo }}</span>
                            <span class="flex flex-wrap items-baseline justify-end gap-x-2 text-right tabular-nums">
                                <span class="whitespace-nowrap text-sm leading-tight lg:text-[length:clamp(0.875rem,42cqh,1.125rem)]">{{ $quantidade }} {{ $quantidade === 1 ? 'tarefa' : 'tarefas' }}</span>
                                <strong class="text-xl leading-tight lg:text-[length:clamp(1.25rem,60cqh,1.75rem)]">{{ round(100 * $quantidade / max(1, $situacoes->sum())) }}%</strong>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section aria-labelledby="titulo-produtividade" class="{{ $classePainel }}">
                <h2 id="titulo-produtividade" class="mb-3 text-base font-bold">Produtividade</h2>
                <div class="grid gap-2">
                    @forelse ($produtividades as $prod)
                        <button type="button" aria-haspopup="dialog" data-produtividade-usuario="{{ $prod->colaborador_id }}"
                                class="block w-full min-w-0 rounded-xl border border-[#e8d6bf] px-3.5 py-2.5 text-left transition-colors hover:border-[#b39359] hover:bg-[#f9f7f2] {{ $classeFoco }}"
                                onclick="abrirModalDetalhesProd(@js((string) $prod->colaborador_id), @js($prod->colaborador_nome), @js($prod->total_tarefas), @js($prod->unidades))">
                            <span class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                                <span class="min-w-0">
                                    <strong class="block break-words text-sm">{{ $prod->colaborador_nome }}</strong>
                                    <span class="block break-words text-xs text-[#536b7b]">{{ $prod->unidades }}</span>
                                </span>
                                <span class="flex min-w-0 flex-wrap items-center gap-1.5 sm:ml-auto sm:justify-end">
                                    @foreach (['atrasado', 'expirando', 'noPrazo', 'semDados'] as $situacao)
                                        @php
                                            [$rotulo, $estilo] = $faixasSla[$situacao];
                                            $quantidade = $prod->situacoes_sla->get($situacao, 0);
                                        @endphp
                                        @continue($situacao === 'semDados' && $quantidade === 0)
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium {{ $estilo }}">
                                            <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                            {{ $rotulo }}
                                            <strong class="tabular-nums">{{ $quantidade }}</strong>
                                        </span>
                                    @endforeach
                                    <span class="whitespace-nowrap text-xs font-medium tabular-nums text-[#536b7b] sm:ml-1.5 sm:text-sm">{{ $prod->concluidas }} de {{ $prod->total_tarefas }} concluídas</span>
                                </span>
                            </span>
                            <span class="mt-2.5 flex items-center gap-2.5">
                                <progress class="h-1.5 w-full accent-[#17384b]" max="100" value="{{ $prod->percentual }}" aria-label="Percentual de tarefas concluídas"></progress>
                                <span class="shrink-0 text-xs font-semibold tabular-nums">{{ $prod->percentual }}%</span>
                                <i class="fa-solid fa-chevron-right text-[0.65rem] text-[#8b6b43]" aria-hidden="true"></i>
                            </span>
                        </button>
                    @empty
                        <p class="py-5 text-center text-sm text-[#536b7b]">Nenhum colaborador tem tarefas para esta seleção.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>

@can('editar-modulo', 'monitoramento')
    @include('sla.partials.modal-atribuir-tarefa')
@endcan
@include('sla.partials.modal-detalhes-produtividade')

<script>
    (() => {
        let atualizando = false;
        const ids = ['painelMonitoramento', 'modalProdListaTarefas'];
        const painel = document.getElementById(ids[0]);
        const modalAtribuir = document.getElementById('modalAtribuirTarefa');
        const modalProdutividade = document.getElementById('modalDetalhesProdutividade');
        const modalAberto = () => modalAtribuir?.open || !modalProdutividade.classList.contains('hidden');

        setInterval(async () => {
            if (document.hidden || atualizando || modalAberto() || painel.contains(document.activeElement)) return;
            atualizando = true;
            try {
                const resposta = await fetch(location.href, {
                    cache: 'no-store',
                    headers: { 'X-Atualizacao-Automatica': '1' },
                });
                if (!resposta.ok || resposta.redirected || modalAberto()) return;

                const pagina = new DOMParser().parseFromString(await resposta.text(), 'text/html');
                if (modalAberto() || painel.contains(document.activeElement) || ids.some(id => !pagina.getElementById(id))) return;
                for (const id of ids) {
                    const atual = document.getElementById(id);
                    const novo = pagina.getElementById(id);
                    if (atual.innerHTML !== novo.innerHTML) {
                        atual.replaceChildren(...novo.childNodes);
                    }
                }
            } catch {
                // A próxima atualização tenta consultar o painel novamente.
            } finally {
                atualizando = false;
            }
        }, 30000);
    })();
</script>
@endsection
