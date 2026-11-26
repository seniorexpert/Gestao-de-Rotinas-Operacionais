@props(['identificador', 'titulo', 'subtitulo' => null, 'campos' => []])

<li {{ $attributes->class('min-w-0 rounded-[1.25rem] border-2 border-[#e8d6bf] bg-white p-4 shadow-sm') }}>
    <div class="flex min-w-0 items-start justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2.5">
            {{ $icone ?? '' }}
            <div class="min-w-0">
                <p class="break-words font-semibold leading-snug">{{ $titulo }}</p>
                @if (filled($subtitulo))
                    <p class="mt-1 break-words text-xs text-[#536b7b]">{{ $subtitulo }}</p>
                @endif
            </div>
        </div>
        <span class="shrink-0 rounded-full bg-[#f9f7f2] px-2.5 py-0.5 text-xs font-semibold tabular-nums text-[#536b7b]">#{{ $identificador }}</span>
    </div>

    <div class="mt-3 flex min-w-0 items-center justify-between gap-2">
        <dl class="grid min-w-0 flex-1 gap-1.5 text-sm">
            @foreach ($campos as $rotulo => $valor)
                <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5">
                    <dt class="font-semibold text-[#536b7b]">{{ $rotulo }}:</dt>
                    <dd class="min-w-0 break-words">{{ $valor }}</dd>
                </div>
            @endforeach
            {{ $slot }}
        </dl>
        {{ $acao ?? '' }}
    </div>
</li>
