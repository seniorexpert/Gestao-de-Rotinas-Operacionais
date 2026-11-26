@props(['rotulo', 'ativo', 'parcial' => false])

<span @class([
    'inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
    'bg-[#e2f1e6] text-[#176b39]' => $ativo && ! $parcial,
    'bg-[#fdf1d9] text-[#80521f]' => $parcial,
    'bg-[#f0ebe2] text-[#536b7b]' => ! $ativo,
])>
    <span class="h-2 w-2 shrink-0 rounded-full bg-current opacity-70" aria-hidden="true"></span>
    {{ $rotulo }}
</span>
