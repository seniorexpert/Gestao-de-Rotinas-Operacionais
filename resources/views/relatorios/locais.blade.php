@extends('layouts.app')

@section('title', 'Relatório de Locais')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-semibold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';

    $podeEditar = auth()->user()->can('editar-modulo', 'locais');
    $registros = $locais->map(fn (stdClass $local): array => [
        'id' => $local->id_local,
        'nome' => $local->local ?? 'Sem nome',
        'ativo' => (bool) $local->status,
        'campos' => ['Unidade' => $local->unidades->pluck('empresa')->join(', ') ?: 'Sem unidade'],
        'edicao' => $podeEditar && $local->editavel ? [
            'id' => $local->id_local,
            'nome' => $local->local,
            'status' => (int) $local->status,
            'unidades' => $local->ids_empresas,
        ] : null,
    ]);
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-5 sm:mb-6">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Locais</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie os locais cadastrados na unidade</p>
    </div>

    <form id="filtrosLocais" action="{{ route('relatorios.locais') }}" method="GET" role="search" aria-label="Filtros de locais"
          class="mb-4 grid min-w-0 items-end gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_auto_auto]">
        <div class="min-w-0">
            <label for="filtroNomeLocal" class="mb-1 block text-[0.8125rem] font-semibold">Nome do local</label>
            <input type="search" id="filtroNomeLocal" placeholder="Buscar pelo nome..." class="{{ $classeCampo }} min-w-0 placeholder:text-[#64748b]">
        </div>
        <div class="flex min-w-0 items-end gap-2 lg:contents">
            <div class="min-w-0 flex-1">
                <label for="filtroSituacaoLocal" class="mb-1 block text-[0.8125rem] font-semibold">Situação</label>
                <div class="relative">
                    <select id="filtroSituacaoLocal" data-filtro-situacao class="{{ $classeCampo }} appearance-none pr-10">
                        <option value="">Todos</option>
                        <option value="1">Ativos</option>
                        <option value="0">Inativos</option>
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
                </div>
            </div>
            <button type="submit" title="Buscar locais" aria-label="Buscar locais" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
        </div>
        @if ($podeEditar)
            <button type="button" onclick="abrirModalNovo('localModal')" aria-haspopup="dialog" class="{{ $classeBotao }} w-full px-4 sm:col-span-2 lg:col-span-1 lg:w-auto">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
            </button>
        @endif
    </form>

    @include('relatorios.partials.listagem-catalogo', [
        'idTabela' => 'tabelaLocais',
        'idCartoes' => 'cartoesLocais',
        'idMensagemVazia' => 'mensagemSemResultadoLocais',
        'rotuloNome' => 'Local',
        'rotuloLista' => 'Locais encontrados',
        'colunas' => ['Unidade' => 'w-44'],
        'larguraTabela' => 'min-w-[40rem]',
        'mensagemVazia' => $registros->isEmpty() ? 'Nenhum local cadastrado no momento.' : 'Nenhum local encontrado.',
    ])

    @include('relatorios.partials.paginacao-catalogo', [
        'idFormulario' => 'filtrosLocais',
        'idTabela' => 'tabelaLocais',
        'idCartoes' => 'cartoesLocais',
        'idMensagemVazia' => 'mensagemSemResultadoLocais',
        'rotulo' => 'local',
        'rotuloPlural' => 'locais',
        'textoEncontrado' => 'encontrado',
    ])
</div>

@can('editar-modulo', 'locais')
@include('processos.partials.modal-local')
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
        const form = document.querySelector(`#${modalId} form`) || document.getElementById('formLocal');
        if (form) form.reset();

        const inputId = document.getElementById('local_id');
        if (inputId) inputId.value = '';

        const titulo = document.getElementById('modalTituloLocal');
        if (titulo) titulo.textContent = 'Novo local';

        openModal(modalId);
    }

    function abrirModalEdicao(dados) {
        const formulario = document.querySelector('#localModal form');
        preencherUnidadesCadastro(formulario, dados.unidades);
        formulario.elements.status.value = dados.status;
        const modalId = 'localModal';

        const inputId = document.getElementById('local_id');
        if (inputId) inputId.value = dados.id;

        const inputNome = document.getElementById('nome_local');
        if (inputNome) inputNome.value = dados.nome;

        const titulo = document.getElementById('modalTituloLocal');
        if (titulo) titulo.textContent = 'Editar local';

        openModal(modalId);
    }

</script>
@endsection
