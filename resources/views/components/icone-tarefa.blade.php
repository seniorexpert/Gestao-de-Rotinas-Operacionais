@props(['icone' => null, 'cor' => null])

@php
    $icone = trim((string) $icone);
    $icone = in_array($icone, ['', '0', '1'], true) ? '📋' : $icone;
    $cor = trim((string) $cor);
    $corValida = (bool) preg_match('/\A#(?:[\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})\z/i', $cor);
@endphp

<span {{ $attributes->class('inline-flex shrink-0 items-center justify-center leading-none') }} aria-hidden="true">
    @if ($corValida)
        {{-- Mesma técnica do modal (aplicarEstiloPintura): camada com mix-blend-mode "color" preserva os detalhes do emoji --}}
        <span class="relative inline-block overflow-hidden leading-none">
            {{ $icone }}
            <span class="pointer-events-none absolute inset-0" style="background-color: {{ $cor }}; mix-blend-mode: color;"></span>
        </span>
    @else
        {{ $icone }}
    @endif
</span>
