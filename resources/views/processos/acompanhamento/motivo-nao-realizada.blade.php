<div class="mb-3 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm text-rose-900">
    <p @class(['break-words', 'line-clamp-3' => $resumir ?? false])>
        <strong>O Colaborador {{ $item->colaborador }} não conseguiu realizar a tarefa {{ $item->tarefa }}. Justificativa:</strong>
        <span class="whitespace-pre-line">{{ $item->justificativa_nao_realizada }}</span>
    </p>
</div>
