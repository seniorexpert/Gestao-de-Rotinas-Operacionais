<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Senior Expert')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
        }

        .senior-sidebar {
            --sidebar-ink: #223a4e;
            --sidebar-gold: #947546;
            --sidebar-highlight: #fbf9f4;
            box-sizing: border-box;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 50;
            display: flex;
            flex-direction: column;
            width: min(100vw, 320px);
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            border: 1px solid #d7d7d7;
            border-radius: 9px 0 0 9px;
            background: #fff;
            color: var(--sidebar-ink);
            font: 500 14px/1.25 'Instrument Sans', sans-serif;
            transform: translateX(0);
            visibility: visible;
            transition: transform 240ms ease, visibility 240ms;
        }

        .senior-sidebar.-translate-x-full {
            transform: translateX(-100%);
            visibility: hidden;
        }

        .senior-sidebar__symbols {
            position: absolute;
            pointer-events: none;
        }

        .senior-sidebar * {
            box-sizing: border-box;
        }

        .senior-sidebar__header,
        .senior-sidebar__nav,
        .senior-sidebar__utility,
        .senior-sidebar__account {
            flex-shrink: 0;
        }

        .senior-sidebar__header {
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 73px;
            padding: 9px 15px 6px;
            background: #fff;
        }

        .senior-sidebar__brand img {
            display: block;
            width: 110px;
            height: auto;
            max-height: 49px;
            object-fit: contain;
            object-position: left center;
        }

        .senior-sidebar__close {
            display: grid;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: inherit;
            cursor: pointer;
        }

        .senior-sidebar__close svg {
            width: 24px;
            height: 24px;
        }

        .senior-sidebar__nav {
            padding: 0 12px 8px;
        }

        .senior-sidebar__item {
            box-sizing: border-box;
            display: flex;
            width: 100%;
            min-height: 38px;
            align-items: center;
            gap: 13px;
            padding: 5px 13px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: inherit;
            font: inherit;
            letter-spacing: 0.08px;
            text-align: left;
            text-decoration: none;
        }

        .senior-sidebar a.senior-sidebar__item,
        .senior-sidebar button.senior-sidebar__item {
            cursor: pointer;
        }

        .senior-sidebar a.senior-sidebar__item:hover,
        .senior-sidebar button.senior-sidebar__item:hover,
        .senior-sidebar__close:hover,
        .senior-sidebar__item[aria-current="page"] {
            background: var(--sidebar-highlight);
        }

        .senior-sidebar__item[aria-current="page"],
        .senior-sidebar__reports-toggle {
            font-weight: 700;
        }

        .senior-sidebar__item:focus-visible,
        .senior-sidebar__close:focus-visible,
        .senior-sidebar__brand:focus-visible {
            outline: 2px solid var(--sidebar-gold);
            outline-offset: -2px;
        }

        .senior-sidebar__icon {
            width: 22px;
            height: 22px;
            flex: 0 0 22px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .senior-sidebar__chevron {
            width: 14px;
            height: 14px;
            flex: 0 0 14px;
            margin-inline-start: auto;
            transition: transform 180ms ease;
        }

        .senior-sidebar__chevron.rotate-180 {
            transform: rotate(180deg);
        }

        .senior-sidebar__reports {
            margin-inline: 20px -6px;
            padding-top: 8px;
        }

        .senior-sidebar__reports.hidden {
            display: none;
        }

        .senior-sidebar__report-group + .senior-sidebar__report-group {
            margin-top: 7px;
        }

        .senior-sidebar__group-title {
            margin: 0 0 4px;
            padding-inline: 12px;
            color: var(--sidebar-gold);
            font-size: 11px;
            font-weight: 700;
            line-height: 16px;
            text-transform: uppercase;
            letter-spacing: 0.1px;
        }

        .senior-sidebar__reports .senior-sidebar__item,
        .senior-sidebar__utility .senior-sidebar__item {
            min-height: 34px;
            gap: 12px;
            padding-inline: 12px;
        }

        .senior-sidebar__reports .senior-sidebar__icon,
        .senior-sidebar__utility .senior-sidebar__icon {
            width: 20px;
            height: 20px;
            flex-basis: 20px;
        }

        .senior-sidebar__utility {
            padding: 5px 12px 6px;
            border-top: 1px solid #efe8dd;
        }

        .senior-sidebar__utility form {
            margin: 0;
        }

        .senior-sidebar__logout {
            color: var(--sidebar-gold);
        }

        .senior-sidebar__account {
            display: flex;
            min-height: 52px;
            align-items: center;
            gap: 12px;
            margin-top: auto;
            padding: 9px 20px 8px;
            border-top: 1px solid #e8ebed;
        }

        .senior-sidebar__avatar {
            display: grid;
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            place-items: center;
            overflow: hidden;
            border-radius: 50%;
            background: #f4f5f6;
        }

        .senior-sidebar__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .senior-sidebar__avatar svg {
            width: 16px;
            height: 16px;
        }

        .senior-sidebar__account-text {
            min-width: 0;
        }

        .senior-sidebar__name,
        .senior-sidebar__role {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .senior-sidebar__name {
            font-weight: 700;
        }

        .senior-sidebar__role {
            margin-top: 2px;
            color: #607586;
            font-size: 12px;
        }

        @media (prefers-reduced-motion: reduce) {
            .senior-sidebar,
            .senior-sidebar__chevron {
                transition: none;
            }
        }
    </style>
    @stack('head')
</head>
<body class="bg-white min-h-screen flex flex-col justify-between text-gray-800">

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden transition-opacity"></div>
    
    @php
        $sidebarUser = Auth::user();
        $sidebarReports = [
            'Cadastros' => [
                ['Relatório de Rotinas', 'relatorios.rotinas', 'calendar-days'],
                ['Relatório de Tarefas', 'relatorios.tarefas', 'list-checks'],
                ['Relatório de Ferramentas', 'relatorios.ferramentas', 'wrench'],
                ['Relatório de Locais', 'relatorios.locais', 'map-pin'],
            ],
            'Históricos e Análise' => [
                ['Relatório de Rotinas Finalizadas', 'relatorios.rotinas-finalizadas', 'clipboard-check'],
                ['Relatório de Rotinas Excel', 'relatorios.excel', 'file-spreadsheet'],
                [$sidebarUser?->can('gerenciar-usuarios') ? 'Logs e Auditoria' : 'Logs de Rotinas', 'relatorios.logs-rotinas', 'scroll-text'],
            ],
        ];
    @endphp

    <aside id="sidebar" class="senior-sidebar -translate-x-full" aria-label="Menu lateral">
        <svg class="senior-sidebar__symbols" xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true">
            <symbol id="senior-menu-clipboard-list" viewBox="0 0 24 24">
                <rect x="8" y="3" width="8" height="4" rx="1"/>
                <path d="M16 5h2a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2M8 11h.01M8 16h.01M12 11h4M12 16h4"/>
            </symbol>
            <symbol id="senior-menu-activity" viewBox="0 0 24 24">
                <path d="M22 12h-3.3a2 2 0 0 0-1.9 1.4L15 22 9 2l-2.8 8.6A2 2 0 0 1 4.3 12H2"/>
            </symbol>
            <symbol id="senior-menu-clipboard-check" viewBox="0 0 24 24">
                <rect x="8" y="3" width="8" height="4" rx="1"/>
                <path d="M16 5h2a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m1 9 2 2 4-4"/>
            </symbol>
            <symbol id="senior-menu-circle-play" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9 8.8a.8.8 0 0 1 1.2-.7l5.3 3.2a.8.8 0 0 1 0 1.4l-5.3 3.2a.8.8 0 0 1-1.2-.7z"/>
            </symbol>
            <symbol id="senior-menu-pie-chart" viewBox="0 0 24 24">
                <path d="M21.3 15.9A10 10 0 1 1 8.1 2.7M12 2v9a1 1 0 0 0 1 1h9A10 10 0 0 0 12 2Z"/>
            </symbol>
            <symbol id="senior-menu-chart-column" viewBox="0 0 24 24">
                <path d="M3 3v16a2 2 0 0 0 2 2h16M8 17v-4m5 4V5m5 12V9"/>
            </symbol>
            <symbol id="senior-menu-calendar-days" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <path d="M16 2v4M8 2v4M3 10h18m-13 5h.01M12 15h.01M16 15h.01M8 18h.01M12 18h.01M16 18h.01"/>
            </symbol>
            <symbol id="senior-menu-list-checks" viewBox="0 0 24 24">
                <path d="m3 6 2 2 4-4m2 2h10m-10 7h10m-10 7h10M3 16l2 2 4-4"/>
            </symbol>
            <symbol id="senior-menu-wrench" viewBox="0 0 24 24">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-7.7 7.7a2.1 2.1 0 0 1-3-3l7.7-7.7a6 6 0 0 1 7.9-7.9z"/>
            </symbol>
            <symbol id="senior-menu-map-pin" viewBox="0 0 24 24">
                <path d="M20 10c0 5-6 10.4-7.6 11.7a.7.7 0 0 1-.8 0C10 20.4 4 15 4 10a8 8 0 1 1 16 0Z"/>
                <circle cx="12" cy="10" r="3"/>
            </symbol>
            <symbol id="senior-menu-file-spreadsheet" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M8 13h2m4 0h2m-8 4h2m4 0h2"/>
            </symbol>
            <symbol id="senior-menu-scroll-text" viewBox="0 0 24 24">
                <path d="M15 12H9m6-4H9m11 8V4a2 2 0 0 0-2-2H4m0 0a2 2 0 0 1 2 2v16a2 2 0 0 0 4 0v-2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H8M4 2a2 2 0 0 0-2 2v3h4"/>
            </symbol>
            <symbol id="senior-menu-circle-help" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4m.1 3h.01"/>
            </symbol>
            <symbol id="senior-menu-user" viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="5"/>
                <path d="M4 22a8 8 0 0 1 16 0"/>
            </symbol>
            <symbol id="senior-menu-logout" viewBox="0 0 24 24">
                <path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5m6-14 5 5-5 5m5-5H9"/>
            </symbol>
        </svg>

        <div class="senior-sidebar__header">
            <a class="senior-sidebar__brand" href="{{ route('home') }}">
                <img src="{{ asset('images/Logo_Senior_Expert_azul.png') }}" alt="Senior Expert">
            </a>
            <button type="button" id="closeSidebar" class="senior-sidebar__close" aria-label="Fechar menu lateral">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18"/>
                </svg>
            </button>
        </div>

        <nav class="senior-sidebar__nav" aria-label="Navegação principal">
            <a class="senior-sidebar__item" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-clipboard-list"/></svg>
                <span>Rotinas Diárias</span>
            </a>

            @can('acompanhar-rotinas')
                <a class="senior-sidebar__item" href="{{ route('acompanhamento.index') }}" @if (request()->routeIs('acompanhamento.*')) aria-current="page" @endif>
                    <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-clipboard-check"/></svg>
                    <span>Rotina de Acompanhamento</span>
                </a>
            @endcan

            <a class="senior-sidebar__item" href="{{ route('monitoramento.index') }}" @if (request()->routeIs('monitoramento.*')) aria-current="page" @endif>
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-activity"/></svg>
                <span>Centro de Monitoramento</span>
            </a>

            <a class="senior-sidebar__item" href="{{ route('processos.index') }}" @if (request()->routeIs('processos.*')) aria-current="page" @endif>
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-circle-play"/></svg>
                <span>Iniciar Processos</span>
            </a>

            <a class="senior-sidebar__item" href="{{ route('relatorios.estatisticas') }}" @if (request()->routeIs('relatorios.estatisticas')) aria-current="page" @endif>
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-pie-chart"/></svg>
                <span>Estatísticas</span>
            </a>

            <button type="button" id="toggleRelatorios" class="senior-sidebar__item senior-sidebar__reports-toggle" aria-controls="relatoriosMenu" aria-expanded="true">
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-chart-column"/></svg>
                <span>Relatórios</span>
                <svg id="relatoriosChevron" class="senior-sidebar__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 15 7-7 7 7"/>
                </svg>
            </button>

            <div id="relatoriosMenu" class="senior-sidebar__reports">
                @foreach ($sidebarReports as $group => $reports)
                    <div class="senior-sidebar__report-group">
                        <p class="senior-sidebar__group-title">{{ $group }}</p>
                        @foreach ($reports as [$label, $routeName, $icon])
                            @php
                                $hasRoute = \Illuminate\Support\Facades\Route::has($routeName);
                                $tag = $hasRoute ? 'a' : 'span';
                            @endphp
                            <{{ $tag }} class="senior-sidebar__item"
                                @if ($hasRoute)
                                    href="{{ route($routeName) }}"
                                    @if (request()->routeIs($routeName)) aria-current="page" @endif
                                @else
                                    role="link" aria-disabled="true"
                                @endif
                            >
                                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-{{ $icon }}"/></svg>
                                <span>{{ $label }}</span>
                            </{{ $tag }}>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </nav>

        <nav class="senior-sidebar__utility" aria-label="Ajuda e conta">
            <span class="senior-sidebar__item" role="link" aria-disabled="true">
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-circle-help"/></svg>
                <span>Central de Ajuda</span>
            </span>
            <a class="senior-sidebar__item" href="{{ url('/mudar-senha') }}" @if (request()->is('mudar-senha')) aria-current="page" @endif>
                <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-user"/></svg>
                <span>Meu perfil</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="senior-sidebar__item senior-sidebar__logout">
                    <svg class="senior-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#senior-menu-logout"/></svg>
                    <span>Sair</span>
                </button>
            </form>
        </nav>

        <div class="senior-sidebar__account">
            <div class="senior-sidebar__avatar" aria-hidden="true">
                @if (!empty($sidebarUser?->photo))
                    <img src="{{ asset('storage/' . $sidebarUser->photo) }}" alt="">
                @else
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20v-1.5C4 15.5 8 14 12 14s8 1.5 8 4.5V20z"/>
                    </svg>
                @endif
            </div>
            <div class="senior-sidebar__account-text">
                <p class="senior-sidebar__name">{{ $sidebarUser?->usuario ?: ($sidebarUser?->name ?: ($sidebarUser?->username ?: 'Usuário')) }}</p>
                <p class="senior-sidebar__role">{{ $sidebarUser?->grupo_acesso_nome ?: 'Usuário' }}</p>
            </div>
        </div>
    </aside>

    <div>
        <header class="border-b border-gray-100 px-4 sm:px-6 py-4 flex items-center justify-between relative">
            <div class="flex items-center space-x-4">
                <button type="button" id="openSidebar" class="text-gray-700 hover:text-gray-900 focus:outline-none" aria-label="Abrir menu lateral" aria-controls="sidebar" aria-expanded="false">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <div class="absolute left-1/2 transform -translate-x-1/2 max-w-[120px] sm:max-w-none">
                <a href="{{ url('/home') }}">
                    <img src="{{ asset('images/Logo_Senior_Expert_azul.png') }}" alt="Senior Expert" class="h-8 sm:h-10 w-auto object-contain">
                </a>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                <div class="relative flex items-center space-x-2 border-l pl-2 sm:pl-4 border-gray-200">
                    <span class="text-sm font-medium text-gray-700 hidden md:inline">{{ Auth::user()->name ?? Auth::user()->username ?? 'Utilizador' }}</span>
                    
                    <button id="profileDropdownBtn" class="flex items-center space-x-1 focus:outline-none">
                        <div class="h-8 w-8 sm:h-9 sm:w-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 border border-gray-200 hover:bg-gray-200 transition overflow-hidden">
                            @if(Auth::check() && !empty(Auth::user()->photo))
                                <img src="{{ asset('storage/' . Auth::user()->photo) }}" alt="Foto de perfil" class="w-full h-full object-cover">
                            @else
                                <i class="fa-regular fa-user text-xs sm:text-sm"></i>
                            @endif
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-gray-400"></i>
                    </button>

                    <div id="profileDropdownMenu" class="hidden absolute right-0 top-12 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                        <div class="w-full">
                            <a href="{{ url('/mudar-senha') }}" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2 transition">
                                <i class="fa-solid fa-user"></i>
                                <span>Área do usuário</span>
                            </a>

                            @can('gerenciar-usuarios')
                                <a href="{{ route('usuarios.index') }}" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2 transition">
                                    <i class="fa-solid fa-users"></i>
                                    <span>Gestão de acessos</span>
                                </a>
                            @endcan

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center space-x-2 transition">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    <span>Sair</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main>
            @if (request()->routeIs('home', 'execucoes.realizando', 'processos.*', 'monitoramento.index', 'relatorios.rotinas', 'relatorios.tarefas', 'relatorios.locais', 'relatorios.ferramentas'))
                @if (session('success'))
                    <p class="mx-auto mt-4 max-w-6xl rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                        {{ session('success') }}
                    </p>
                @endif
                @if ($errors->any())
                    <ul class="mx-auto mt-4 max-w-6xl rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        @foreach ($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
            @yield('content')
        </main>
    </div>

    <footer class="text-center py-6 text-xs text-gray-400 border-t border-gray-100 mt-12">
        © 2026 - Senior Expert
    </footer>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const openSidebar = document.getElementById('openSidebar');
        const closeSidebar = document.getElementById('closeSidebar');

        function toggleSidebar() {
            const expanded = !sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden', !expanded);
            openSidebar.setAttribute('aria-expanded', String(expanded));

            if (expanded) {
                sidebar.scrollTop = 0;
                closeSidebar.focus({ preventScroll: true });
            } else {
                openSidebar.focus({ preventScroll: true });
            }
        }

        openSidebar.addEventListener('click', toggleSidebar);
        closeSidebar.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !event.defaultPrevented && !sidebar.classList.contains('-translate-x-full')) {
                event.preventDefault();
                toggleSidebar();
            }
        });

        const profileBtn = document.getElementById('profileDropdownBtn');
        const profileMenu = document.getElementById('profileDropdownMenu');

        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileMenu.classList.toggle('hidden');
        });

        window.addEventListener('click', function () {
            if (!profileMenu.classList.contains('hidden')) {
                profileMenu.classList.add('hidden');
            }
        });

        const toggleRelatorios = document.getElementById('toggleRelatorios');
        const relatoriosMenu = document.getElementById('relatoriosMenu');
        const relatoriosChevron = document.getElementById('relatoriosChevron');

        toggleRelatorios.addEventListener('click', function () {
            const expanded = !relatoriosMenu.classList.toggle('hidden');
            toggleRelatorios.setAttribute('aria-expanded', String(expanded));
            relatoriosChevron.classList.toggle('rotate-180', !expanded);
        });
    </script>
</body>
</html>
