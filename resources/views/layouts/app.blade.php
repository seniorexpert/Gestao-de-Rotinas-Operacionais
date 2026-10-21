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
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col justify-between text-gray-800">

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden transition-opacity"></div>
    <aside id="sidebar" class="fixed top-0 left-0 w-72 h-full bg-white border-r border-gray-200 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col justify-between p-6">
        <div>
            <div class="flex items-center justify-between mb-8">
                <img src="{{ asset('images/Logo_Senior_Expert_azul.png') }}" alt="Senior Expert" class="h-8 w-auto object-contain">
                <button id="closeSidebar" class="text-gray-500 hover:text-gray-800 focus:outline-none">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <nav class="space-y-2">
                <a href="#" class="flex items-center space-x-3 px-4 py-3 bg-gray-100 text-[#101722] font-medium rounded-xl">
                    <i class="fa-solid fa-house text-sm"></i>
                    <span>Rotinas</span>
                </a>
            </nav>
        </div>
        <div class="border-t border-gray-100 pt-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition font-medium">
                    <i class="fa-solid fa-right-from-bracket text-sm"></i>
                    <span>Sair</span>
                </button>
            </form>
        </div>
    </aside>

    <div>
        <header class="border-b border-gray-100 px-4 sm:px-6 py-4 flex items-center justify-between relative">
            <div class="flex items-center space-x-4">
                <button id="openSidebar" class="text-gray-700 hover:text-gray-900 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <div class="absolute left-1/2 transform -translate-x-1/2 max-w-[120px] sm:max-w-none">
                <img src="{{ asset('images/Logo_Senior_Expert_azul.png') }}" alt="Senior Expert" class="h-8 sm:h-10 w-auto object-contain">
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                <!-- <button class="relative text-gray-600 hover:text-gray-900 focus:outline-none p-1">
                    <i class="fa-regular fa-bell text-base sm:text-lg"></i>
                    <span class="absolute -top-1 -right-1 bg-[#1e293b] text-white text-[9px] font-bold rounded-full h-3.5 w-3.5 sm:h-4 sm:w-4 flex items-center justify-center">3</span>
                </button> -->
                
                <div class="relative flex items-center space-x-2 border-l pl-2 sm:pl-4 border-gray-200">
                    <span class="text-sm font-medium text-gray-700 hidden md:inline">{{ Auth::user()->name ?? Auth::user()->username ?? 'Utilizador' }}</span>
                    
                    <button id="profileDropdownBtn" class="flex items-center space-x-1 focus:outline-none">
                        <div class="h-8 w-8 sm:h-9 sm:w-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 border border-gray-200 hover:bg-gray-200 transition">
                            <i class="fa-regular fa-user text-xs sm:text-sm"></i>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-gray-400"></i>
                    </button>

                    <div id="profileDropdownMenu" class="hidden absolute right-0 top-12 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                        <div class="w-full">
                        <a href="{{ url('/mudar-senha') }}" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2 transition">
                            <i class="fa-solid fa-user"></i>
                            <span>Área do usuário</span>
                        </a>

                        <a href="{{ url('/gestao-acessos') }}" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2 transition">
                            <i class="fa-solid fa-users"></i>
                            <span>Gestão de acessos</span>
                        </a>

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
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        }

        openSidebar.addEventListener('click', toggleSidebar);
        closeSidebar.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

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
    </script>
</body>
</html>