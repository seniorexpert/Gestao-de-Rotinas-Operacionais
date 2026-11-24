@extends('layouts.app')

@section('content')
<div>
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-[#101722] font-serif">Gestão de Acessos</h1>
        <p class="text-sm text-gray-500 mt-1">Gerencie os usuários e grupos de acesso do sistema.</p>
    </div>

    @if (session('status'))
        <p class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl" role="status">
            {{ session('status') }}
        </p>
    @endif

    @can('editar-modulo', 'usuarios')
    <button type="button" id="adicionarUsuario" class="mb-6 inline-flex items-center gap-2 px-4 py-2 border border-gray-900 rounded-lg text-sm font-medium text-gray-900 hover:bg-gray-50 transition">
        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
        Adicionar
    </button>
    @endcan
    @can('administrar-permissoes')
        <a href="{{ route('permissoes.index') }}" class="mb-6 inline-flex items-center gap-2 rounded-lg border border-[#b39359]/50 px-4 py-2 text-sm font-semibold hover:bg-[#f4f1ea]">Permissões de Acesso</a>
    @endcan

    <div class="border-b border-gray-200 mb-6 flex gap-8">
        <button type="button" data-secao="usuarios" aria-controls="usuarios" aria-pressed="true"
            class="pb-3 border-b-2 border-transparent text-sm text-gray-500 hover:text-gray-900 aria-pressed:border-gray-900 aria-pressed:font-medium aria-pressed:text-gray-900">
            Usuários
        </button>
        <button type="button" data-secao="gruposAcesso" aria-controls="gruposAcesso" aria-pressed="false"
            class="pb-3 border-b-2 border-transparent text-sm text-gray-500 hover:text-gray-900 aria-pressed:border-gray-900 aria-pressed:font-medium aria-pressed:text-gray-900">
            Grupos de Acesso
        </button>
    </div>

    <div class="mb-8 flex flex-col gap-3 sm:flex-row">
        <select id="filtroSituacao" aria-label="Filtrar por situação" class="rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700">
            <option value="">Todas as situações</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
        </select>
        <input type="search" id="buscaAcessos" aria-label="Buscar em Gestão de Acessos" placeholder="Buscar por nome, login ou código…"
            class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
    </div>

    <section id="usuarios" aria-label="Usuários">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @forelse ($usuarios as $usuario)
                @php
                    $fotoUrl = $usuario->photo ? asset('storage/'.$usuario->photo) : null;
                    $dadosEdicao = [
                        'id' => (string) $usuario->getKey(),
                        'url' => route('users.update', ['id' => $usuario->getKey()]),
                        'usuario' => $usuario->usuario,
                        'username' => $usuario->username,
                        'fotoUrl' => $fotoUrl,
                        'id_grupo_acesso' => (string) $usuario->id_grupo_acesso,
                        'status' => (int) $usuario->status,
                        'empresas' => array_map('strval', $usuario->empresas->modelKeys()),
                    ];
                @endphp

                <article class="bg-white border border-gray-200 rounded-2xl p-6 text-center flex flex-col justify-between"
                    data-busca="{{ $usuario->usuario }} {{ $usuario->username }} {{ $usuario->getKey() }}" data-status="{{ (int) $usuario->status }}">
                    <div>
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-600 overflow-hidden" aria-hidden="true">
                            @if ($fotoUrl)
                                <img src="{{ $fotoUrl }}" alt="" class="w-full h-full object-cover">
                            @else
                                <i class="fa-solid fa-user text-xl"></i>
                            @endif
                        </div>
                        <h2 class="font-bold text-gray-900 text-base break-words">{{ $usuario->usuario }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5 break-words">{{ $usuario->username }}</p>
                        <p class="mt-3 text-sm font-medium text-gray-700">{{ $usuario->grupoAcesso?->grupo_acesso ?? 'Sem grupo de acesso' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $usuario->empresas->pluck('empresa')->sort()->implode(', ') ?: 'Sem unidade vinculada' }}</p>
                        <span @class([
                            'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium mt-3',
                            'bg-emerald-50 text-emerald-700' => $usuario->status,
                            'bg-rose-50 text-rose-700' => ! $usuario->status,
                        ])>{{ $usuario->status ? 'Ativo' : 'Inativo' }}</span>
                    </div>

                    @if (auth()->user()->can('editar-modulo', 'usuarios') && (!$usuario->isAdmin() || auth()->user()->isAdmin()))
                    <button type="button" data-usuario-id="{{ $usuario->getKey() }}"
                        data-editar-usuario="{{ json_encode($dadosEdicao, JSON_THROW_ON_ERROR) }}"
                        class="w-full mt-6 py-2 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i>
                        Editar
                    </button>
                    @endif
                </article>
            @empty
                <p class="text-sm text-gray-500">Nenhum usuário cadastrado.</p>
            @endforelse
        </div>
    </section>

    <section id="gruposAcesso" aria-label="Grupos de Acesso" hidden>
        <div class="space-y-4 mb-8">
            @forelse ($gruposAcesso as $grupo)
                <article data-busca="{{ $grupo->grupo_acesso }}" data-status="{{ (int) $grupo->status }}"
                    class="bg-white border border-gray-200 rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 shrink-0 bg-gray-100 rounded-full flex items-center justify-center text-gray-600" aria-hidden="true">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-sm">{{ $grupo->grupo_acesso }}</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $grupo->usuarios_count }} {{ $grupo->usuarios_count == 1 ? 'usuário' : 'usuários' }}
                        </p>
                    </div>
                    <span @class([
                        'ml-auto shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-emerald-50 text-emerald-700' => $grupo->status,
                        'bg-rose-50 text-rose-700' => ! $grupo->status,
                    ])>{{ $grupo->status ? 'Ativo' : 'Inativo' }}</span>
                </article>
            @empty
                <p class="text-sm text-gray-500">Nenhum grupo de acesso cadastrado.</p>
            @endforelse
        </div>
    </section>
</div>

@can('editar-modulo', 'usuarios')
    @include('auth.partials.user-modal')
@endcan

<script>
    const buscaAcessos = document.getElementById('buscaAcessos');
    const filtroSituacao = document.getElementById('filtroSituacao');
    const abasAcessos = Array.from(document.querySelectorAll('[data-secao]'));
    const cartoesAcessos = document.querySelectorAll('[data-busca]');

    function filtrarAcessos() {
        const termo = buscaAcessos.value.trim().toLocaleLowerCase('pt-BR');
        const situacao = filtroSituacao.value;

        cartoesAcessos.forEach(cartao => {
            const corresponde = cartao.dataset.busca.toLocaleLowerCase('pt-BR').includes(termo)
                && (situacao === '' || cartao.dataset.status === situacao);
            cartao.style.display = corresponde ? '' : 'none';
        });
    }

    abasAcessos.forEach(aba => aba.addEventListener('click', () => {
        abasAcessos.forEach(item => {
            item.setAttribute('aria-pressed', String(item === aba));
            document.getElementById(item.dataset.secao).hidden = item !== aba;
        });
        buscaAcessos.placeholder = aba.dataset.secao === 'usuarios' ? 'Buscar por nome, login ou código…' : 'Buscar grupo de acesso…';
    }));

    buscaAcessos.addEventListener('input', filtrarAcessos);
    filtroSituacao.addEventListener('change', filtrarAcessos);
</script>
@endsection
