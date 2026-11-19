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

                <div id="modalSlaExecucao" class="hidden border-t border-gray-100 pt-4">
                    <span class="block text-xs text-gray-500">SLA da etapa</span>
                    <strong id="modalSituacaoSla" class="text-sm text-slate-800"></strong>
                    <span id="modalPrazoExecucao" class="mt-1 block text-xs text-gray-600"></span>
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

        <form id="modalFooterAcoes" method="POST" class="hidden pt-6 border-t border-gray-100 mt-4">
            @csrf
            <button id="modalExecutarTarefa" type="submit" disabled class="w-full py-3.5 rounded-2xl bg-[#1e2d3d] text-white hover:bg-opacity-90 text-sm font-bold transition text-center"></button>
        </form>

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
        badge.textContent = data.estado;
        badge.className = 'inline-block px-3 py-1 text-xs font-medium rounded-full mb-3 '
            + (data.ativa ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600');

        document.getElementById('modalSlaExecucao').classList.toggle('hidden', !data.sla);
        document.getElementById('modalSituacaoSla').textContent = data.sla ?? '';
        document.getElementById('modalPrazoExecucao').textContent = data.prazo ? 'Limite em ' + data.prazo : '';

        const formulario = document.getElementById('modalFooterAcoes');
        const botao = document.getElementById('modalExecutarTarefa');
        formulario.classList.toggle('hidden', !data.acao);
        formulario.removeAttribute('action');
        botao.textContent = data.acao?.rotulo ?? '';
        botao.disabled = !data.acao;

        if (data.acao) {
            formulario.action = data.acao.url;
        }

        const modal = document.getElementById('modalDetalhesRotina');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function fecharModalDetalhes() {
        const modal = document.getElementById('modalDetalhesRotina');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
</script>
