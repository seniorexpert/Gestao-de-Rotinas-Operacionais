@extends('layouts.app')

@section('title', 'Rotinas - Senior Expert')

@section('content')
<div class="max-w-6xl mx-auto p-6 bg-white min-h-screen font-sans text-gray-800">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-800 font-serif">Rotinas</h1>
        <p class="text-sm text-gray-500 mt-1">Acompanhe e gerencie suas tarefas do dia</p>
    </div>

    <div class="flex items-center gap-6 border-b border-gray-200 mb-6 text-sm">
        <button type="button" onclick="mudarAba(this)" class="aba-btn pb-3 border-b-2 border-emerald-800 font-semibold text-emerald-900 transition">
            Todas
        </button>
        <button type="button" onclick="mudarAba(this)" class="aba-btn pb-3 text-gray-500 hover:text-gray-700 font-medium transition border-b-2 border-transparent">
            Em andamento
        </button>
        <button type="button" onclick="mudarAba(this)" class="aba-btn pb-3 text-gray-500 hover:text-gray-700 font-medium transition border-b-2 border-transparent">
            Concluídas
        </button>
    </div>

    <div class="space-y-4">
        @forelse($rotinas as $rotina)
            @php
                $icone = '📋';
                $localLower = strtolower($rotina->local ?? '');
                if (str_contains($localLower, 'refeitório') || str_contains($localLower, 'cozinha')) {
                    $icone = '🍽️';
                } elseif (str_contains($localLower, 'banheiro') || str_contains($localLower, 'higiene')) {
                    $icone = '🛁';
                } elseif (str_contains($localLower, 'enfermagem') || str_contains($localLower, 'quarto')) {
                    $icone = '💊';
                } else {
                    $icone = '🚶';
                }
            @endphp

            <div onclick="abrirModalDetalhes({
                id: '{{ $rotina->id ?? '' }}',
                tarefa: '{{ addslashes($rotina->tarefa_nome ?? $rotina->tarefa ?? 'Tarefa') }}',
                horario: '{{ addslashes($rotina->horario ?? '') }}',
                local: '{{ addslashes($rotina->local ?? '') }}',
                morador: '{{ addslashes($rotina->morador ?? '') }}',
                dias: '{{ addslashes($rotina->dias_semanas ?? '') }}',
                auxilio: '{{ $rotina->auxilio ?? 0 }}',
                obs: '{{ addslashes($rotina->obs ?? '') }}',
                icone: '{{ $icone }}'
            })" class="flex items-center justify-between p-4 border border-amber-200/80 rounded-2xl bg-white shadow-sm hover:shadow-md hover:border-slate-400 transition cursor-pointer">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center text-3xl">
                        {{ $icone }}
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">{{ $rotina->morador }}</h2>
                        <div class="flex items-center gap-4 text-xs text-gray-500 mt-1">
                            <span class="flex items-center gap-1">
                                📍 <span><strong>Onde:</strong> {{ $rotina->local }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                👤 <span><strong>Morador:</strong> {{ $rotina->morador }}</span>
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
                    <span class="px-3 py-1 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">
                        Ativa
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
    </div>
</div>

@include('sla.modal-detalhes-rotina')

<script>
    function mudarAba(botaoClicado) {
        const botoes = document.querySelectorAll('.aba-btn');
        botoes.forEach(btn => {
            btn.classList.remove('border-emerald-800', 'font-semibold', 'text-emerald-900');
            btn.classList.add('text-gray-500', 'font-medium', 'border-transparent');
        });

        botaoClicado.classList.remove('text-gray-500', 'font-medium', 'border-transparent');
        botaoClicado.classList.add('border-emerald-800', 'font-semibold', 'text-emerald-900');
    }
</script>
@endsection