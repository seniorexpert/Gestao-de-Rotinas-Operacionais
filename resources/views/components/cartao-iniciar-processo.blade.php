@props(['titulo', 'descricao', 'icone', 'acao' => 'Acessar', 'url' => null, 'horizontal' => false])

@php
    $elemento = $url === null ? 'button' : 'a';
@endphp

<{{ $elemento }} @if ($url === null) type="button" @else href="{{ $url }}" @endif
    {{ $attributes->class([
        'group flex min-w-0 rounded-3xl border-2 border-[#e8d6bf] bg-white text-left text-[#193b50] shadow-[0_1px_3px_#0002]',
        'min-h-32 items-center gap-3 px-4 py-5 sm:gap-4 sm:px-5 md:aspect-[1.95] lg:gap-6 lg:pl-7 lg:pr-6' => $horizontal,
        'min-h-[316px] flex-col px-6 pb-[27px] pt-[26px] sm:px-7' => ! $horizontal,
        'hover:border-[#a0b0bb] hover:shadow-[0_12px_30px_#193b5021] focus-visible:border-[#a0b0bb] focus-visible:shadow-[0_12px_30px_#193b5021]',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'motion-safe:transition-[border-color,box-shadow,transform] motion-safe:duration-200 motion-safe:hover:-translate-y-0.5',
    ]) }}>
    <span @class([
        'flex shrink-0 items-center justify-center self-center rounded-full group-hover:bg-[#17384a] group-hover:text-white group-focus-visible:bg-[#17384a] group-focus-visible:text-white motion-safe:transition-colors motion-safe:duration-200',
        'h-12 w-12 sm:h-14 sm:w-14 lg:h-20 lg:w-20' => $horizontal,
        'h-20 w-20' => ! $horizontal,
    ]) aria-hidden="true">
        <svg @class([
            'h-9 w-9 sm:h-10 sm:w-10 lg:h-14 lg:w-14' => $horizontal,
            'h-14 w-14 translate-y-px' => ! $horizontal,
        ]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            @if ($icone === 'sirene')
                <path d="M7 18v-6a5 5 0 0 1 10 0v6M12 12v6M12 2v1M5 5l1 1m13-1-1 1M2 12h1m18 0h1"/>
                <path d="M6 18h12a2 2 0 0 1 2 2v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-1a2 2 0 0 1 2-2Z"/>
            @elseif ($icone === 'engrenagem')
                <path d="M9.67 4.14a2.34 2.34 0 0 1 4.66 0 2.34 2.34 0 0 0 3.32 1.91 2.34 2.34 0 0 1 2.33 4.03 2.34 2.34 0 0 0 0 3.84 2.34 2.34 0 0 1-2.33 4.03 2.34 2.34 0 0 0-3.32 1.91 2.34 2.34 0 0 1-4.66 0 2.34 2.34 0 0 0-3.32-1.91 2.34 2.34 0 0 1-2.33-4.03 2.34 2.34 0 0 0 0-3.84 2.34 2.34 0 0 1 2.33-4.03 2.34 2.34 0 0 0 3.32-1.91Z"/>
                <circle cx="12" cy="12" r="3"/>
            @else
                <use href="#senior-menu-{{ $icone }}"/>
            @endif
        </svg>
    </span>

    <span @class([
        'min-w-0',
        'flex flex-1 flex-col gap-2 lg:flex-row lg:items-center lg:gap-4' => $horizontal,
        'mt-[22px]' => ! $horizontal,
    ])>
        <span @class([
            'block min-w-0',
            'lg:flex-1' => $horizontal,
        ])>
            <span @class([
                "block font-['Fraunces',Georgia,serif] font-semibold leading-tight tracking-[-0.01em]",
                'break-words text-xl lg:text-[1.625rem]' => $horizontal,
                'max-w-[20rem] text-[1.625rem]' => ! $horizontal,
            ])>{{ $titulo }}</span>
            <span @class([
                'mt-1.5 block leading-snug tracking-[0.01em] text-[#526b80]',
                'break-words text-sm lg:text-[1.0625rem]' => $horizontal,
                'text-[1.0625rem]' => ! $horizontal,
                'max-w-[20rem]' => ! $horizontal && $icone === 'clipboard-list',
            ])>{{ $descricao }}</span>
        </span>

        @if ($horizontal)
            <span class="inline-flex shrink-0 items-center gap-2 self-start whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-[#986b35] sm:text-sm lg:self-center">
                {{ $acao }}
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#f8f6f1] lg:h-10 lg:w-10">
                    <svg class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 5 7 7-7 7"/>
                    </svg>
                </span>
            </span>
        @endif
    </span>

    @unless ($horizontal)
        <span class="mt-5 inline-flex items-center gap-2.5 text-sm font-semibold uppercase tracking-wider text-[#986b35]">
            {{ $acao }}
            <svg class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14m-7-7 7 7-7 7"/>
            </svg>
        </span>
    @endunless
</{{ $elemento }}>
