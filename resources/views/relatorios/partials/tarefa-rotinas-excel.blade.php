<x-icone-tarefa :icone="$tarefa->icone" :cor="$tarefa->cor_icone" class="mt-0.5 h-6 w-6 text-xl" />
<span class="min-w-0 flex-1 [overflow-wrap:anywhere]">
    <span class="block text-sm font-bold leading-snug">{{ $tarefa->tarefa }}</span>
    @if ($tarefa->detalhes !== '')
        <span class="mt-0.5 block text-xs text-[#536b7b]">{{ $tarefa->detalhes }}</span>
    @endif
    <span class="mt-0.5 block text-[0.7rem] font-semibold text-[#855f33]">{{ $tarefa->dias_formatados }}</span>
</span>
