@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Alterar Palavra-passe</div>

                <div class="card-body">
                    {{-- Mensagem de Sucesso --}}
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.update.custom') }}">
                        @csrf

                        {{-- Palavra-passe Atual --}}
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Palavra-passe Atual</label>
                            <input id="current_password" type="password" 
                                   class="form-control @error('current_password') is-invalid @enderror" 
                                   name="current_password" required>

                            @error('current_password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        {{-- Nova Palavra-passe --}}
                        <div class="mb-3">
                            <label for="password" class="form-label">Nova Palavra-passe</label>
                            <input id="password" type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   name="password" required>

                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        {{-- Confirmar Nova Palavra-passe --}}
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar Nova Palavra-passe</label>
                            <input id="password_confirmation" type="password" 
                                   class="form-control" 
                                   name="password_confirmation" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                Guardar Nova Palavra-passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection