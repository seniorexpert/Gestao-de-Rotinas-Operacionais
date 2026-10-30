<div id="rotinaModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="closeModal('rotinaModal')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0">
            <h2 class="text-3xl font-bold text-[#1e2d3d] mb-1">Cadastro de Rotinas</h2>
            <p class="sans-text text-gray-500 text-sm mb-6">Configure a rotina e adicione suas tarefas</p>
        </div>

        <form action="#" method="POST" class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            @csrf
            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Unidade</label>
                <select name="unidade_id" id="selectUnidadeRotina" required onchange="carregarColaboradoresPorUnidade(this.value)" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione a unidade</option>
                    <option value="Garden Ville">Garden Ville</option>
                    <option value="Menino Deus">Menino Deus</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Colaborador(a)</label>
                <select name="colaborador_id" id="selectColaboradorRotina" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione a unidade primeiro</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Situação da rotina</label>
                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="status_rotina" value="Ativa" checked class="w-4 h-4 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                        <span class="text-sm text-gray-800">Ativa</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="status_rotina" value="Inativa" class="w-4 h-4 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                        <span class="text-sm text-gray-800">Inativa</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Tarefas da rotina</label>
                <p class="text-xs text-gray-500 mb-3">Inclua uma ou mais tarefas nesta rotina.</p>
                
                <div id="listaTarefasAdicionadas" class="border border-gray-200 rounded-2xl p-6 text-center bg-gray-50 mb-3">
                    <div id="nenhumaTarefaMensagem">
                        <p class="text-sm font-medium text-gray-700">Nenhuma tarefa adicionada</p>
                        <p class="text-xs text-gray-400 mt-1">As tarefas serão exibidas aqui.</p>
                    </div>
                </div>

                <button type="button" onclick="abrirModalTarefaRotina()" class="w-full py-3.5 rounded-2xl border border-gray-300 text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i> Adicionar nova tarefa
                </button>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 sticky bottom-0 bg-white pb-1">
                <button type="button" onclick="closeModal('rotinaModal')" class="w-1/2 py-3.5 rounded-2xl border border-[#e5dec9] text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center">Cancelar</button>
                <button type="submit" class="w-1/2 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">Salvar rotina</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }

    function carregarColaboradoresPorUnidade(unidadeNome) {
        const selectColab = document.getElementById('selectColaboradorRotina');
        if (selectColab) {
            selectColab.innerHTML = '<option value="" disabled selected>A carregar...</option>';
            fetch(`/api/usuarios-por-unidade?unit=${encodeURIComponent(unidadeNome)}`)
                .then(response => response.json())
                .then(data => {
                    selectColab.innerHTML = '<option value="" disabled selected>Selecione a pessoa</option>';
                    data.forEach(user => {
                        selectColab.innerHTML += `<option value="${user.id}">${user.name}</option>`;
                    });
                });
        }

        if (typeof carregarMoradoresPorUnidade === 'function') {
            carregarMoradoresPorUnidade(unidadeNome);
        }
    }

    function abrirModalTarefaRotina() {
        const unidadeSelect = document.getElementById('selectUnidadeRotina');
        if (!unidadeSelect || !unidadeSelect.value) {
            alert('Por favor, selecione uma unidade primeiro na rotina.');
            return;
        }
        
        if (typeof carregarCuidadoresAuxiliares === 'function') {
            carregarCuidadoresAuxiliares(unidadeSelect.value);
        }
        
        abrirModal('tarefaRotinaModal');
    }
</script>