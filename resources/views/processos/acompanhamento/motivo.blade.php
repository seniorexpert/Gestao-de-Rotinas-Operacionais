<div @class([
    'mb-3 rounded-xl px-3.5 py-2.5 text-sm',
    'bg-amber-50 text-amber-900' => $item->tipo === 'auxilio',
    'bg-rose-50 text-rose-900' => $item->tipo === 'nao_realizada',
])>
    <p @class(['break-words', 'line-clamp-3' => $resumir ?? false])>
        @if ($item->tipo === 'auxilio')
            <strong>O Colaborador {{ $item->colaborador }} Solicita Auxílio para realizar a tarefa {{ $item->tarefa }}! Justificativa:</strong>
        @else
            <strong>O Colaborador {{ $item->colaborador }} não conseguiu realizar a tarefa {{ $item->tarefa }}. Justificativa:</strong>
        @endif
        <span class="whitespace-pre-line">{{ $item->justificativa }}</span>
    </p>
</div>
