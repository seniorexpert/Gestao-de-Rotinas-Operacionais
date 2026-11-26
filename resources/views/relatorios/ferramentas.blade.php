@extends('layouts.app')

@section('title', 'Relatório de Ferramentas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-semibold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';

    $podeEditar = auth()->user()->can('editar-modulo', 'ferramentas');
    $registros = $ferramentas->map(fn (stdClass $ferramenta): array => [
        'id' => $ferramenta->id_ferramenta,
        'nome' => $ferramenta->ferramenta ?? 'Sem nome',
        'ativo' => (bool) $ferramenta->status,
        'campos' => ['Unidades' => $ferramenta->unidades->pluck('empresa')->join(', ') ?: 'Sem unidade'],
        'edicao' => $podeEditar && $ferramenta->editavel ? [
            'id' => $ferramenta->id_ferramenta,
            'nome' => $ferramenta->ferramenta,
            'status' => (int) $ferramenta->status,
            'unidades' => $ferramenta->ids_empresas,
        ] : null,
    ]);
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-5 sm:mb-6">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Ferramentas</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie as ferramentas de apoio cadastradas</p>
    </div>

    <form id="filtrosFerramentas" action="{{ route('relatorios.ferramentas') }}" method="GET" role="search" aria-label="Filtros de ferramentas"
          class="mb-4 grid min-w-0 items-end gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_auto_auto]">
        <div class="min-w-0">
            <label for="filtroNomeFerramenta" class="mb-1 block text-[0.8125rem] font-semibold">Nome da ferramenta</label>
            <input type="search" id="filtroNomeFerramenta" placeholder="Buscar pelo nome..." class="{{ $classeCampo }} min-w-0 placeholder:text-[#64748b]">
        </div>
        <div class="flex min-w-0 items-end gap-2 lg:contents">
            <div class="min-w-0 flex-1">
                <label for="filtroSituacaoFerramenta" class="mb-1 block text-[0.8125rem] font-semibold">Situação</label>
                <div class="relative">
                    <select id="filtroSituacaoFerramenta" data-filtro-situacao class="{{ $classeCampo }} appearance-none pr-10">
                        <option value="">Todas</option>
                        <option value="1">Ativas</option>
                        <option value="0">Inativas</option>
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                </div>
            </div>
            <button type="submit" title="Buscar ferramentas" aria-label="Buscar ferramentas" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
        </div>
        @if ($podeEditar)
            <button type="button" onclick="abrirModalNovo('ferramentaModal')" aria-haspopup="dialog" class="{{ $classeBotao }} w-full px-4 sm:col-span-2 lg:col-span-1 lg:w-auto">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
            </button>
        @endif
    </form>

    @include('relatorios.partials.listagem-catalogo', [
        'idTabela' => 'tabelaFerramentas',
        'idCartoes' => 'cartoesFerramentas',
        'idMensagemVazia' => 'mensagemSemResultadoFerramentas',
        'rotuloNome' => 'Ferramenta',
        'rotuloLista' => 'Ferramentas encontradas',
        'colunas' => ['Unidades' => 'w-44'],
        'larguraTabela' => 'min-w-[40rem]',
        'mensagemVazia' => $registros->isEmpty() ? 'Nenhuma ferramenta cadastrada no momento.' : 'Nenhuma ferramenta encontrada.',
    ])

    @include('relatorios.partials.paginacao-catalogo', [
        'idFormulario' => 'filtrosFerramentas',
        'idTabela' => 'tabelaFerramentas',
        'idCartoes' => 'cartoesFerramentas',
        'idMensagemVazia' => 'mensagemSemResultadoFerramentas',
        'rotulo' => 'ferramenta',
    ])
</div>

@can('editar-modulo', 'ferramentas')
@include('processos.partials.modal-ferramenta')
@endcan

<script>
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
        const formulario = document.querySelector('#ferramentaModal form');
        preencherUnidadesCadastro(formulario, dados.unidades);
        formulario.elements.status.value = dados.status;
        const modalId = 'ferramentaModal';

        const inputId = document.getElementById('ferramenta_id');
        if (inputId) inputId.value = dados.id;

        const inputNome = document.getElementById('nome_ferramenta');
        if (inputNome) inputNome.value = dados.nome;

        const titulo = document.getElementById('modalTitulo');
        if (titulo) titulo.textContent = 'Editar ferramenta';

        openModal(modalId);
    }

</script>
@endsection
