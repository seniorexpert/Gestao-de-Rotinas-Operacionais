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
    $categoria = \Illuminate\Support\Str::ascii(mb_strtolower($tarefa.' '.$execucao->local));
    $icone = match (true) {
        str_contains($categoria, 'banho') || str_contains($categoria, 'higiene') => '🛁',
        str_contains($categoria, 'aliment') || str_contains($categoria, 'refeitorio') => '🍽️',
        str_contains($categoria, 'medic') => '💊',
        default => '📋',
    };
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
      class="mx-auto flex min-h-[calc(100dvh_-_12rem)] w-full max-w-[38rem] flex-col px-4 pt-5 text-[#21394e] sm:px-6">
    @csrf

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
        <input type="hidden" name="fluxo_diario" value="1">
        <p class="mb-4 self-start rounded-full bg-[#e6eff2] px-4 py-1 text-xs font-bold"><span aria-hidden="true">{{ $icone }}</span> {{ $tarefa }}</p>
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
        <div class="mb-5 rounded-3xl border-2 border-[#d3ae74]/35 bg-white p-5 shadow-sm">
            <p class="mb-1 text-xs text-[#64748b]">Tarefa selecionada</p>
            <p class="font-bold"><span class="mr-1.5" aria-hidden="true">{{ $icone }}</span>{{ $tarefa }} · {{ $execucao->horario }}</p>
        </div>
    @endif

    <label for="{{ $campo }}" class="mb-1.5 text-xs font-bold">{{ $finalizar ? 'Comentário' : 'Justificativa' }}</label>
    <textarea id="{{ $campo }}" name="{{ $campo }}" rows="{{ $finalizar ? 5 : 7 }}" maxlength="2000" @required(! $finalizar)
              placeholder="{{ $finalizar ? 'Descreva como a tarefa foi realizada...' : 'Descreva o motivo...' }}"
              class="w-full resize-y rounded-3xl border border-[#d3ae74]/45 bg-white px-4 py-4 text-sm placeholder:text-slate-400 shadow-sm focus:border-[#21394e] focus:outline-none">{{ old($campo) }}</textarea>
    @error($campo)
        <p class="mt-1.5 text-xs text-rose-800" role="alert">{{ $message }}</p>
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
