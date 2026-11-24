@php
    $emergencial = $emergencial ?? false;
    $modalRotina = $emergencial ? 'modalRotinaEmergencial' : 'rotinaModal';
    $modalTarefa = $emergencial ? 'tarefaRotinaEmergencialModal' : 'tarefaRotinaModal';
@endphp

<div id="{{ $modalRotina }}" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="closeModal('{{ $modalRotina }}')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0">
            <h2 class="text-3xl font-bold text-[#1e2d3d] mb-1">{{ $emergencial ? 'Cadastro de Rotina Emergencial' : 'Cadastro de Rotinas' }}</h2>
            <p class="sans-text text-gray-500 text-sm mb-6">{{ $emergencial ? 'Escolha a unidade e o colaborador para agendar as tarefas.' : 'Configure a rotina e adicione suas tarefas' }}</p>
        </div>

        <div class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            <div>
                <label for="selectUnidadeRotina" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Unidade</label>
                <select name="id_empresa" id="selectUnidadeRotina" data-url-usuarios="{{ route('processos.usuarios-por-unidade') }}" required onchange="carregarColaboradoresPorUnidade(this)" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione a unidade</option>
                    @foreach ($empresas as $empresa)
                        <option value="{{ $empresa->id_empresa }}" data-nome="{{ $empresa->empresa }}">{{ $empresa->empresa }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="selectColaboradorModalRotina" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Colaborador(a)</label>
                <select name="colaborador_id" id="selectColaboradorModalRotina" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="" disabled selected>Selecione a unidade primeiro</option>
                </select>
            </div>
            
            @unless ($emergencial)
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
            @endunless

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
                <button type="button" onclick="closeModal('{{ $modalRotina }}')" class="w-1/2 py-3.5 rounded-2xl border border-[#e5dec9] text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center">Cancelar</button>
                <button type="button" onclick="enviarRotinasParaBanco()" class="w-1/2 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">Salvar rotina</button>
            </div>
        </div>
    </div>
</div>

<script>
    const consultasDeUsuarios = new WeakMap();

    function abrirModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }

    async function carregarUsuariosPorUnidade(select, unidadeId, rotulo, selecionado = '') {
        const consulta = Symbol();
        consultasDeUsuarios.set(select, consulta);
        select.replaceChildren(new Option('Carregando...', '', true, true));

        try {
            const url = new URL(document.getElementById('selectUnidadeRotina').dataset.urlUsuarios);
            url.searchParams.set('id_empresa', unidadeId);
            const resposta = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!resposta.ok) {
                throw new Error('Falha ao consultar colaboradores.');
            }

            const usuarios = await resposta.json();
            if (consultasDeUsuarios.get(select) !== consulta) {
                return;
            }

            select.replaceChildren(new Option(rotulo, '', true, true));
            for (const usuario of usuarios) {
                const id = String(usuario.id_usuario);
                select.add(new Option(usuario.usuario, id, false, id === String(selecionado)));
            }
        } catch {
            if (consultasDeUsuarios.get(select) === consulta) {
                select.replaceChildren(new Option('Erro ao carregar colaboradores', '', true, true));
            }
        }
    }

    function carregarColaboradoresPorUnidade(unidade) {
        const select = document.getElementById('selectColaboradorModalRotina');
        carregarUsuariosPorUnidade(select, unidade.value, 'Selecione a pessoa');
    }

    function abrirModalTarefaRotina() {
        const unidadeSelect = document.getElementById('selectUnidadeRotina');
        if (!unidadeSelect?.reportValidity()) {
            return;
        }
        @if ($emergencial)
        if (!document.getElementById('selectColaboradorModalRotina').reportValidity()) {
            return;
        }
        preencherAgendamentoEmergencial();
        @endif
        
        if (typeof carregarCuidadoresAuxiliares === 'function') {
            carregarCuidadoresAuxiliares(unidadeSelect.value);
        }
        
        if (typeof carregarMoradoresPorUnidade === 'function') {
            carregarMoradoresPorUnidade(unidadeSelect.selectedOptions[0].dataset.nome);
        }
        
        abrirModal('{{ $modalTarefa }}');
    }
</script>
