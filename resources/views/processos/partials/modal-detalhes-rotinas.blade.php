@php
    $primeiraTarefa = $tarefasEdicao->first();
    $statusDasTarefas = $tarefasEdicao->map(fn ($tarefa) => (int) $tarefa->status)->unique();
    $situacao = old('status', $statusDasTarefas->count() === 1 ? (string) $statusDasTarefas->first() : '');
    $responsavel = old('colaborador_id', $primeiraTarefa->id_usuario);
    $voltar = route('relatorios.rotinas', array_filter(['colaborador' => $colaboradorFiltro]));
    $parametrosEdicao = array_filter(['colaborador' => $colaboradorFiltro, 'editar' => $grupoEdicao]);
@endphp

<div id="modalDetalhesRotina" role="dialog" aria-modal="true" aria-labelledby="tituloEdicaoRotina" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="relative flex max-h-[90vh] w-full max-w-[115rem] flex-col rounded-3xl bg-white p-5 shadow-2xl sm:p-8">
        <a href="{{ $voltar }}" aria-label="Fechar edição" class="absolute right-5 top-5 text-gray-500 hover:text-gray-900">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </a>

        <div class="mb-5 shrink-0 pr-8">
            <h2 id="tituloEdicaoRotina" class="mb-1 text-3xl font-bold text-[#1e2d3d]">Editar rotina</h2>
            <p class="sans-text text-sm text-gray-500">{{ $primeiraTarefa->empresa }}</p>
        </div>

        <div class="sans-text min-h-0 flex-1 space-y-6 overflow-y-auto pr-1">
            @if (session('success'))
                <p role="status" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
            @endif

            <form id="formEditarRotina" action="{{ route('rotinas.update', $grupoEdicao) }}" method="POST" class="grid max-w-3xl gap-4 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <div>
                    <label for="editarColaboradorRotina" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Colaborador(a)</label>
                    <select id="editarColaboradorRotina" name="colaborador_id" required class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800">
                        <option value="">Selecione um colaborador</option>
                        @foreach ($colaboradoresEdicao as $colaborador)
                            <option value="{{ $colaborador->id_usuario }}" @selected(is_scalar($responsavel) && (string) $responsavel === (string) $colaborador->id_usuario)>{{ $colaborador->usuario }}</option>
                        @endforeach
                    </select>
                    @error('colaborador_id')
                        <p role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="editarSituacaoRotina" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Situação da rotina</label>
                    <select id="editarSituacaoRotina" name="status" required class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800">
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
                <h3 id="tituloTarefasDaRotina" class="mb-3 text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Tarefas da rotina</h3>

                <ul class="space-y-2.5">
                    @foreach ($tarefasEdicao as $tarefa)
                        @php
                            $morador = trim((string) $tarefa->morador);
                            $dias = count($tarefa->dias_programados) === 7 ? 'Todos os dias' : $tarefa->dias_semanas;
                        @endphp
                        <li class="flex flex-col gap-3 rounded-[1.25rem] border-2 border-[#e8d8c4] bg-white px-3 py-4 text-[#17384b] shadow-sm sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex min-w-0 flex-1 items-start gap-3 sm:items-center sm:gap-5">
                                <x-icone-tarefa :icone="$tarefa->icone" :cor="$tarefa->cor_icone" class="h-9 w-9 text-3xl sm:h-12 sm:w-12 sm:text-[2.65rem]" />
                                <div class="min-w-0">
                                    <p class="mb-1 break-words text-sm font-semibold sm:text-base">{{ $tarefa->tarefa ?? 'Tarefa de rotina' }}</p>
                                    <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs sm:text-sm">
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
                            <div class="flex flex-wrap items-center justify-end gap-x-5 gap-y-2 lg:shrink-0">
                                <a href="{{ route('relatorios.rotinas', [...$parametrosEdicao, 'tarefa' => $tarefa->id_rotina]) }}" class="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold hover:underline">
                                    <i class="fa-solid fa-pen text-[#a77a41]" aria-hidden="true"></i> Editar
                                </a>
                                <form action="{{ route('rotinas.tarefas.destroy', [$grupoEdicao, $tarefa->id_rotina]) }}" method="POST" onsubmit="return confirm('Remover esta tarefa da rotina?')">
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
                   class="mt-3 inline-flex min-h-12 items-center gap-2 rounded-xl border border-[#e7cba6] px-5 py-3 text-sm font-semibold text-[#17384b] transition hover:bg-[#fbf8f3]">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Adicionar nova tarefa
                </a>
            </section>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 text-sm font-bold">
            <form action="{{ route('rotinas.destroy', $grupoEdicao) }}" method="POST" onsubmit="return confirm('Excluir esta rotina e todas as suas tarefas?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-red-200 px-4 py-3 text-red-700 hover:bg-red-50">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Excluir rotina
                </button>
            </form>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ $voltar }}" class="rounded-xl border border-gray-300 px-4 py-3 text-[#1e2d3d] hover:bg-gray-50">Cancelar</a>
                <button type="submit" form="formEditarRotina" class="rounded-xl bg-[#1e2d3d] px-5 py-3 text-white hover:bg-[#1e2d3d]/90">Salvar rotina</button>
            </div>
        </div>
    </div>
</div>
