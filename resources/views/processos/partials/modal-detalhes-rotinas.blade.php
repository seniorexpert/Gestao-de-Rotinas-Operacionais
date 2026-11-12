<div id="modalDetalhesRotina" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-4xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="closeModal('modalDetalhesRotina')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 class="text-3xl font-bold text-[#1e2d3d] mb-1">Detalhes da Rotina</h2>
            <p class="sans-text text-gray-500 text-sm">Veja as tarefas da rotina e ajuste o que precisar</p>
        </div>

        <div class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">

            <div>
                <label class="block text-xs font-bold text-[#1e2d3d] uppercase tracking-wider mb-1">Tarefas da rotina</label>
                <p class="text-xs text-gray-400 mb-4">Use Editar ou Remover em cada tarefa.</p>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-5 border border-gray-200 rounded-2xl bg-white shadow-sm hover:border-gray-300 transition">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-[#f4f1ea] flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-stethoscope text-[#1e2d3d]"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-[#1e2d3d] text-sm mb-1">VERIFICAÇÃO DE PRESSÃO ARTERIAL E FREQUÊNCIA CARDÍACA COM APARELHO DIGITAL</h3>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 font-medium">
                                    <span><i class="fa-regular fa-clock mr-1"></i> Horário: 07:00</span>
                                    <span><i class="fa-regular fa-calendar mr-1"></i> Dias: Todos os dias</span>
                                    <span><i class="fa-regular fa-user mr-1"></i> Morador(a): MARIA OLIVEIRA</span>
                                    <span><i class="fa-solid fa-location-dot mr-1"></i> Local: Quarto 02</span>
                                    <span><i class="fa-solid fa-link mr-1"></i> Auxílio: Não</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-4">
                            <button type="button" class="text-xs font-bold text-[#1e2d3d] hover:underline flex items-center gap-1">
                                <i class="fa-solid fa-pen"></i> Editar
                            </button>
                            <button type="button" class="text-xs font-bold text-red-600 hover:underline flex items-center gap-1">
                                <i class="fa-regular fa-trash-can"></i> Remover
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-5 border border-gray-200 rounded-2xl bg-white shadow-sm hover:border-gray-300 transition">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-[#f4f1ea] flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-pills text-[#1e2d3d]"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-[#1e2d3d] text-sm mb-1">ADMINISTRAÇÃO DE MEDICAMENTOS VIA ORAL</h3>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 font-medium">
                                    <span><i class="fa-regular fa-clock mr-1"></i> Horário: 08:00</span>
                                    <span><i class="fa-regular fa-calendar mr-1"></i> Dias: Todos os dias</span>
                                    <span><i class="fa-regular fa-user mr-1"></i> Morador(a): MARIA OLIVEIRA</span>
                                    <span><i class="fa-solid fa-location-dot mr-1"></i> Local: Quarto 02</span>
                                    <span><i class="fa-solid fa-link mr-1"></i> Auxílio: Não</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-4">
                            <button type="button" class="text-xs font-bold text-[#1e2d3d] hover:underline flex items-center gap-1">
                                <i class="fa-solid fa-pen"></i> Editar
                            </button>
                            <button type="button" class="text-xs font-bold text-red-600 hover:underline flex items-center gap-1">
                                <i class="fa-regular fa-trash-can"></i> Remover
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-5 border border-gray-200 rounded-2xl bg-white shadow-sm hover:border-gray-300 transition">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-[#f4f1ea] flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-pills text-[#1e2d3d]"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-[#1e2d3d] text-sm mb-1">ADMINISTRAÇÃO DE MEDICAMENTOS VIA ORAL</h3>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 font-medium">
                                    <span><i class="fa-regular fa-clock mr-1"></i> Horário: 14:00</span>
                                    <span><i class="fa-regular fa-calendar mr-1"></i> Dias: Todos os dias</span>
                                    <span><i class="fa-regular fa-user mr-1"></i> Morador(a): JOSÉ SANTOS</span>
                                    <span><i class="fa-solid fa-location-dot mr-1"></i> Local: Quarto 01</span>
                                    <span><i class="fa-solid fa-link mr-1"></i> Auxílio: Não</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-4">
                            <button type="button" class="text-xs font-bold text-[#1e2d3d] hover:underline flex items-center gap-1">
                                <i class="fa-solid fa-pen"></i> Editar
                            </button>
                            <button type="button" class="text-xs font-bold text-red-600 hover:underline flex items-center gap-1">
                                <i class="fa-regular fa-trash-can"></i> Remover
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200 sticky bottom-0 bg-white pb-1">
                <button type="button" class="px-6 py-3.5 rounded-2xl border border-red-200 text-red-600 hover:bg-red-50 text-sm font-bold transition flex items-center gap-2">
                    <i class="fa-regular fa-trash-can"></i> Excluir rotina
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeModal('modalDetalhesRotina')" class="px-6 py-3.5 rounded-2xl border border-gray-300 text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition">Cancelar</button>
                    <button type="button" onclick="closeModal('modalDetalhesRotina')" class="px-8 py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition">Salvar rotina</button>
                </div>
            </div>
        </div>
    </div>
</div>