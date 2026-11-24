@props(['empresas', 'padrao', 'id', 'multiplo' => false])

@php
    $marcadas = (array) old('id_empresas', $padrao === null ? [] : [$padrao]);
    $selecionada = old('id_empresa', $padrao);
@endphp

@if ($multiplo)
    <fieldset id="{{ $id }}">
        <legend class="mb-2 text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Unidade</legend>
        @if ($empresas->count() > 1)
            <p class="mb-2 text-xs text-slate-600">Selecione uma ou mais unidades.</p>
        @endif
        <div class="grid gap-2 sm:grid-cols-2">
            @forelse ($empresas as $empresa)
                <label class="flex min-w-0 items-center gap-2 rounded-xl border border-gray-300 px-3 py-2.5 text-sm text-gray-800">
                    <input type="checkbox" name="id_empresas[]" value="{{ $empresa->id_empresa }}" data-unidade-cadastro
                           @checked(in_array($empresa->id_empresa, $marcadas)) class="h-4 w-4 shrink-0 accent-[#193b50]">
                    <span class="break-words">{{ $empresa->empresa }}</span>
                </label>
            @empty
                <p class="text-sm text-slate-600">Nenhuma unidade disponível.</p>
            @endforelse
        </div>
    </fieldset>
@else
    <div>
        <label for="{{ $id }}" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">Unidade</label>
        <select id="{{ $id }}" name="id_empresa" data-unidade-cadastro required
                class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none">
            <option value="" disabled @selected($selecionada === null)>Selecione a unidade</option>
            @foreach ($empresas as $empresa)
                <option value="{{ $empresa->id_empresa }}" @selected($empresa->id_empresa == $selecionada)>{{ $empresa->empresa }}</option>
            @endforeach
        </select>
    </div>
@endif

@once
    <script>
        function preencherUnidadesCadastro(formulario, unidades) {
            const ids = unidades.map(String);
            const campos = formulario.querySelectorAll('[data-unidade-cadastro]');
            for (const campo of campos) {
                if (campo.type === 'checkbox') {
                    campo.checked = ids.includes(campo.value);
                } else {
                    campo.value = ids[0] ?? '';
                }
            }
            campos[0]?.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.addEventListener('submit', (evento) => {
            const unidades = [...evento.target.querySelectorAll('input[data-unidade-cadastro]')];
            if (unidades.length && !unidades.some(unidade => unidade.checked)) {
                evento.preventDefault();
                unidades[0].setCustomValidity('Selecione pelo menos uma unidade.');
                unidades[0].reportValidity();
                unidades[0].setCustomValidity('');
            }
        });
    </script>
@endonce
