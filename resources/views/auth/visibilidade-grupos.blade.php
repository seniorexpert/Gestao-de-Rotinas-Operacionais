@extends('layouts.app')

@section('title', 'Visibilidade entre Grupos')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <div class="min-w-0 text-[#17384b]">
        <header class="mb-5 sm:mb-6">
            <h1 class="font-['Fraunces',Georgia,serif] text-[1.8rem] font-semibold leading-tight tracking-[-0.01em] sm:text-[2rem]">Visibilidade entre Grupos</h1>
            <p class="mt-1 text-sm text-[#536b7b] sm:text-[0.95rem]">Defina quais grupos aparecem para cada equipe nas consultas de colaboradores, nos indicadores e nos relatórios.</p>
        </header>

        @if (session('success'))
            <p role="status" class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="mb-4 list-disc rounded-xl bg-rose-50 px-8 py-3 text-sm text-rose-800">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        @endif

        @if ($origem)
            @php
                $idOrigem = (int) $origem->getKey();
                $modo = old('modo', $visiveis === [] ? 'todos' : 'selecionados');
                $selecionados = $errors->any() ? old('grupos_visiveis', []) : $visiveis;
                $classeBotao = 'inline-flex min-h-11 items-center justify-center rounded-xl bg-[#17384b] px-4 text-sm font-semibold text-white transition hover:bg-[#244d64] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#17384b]';
            @endphp

            <form method="GET" action="{{ route('visibilidade-grupos.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1 sm:max-w-md">
                    <label for="grupo-origem" class="mb-1 block text-[0.8125rem] font-semibold">Grupo que consulta</label>
                    <select id="grupo-origem" name="grupo" class="h-11 w-full rounded-xl border border-[#e8d6bf] bg-white px-4 text-sm focus:border-[#8b6b43] focus:outline-none focus:ring-1 focus:ring-[#8b6b43]">
                        @foreach ($grupos as $grupo)
                            <option value="{{ $grupo->getKey() }}" @selected($grupo->is($origem))>{{ $grupo->grupo_acesso }}{{ $grupo->status ? '' : ' (inativo)' }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="{{ $classeBotao }}">Carregar</button>
            </form>

            <form method="POST" action="{{ route('visibilidade-grupos.update', $idOrigem) }}" class="overflow-hidden rounded-2xl border border-[#e8d6bf] bg-white shadow-sm">
                @csrf
                @method('PUT')

                <header class="border-b border-[#e8d6bf] bg-[#f9f7f2] px-4 py-4 sm:px-6">
                    <h2 class="break-words text-lg font-semibold">{{ $origem->grupo_acesso }}</h2>
                    <p class="mt-1 text-sm text-[#536b7b]">A consulta segue as permissões de módulo e unidade do usuário.</p>
                </header>

                <div class="grid gap-5 px-4 py-4 sm:px-6 sm:py-5">
                    <fieldset class="grid gap-2 sm:grid-cols-2">
                        <legend class="mb-2 text-sm font-semibold">Quais grupos podem ser consultados?</legend>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e8d6bf] px-3 py-3 text-sm">
                            <input type="radio" name="modo" value="todos" @checked($modo === 'todos') required class="mt-0.5 h-4 w-4 shrink-0 accent-[#17384b]">
                            <span>Todos os grupos <span class="block text-xs text-[#536b7b]">Padrão do sistema</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e8d6bf] px-3 py-3 text-sm">
                            <input type="radio" name="modo" value="selecionados" @checked($modo === 'selecionados') required class="mt-0.5 h-4 w-4 shrink-0 accent-[#17384b]">
                            <span>Somente grupos selecionados <span class="block text-xs text-[#536b7b]">Inclui o próprio grupo</span></span>
                        </label>
                    </fieldset>

                    <fieldset id="grupos-visiveis">
                        <legend class="mb-2 text-sm font-semibold">Grupos visíveis</legend>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($grupos as $grupo)
                                @php($proprioGrupo = $grupo->is($origem))
                                <label class="flex min-w-0 items-start gap-2 rounded-xl border border-[#e8d6bf] px-3 py-2.5 text-sm {{ $proprioGrupo ? 'bg-[#f9f7f2]' : 'cursor-pointer' }}">
                                    <input type="checkbox" name="grupos_visiveis[]" value="{{ $grupo->getKey() }}" @disabled($proprioGrupo)
                                           @checked($proprioGrupo || (is_array($selecionados) && in_array((int) $grupo->getKey(), $selecionados)))
                                           class="mt-0.5 h-4 w-4 shrink-0 accent-[#17384b]">
                                    <span class="min-w-0 break-words">
                                        {{ $grupo->grupo_acesso }}
                                        @if ($proprioGrupo)<span class="block text-xs text-[#536b7b]">Próprio grupo</span>@endif
                                        @unless ($grupo->status)<span class="block text-xs text-[#536b7b]">Inativo</span>@endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="flex justify-end border-t border-[#e8d6bf] px-4 py-4 sm:px-6">
                    <button type="submit" class="{{ $classeBotao }} w-full sm:w-auto">Salvar visibilidade</button>
                </div>
            </form>

            <script>
                (() => {
                    const opcoes = document.getElementById('grupos-visiveis');
                    const formulario = opcoes.closest('form');
                    const mostrarSelecao = () => {
                        const selecionar = formulario.elements.modo.value === 'selecionados';
                        opcoes.hidden = !selecionar;
                        opcoes.disabled = !selecionar;
                    };
                    formulario.addEventListener('change', mostrarSelecao);
                    mostrarSelecao();
                })();
            </script>
        @else
            <p class="rounded-xl border border-[#e8d6bf] bg-[#f9f7f2] p-4 text-sm text-[#536b7b]">Nenhum grupo de acesso cadastrado.</p>
        @endif
    </div>
@endsection
