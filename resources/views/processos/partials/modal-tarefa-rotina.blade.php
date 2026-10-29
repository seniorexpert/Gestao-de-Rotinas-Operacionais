<div id="tarefaRotinaModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="fecharModalTarefa()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0">
            <h2 id="modalTarefaTitulo" class="text-3xl font-bold text-[#1e2d3d] mb-1">Dados da tarefa</h2>
            <p class="sans-text text-gray-500 text-sm mb-6">Adicione esta tarefa à rotina</p>
        </div>

        <form id="formDadosTarefa" class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            <input type="hidden" id="tarefaIndexEdicao" value="">

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

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Domingo</span>
                        <input type="checkbox" name="dias_semana[]" value="Domingo" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Segunda-feira</span>
                        <input type="checkbox" name="dias_semana[]" value="Segunda-feira" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Terça-feira</span>
                        <input type="checkbox" name="dias_semana[]" value="Terça-feira" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Quarta-feira</span>
                        <input type="checkbox" name="dias_semana[]" value="Quarta-feira" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Quinta-feira</span>
                        <input type="checkbox" name="dias_semana[]" value="Quinta-feira" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Sexta-feira</span>
                        <input type="checkbox" name="dias_semana[]" value="Sexta-feira" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                        <span class="text-sm text-gray-800">Sábado</span>
                        <input type="checkbox" name="dias_semana[]" value="Sábado" onchange="atualizarContagemDias()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d] dia-checkbox">
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Horário</label>
                <input type="time" id="inputHorario" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Tarefa</label>
                <select id="selectTarefa" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione a tarefa</option>
                    <option value="Alimentação">Alimentação</option>
                    <option value="Banho e Higiene">Banho e Higiene</option>
                    <option value="Medicação">Medicação</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Morador(a)</label>
                <select id="selectMorador" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione a unidade primeiro</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Local</label>
                <select id="selectLocal" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione o local</option>
                    @foreach(DB::table('gr_locais')->orderBy('nome_local')->get() as $local)
                        <option value="{{ $local->nome_local }}">{{ $local->nome_local }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Necessita auxílio?</label>
                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="necessita_auxilio" value="Não" checked onchange="toggleCuidadorAuxiliar(false)" class="w-4 h-4 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                        <span class="text-sm text-gray-800">Não</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="necessita_auxilio" value="Sim" onchange="toggleCuidadorAuxiliar(true)" class="w-4 h-4 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                        <span class="text-sm text-gray-800">Sim</span>
                    </label>
                </div>
            </div>

            <div id="campoCuidadorAuxiliar" class="hidden">
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Cuidador(a) auxiliar</label>
                <select id="selectCuidadorAux" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione se necessário</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Observação</label>
                <textarea id="inputObservacao" rows="3" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Orientações adicionais (opcional)"></textarea>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 sticky bottom-0 bg-white pb-1">
                <button type="button" onclick="fecharModalTarefa()" class="w-1/2 py-3.5 rounded-2xl border border-[#e5dec9] text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center">Cancelar</button>
                <button type="button" onclick="salvarTarefaDaRotina()" class="w-1/2 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">Adicionar à rotina</button>
            </div>
        </form>
    </div>
</div>

<script>
    let listaTarefasMemoria = [];

    function fecharModalTarefa() {
        limparFormularioTarefa();
        closeModal('tarefaRotinaModal');
    }

    function carregarCuidadoresAuxiliares(unidadeNome, valorSelecionado = '') {
        const selectCuidador = document.getElementById('selectCuidadorAux');
        if (!selectCuidador) return;

        selectCuidador.innerHTML = '<option value="" disabled selected>A carregar...</option>';

        fetch(`/api/usuarios-por-unidade?unit=${encodeURIComponent(unidadeNome)}`)
            .then(response => response.json())
            .then(data => {
                selectCuidador.innerHTML = '<option value="" disabled selected>Selecione se necessário</option>';
                data.forEach(user => {
                    const selected = user.name === valorSelecionado ? 'selected' : '';
                    selectCuidador.innerHTML += `<option value="${user.name}" ${selected}>${user.name}</option>`;
                });
            })
            .catch(() => {
                selectCuidador.innerHTML = '<option value="" disabled selected>Erro ao carregar cuidadores</option>';
            });
    }

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

    function salvarTarefaDaRotina() {
        const indexEdicao = document.getElementById('tarefaIndexEdicao').value;
        const diasSelecionados = Array.from(document.querySelectorAll('.dia-checkbox:checked')).map(cb => cb.value);
        const horario = document.getElementById('inputHorario').value;
        const tarefa = document.getElementById('selectTarefa').value;
        const morador = document.getElementById('selectMorador').value;
        const local = document.getElementById('selectLocal').value;
        const auxilioRadio = document.querySelector('input[name="necessita_auxilio"]:checked');
        const necessitaAuxilio = auxilioRadio ? auxilioRadio.value : 'Não';
        const selectCuidador = document.getElementById('selectCuidadorAux');
        const cuidadorAux = selectCuidador ? selectCuidador.value : '';
        const observacao = document.getElementById('inputObservacao').value;

        if (!tarefa || !morador || !local || !horario || diasSelecionados.length === 0) {
            alert('Por favor, preencha os campos obrigatórios e selecione ao menos um dia.');
            return;
        }

        const dadosTarefa = {
            dias: diasSelecionados.join(', '),
            horario: horario,
            tarefa: tarefa,
            morador: morador,
            local: local,
            auxilio: necessitaAuxilio,
            cuidador: cuidadorAux,
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
                            <h4 class="text-lg font-bold text-[#1e2d3d]">${t.tarefa}</h4>
                            <p class="text-xs text-gray-500 mt-0.5">${t.dias} · ${t.morador}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="bg-gray-100 text-gray-800 text-xs font-bold px-3 py-1.5 rounded-full">${t.horario}</span>
                            <i id="iconeAcordeao_${index}" class="fa-solid fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                        </div>
                    </div>

                    <div id="detalhesTarefa_${index}" class="hidden mt-4 pt-4 border-t border-gray-100 space-y-2 text-xs text-gray-600">
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Dias</span> <span class="text-gray-800 font-medium">${t.dias}</span></div>
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Morador(a)</span> <span class="text-gray-800 font-medium">${t.morador}</span></div>
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Local</span> <span class="text-gray-800 font-medium">${t.local}</span></div>
                        <div class="flex justify-between"><span class="font-bold text-gray-400">Auxílio</span> <span class="text-gray-800 font-medium">${t.auxilio} ${t.auxilio === 'Sim' && t.cuidador ? '('+t.cuidador+')' : ''}</span></div>
                        ${t.observacao ? '<div class="flex justify-between"><span class="font-bold text-gray-400">Observação</span> <span class="text-gray-800 font-medium">'+t.observacao+'</span></div>' : ''}

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

    function editarTarefa(index) {
        const unidadeSelect = document.getElementById('selectUnidadeRotina');
        const unidadeNome = unidadeSelect ? unidadeSelect.value : '';
        const t = listaTarefasMemoria[index];
        
        document.getElementById('tarefaIndexEdicao').value = index;
        document.getElementById('inputHorario').value = t.horario;
        document.getElementById('selectTarefa').value = t.tarefa;
        document.getElementById('selectMorador').value = t.morador;
        document.getElementById('selectLocal').value = t.local;
        document.getElementById('inputObservacao').value = t.observacao || '';

        const radios = document.querySelectorAll('input[name="necessita_auxilio"]');
        radios.forEach(r => {
            if (r.value === t.auxilio) {
                r.checked = true;
            }
        });

        toggleCuidadorAuxiliar(t.auxilio === 'Sim');
        carregarCuidadoresAuxiliares(unidadeNome, t.cuidador || '');

        const diasArray = t.dias.split(', ');
        document.querySelectorAll('.dia-checkbox').forEach(cb => {
            cb.checked = diasArray.includes(cb.value);
        });
        atualizarContagemDias();

        abrirModal('tarefaRotinaModal');
    }

    function removerTarefa(index) {
        listaTarefasMemoria.splice(index, 1);
        renderizarTarefasNaTela();
    }

    function limparFormularioTarefa() {
        const form = document.getElementById('formDadosTarefa');
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
    }

    function carregarMoradoresPorUnidade(unidadeNome) {
        const selectMorador = document.getElementById('selectMorador');
        if (!selectMorador) return;

        selectMorador.innerHTML = '<option value="" disabled selected>A carregar moradores...</option>';

        fetch(`https://adm.gestaoseniorexpert.com.br/consulta_moradores.php/${encodeURIComponent(unidadeNome)}`)
            .then(response => response.json())
            .then(data => {
                selectMorador.innerHTML = '<option value="" disabled selected>Selecione a pessoa</option>';
                
                if (data.length === 0) {
                    selectMorador.innerHTML += '<option value="" disabled>Nenhum morador ativo nesta unidade</option>';
                    return;
                }

                data.forEach(nomeMorador => {
                    selectMorador.innerHTML += `<option value="${nomeMorador}">${nomeMorador}</option>`;
                });
            })
            .catch(() => {
                selectMorador.innerHTML = '<option value="" disabled selected>Erro ao carregar moradores</option>';
            });
    }
</script>