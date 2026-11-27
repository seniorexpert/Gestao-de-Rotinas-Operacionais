@extends('layouts.app')

@section('title', 'Permissões de Acesso')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="text-[#1e2d3d]">
        <header class="mb-5">
            <h1 class="text-2xl font-bold sm:text-3xl" style="font-family: Fraunces, Georgia, serif">Permissões de Acesso</h1>
            <p class="mt-1 text-sm text-slate-600">Uma regra individual tem prioridade sobre a regra do grupo.</p>
        </header>

        @if (session('success'))
            <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800" role="alert">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        @endif

        <section class="mb-5 rounded-2xl border border-[#b39359]/35 bg-white p-4 shadow-sm sm:p-5">
            <!-- <nav aria-label="Tipo de permissão" class="mb-4 inline-grid grid-cols-2 gap-1 rounded-xl bg-[#f4f1ea] p-1">
                @foreach (['grupo' => 'Por grupo', 'usuario' => 'Por usuário'] as $chave => $texto)
                    <a href="{{ route('permissoes.index', ['tipo' => $chave]) }}" @if ($tipo === $chave) aria-current="page" @endif
                       class="rounded-lg px-4 py-2 text-center text-sm font-semibold {{ $tipo === $chave ? 'bg-white text-[#1e2d3d] shadow-sm' : 'text-slate-600 hover:text-[#1e2d3d]' }}">
                        {{ $texto }}
                    </a>
                @endforeach
            </nav> -->

            @if ($opcoes->isNotEmpty())
                <form method="GET" action="{{ route('permissoes.index') }}" class="flex flex-wrap items-end gap-2.5">
                    <input type="hidden" name="tipo" value="{{ $tipo }}">
                    <label class="grid min-w-0 flex-1 gap-1.5 text-xs font-bold sm:max-w-lg">
                        {{ $tipo === 'grupo' ? 'Grupo de acesso' : 'Usuário' }}
                        <select name="id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium focus:border-[#886934] focus:outline-none focus:ring-1 focus:ring-[#886934]">
                            @foreach ($opcoes as $opcao)
                                <option value="{{ $opcao->getKey() }}" @selected($opcao->is($alvo))>
                                    {{ $tipo === 'grupo' ? $opcao->grupo_acesso : $opcao->usuario.' · '.$opcao->username }}
                                    @if (!$opcao->status) (Inativo) @endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="min-h-11 rounded-xl border border-[#b39359]/40 px-4 text-sm font-bold hover:bg-[#f4f1ea]">Carregar</button>
                </form>
            @else
                <p class="text-sm text-slate-600">{{ $tipo === 'grupo' ? 'Nenhum grupo de acesso cadastrado.' : 'Nenhum usuário cadastrado.' }}</p>
            @endif
        </section>

        @if ($alvo)
            <form method="POST" action="{{ route('permissoes.update', [$tipo, $alvo->getKey()]) }}"
                  class="rounded-2xl border border-[#b39359]/35 bg-white shadow-sm">
                @csrf
                @method('PUT')
                <header class="border-b border-slate-200 p-4 sm:px-6">
                    <h2 class="font-bold">{{ $tipo === 'grupo' ? $alvo->grupo_acesso : $alvo->usuario }}</h2>
                    @if ($admin)
                        <p class="mt-1 text-sm text-slate-600">A conta <strong>admin</strong> tem acesso integral ao sistema.</p>
                    @elseif ($tipo === 'usuario')
                        <p class="mt-1 text-sm text-slate-600">{{ $alvo->grupoAcesso?->grupo_acesso ?? 'Sem grupo de acesso' }}</p>
                    @endif
                </header>

                <div class="divide-y divide-slate-100">
                    @foreach ($modulos as $chave => $modulo)
                        @php
                            $origem = $tipo === 'grupo' ? 'Padrão do sistema' : 'Herdar do grupo';
                            $salvo = isset($regras[$chave]) ? (string) $regras[$chave] : 'herdar';
                            $selecionado = old('regras.'.$chave, $salvo);
                        @endphp
                        <div class="grid items-center gap-2 px-4 py-3 sm:grid-cols-[1fr_minmax(14rem,20rem)] sm:gap-4 sm:px-6">
                            <label for="regra-{{ $chave }}" class="text-sm font-semibold">{{ $modulo['rotulo'] }}</label>
                            @if ($admin)
                                <p id="regra-{{ $chave }}" class="text-sm text-slate-600">{{ $modulo['editavel'] ? 'Editar' : 'Visualizar' }}</p>
                            @else
                                <select id="regra-{{ $chave }}" name="regras[{{ $chave }}]"
                                        class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-[#886934] focus:outline-none focus:ring-1 focus:ring-[#886934]">
                                    <option value="herdar" @selected($selecionado === 'herdar')>{{ $origem }} · {{ $niveis[$modulo['herdado']] }}</option>
                                    @foreach ($niveis as $nivel => $rotulo)
                                        @continue($nivel === 2 && !$modulo['editavel'])
                                        <option value="{{ $nivel }}" @selected((string) $nivel === $selecionado)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endforeach
                </div>

                @unless ($admin)
                    <div class="flex justify-end border-t border-slate-200 p-4 sm:px-6">
                        <button type="submit" class="w-full rounded-xl bg-[#1e2d3d] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#30475e] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto">
                            Salvar permissões
                        </button>
                    </div>
                @endunless
            </form>
        @endif
    </div>
@endsection
