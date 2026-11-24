<div data-lista-acompanhamento>
    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <nav id="abas-acompanhamento" aria-label="Situação da verificação"
             class="grid grid-cols-2 gap-1 rounded-xl border border-[#b39359]/30 bg-[#f4f1ea]/80 p-1 md:grid-cols-4 xl:grid-cols-[auto_auto_auto_auto]">
            @foreach ($abas as $chave => $rotulo)
                @php($total = $totais[$chave])
                <a href="{{ route('acompanhamento.index', [...$parametros, 'aba' => $chave]) }}"
                   @if ($aba === $chave) aria-current="page" @endif
                   class="flex min-h-9 items-center justify-center gap-1 rounded-lg px-1 py-2 text-center text-xs font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#1e2d3d] sm:gap-1.5 sm:px-3 sm:text-sm
                          {{ $aba === $chave ? 'bg-white text-[#1e2d3d] shadow-sm' : 'text-slate-600 hover:text-[#1e2d3d]' }}">
                    {{ $rotulo }}
                    <span class="rounded-full px-1.5 text-xs font-bold tabular-nums
                                 {{ $chave !== 'verificadas' && $total ? 'bg-rose-100 text-rose-800' : 'bg-[#f4f1ea] text-slate-600' }}">
                        {{ $total }}
                    </span>
                </a>
            @endforeach
        </nav>

        <form id="busca-acompanhamento" method="GET" action="{{ route('acompanhamento.index') }}"
              role="search" class="flex h-11 w-full items-center rounded-xl border border-[#b39359]/40 bg-white focus-within:border-[#21394e] xl:w-80 xl:shrink-0">
            <input type="hidden" name="aba" value="{{ $aba }}">
            @if ($parametros['origem'])
                <input type="hidden" name="origem" value="{{ $parametros['origem'] }}">
            @endif
            <button type="submit" aria-label="Buscar" class="flex h-full w-11 shrink-0 items-center justify-center rounded-l-xl text-[#886934] hover:text-[#1e2d3d]">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <input type="search" name="busca" maxlength="150" value="{{ $busca }}"
                   aria-label="Buscar na rotina de acompanhamento" autocomplete="off"
                   placeholder="Buscar colaborador, tarefa ou local..."
                   class="h-full w-full min-w-0 rounded-r-xl bg-transparent pr-3 text-sm text-[#1e2d3d] placeholder:text-slate-500 focus:outline-none">
        </form>
    </div>

    <div id="resultados-acompanhamento" aria-live="polite" tabindex="-1">
        @if ($itens->isNotEmpty())
            <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" aria-label="Tarefas para verificação">
                @foreach ($itens as $item)
                    <li class="min-w-0">
                        <a data-item-acompanhamento
                           href="{{ route('acompanhamento.index', [...$parametros, 'item' => $item->id_item, 'tipo' => $item->tipo === 'atraso' ? null : $item->tipo, 'page' => $itens->currentPage() > 1 ? $itens->currentPage() : null]) }}"
                           class="group flex h-full flex-col rounded-[1.25rem] border-2 bg-white p-4 shadow-sm transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#1e2d3d]
                                  {{ $item->verificado_em ? 'border-[#b39359]/30' : 'border-rose-200' }}">
                            @if ($item->tipo !== 'atraso')
                                @include('processos.acompanhamento.motivo', ['resumir' => true])
                            @endif

                            <div class="mb-3 flex items-start gap-3">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#f4f1ea] transition-transform group-hover:scale-105">
                                    <x-icone-tarefa :icone="$item->icone" :cor="$item->cor_icone" class="h-7 w-7 text-2xl" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h2 class="break-words text-base font-bold leading-snug text-[#1e2d3d]">{{ $item->tarefa }}</h2>
                                    <p class="mt-1 flex min-w-0 items-center gap-1.5 text-sm text-[#1e2d3d]">
                                        <i class="fa-regular fa-user shrink-0 text-xs text-[#886934]" aria-hidden="true"></i>
                                        <span class="min-w-0 break-words">{{ $item->colaborador }}</span>
                                    </p>
                                </div>
                                <i class="fa-solid fa-chevron-right mt-1 shrink-0 text-sm text-[#886934] transition-transform group-hover:translate-x-0.5"
                                   aria-hidden="true"></i>
                            </div>

                            <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-600">
                                <time datetime="{{ $item->horario->toIso8601String() }}" class="whitespace-nowrap font-semibold tabular-nums text-[#1e2d3d]">
                                    {{ $item->horario->isoFormat('HH:mm · ddd, DD/MM') }}
                                </time>
                                <span class="inline-flex min-w-0 items-center gap-1">
                                    <i class="fa-solid fa-location-dot shrink-0 text-[#886934]" aria-hidden="true"></i>
                                        <span class="min-w-0 break-words">{{ $item->local }}</span>
                                </span>
                            </div>

                            <div class="mt-2.5 flex flex-wrap gap-1.5 text-xs font-bold">
                                @if ($item->verificado_em)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-emerald-800">Verificada</span>
                                @else
                                    @include('processos.acompanhamento.selo-sla', ['situacao' => $item->sla_acompanhamento])
                                    @if ($item->tipo === 'atraso')
                                        <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-rose-800">Atraso da tarefa · {{ $item->atraso }}</span>
                                    @endif
                                @endif
                                @if ($item->tipo === 'nao_realizada')
                                    <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-rose-800">Não realizada</span>
                                @elseif ($item->tipo === 'auxilio' || ($item->auxilio && $item->tipo_rotina === \App\Services\ExecucoesDiarias::TIPO_DIARIA))
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-amber-900">{{ $item->processo }}</span>
                                @endif
                                @if ($item->tipo_rotina === \App\Services\ExecucoesDiarias::TIPO_EMERGENCIAL)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-amber-900">Tarefa emergencial</span>
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
                        Nenhuma tarefa em atraso aguardando verificação nesta caixa.
                    @elseif ($aba === 'nao_realizadas')
                        Nenhuma tarefa não realizada aguardando verificação nesta caixa.
                    @elseif ($aba === 'auxilio')
                        Nenhuma solicitação de auxílio aguardando verificação nesta caixa.
                    @else
                        Nenhuma tarefa verificada hoje.
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
