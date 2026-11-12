<div id="modalDetalhesLog" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-3xl w-full p-8 relative shadow-2xl max-h-[90vh] flex flex-col">
        <button type="button" onclick="fecharModalLog()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0 mb-6">
            <h2 class="text-3xl font-bold text-[#1e2d3d] mb-3">Detalhes do Registro de Log</h2>
            <div class="flex items-center gap-2">
                <span id="modalLogSituacao" class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Aberto</span>
                <span id="modalLogId" class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 tabular-nums">#101</span>
            </div>
        </div>

        <div class="space-y-6 overflow-y-auto flex-1 pr-1">
            <div class="border border-gray-200 rounded-3xl p-6 bg-white shadow-sm space-y-6">
                <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-2xl bg-[#f4f1ea] flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-file-lines text-[#1e2d3d] text-lg"></i>
                    </div>
                    <div>
                        <h3 id="modalLogDescricaoEvento" class="font-bold text-[#1e2d3d] text-base mb-0.5">—</h3>
                        <p class="text-xs text-gray-500 font-medium">Origem: Rotinas Diárias</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pb-6 border-b border-gray-100 text-sm">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Data do Evento</span>
                        <span id="modalLogDataEvento" class="font-bold text-[#1e2d3d] tabular-nums">—</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Executor</span>
                        <span id="modalLogExecutor" class="font-bold text-[#1e2d3d]">—</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pb-6 border-b border-gray-100 text-sm">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Tarefa</span>
                        <span id="modalLogTarefa" class="block font-bold text-[#1e2d3d] uppercase text-xs leading-snug mb-1">—</span>
                        <span id="modalLogHoraProgramada" class="text-xs text-gray-500 font-medium"></span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Colaborador(a) Original</span>
                        <span id="modalLogColaborador" class="block font-bold text-[#1e2d3d]">—</span>
                        <span id="modalLogGrupo" class="text-xs text-gray-500 font-medium"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Criado em</span>
                        <span id="modalLogCriadoEm" class="font-bold text-[#1e2d3d] tabular-nums">—</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Finalizado em</span>
                        <span id="modalLogFinalizadoEm" class="font-bold text-[#1e2d3d] tabular-nums">Em aberto</span>
                    </div>
                </div>
            </div>

            <div class="w-full py-4 bg-[#f4f1ea] rounded-2xl text-center text-xs font-bold text-[#1e2d3d]">
                Evento registrado no sistema
            </div>
        </div>
    </div>
</div>

<script>
    function abrirModalDetalhesLog(button) {
        const d = button.dataset;

        document.getElementById('modalLogId').innerText = `#${d.id}`;
        document.getElementById('modalLogSituacao').innerText = d.situacao;
        
        const badgeSituacao = document.getElementById('modalLogSituacao');
        if (d.situacao === 'Finalizado') {
            badgeSituacao.className = 'px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700';
        } else {
            badgeSituacao.className = 'px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700';
        }

        document.getElementById('modalLogDescricaoEvento').innerText = d.descricao;
        document.getElementById('modalLogDataEvento').innerText = d.data;
        document.getElementById('modalLogExecutor').innerText = d.executor;
        document.getElementById('modalLogTarefa').innerText = d.tarefa;
        document.getElementById('modalLogHoraProgramada').innerText = d.detalhe ? `Detalhe: ${d.detalhe}` : '';
        document.getElementById('modalLogColaborador').innerText = d.colaborador;
        document.getElementById('modalLogGrupo').innerText = d.grupo;
        document.getElementById('modalLogCriadoEm').innerText = d.criado;
        document.getElementById('modalLogFinalizadoEm').innerText = d.situacao === 'Finalizado' ? d.criado : 'Em aberto';

        const modal = document.getElementById('modalDetalhesLog');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function fecharModalLog() {
        const modal = document.getElementById('modalDetalhesLog');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
</script>