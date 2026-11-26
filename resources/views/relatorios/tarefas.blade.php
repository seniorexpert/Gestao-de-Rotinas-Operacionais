@extends('layouts.app')

@section('title', 'Relatório de Tarefas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $classeCampo = 'h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]';
    $classeBotao = 'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#17384b] text-sm font-semibold text-white transition-colors hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';

    $podeEditar = auth()->user()->can('editar-modulo', 'tarefas');
    $registros = $tarefas->map(function (stdClass $tarefa) use ($podeEditar): array {
        $duracao = $tarefa->tempo_realizar_tarefa;
        [$horas, $minutos, $segundos] = array_pad(explode(':', $duracao ?? ''), 3, 0);
        $icone = trim((string) $tarefa->icone) ?: '📋';
        $corIcone = trim((string) $tarefa->cor_icone) ?: 'original';

        return [
            'id' => $tarefa->id_tarefa,
            'nome' => $tarefa->tarefa ?? 'Sem nome',
            'icone' => $icone,
            'cor_icone' => $corIcone,
            'ativo' => (bool) $tarefa->status,
            'campos' => [
                'Duração' => $duracao ? substr($duracao, 0, 5) : 'Não informada',
                'Ferramentas' => $tarefa->ferramenta ?? '—',
                'Unidades' => $tarefa->unidades->pluck('empresa')->join(', ') ?: 'Sem unidade',
            ],
            'edicao' => $podeEditar && $tarefa->editavel ? [
                'id' => $tarefa->id_tarefa,
                'nome' => $tarefa->tarefa,
                'tempo' => ((int) $horas * 3600 + (int) $minutos * 60 + (int) $segundos) / 60,
                'status' => $tarefa->status ? 'Ativa' : 'Inativa',
                'descricao' => $tarefa->descricao,
                'id_ferramenta' => $tarefa->id_ferramenta,
                'unidades' => $tarefa->ids_empresas,
                'icone' => $icone,
                'cor_icone' => $corIcone,
            ] : null,
        ];
    });
@endphp

<div class="min-w-0 text-[#17384b]">
    <div class="mb-5 sm:mb-6">
        <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Relatório de Tarefas</h1>
        <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Visualize, busque e gerencie as tarefas cadastradas no sistema</p>
    </div>

    <form id="filtrosTarefas" action="{{ route('relatorios.tarefas') }}" method="GET" role="search" aria-label="Filtros de tarefas"
          class="mb-4 grid min-w-0 items-end gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_auto_auto]">
        <div class="min-w-0">
            <label for="filtroNomeTarefa" class="mb-1 block text-[0.8125rem] font-semibold">Nome da Tarefa</label>
            <input type="search" id="filtroNomeTarefa" placeholder="Buscar pelo nome..." class="{{ $classeCampo }} min-w-0 placeholder:text-[#64748b]">
        </div>
        <div class="flex min-w-0 items-end gap-2 lg:contents">
            <div class="min-w-0 flex-1">
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
            <button type="submit" title="Buscar tarefas" aria-label="Buscar tarefas" class="{{ $classeBotao }} w-11">
                <i class="fa-solid fa-magnifying-glass text-base" aria-hidden="true"></i>
            </button>
        </div>
        @if ($podeEditar)
            <button type="button" onclick="abrirModalNovo('taskModal')" aria-haspopup="dialog" class="{{ $classeBotao }} w-full px-4 sm:col-span-2 lg:col-span-1 lg:w-auto">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo registro
            </button>
        @endif
    </form>

    @include('relatorios.partials.listagem-catalogo', [
        'idTabela' => 'tabelaTarefas',
        'idCartoes' => 'cartoesTarefas',
        'idMensagemVazia' => 'mensagemSemResultadoTarefas',
        'rotuloNome' => 'Nome da Tarefa',
        'rotuloLista' => 'Tarefas encontradas',
        'colunas' => ['Duração' => 'w-28', 'Ferramentas' => 'w-36', 'Unidades' => 'w-36'],
        'larguraTabela' => 'min-w-[55rem]',
        'mensagemVazia' => $registros->isEmpty() ? 'Nenhuma tarefa cadastrada no momento.' : 'Nenhuma tarefa encontrada.',
    ])

    @include('relatorios.partials.paginacao-catalogo', [
        'idFormulario' => 'filtrosTarefas',
        'idTabela' => 'tabelaTarefas',
        'idCartoes' => 'cartoesTarefas',
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
