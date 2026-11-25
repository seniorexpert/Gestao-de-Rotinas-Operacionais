@php
    $primeiraTarefa = $tarefasEdicao->first();
    $statusDasTarefas = $tarefasEdicao->map(fn ($tarefa) => (int) $tarefa->status)->unique();
    $situacao = old('status', $statusDasTarefas->count() === 1 ? (string) $statusDasTarefas->first() : '');
    $responsavel = old('colaborador_id', $primeiraTarefa->id_usuario);
    $voltar = route('relatorios.rotinas', $filtrosRelatorio);
    $parametrosEdicao = [...$filtrosRelatorio, 'editar' => $grupoEdicao];
@endphp

@push('head')
    <style>
        html:has(#modalDetalhesRotina) { overflow: hidden; }
    </style>
@endpush

<div id="modalDetalhesRotina" role="dialog" aria-modal="true" aria-labelledby="tituloEdicaoRotina" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="relative flex max-h-[90dvh] w-full max-w-[clamp(36rem,50%,57.5rem)] flex-col rounded-3xl bg-white p-4 shadow-2xl sm:p-5">
        <a href="{{ $voltar }}" aria-label="Fechar edição" class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 hover:text-gray-900">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </a>

        <div class="mb-4 shrink-0 pr-9">
            <h2 id="tituloEdicaoRotina" class="mb-1 text-2xl font-bold leading-tight text-[#1e2d3d]">Editar rotina</h2>
            <p class="sans-text text-sm text-gray-500">{{ $primeiraTarefa->empresa }}</p>
        </div>

        <div class="sans-text min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain pr-1">
            @if (session('success'))
                <p role="status" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
            @endif

            <form id="formEditarRotina" action="{{ route('rotinas.update', ['grupo' => $grupoEdicao, ...$filtrosRelatorio]) }}" method="POST" class="grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <div class="min-w-0">
                    <label for="editarColaboradorRotina" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Colaborador(a)</label>
                    <select id="editarColaboradorRotina" name="colaborador_id" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800">
                        <option value="">Selecione um colaborador</option>
                        @foreach ($colaboradoresEdicao as $colaborador)
                            <option value="{{ $colaborador->id_usuario }}" @selected(is_scalar($responsavel) && (string) $responsavel === (string) $colaborador->id_usuario)>{{ $colaborador->usuario }}</option>
                        @endforeach
                    </select>
                    @error('colaborador_id')
                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="editarSituacaoRotina" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Situação da rotina</label>
                    <select id="editarSituacaoRotina" name="status" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800">
                        <option value="" @selected($situacao === '')>Selecione a situação</option>
                        <option value="1" @selected($situacao === '1')>Ativa</option>
                        <option value="0" @selected($situacao === '0')>Inativa</option>
                    </select>
                    @error('status')
                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </form>

            <section aria-labelledby="tituloTarefasDaRotina">
                <h3 id="tituloTarefasDaRotina" class="mb-2 text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Tarefas da rotina</h3>

                <ul class="space-y-2">
                    @foreach ($tarefasEdicao as $tarefa)
                        @php
                            $morador = trim((string) $tarefa->morador);
                            $dias = count($tarefa->dias_programados) === 7 ? 'Todos os dias' : $tarefa->dias_semanas;
                        @endphp
                        <li class="flex flex-col gap-x-3 gap-y-1.5 rounded-2xl border-2 border-[#e8d8c4] bg-white px-3 py-2.5 text-[#17384b] shadow-sm md:flex-row md:items-center">
                            <div class="flex min-w-0 flex-1 items-start gap-2.5">
                                <x-icone-tarefa :icone="$tarefa->icone" :cor="$tarefa->cor_icone" class="mt-0.5 h-8 w-8 text-2xl sm:h-9 sm:w-9 sm:text-3xl" />
                                <div class="min-w-0 [overflow-wrap:anywhere]">
                                    <p class="mb-1 text-sm font-semibold leading-snug">{{ $tarefa->tarefa ?? 'Tarefa de rotina' }}</p>
                                    <dl class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs leading-relaxed sm:text-[0.8125rem]">
                                        <div class="min-w-0">
                                            <dt class="inline font-semibold"><i class="fa-regular fa-clock mr-1 text-[#a77a41]" aria-hidden="true"></i> Horário:</dt>
                                            <dd class="inline tabular-nums">{{ substr($tarefa->horario, 0, 5) }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="inline font-semibold"><i class="fa-regular fa-calendar-days mr-1 text-[#a77a41]" aria-hidden="true"></i> Dias:</dt>
                                            <dd class="inline break-words">{{ $dias }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="inline font-semibold"><i class="fa-regular fa-user mr-1 text-[#a77a41]" aria-hidden="true"></i> {{ strpbrk($morador, ',;') === false ? 'Morador(a):' : 'Moradores:' }}</dt>
                                            <dd class="inline break-words">{{ $morador ?: 'Tarefa geral' }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="inline font-semibold"><i class="fa-solid fa-location-dot mr-1 text-[#a77a41]" aria-hidden="true"></i> Local:</dt>
                                            <dd class="inline break-words">{{ $tarefa->local_cadastrado ?: ($tarefa->local_rotina ?: 'Não informado') }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="inline font-semibold"><i class="fa-solid fa-link mr-1 text-[#a77a41]" aria-hidden="true"></i> Auxílio:</dt>
                                            <dd class="inline">{{ $tarefa->auxilio ? 'Sim' : 'Não' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center justify-end gap-x-3 gap-y-1">
                                <a href="{{ route('relatorios.rotinas', [...$parametrosEdicao, 'tarefa' => $tarefa->id_rotina]) }}" class="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold hover:underline">
                                    <i class="fa-solid fa-pen text-[#a77a41]" aria-hidden="true"></i> Editar
                                </a>
                                <form action="{{ route('rotinas.tarefas.destroy', ['grupo' => $grupoEdicao, 'tarefa' => $tarefa->id_rotina, ...$filtrosRelatorio]) }}" method="POST" onsubmit="return confirm('Remover esta tarefa da rotina?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-red-700 hover:underline">
                                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Remover
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('relatorios.rotinas', [...$parametrosEdicao, 'adicionar_tarefa' => '1']) }}"
                   class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#e7cba6] px-4 py-2.5 text-sm font-semibold text-[#17384b] transition hover:bg-[#fbf8f3]">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Adicionar nova tarefa
                </a>
            </section>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-gray-200 pt-3 text-xs font-bold sm:text-sm">
            <form action="{{ route('rotinas.destroy', ['grupo' => $grupoEdicao, ...$filtrosRelatorio]) }}" method="POST" onsubmit="return confirm('Excluir esta rotina e todas as suas tarefas?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-red-200 px-3 py-2 text-red-700 hover:bg-red-50">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Excluir rotina
                </button>
            </form>
            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                <a href="{{ $voltar }}" class="inline-flex min-h-10 items-center rounded-xl border border-gray-300 px-3 py-2 text-[#1e2d3d] hover:bg-gray-50">Cancelar</a>
                <button type="submit" form="formEditarRotina" class="min-h-10 rounded-xl bg-[#1e2d3d] px-3 py-2 text-white hover:bg-[#1e2d3d]/90">Salvar rotina</button>
            </div>
        </div>
    </div>
</div>
