@extends('layouts.app')

@section('content')
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastros de Gestão de Rotinas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Georgia', serif;
            background-color: #fafafa;
        }
        .sans-text {
            font-family: system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body class="p-8 text-[#1e293b]">

    <div class="max-w-5xl mx-auto">
        <a href="{{ route('processos.index') }}" class="text-gray-600 hover:text-black text-xl mb-4 inline-block">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <h1 class="text-3xl font-bold text-[#1e2d3d] mb-1">Cadastros de Gestão de Rotinas</h1>
        <p class="sans-text text-gray-500 mb-8">Gerencie rotinas, tarefas, ferramentas e locais com eficiência e organização.</p>

        @php
            $cadastros = [
                'rotinas' => ['Rotinas', 'fa-calendar-days', 'relatorios.rotinas', 'rotinaModal'],
                'tarefas' => ['Tarefas', 'fa-list-check', 'relatorios.tarefas', 'taskModal'],
                'ferramentas' => ['Ferramentas', 'fa-wrench', 'relatorios.ferramentas', 'ferramentaModal'],
                'locais' => ['Locais', 'fa-location-dot', 'relatorios.locais', 'localModal'],
            ];
            $editarProcessos = auth()->user()->can('editar-modulo', 'processos');
            $cadastros = array_filter($cadastros, fn ($modulo) => auth()->user()->can('visualizar-modulo', $modulo), ARRAY_FILTER_USE_KEY);
        @endphp

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @forelse ($cadastros as $modulo => [$nome, $icone, $rota, $modal])
                @php($editar = $editarProcessos && auth()->user()->can('editar-modulo', $modulo))
                <a href="{{ route($rota) }}" @if ($editar) onclick="event.preventDefault(); openModal(@js($modal))" @endif
                   class="group flex w-full items-center gap-5 rounded-3xl border border-gray-100 bg-white p-6 text-left shadow-sm transition hover:shadow-md">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#f4f1ea] text-lg" aria-hidden="true">
                        <i class="fa-solid {{ $icone }}"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <strong class="block text-xl">{{ $nome }}</strong>
                        <span class="sans-text mt-1 block text-xs text-slate-600">{{ $editar ? 'Cadastrar' : 'Consultar' }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs text-[#886934]" aria-hidden="true"></i>
                </a>
            @empty
                <p class="sans-text col-span-full text-sm text-slate-600">Nenhum cadastro está disponível para o seu usuário.</p>
            @endforelse
        </div>
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

</body>
</html>
@endsection