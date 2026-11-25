<div class="flex min-w-0 items-start gap-2.5">
    <x-icone-tarefa :icone="$registro->icone" :cor="$registro->cor_icone" class="mt-0.5 h-7 w-7 text-xl" />
    <div class="min-w-0 break-words">
        <span class="block font-bold leading-snug group-hover:underline group-hover:underline-offset-2">{{ $registro->tarefa }}</span>
        <span class="mt-1.5 flex items-baseline gap-1.5 text-xs text-[#536b7b]">
            <i class="fa-regular fa-user w-3 shrink-0 text-[#8b6b43]" aria-hidden="true"></i>
            <span class="min-w-0"><strong class="text-[#17384b]">Quem:</strong> {{ $registro->morador }}</span>
        </span>
        <span class="mt-1 flex items-baseline gap-1.5 text-xs text-[#536b7b]">
            <i class="fa-solid fa-location-dot w-3 shrink-0 text-[#8b6b43]" aria-hidden="true"></i>
            <span class="min-w-0"><strong class="text-[#17384b]">Onde:</strong> {{ $registro->local }}</span>
        </span>
    </div>
</div>
