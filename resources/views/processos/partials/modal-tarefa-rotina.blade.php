@php
    $emergencial = $emergencial ?? false;
    $modalTarefa = $emergencial ? 'tarefaRotinaEmergencialModal' : 'tarefaRotinaModal';
    $agoraEmergencial = $emergencial ? now(\App\Services\ExecucoesDiarias::FUSO) : null;
    $tarefaEmEdicao = $edicaoTarefa['registro'] ?? null;
    $editandoTarefa = $tarefaEmEdicao !== null;
    $rotinaDaTarefa = $tarefaEmEdicao ?? $rotinaParaAdicionarTarefa ?? null;
    $salvarNaRotina = $rotinaDaTarefa !== null;
    $localObrigatorio = !$editandoTarefa || $tarefaEmEdicao->id_local !== null;
    $classeCancelar = 'w-1/2 py-3.5 rounded-2xl border border-[#e5dec9] text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center';
    $acaoFormulario = route($emergencial ? 'rotinas.emergenciais.store' : 'rotinas.store');
    $idFormulario = 'formSalvarRotina';
    if ($salvarNaRotina) {
        $acaoFormulario = $editandoTarefa
            ? route('rotinas.tarefas.update', [$grupoEdicao, $tarefaEmEdicao->id_rotina])
            : route('rotinas.tarefas.store', $grupoEdicao);
        $idFormulario = $editandoTarefa ? 'formEditarTarefaRotina' : 'formAdicionarTarefaRotina';
        $voltarParaRotina = route('relatorios.rotinas', array_filter(['colaborador' => $colaboradorFiltro, 'editar' => $grupoEdicao]));
        $unidadeDaTarefa = (int) $rotinaDaTarefa->id_empresa;
        $tarefas = $tarefas->filter(fn ($tarefa) => in_array($unidadeDaTarefa, $tarefa->ids_empresas, true));
        $locais = $locais->filter(fn ($local) => in_array($unidadeDaTarefa, $local->ids_empresas, true));
        $diasSelecionados = array_map('intval', array_filter((array) old('dias', $errors->any() ? [] : ($edicaoTarefa['dias'] ?? [])), 'is_scalar'));
        $auxiliaresSelecionados = array_map('intval', array_filter((array) old('auxiliares', $errors->any() ? [] : ($edicaoTarefa['auxiliares'] ?? [])), 'is_scalar'));
        $auxilioSelecionado = in_array(old('auxilio', (int) ($tarefaEmEdicao->auxilio ?? 0)), [1, '1'], true);
        $horarioSelecionado = old('horario', $tarefaEmEdicao->horario ?? '');
        $tarefaSelecionada = old('id_tarefa', $tarefaEmEdicao->id_tarefa ?? null);
        $localSelecionado = old('id_local', $tarefaEmEdicao->id_local ?? null);
        $moradorSelecionado = old('morador', $tarefaEmEdicao->morador ?? null);
        $observacaoSelecionada = old('observacao', $tarefaEmEdicao->obs ?? null);
    }
@endphp

