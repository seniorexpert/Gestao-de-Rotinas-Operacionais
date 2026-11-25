@extends('layouts.app')

@section('title', 'Relatório de Tarefas')

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
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Tarefas</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie as tarefas cadastradas no sistema</p>
    </div>

    <form id="filtrosTarefas" action="{{ route('relatorios.tarefas') }}" method="GET" role="search" aria-label="Filtros de tarefas"
          class="mb-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_auto] md:items-end md:gap-2">
        <div class="min-w-0">
            <label for="filtroNomeTarefa" class="mb-1 block text-[0.8125rem] font-semibold">Nome da Tarefa</label>
            <input type="search" id="filtroNomeTarefa" placeholder="Buscar pelo nome..." class="{{ $classeCampo }} placeholder:text-[#64748b]">
        </div>
        <div class="min-w-0">
            <label for="filtroSituacaoTarefa" class="mb-1 block text-[0.8125rem] font-semibold">Situação</label>
            <div class="relative">
                <select id="filtroSituacaoTarefa" data-filtro-situacao class="{{ $classeCampo }} appearance-none pr-10">
                    <option value="">Todas</option>
                    <option value="1">Ativas</option>
                    <option value="0">Inativas</option>
                </select>
                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[0.65rem]" aria-hidden="true"></i>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2">
            <button type="submit" title="Buscar tarefas" aria-label="Buscar tarefas" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
            @can('editar-modulo', 'tarefas')
                <button type="button" onclick="abrirModalNovo('taskModal')" aria-haspopup="dialog" class="{{ $classeBotao }} px-4">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
                </button>
            @endcan
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm">
        <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#17384b]"
             role="region" aria-label="Tarefas encontradas" tabindex="0">
            <table id="tabelaTarefas" class="w-full min-w-[55rem] border-collapse text-left text-sm">
                <thead class="border-b border-[#e8d6bf] bg-[#f9f7f2] text-xs font-semibold uppercase text-[#536b7b]">
                    <tr>
                        <th scope="col" class="w-20 px-4 py-3.5">ID</th>
                        <th scope="col" class="px-4 py-3.5">Nome da Tarefa</th>
                        <th scope="col" class="w-28 px-4 py-3.5">Duração</th>
                        <th scope="col" class="w-36 px-4 py-3.5">Ferramentas</th>
                        <th scope="col" class="w-36 px-4 py-3.5">Unidades</th>
                        <th scope="col" class="w-28 px-4 py-3.5">Situação</th>
                        <th scope="col" class="w-24 px-4 py-3.5 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tarefas as $tarefa)
                        @php
                            [$horas, $minutos, $segundos] = array_pad(explode(':', $tarefa->tempo_realizar_tarefa ?? ''), 3, 0);
                            $iconeRaw = $tarefa->icone ?? '';
                            $iconeTarefa = trim((string) $iconeRaw) !== '' ? trim((string) $iconeRaw) : '📋';
                            $corIconeTarefa = trim((string) ($tarefa->cor_icone ?? '')) ?: 'original';
                            $dadosEdicao = [
                                'id' => $tarefa->id_tarefa,
                                'nome' => $tarefa->tarefa,
                                'tempo' => ((int) $horas * 3600 + (int) $minutos * 60 + (int) $segundos) / 60,
                                'status' => $tarefa->status ? 'Ativa' : 'Inativa',
                                'descricao' => $tarefa->descricao,
                                'id_ferramenta' => $tarefa->id_ferramenta,
                                'unidades' => $tarefa->ids_empresas,
                                'icone' => $iconeTarefa,
                                'cor_icone' => $corIconeTarefa,
                            ];
                        @endphp
                        <tr class="linha-tarefa border-b border-[#f0e9df]" data-situacao="{{ $tarefa->status ? '1' : '0' }}">
                            <td class="px-4 py-4 font-semibold tabular-nums text-[#536b7b]">#{{ $tarefa->id_tarefa }}</td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <x-icone-tarefa :icone="$iconeTarefa" :cor="$corIconeTarefa" class="h-8 w-8 text-2xl" />
                                    <span data-nome-catalogo class="min-w-0 break-words font-semibold uppercase">{{ $tarefa->tarefa ?? 'Sem nome' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 tabular-nums">
                                {{ $tarefa->tempo_realizar_tarefa ? substr($tarefa->tempo_realizar_tarefa, 0, 5) : 'Não informada' }}
                            </td>
                            <td class="px-4 py-4">
                                {{ $tarefa->ferramenta ?? '—' }}
                            </td>
                            <td class="px-4 py-4">{{ $tarefa->unidades->pluck('empresa')->join(', ') ?: 'Sem unidade' }}</td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-[#e2f1e6] text-[#176b39]' => $tarefa->status,
                                    'bg-[#f0ebe2] text-[#536b7b]' => !$tarefa->status,
                                ])>
                                    <span class="h-2 w-2 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                                    {{ $tarefa->status ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if ($tarefa->editavel && auth()->user()->can('editar-modulo', 'tarefas'))
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
        <p id="mensagemSemResultadoTarefas" @if ($tarefas->isNotEmpty()) hidden @endif class="px-4 py-8 text-center text-sm text-[#536b7b]">
            {{ $tarefas->isEmpty() ? 'Nenhuma tarefa cadastrada no momento.' : 'Nenhuma tarefa encontrada.' }}
        </p>
    </div>
    @include('relatorios.partials.paginacao-catalogo', [
        'idFormulario' => 'filtrosTarefas',
        'idTabela' => 'tabelaTarefas',
        'idMensagemVazia' => 'mensagemSemResultadoTarefas',
        'rotulo' => 'tarefa',
    ])
</div>

@can('editar-modulo', 'tarefas')
@include('processos.partials.modal-tarefa')
@endcan

<script>
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
        preencherUnidadesCadastro(document.querySelector('#taskModal form'), dados.unidades);

        const titulo = document.getElementById('modalTituloTarefa');
        if (titulo) titulo.textContent = 'Editar tarefa';

        openModal(modalId);
        sincronizarModalTarefa(dados.icone || '📋', dados.cor_icone || 'original');
    }

</script>
@endsection
