@extends('layouts.app')

@section('title', 'Rotina de Acompanhamento')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
        <header class="mb-5 lg:mb-6">
            <h1 class="text-[1.8rem] font-semibold leading-tight text-[#1e2d3d] lg:text-[2rem]"
                style="font-family: 'Fraunces', Georgia, serif;">
                Rotina de Acompanhamento
            </h1>
            <p class="mt-1 text-sm text-slate-600 sm:text-[0.95rem]">
                Caixa · {{ $caixa }}
            </p>
        </header>

        @if (session('success'))
            <p class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"
               role="status">
                <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                {{ session('success') }}
            </p>
        @endif

        <section data-painel-acompanhamento aria-label="Rotina de Acompanhamento">
            @include('processos.acompanhamento.painel')
        </section>
    </div>

    @include('processos.acompanhamento.interacoes')
@endsection
