@extends('layouts.app')

@section('title', 'Relatório de Tarefas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Relatório de Tarefas</h1>
        <p class="text-sm text-gray-500 mt-1">Visualize, busque e gerencie as tarefas cadastradas no sistema</p>
    </div>

    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-200 mb-6">
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Nome da Tarefa</label>
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative w-full">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" id="filtroNomeTarefa" placeholder="Buscar pelo nome..." class="w-full pl-11 pr-4 py-3 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition">
            </div>
            <a href="#" onclick="event.preventDefault(); abrirModalNovo('taskModal')" class="w-full sm:w-auto inline-flex items-center justify-center space-x-2 bg-[#1b2a38] hover:bg-[#111c26] text-white px-5 py-3 rounded-xl font-medium text-sm transition shrink-0">
                <i class="fa-solid fa-plus"></i>
                <span>Novo registro</span>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tabelaTarefas" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#fcfbf9] border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-4 px-6 w-24">ID</th>
                        <th class="py-4 px-6">Nome da Tarefa</th>
                        <th class="py-4 px-6 w-40">Duração</th>
                        <th class="py-4 px-6 w-44">Ferramentas</th>
                        <th class="py-4 px-6 w-28 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($tarefas as $tarefa)
                        @php
                            [$horas, $minutos, $segundos] = array_pad(explode(':', $tarefa->tempo_realizar_tarefa ?? ''), 3, 0);
                            $iconeTarefa = trim((string) ($tarefa->icone ?? '')) ?: '📋';
                            $corIconeTarefa = trim((string) ($tarefa->cor_icone ?? '')) ?: 'original';
                            $hexCorTarefa = ($corIconeTarefa !== 'original' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $corIconeTarefa))
                                ? $corIconeTarefa
                                : null;

                            $dadosEdicao = [
                                'id' => $tarefa->id_tarefa,
                                'nome' => $tarefa->tarefa,
                                'tempo' => ((int) $horas * 3600 + (int) $minutos * 60 + (int) $segundos) / 60,
                                'status' => $tarefa->status ? 'Ativa' : 'Inativa',
                                'descricao' => $tarefa->descricao,
                                'id_ferramenta' => $tarefa->id_ferramenta,
                                'icone' => $iconeTarefa,
                                'cor_icone' => $corIconeTarefa,
                            ];
                        @endphp
                        <tr class="linha-tarefa hover:bg-gray-50/60 transition">
                            <td class="py-4 px-6 font-medium text-gray-500">#{{ $tarefa->id_tarefa }}</td>
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    <span data-tabela-icone-box
                                          data-icone="{{ $iconeTarefa }}"
                                          data-hex-cor="{{ $hexCorTarefa ?? '' }}"
                                          class="h-9 w-9 rounded-xl bg-[#fcfbf9] border border-amber-200/70 flex items-center justify-center text-xl leading-none shrink-0">
                                        {{ $iconeTarefa }}
                                    </span>
                                    <span class="font-bold text-gray-800 uppercase">{{ $tarefa->tarefa ?? 'Sem nome' }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-gray-500">
                                {{ $tarefa->tempo_realizar_tarefa ? substr($tarefa->tempo_realizar_tarefa, 0, 8) : 'Não informada' }}
                            </td>
                            <td class="py-4 px-6 text-gray-500">
                                {{ $tarefa->ferramenta ?? '—' }}
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button type="button"
                                    onclick="abrirModalEdicao(@js($dadosEdicao))"
                                    class="inline-flex items-center space-x-1 text-sm font-semibold text-gray-700 hover:text-gray-900 transition">
                                    <span>Editar</span>
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="linha-vazia">
                            <td colspan="5" class="py-8 text-center text-gray-400">
                                Nenhuma tarefa cadastrada no momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-white border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div id="contadorResultados" class="text-xs text-gray-500 font-medium"></div>
            
            <div class="flex items-center space-x-2">
                <button id="btnAnterior" onclick="mudarPagina(-1)" class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <span id="infoPaginacao" class="text-xs font-semibold text-gray-700 px-2">1 / 1</span>
                <button id="btnProximo" onclick="mudarPagina(1)" class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
    </div>

</div>

@include('processos.partials.modal-tarefa')

<script>
    const itensPorPagina = 10; 
    let paginaAtual = 1;
    let linhasFiltradas = [];

    function aplicarPinturaIconeTabela(el, emoji, hex) {
        if (!el) return;

        el.textContent = emoji;
        el.style.position = '';
        el.style.overflow = '';

        const overlayAnterior = el.querySelector('[data-overlay-cor]');
        if (overlayAnterior) {
            overlayAnterior.remove();
        }

        if (!hex) return;

        el.style.position = 'relative';
        el.style.overflow = 'hidden';

        const camada = document.createElement('span');
        camada.setAttribute('data-overlay-cor', '1');
        camada.style.position = 'absolute';
        camada.style.inset = '0';
        camada.style.backgroundColor = hex;
        camada.style.mixBlendMode = 'color';
        camada.style.pointerEvents = 'none';
        camada.style.borderRadius = 'inherit';

        el.appendChild(camada);
    }

    function sincronizarModalTarefa(icone, corIcone) {
        const emoji = icone || '📋';
        const cor = corIcone || 'original';

        const inputIcone = document.querySelector('#taskModal [data-icone-input]');
        if (inputIcone) {
            inputIcone.value = emoji;
            inputIcone.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const previewTopo = document.querySelector('#taskModal [data-icone-preview]');
        if (previewTopo) {
            previewTopo.textContent = emoji;
        }

        if (typeof window.selecionarCorIcone === 'function') {
            window.selecionarCorIcone(cor);
        }

        const btnIr = document.querySelector('#taskModal [data-ir-selecionado]');
        if (btnIr) {
            btnIr.click();
        }
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            window.dispatchEvent(new Event('resize'));
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function abrirModalNovo(modalId) {
        const form = document.querySelector(`#${modalId} form`);
        if (form) form.reset();
        
        const inputId = document.getElementById('tarefa_id');
        if (inputId) inputId.value = '';

        const titulo = document.getElementById('modalTituloTarefa');
        if (titulo) titulo.textContent = 'Cadastro de tarefa';

        openModal(modalId);
        sincronizarModalTarefa('📋', 'original');
    }

    function abrirModalEdicao(dados) {
        const modalId = 'taskModal';
        document.querySelector('#taskModal form')?.reset();
        
        const inputId = document.getElementById('tarefa_id');
        if (inputId) inputId.value = dados.id;

        const inputNome = document.getElementById('nome_tarefa');
        if (inputNome) inputNome.value = dados.nome;

        const inputTempo = document.getElementById('tempo_tarefa');
        if (inputTempo) inputTempo.value = dados.tempo || '';

        const selectStatus = document.getElementById('status_tarefa');
        if (selectStatus) selectStatus.value = dados.status;

        const inputDescricao = document.getElementById('descricao_tarefa');
        if (inputDescricao) inputDescricao.value = dados.descricao ?? '';

        document.getElementById('id_ferramenta').value = dados.id_ferramenta ?? '';

        const titulo = document.getElementById('modalTituloTarefa');
        if (titulo) titulo.textContent = 'Editar tarefa';

        openModal(modalId);
        sincronizarModalTarefa(dados.icone || '📋', dados.cor_icone || 'original');
    }

    function atualizarTabela() {
        const todasLinhas = Array.from(document.querySelectorAll('#tabelaTarefas tbody tr.linha-tarefa'));
        const termo = document.getElementById('filtroNomeTarefa').value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

        linhasFiltradas = todasLinhas.filter(linha => {
            const colunaNome = linha.cells[1];
            if (!colunaNome) return false;
            const texto = colunaNome.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            return texto.includes(termo);
        });

        todasLinhas.forEach(l => l.style.display = 'none');

        const totalItens = linhasFiltradas.length;
        const totalPaginas = Math.ceil(totalItens / itensPorPagina) || 1;
        
        if (paginaAtual > totalPaginas) paginaAtual = totalPaginas;
        if (paginaAtual < 1) paginaAtual = 1;

        const inicio = (paginaAtual - 1) * itensPorPagina;
        const fim = inicio + itensPorPagina;
        const itensPagina = linhasFiltradas.slice(inicio, fim);

        itensPagina.forEach(l => l.style.display = '');

        document.getElementById('contadorResultados').textContent = `${totalItens} tarefa${totalItens !== 1 ? 's' : ''} encontrada${totalItens !== 1 ? 's' : ''}`;
        document.getElementById('infoPaginacao').textContent = `${paginaAtual} / ${totalPaginas}`;
        document.getElementById('btnAnterior').disabled = paginaAtual === 1;
        document.getElementById('btnProximo').disabled = paginaAtual === totalPaginas || totalPaginas === 0;
    }

    function mudarPagina(direcao) {
        paginaAtual += direcao;
        atualizarTabela();
    }

    document.getElementById('filtroNomeTarefa').addEventListener('input', function() {
        paginaAtual = 1; 
        atualizarTabela();
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-tabela-icone-box]').forEach((box) => {
            aplicarPinturaIconeTabela(box, box.dataset.icone || '📋', box.dataset.hexCor || '');
        });
        atualizarTabela();
    });
</script>
@endsection