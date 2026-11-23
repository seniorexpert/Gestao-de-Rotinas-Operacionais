@extends('layouts.app')

@section('title', 'Rotinas Diárias - Senior Expert')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
    <style>
        [data-caixa-rotinas] [hidden] { display: none; }
        .rotinas-titulo { font-family: Fraunces, Georgia, serif; }
    </style>
@endpush

@section('content')
<div data-caixa-rotinas class="mx-auto w-full max-w-3xl px-4 py-6 text-[#21394e] sm:px-6 sm:py-8">
    <h1 class="rotinas-titulo mb-5 text-2xl font-bold sm:text-3xl">Rotinas Diárias</h1>

    <label class="mb-4 flex h-11 items-center gap-3 rounded-xl border border-[#d3ae74]/40 bg-white px-3.5 focus-within:border-[#21394e] sm:mb-5">
        <i class="fa-solid fa-magnifying-glass text-sm text-[#64748b]" aria-hidden="true"></i>
        <span class="sr-only">Buscar rotina</span>
        <input id="buscaRotinas" type="search" placeholder="Buscar rotina..." class="min-w-0 flex-1 bg-transparent text-sm placeholder:text-[#64748b] focus:outline-none">
    </label>

    <div id="abasRotinas" role="tablist" aria-label="Situação das rotinas"
         class="mb-4 flex items-center gap-4 overflow-x-auto border-b border-[#d3ae74]/40 sm:mb-6 sm:gap-8">
        @foreach ($abas as $estado => $nome)
            <a id="aba-rotinas-{{ $estado }}" href="{{ route('home', ['aba' => $estado]) }}"
               role="tab" aria-controls="listaRotinas" aria-selected="{{ $aba === $estado ? 'true' : 'false' }}" tabindex="{{ $aba === $estado ? 0 : -1 }}"
               data-aba-rotina="{{ $estado }}"
               class="shrink-0 whitespace-nowrap border-b-2 border-transparent px-0.5 pb-2.5 pt-1 text-xs font-bold text-[#64748b] hover:text-[#21394e] focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 aria-selected:border-[#21394e] aria-selected:text-[#21394e] sm:text-sm">
                {{ $nome }}
            </a>
        @endforeach
    </div>

    <div id="listaRotinas" role="tabpanel" aria-labelledby="aba-rotinas-{{ $aba }}" class="space-y-3">
        @foreach ($rotinas as $rotina)
            @php
                $tarefa = $rotina->tarefa ?: 'Tarefa de rotina';
                $morador = trim($rotina->morador ?? '') ?: 'Não informado';
                $categoria = \Illuminate\Support\Str::ascii(mb_strtolower($tarefa.' '.$rotina->local));
                $iconeSalvo = trim((string) $rotina->icone);
                $icone = match (true) {
                    ! in_array($iconeSalvo, ['', '0', '1'], true) => $iconeSalvo,
                    str_contains($categoria, 'refeitorio') || str_contains($categoria, 'cozinha') || str_contains($categoria, 'aliment') => '🍽️',
                    str_contains($categoria, 'banheiro') || str_contains($categoria, 'higiene') || str_contains($categoria, 'banho') => '🛁',
                    str_contains($categoria, 'enfermagem') || str_contains($categoria, 'medic') => '💊',
                    default => '🚶',
                };
                $corIcone = trim((string) $rotina->cor_icone);
                $corIcone = preg_match('/\A#(?:[\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})\z/i', $corIcone) ? $corIcone : null;
                [$selo, $corSelo] = match (true) {
                    $rotina->status === 'nao_realizada' => ['Não realizada', 'bg-rose-100 text-rose-800'],
                    $rotina->status === 'concluida' => ['Concluída', 'bg-emerald-100 text-emerald-800'],
                    $rotina->situacao_sla === 'atrasado' => ['Em atraso', 'bg-rose-100 text-rose-800'],
                    $rotina->situacao_sla === 'expirando' => ['Expirando', 'bg-amber-100 text-amber-800'],
                    $rotina->status === 'em_andamento' => ['Realizando', 'bg-sky-100 text-[#21394e]'],
                    $rotina->situacao_sla === 'noPrazo' => ['No prazo', 'bg-emerald-100 text-emerald-800'],
                    default => ['Pendente', 'bg-slate-100 text-[#21394e]'],
                };
                $dados = [
                    'tarefa' => $tarefa,
                    'morador' => $morador,
                    'local' => $rotina->local,
                    'horario' => $rotina->horario,
                    'ferramenta' => $rotina->ferramenta,
                    'instrucao' => $rotina->descricao,
                    'obs' => $rotina->obs,
                    'documentacao' => $rotina->url_documentacao,
                    'icone' => $icone,
                    'corIcone' => $corIcone,
                    'selo' => $selo,
                    'corSelo' => $corSelo,
                    'prazo' => $rotina->prazo_execucao,
                    'sla' => $rotina->status === 'nao_realizada' ? null : (\App\Services\CalculadoraSla::ROTULOS[$rotina->situacao_sla] ?? 'Sem dados de SLA'),
                    'acao' => $rotina->acao_execucao,
                    'auxilio' => (bool) $rotina->auxilio,
                    'comentario' => $rotina->comentario_conclusao,
                    'justificativa' => $rotina->justificativa_nao_realizada,
                    'foto' => $rotina->foto_conclusao ? route('execucoes.foto', $rotina->id_execucao) : null,
                ];
            @endphp

            <button type="button" data-estado-rotina="{{ $rotina->status }}" data-aba-execucao="{{ $rotina->aba }}" data-busca="{{ $tarefa }} {{ $rotina->local }} {{ $morador }}"
                    @if ($aba !== 'todas' && $aba !== $rotina->aba) hidden @endif
                    @if ($rotina->status === 'em_andamento')
                        onclick="location.assign(@js(route('execucoes.realizando', $rotina->id_execucao)))"
                    @else
                        aria-haspopup="dialog" onclick="abrirModalDetalhes(@js($dados))"
                    @endif
                    class="group flex w-full items-center rounded-[1.25rem] border-2 border-[#d3ae74]/35 bg-white px-2.5 py-1.5 text-left shadow-sm transition-colors hover:border-[#21394e]/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:px-4">
                <span class="mr-2.5 flex h-12 w-10 shrink-0 items-center justify-center text-3xl leading-none transition-transform group-hover:scale-105 sm:mr-4 sm:h-14 sm:w-14 sm:text-[2.75rem]" aria-hidden="true">
                    <span class="relative isolate inline-flex">{{ $icone }}
                        @if ($corIcone)
                            <span class="pointer-events-none absolute inset-0 mix-blend-color" style="background-color: {{ $corIcone }}"></span>
                        @endif
                    </span>
                </span>
                <span class="min-w-0 flex-1 py-1">
                    <strong class="block truncate text-[1.05rem] leading-tight sm:text-lg" title="{{ $tarefa }}">{{ $tarefa }}</strong>
                    <span class="mt-1 flex flex-col gap-0.5 text-xs leading-snug sm:flex-row sm:flex-wrap sm:gap-x-3.5 sm:text-sm">
                        <span class="flex min-w-0 items-center gap-1">
                            <i class="fa-solid fa-location-dot shrink-0 text-[#b18b50]" aria-hidden="true"></i>
                            <span class="truncate" title="{{ $rotina->local }}"><b>Onde:</b> {{ $rotina->local }}</span>
                        </span>
                        <span class="flex min-w-0 items-center gap-1">
                            <i class="fa-regular fa-user shrink-0 text-[#b18b50]" aria-hidden="true"></i>
                            <span class="truncate" title="{{ $morador }}"><b>Morador(a):</b> {{ $morador }}</span>
                        </span>
                    </span>
                    <span class="mt-1 block text-[0.82rem] font-bold tabular-nums sm:text-sm" title="Horário previsto">{{ $rotina->horario }}</span>
                </span>
                <span class="ml-1.5 flex shrink-0 flex-col items-end justify-between self-stretch py-1 sm:ml-3">
                    <i class="fa-solid fa-chevron-right mr-0.5 text-xs text-[#64748b]" aria-hidden="true"></i>
                    <span class="mt-1.5 whitespace-nowrap rounded-full px-2 py-1 text-[0.65rem] font-bold sm:px-3 sm:text-xs {{ $corSelo }}">{{ $selo }}</span>
                </span>
            </button>
        @endforeach

        <p id="rotinasAbaVazia" role="status"
           @if ($rotinas->contains(fn ($rotina) => $aba === 'todas' || $rotina->aba === $aba)) hidden @endif
           class="rounded-[1.25rem] border border-dashed border-[#d3ae74]/50 px-4 py-9 text-center text-sm text-[#64748b]">
            {{ $rotinas->isEmpty() ? 'Nenhuma rotina atribuída a você para hoje.' : 'Nenhuma rotina nesta situação.' }}
        </p>
    </div>
