<div id="taskModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="closeModal('taskModal')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0">
            <h2 class="text-3xl font-bold text-[#1e2d3d] mb-1">Cadastro de tarefa</h2>
            <p class="sans-text text-gray-500 text-sm mb-6">Instruções e recursos utilizados</p>
        </div>

        <form action="{{ route('tarefas.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            @csrf
            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Nome da tarefa</label>
                <input type="text" name="nome_tarefa" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Ex.: Alimentação">
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Duração</label>
                <div class="relative flex items-center">
                    <input type="number" name="tempo_tarefa" required min="1" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 pr-14 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Ex: 15">
                    <span class="absolute right-4 text-gray-400 text-sm font-medium">min</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Situação</label>
                <select name="status_tarefa" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="Ativa">Ativa</option>
                    <option value="Inativa">Inativa</option>
                </select>
            </div>

            <div class="relative">
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Ferramentas</label>
                
                <button type="button" onclick="toggleFerramentasDropdown()" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 flex items-center justify-between focus:outline-none focus:border-[#1e2d3d]">
                    <span id="ferramentasTextoBtn" class="text-gray-400">Selecionar ferramentas</span>
                    <i id="ferramentasIconeSetra" class="fa-solid fa-chevron-up transition-transform duration-200 text-gray-400 text-xs"></i>
                </button>

                <div id="ferramentasDropdownList" class="absolute left-0 right-0 mt-2 bg-white border border-gray-300 rounded-2xl p-4 max-h-52 overflow-y-auto space-y-3 shadow-xl z-50 hidden">
                    @php
                        $ferramentasList = DB::table('gr_ferramentas')->orderBy('nome_ferramenta')->get();
                    @endphp

                    @if($ferramentasList->count() > 0)
                        @foreach($ferramentasList as $ferramenta)
                            <label class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-xl transition">
                                <span class="text-sm text-gray-800">{{ $ferramenta->nome_ferramenta }}</span>
                                <input type="checkbox" name="ferramentas_tarefa[]" value="{{ $ferramenta->nome_ferramenta }}" onchange="atualizarContagemFerramentas()" class="w-4 h-4 rounded border-gray-300 text-[#1e2d3d] focus:ring-[#1e2d3d]">
                            </label>
                        @endforeach
                    @else
                        <div class="text-center py-4 text-gray-400 text-xs">
                            Nenhuma ferramenta cadastrada.
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Descrição e instruções</label>
                <textarea name="descricao_tarefa" rows="3" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Orientações para execução..."></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Manual ou arquivo de apoio</label>
                <input type="file" name="manual_tarefa" class="w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-4 file:rounded-2xl file:border-0 file:text-sm file:font-semibold file:bg-[#1e2d3d] file:text-white hover:file:bg-opacity-90">
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 sticky bottom-0 bg-white pb-1">
                <button type="button" onclick="closeModal('taskModal')" class="w-1/2 py-3.5 rounded-2xl border border-[#e5dec9] text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center">Cancelar</button>
                <button type="submit" class="w-1/2 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleFerramentasDropdown() {
        const lista = document.getElementById('ferramentasDropdownList');
        const icone = document.getElementById('ferramentasIconeSetra');
        lista.classList.toggle('hidden');
        icone.classList.toggle('rotate-180');
    }

    function atualizarContagemFerramentas() {
        const checkboxes = document.querySelectorAll('input[name="ferramentas_tarefa[]"]:checked');
        const spanTexto = document.getElementById('ferramentasTextoBtn');
        
        if (checkboxes.length === 0) {
            spanTexto.textContent = 'Selecionar ferramentas';
            spanTexto.className = 'text-gray-400';
        } else {
            spanTexto.textContent = checkboxes.length + ' ferramenta(s) selecionada(s)';
            spanTexto.className = 'text-gray-800 font-medium';
        }
    }

    document.addEventListener('click', function(event) {
        const container = document.getElementById('ferramentasDropdownList');
        const botao = container.previousElementSibling;
        if (!container.contains(event.target) && !botao.contains(event.target)) {
            container.classList.add('hidden');
            document.getElementById('ferramentasIconeSetra').classList.remove('rotate-180');
        }
    });
</script>