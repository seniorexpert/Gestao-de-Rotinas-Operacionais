@extends('layouts.app')

@php
    [$titulo, $descricao, $rota, $confirmar, $cor] = match ($acao) {
        'finalizar' => ['Finalizar Tarefa', null, 'execucoes.concluir', 'Confirmar Envio', 'bg-[#2d5a27] hover:bg-[#23471f]'],
        'nao-realizada' => ['Não Pode Ser Realizada', 'Informe por que a tarefa não pôde ser feita', 'execucoes.nao-realizada', 'Confirmar Motivo', 'bg-[#5c2328] hover:bg-[#4a1c20]'],
        'auxilio' => ['Solicitar Auxílio', 'Descreva o apoio necessário', 'execucoes.auxilio', 'Enviar Solicitação', 'bg-[#5d461e] hover:bg-[#4b3818]'],
    };
    $finalizar = $acao === 'finalizar';
    $voltar = route('execucoes.realizando', $execucao->id_execucao);
    $tarefa = $execucao->tarefa ?: 'Tarefa de rotina';
    $campo = $finalizar ? 'comentario' : 'justificativa';
@endphp

@section('title', $titulo.' - Senior Expert')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
    <style>
        [data-anexo-execucao] [hidden] { display: none; }
    </style>
@endpush

