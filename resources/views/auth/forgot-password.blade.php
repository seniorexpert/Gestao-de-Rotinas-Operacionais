@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto px-4 py-12">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Meu Perfil</h1>
        <p class="text-sm text-gray-500 mt-0.5">Atualize a sua senha de acesso</p>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('status') }}
        </div>
    @endif

    <div id="password-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl">
        As senhas não coincidem. Por favor, verifique os campos.
    </div>

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="update-password-form" method="POST" action="{{ route('password.update.custom') }}" class="space-y-5">
        @csrf

        <div>
            <label for="password" class="block text-sm font-medium text-gray-900 mb-1.5">
                Nova Senha
            </label>
            <input id="password" type="password" name="password" required
                   class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-900 mb-1.5">
                Confirmar Nova Senha
            </label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
        </div>

        <div class="pt-2">
            <button type="submit" class="px-5 py-2.5 bg-[#121619] hover:bg-black text-white font-medium text-sm rounded-lg transition shadow-sm">
                Salvar Alterações
            </button>
        </div>
    </form>
</div>

<script>
    document.getElementById('update-password-form').addEventListener('submit', function(event) {
        const password = document.getElementById('password').value;
        const confirmation = document.getElementById('password_confirmation').value;
        const errorAlert = document.getElementById('password-error');

        if (password !== confirmation) {
            event.preventDefault();
            errorAlert.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            errorAlert.classList.add('hidden');
        }
    });
</script>
@endsection