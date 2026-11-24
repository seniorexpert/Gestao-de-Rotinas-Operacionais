@props(['id', 'nome' => 'status', 'rotulo' => 'Situação'])

@php($inativo = in_array(old($nome), [0, '0', 'Inativa'], true))

<div>
    <label for="{{ $id }}" class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#1e2d3d]">{{ $rotulo }}</label>
    <select id="{{ $id }}" name="{{ $nome }}" required
            class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3.5 text-sm text-gray-800 focus:border-[#1e2d3d] focus:outline-none">
        <option value="1" @selected(! $inativo)>Ativo</option>
        <option value="0" @selected($inativo)>Inativo</option>
    </select>
</div>
