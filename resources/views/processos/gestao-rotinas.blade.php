@extends('layouts.app')

@section('title', 'Cadastros de Gestão de Rotinas')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .sans-text {
            font-family: system-ui, -apple-system, sans-serif;
        }
    </style>
@endpush

@section('content')
    <div>
        <a href="{{ route('processos.index') }}" aria-label="Voltar para Iniciar Processos"
           class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#193b50] hover:bg-[#f4f1ea] focus-visible:outline focus-visible:outline-2">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>

        <div class="mb-[30px]">
            <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] text-[#193b50] lg:text-[2rem]">Cadastros de Gestão de Rotinas</h1>
            <p class="mt-1 text-sm tracking-[0.012em] text-[#64748b] sm:text-[0.95rem]">Escolha o cadastro que deseja acessar</p>
        </div>

        @php
            $cadastros = [
                'rotinas' => ['Rotinas', 'calendar-days', 'Criar, consultar e editar rotinas, horários e colaboradores', 'relatorios.rotinas', 'rotinaModal'],
                'tarefas' => ['Tarefas', 'clipboard-list', 'Cadastrar instruções, tempo de duração e recursos utilizados', 'relatorios.tarefas', 'taskModal'],
                'ferramentas' => ['Ferramentas', 'engrenagem', 'Gerenciar equipamentos e ferramentas de apoio às tarefas', 'relatorios.ferramentas', 'ferramentaModal'],
                'locais' => ['Local', 'map-pin', 'Cadastrar e gerenciar os ambientes de realização das tarefas', 'relatorios.locais', 'localModal'],
            ];
            $editarProcessos = auth()->user()->can('editar-modulo', 'processos');
            $cadastros = array_filter($cadastros, fn ($modulo) => auth()->user()->can('visualizar-modulo', $modulo), ARRAY_FILTER_USE_KEY);
        @endphp

        <section aria-label="Cadastros disponíveis" class="grid w-full grid-cols-1 gap-4 md:grid-cols-2">
            @forelse ($cadastros as $modulo => [$nome, $icone, $descricao, $rota, $modal])
                @php($editar = $editarProcessos && auth()->user()->can('editar-modulo', $modulo))
                <x-cartao-iniciar-processo :url="route($rota)" :titulo="$nome" :descricao="$descricao" :icone="$icone"
                    :horizontal="true" :acao="$editar ? 'Cadastrar' : 'Consultar'" :aria-controls="$editar ? $modal : null"
                    :onclick="$editar ? 'event.preventDefault(); openModal('.Js::from($modal).')' : null" />
            @empty
                <p class="sans-text col-span-full text-sm text-slate-600">Nenhum cadastro está disponível para o seu usuário.</p>
            @endforelse
        </section>
    </div>

    @if ($editarProcessos)
        @can('editar-modulo', 'tarefas')
            @include('processos.partials.modal-tarefa')
        @endcan
        @can('editar-modulo', 'ferramentas')
            @include('processos.partials.modal-ferramenta')
        @endcan
        @can('editar-modulo', 'locais')
            @include('processos.partials.modal-local')
        @endcan
        @can('editar-modulo', 'rotinas')
            @include('processos.partials.modal-rotina')
            @include('processos.partials.modal-tarefa-rotina')
        @endcan
    @endif

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('hidden');
        }

        window.addEventListener('click', function(e) {
            const taskModal = document.getElementById('taskModal');
            const ferramentaModal = document.getElementById('ferramentaModal');
            const localModal = document.getElementById('localModal');
            const rotinaModal = document.getElementById('rotinaModal');
            const tarefaRotinaModal = document.getElementById('tarefaRotinaModal');

            if (e.target === taskModal) taskModal.classList.add('hidden');
            if (e.target === ferramentaModal) ferramentaModal.classList.add('hidden');
            if (e.target === localModal) localModal.classList.add('hidden');
            if (e.target === rotinaModal) rotinaModal.classList.add('hidden');
            if (e.target === tarefaRotinaModal) tarefaRotinaModal.classList.add('hidden');
        });
    </script>

@endsection
