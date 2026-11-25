@extends('layouts.app')

@section('title', 'Relatório de Ferramentas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-semibold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-5 sm:mb-6">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Ferramentas</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie as ferramentas de apoio cadastradas</p>
    </div>

    <form id="filtrosFerramentas" action="{{ route('relatorios.ferramentas') }}" method="GET" role="search" aria-label="Filtros de ferramentas"
          class="mb-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_auto] md:items-end md:gap-2">
        <div class="min-w-0">
            <label for="filtroNomeFerramenta" class="mb-1 block text-[0.8125rem] font-semibold">Nome da ferramenta</label>
            <input type="search" id="filtroNomeFerramenta" placeholder="Buscar pelo nome..." class="{{ $classeCampo }} placeholder:text-[#64748b]">
        </div>
        <div class="min-w-0">
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
        <div class="flex items-center justify-end gap-2">
            <button type="submit" title="Buscar ferramentas" aria-label="Buscar ferramentas" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
            @can('editar-modulo', 'ferramentas')
                <button type="button" onclick="abrirModalNovo('ferramentaModal')" aria-haspopup="dialog" class="{{ $classeBotao }} px-4">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
                </button>
            @endcan
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm">
        <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#17384b]"
             role="region" aria-label="Ferramentas encontradas" tabindex="0">
            <table id="tabelaFerramentas" class="w-full min-w-[40rem] border-collapse text-left text-sm">
                <thead class="border-b border-[#e8d6bf] bg-[#f9f7f2] text-xs font-semibold uppercase text-[#536b7b]">
                    <tr>
                        <th scope="col" class="w-20 px-4 py-3.5">ID</th>
                        <th scope="col" class="px-4 py-3.5">Ferramenta</th>
                        <th scope="col" class="w-44 px-4 py-3.5">Unidades</th>
                        <th scope="col" class="w-28 px-4 py-3.5">Situação</th>
                        <th scope="col" class="w-24 px-4 py-3.5 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ferramentas as $ferramenta)
                        <tr class="linha-ferramenta border-b border-[#f0e9df]" data-situacao="{{ $ferramenta->status ? '1' : '0' }}">
                            <td class="px-4 py-4 font-semibold tabular-nums text-[#536b7b]">#{{ $ferramenta->id_ferramenta }}</td>
                            <td class="break-words px-4 py-4 font-semibold uppercase" data-nome-catalogo>
                                {{ $ferramenta->ferramenta ?? 'Sem nome' }}
                            </td>
                            <td class="px-4 py-4">{{ $ferramenta->unidades->pluck('empresa')->join(', ') ?: 'Sem unidade' }}</td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-[#e2f1e6] text-[#176b39]' => $ferramenta->status,
                                    'bg-[#f0ebe2] text-[#536b7b]' => !$ferramenta->status,
                                ])>
                                    <span class="h-2 w-2 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                                    {{ $ferramenta->status ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if ($ferramenta->editavel && auth()->user()->can('editar-modulo', 'ferramentas'))
                                    @php($dadosEdicao = [
                                        'id' => $ferramenta->id_ferramenta,
                                        'nome' => $ferramenta->ferramenta,
                                        'status' => (int) $ferramenta->status,
                                        'unidades' => $ferramenta->ids_empresas,
                                    ])
                                    <button type="button" onclick="abrirModalEdicao(@js($dadosEdicao))" aria-haspopup="dialog"
                                            class="inline-flex items-center gap-2 whitespace-nowrap text-xs font-semibold transition-colors hover:text-[#8b6b43] hover:underline">
                                        Editar <i class="fa-solid fa-chevron-right text-[0.625rem] text-[#a36f39]" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p id="mensagemSemResultadoFerramentas" @if ($ferramentas->isNotEmpty()) hidden @endif class="px-4 py-8 text-center text-sm text-[#536b7b]">
            {{ $ferramentas->isEmpty() ? 'Nenhuma ferramenta cadastrada no momento.' : 'Nenhuma ferramenta encontrada.' }}
        </p>
    </div>

    @include('relatorios.partials.paginacao-catalogo', [
        'idFormulario' => 'filtrosFerramentas',
        'idTabela' => 'tabelaFerramentas',
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
