@extends('layouts.app')

@section('title', 'Detalhes do evento')

@section('content')
<div>
    <a href="{{ route('relatorios.logs-rotinas', $filtros) }}" class="inline-flex items-center gap-2 text-sm font-medium text-[#1e2d3d] hover:underline">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar aos logs
    </a>
    <h1 class="mb-6 mt-4 break-words font-serif text-3xl font-bold text-[#1e2d3d]">{{ $log->descricao_evento }}</h1>

    @php
        $informacoes = [
            'Evento' => '#'.$log->id,
            'Data e hora (Brasília)' => $log->data_local,
            'Usuário' => $log->autor,
            'Login' => $log->login,
            'Ação' => $log->acao_label,
            'Resultado' => $log->resultado_label,
            'Recurso' => $log->recurso,
            'Registro' => $log->registro_id,
            'Unidade' => $log->empresa,
            'Tarefa' => $log->tarefa,
            'Colaborador responsável' => $log->colaborador_original,
            'Origem' => $log->origem === 'web' ? 'Sistema web' : $log->origem,
            'Rota' => $log->rota,
            'Método HTTP' => $log->metodo_http,
            'Código HTTP' => $log->status_http,
        ];
        if ($visaoGeral) {
            $informacoes['IP'] = $log->ip;
        }
    @endphp

    <dl class="grid gap-x-8 gap-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
        @foreach ($informacoes as $rotulo => $valor)
            @if (filled($valor))
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-slate-600">{{ $rotulo }}</dt>
                    <dd class="mt-1 break-words text-sm text-slate-900">{{ $valor }}</dd>
                </div>
            @endif
        @endforeach
        @if ($visaoGeral && $log->user_agent)
            <div class="min-w-0 sm:col-span-full">
                <dt class="text-xs font-medium text-slate-600">Navegador</dt>
                <dd class="mt-1 break-words text-sm text-slate-900">{{ $log->user_agent }}</dd>
            </div>
        @endif
    </dl>

    @if ($alteracoes)
        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <h2 class="px-5 py-4 text-base font-semibold text-[#1e2d3d] sm:px-6">Dados alterados</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-[#fbfaf7] text-xs font-semibold text-slate-600">
                        <tr>
                            <th scope="col" class="w-1/4 px-5 py-3">Campo</th>
                            <th scope="col" class="px-4 py-3">Antes</th>
                            <th scope="col" class="px-4 py-3">Depois</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($alteracoes as $campo => $valores)
                            <tr>
                                <th scope="row" class="break-words px-5 py-3 align-top font-medium text-slate-700">{{ str_replace('_', ' ', $campo) }}</th>
                                <td class="whitespace-pre-wrap break-words px-4 py-3 align-top text-slate-700">{{ $valores['antes'] }}</td>
                                <td class="whitespace-pre-wrap break-words px-4 py-3 align-top text-slate-900">{{ $valores['depois'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
