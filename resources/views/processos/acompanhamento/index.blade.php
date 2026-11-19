@extends('layouts.app')

@section('title', 'Rotina de Acompanhamento')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
        <header class="mb-5 lg:mb-6">
            <h1 class="text-[1.8rem] font-semibold leading-tight text-[#1e2d3d] lg:text-[2rem]"
                style="font-family: 'Fraunces', Georgia, serif;">
                Rotina de Acompanhamento
            </h1>
            <p class="mt-1 text-sm text-slate-600 sm:text-[0.95rem]">
                Caixa · {{ $caixa }}
            </p>
        </header>

        @if (session('success'))
            <p class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"
               role="status">
                <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                {{ session('success') }}
            </p>
        @endif

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <nav id="abas-acompanhamento" aria-label="Situação da verificação"
                 class="grid grid-cols-2 gap-1 rounded-xl border border-[#b39359]/30 bg-[#f4f1ea]/80 p-1 sm:w-80">
                @foreach ($abas as $chave => $rotulo)
                    @php($total = $totais[$chave])
                    <a href="{{ route('acompanhamento.index', [...$parametros, 'aba' => $chave]) }}"
                       @if ($aba === $chave) aria-current="page" @endif
                       class="flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#1e2d3d]
                              {{ $aba === $chave ? 'bg-white text-[#1e2d3d] shadow-sm' : 'text-slate-600 hover:text-[#1e2d3d]' }}">
                        {{ $rotulo }}
                        <span class="rounded-full px-1.5 text-xs font-bold tabular-nums
                                     {{ $chave === 'pendentes' && $total ? 'bg-rose-100 text-rose-800' : 'bg-[#f4f1ea] text-slate-600' }}">
                            {{ $total }}
                        </span>
                    </a>
                @endforeach
            </nav>

            <form id="busca-acompanhamento" method="GET" action="{{ route('acompanhamento.index') }}"
                  role="search" class="flex h-12 items-center rounded-xl border border-[#b39359]/40 bg-white shadow-sm focus-within:ring-2 focus-within:ring-[#b39359]/50 sm:w-80">
                <input type="hidden" name="aba" value="{{ $aba }}">
                <button type="submit" aria-label="Buscar" class="flex h-full w-11 shrink-0 items-center justify-center rounded-l-xl text-[#886934] hover:text-[#1e2d3d]">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
                <input type="search" name="busca" maxlength="150" value="{{ $busca }}"
                       aria-label="Buscar na rotina de acompanhamento" autocomplete="off"
                       placeholder="Buscar colaborador, tarefa ou local..."
                       class="h-full w-full min-w-0 rounded-r-xl bg-transparent pr-3 text-sm text-[#1e2d3d] placeholder:text-slate-500 focus:outline-none">
            </form>
        </div>

        <div id="resultados-acompanhamento" aria-live="polite">
            @if ($itens->isNotEmpty())
                <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" aria-label="Tarefas para verificação">
                    @foreach ($itens as $item)
                        <li>
                            <a href="{{ route('acompanhamento.index', [...$parametros, 'item' => $item->id_execucao, 'page' => $itens->currentPage() > 1 ? $itens->currentPage() : null]) }}"
                               class="group flex h-full flex-col rounded-[1.25rem] border-2 bg-white p-4 shadow-sm transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#1e2d3d]
                                      {{ $item->verificado_em ? 'border-[#b39359]/30' : 'border-rose-200' }}">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#f4f1ea] text-2xl text-[#886934] transition-transform group-hover:scale-105"
                                          aria-hidden="true">
                                        <i class="fa-solid {{ $item->auxilio ? 'fa-handshake-angle' : 'fa-list-check' }}"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h2 class="break-words text-base font-bold leading-snug text-[#1e2d3d]">{{ $item->tarefa }}</h2>
                                        <p class="mt-1 flex min-w-0 items-center gap-1.5 text-sm text-[#1e2d3d]">
                                            <i class="fa-regular fa-user shrink-0 text-xs text-[#886934]" aria-hidden="true"></i>
                                            <span class="truncate">{{ $item->colaborador }}</span>
                                        </p>
                                    </div>
                                    <i class="fa-solid fa-chevron-right mt-1 shrink-0 text-sm text-[#886934] transition-transform group-hover:translate-x-0.5"
                                       aria-hidden="true"></i>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-600">
                                    <time datetime="{{ $item->horario->toIso8601String() }}" class="font-semibold tabular-nums text-[#1e2d3d]">
                                        {{ $item->horario->isoFormat('HH:mm · ddd, DD/MM') }}
                                    </time>
                                    <span class="inline-flex min-w-0 items-center gap-1">
                                        <i class="fa-solid fa-location-dot shrink-0 text-[#886934]" aria-hidden="true"></i>
                                        <span class="truncate">{{ $item->local }}</span>
                                    </span>
                                </div>

                                <div class="mt-2.5 flex flex-wrap gap-1.5 text-xs font-bold">
                                    @if ($item->verificado_em)
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-emerald-800">Verificada</span>
                                    @else
                                        @include('processos.acompanhamento.selo-sla', ['situacao' => $item->sla_acompanhamento])
                                        <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-rose-800">Atraso da tarefa · {{ $item->atraso }}</span>
                                    @endif
                                    @if ($item->auxilio)
                                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-amber-900">Solicitação de auxílio</span>
                                    @endif
                                </div>
                                @if (!$item->verificado_em)
                                    <p class="mt-2 text-xs text-slate-600">
                                        Prazo da caixa
                                        <time datetime="{{ $item->limite_acompanhamento->toIso8601String() }}" class="font-semibold tabular-nums">
                                            {{ $item->limite_acompanhamento->format('d/m H:i') }}
                                        </time>
                                    </p>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($itens->hasPages())
                    <div class="mt-5">{{ $itens->links() }}</div>
                @endif
            @else
                <div class="rounded-[1.25rem] border-2 border-dashed border-[#b39359]/35 bg-white px-5 py-12 text-center">
                    <i class="fa-regular fa-circle-check mb-3 text-4xl text-emerald-700" aria-hidden="true"></i>
                    <p class="text-sm font-medium text-slate-600">
                        @if ($busca !== '')
                            Nenhuma tarefa encontrada para essa busca.
                        @elseif ($aba === 'pendentes')
                            Nenhuma tarefa aguardando verificação.
                        @else
                            Nenhuma verificação concluída ainda.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const formulario = document.getElementById('busca-acompanhamento');
            const abas = document.getElementById('abas-acompanhamento');
            const resultados = document.getElementById('resultados-acompanhamento');
            let agendamento;
            let requisicao;

            async function atualizarLista(endereco, atualizarEndereco = false, automatica = false) {
                requisicao?.abort();
                const controle = new AbortController();
                const { signal } = controle;
                requisicao = controle;

                try {
                    const resposta = await fetch(endereco, {
                        signal,
                        cache: 'no-store',
                        headers: automatica ? { 'X-Atualizacao-Automatica': '1' } : {},
                    });
                    if (!resposta.ok) {
                        return;
                    }

                    const pagina = new DOMParser().parseFromString(await resposta.text(), 'text/html');
                    const novasAbas = pagina.getElementById(abas.id);
                    const novosResultados = pagina.getElementById(resultados.id);

                    if (!signal.aborted && novasAbas && novosResultados) {
                        if (abas.innerHTML !== novasAbas.innerHTML) {
                            abas.replaceChildren(...novasAbas.childNodes);
                        }
                        if (resultados.innerHTML !== novosResultados.innerHTML) {
                            resultados.replaceChildren(...novosResultados.childNodes);
                        }
                        if (atualizarEndereco || resposta.redirected) {
                            history.replaceState(history.state, '', resposta.url);
                        }
                    }
                } catch {
                    // O formulário continua disponível para busca com envio normal.
                } finally {
                    if (requisicao === controle) {
                        requisicao = null;
                    }
                }
            }

            formulario.elements.busca.addEventListener('input', () => {
                clearTimeout(agendamento);
                requisicao?.abort();

                agendamento = setTimeout(() => {
                    agendamento = null;
                    const endereco = new URL(formulario.action);
                    endereco.search = new URLSearchParams(new FormData(formulario));
                    atualizarLista(endereco, true);
                }, 250);
            });

            setInterval(() => {
                const foco = document.activeElement;
                if (!document.hidden && !agendamento && !requisicao && !abas.contains(foco) && !resultados.contains(foco)) {
                    atualizarLista(window.location.href, false, true);
                }
            }, 30000);
        })();
    </script>
@endsection
