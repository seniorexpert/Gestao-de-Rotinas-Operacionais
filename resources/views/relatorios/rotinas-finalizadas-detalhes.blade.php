@extends('layouts.app')

@section('title', 'Detalhes da Rotina Finalizada')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="text-[#17384b]">
        <a href="{{ $voltar }}" class="mb-3 inline-flex items-center gap-2 rounded-lg px-2 py-1 text-sm font-semibold transition-colors hover:bg-[#f8f5ef]">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar
        </a>

        <header class="mb-4">
            <h1 class="text-2xl font-bold leading-tight sm:text-[1.85rem]" style="font-family: 'Fraunces', Georgia, serif;">
                Detalhes do Registro
            </h1>
            <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs font-bold">
                <span class="rounded-full bg-emerald-50 px-3.5 py-1 text-emerald-800">Concluída</span>
                <span class="rounded-lg bg-[#f8f5ef] px-2.5 py-1 tabular-nums text-[#536b7b]">ID #{{ $registro->id_execucao }}</span>
            </div>
        </header>

        <div class="grid items-start gap-4 lg:grid-cols-2">
            <section aria-labelledby="tarefa-finalizada" class="min-w-0 rounded-[1.25rem] border-2 border-[#e8d6bf] bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
                <h2 id="tarefa-finalizada" class="mb-4 flex items-center gap-3 border-b border-[#efe6d9] pb-3 text-lg font-bold sm:text-xl">
                    <x-icone-tarefa :icone="$registro->icone" :cor="$registro->cor_icone" class="h-8 w-8 text-2xl" />
                    <span class="min-w-0 break-words">{{ $registro->tarefa }}</span>
                </h2>

                <dl class="grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2">
                    <div>
                        <dt class="mb-1 text-xs text-[#536b7b]">Finalizado em</dt>
                        <dd class="font-bold tabular-nums">
                            <time datetime="{{ $registro->finalizado_em->toIso8601String() }}">
                                {{ $registro->finalizado_em->format('d/m/Y · H:i') }}
                                <span class="block text-xs font-normal text-[#536b7b]">{{ ucfirst($registro->finalizado_em->dayName) }}</span>
                            </time>
                        </dd>
                    </div>
                    <div>
                        <dt class="mb-1 text-xs text-[#536b7b]">Programado para</dt>
                        <dd class="font-bold tabular-nums">
                            <time datetime="{{ $registro->programado_em->toIso8601String() }}">{{ $registro->programado_em->format('d/m/Y · H:i') }}</time>
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-[#536b7b]">Quem (morador)</dt>
                        <dd class="break-words font-bold">{{ $registro->morador }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-[#536b7b]">Onde</dt>
                        <dd class="break-words font-bold">{{ $registro->local }}</dd>
                        @if (filled($registro->empresa))
                            <dd class="break-words text-xs text-[#536b7b]">{{ $registro->empresa }}</dd>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-[#536b7b]">Colaborador(a) responsável</dt>
                        <dd class="break-words font-bold">{{ $registro->responsavel ?? 'Não registrado' }}</dd>
                        @if ($registro->grupo_acesso)
                            <dd class="text-xs text-[#536b7b]">{{ $registro->grupo_acesso }}</dd>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <dt class="mb-1 text-xs text-[#536b7b]">Colaborador(a) que finalizou</dt>
                        <dd class="break-words font-bold">{{ $registro->finalizador }}</dd>
                        @if ($registro->grupo_finalizador)
                            <dd class="text-xs text-[#536b7b]">{{ $registro->grupo_finalizador }}</dd>
                        @endif
                    </div>
                </dl>

                <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-[#efe6d9] pt-3 text-sm">
                    <div>
                        <dt class="mb-1 text-xs text-[#536b7b]">Tempo de execução</dt>
                        <dd @class(['font-bold tabular-nums', 'text-rose-700' => $registro->acima_do_planejado])>{{ $registro->tempo_execucao ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="mb-1 text-xs text-[#536b7b]">Tempo planejado</dt>
                        <dd class="font-bold tabular-nums">{{ $registro->tempo_planejado ?? '—' }}</dd>
                    </div>
                </dl>

                @if (filled($registro->descricao) && $registro->descricao !== $registro->tarefa)
                    <div class="mt-4 border-t border-[#efe6d9] pt-3">
                        <h3 class="mb-1 text-xs font-semibold text-[#536b7b]">Instrução de trabalho</h3>
                        <p class="whitespace-pre-wrap break-words text-sm">{{ $registro->descricao }}</p>
                    </div>
                @endif

                @if (filled($registro->observacao))
                    <div class="mt-4 border-t border-[#efe6d9] pt-3">
                        <h3 class="mb-1 text-xs font-semibold text-[#536b7b]">Observação da rotina</h3>
                        <p class="whitespace-pre-wrap break-words text-sm">{{ $registro->observacao }}</p>
                    </div>
                @endif
            </section>

            <section aria-labelledby="titulo-comprovacao" class="min-w-0 rounded-[1.25rem] border-2 border-[#e8d6bf] bg-white p-5 shadow-sm sm:rounded-3xl sm:p-6">
                <h2 id="titulo-comprovacao" class="mb-3 text-lg font-bold">Foto de comprovação</h2>

                @if ($foto)
                    <button type="button" aria-label="Ampliar foto de comprovação" aria-haspopup="dialog" aria-controls="foto-finalizada-ampliada"
                            onclick="document.getElementById('foto-finalizada-ampliada').showModal()"
                            class="relative block w-full cursor-zoom-in overflow-hidden rounded-xl border border-[#e8d6bf] bg-[#f8f5ef] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]">
                        <img src="{{ $foto }}" alt="Foto de comprovação de {{ $registro->tarefa }}" class="max-h-[26rem] w-full object-contain">
                        <span class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md" aria-hidden="true">
                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                        </span>
                    </button>
                @else
                    <p class="rounded-xl border border-dashed border-[#e8d6bf] bg-[#f8f5ef] px-4 py-9 text-center text-sm text-[#536b7b]">
                        Nenhuma foto de comprovação disponível.
                    </p>
                @endif

                <div class="mt-4 border-t border-[#efe6d9] pt-3">
                    <h3 class="mb-1 text-sm font-bold">Justificativa da finalização</h3>
                    <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ filled($registro->justificativa) ? $registro->justificativa : 'Sem comentário registrado.' }}</p>
                </div>
            </section>
        </div>

        @if ($foto)
            <dialog id="foto-finalizada-ampliada" aria-label="Foto de comprovação em tela cheia"
                    onclick="if (event.target === this) this.close()"
                    class="w-[min(94vw,72rem)] max-w-none overflow-visible rounded-xl bg-transparent p-0 backdrop:bg-black/85">
                <figure class="relative flex flex-col items-center gap-2 rounded-xl bg-[#1e2d3d] p-3">
                    <form method="dialog" class="absolute right-3 top-3">
                        <button type="submit" autofocus aria-label="Fechar a foto"
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#17384b] shadow-sm hover:bg-[#f8f5ef]">
                            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                        </button>
                    </form>
                    <img src="{{ $foto }}" loading="lazy" alt="Foto de comprovação de {{ $registro->tarefa }}" class="max-h-[80vh] max-w-full rounded-lg object-contain">
                    <figcaption class="text-center text-xs font-semibold tabular-nums text-white">
                        Registro #{{ $registro->id_execucao }} · {{ $registro->finalizado_em->format('d/m/Y H:i') }}
                    </figcaption>
                </figure>
            </dialog>
        @endif
    </div>
@endsection
