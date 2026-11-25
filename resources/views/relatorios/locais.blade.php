@extends('layouts.app')

@section('title', 'Relatório de Locais')

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
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Locais</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie os locais cadastrados na unidade</p>
    </div>

    <form id="filtrosLocais" action="{{ route('relatorios.locais') }}" method="GET" role="search" aria-label="Filtros de locais"
          class="mb-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_auto] md:items-end md:gap-2">
        <div class="min-w-0">
            <label for="filtroNomeLocal" class="mb-1 block text-[0.8125rem] font-semibold">Nome do local</label>
            <input type="search" id="filtroNomeLocal" placeholder="Buscar pelo nome..." class="{{ $classeCampo }} placeholder:text-[#64748b]">
        </div>
        <div class="min-w-0">
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
        <div class="flex items-center justify-end gap-2">
            <button type="submit" title="Buscar locais" aria-label="Buscar locais" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
            @can('editar-modulo', 'locais')
                <button type="button" onclick="abrirModalNovo('localModal')" aria-haspopup="dialog" class="{{ $classeBotao }} px-4">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
                </button>
            @endcan
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm">
        <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#17384b]"
             role="region" aria-label="Locais encontrados" tabindex="0">
            <table id="tabelaLocais" class="w-full min-w-[40rem] border-collapse text-left text-sm">
                <thead class="border-b border-[#e8d6bf] bg-[#f9f7f2] text-xs font-semibold uppercase text-[#536b7b]">
                    <tr>
                        <th scope="col" class="w-20 px-4 py-3.5">ID</th>
                        <th scope="col" class="px-4 py-3.5">Local</th>
                        <th scope="col" class="w-44 px-4 py-3.5">Unidade</th>
                        <th scope="col" class="w-28 px-4 py-3.5">Situação</th>
                        <th scope="col" class="w-24 px-4 py-3.5 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($locais as $local)
                        <tr class="linha-local border-b border-[#f0e9df]" data-situacao="{{ $local->status ? '1' : '0' }}">
                            <td class="px-4 py-4 font-semibold tabular-nums text-[#536b7b]">#{{ $local->id_local }}</td>
                            <td class="break-words px-4 py-4 font-semibold uppercase" data-nome-catalogo>{{ $local->local }}</td>
                            <td class="px-4 py-4">{{ $local->unidades->pluck('empresa')->join(', ') ?: 'Sem unidade' }}</td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-[#e2f1e6] text-[#176b39]' => $local->status,
                                    'bg-[#f0ebe2] text-[#536b7b]' => !$local->status,
                                ])>
                                    <span class="h-2 w-2 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                                    {{ $local->status ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if ($local->editavel && auth()->user()->can('editar-modulo', 'locais'))
                                    @php($dadosEdicao = [
                                        'id' => $local->id_local,
                                        'nome' => $local->local,
                                        'status' => (int) $local->status,
                                        'unidades' => $local->ids_empresas,
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
        <p id="mensagemSemResultadoLocais" @if ($locais->isNotEmpty()) hidden @endif class="px-4 py-8 text-center text-sm text-[#536b7b]">
            {{ $locais->isEmpty() ? 'Nenhum local cadastrado no momento.' : 'Nenhum local encontrado.' }}
        </p>
    </div>

    @include('relatorios.partials.paginacao-catalogo', [
        'idFormulario' => 'filtrosLocais',
        'idTabela' => 'tabelaLocais',
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
