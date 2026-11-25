@php
    $classePaginacao = 'flex h-8 w-8 items-center justify-center rounded-full border border-[#e8d6bf] bg-white text-[#17384b] transition-colors hover:bg-[#f9f7f2] disabled:cursor-default disabled:opacity-35';
    $rotuloPlural ??= $rotulo.'s';
    $textoEncontrado ??= 'encontrada';
@endphp

<div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs font-semibold text-[#536b7b]">
    <p id="contadorResultados" class="tabular-nums" role="status"></p>
    <div class="flex items-center gap-2" role="group" aria-label="Paginação de {{ $rotuloPlural }}">
        <button type="button" id="btnAnterior" aria-label="Página anterior" class="{{ $classePaginacao }}">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
        <span id="infoPaginacao" class="px-1 font-bold tabular-nums text-[#17384b]">1 / 1</span>
        <button type="button" id="btnProximo" aria-label="Próxima página" class="{{ $classePaginacao }}">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>
</div>

<script>
    (() => {
        const formulario = document.getElementById(@js($idFormulario));
        const tabela = document.getElementById(@js($idTabela));
        const mensagemVazia = document.getElementById(@js($idMensagemVazia));
        const filtroNome = formulario.querySelector('input[type="search"]');
        const filtroSituacao = formulario.querySelector('[data-filtro-situacao]');
        const linhas = [...tabela.querySelectorAll('tbody tr[data-situacao]')];
        const contador = document.getElementById('contadorResultados');
        const indicadorPagina = document.getElementById('infoPaginacao');
        const anterior = document.getElementById('btnAnterior');
        const proximo = document.getElementById('btnProximo');
        const rotulo = @js($rotulo);
        const rotuloPlural = @js($rotuloPlural);
        const textoEncontrado = @js($textoEncontrado);
        const itensPorPagina = 10;
        let paginaAtual = 1;

        const normalizar = texto => texto.toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        function atualizarTabela() {
            const termo = normalizar(filtroNome.value);
            const situacao = filtroSituacao.value;
            const filtradas = linhas.filter(linha =>
                (situacao === '' || linha.dataset.situacao === situacao)
                && normalizar(linha.querySelector('[data-nome-catalogo]').textContent).includes(termo)
            );

            const totalItens = filtradas.length;
            const totalPaginas = Math.max(1, Math.ceil(totalItens / itensPorPagina));
            paginaAtual = Math.min(totalPaginas, Math.max(1, paginaAtual));
            const inicio = (paginaAtual - 1) * itensPorPagina;

            linhas.forEach(linha => {
                linha.hidden = true;
            });
            filtradas.slice(inicio, inicio + itensPorPagina).forEach(linha => {
                linha.hidden = false;
            });

            const plural = totalItens !== 1;
            mensagemVazia.hidden = totalItens > 0;
            contador.textContent = `${totalItens} ${plural ? rotuloPlural : rotulo} ${textoEncontrado}${plural ? 's' : ''}`;
            indicadorPagina.textContent = `${paginaAtual} / ${totalPaginas}`;
            anterior.disabled = paginaAtual === 1;
            proximo.disabled = paginaAtual === totalPaginas;
        }

        function mudarPagina(direcao) {
            paginaAtual += direcao;
            atualizarTabela();
        }

        function aplicarFiltros() {
            paginaAtual = 1;
            atualizarTabela();
        }

        filtroNome.addEventListener('input', aplicarFiltros);
        filtroSituacao.addEventListener('change', aplicarFiltros);
        formulario.addEventListener('submit', event => {
            event.preventDefault();
            aplicarFiltros();
        });
        anterior.addEventListener('click', () => mudarPagina(-1));
        proximo.addEventListener('click', () => mudarPagina(1));

        atualizarTabela();
    })();
</script>
