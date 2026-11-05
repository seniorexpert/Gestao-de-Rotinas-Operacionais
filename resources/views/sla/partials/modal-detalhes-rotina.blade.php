<div id="modalDetalhesRotina" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        
        <button type="button" onclick="fecharModalDetalhes()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            ✕
        </button>

        <div class="flex-shrink-0 mb-6">
            <span id="modalBadgeStatus" class="inline-block px-3 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full mb-3">Ativa</span>
            <h2 class="text-3xl font-bold text-[#1e2d3d] font-serif">Detalhes da tarefa</h2>
        </div>

        <div class="space-y-6 sans-text overflow-y-auto flex-1 pr-1">
            <div class="border border-gray-200 rounded-3xl p-6 bg-white shadow-sm space-y-6">
                <div class="flex items-center gap-4">
                    <div id="modalIconeDiv" class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center text-3xl">
                        📋
                    </div>
                    <h3 id="modalTituloTarefa" class="text-2xl font-bold text-[#1e2d3d]">Nome da Tarefa</h3>
                </div>

                <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Quando</span>
                        <span id="modalHorario" class="text-sm font-bold text-[#1e2d3d]">--:--</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Onde</span>
                        <span id="modalLocal" class="text-sm font-bold text-[#1e2d3d]">--</span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <span class="text-xs text-gray-400 block mb-1">Morador</span>
                    <span id="modalMorador" class="text-sm font-bold text-[#1e2d3d]">--</span>
                </div>
            </div>

            <div class="border border-gray-200 rounded-3xl p-6 bg-white shadow-sm space-y-3">
                <h4 class="text-sm font-bold text-[#1e2d3d]">Instrução de trabalho / Observação</h4>
                <p id="modalObs" class="text-sm text-gray-600">Nenhuma observação informada.</p>
            </div>
        </div>

        <div id="modalFooterAcoes" class="pt-6 border-t border-gray-100 mt-4">
            <button type="button" onclick="iniciarTarefa()" class="w-full py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">
                Iniciar tarefa
            </button>
        </div>

    </div>
</div>

<script>
    function abrirModalDetalhes(data) {
        document.getElementById('modalIconeDiv').textContent = data.icone;
        document.getElementById('modalTituloTarefa').textContent = data.tarefa;
        document.getElementById('modalHorario').textContent = data.horario;
        document.getElementById('modalLocal').textContent = data.local;
        document.getElementById('modalMorador').textContent = data.morador;
        document.getElementById('modalObs').textContent = data.obs ? data.obs : 'Respeitar orientações individuais cadastradas para esta rotina.';

        const badge = document.getElementById('modalBadgeStatus');
        badge.textContent = 'Ativa';
        badge.className = 'inline-block px-3 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full mb-3';

        document.getElementById('modalFooterAcoes').innerHTML = `
            <button type="button" onclick="iniciarTarefa()" class="w-full py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">
                Iniciar tarefa
            </button>
        `;

        const modal = document.getElementById('modalDetalhesRotina');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function iniciarTarefa() {
        const badge = document.getElementById('modalBadgeStatus');
        badge.textContent = 'Realizando';
        badge.className = 'inline-block px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full mb-3';

        const footer = document.getElementById('modalFooterAcoes');
        footer.innerHTML = `
            <div class="space-y-3">
                <button type="button" onclick="finalizarTarefa()" class="w-full py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center">
                    Finalizar tarefa
                </button>
                <button type="button" onclick="naoPodeSerRealizada()" class="w-full py-3.5 rounded-2xl bg-white border border-red-300 text-red-800 hover:bg-red-50 text-sm font-bold transition text-center">
                    Não pode ser realizada
                </button>
                <button type="button" onclick="solicitarAuxilio()" class="w-full py-3.5 rounded-2xl bg-white border border-gray-300 text-[#1e2d3d] hover:bg-gray-50 text-sm font-bold transition text-center">
                    Solicitar auxílio
                </button>
            </div>
        `;
    }

    function finalizarTarefa() {
        alert('Tarefa finalizada com sucesso!');
        fecharModalDetalhes();
    }

    function naoPodeSerRealizada() {
        alert('Ação registrada: Não pode ser realizada.');
        fecharModalDetalhes();
    }

    function solicitarAuxilio() {
        alert('Auxílio solicitado!');
    }

    function fecharModalDetalhes() {
        const modal = document.getElementById('modalDetalhesRotina');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
</script>