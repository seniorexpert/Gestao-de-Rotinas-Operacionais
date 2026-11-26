@if ($dadosEdicao !== null)
    <button type="button" onclick="abrirModalEdicao(@js($dadosEdicao))" aria-haspopup="dialog"
            class="inline-flex min-h-9 shrink-0 items-center gap-2 whitespace-nowrap text-xs font-semibold transition-colors hover:text-[#8b6b43] hover:underline">
        Editar <i class="fa-solid fa-chevron-right text-[0.625rem] text-[#a36f39]" aria-hidden="true"></i>
    </button>
@endif
