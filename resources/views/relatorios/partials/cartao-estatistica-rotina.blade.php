@php
    $status = $statusExecucao[$execucao->status];
    $tipo = $execucao->tipo ?? null;
    $idTitulo = 'tarefa-'.($tipo ?? 'execucao').'-'.($execucao->id_item ?? $execucao->id_execucao);
@endphp

<article class="cartao-rotina" aria-labelledby="{{ $idTitulo }}">
    <div class="topo-rotina">
        <span class="icone-tarefa">
            <x-icone-tarefa :icone="$execucao->icone" :cor="$execucao->cor_icone" class="h-6 w-6 text-xl" />
        </span>
        <div class="nome-tarefa">
            <h3 id="{{ $idTitulo }}">{{ $execucao->tarefa }}</h3>
            <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['classe'] }}">{{ $status['nome'] }}</span>
            @if ($tipo === 'atraso')
                <p>Acompanhamento de atraso</p>
            @elseif ($tipo === 'nao_realizada')
                <p>Tarefa não realizada</p>
            @elseif ($aba !== 'processos' && $execucao->tipo_rotina === \App\Services\ExecucoesDiarias::TIPO_EMERGENCIAL)
                <p>Tarefa emergencial</p>
            @endif
            @if ($execucao->status === 'concluida' && $execucao->concluido_em)
                <p>{{ $tipo ? 'Verificada em' : 'Finalizada em' }} {{ $dataLocal($execucao->concluido_em)->format('d/m/Y') }}</p>
            @endif
        </div>
        <time class="horario-rotina" datetime="{{ $dataLocal($execucao->previsto_em)?->toIso8601String() }}" title="Horário previsto em São Paulo">
            <span aria-hidden="true">◷</span>
            {{ $dataLocal($execucao->previsto_em)?->format('H:i') }}
        </time>
    </div>

    <dl class="dados-rotina">
        <div>
            <dt><svg class="icone-dado" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="6" width="16" height="15" rx="2"/><path d="M8 3v6m8-6v6M4 11h16"/></svg> Início:</dt>
            <dd>{{ $dataLocal($execucao->iniciado_em)?->format('d/m/Y') ?? ($execucao->status === 'pendente' ? 'Não iniciada' : 'Não informado') }}</dd>
        </div>
        <div>
            <dt><svg class="icone-dado" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="7" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/></svg> Morador(a):</dt>
            <dd>{{ $execucao->morador }}</dd>
        </div>
        <div>
            <dt><svg class="icone-dado" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg> Local:</dt>
            <dd>{{ $execucao->local }}</dd>
        </div>
        @if ($aba !== 'usuarios')
            <div>
                <dt><i class="fa-regular fa-user icone-dado" aria-hidden="true"></i> Responsável:</dt>
                <dd>{{ $execucao->responsavel ?? 'Não localizado' }}</dd>
            </div>
        @endif
    </dl>
</article>
