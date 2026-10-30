<div id="ferramentaModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 relative shadow-2xl my-8">
        <button onclick="closeModal('ferramentaModal')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <h2 class="text-3xl font-bold text-[#1e2d3d] mb-1">Nova ferramenta</h2>
        <p class="sans-text text-gray-500 text-sm mb-6">Informe os dados da ferramenta de apoio.</p>

        <form action="{{ route('ferramentas.store') }}" method="POST" class="space-y-6 sans-text">
            @csrf
            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-2">Nome da ferramenta</label>
                <input type="text" name="nome_ferramenta" required class="w-full bg-white border border-gray-300 rounded-2xl px-4 py-3.5 text-sm text-gray-800 focus:outline-none focus:border-[#1e2d3d]" placeholder="Informe nome da ferramenta">
            </div>

            <div class="flex items-center justify-end gap-4 pt-4">
                <button type="button" onclick="closeModal('ferramentaModal')" class="w-1/2 py-3.5 rounded-2xl border border-[#e5dec9] text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center">Cancelar</button>
                <button type="submit" class="w-1/2 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">Salvar</button>
            </div>
        </form>
    </div>
</div>