@section('content')
<form method="POST" action="{{ route($rota, $execucao->id_execucao) }}" @if ($finalizar) enctype="multipart/form-data" @endif
      class="flex min-h-[calc(100dvh_-_16rem)] w-full max-w-[38rem] flex-col text-[#21394e]">
    @csrf
    @if ($protocolo)
        <input type="hidden" name="protocolo" value="{{ $protocolo }}">
    @endif

    <header class="mb-4">
        <a href="{{ $voltar }}" class="mb-2 inline-block rounded px-0.5 py-1 hover:text-[#64748b]" aria-label="Voltar aos detalhes da tarefa">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="mb-1 text-2xl font-bold leading-tight sm:text-3xl" style="font-family: Fraunces, Georgia, serif">{{ $titulo }}</h1>
        @if ($descricao)
            <p class="text-xs text-[#64748b]">{{ $descricao }}</p>
        @endif
    </header>

    @error('execucao')
        <p class="mb-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{{ $message }}</p>
    @enderror

    @if ($finalizar)
        @php
            $categoria = \Illuminate\Support\Str::ascii(mb_strtolower($tarefa.' '.$execucao->local));
            $iconeSalvo = trim((string) $execucao->icone);
            $icone = match (true) {
                ! in_array($iconeSalvo, ['', '0', '1'], true) => $iconeSalvo,
                str_contains($categoria, 'banho') || str_contains($categoria, 'higiene') => '🛁',
                str_contains($categoria, 'aliment') || str_contains($categoria, 'refeitorio') => '🍽️',
                str_contains($categoria, 'medic') => '💊',
                default => '📋',
            };
        @endphp
        <input type="hidden" name="fluxo_diario" value="1">
        <p class="mb-4 self-start rounded-full bg-[#e6eff2] px-4 py-1 text-xs font-bold"><x-icone-tarefa :icone="$icone" :cor="$execucao->cor_icone" class="align-middle" /> {{ $tarefa }}</p>
        <div data-anexo-execucao class="mb-4 grid justify-items-center gap-3 rounded-3xl border-2 border-[#d3ae74]/35 bg-white p-6 text-center shadow-sm">
            <span id="iconeFoto" class="grid h-12 w-12 place-items-center rounded-xl border border-dashed border-[#d3ae74]/60 text-[#64748b]" aria-hidden="true"><i class="fa-regular fa-image"></i></span>
            <img id="previewFoto" alt="Prévia da foto selecionada" hidden class="max-h-48 max-w-full rounded-xl object-contain">
            <p id="nomeFoto" class="max-w-full break-all text-xs text-[#64748b]" aria-live="polite">Nenhuma foto selecionada</p>
            <label class="relative flex min-h-11 w-full max-w-xs cursor-pointer items-center justify-center rounded-xl border border-[#d3ae74]/60 px-3 py-2 text-sm font-bold hover:bg-[#fcfbf8] focus-within:outline focus-within:outline-2 focus-within:outline-offset-2">
                <input id="foto" name="foto" type="file" accept="image/*" aria-describedby="limiteFoto" class="sr-only">
                <span>+ Tirar foto ou escolher arquivo</span>
            </label>
            <p id="limiteFoto" class="text-xs text-[#64748b]">Imagem de até 2 MB</p>
            <button id="removerFoto" type="button" hidden class="text-xs font-bold text-rose-800 underline underline-offset-2">Remover foto</button>
            @error('foto')
                <p class="text-xs text-rose-800" role="alert">{{ $message }}</p>
            @enderror
        </div>
    @else
        <section aria-labelledby="tarefa-selecionada" class="mb-5 rounded-3xl border-2 border-[#d3ae74]/35 bg-white p-5 shadow-sm">
            <p class="mb-1 text-xs text-[#64748b]">Tarefa selecionada</p>
            <h2 id="tarefa-selecionada" class="flex items-center gap-2 font-bold">
                <x-icone-tarefa :icone="$execucao->icone" :cor="$execucao->cor_icone" class="h-6 w-6 text-xl" />
                <span class="min-w-0 break-words">{{ $tarefa }} <span class="whitespace-nowrap tabular-nums">· {{ $execucao->horario }}</span></span>
            </h2>

            <dl class="mt-3 grid grid-cols-2 gap-4 border-t border-slate-100 pt-3 text-sm">
                <div class="min-w-0">
                    <dt class="text-xs text-[#64748b]">Local</dt>
                    <dd class="mt-1 break-words font-semibold">{{ $execucao->local }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-[#64748b]">Quem</dt>
                    <dd class="mt-1 break-words font-semibold">{{ trim($execucao->morador ?? '') ?: 'Não informado' }}</dd>
                </div>
            </dl>
        </section>
    @endif

    <label for="{{ $campo }}" class="mb-1.5 text-xs font-bold">
        {{ $finalizar ? 'Comentário' : 'Justificativa' }} <span class="font-normal">(obrigatório)</span>
    </label>
    <textarea id="{{ $campo }}" name="{{ $campo }}" rows="{{ $finalizar ? 5 : 7 }}" maxlength="2000" required
              placeholder="{{ $finalizar ? 'Descreva como a tarefa foi realizada...' : 'Descreva o motivo...' }}"
              @error($campo) aria-invalid="true" aria-describedby="erro-{{ $campo }}" @enderror
              class="w-full resize-y rounded-3xl border border-[#d3ae74]/45 bg-white px-4 py-4 text-sm placeholder:text-slate-400 shadow-sm focus:border-[#21394e] focus:outline-none">{{ is_string(old($campo)) ? old($campo) : '' }}</textarea>
    @error($campo)
        <p id="erro-{{ $campo }}" class="mt-1.5 text-xs text-rose-800" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-auto grid grid-cols-2 gap-3 pt-8">
        <a href="{{ $voltar }}" class="flex min-h-12 items-center justify-center rounded-xl border border-[#d3ae74]/60 bg-white px-2 text-center text-sm font-bold hover:bg-[#fcfbf8]">{{ $finalizar ? 'Voltar' : 'Cancelar' }}</a>
        <button type="submit" class="min-h-12 rounded-xl px-2 py-2 text-sm font-bold text-white shadow-sm {{ $cor }}">{{ $confirmar }}</button>
    </div>
</form>

@if ($finalizar)
    <script>
        (() => {
            const campo = document.getElementById('foto');
            const imagem = document.getElementById('previewFoto');
            const icone = document.getElementById('iconeFoto');
            const nome = document.getElementById('nomeFoto');
            const remover = document.getElementById('removerFoto');
            let url;

            function atualizarFoto() {
                if (url) URL.revokeObjectURL(url);
                const arquivo = campo.files[0];
                url = arquivo?.type.startsWith('image/') ? URL.createObjectURL(arquivo) : null;
                imagem.hidden = !url;
                imagem.removeAttribute('src');
                if (url) imagem.src = url;
                icone.hidden = Boolean(url);
                nome.textContent = arquivo?.name ?? 'Nenhuma foto selecionada';
                remover.hidden = !arquivo;
            }

            campo.addEventListener('change', atualizarFoto);
            remover.addEventListener('click', () => {
                campo.value = '';
                atualizarFoto();
                campo.focus();
            });
        })();
    </script>
@endif
@endsection
