@extends('layouts.app')

@section('title', 'Iniciar Processos')
@section('estilo-cabecalho', 'min-h-[50px] border-[#e8d6bf] py-1')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .sans-text {
            font-family: system-ui, -apple-system, sans-serif;
        }
    </style>
@endpush

@section('content')
    @php
        $podeAgendarEmergencial = auth()->user()->can('editar-modulo', 'processos') && auth()->user()->can('editar-modulo', 'rotinas');
    @endphp

    <div>
        <div class="mb-[30px]">
            <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] text-[#193b50] lg:text-[2rem]">Iniciar Processos</h1>
            <p class="mt-1 text-sm tracking-[0.012em] text-[#64748b] sm:text-[0.95rem]">Escolha o que você precisa começar agora</p>
        </div>

        <section aria-label="Processos disponíveis" class="grid w-full max-w-4xl grid-cols-1 gap-4 md:grid-cols-2">
            <x-cartao-iniciar-processo :url="route('processos.gestao-rotinas')" titulo="Cadastros de Gestão de Rotinas"
                descricao="Cadastre rotinas, horários, etapas e responsáveis." icone="clipboard-list" />

            @if ($podeAgendarEmergencial)
                <x-cartao-iniciar-processo titulo="Tarefas Emergenciais" descricao="Abra uma tarefa que precisa ser feita agora."
                    icone="sirene" class="cursor-pointer" aria-controls="modalRotinaEmergencial" onclick="abrirModal('modalRotinaEmergencial')" />
            @endif
        </section>
    </div>

    @if ($podeAgendarEmergencial)
        @include('processos.partials.modal-rotina-emergencial')
        @include('processos.partials.modal-tarefa-rotina-emergencial')
    @endif
@endsection
