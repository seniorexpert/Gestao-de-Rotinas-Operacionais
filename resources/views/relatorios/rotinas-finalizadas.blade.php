@extends('layouts.app')

@section('title', 'Relatório de Rotinas Finalizadas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    @php
        $campo = 'h-11 w-full min-w-0 rounded-xl border border-[#e8d6bf] bg-white px-3 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
        $primario = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-bold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
        $paginacao = 'flex h-8 w-8 items-center justify-center rounded-full border border-[#e8d6bf] bg-white text-[#17384b] transition-colors';
        $contexto = $filtros + ($registros->currentPage() > 1 ? ['page' => $registros->currentPage()] : []);
    @endphp

    <div class="flex min-w-0 flex-col text-[#17384b]">
        <header class="mb-4 lg:mb-5">
            <h1 class="text-[1.8rem] font-semibold leading-tight lg:text-[2rem]" style="font-family: 'Fraunces', Georgia, serif;">
                Relatório de Rotinas Finalizadas
            </h1>
            <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Consulte o histórico de execução e a comprovação das tarefas.</p>
        </header>

        @if ($errors->any())
            <ul class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        @endif

        <form method="GET" action="{{ route('relatorios.rotinas-finalizadas') }}" role="search"
              aria-label="Filtros de rotinas finalizadas"
              class="mb-4 grid items-end gap-2.5 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.6fr)_auto]">
            <div class="min-w-0 sm:col-span-2 xl:col-span-1">
                <label for="finalizadas-processo" class="mb-1 block text-[0.8125rem] font-semibold">Tipo de processo</label>
                <div class="relative">
                    <select id="finalizadas-processo" name="processo" class="{{ $campo }} appearance-none pr-8">
                        @foreach ($processos as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($processo === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5 sm:col-span-2 xl:contents">
                <div class="min-w-0">
                    <label for="finalizadas-inicio" class="mb-1 block text-[0.8125rem] font-semibold">Data inicial</label>
                    <input id="finalizadas-inicio" name="data_inicio" type="date" value="{{ $filtros['data_inicio'] ?? '' }}" class="{{ $campo }}">
                </div>
                <div class="min-w-0">
                    <label for="finalizadas-fim" class="mb-1 block text-[0.8125rem] font-semibold">Data final</label>
                    <input id="finalizadas-fim" name="data_fim" type="date" value="{{ $filtros['data_fim'] ?? '' }}" class="{{ $campo }}">
                </div>
            </div>

            <div class="min-w-0">
                <label for="finalizadas-grupo" class="mb-1 block text-[0.8125rem] font-semibold">Grupo de acesso</label>
                <div class="relative">
                    <select id="finalizadas-grupo" name="grupo" class="{{ $campo }} appearance-none pr-8">
                        <option value="">Todos</option>
                        @foreach ($grupos as $grupo)
                            <option value="{{ $grupo->id_grupo_acesso }}" @selected(($filtros['grupo'] ?? null) === (int) $grupo->id_grupo_acesso)>
                                {{ $grupo->grupo_acesso }}
                            </option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                </div>
            </div>

            <div class="flex min-w-0 items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="finalizadas-colaborador" class="mb-1 block text-[0.8125rem] font-semibold">Colaborador(a)</label>
                    <div class="relative">
                        <select id="finalizadas-colaborador" name="colaborador" class="{{ $campo }} appearance-none pr-8">
                            <option value="">Todos</option>
                            @foreach ($colaboradores as $nome => $opcoes)
                                <option value="{{ $nome }}" data-grupos="{{ $opcoes->pluck('id_grupo_acesso')->implode(',') }}"
                                        @selected(($filtros['colaborador'] ?? null) === (string) $nome)>
                                    {{ $nome }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                    </div>
                </div>
                <button type="submit" class="{{ $primario }} w-11" title="Consultar" aria-label="Consultar">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
                @if ($filtros !== [] || $errors->any())
                    <a href="{{ route('relatorios.rotinas-finalizadas') }}" title="Limpar filtros" aria-label="Limpar filtros"
                       class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#e8d6bf] bg-white transition-colors hover:bg-[#f8f5ef]">
                        <i class="fa-solid fa-rotate-left text-sm" aria-hidden="true"></i>
                    </a>
                @endif
            </div>

            @if ($registros->total() > 0)
                <a href="{{ route('relatorios.rotinas-finalizadas.exportar', $filtros) }}" class="{{ $primario }} px-5 sm:col-span-2 xl:col-span-1">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i> Exportar Excel
                </a>
            @else
                <button type="button" disabled class="{{ $primario }} cursor-default px-5 opacity-50 sm:col-span-2 xl:col-span-1">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i> Exportar Excel
                </button>
            @endif
        </form>

        <section aria-label="Rotinas finalizadas">
            @if ($registros->isEmpty())
                <div class="rounded-xl border border-[#e8d6bf] bg-[#f8f5ef] px-4 py-10 text-center">
                    <p class="text-sm font-bold">Nenhum registro encontrado</p>
                    @if ($filtros !== [])
                        <a href="{{ route('relatorios.rotinas-finalizadas') }}" class="mt-2 inline-block text-sm font-semibold underline underline-offset-4">Limpar filtros</a>
                    @endif
                </div>
            @else
                <ul class="space-y-3 lg:hidden">
                    @foreach ($registros as $registro)
                        <li>
                            <a href="{{ route('relatorios.rotinas-finalizadas.detalhes', ['id' => $registro->id_item] + $contexto) }}"
                               class="group block rounded-2xl border border-[#e8d6bf] bg-white p-4 shadow-sm transition-colors hover:bg-[#fbf9f5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#17384b]">
                                <time datetime="{{ $registro->finalizado_em->toIso8601String() }}" class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                    <span class="rounded-full bg-[#e4eef1] px-3 py-1 font-bold tabular-nums">{{ $registro->finalizado_em->format('H:i') }}</span>
                                    <span>{{ ucfirst($registro->finalizado_em->dayName) }} · {{ $registro->finalizado_em->format('d/m/Y') }}</span>
                                </time>
                                <div class="my-3 border-b border-[#efe6d9] pb-3">
                                    @include('relatorios.partials.tarefa-rotina-finalizada', ['registro' => $registro])
                                </div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="col-span-2 min-w-0">
                                        <dt class="text-xs text-[#536b7b]">{{ $registro->verificado ? 'Colaborador(a) que verificou' : 'Colaborador(a) que finalizou' }}</dt>
                                        <dd class="break-words font-bold">{{ $registro->finalizador }}</dd>
                                        @if ($registro->grupo_finalizador)
                                            <dd class="text-xs text-[#536b7b]">{{ $registro->grupo_finalizador }}</dd>
                                        @endif
                                    </div>
                                    <div>
                                        <dt class="text-xs text-[#536b7b]">Tempo de execução</dt>
                                        <dd @class(['font-bold tabular-nums', 'text-rose-700' => $registro->acima_do_planejado])>{{ $registro->tempo_execucao ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-[#536b7b]">Tempo planejado</dt>
                                        <dd class="font-bold tabular-nums">{{ $registro->tempo_planejado ?? '—' }}</dd>
                                    </div>
                                    @if (filled($registro->justificativa))
                                        <div class="col-span-2 min-w-0">
                                            <dt class="text-xs text-[#536b7b]">{{ $rotuloResultado }}</dt>
                                            <dd class="whitespace-pre-wrap break-words">{{ $registro->justificativa }}</dd>
                                        </div>
                                    @endif
                                </dl>
                                <span class="mt-3 flex items-center justify-end gap-2 text-sm font-bold">
                                    Ver detalhes
                                    <i class="fa-solid fa-chevron-right text-xs text-[#8b6b43] transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="hidden overflow-hidden rounded-xl border border-[#e8d6bf] bg-white shadow-sm lg:block">
                    <div class="overflow-x-auto">
                        <table class="w-full table-fixed text-left text-sm">
                            <thead class="border-b border-[#e8d6bf] bg-[#f8f5ef] text-xs font-bold uppercase tracking-wide text-[#536b7b]">
                                <tr>
                                    <th scope="col" class="w-[16%] px-4 py-3.5">Finalizado em</th>
                                    <th scope="col" class="w-[17%] px-4 py-3.5">Colaborador(a)</th>
                                    <th scope="col" class="w-[29%] px-4 py-3.5">Tarefa</th>
                                    <th scope="col" class="w-[16%] px-4 py-3.5">Tempo de execução</th>
                                    <th scope="col" class="px-4 py-3.5">{{ $rotuloResultado }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#efe6d9]">
                                @foreach ($registros as $registro)
                                    @php($urlDetalhes = route('relatorios.rotinas-finalizadas.detalhes', ['id' => $registro->id_item] + $contexto))
                                    <tr class="even:bg-[#fcfaf7] hover:bg-[#f5efe5] focus-within:bg-[#f5efe5]">
                                        <td class="px-4 py-3.5 align-middle">
                                            <time datetime="{{ $registro->finalizado_em->toIso8601String() }}" class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                                <span class="inline-flex rounded-full bg-[#e4eef1] px-2.5 py-1 text-xs font-bold tabular-nums">{{ $registro->finalizado_em->format('H:i') }}</span>
                                                <span>
                                                    <span class="block text-xs font-bold">{{ ucfirst($registro->finalizado_em->dayName) }}</span>
                                                    <span class="block text-xs tabular-nums text-[#536b7b]">{{ $registro->finalizado_em->format('d/m/Y') }}</span>
                                                </span>
                                            </time>
                                        </td>
                                        <td class="break-words px-4 py-3.5 align-middle">
                                            <span class="block font-bold">{{ $registro->finalizador }}</span>
                                            @if ($registro->grupo_finalizador)
                                                <span class="mt-0.5 block text-xs text-[#536b7b]">{{ $registro->grupo_finalizador }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 align-middle">
                                            <a href="{{ $urlDetalhes }}"
                                               class="group block rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#17384b]">
                                                @include('relatorios.partials.tarefa-rotina-finalizada', ['registro' => $registro])
                                            </a>
                                        </td>
                                        <td class="px-4 py-3.5 align-middle tabular-nums">
                                            <span @class(['block font-bold', 'text-rose-700' => $registro->acima_do_planejado])>{{ $registro->tempo_execucao ?? '—' }}</span>
                                            <span class="mt-0.5 block text-xs text-[#536b7b]">Planejado {{ $registro->tempo_planejado ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 align-middle">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="min-w-0 whitespace-pre-wrap break-words">{{ filled($registro->justificativa) ? $registro->justificativa : '—' }}</span>
                                                <a href="{{ $urlDetalhes }}" aria-label="Ver detalhes de {{ $registro->tarefa }}"
                                                   class="group flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-[#8b6b43] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#17384b]">
                                                    <i class="fa-solid fa-chevron-right text-xs transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs font-semibold text-[#536b7b]">
                <span>{{ $registros->total() === 1 ? '1 registro encontrado' : $registros->total().' registros encontrados' }}</span>
                @if ($registros->hasPages())
                    <nav class="flex items-center gap-2" aria-label="Paginação dos registros">
                        @if ($registros->previousPageUrl())
                            <a href="{{ $registros->previousPageUrl() }}" rel="prev" aria-label="Página anterior" class="{{ $paginacao }} hover:bg-[#f8f5ef]">
                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        @endif
                        <span aria-current="page" class="px-1 font-bold tabular-nums">{{ $registros->currentPage() }} / {{ $registros->lastPage() }}</span>
                        @if ($registros->nextPageUrl())
                            <a href="{{ $registros->nextPageUrl() }}" rel="next" aria-label="Próxima página" class="{{ $paginacao }} hover:bg-[#f8f5ef]">
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        @endif
                    </nav>
                @endif
            </div>
        </section>
    </div>

    <script>
        (() => {
            const processo = document.getElementById('finalizadas-processo');
            const grupo = document.getElementById('finalizadas-grupo');
            const colaborador = document.getElementById('finalizadas-colaborador');

            const atualizarColaboradores = () => {
                for (const opcao of colaborador.options) {
                    const foraDoGrupo = Boolean(grupo.value && opcao.value && !opcao.dataset.grupos.split(',').includes(grupo.value));
                    opcao.hidden = foraDoGrupo;
                    opcao.disabled = foraDoGrupo;
                }
                if (colaborador.selectedOptions[0]?.disabled) {
                    colaborador.value = '';
                }
            };

            processo.addEventListener('change', () => {
                grupo.value = '';
                colaborador.value = '';
                atualizarColaboradores();
            });
            grupo.addEventListener('change', atualizarColaboradores);
            atualizarColaboradores();
        })();
    </script>
@endsection
