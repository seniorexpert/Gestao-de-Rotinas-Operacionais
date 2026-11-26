<dialog id="modalDetalhesRotina" aria-labelledby="rotinasTituloDetalhes"
        class="m-auto max-h-[92vh] w-[calc(100%_-_1.5rem)] max-w-xl overflow-y-auto rounded-[1.25rem] bg-[#fcfbf8] p-4 text-[#21394e] shadow-xl backdrop:bg-slate-950/45 sm:p-6">
    <button type="button" onclick="this.closest('dialog').close()"
            class="mb-3 inline-flex items-center gap-2 rounded-lg px-1 py-1.5 text-sm font-bold hover:underline focus-visible:outline focus-visible:outline-2">
        <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
        Voltar
    </button>

    <h2 id="rotinasTituloDetalhes" class="rotinas-titulo mb-3 text-2xl font-bold sm:text-[1.85rem]">Detalhes da Tarefa</h2>
    <div class="mb-4"><span id="modalBadgeStatus"></span></div>

    <div class="mb-3 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-4 shadow-sm sm:p-5">
        <h3 class="mb-3 flex items-center gap-2.5 border-b border-slate-100 pb-3 text-lg font-bold sm:text-xl">
            <span class="relative isolate inline-flex shrink-0 text-2xl leading-none" aria-hidden="true">
                <span id="modalIconeDiv"></span>
                <span id="modalCorIcone" class="pointer-events-none absolute inset-0 hidden mix-blend-color"></span>
            </span>
            <span id="modalTituloTarefa"></span>
        </h3>
        <dl class="grid grid-cols-[auto_1fr] gap-x-5 gap-y-4 sm:gap-x-7">
            <div>
                <dt class="mb-1 text-xs text-[#64748b]">Quando</dt>
                <dd id="modalHorario" class="text-sm font-bold sm:text-base"></dd>
            </div>
            <div>
                <dt class="mb-1 text-xs text-[#64748b]">Onde</dt>
                <dd id="modalLocal" class="break-words text-sm font-bold sm:text-base"></dd>
            </div>
            <div class="col-span-2">
                <dt class="mb-1 text-xs text-[#64748b]">Morador</dt>
                <dd id="modalMorador" class="break-words text-sm font-bold sm:text-base"></dd>
            </div>
            <div id="modalFerramentas" class="col-span-2 hidden border-t border-slate-100 pt-3">
                <dt class="mb-2 text-xs text-[#64748b]">Ferramentas / Materiais</dt>
                <dd id="modalFerramenta" class="w-fit rounded-xl border border-[#d3ae74]/35 bg-[#fcfbf8] px-3 py-1.5 text-sm font-semibold"></dd>
            </div>
        </dl>
    </div>

    <div id="modalOrientacoes" class="mb-3 hidden space-y-3 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-4 shadow-sm sm:p-5">
        <div id="modalInstrucaoBloco">
            <h3 class="mb-1 text-sm font-bold">Instrução de trabalho</h3>
            <p id="modalInstrucao" class="whitespace-pre-wrap break-words text-sm text-slate-600"></p>
        </div>
        <div id="modalObsBloco">
            <h3 class="mb-1 text-sm font-bold">Observações</h3>
            <p id="modalObs" class="whitespace-pre-wrap break-words text-sm text-slate-600"></p>
        </div>
        <a id="modalDocumentacao" class="inline-flex items-center gap-2 rounded-lg text-sm font-semibold underline underline-offset-2">
            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
            Ver documentação
        </a>
        <div id="modalAuxilio" class="hidden text-sm">
            <h3 id="modalAuxilioTitulo" class="font-semibold text-amber-800">Auxílio previsto para esta tarefa de:</h3>
            <p id="modalAuxiliares" class="mt-1 break-words font-semibold"></p>
        </div>
    </div>

    <div id="modalSlaExecucao" class="flex flex-wrap items-baseline justify-between gap-1 px-1 py-2 text-xs text-[#64748b]">
        <p>SLA da etapa <strong id="modalSituacaoSla" class="ml-1 text-[#21394e]"></strong></p>
        <p id="modalPrazoExecucao"></p>
    </div>

    <div id="modalResultado" class="mb-3 hidden space-y-2 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-4 shadow-sm sm:p-5">
        <h3 id="modalTituloResultado" class="text-sm font-bold"></h3>
        <p id="modalTextoResultado" class="whitespace-pre-wrap break-words text-sm text-slate-600"></p>
        <a id="modalFoto" class="block text-sm font-semibold underline underline-offset-2">Baixar foto da conclusão</a>
    </div>

    <form id="modalFooterAcoes" method="POST" class="mt-3 hidden">
        @csrf
        <input type="hidden" name="fluxo_diario" value="1">
        <button id="modalExecutarTarefa" type="submit" disabled
                class="w-full rounded-xl bg-[#2d5a27] px-4 py-3.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-[#23471f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"></button>
    </form>
