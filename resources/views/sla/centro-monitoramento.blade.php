@extends('layouts.app')

@section('title', 'Centro de Monitoramento')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 text-slate-800 sm:px-6">
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-serif text-3xl font-bold">Centro de Monitoramento</h1>
        <button type="button" onclick="abrirModalAtribuir()"
            class="inline-flex items-center gap-2 rounded-xl bg-[#1e293b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
            <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
            Atribuir tarefa
        </button>
    </div>

    <div id="painelMonitoramento">
        <section class="mb-6 rounded-2xl border border-amber-200/80 bg-white p-5 sm:p-6">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Rotinas Diárias</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $data }} · Horário de Brasília</p>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    'Previstas' => $execucoes->count(),
                    'Pendentes' => $execucoes->where('status', 'pendente')->count(),
                    'Em andamento' => $execucoes->where('status', 'em_andamento')->count(),
                    'Concluídas' => $execucoes->where('status', 'concluida')->count(),
                    'Não realizadas' => $execucoes->where('status', 'nao_realizada')->count(),
                ] as $nome => $total)
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-600">{{ $nome }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $total }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-12">
            <section class="rounded-2xl border border-amber-200/80 bg-white p-5 sm:p-6 lg:col-span-5">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">SLA das tarefas em aberto</h2>
                    <span class="text-xs text-slate-600">{{ $abertas->count() }} {{ $abertas->count() === 1 ? 'execução' : 'execuções' }}</span>
                </div>

                <dl class="space-y-3">
                    @foreach ([
                        'noPrazo' => ['No prazo', 'border-emerald-100 bg-emerald-50 text-emerald-900'],
                        'expirando' => ['Expirando', 'border-amber-100 bg-amber-50 text-amber-900'],
                        'atrasado' => ['Atrasadas', 'border-rose-100 bg-rose-50 text-rose-900'],
                    ] as $situacao => [$nome, $estilo])
                        @php($quantidade = $situacoes->get($situacao, 0))
                        <div data-sla="{{ $situacao }}" class="flex items-center justify-between gap-4 rounded-xl border px-4 py-3 {{ $estilo }}">
                            <dt class="font-medium">{{ $nome }}</dt>
                            <dd class="text-right">
                                <span class="text-lg font-semibold tabular-nums">{{ $abertas->isEmpty() ? '—' : round(100 * $quantidade / $abertas->count()).'%' }}</span>
                                <span class="block text-xs">{{ $quantidade }} {{ $quantidade === 1 ? 'tarefa' : 'tarefas' }}</span>
                            </dd>
                        </div>
                    @endforeach
                    @if ($situacoes->get('semDados', 0))
                        <div class="flex justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <dt>Sem dados de SLA</dt>
                            <dd>{{ $situacoes->get('semDados') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="rounded-2xl border border-amber-200/80 bg-white p-5 sm:p-6 lg:col-span-7">
                <h2 class="mb-5 text-lg font-semibold">Produtividade</h2>
                <div class="space-y-3">
                    @forelse ($produtividades as $prod)
                        <button type="button" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-left hover:border-slate-400 hover:bg-slate-50"
                            onclick="abrirModalDetalhesProd(@js((string) $prod->colaborador_id), @js($prod->colaborador_nome), @js($prod->total_tarefas), @js($prod->unidades))">
                            <span class="flex flex-wrap items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <strong class="block break-words">{{ $prod->colaborador_nome }}</strong>
                                    <span class="text-xs text-slate-600">{{ $prod->unidades }}</span>
                                </span>
                                <span class="text-right text-sm font-medium">{{ $prod->concluidas }} de {{ $prod->total_tarefas }} concluídas</span>
                            </span>
                            <span class="mt-3 flex items-center gap-3">
                                <progress class="h-2 w-full accent-[#1e293b]" max="100" value="{{ $prod->percentual }}" aria-label="Tarefas concluídas"></progress>
                                <span class="shrink-0 text-xs font-semibold tabular-nums">{{ $prod->percentual }}%</span>
                            </span>
                        </button>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-600">Nenhuma execução prevista para hoje.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>

@include('sla.partials.modal-atribuir-tarefa')
@include('sla.partials.modal-detalhes-produtividade')

<script>
    (() => {
        let atualizando = false;
        const modais = ['modalAtribuirTarefa', 'modalDetalhesProdutividade'].map(id => document.getElementById(id));
        const modalAberto = () => modais.some(modal => !modal.classList.contains('hidden'));

        setInterval(async () => {
            if (document.hidden || atualizando || modalAberto()) return;
            atualizando = true;
            try {
                const resposta = await fetch(location.href, {
                    cache: 'no-store',
                    headers: { 'X-Atualizacao-Automatica': '1' },
                });
                if (!resposta.ok || resposta.redirected || modalAberto()) return;

                const pagina = new DOMParser().parseFromString(await resposta.text(), 'text/html');
                if (modalAberto()) return;
                for (const id of ['painelMonitoramento', 'modalProdListaTarefas']) {
                    const atual = document.getElementById(id);
                    const novo = pagina.getElementById(id);
                    if (novo && !atual.contains(document.activeElement) && atual.innerHTML !== novo.innerHTML) {
                        atual.replaceChildren(...novo.childNodes);
                    }
                }
            } catch {
                // A próxima atualização tenta consultar o painel novamente.
            } finally {
                atualizando = false;
            }
        }, 30000);
    })();
</script>
@endsection
