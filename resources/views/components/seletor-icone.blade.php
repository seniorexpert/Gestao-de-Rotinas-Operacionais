@props([
    'name' => 'icone',
    'value' => '📋',
    'emojis' => [],
])

@php
    $uid = 'seletor-icone-' . \Illuminate\Support\Str::random(8);
    $listaPadrao = $emojis ?: config('icones_tarefa.emojis', []);
@endphp

<div id="{{ $uid }}" class="w-full select-none">
    <input type="hidden" name="{{ $name }}" value="{{ old($name, $value) }}" data-icone-input>

    <div class="mb-1.5 flex items-center justify-between gap-2">
        <span class="text-xs font-bold text-gray-800">Ícone</span>
        <button
            type="button"
            data-ir-selecionado
            title="Ir para o emoji selecionado"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-amber-200 bg-[#fcfbf9] px-2.5 py-0.5 text-xs font-bold text-[#1b2a38] transition-colors hover:bg-[#f4f1ea]"
        >
            <span>Selecionado:</span>
            <span data-icone-preview class="text-base leading-none">{{ old($name, $value) }}</span>
        </button>
    </div>

    <div class="relative mb-2">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-xs text-gray-400"></i>
        <input
            type="text"
            data-busca-icone
            placeholder="Pesquisar ícone (ex: banho, remédio, luva)..."
            autocomplete="off"
            class="h-10 w-full rounded-xl border border-amber-200/80 bg-white pr-8 pl-9 text-xs text-[#1b2a38] transition-colors placeholder:text-gray-400 hover:border-gray-300 focus:border-[#1b2a38] focus:ring-2 focus:ring-[#1b2a38]/10 focus:outline-none"
        >
        <button
            type="button"
            data-limpar-busca
            aria-label="Limpar busca de ícone"
            class="hidden absolute top-1/2 right-2.5 h-5 w-5 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full text-gray-400 hover:bg-[#f4f1ea] hover:text-[#1b2a38]"
        >
            <i class="fa-solid fa-xmark text-xs"></i>
        </button>
    </div>

    <div
        data-grade-container
        class="max-h-56 overflow-y-auto rounded-2xl border border-amber-200/80 bg-[#fcfbf9] p-2.5 lg:max-h-72"
    >
        <div data-espacador class="relative w-full">
            <div data-janela class="absolute inset-x-0 grid grid-cols-5 gap-2"></div>
        </div>
        <p data-vazio class="hidden py-5 text-center text-xs text-gray-400"></p>
    </div>
</div>

@once
    <script>
        (function () {
            const COLUNAS = 5;
            const FOLGA = 3;
            const RECUO = 10;
            const BORDA = 1;
            const ALTURA_BOTAO = 52;
            const GAP = 8;
            const ALTURA_LINHA = ALTURA_BOTAO + GAP;

            function iniciarSeletorIcone(root, itens) {
                const inputHidden = root.querySelector('[data-icone-input]');
                const preview = root.querySelector('[data-icone-preview]');
                const btnIrSelecionado = root.querySelector('[data-ir-selecionado]');
                const inputBusca = root.querySelector('[data-busca-icone]');
                const btnLimpar = root.querySelector('[data-limpar-busca]');
                const container = root.querySelector('[data-grade-container]');
                const espacador = root.querySelector('[data-espacador]');
                const janela = root.querySelector('[data-janela]');
                const vazio = root.querySelector('[data-vazio]');

                let selecionada = inputHidden.value || '📋';
                let filtrados = itens;

                function normalizar(texto) {
                    return (texto || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .trim();
                }

                function filtrar() {
                    const termo = normalizar(inputBusca.value);
                    btnLimpar.classList.toggle('hidden', !inputBusca.value);
                    btnLimpar.classList.toggle('flex', Boolean(inputBusca.value));

                    if (!termo) {
                        filtrados = itens;
                        return;
                    }

                    filtrados = itens.filter((item) => {
                        const alvo = normalizar(`${item.emoji} ${item.tags || ''}`);
                        return alvo.includes(termo);
                    });
                }

                function atualizarSelecao(novoEmoji) {
                    selecionada = novoEmoji;
                    inputHidden.value = novoEmoji;
                    preview.textContent = novoEmoji;
                    inputHidden.dispatchEvent(new Event('change', { bubbles: true }));
                    renderizar();
                }

                function renderizar() {
                    if (!filtrados.length) {
                        espacador.classList.add('hidden');
                        vazio.textContent = `Nenhum ícone encontrado para "${inputBusca.value}".`;
                        vazio.classList.remove('hidden');
                        return;
                    }

                    vazio.classList.add('hidden');
                    espacador.classList.remove('hidden');

                    const totalLinhas = Math.ceil(filtrados.length / COLUNAS);
                    const alturaTotal = Math.max(0, totalLinhas * ALTURA_LINHA - GAP);
                    espacador.style.height = `${alturaTotal}px`;

                    const rolagem = container.scrollTop;
                    const alturaVisivel = container.clientHeight || 224;

                    const primeiraLinha = Math.max(
                        0,
                        Math.floor((rolagem - RECUO) / ALTURA_LINHA) - FOLGA
                    );
                    const ultimaLinha = Math.min(
                        totalLinhas,
                        Math.ceil((rolagem + alturaVisivel) / ALTURA_LINHA) + FOLGA
                    );

                    janela.style.top = `${primeiraLinha * ALTURA_LINHA}px`;

                    const inicio = primeiraLinha * COLUNAS;
                    const fim = ultimaLinha * COLUNAS;
                    const fatia = filtrados.slice(inicio, fim);

                    janela.innerHTML = '';
                    fatia.forEach((item) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = item.emoji;
                        btn.title = item.tags || item.emoji;
                        const ativo = item.emoji === selecionada;

                        btn.className = [
                            'flex h-[52px] cursor-pointer items-center justify-center rounded-xl text-[1.75rem] leading-none transition-all select-none',
                            ativo
                                ? 'border border-[#1b2a38] bg-[#f4f1ea] ring-2 ring-[#1b2a38]/15'
                                : 'hover:bg-white'
                        ].join(' ');

                        btn.addEventListener('click', () => atualizarSelecao(item.emoji));
                        janela.appendChild(btn);
                    });

                    container.dispatchEvent(new CustomEvent('cor-icone:aplicar', { bubbles: true }));
                }

                function rolarParaSelecionado() {
                    const idx = filtrados.findIndex((item) => item.emoji === selecionada);
                    if (idx === -1) return;

                    const topoBotao = BORDA + RECUO + Math.floor(idx / COLUNAS) * ALTURA_LINHA;
                    const destino = topoBotao - container.clientHeight / 2 + ALTURA_BOTAO / 2;
                    container.scrollTop = Math.max(0, destino);
                    renderizar();
                }

                container.addEventListener('scroll', renderizar, { passive: true });
                window.addEventListener('resize', renderizar);

                inputBusca.addEventListener('input', () => {
                    filtrar();
                    container.scrollTop = 0;
                    renderizar();
                });

                btnLimpar.addEventListener('click', () => {
                    inputBusca.value = '';
                    filtrar();
                    rolarParaSelecionado();
                    inputBusca.focus();
                });

                btnIrSelecionado.addEventListener('click', () => {
                    if (inputBusca.value) {
                        inputBusca.value = '';
                        filtrar();
                    }
                    rolarParaSelecionado();
                });

                filtrar();
                requestAnimationFrame(() => {
                    rolarParaSelecionado();
                });
            }

            window.iniciarSeletorIcone = iniciarSeletorIcone;
        })();
    </script>
@endonce

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('{{ $uid }}');
        if (root && typeof window.iniciarSeletorIcone === 'function') {
            window.iniciarSeletorIcone(root, @json($listaPadrao));
        }
    });
</script>