<div id="{{ $modalTarefa }}" role="dialog" aria-modal="true" aria-labelledby="modalTarefaTitulo" @class(['fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4', 'hidden' => !$salvarNaRotina])>
    <div class="bg-white rounded-3xl max-w-xl w-full p-5 sm:p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        @if ($salvarNaRotina)
            <a href="{{ $voltarParaRotina }}" aria-label="Fechar" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700">
                <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
            </a>
        @else
            <button type="button" onclick="fecharModalTarefa()" aria-label="Fechar" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700">
                <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
            </button>
        @endif

        <div class="shrink-0 pr-8">
            <h2 id="modalTarefaTitulo" class="text-3xl font-bold text-[#1e2d3d] mb-1">{{ $emergencial ? 'Dados da tarefa emergencial' : 'Dados da tarefa' }}</h2>
            <p class="sans-text text-gray-500 text-sm mb-6">{{ $editandoTarefa ? 'Edite os dados da tarefa da rotina' : 'Adicione esta tarefa à rotina' }}</p>
        </div>

        <form action="{{ $acaoFormulario }}" method="POST" id="{{ $idFormulario }}" class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            @csrf
            @if ($salvarNaRotina)
                @if ($editandoTarefa)
                    @method('PUT')
                @endif
                @if ($errors->any())
                    <ul role="alert" class="space-y-1 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        @foreach ($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <input type="hidden" name="tarefas_json" id="tarefasJsonInput">
                <input type="hidden" name="id_empresa">
                <input type="hidden" name="colaborador_id">
                @unless ($emergencial)
                    <input type="hidden" name="status_rotina">
                @endunless
                <input type="hidden" id="tarefaIndexEdicao" value="">
            @endif

            @if ($emergencial)
                <div>
                    <label for="inputDataEmergencial" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Data de execução</label>
                    <input type="date" id="inputDataEmergencial" required
                           value="{{ $agoraEmergencial->toDateString() }}" min="{{ $agoraEmergencial->toDateString() }}"
                           onchange="atualizarDiaEmergencial()" aria-describedby="diaEmergencial"
                           class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none">
                    <p id="diaEmergencial" class="mt-1 text-xs capitalize text-gray-500">{{ $agoraEmergencial->locale('pt_BR')->dayName }}</p>
                </div>
            @else
            <div class="relative">
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Dia(s) da semana</label>
                
                <button type="button" onclick="toggleDiasDropdown()" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 flex items-center justify-between focus:outline-none focus:border-[#1e2d3d]">
                    <span id="diasTextoBtn" class="text-gray-400">Selecionar dias</span>
                    <i id="diasIconeSetra" class="fa-solid fa-chevron-up transition-transform duration-200 text-gray-400 text-xs"></i>
                </button>

                <div id="diasDropdownList" class="absolute left-0 right-0 mt-2 bg-white border border-gray-300 rounded-2xl p-4 max-h-56 overflow-y-auto space-y-3 shadow-xl z-50 hidden">
                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition border-b border-gray-100 pb-3">
                        <span class="text-sm font-bold text-[#1e2d3d]">Selecionar todos os dias</span>
                        <input type="checkbox" id="selectAllDias" onchange="toggleTodosDias(this)" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                    </label>

                    @foreach (\App\Services\ExecucoesDiarias::DIAS_DA_SEMANA as $dia => $nome)
                        <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                            <span class="text-sm text-gray-800">{{ $nome }}</span>
                            <input type="checkbox" name="{{ $salvarNaRotina ? 'dias[]' : 'dias_semana[]' }}" value="{{ $salvarNaRotina ? $dia : $nome }}"
                                   @checked($salvarNaRotina && in_array($dia, $diasSelecionados, true)) onchange="atualizarContagemDias()"
                                   class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                        </label>
                    @endforeach
                </div>
            </div>
            @endif

            <div>
                <label for="inputHorario" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Horário</label>
                <input type="time" name="horario" id="inputHorario" required
                       @if ($salvarNaRotina) step="60" value="{{ is_string($horarioSelecionado) ? substr($horarioSelecionado, 0, 5) : '' }}"
                       @elseif ($emergencial) value="{{ $agoraEmergencial->format('H:i') }}" @endif
                       class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                @if ($editandoTarefa)
                    <p class="mt-1 text-xs text-gray-500">O horário e os dias atualizados valem para as próximas execuções geradas.</p>
                @endif
            </div>

            <div>
                <label for="selectTarefa" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Tarefa</label>
                <select name="{{ $salvarNaRotina ? 'id_tarefa' : 'tarefa' }}" id="selectTarefa" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled @selected(!$salvarNaRotina || blank($tarefaSelecionada))>Selecione a tarefa</option>
                    @if ($editandoTarefa && !$tarefas->contains('id_tarefa', $tarefaEmEdicao->id_tarefa))
                        <option value="{{ $tarefaEmEdicao->id_tarefa }}" @selected($tarefaSelecionada == $tarefaEmEdicao->id_tarefa)>{{ $tarefaEmEdicao->tarefa ?? 'Tarefa atual' }}</option>
                    @endif
                    @foreach ($tarefas as $tarefa)
                        <option value="{{ $tarefa->id_tarefa }}" data-unidades="{{ implode(',', $tarefa->ids_empresas) }}" @selected($salvarNaRotina && $tarefaSelecionada == $tarefa->id_tarefa)>{{ $tarefa->tarefa }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="selectMorador" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Morador(a)</label>
                <select name="morador" id="selectMorador" @required(!$salvarNaRotina) onchange="aoMudarMorador(this.value)" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    @if ($salvarNaRotina)
                        <option value="{{ $moradorSelecionado === 'Nenhum (Tarefa Geral)' ? $moradorSelecionado : '' }}">Nenhum (Tarefa Geral)</option>
                        @if (is_string($moradorSelecionado) && filled($moradorSelecionado) && $moradorSelecionado !== 'Nenhum (Tarefa Geral)')
                            <option value="{{ $moradorSelecionado }}" selected>{{ $moradorSelecionado }}</option>
                        @endif
                    @else
                        <option value="" disabled selected>Selecione a unidade primeiro</option>
                    @endif
                </select>
            </div>

            <div id="cardMoradorSelecionado" class="hidden mt-4">
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Morador(a) Selecionado(a)</label>
                <div class="border border-gray-200 rounded-3xl p-4 bg-white shadow-sm flex items-center gap-4">
                    <div id="containerFotoMorador" class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden flex items-center justify-center shrink-0 border border-gray-100">
                    </div>
                    <div>
                        <h4 id="nomeMoradorCard" class="text-base font-bold text-[#1e2d3d]">--</h4>
                        <p class="sans-text text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                            <i class="fa-regular fa-user text-[10px]"></i> Morador(a)
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <label for="selectLocal" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Local</label>
                <select name="{{ $salvarNaRotina ? 'id_local' : 'local' }}" id="selectLocal" @required($localObrigatorio) class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" @disabled($localObrigatorio) @selected(!$salvarNaRotina || blank($localSelecionado))>
                        {{ $localObrigatorio ? 'Selecione o local' : ($tarefaEmEdicao->local_rotina ?: 'Selecione o local') }}
                    </option>
                    @if ($editandoTarefa && $tarefaEmEdicao->id_local !== null && !$locais->contains('id_local', $tarefaEmEdicao->id_local))
                        <option value="{{ $tarefaEmEdicao->id_local }}" @selected($localSelecionado == $tarefaEmEdicao->id_local)>{{ $tarefaEmEdicao->local_cadastrado ?: $tarefaEmEdicao->local_rotina ?: 'Local atual' }}</option>
                    @endif
                    @foreach ($locais as $local)
                        <option value="{{ $local->id_local }}" data-unidades="{{ implode(',', $local->ids_empresas) }}" @selected($salvarNaRotina && $localSelecionado == $local->id_local)>{{ $local->local }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Necessita auxílio?</label>
                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="{{ $salvarNaRotina ? 'auxilio' : 'necessita_auxilio' }}" value="{{ $salvarNaRotina ? '0' : 'Não' }}" @checked(!$salvarNaRotina || !$auxilioSelecionado) onchange="toggleCuidadorAuxiliar(false)" class="w-4 h-4 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                        <span class="text-sm text-gray-800">Não</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="{{ $salvarNaRotina ? 'auxilio' : 'necessita_auxilio' }}" value="{{ $salvarNaRotina ? '1' : 'Sim' }}" @checked($salvarNaRotina && $auxilioSelecionado) onchange="toggleCuidadorAuxiliar(true)" class="w-4 h-4 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                        <span class="text-sm text-gray-800">Sim</span>
                    </label>
                </div>
            </div>

            <div id="campoCuidadorAuxiliar" @class(['hidden' => !$salvarNaRotina || !$auxilioSelecionado])>
                <label for="selectCuidadorAux" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">{{ $salvarNaRotina ? 'Colaboradores auxiliares' : 'Cuidador(a) auxiliar' }}</label>
                <select name="{{ $salvarNaRotina ? 'auxiliares[]' : 'cuidador_auxiliar' }}" id="selectCuidadorAux" @if ($salvarNaRotina) multiple size="3" @endif class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    @if ($salvarNaRotina)
                        @forelse ($colaboradoresEdicao->where('id_usuario', '!=', $rotinaDaTarefa->id_usuario) as $auxiliar)
                            <option value="{{ $auxiliar->id_usuario }}" @selected(in_array((int) $auxiliar->id_usuario, $auxiliaresSelecionados, true))>{{ $auxiliar->usuario }}</option>
                        @empty
                            <option disabled>Nenhum outro colaborador vinculado à unidade.</option>
                        @endforelse
                    @else
                        <option value="" disabled selected>Selecione se necessário</option>
                    @endif
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Observação</label>
                <textarea name="observacao" id="inputObservacao" rows="3" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Orientações adicionais (opcional)">{{ is_string($observacaoSelecionada ?? null) ? $observacaoSelecionada : '' }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 sticky bottom-0 bg-white pb-1">
                @if ($salvarNaRotina)
                    <a href="{{ $voltarParaRotina }}" class="{{ $classeCancelar }}">Cancelar</a>
                @else
                    <button type="button" onclick="fecharModalTarefa()" class="{{ $classeCancelar }}">Cancelar</button>
                @endif
                <button type="{{ $salvarNaRotina ? 'submit' : 'button' }}" @unless ($salvarNaRotina) onclick="salvarTarefaDaRotina()" @endunless class="w-1/2 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-[#1e2d3d]/90 text-sm font-bold transition text-center">{{ $editandoTarefa ? 'Salvar tarefa' : 'Adicionar à rotina' }}</button>
            </div>
        </form>
    </div>
</div>

<script>
    @unless ($salvarNaRotina)
    let listaTarefasMemoria = [];

    @if ($emergencial)
    function atualizarDiaEmergencial() {
        const data = document.getElementById('inputDataEmergencial').value;
        document.getElementById('diaEmergencial').textContent = data
            ? new Date(data + 'T12:00:00Z').toLocaleDateString('pt-BR', { weekday: 'long', timeZone: 'UTC' })
            : '';
    }

    function preencherAgendamentoEmergencial() {
        const partes = new Intl.DateTimeFormat('pt-BR', {
            timeZone: 'America/Sao_Paulo',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(new Date());
        const agora = Object.fromEntries(partes.map(parte => [parte.type, parte.value]));
        const campoData = document.getElementById('inputDataEmergencial');
        campoData.value = campoData.min = `${agora.year}-${agora.month}-${agora.day}`;
        document.getElementById('inputHorario').value = `${agora.hour}:${agora.minute}`;
        atualizarDiaEmergencial();
    }
    @endif

    function fecharModalTarefa() {
        limparFormularioTarefa();
        const modal = document.getElementById('{{ $modalTarefa }}');
        if (modal) modal.classList.add('hidden');
    }

    function carregarCuidadoresAuxiliares(unidadeId, valorSelecionado = '') {
        const selectCuidador = document.getElementById('selectCuidadorAux');
        carregarUsuariosPorUnidade(selectCuidador, unidadeId, 'Selecione se necessário', valorSelecionado);
    }
    @endunless

    function toggleDiasDropdown() {
        const lista = document.getElementById('diasDropdownList');
        const icone = document.getElementById('diasIconeSetra');
        if (lista) lista.classList.toggle('hidden');
        if (icone) icone.classList.toggle('rotate-180');
    }

    function toggleTodosDias(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.dia-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = masterCheckbox.checked;
        });
        atualizarContagemDias();
    }

    function atualizarContagemDias() {
        const checkboxes = document.querySelectorAll('.dia-checkbox:checked');
        const spanTexto = document.getElementById('diasTextoBtn');
        const masterCheckbox = document.getElementById('selectAllDias');
        const totalDias = document.querySelectorAll('.dia-checkbox').length;

        if (masterCheckbox) {
            masterCheckbox.checked = (checkboxes.length === totalDias);
        }

        if (spanTexto) {
            if (checkboxes.length === 0) {
                spanTexto.textContent = 'Selecionar dias';
                spanTexto.className = 'text-gray-400';
            } else if (checkboxes.length === totalDias) {
                spanTexto.textContent = 'Todos os dias selecionados';
                spanTexto.className = 'text-gray-800 font-medium';
            } else {
                spanTexto.textContent = checkboxes.length + ' dia(s) selecionado(s)';
                spanTexto.className = 'text-gray-800 font-medium';
            }
        }
    }

    function toggleCuidadorAuxiliar(mostrar) {
        const campo = document.getElementById('campoCuidadorAuxiliar');
        if (campo) {
            if (mostrar) {
                campo.classList.remove('hidden');
            } else {
                campo.classList.add('hidden');
            }
        }
    }

    function escaparTexto(texto) {
        const elemento = document.createElement('span');
        elemento.textContent = texto;
        return elemento.innerHTML;
    }

    @unless ($salvarNaRotina)
    function salvarTarefaDaRotina() {
        const indexEdicao = document.getElementById('tarefaIndexEdicao').value;
        const campoData = document.getElementById('inputDataEmergencial');
        const data = campoData?.value;
        if (campoData && !campoData.reportValidity()) return;
        const diasSelecionados = Array.from(document.querySelectorAll('.dia-checkbox:checked')).map(cb => cb.value);
        const horario = document.getElementById('inputHorario').value;
        
        const selectTarefa = document.getElementById('selectTarefa');
        const tarefaId = selectTarefa ? selectTarefa.value : null;
        const tarefaNome = selectTarefa && selectTarefa.selectedIndex >= 0 ? selectTarefa.options[selectTarefa.selectedIndex].text : '';

        const selectLocal = document.getElementById('selectLocal');
        const localId = selectLocal ? selectLocal.value : null;
        const localNome = selectLocal && selectLocal.selectedIndex >= 0 ? selectLocal.options[selectLocal.selectedIndex].text : '';

        const morador = document.getElementById('selectMorador').value;
        const auxilioRadio = document.querySelector('input[name="necessita_auxilio"]:checked');
        const necessitaAuxilio = auxilioRadio ? auxilioRadio.value : 'Não';
        const selectCuidador = document.getElementById('selectCuidadorAux');
        const cuidadorAux = necessitaAuxilio === 'Sim' ? selectCuidador.value || null : null;
        const observacao = document.getElementById('inputObservacao').value;

        if (!tarefaId || !morador || !localId || !horario || (!data && diasSelecionados.length === 0)) {
            alert('Preencha os campos obrigatórios da tarefa.');
            return;
        }

        const dadosTarefa = {
            data: data,
            dias: data ? data.split('-').reverse().join('/') : diasSelecionados.join(', '),
            horario: horario,
            id_tarefa: tarefaId,
            tarefa: tarefaNome,
            morador: morador,
            id_local: localId,
            local: localNome,
            auxilio: necessitaAuxilio,
            cuidador: cuidadorAux,
            cuidador_nome: cuidadorAux ? selectCuidador.selectedOptions[0].text : '',
            observacao: observacao
        };

        if (indexEdicao === "") {
            listaTarefasMemoria.push(dadosTarefa);
        } else {
            listaTarefasMemoria[parseInt(indexEdicao)] = dadosTarefa;
        }

        renderizarTarefasNaTela();
        fecharModalTarefa();
    }

    function renderizarTarefasNaTela() {
        const container = document.getElementById('listaTarefasAdicionadas');
        if (!container) return;
        
        if (listaTarefasMemoria.length === 0) {
            container.innerHTML = `
                <div id="nenhumaTarefaMensagem">
                    <p class="text-sm font-medium text-gray-700">Nenhuma tarefa adicionada</p>
                    <p class="text-xs text-gray-400 mt-1">As tarefas serão exibidas aqui.</p>
                </div>
            `;
            return;
        }

        let html = '';
        listaTarefasMemoria.forEach((t, index) => {
            html += `
                <div class="border border-gray-200 rounded-3xl p-6 mb-4 bg-white shadow-sm text-left transition-all">
                    <div class="flex items-center justify-between cursor-pointer" onclick="toggleAcordeaoTarefa(${index})">
                        <div>
                            <h4 class="text-lg font-bold text-[#1e2d3d]">${escaparTexto(t.tarefa)}</h4>
                            <p class="text-xs text-gray-500 mt-0.5">${escaparTexto(t.dias)} · ${escaparTexto(t.morador)}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="bg-gray-100 text-gray-800 text-xs font-bold px-3 py-1.5 rounded-full">${escaparTexto(t.horario)}</span>
                            <i id="iconeAcordeao_${index}" class="fa-solid fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                        </div>
                    </div>

                    <div id="detalhesTarefa_${index}" class="hidden mt-4 pt-4 border-t border-gray-100 space-y-2 text-xs text-gray-600">
                        <div class="flex justify-between"><span class="font-bold text-gray-400">${t.data ? 'Data' : 'Dias'}</span> <span class="text-gray-800 font-medium">${escaparTexto(t.dias)}</span></div>
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Morador(a)</span> <span class="text-gray-800 font-medium">${escaparTexto(t.morador)}</span></div>
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Local</span> <span class="text-gray-800 font-medium">${escaparTexto(t.local)}</span></div>
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Auxílio</span> <span class="text-gray-800 font-medium">${escaparTexto(t.auxilio)} ${t.cuidador_nome ? '(' + escaparTexto(t.cuidador_nome) + ')' : ''}</span></div>
                        ${t.observacao ? '<div class="flex justify-between"><span class="font-bold text-gray-400">Observação</span> <span class="text-gray-800 font-medium">' + escaparTexto(t.observacao) + '</span></div>' : ''}

                        <div class="flex items-center justify-end gap-6 pt-4 mt-2 border-t border-gray-100">
                            <button type="button" onclick="editarTarefa(${index})" class="text-[#1e2d3d] hover:underline font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-pen text-xs"></i> Editar tarefa
                            </button>
                            <button type="button" onclick="removerTarefa(${index})" class="text-red-600 hover:underline font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-trash text-xs"></i> Remover tarefa
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function toggleAcordeaoTarefa(index) {
        const detalhes = document.getElementById(`detalhesTarefa_${index}`);
        const icone = document.getElementById(`iconeAcordeao_${index}`);
        if (detalhes) detalhes.classList.toggle('hidden');
        if (icone) icone.classList.toggle('rotate-180');
    }

    function filtrarCatalogoDaRotina(unidade) {
        for (const campo of ['selectTarefa', 'selectLocal']) {
            const select = document.getElementById(campo);
            for (const opcao of select.querySelectorAll('[data-unidades]')) {
                opcao.disabled = opcao.hidden = !opcao.dataset.unidades.split(',').includes(unidade);
            }
            if (select.selectedOptions[0]?.disabled) select.value = '';
        }
    }

    function editarTarefa(index) {
        filtrarCatalogoDaRotina(document.getElementById('selectUnidadeRotina').value);
        const unidadeSelect = document.getElementById('selectUnidadeRotina');
        const unidadeId = unidadeSelect ? unidadeSelect.value : '';
        const t = listaTarefasMemoria[index];
        
        document.getElementById('tarefaIndexEdicao').value = index;
        @if ($emergencial)
        document.getElementById('inputDataEmergencial').value = t.data;
        atualizarDiaEmergencial();
        @endif
        document.getElementById('inputHorario').value = t.horario;
        document.getElementById('selectTarefa').value = t.id_tarefa;
        document.getElementById('selectMorador').value = t.morador;
        aoMudarMorador(t.morador);
        document.getElementById('selectLocal').value = t.id_local;
        document.getElementById('inputObservacao').value = t.observacao || '';

        const radios = document.querySelectorAll('input[name="necessita_auxilio"]');
        radios.forEach(r => {
            if (r.value === t.auxilio) {
                r.checked = true;
            }
        });

        toggleCuidadorAuxiliar(t.auxilio === 'Sim');
        carregarCuidadoresAuxiliares(unidadeId, t.cuidador || '');

        const diasArray = t.dias.split(', ');
        document.querySelectorAll('.dia-checkbox').forEach(cb => {
            cb.checked = diasArray.includes(cb.value);
        });
        atualizarContagemDias();

        if (typeof abrirModal === 'function') {
            abrirModal('{{ $modalTarefa }}');
        }
    }

    function removerTarefa(index) {
        listaTarefasMemoria.splice(index, 1);
        renderizarTarefasNaTela();
    }

    function limparFormularioTarefa() {
        const form = document.getElementById('formSalvarRotina');
        if (form) form.reset();
        
        const indexEdicao = document.getElementById('tarefaIndexEdicao');
        if (indexEdicao) indexEdicao.value = '';

        document.querySelectorAll('.dia-checkbox').forEach(cb => cb.checked = false);
        
        const selectAll = document.getElementById('selectAllDias');
        if (selectAll) selectAll.checked = false;

        const diasTexto = document.getElementById('diasTextoBtn');
        if (diasTexto) {
            diasTexto.textContent = 'Selecionar dias';
            diasTexto.className = 'text-gray-400';
        }

        const campoCuidador = document.getElementById('campoCuidadorAuxiliar');
        if (campoCuidador) {
            campoCuidador.classList.add('hidden');
        }
        
        const radioNao = document.querySelector('input[name="necessita_auxilio"][value="Não"]');
        if (radioNao) {
            radioNao.checked = true;
        }

        esconderCardMorador();
    }
    @endunless

    function carregarMoradoresPorUnidade(unidadeNome) {
        const selectMorador = document.getElementById('selectMorador');
        if (!selectMorador) return;

        @unless ($salvarNaRotina)
        const tarefaGeral = 'Nenhum (Tarefa Geral)';
        selectMorador.replaceChildren(new Option(tarefaGeral, tarefaGeral, true, true));
        esconderCardMorador();
        @endunless

        fetch(`https://adm.gestaoseniorexpert.com.br/consulta_moradores.php/${encodeURIComponent(unidadeNome)}`)
            .then(response => response.json())
            .then(data => {
                @unless ($salvarNaRotina)
                const unidade = document.getElementById('selectUnidadeRotina');
                if (unidade.selectedOptions[0]?.dataset.nome !== unidadeNome) {
                    return;
                }
                @endunless
                const cadastrados = new Set([...selectMorador.options].map(opcao => opcao.text));
                for (const nomeMorador of Array.isArray(data) ? data : []) {
                    if (!cadastrados.has(nomeMorador)) {
                        selectMorador.add(new Option(nomeMorador, nomeMorador));
                        cadastrados.add(nomeMorador);
                    }
                }
            })
            .catch(() => {
                // A tarefa geral continua disponível durante falhas no cadastro externo.
            });
    }

    function aoMudarMorador(nomeMorador) {
        if (!nomeMorador || nomeMorador === "" || nomeMorador === "Nenhum (Tarefa Geral)") {
            esconderCardMorador();
            return;
        }

        document.getElementById('nomeMoradorCard').textContent = nomeMorador;
        document.getElementById('cardMoradorSelecionado').classList.remove('hidden');

        const containerFoto = document.getElementById('containerFotoMorador');
        containerFoto.innerHTML = '<i class="fa-solid fa-spinner animate-spin text-gray-400 text-sm"></i>';

        fetch(`https://adm.gestaoseniorexpert.com.br/consulta_foto_morador.php/${encodeURIComponent(nomeMorador)}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0 && data[0]) {
                    let fotoSrc = data[0];
                    if (!fotoSrc.startsWith('http') && !fotoSrc.startsWith('data:image')) {
                        fotoSrc = `data:image/jpeg;base64,${fotoSrc}`;
                    }
                    const foto = document.createElement('img');
                    foto.src = fotoSrc;
                    foto.alt = nomeMorador;
                    foto.className = 'w-full h-full object-cover';
                    containerFoto.replaceChildren(foto);
                } else {
                    const iniciais = nomeMorador.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
                    containerFoto.innerHTML = `<span class="text-sm font-bold text-[#1e2d3d]">${escaparTexto(iniciais)}</span>`;
                    containerFoto.className = "w-16 h-16 rounded-2xl bg-[#f4f1ea] overflow-hidden flex items-center justify-center shrink-0 border border-gray-100";
                }
            })
            .catch(() => {
                const iniciais = nomeMorador.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
                containerFoto.innerHTML = `<span class="text-sm font-bold text-[#1e2d3d]">${escaparTexto(iniciais)}</span>`;
            });
    }

    function esconderCardMorador() {
        const card = document.getElementById('cardMoradorSelecionado');
        if (card) card.classList.add('hidden');
    }

    @if ($salvarNaRotina)
    atualizarContagemDias();
    aoMudarMorador(document.getElementById('selectMorador').value);
    carregarMoradoresPorUnidade(@js($rotinaDaTarefa->empresa));
    @else
    function enviarRotinasParaBanco() {
        const unidade = document.getElementById('selectUnidadeRotina');
        const colaborador = document.getElementById('selectColaboradorModalRotina');
        if (!unidade.reportValidity() || !colaborador.reportValidity()) return;

        if (listaTarefasMemoria.length === 0) {
            alert('Adicione pelo menos uma tarefa na rotina antes de salvar.');
            return;
        }

        const form = document.getElementById('formSalvarRotina');
        form.elements.id_empresa.value = unidade.value;
        form.elements.colaborador_id.value = colaborador.value;
        if (form.elements.status_rotina) {
            form.elements.status_rotina.value = document.getElementById('situacaoRotina').value;
        }

        const inputJson = form.querySelector('#tarefasJsonInput');
        if (inputJson) {
            inputJson.value = JSON.stringify(listaTarefasMemoria);
        } else {
            alert("Erro: Campo de JSON não encontrado no formulário.");
            return;
        }

        form.submit();
    }
    @endif
</script>
