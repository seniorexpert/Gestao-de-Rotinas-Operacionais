@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6 bg-white min-h-screen font-sans text-gray-800">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 font-serif" style="font-family: 'Fraunces', serif;">Centro de Monitoramento</h1>
            <p class="text-sm text-gray-500 mt-1">Processos, prazos e produtividade da equipe hoje</p>
        </div>
        <button type="button" onclick="abrirModalAtribuir()" class="flex items-center gap-2 px-5 py-3 bg-[#1e293b] text-white rounded-xl text-sm font-semibold hover:bg-slate-700 transition shadow-sm cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            Atribuir tarefa
        </button>
    </div>

    <div class="mb-8">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3">Processos</h2>
        
        <div class="border border-amber-200/80 rounded-2xl p-6 bg-white shadow-sm relative">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Acompanhamento Coordenador</h3>
                    <span class="text-xs font-bold text-emerald-700 uppercase tracking-wide">SLA NO PRAZO</span>
                </div>
                <span class="text-2xl font-bold text-slate-800">71%</span>
            </div>

            <div class="w-full bg-gray-100 rounded-full h-2.5 mb-4 overflow-hidden">
                <div class="bg-[#1e293b] h-2.5 rounded-full" style="width: 71%"></div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 text-xs text-gray-500 pt-2 border-t border-gray-100">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-800 rounded-lg font-medium border border-amber-100">
                        📋 7 itens
                    </span>
                    <span class="flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-800 rounded-lg font-medium border border-amber-100">
                        💬 7 pendências
                    </span>
                    <span class="hidden sm:inline">
                        👤 Maior fila: <strong class="text-slate-700">Fabiana Duarte (4 pendências)</strong>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span>📅 Último item criado: 04/11/2026 14:00</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </div>

            <div class="flex justify-center items-center gap-2 mt-6">
                <span class="w-2.5 h-2.5 rounded-full bg-[#1e293b]"></span>
                <span class="w-2 h-2 rounded-full bg-amber-200"></span>
                <span class="w-2 h-2 rounded-full bg-amber-200"></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <div class="lg:col-span-5 border border-amber-200/80 rounded-2xl p-6 bg-white shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        ⏱️ SLA por etapa
                    </h3>
                    <span class="text-xs text-gray-400 font-medium">tarefas em aberto</span>
                </div>

                <div class="space-y-4">
                    <div class="p-4 bg-emerald-50/60 border border-emerald-100 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-2xl font-bold text-emerald-800">71%</span>
                        </div>
                        <div class="text-right">
                            <h4 class="font-bold text-emerald-800 text-sm">No prazo</h4>
                            <p class="text-xs text-gray-500">20 tarefas</p>
                        </div>
                    </div>

                    <div class="p-4 bg-amber-50/60 border border-amber-100 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-2xl font-bold text-amber-800">14%</span>
                        </div>
                        <div class="text-right">
                            <h4 class="font-bold text-amber-800 text-sm">Expirando</h4>
                            <p class="text-xs text-gray-500">4 tarefas</p>
                        </div>
                    </div>

                    <div class="p-4 bg-red-50/60 border border-red-100 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-2xl font-bold text-red-800">14%</span>
                        </div>
                        <div class="text-right">
                            <h4 class="font-bold text-red-800 text-sm">Em atraso</h4>
                            <p class="text-xs text-gray-500">4 tarefas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7 border border-amber-200/80 rounded-2xl p-6 bg-white shadow-sm">
            <div class="flex items-center gap-2 mb-6">
                <svg class="w-5 h-5 text-slate-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <h3 class="text-lg font-bold text-slate-800">Produtividade</h3>
            </div>

            <div class="space-y-4">
                @forelse($produtividades ?? [] as $prod)
                    <div onclick="abrirModalDetalhesProd('{{ $prod->colaborador_id }}', '{{ addslashes($prod->colaborador_nome) }}', '{{ $prod->total_tarefas }}')" class="p-4 border border-gray-100 rounded-2xl bg-white shadow-sm cursor-pointer hover:border-slate-400 hover:shadow-md transition">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-semibold">
                                    👤
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 text-sm">{{ $prod->colaborador_nome }}</h4>
                                    <p class="text-xs text-gray-500">Colaborador(a) · {{ $prod->total_tarefas }} tarefa(s)</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-amber-50 text-amber-800 text-xs font-semibold rounded-full border border-amber-100">
                                {{ $prod->ativas }} ativa(s)
                            </span>
                        </div>

                        <div class="w-full bg-gray-100 rounded-full h-2 mb-3 overflow-hidden">
                            <div class="bg-[#1e293b] h-2 rounded-full" style="width: 100%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#1e293b] text-white text-xs font-medium rounded-lg">
                                🔗 {{ $prod->total_tarefas }} vinculados
                            </span>
                            <span class="text-xs font-bold text-slate-700">100%</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-400 text-xs">
                        Nenhum registro de produtividade encontrado.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@include('sla.partials.modal-atribuir-tarefa')
@include('sla.partials.modal-detalhes-produtividade')

@endsection