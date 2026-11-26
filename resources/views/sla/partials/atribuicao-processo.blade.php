@php
    $campo = 'mt-1 w-full min-w-0 rounded-xl border border-[#e8d6bf] bg-white px-3 py-2.5 text-sm font-normal focus:border-[#17384b] focus:outline-none focus:ring-1 focus:ring-[#17384b]';
    $statusDaTarefa = ['pendente' => 'Pendente', 'em_andamento' => 'Em andamento'];
    $coresSla = ['noPrazo' => 'bg-emerald-50 text-emerald-900', 'expirando' => 'bg-amber-50 text-amber-900', 'atrasado' => 'bg-rose-100 text-rose-900', 'semDados' => 'bg-slate-100 text-slate-700'];
    $permiteSelecionar = $colaboradores->isNotEmpty();
@endphp

<div data-painel-atribuicao data-titulo="{{ $atribuiResponsavel ? 'Atribuir tarefa · '.$nomeDoProcesso : $nomeDoProcesso }}">
    <ul class="mb-4 flex flex-wrap gap-1.5 text-xs">
        @foreach ($rotulosStatus as $status => $rotulo)
            <li class="rounded-lg bg-[#f9f7f2] px-2.5 py-1.5">{{ $rotulo }} <strong class="ml-1 tabular-nums">{{ $totais->get($status, 0) }}</strong></li>
        @endforeach
    </ul>

    <form @if ($atribuiResponsavel) action="{{ route('rotinas.atribuir') }}" method="POST" @endif
          @class(['grid items-start gap-4', 'md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' => $atribuiResponsavel])>
        @if ($atribuiResponsavel)
            @csrf
            <input type="hidden" name="processo" value="{{ $processo }}">
        @endif

        <section aria-label="Tarefas do processo" class="min-w-0">
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="text-xs font-semibold">Buscar tarefa
                    <input type="search" data-busca-atribuicao placeholder="Tarefa, morador ou local" class="{{ $campo }}">
                </label>
                <label class="text-xs font-semibold">Colaborador atual
                    <select data-responsavel-atribuicao class="{{ $campo }}">
                        <option value="">Todos os colaboradores</option>
                        @foreach ($tarefas->unique('id_usuario_responsavel')->sortBy('responsavel', SORT_NATURAL | SORT_FLAG_CASE) as $tarefa)
                            <option value="{{ $tarefa->id_usuario_responsavel }}">{{ $tarefa->responsavel ?? 'Colaborador não localizado' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold sm:col-span-2">Status
                    <select data-status-atribuicao class="{{ $campo }}">
                        <option value="">{{ $rotulosStatus['todas'] }}</option>
                        @foreach ($rotulosStatus as $status => $rotulo)
                            @continue($status === 'todas')
                            <option value="{{ $status }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($permiteSelecionar)
                <label class="mt-3 flex min-h-9 cursor-pointer items-center gap-2 rounded-xl bg-[#f9f7f2] px-3 py-2 text-sm font-semibold">
                    <input type="checkbox" data-selecionar-todas class="h-4 w-4 shrink-0 accent-[#17384b]">
                    Selecionar todas
                </label>
            @endif

            <div class="mt-3 grid gap-2 md:max-h-[45dvh] md:overflow-y-auto md:pr-1">
                @foreach ($tarefas as $tarefa)
                    <article data-tarefa-atribuicao data-responsavel="{{ $tarefa->id_usuario_responsavel }}" data-status="{{ $tarefa->status }}"
                             class="rounded-xl border border-[#e8d6bf] p-3">
                        <div class="flex items-start gap-2.5">
                            <x-icone-tarefa :icone="$tarefa->icone" :cor="$tarefa->cor_icone" class="mt-0.5 h-5 w-5 shrink-0 text-lg" />
                            <div class="min-w-0 flex-1 break-words">
                                @if ($permiteSelecionar)
                                    <label class="flex cursor-pointer items-start justify-between gap-3 text-sm font-bold">
                                        <span>{{ $tarefa->tarefa ?? 'Tarefa' }}</span>
                                        <input type="checkbox" name="execucoes_ids[]" value="{{ $tarefa->id_execucao }}"
                                               aria-label="Selecionar {{ $tarefa->tarefa ?? 'tarefa' }} · {{ $tarefa->horario }} · {{ $tarefa->responsavel }}"
                                               class="mt-0.5 h-4 w-4 shrink-0 accent-[#17384b]">
                                    </label>
                                @else
                                    <h3 class="text-sm font-bold">{{ $tarefa->tarefa ?? 'Tarefa' }}</h3>
                                @endif
                                <p class="mt-1 text-xs text-[#536b7b]">
                                    {{ $tarefa->responsavel ?? 'Colaborador não localizado' }}@if ($tarefa->empresa) · {{ $tarefa->empresa }} @endif
                                </p>
                                <p class="mt-1 text-xs text-[#536b7b]">
                                    {{ $tarefa->horario }} · {{ $tarefa->local_rotina ?: ($tarefa->local_cadastrado ?? 'Local não informado') }} · {{ $tarefa->morador ?: 'Tarefa geral' }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="rounded-full bg-[#f9f7f2] px-2 py-0.5 font-semibold">{{ $statusDaTarefa[$tarefa->status] }}</span>
                                    @if ($tarefa->situacao_sla !== 'semDados')
                                        <span class="rounded-full px-2 py-0.5 font-semibold {{ $coresSla[$tarefa->situacao_sla] }}">{{ \App\Services\CalculadoraSla::ROTULOS[$tarefa->situacao_sla] }}</span>
                                    @endif
                                    @if ($tarefa->limite)
                                        <span class="text-[#536b7b]">Prazo {{ $tarefa->limite }}</span>
                                    @endif
                                </div>
                                @if ($tarefa->justificativa ?? null)
                                    <p class="mt-2 text-xs"><strong>Justificativa</strong> · {{ $tarefa->justificativa }}</p>
                                @endif
                                @unless ($atribuiResponsavel)
                                    <a href="{{ route('acompanhamento.index', ['item' => $tarefa->id_item, 'tipo' => $tarefa->tipo]) }}"
                                       class="mt-2 inline-block text-xs font-semibold text-[#805e30] underline underline-offset-2">
                                        {{ auth()->user()->can('editar-modulo', 'acompanhamento') ? 'Verificar tarefa' : 'Ver detalhes' }}
                                    </a>
                                @endunless
                            </div>
                        </div>
                    </article>
                @endforeach
                <p data-vazio-atribuicao role="status" hidden class="rounded-xl border border-dashed border-[#e8d6bf] p-4 text-center text-sm text-[#536b7b]">Nenhuma tarefa para esta seleção.</p>
            </div>
        </section>

        @if ($atribuiResponsavel)
            <section class="rounded-xl border border-[#e8d6bf] bg-[#f9f7f2] p-4">
                <h3 class="text-sm font-bold">Atribuir para</h3>
                @if ($permiteSelecionar)
                    <label class="mt-3 block text-xs font-semibold">Novo responsável
                        <select name="novo_colaborador_id" required class="{{ $campo }}">
                            <option value="">Selecione um colaborador</option>
                            @foreach ($colaboradores as $colaborador)
                                <option value="{{ $colaborador->id_usuario }}" data-grupo="{{ $colaborador->grupo_acesso }}">{{ $colaborador->usuario }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p data-grupo-atribuicao class="mt-1 text-xs text-[#536b7b]"></p>
                    <p data-total-atribuicao aria-live="polite" class="mt-3 text-xs text-[#536b7b]"></p>
                    <p data-erro-atribuicao role="alert" hidden class="mt-3 rounded-lg bg-rose-50 p-2 text-sm text-rose-900"></p>
                    <button type="submit" data-enviar-atribuicao disabled
                            class="mt-3 min-h-10 w-full rounded-xl bg-[#17384b] px-3 py-2 text-sm font-semibold text-white enabled:hover:bg-[#244d64] disabled:cursor-not-allowed disabled:opacity-50">
                        Atribuir tarefa
                    </button>
                @else
                    <p class="mt-2 text-sm text-[#536b7b]">Nenhuma tarefa disponível para atribuição.</p>
                @endif
                <button type="button" data-fechar-atribuicao class="mt-2 min-h-10 w-full rounded-xl border border-[#e8d6bf] bg-white px-3 py-2 text-sm font-semibold hover:border-[#b39359]">Fechar</button>
            </section>
        @endif
    </form>
</div>
