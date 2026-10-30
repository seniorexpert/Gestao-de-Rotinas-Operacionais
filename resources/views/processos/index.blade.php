@extends('layouts.app')

@section('content')
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Processos</title>
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
        <h1 class="text-3xl font-bold text-[#1e2d3d] mb-1">Iniciar Processos</h1>
        <p class="sans-text text-gray-500 mb-8">Escolha o que você precisa começar agora</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <a href="{{ route('processos.gestao-rotinas') }}" 
            class="group bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-64 border-opacity-60">
                <div>
                    <div class="w-16 h-16 rounded-full bg-[#f4f1ea] group-hover:bg-[#1e2d3d] transition-colors duration-300 flex items-center justify-center mb-6">
                        <i class="fa-regular fa-clipboard text-[#1e2d3d] group-hover:text-white text-2xl transition-colors duration-300"></i>
                    </div>

                    <h2 class="text-2xl font-bold text-[#1e2d3d] mb-2 leading-tight">
                        Cadastros de Gestão de Rotinas
                    </h2>
                    <p class="sans-text text-gray-500 text-sm">
                        Cadastre rotinas, horários, etapas e responsáveis.
                    </p>
                </div>

                <div class="sans-text text-[#b39359] font-bold text-xs tracking-wider flex items-center gap-2 mt-4">
                    ACESSAR <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                </div>
            </a>

            <a href="#" 
               class="group bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-64">
                <div>
                    <div class="w-16 h-16 rounded-full bg-[#f4f1ea] group-hover:bg-[#1e2d3d] transition-colors duration-300 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-lightbulb text-2xl text-[#1e2d3d] group-hover:text-white transition-colors duration-300"></i>
                    </div>

                    <h2 class="text-2xl font-bold text-[#1e2d3d] mb-2">
                        Tarefas Emergenciais
                    </h2>
                    <p class="sans-text text-gray-500 text-sm">
                        Abra uma tarefa que precisa ser feita agora.
                    </p>
                </div>

                <div class="sans-text text-[#b39359] font-bold text-xs tracking-wider flex items-center gap-2 mt-4">
                    ACESSAR <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                </div>
            </a>

        </div>
    </div>

</body>
</html>
@endsection
