@extends('layouts.app')

@section('title', 'Relatório de Ferramentas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Relatório de Ferramentas</h1>
        <p class="text-sm text-gray-500 mt-1">Visualize, busque e gerencie as ferramentas de apoio cadastradas</p>
    </div>

    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-200 mb-6">
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Nome da ferramenta</label>
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative w-full">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" id="filtroNomeFerramenta" placeholder="Buscar pelo nome..." class="w-full pl-11 pr-4 py-3 bg-white border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition">
            </div>
            <a href="#" onclick="openModal('ferramentaModal')" class="w-full sm:w-auto inline-flex items-center justify-center space-x-2 bg-[#1b2a38] hover:bg-[#111c26] text-white px-5 py-3 rounded-xl font-medium text-sm transition shrink-0">
                <i class="fa-solid fa-plus"></i>
                <span>Novo registro</span>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tabelaFerramentas" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#fcfbf9] border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-4 px-6 w-24">ID</th>
                        <th class="py-4 px-6">Ferramenta</th>
                        <th class="py-4 px-6 w-28 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($ferramentas as $ferramenta)
                        <tr class="linha-ferramenta hover:bg-gray-50/60 transition">
                            <td class="py-4 px-6 font-medium text-gray-500">#{{ $ferramenta->id }}</td>
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    <span class="font-bold text-gray-800 uppercase">{{ $ferramenta->nome_ferramenta ?? $ferramenta->nome ?? 'Sem nome' }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button type="button" 
                                    onclick="abrirModalEdicao({ id: '{{ $ferramenta->id }}', nome: '{{ $ferramenta->nome_ferramenta }}' })" 
                                    class="inline-flex items-center space-x-1 text-sm font-semibold text-gray-700 hover:text-gray-900 transition">
                                    <span>Editar</span>
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-8 text-center text-gray-400">
                                Nenhuma ferramenta cadastrada no momento.
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

@include('processos.partials.modal-ferramenta')

<script>
    const itensPorPagina = 10;
    let paginaAtual = 1;
    let linhasFiltradas = [];

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }

    function abrirModalNovo(modalId) {
        const form = document.querySelector(`#${modalId} form`) || document.getElementById('formFerramenta');
        if (form) form.reset();
        
        const inputId = document.getElementById('ferramenta_id');
        if (inputId) inputId.value = '';

        const titulo = document.getElementById('modalTitulo');
        if (titulo) titulo.textContent = 'Nova ferramenta';

        openModal(modalId);
    }

    function abrirModalEdicao(dados) {
        const modalId = 'ferramentaModal'; 
        
        const inputId = document.getElementById('ferramenta_id');
        if (inputId) inputId.value = dados.id;

        const inputNome = document.getElementById('nome_ferramenta');
        if (inputNome) inputNome.value = dados.nome;

        const titulo = document.getElementById('modalTitulo');
        if (titulo) titulo.textContent = 'Editar ferramenta';

        openModal(modalId);
    }

    function atualizarTabela() {
        const todasLinhas = Array.from(document.querySelectorAll('#tabelaFerramentas tbody tr.linha-ferramenta'));
        const termo = document.getElementById('filtroNomeFerramenta').value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

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

        document.getElementById('contadorResultados').textContent = `${totalItens} ferramenta${totalItens !== 1 ? 's' : ''} encontrada${totalItens !== 1 ? 's' : ''}`;
        document.getElementById('infoPaginacao').textContent = `${paginaAtual} / ${totalPaginas}`;
        document.getElementById('btnAnterior').disabled = paginaAtual === 1;
        document.getElementById('btnProximo').disabled = paginaAtual === totalPaginas || totalPaginas === 0;
    }

    function mudarPagina(direcao) {
        paginaAtual += direcao;
        atualizarTabela();
    }

    document.getElementById('filtroNomeFerramenta').addEventListener('input', function() {
        paginaAtual = 1;
        atualizarTabela();
    });

    document.addEventListener('DOMContentLoaded', function() {
        atualizarTabela();
    });
</script>
@endsection