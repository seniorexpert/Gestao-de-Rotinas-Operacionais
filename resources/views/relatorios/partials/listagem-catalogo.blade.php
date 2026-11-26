<ul id="{{ $idCartoes }}" class="space-y-3 lg:hidden" aria-label="{{ $rotuloLista }}">
    @foreach ($registros as $registro)
        <x-cartao-registro-relatorio :identificador="$registro['id']" :titulo="$registro['nome']"
                                    :campos="$registro['campos']" data-id-registro="{{ $registro['id'] }}">
            @isset($registro['icone'])
                <x-slot:icone>
                    <x-icone-tarefa :icone="$registro['icone']" :cor="$registro['cor_icone']" class="h-8 w-8 text-2xl" />
                </x-slot:icone>
            @endisset
            <div class="flex min-w-0 flex-wrap items-center gap-x-1.5">
                <dt class="font-semibold text-[#536b7b]">Situação:</dt>
                <dd class="min-w-0">
                    <x-selo-situacao-relatorio :ativo="$registro['ativo']" :rotulo="$registro['ativo'] ? 'Ativo' : 'Inativo'" />
                </dd>
            </div>
            <x-slot:acao>
                @include('relatorios.partials.editar-registro-catalogo', ['dadosEdicao' => $registro['edicao']])
            </x-slot:acao>
        </x-cartao-registro-relatorio>
    @endforeach
</ul>

<div class="hidden overflow-hidden rounded-xl border border-[#e4ceb2] bg-white shadow-sm lg:block">
    <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#17384b]"
         role="region" aria-label="{{ $rotuloLista }}" tabindex="0">
        <table id="{{ $idTabela }}" class="w-full {{ $larguraTabela }} border-collapse text-left text-sm">
            <thead class="border-b border-[#e8d6bf] bg-[#f9f7f2] text-xs font-semibold uppercase text-[#536b7b]">
                <tr>
                    <th scope="col" class="w-20 px-4 py-3.5">ID</th>
                    <th scope="col" class="px-4 py-3.5">{{ $rotuloNome }}</th>
                    @foreach ($colunas as $rotulo => $largura)
                        <th scope="col" class="{{ $largura }} px-4 py-3.5">{{ $rotulo }}</th>
                    @endforeach
                    <th scope="col" class="w-28 px-4 py-3.5">Situação</th>
                    <th scope="col" class="w-24 px-4 py-3.5 text-right">Ação</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registros as $registro)
                    <tr class="border-b border-[#f0e9df]" data-id-registro="{{ $registro['id'] }}" data-situacao="{{ $registro['ativo'] ? '1' : '0' }}">
                        <td class="px-4 py-4 font-semibold tabular-nums text-[#536b7b]">#{{ $registro['id'] }}</td>
                        <td class="px-4 py-4">
                            <div class="flex min-w-0 items-center gap-3">
                                @isset($registro['icone'])
                                    <x-icone-tarefa :icone="$registro['icone']" :cor="$registro['cor_icone']" class="h-8 w-8 text-2xl" />
                                @endisset
                                <span data-nome-catalogo class="min-w-0 break-words font-semibold uppercase">{{ $registro['nome'] }}</span>
                            </div>
                        </td>
                        @foreach ($colunas as $rotulo => $largura)
                            <td @class(['break-words px-4 py-4', 'tabular-nums' => $rotulo === 'Duração'])>{{ $registro['campos'][$rotulo] }}</td>
                        @endforeach
                        <td class="px-4 py-4">
                            <x-selo-situacao-relatorio :ativo="$registro['ativo']" :rotulo="$registro['ativo'] ? 'Ativo' : 'Inativo'" />
                        </td>
                        <td class="px-4 py-4 text-right">
                            @include('relatorios.partials.editar-registro-catalogo', ['dadosEdicao' => $registro['edicao']])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<p id="{{ $idMensagemVazia }}" @if ($registros->isNotEmpty()) hidden @endif
   class="rounded-xl border border-[#e8d6bf] bg-[#f9f7f2] px-4 py-8 text-center text-sm text-[#536b7b]">
    {{ $mensagemVazia }}
</p>
