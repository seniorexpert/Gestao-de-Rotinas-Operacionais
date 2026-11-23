<div id="taskModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="closeModal('taskModal')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0">
            <h2 id="modalTituloTarefa" class="text-3xl font-bold text-[#1e2d3d] mb-1">Cadastro de tarefa</h2>
            <p class="sans-text text-gray-500 text-sm mb-6">Instruções e recursos utilizados</p>
        </div>

        <form action="{{ route('tarefas.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            @csrf

            <input type="hidden" name="id" id="tarefa_id">

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Nome da tarefa</label>
                <input type="text" name="nome_tarefa" id="nome_tarefa" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Ex.: Alimentação">
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Duração</label>
                <div class="relative flex items-center">
                    <input type="number" name="tempo_tarefa" id="tempo_tarefa" required min="0.01" max="1439.99" step="any" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 pr-14 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="15">
                    <span class="absolute right-4 text-gray-400 text-sm font-medium">min</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Situação</label>
                <select name="status_tarefa" id="status_tarefa" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="Ativa">Ativa</option>
                    <option value="Inativa">Inativa</option>
                </select>
            </div>

            <div>
                <label for="id_ferramenta" class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Ferramenta</label>
                <select name="id_ferramenta" id="id_ferramenta" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]">
                    <option value="">Nenhuma ferramenta</option>
                    @foreach ($ferramentas as $ferramenta)
                        <option value="{{ $ferramenta->id_ferramenta }}">{{ $ferramenta->ferramenta }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-seletor-icone name="icone" value="📋" />
            </div>

            @php
                $coresIcone = [
                    'original' => ['label' => 'Cores originais', 'hex' => null],
                    '#1e2d3d'   => ['label' => 'Azul escuro', 'hex' => '#1e2d3d'],
                    '#2563eb'   => ['label' => 'Azul', 'hex' => '#2563eb'],
                    '#16a34a'   => ['label' => 'Verde', 'hex' => '#16a34a'],
                    '#dc2626'   => ['label' => 'Vermelho', 'hex' => '#dc2626'],
                    '#ea580c'   => ['label' => 'Laranja', 'hex' => '#ea580c'],
                    '#9333ea'   => ['label' => 'Roxo', 'hex' => '#9333ea'],
                    '#4b5563'   => ['label' => 'Cinza', 'hex' => '#4b5563'],
                ];
            @endphp
            <div x-data="{ cor: 'original' }">
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Cor do ícone</label>
                <input type="hidden" name="cor_icone" id="cor_icone" :value="cor">
                <div class="flex flex-wrap items-center gap-2.5">
                    @foreach ($coresIcone as $valorCor => $infoCor)
                        <button
                            type="button"
                            @click="cor = '{{ $valorCor }}'"
                            title="{{ $infoCor['label'] }}"
                            :class="cor === '{{ $valorCor }}' ? 'ring-2 ring-[#1e2d3d] ring-offset-2 border-[#1e2d3d]' : 'border-gray-300'"
                            class="h-8 w-8 rounded-full border flex items-center justify-center transition cursor-pointer"
                            style="{{ $infoCor['hex'] ? 'background-color: ' . $infoCor['hex'] . ';' : 'background-color: #f9fafb;' }}"
                        >
                            @if (!$infoCor['hex'])
                                <span class="text-[10px] font-bold text-gray-600">Org</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Descrição e instruções</label>
                <textarea name="descricao_tarefa" id="descricao_tarefa" rows="3" class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Orientações para execução..."></textarea>
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