<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão de Rotinas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --theme-azul-esc: #101722;
            --theme-azul: #1e293b;
            --theme-azul-light: #2c3e55;
            --theme-senior-azul: #193B50;
        }
        body {
            font-family: 'Instrument Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-white min-h-screen flex items-center justify-center relative overflow-hidden">

    <svg width="0" height="0" aria-hidden="true" focusable="false" style="position: absolute;">
        <defs>
            <linearGradient id="gAzul" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="var(--theme-azul-esc)"></stop>
                <stop offset=".55" stop-color="var(--theme-azul-light)"></stop>
                <stop offset="1" stop-color="var(--theme-azul)"></stop>
            </linearGradient>
            <linearGradient id="gOuro" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#D9BC8A"></stop>
                <stop offset=".5" stop-color="#B48B55"></stop>
                <stop offset="1" stop-color="#D9BC8A"></stop>
            </linearGradient>
            <linearGradient id="gVidro" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#5F859D" stop-opacity=".55"></stop>
                <stop offset="1" stop-color="#A5BCCD" stop-opacity=".25"></stop>
            </linearGradient>
            <symbol id="canto-tl" viewBox="0 0 240 200">
                <path d="M-2 -2H216C178 52 126 85 78 115C44 136 15 161 -2 184Z" fill="url(#gVidro)"></path>
                <path d="M-2 -2H200C166 44 118 74 70 103C38 123 12 148 -2 170Z" fill="url(#gAzul)"></path>
                <path d="M228 -2C192 54 134 89 84 119C49 140 21 167 -2 194" stroke="url(#gOuro)" stroke-width="1.4" stroke-linecap="round" fill="none"></path>
            </symbol>
            <symbol id="canto-br" viewBox="0 0 240 200">
                <path d="M242 202H24C62 148 114 115 162 85C196 64 225 39 242 16Z" fill="url(#gVidro)"></path>
                <path d="M242 202H40C74 156 122 126 170 97C202 77 228 52 242 30Z" fill="url(#gAzul)"></path>
                <path d="M12 202C48 146 106 111 156 81C191 60 219 33 242 6" stroke="url(#gOuro)" stroke-width="1.4" stroke-linecap="round" fill="none"></path>
            </symbol>
            <symbol id="ornamento" viewBox="0 0 84 12">
                <path d="M0 6H30M54 6H84" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
                <path d="M42 1.5L46.5 6L42 10.5L37.5 6Z" fill="currentColor"></path>
            </symbol>
        </defs>
    </svg>

    <svg class="absolute top-0 left-0 w-64 md:w-80 h-auto pointer-events-none -z-10" viewBox="0 0 240 200">
        <use href="#canto-tl"></use>
    </svg>

    <svg class="absolute bottom-0 right-0 w-64 md:w-80 h-auto pointer-events-none -z-10" viewBox="0 0 240 200">
        <use href="#canto-br"></use>
    </svg>

    <div class="w-full max-w-sm p-6 z-10">
        
        <div class="text-center mb-8">
            <img src="{{ asset('images/Logo_Senior_Expert_azul.png') }}" alt="Senior Expert" class="mx-auto h-16 w-auto mb-2 object-contain">

            <div class="flex items-center justify-center my-3 text-[#b49b78]">
                <svg class="w-20 h-4" viewBox="0 0 84 12">
                    <use href="#ornamento"></use>
                </svg>
            </div>
            <p class="m-0 text-center text-[1.0625rem] leading-[1.55] text-[#193B50] font-normal uppercase tracking-wider">Gestão de Rotinas</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 @error('username') text-red-500 @else text-gray-400 @enderror">
                    <i class="fa-regular fa-user"></i>
                </span>
                <input type="text" name="username" value="{{ old('username') }}" required autofocus
                    oninvalid="this.setCustomValidity('Por favor, preencha este campo.')"
                    oninput="this.setCustomValidity('')"
                    placeholder="Usuário" 
                    class="w-full pl-11 pr-4 py-3.5 bg-white border @error('username') border-red-500 text-red-900 focus:border-red-500 @else border-gray-200 text-gray-700 focus:border-[#1e293b] @enderror rounded-full text-sm focus:outline-none shadow-sm">
            </div>

            @error('username')
                <p class="text-xs text-[#a03828] mb-4 ml-4">Usuário ou senha incorretos.</p>
            @enderror

            <div class="mb-6 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 @error('password') text-red-500 @else text-gray-400 @enderror">
                    <i class="fa-solid fa-lock"></i>
                </span>
                <input type="password" name="password" id="password" required
                    oninvalid="this.setCustomValidity('Por favor, preencha este campo.')"
                    oninput="this.setCustomValidity('')"
                    placeholder="Senha" 
                    class="w-full pl-11 pr-12 py-3.5 bg-white border @error('password') border-red-500 text-red-900 focus:border-red-500 @else border-gray-200 text-gray-700 focus:border-[#1e293b] @enderror rounded-full text-sm focus:outline-none shadow-sm">
                <span id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 cursor-pointer">
                    <i id="eyeIcon" class="fa-regular fa-eye-slash"></i>
                </span>
            </div>

            <button type="submit" 
                class="w-full py-3.5 px-4 bg-[#1e293b] hover:bg-[#2e3b4e] text-white font-medium rounded-full shadow-lg transition duration-200 flex items-center justify-center space-x-2">
                <span>Entrar</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>

            <div class="text-center mt-6">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs text-gray-400 hover:text-gray-500 transition">
                        Esqueci minha senha?
                    </a>
                @endif
            </div>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            if (type === 'password') {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>