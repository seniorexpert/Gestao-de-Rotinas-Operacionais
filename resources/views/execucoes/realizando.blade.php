@extends('layouts.app')

@section('title', 'Realizando - Senior Expert')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="mx-auto w-full max-w-[38rem] px-4 py-6 text-[#21394e] sm:px-6">
    <a href="{{ route('home', ['aba' => 'em_andamento']) }}" class="mb-2 inline-block rounded px-0.5 py-1 hover:text-[#64748b]" aria-label="Voltar para Rotinas Diárias">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    </a>

    <h1 class="mb-3 text-2xl font-bold sm:text-3xl" style="font-family: Fraunces, Georgia, serif">Detalhes da Tarefa</h1>
    <p class="mb-4"><span class="inline-block rounded-full bg-sky-100 px-3.5 py-1 text-xs font-bold">Realizando</span></p>

    <section class="mb-4 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
        <h2 class="mb-4 border-b border-slate-100 pb-3 text-lg font-bold sm:text-xl">{{ $execucao->tarefa ?: 'Tarefa de rotina' }}</h2>
        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="mb-1 text-xs text-[#64748b]">Quando</dt>
                <dd class="font-bold">{{ $execucao->horario }}</dd>
            </div>
            <div>
                <dt class="mb-1 text-xs text-[#64748b]">Onde</dt>
                <dd class="break-words font-bold">{{ $execucao->local }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="mb-1 text-xs text-[#64748b]">Morador</dt>
                <dd class="break-words font-bold">{{ $execucao->morador ?: 'Não informado' }}</dd>
            </div>
            @if ($execucao->ferramenta)
                <div class="col-span-2 border-t border-slate-100 pt-3">
                    <dt class="mb-2 text-xs text-[#64748b]">Ferramentas / Materiais</dt>
                    <dd class="inline-block rounded-xl border border-[#d3ae74]/35 bg-[#fcfbf8] px-3 py-1.5 font-semibold">{{ $execucao->ferramenta }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($execucao->descricao || $execucao->obs || $documentacao)
        <section class="mb-4 space-y-3 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
            @if ($execucao->descricao)
                <div>
                    <h2 class="mb-1 text-sm font-bold">Instrução de trabalho</h2>
                    <p class="whitespace-pre-wrap break-words text-sm text-slate-600">{{ $execucao->descricao }}</p>
                </div>
            @endif
            @if ($execucao->obs)
                <div>
                    <h2 class="mb-1 text-sm font-bold">Observações</h2>
                    <p class="whitespace-pre-wrap break-words text-sm text-slate-600">{{ $execucao->obs }}</p>
                </div>
            @endif
            @if ($documentacao)
                <a href="{{ $documentacao }}" class="inline-flex items-center gap-2 text-sm font-semibold underline underline-offset-2">
                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i> Ver documentação
                </a>
            @endif
        </section>
    @endif

    @if ($prazo)
        <p class="mb-4 text-sm text-[#64748b]">Limite para finalizar <strong class="font-semibold text-[#21394e]">{{ $prazo }}</strong></p>
    @endif

    <div class="grid gap-2.5">
        @foreach ([
            'finalizar' => ['Finalizar Tarefa', 'bg-[#2d5a27] hover:bg-[#23471f]'],
            'nao-realizada' => ['Não Pode Ser Realizada', 'bg-[#5c2328] hover:bg-[#4a1c20]'],
            'auxilio' => ['Solicitar Auxílio', 'bg-[#5d461e] hover:bg-[#4b3818]'],
        ] as $acao => [$nome, $cor])
            <a href="{{ route('execucoes.formulario', [$execucao->id_execucao, $acao]) }}" class="rounded-xl px-3 py-3.5 text-center text-sm font-bold text-white shadow-sm transition-colors {{ $cor }}">{{ $nome }}</a>
        @endforeach
    </div>
</div>
@endsection
