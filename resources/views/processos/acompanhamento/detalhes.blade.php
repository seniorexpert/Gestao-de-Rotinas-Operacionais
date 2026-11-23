@extends('layouts.app')

@section('title', 'Verificação da Tarefa')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:py-8">
        <a href="{{ route('acompanhamento.index', $filtros) }}"
           class="mb-4 inline-flex items-center gap-2 rounded-lg border border-[#b39359]/30 bg-[#f4f1ea]/60 px-3 py-2 text-sm font-semibold text-[#1e2d3d] transition hover:bg-[#f4f1ea]">
            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
            Voltar
        </a>

        <h1 class="mb-1 text-2xl font-bold leading-tight text-[#1e2d3d] sm:text-[1.85rem]"
            style="font-family: 'Fraunces', Georgia, serif;">
            Verificação da Tarefa
        </h1>
        <p class="mb-3 text-sm text-slate-600">Caixa · {{ $caixa }}</p>

        <div class="mb-4 flex flex-wrap gap-2 text-xs font-bold">
            @if ($item->verificado_em)
                <span class="rounded-full bg-emerald-50 px-3.5 py-1 text-emerald-800">Verificada</span>
            @else
                @include('processos.acompanhamento.selo-sla', ['situacao' => $item->sla_acompanhamento])
                @if (! $item->nao_realizada)
                    <span class="rounded-full bg-rose-50 px-3.5 py-1 text-rose-800">Atraso da tarefa · {{ $item->atraso }}</span>
                @endif
            @endif
            @if ($item->nao_realizada)
                <span class="rounded-full bg-rose-50 px-3.5 py-1 text-rose-800">Não realizada</span>
            @endif
            <span class="rounded-full bg-[#f4f1ea] px-3.5 py-1 text-[#1e2d3d]">{{ $item->processo }}</span>
        </div>

        <div class="flex flex-col gap-4">
            <section aria-labelledby="tarefa-acompanhamento"
                     class="rounded-[1.25rem] border-2 border-[#b39359]/30 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
                @if ($item->nao_realizada)
                    @include('processos.acompanhamento.motivo-nao-realizada')
                @else
                    <p class="mb-3 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm font-semibold text-rose-800">
                        A tarefa de {{ $item->colaborador }} passou do prazo.
                    </p>
                @endif

                <h2 id="tarefa-acompanhamento"
                    class="mb-3 flex items-center gap-3 border-b border-slate-100 pb-3 text-lg font-bold text-[#1e2d3d] sm:text-xl">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f4f1ea] text-lg text-[#886934]"
                          aria-hidden="true">
                        <i class="fa-solid {{ $item->auxilio ? 'fa-handshake-angle' : 'fa-list-check' }}"></i>
                    </span>
                    <span class="min-w-0 break-words">{{ $item->tarefa }}</span>
                </h2>

                <dl class="grid grid-cols-2 gap-4 lg:grid-cols-3 lg:gap-x-6">
                    <div>
                        <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Horário</dt>
                        <dd class="text-sm font-bold tabular-nums text-[#1e2d3d] sm:text-base">
                            <time datetime="{{ $item->horario->toIso8601String() }}">
                                {{ $item->horario->isoFormat('HH:mm · dddd') }}
                                <span class="block text-xs font-medium text-slate-600">{{ $item->horario->format('d/m/Y') }}</span>
                            </time>
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Local</dt>
                        <dd class="break-words text-sm font-bold text-[#1e2d3d] sm:text-base">{{ $item->local }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Colaborador(a)</dt>
                        <dd class="break-words text-sm font-bold text-[#1e2d3d] sm:text-base">{{ $item->colaborador }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Quem (morador)</dt>
                        <dd class="break-words text-sm font-bold text-[#1e2d3d] sm:text-base">{{ $item->morador }}</dd>
                    </div>
                    <div class="col-span-2 min-w-0 border-t border-slate-100 pt-3 lg:col-span-1 lg:border-t-0 lg:pt-0">
                        <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Tarefa em dupla com</dt>
                        <dd class="break-words text-sm font-bold text-[#1e2d3d] sm:text-base">{{ $auxiliares->implode(', ') ?: 'Não é em dupla' }}</dd>
                    </div>
                </dl>
                @if (!$item->verificado_em)
                    <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-3 tabular-nums">
                        <div>
                            <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Recebida na caixa</dt>
                            <dd class="text-sm font-bold text-[#1e2d3d]">
                                <time datetime="{{ $item->recebido_em->toIso8601String() }}">{{ $item->recebido_em->format('d/m/Y H:i') }}</time>
                            </dd>
                        </div>
                        <div>
                            <dt class="mb-1 text-xs text-slate-600 sm:text-sm">Prazo para verificar</dt>
                            <dd class="text-sm font-bold text-[#1e2d3d]">
                                <time datetime="{{ $item->limite_acompanhamento->toIso8601String() }}">{{ $item->limite_acompanhamento->format('d/m/Y H:i') }}</time>
                            </dd>
                        </div>
                    </dl>
                @endif
            </section>

            @if ($item->verificado_em)
                <section aria-labelledby="verificacao-concluida"
                         class="rounded-[1.25rem] border-2 border-[#b39359]/30 bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
                    <h2 id="verificacao-concluida" class="mb-2 flex items-center gap-2 text-sm font-bold text-emerald-800 sm:text-base">
                        <i class="fa-regular fa-circle-check text-lg" aria-hidden="true"></i>
                        Verificação concluída
                    </h2>
                    <p class="mb-3 text-xs tabular-nums text-slate-600">
                        <time datetime="{{ $item->verificado_em->toIso8601String() }}">{{ $item->verificado_em->format('d/m/Y \à\s H:i') }}</time>
                        @if ($item->verificado_por)
                            · {{ $item->verificado_por }}
                        @endif
                    </p>
                    <p class="mb-1 text-xs font-bold text-[#1e2d3d]">Ação realizada</p>
                    <p class="whitespace-pre-wrap break-words text-sm leading-relaxed text-[#1e2d3d]">{{ $item->acao }}</p>
                </section>
            @else
                <form method="POST" action="{{ route('acompanhamento.store', ['id' => $item->id_execucao, 'tipo' => $item->nao_realizada ? 'nao_realizada' : null, ...$filtros]) }}"
                      class="flex flex-col gap-2">
                    @csrf
                    <label for="acao-realizada" class="text-sm font-bold text-[#1e2d3d] sm:text-base">Qual ação foi realizada?</label>
                    <textarea id="acao-realizada" name="acao" rows="3" maxlength="2000" required
                              placeholder="Conversei com a colaboradora e a tarefa foi feita às 15:20."
                              @error('acao') aria-invalid="true" aria-describedby="erro-acao" @enderror
                              class="w-full rounded-xl border-2 bg-white px-4 py-3 text-sm text-[#1e2d3d] shadow-sm placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#b39359]/50
                                     @error('acao') border-rose-300 @else border-[#b39359]/30 @enderror">{{ is_string(old('acao')) ? old('acao') : '' }}</textarea>
                    @error('acao')
                        <p id="erro-acao" role="alert" class="text-sm font-semibold text-rose-800">{{ $message }}</p>
                    @enderror

                    <button type="submit"
                            class="mt-1 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#1e2d3d] px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#2c4054] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1e2d3d] sm:text-base">
                        <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                        Concluir verificação
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
