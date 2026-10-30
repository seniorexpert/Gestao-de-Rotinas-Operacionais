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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <button type="button" onclick="openModal('rotinaModal')" class="group bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex items-center justify-between text-left w-full">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-full bg-[#f4f1ea] text-[#1e2d3d] group-hover:bg-[#1e2d3d] group-hover:text-white transition-colors duration-300 flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-calendar-days text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#1e2d3d]">Rotinas</h3>
                        <p class="sans-text text-gray-500 text-xs mt-1">Criar, consultar e editar rotinas, horários e colaboradores</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#f4f1ea] text-gray-400 group-hover:bg-[#1e2d3d] group-hover:text-white flex items-center justify-center shrink-0 ml-4 group-hover:translate-x-1 transition-transform">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </div>
            </button>

            <button type="button" onclick="openModal('taskModal')" class="group bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex items-center justify-between text-left w-full">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-full bg-[#f4f1ea] text-[#1e2d3d] group-hover:bg-[#1e2d3d] group-hover:text-white transition-colors duration-300 flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-clipboard text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#1e2d3d]">Tarefas</h3>
                        <p class="sans-text text-gray-500 text-xs mt-1">Cadastrar instruções, tempo de duração e recursos utilizados</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#f4f1ea] text-gray-400 group-hover:bg-[#1e2d3d] group-hover:text-white flex items-center justify-center shrink-0 ml-4 group-hover:translate-x-1 transition-transform">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </div>
            </button>

            <button type="button" onclick="openModal('ferramentaModal')" class="group bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex items-center justify-between text-left w-full">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-full bg-[#f4f1ea] text-[#1e2d3d] group-hover:bg-[#1e2d3d] group-hover:text-white transition-colors duration-300 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-gear text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#1e2d3d]">Ferramentas</h3>
                        <p class="sans-text text-gray-500 text-xs mt-1">Gerenciar equipamentos e ferramentas de apoio às tarefas</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#f4f1ea] text-gray-400 group-hover:bg-[#1e2d3d] group-hover:text-white flex items-center justify-center shrink-0 ml-4 group-hover:translate-x-1 transition-transform">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </div>
            </button>

            <button type="button" onclick="openModal('localModal')" class="group bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex items-center justify-between text-left w-full">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-full bg-[#f4f1ea] text-[#1e2d3d] group-hover:bg-[#1e2d3d] group-hover:text-white transition-colors duration-300 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-location-dot text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-[#1e2d3d]">Local</h3>
                        <p class="sans-text text-gray-500 text-xs mt-1">Cadastrar e gerenciar os ambientes de realização das tarefas</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#f4f1ea] text-gray-400 group-hover:bg-[#1e2d3d] group-hover:text-white flex items-center justify-center shrink-0 ml-4 group-hover:translate-x-1 transition-transform">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </div>
            </button>

        </div>
    </div>

    @include('processos.partials.modal-tarefa')
    @include('processos.partials.modal-ferramenta')
    @include('processos.partials.modal-local')
    @include('processos.partials.modal-rotina')
    @include('processos.partials.modal-tarefa-rotina')

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