</div>

@include('sla.partials.modal-detalhes-rotina')

<script>
    (() => {
        const lista = document.getElementById('listaRotinas');
        const abaLista = document.getElementById('abasRotinas');
        const abas = Array.from(abaLista.querySelectorAll('[role="tab"]'));
        const busca = document.getElementById('buscaRotinas');
        const vazio = document.getElementById('rotinasAbaVazia');
        const mensagemVazia = vazio.textContent.trim();
        const normalizar = texto => texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
        const cartoes = Array.from(lista.querySelectorAll('[data-estado-rotina]'), elemento => ({
            elemento,
            estado: elemento.dataset.abaExecucao,
            texto: normalizar(elemento.dataset.busca),
        }));
        let abaAtiva = abas.find(aba => aba.getAttribute('aria-selected') === 'true');

        function atualizarLista() {
            const termo = normalizar(busca.value.trim());
            const estado = abaAtiva.dataset.abaRotina;
            let temResultado = false;

            for (const { elemento, estado: situacao, texto } of cartoes) {
                const visivel = (estado === 'todas' || situacao === estado) && texto.includes(termo);
                elemento.hidden = !visivel;
                temResultado ||= visivel;
            }
            vazio.hidden = temResultado;
            vazio.textContent = termo ? 'Nenhuma rotina encontrada para essa busca.' : mensagemVazia;
        }

        function selecionarAba(aba) {
            abaAtiva.setAttribute('aria-selected', 'false');
            abaAtiva.tabIndex = -1;
            abaAtiva = aba;
            aba.setAttribute('aria-selected', 'true');
            aba.tabIndex = 0;
            lista.setAttribute('aria-labelledby', aba.id);
            const url = new URL(location.href);
            url.searchParams.set('aba', aba.dataset.abaRotina);
            history.replaceState(null, '', url);
            atualizarLista();
        }

        for (const aba of abas) {
            aba.addEventListener('click', evento => {
                if (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
                evento.preventDefault();
                selecionarAba(aba);
            });
        }

        abaLista.addEventListener('keydown', evento => {
            const indice = abas.indexOf(document.activeElement);
            const destinos = {
                ArrowLeft: (indice + abas.length - 1) % abas.length,
                ArrowRight: (indice + 1) % abas.length,
                Home: 0,
                End: abas.length - 1,
            };
            const destino = destinos[evento.key];
            if (indice < 0 || !Number.isInteger(destino)) return;
            evento.preventDefault();
            selecionarAba(abas[destino]);
            abas[destino].focus();
        });

        busca.addEventListener('input', atualizarLista);
        atualizarLista();
    })();
</script>
@endsection