</dialog>

<script>
    function abrirModalDetalhes(data) {
        const modal = document.getElementById('modalDetalhesRotina');
        const campo = id => modal.querySelector('#' + id);
        const preencher = (id, valor) => { campo(id).textContent = valor ?? ''; };

        preencher('modalIconeDiv', data.icone);
        const corIcone = campo('modalCorIcone');
        corIcone.style.backgroundColor = data.corIcone ?? '';
        corIcone.classList.toggle('hidden', !data.corIcone);
        preencher('modalTituloTarefa', data.tarefa);
        preencher('modalHorario', data.horario);
        preencher('modalLocal', data.local);
        preencher('modalMorador', data.morador);
        preencher('modalFerramenta', data.ferramenta);
        preencher('modalInstrucao', data.instrucao);
        preencher('modalObs', data.obs);
        preencher('modalSituacaoSla', data.sla);
        preencher('modalPrazoExecucao', data.prazo ? 'Limite em ' + data.prazo : '');
        campo('modalSlaExecucao').classList.toggle('hidden', !data.sla);

        campo('modalFerramentas').classList.toggle('hidden', !data.ferramenta);
        campo('modalOrientacoes').classList.toggle('hidden', !(data.instrucao || data.obs || data.documentacao || data.auxilio));
        campo('modalInstrucaoBloco').classList.toggle('hidden', !data.instrucao);
        campo('modalObsBloco').classList.toggle('hidden', !data.obs);
        const nomesAuxiliares = data.auxiliares?.join(', ') ?? '';
        preencher('modalAuxilioTitulo', nomesAuxiliares ? 'Auxílio previsto para esta tarefa de:' : 'Auxílio previsto para esta tarefa.');
        preencher('modalAuxiliares', nomesAuxiliares);
        campo('modalAuxiliares').classList.toggle('hidden', !nomesAuxiliares);
        campo('modalAuxilio').classList.toggle('hidden', !data.auxilio);
        const documento = campo('modalDocumentacao');
        documento.classList.toggle('hidden', !data.documentacao);
        documento.removeAttribute('href');
        if (data.documentacao) documento.href = data.documentacao;

        const resultado = data.justificativa || data.comentario;
        campo('modalResultado').classList.toggle('hidden', !(resultado || data.foto));
        preencher('modalTituloResultado', data.justificativa ? 'Justificativa' : 'Conclusão');
        preencher('modalTextoResultado', resultado);
        campo('modalTextoResultado').classList.toggle('hidden', !resultado);
        const foto = campo('modalFoto');
        foto.classList.toggle('hidden', !data.foto);
        foto.removeAttribute('href');
        if (data.foto) foto.href = data.foto;

        const selo = campo('modalBadgeStatus');
        selo.textContent = data.selo;
        selo.className = 'inline-block rounded-full px-3.5 py-1 text-xs font-bold ' + data.corSelo;

        const formulario = campo('modalFooterAcoes');
        const botao = campo('modalExecutarTarefa');
        formulario.classList.toggle('hidden', !data.acao);
        formulario.removeAttribute('action');
        botao.textContent = data.acao?.rotulo ?? '';
        botao.disabled = !data.acao;
        if (data.acao) formulario.action = data.acao.url;

        modal.showModal();
    }
</script>
