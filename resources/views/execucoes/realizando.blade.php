@extends('layouts.app')

@section('title', 'Realizando - Senior Expert')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
@php($temOrientacoes = $execucao->descricao || $execucao->obs || $documentacao)

<div class="text-[#21394e]">
    <a href="{{ route('home', ['aba' => 'em_andamento']) }}" class="mb-2 inline-block rounded px-0.5 py-1 hover:text-[#64748b]" aria-label="Voltar para Rotinas Diárias">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    </a>

    <h1 class="mb-3 text-2xl font-bold sm:text-3xl" style="font-family: Fraunces, Georgia, serif">Detalhes da Tarefa</h1>
    <p class="mb-5 flex flex-wrap gap-2">
        <span class="rounded-full bg-[#e6eff2] px-3.5 py-1 text-xs font-semibold">Realizando</span>
        @if ($execucao->tipo_rotina === \App\Services\ExecucoesDiarias::TIPO_EMERGENCIAL)
            <span class="rounded-full bg-amber-100 px-3.5 py-1 text-xs font-semibold text-amber-900">Tarefa emergencial</span>
        @endif
    </p>

    <div @class(['mb-4 grid gap-4 lg:gap-6', 'lg:grid-cols-2' => $temOrientacoes])>
        <section class="min-w-0 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
            <h2 class="mb-4 flex items-center gap-2.5 border-b border-slate-100 pb-3 text-lg font-bold sm:text-xl">
                <x-icone-tarefa :icone="$execucao->icone" :cor="$execucao->cor_icone" class="h-7 w-7 text-2xl" />
                <span class="min-w-0 break-words">{{ $execucao->tarefa ?: 'Tarefa de rotina' }}</span>
            </h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
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

        @if ($temOrientacoes)
            <section class="min-w-0 space-y-3 rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
                @if ($execucao->descricao)
                    <div>
                        <h2 class="mb-2 text-sm font-bold sm:text-base">Instrução de trabalho</h2>
                        <p class="whitespace-pre-wrap break-words text-sm text-slate-600">{{ $execucao->descricao }}</p>
                    </div>
                @endif
                @if ($execucao->obs)
                    <div>
                        <h2 class="mb-2 text-sm font-bold sm:text-base">Observações</h2>
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
    </div>

    @if ($prazo)
        <p class="mb-4 text-sm text-[#64748b]">Limite para finalizar <strong class="font-semibold text-[#21394e]">{{ $prazo }}</strong></p>
    @endif

    @can('editar-modulo', 'rotinas_diarias')
    <div class="grid gap-2.5 lg:grid-cols-3 lg:gap-3">
        @foreach ([
            'finalizar' => ['Finalizar Tarefa', 'border-transparent bg-[#17384a] text-white shadow-sm hover:bg-[#25495c]'],
            'nao-realizada' => ['Não Pode Ser Realizada', 'border-[#d92318] bg-white text-[#b91c12] hover:bg-red-50'],
            'auxilio' => ['Solicitar Auxílio', 'border-[#d3ae74]/60 bg-white text-[#17384a] hover:bg-[#fcfbf8]'],
        ] as $acao => [$nome, $estilo])
            <a href="{{ route('execucoes.formulario', [$execucao->id_execucao, $acao]) }}"
               class="flex min-h-12 items-center justify-center rounded-xl border px-3 py-2 text-center text-sm font-bold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384a] sm:text-base {{ $estilo }}">
                {{ $nome }}
            </a>
        @endforeach
    </div>
    @endcan
</div>
@endsection
