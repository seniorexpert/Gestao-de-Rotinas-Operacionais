@props(['icone' => null, 'cor' => null])

@php
    $icone = trim((string) $icone);
    $icone = in_array($icone, ['', '0', '1'], true) ? '📋' : $icone;
    $cor = trim((string) $cor);
    $mascara = null;

    if (preg_match('/\A#(?:[\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})\z/i', $cor)) {
        $emoji = htmlspecialchars($icone, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-size="52">'
            .$emoji.'</text></svg>';
        $mascara = 'url("data:image/svg+xml,'.rawurlencode($svg).'")';
    }
@endphp

<span {{ $attributes->class('inline-flex shrink-0 items-center justify-center leading-none') }} aria-hidden="true">
    @if ($mascara)
        <span class="inline-block h-full w-full"
              style="background-color: {{ $cor }}; -webkit-mask: {{ $mascara }} center / contain no-repeat; mask: {{ $mascara }} center / contain no-repeat;"></span>
    @else
        {{ $icone }}
    @endif
</span>
