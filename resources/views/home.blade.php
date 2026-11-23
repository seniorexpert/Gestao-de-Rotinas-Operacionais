@extends('layouts.app')

@section('title', 'Rotinas - Senior Expert')

@section('content')
<div class="max-w-6xl mx-auto p-6 bg-white min-h-screen font-sans text-gray-800">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-800 font-serif">Rotinas</h1>
        <p class="text-sm text-gray-500 mt-1">Acompanhe e gerencie suas tarefas do dia</p>
    </div>

    <div class="flex items-center gap-6 border-b border-gray-200 mb-6 text-sm">
        <button type="button" data-estado="" aria-pressed="true" onclick="mudarAba(this)" class="aba-btn pb-3 border-b-2 border-emerald-800 font-semibold text-emerald-900 transition">
            Todas
        </button>
        <button type="button" data-estado="em_andamento" aria-pressed="false" onclick="mudarAba(this)" class="aba-btn pb-3 text-gray-500 hover:text-gray-700 font-medium transition border-b-2 border-transparent">
            Em andamento
        </button>
        <button type="button" data-estado="concluida" aria-pressed="false" onclick="mudarAba(this)" class="aba-btn pb-3 text-gray-500 hover:text-gray-700 font-medium transition border-b-2 border-transparent">
            Concluídas
        </button>
    </div>

    <div class="space-y-4">
        @forelse($rotinas as $rotina)
            @php
                $iconeSalvo = trim((string) ($rotina->icone ?? ''));
                if ($iconeSalvo !== '') {
                    $icone = $iconeSalvo;
                } else {
                    $localLower = mb_strtolower($rotina->local ?? '', 'UTF-8');
                    if (str_contains($localLower, 'refeitório') || str_contains($localLower, 'cozinha')) {
                        $icone = '🍽️';
                    } elseif (str_contains($localLower, 'banheiro') || str_contains($localLower, 'higiene')) {
                        $icone = '🛁';
                    } elseif (str_contains($localLower, 'enfermagem') || str_contains($localLower, 'quarto')) {
                        $icone = '💊';
                    } else {
                        $icone = '🚶';
                    }
                }

                $corIcone = trim((string) ($rotina->cor_icone ?? 'original'));
                $hexCor = ($corIcone !== '' && $corIcone !== 'original' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $corIcone))
                    ? $corIcone
                    : null;

                $ativa = (bool) $rotina->status;
                $estado = match ($rotina->estado) {
                    'pendente' => 'Pendente',
                    'em_andamento' => 'Em andamento',
                    'concluida' => 'Concluída',
                    default => $ativa ? 'Ativa' : 'Inativa',
                };
                $detalhes = [
                    'tarefa' => $rotina->tarefa ?: 'Tarefa',
                    'horario' => $rotina->horario,
                    'local' => $rotina->local,
                    'morador' => $rotina->morador ?: 'Não informado',
                    'obs' => $rotina->obs,
                    'ativa' => $ativa,
                    'estado' => $estado,
                    'sla' => $rotina->sla_execucao,
                    'prazo' => $rotina->prazo_execucao,
                    'acao' => $rotina->acao_execucao,
                    'icone' => $icone,
                    'cor_icone' => $corIcone ?: 'original',
                    'hex_cor' => $hexCor,
                ];
            @endphp

            <div data-estado-rotina="{{ $rotina->estado ?? '' }}" role="button" tabindex="0"
                 onclick="abrirModalDetalhes({{ \Illuminate\Support\Js::from($detalhes) }})"
                 onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }"
                 class="flex items-center justify-between p-4 border border-amber-200/80 rounded-2xl bg-white shadow-sm hover:shadow-md hover:border-slate-400 transition cursor-pointer">
                <div class="flex items-center gap-4">
                    <div data-rotina-icone-box
                         data-icone="{{ $icone }}"
                         data-hex-cor="{{ $hexCor ?? '' }}"
                         class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center text-3xl">
                        {{ $icone }}
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">{{ $detalhes['tarefa'] }}</h2>
                        <div class="flex items-center gap-4 text-xs text-gray-500 mt-1">
                            <span class="flex items-center gap-1">
                                📍 <span><strong>Onde:</strong> {{ $rotina->local }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                👤 <span><strong>Morador:</strong> {{ $detalhes['morador'] }}</span>
                            </span>
                            @if(isset($rotina->auxilio) && $rotina->auxilio == 1)
                                <span class="flex items-center gap-1 text-amber-700 font-medium">
                                    ⚠️ <span>Auxílio necessário</span>
                                </span>
                            @endif
                        </div>
                        <div class="text-xs font-semibold text-gray-700 mt-1">
                            ⏰ {{ $rotina->horario }}
                            @if(!empty($rotina->obs))
                                <span class="ml-2 text-gray-400 font-normal">| Obs: {{ $rotina->obs }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="px-3 py-1 text-xs font-medium rounded-full {{ $ativa ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $estado }}
                    </span>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </div>
        @empty
            <div class="text-center py-12 border border-dashed border-gray-200 rounded-2xl">
                <p class="text-sm font-medium text-gray-500">Nenhuma rotina cadastrada ainda.</p>
                <p class="text-xs text-gray-400 mt-1">Adicione novas tarefas através do modal de cadastro.</p>
            </div>
        @endforelse
        <p id="rotinasAbaVazia" class="hidden text-center py-8 text-sm text-gray-500">Nenhuma execução nesta situação.</p>
    </div>
</div>

@include('sla.partials.modal-detalhes-rotina')

<script>
    function aplicarCorElementoRotina(el, emoji, hex) {
        if (!el) return;

        el.textContent = emoji;
        el.style.color = '';
        el.style.webkitTextFillColor = '';
        el.style.filter = '';
        el.style.position = '';
        el.style.overflow = '';

        const overlayAnterior = el.querySelector('[data-overlay-cor]');
        if (overlayAnterior) {
            overlayAnterior.remove();
        }

        if (!hex) return;

        el.style.position = 'relative';
        el.style.overflow = 'hidden';

        const camada = document.createElement('span');
        camada.setAttribute('data-overlay-cor', '1');
        camada.style.position = 'absolute';
        camada.style.inset = '0';
        camada.style.backgroundColor = hex;
        camada.style.mixBlendMode = 'color';
        camada.style.pointerEvents = 'none';
        camada.style.borderRadius = 'inherit';

        el.appendChild(camada);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rotina-icone-box]').forEach((box) => {
            const emoji = box.dataset.icone || '📋';
            const hex = box.dataset.hexCor || '';
            aplicarCorElementoRotina(box, emoji, hex);
        });
    });

    function mudarAba(botaoClicado) {
        const botoes = document.querySelectorAll('.aba-btn');
        botoes.forEach(btn => {
            btn.setAttribute('aria-pressed', String(btn === botaoClicado));
            btn.classList.remove('border-emerald-800', 'font-semibold', 'text-emerald-900');
            btn.classList.add('text-gray-500', 'font-medium', 'border-transparent');
        });

        botaoClicado.classList.remove('text-gray-500', 'font-medium', 'border-transparent');
        botaoClicado.classList.add('border-emerald-800', 'font-semibold', 'text-emerald-900');

        const estado = botaoClicado.dataset.estado;
        const rotinas = document.querySelectorAll('[data-estado-rotina]');
        let visiveis = 0;
        rotinas.forEach(rotina => {
            const visivel = estado === '' || rotina.dataset.estadoRotina === estado;
            rotina.style.display = visivel ? '' : 'none';
            visiveis += Number(visivel);
        });
        document.getElementById('rotinasAbaVazia').classList.toggle('hidden', estado === '' || visiveis > 0);
    }
</script>
@endsection