<div id="taskModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="relative flex max-h-[90vh] w-full max-w-xl flex-col rounded-3xl bg-white p-8 shadow-2xl">
        <button type="button" onclick="closeModal('taskModal')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 focus:outline-none">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="flex-shrink-0">
            <h2 id="modalTituloTarefa" class="mb-1 text-3xl font-bold text-[#1e2d3d]">Cadastro de tarefa</h2>
            <p class="sans-text mb-6 text-sm text-gray-500">Instruções e recursos utilizados</p>
        </div>

        <form action="{{ route('tarefas.store') }}" method="POST" enctype="multipart/form-data" class="sans-text flex-1 space-y-6 overflow-y-auto pr-1">
            @csrf

            <input type="hidden" name="id" id="tarefa_id">

            <div>
                <label class="mb-2 block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Nome da tarefa</label>
                <input type="text" name="nome_tarefa" id="nome_tarefa" required class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none" placeholder="Ex.: Alimentação">
            </div>

            <div>
                <label class="mb-2 block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Duração</label>
                <div class="relative flex items-center">
                    <input type="number" name="tempo_tarefa" id="tempo_tarefa" required min="0.01" max="1439.99" step="any" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 pr-14 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none" placeholder="15">
                    <span class="absolute right-4 text-sm font-medium text-gray-400">min</span>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Situação</label>
                <select name="status_tarefa" id="status_tarefa" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none">
                    <option value="Ativa">Ativa</option>
                    <option value="Inativa">Inativa</option>
                </select>
            </div>

            <div>
                <label for="id_ferramenta" class="mb-2 block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Ferramenta</label>
                <select name="id_ferramenta" id="id_ferramenta" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none">
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
                    '#1e2d3d'   => ['label' => 'Azul escuro',    'hex' => '#1e2d3d'],
                    '#2563eb'   => ['label' => 'Azul',           'hex' => '#2563eb'],
                    '#16a34a'   => ['label' => 'Verde',          'hex' => '#16a34a'],
                    '#dc2626'   => ['label' => 'Vermelho',       'hex' => '#dc2626'],
                    '#ea580c'   => ['label' => 'Laranja',        'hex' => '#ea580c'],
                    '#9333ea'   => ['label' => 'Roxo',           'hex' => '#9333ea'],
                    '#4b5563'   => ['label' => 'Cinza',          'hex' => '#4b5563'],
                ];
            @endphp

            <div id="blocoCorIcone">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <label class="block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Cor do ícone</label>

                    <!-- Badge com ícone pintado + nome da cor selecionada -->
                    <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-[#fcfbf9] px-3 py-1">
                        <span id="previewCorIcone" class="relative inline-flex h-6 w-6 items-center justify-center text-lg leading-none">
                            <span id="previewCorIconeEmoji">📋</span>
                        </span>
                        <span id="labelCorIcone" class="text-xs font-bold text-[#1e2d3d]">Cores originais</span>
                    </div>
                </div>

                <input type="hidden" name="cor_icone" id="cor_icone" value="original">

                <div class="flex flex-wrap items-center gap-2.5">
                    @foreach ($coresIcone as $valorCor => $infoCor)
                        <button
                            type="button"
                            data-cor-btn="{{ $valorCor }}"
                            data-cor-label="{{ $infoCor['label'] }}"
                            data-cor-hex="{{ $infoCor['hex'] ?? '' }}"
                            title="{{ $infoCor['label'] }}"
                            onclick="selecionarCorIcone('{{ $valorCor }}')"
                            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border border-gray-300 transition ring-2 ring-[#1e2d3d] ring-offset-2"
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
                <label class="mb-2 block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Descrição e instruções</label>
                <textarea name="descricao_tarefa" id="descricao_tarefa" rows="3" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none" placeholder="Orientações para execução..."></textarea>
            </div>

            <div>
                <label class="mb-2 block text-xs font-bold tracking-wider text-[#1e2d3d] uppercase">Manual ou arquivo de apoio</label>
                <input type="file" name="manual_tarefa" class="w-full text-sm text-gray-500 file:mr-4 file:rounded-2xl file:border-0 file:bg-[#1e2d3d] file:px-4 file:py-3 file:text-sm file:font-semibold file:text-white hover:file:bg-opacity-90">
            </div>

            <div class="sticky bottom-0 flex items-center justify-end gap-4 bg-white pt-4 pb-1">
                <button type="button" onclick="closeModal('taskModal')" class="w-1/2 rounded-2xl border border-[#e5dec9] py-3.5 text-center text-sm font-bold text-[#1e2d3d] transition hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="w-1/2 rounded-2xl bg-[#1e2d3d] py-3.5 text-center text-sm font-bold text-white transition hover:bg-opacity-90">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const CORES_MAP = {
            'original': { label: 'Cores originais', hex: null },
            '#1e2d3d':  { label: 'Azul escuro',    hex: '#1e2d3d' },
            '#2563eb':  { label: 'Azul',           hex: '#2563eb' },
            '#16a34a':  { label: 'Verde',          hex: '#16a34a' },
            '#dc2626':  { label: 'Vermelho',       hex: '#dc2626' },
            '#ea580c':  { label: 'Laranja',        hex: '#ea580c' },
            '#9333ea':  { label: 'Roxo',           hex: '#9333ea' },
            '#4b5563':  { label: 'Cinza',          hex: '#4b5563' },
        };

        function getEmojiAtual() {
            const input = document.querySelector('#taskModal [data-icone-input]');
            return (input && input.value) ? input.value : '📋';
        }

        function aplicarEstiloPintura(el, emoji, hex) {
            if (!el) return;

            el.style.color = '';
            el.style.webkitTextFillColor = '';
            el.style.filter = '';
            el.textContent = emoji;

            if (!hex) {
                return;
            }

            requestAnimationFrame(() => {
                el.style.color = hex;
                el.style.webkitTextFillColor = hex;
                el.style.filter = 'grayscale(100%) contrast(150%) drop-shadow(0 0 0 ' + hex + ')';
            });
        }

        window.atualizarPreviewCorIcone = function () {
            const corAtual = document.getElementById('cor_icone')?.value || 'original';
            const info = CORES_MAP[corAtual] || CORES_MAP['original'];
            const emoji = getEmojiAtual();

            const labelEl = document.getElementById('labelCorIcone');
            const emojiEl = document.getElementById('previewCorIconeEmoji');

            if (labelEl) labelEl.textContent = info.label;
            aplicarEstiloPintura(emojiEl, emoji, info.hex);

            const previewTopo = document.querySelector('#taskModal [data-icone-preview]');
            aplicarEstiloPintura(previewTopo, emoji, info.hex);

            document.querySelectorAll('#taskModal [data-janela] button').forEach((btn) => {
                aplicarEstiloPintura(btn, btn.textContent, info.hex);
            });
        };

        window.selecionarCorIcone = function (valorCor) {
            const inputCor = document.getElementById('cor_icone');
            if (inputCor) inputCor.value = valorCor;

            document.querySelectorAll('[data-cor-btn]').forEach((btn) => {
                const ativo = btn.getAttribute('data-cor-btn') === valorCor;
                btn.classList.toggle('ring-2', ativo);
                btn.classList.toggle('ring-[#1e2d3d]', ativo);
                btn.classList.toggle('ring-offset-2', ativo);
                btn.classList.toggle('border-[#1e2d3d]', ativo);
                btn.classList.toggle('border-gray-300', !ativo);
            });

            window.atualizarPreviewCorIcone();
        };

        document.addEventListener('change', function (e) {
            if (e.target && e.target.matches('#taskModal [data-icone-input]')) {
                window.atualizarPreviewCorIcone();
            }
        });

        document.addEventListener('cor-icone:aplicar', function () {
            window.atualizarPreviewCorIcone();
        });

        const modal = document.getElementById('taskModal');
        if (modal) {
            const observer = new MutationObserver(() => {
                const visivel = !modal.classList.contains('hidden') && getComputedStyle(modal).display !== 'none';
                if (visivel) {
                    modal.style.display = 'flex';
                    modal.style.alignItems = 'center';
                    modal.style.justifyContent = 'center';
                    window.atualizarPreviewCorIcone();
                }
            });
            observer.observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
        }

        document.addEventListener('DOMContentLoaded', function () {
            window.selecionarCorIcone('original');
        });
    })();
</script>