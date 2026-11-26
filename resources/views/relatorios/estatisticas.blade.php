@extends('layouts.app')

@section('title', 'Estatísticas - Senior Expert')

@section('content')
@php
    $situacoes = [
        'atrasado' => [
            'nome' => 'Atrasado',
            'cor' => '#c84646',
            'fundo' => '#fff0f0',
            'borda' => '#dfb9b9',
            'descricao' => 'Rotinas que ultrapassaram o limite da etapa.',
        ],
        'expirando' => [
            'nome' => 'Expirando',
            'cor' => '#ab874e',
            'fundo' => '#eee6d7',
            'borda' => '#ddcbae',
            'descricao' => 'Rotinas próximas do limite da etapa.',
        ],
        'noPrazo' => [
            'nome' => 'No prazo',
            'cor' => '#32703b',
            'fundo' => '#e7f2e9',
            'borda' => '#b5d0b7',
            'descricao' => 'Rotinas dentro do prazo da etapa.',
        ],
    ];

    $dataLocal = fn ($data) => $data
        ? \Carbon\Carbon::parse($data, 'UTC')->setTimezone('America/Sao_Paulo')
        : null;

    $statusExecucao = [
        'pendente' => ['nome' => 'Pendente', 'classe' => 'bg-[#f4efe7] text-[#665233]'],
        'em_andamento' => ['nome' => 'Iniciada', 'classe' => 'bg-[#e4eef1] text-[#153e56]'],
        'concluida' => ['nome' => 'Concluída', 'classe' => 'bg-[#e7f2e9] text-[#2e6438]'],
    ];

    $porProcesso = $aba === 'processos';
    $tipoItem = $porProcesso ? 'tarefa' : 'rotina';
    $secoesDaLista = $porProcesso
        ? $grupos->groupBy(fn (array $item): string => mb_substr($item['nome'], 0, 1))
        : $grupos;

@endphp

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=Fraunces:wght@600;700&amp;display=swap">
<style>
    .estatisticas--lista .filtros-estatisticas { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .estatisticas--lista .busca-lista { display: flex; flex: 0 1 37%; min-width: min(240px, 100%); margin-right: auto; border: 1px solid #e5d7c3; border-radius: 12px; overflow: hidden; }
    .busca-lista input { flex: 1; width: 0; padding: 11px 16px; background: white; }
    .busca-lista button { padding: 0 14px; color: #798693; }
    .estatisticas--lista .filtro-status { display: flex; align-items: center; gap: 8px; padding: 9px 12px; border: 1px solid #e5d7c3; border-radius: 12px; cursor: pointer; font-weight: 600; }
    .filtro-status:has(input:checked) { background: #f0f2f4; border-color: #a0adb7; }
    .filtro-status input { width: 17px; height: 17px; accent-color: #21394e; }
    .estatisticas--lista .botao.filtro-periodo {display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; border: 1; padding: 9px 10px; font-size: 13px;}
    .estatisticas--lista .periodos { padding: 20px; background: #f9f8f6; }
    .estatisticas--lista .periodos .campo { border-color: #e5d7c3; border-radius: 12px; min-height: 43px; }
    .estatisticas--lista .periodos .primario { border-radius: 12px; font-weight: 700; }
    .estatisticas--lista .letra-resumo { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5d7c3; list-style: none; font-size: 24px; font-weight: 800; }
    .letra-resumo::-webkit-details-marker { display: none; }
    .letra-resumo span:first-child { padding: 0 10px 2px; border-bottom: 2px solid #21394e; }
    .letra-resumo span:last-child { color: #ab874e; margin-right: 8px; }
    details[open] > .letra-resumo span:last-child { transform: rotate(90deg); }
    .grade-colaboradores { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 14px; margin-top: 12px; }
    .estatisticas--lista .colaborador { height: 100%; border: 2px solid #e5d7c3; border-radius: 20px; padding: 14px; box-shadow: 0 1px 3px #21394e22; }
    .cabecalho-colaborador { display: flex; align-items: center; gap: 8px; }
    .cabecalho-colaborador strong { flex: 1; text-transform: uppercase; overflow-wrap: anywhere; }
    .total-colaborador { white-space: nowrap; background: #f8f6f1; color: #697783; border-radius: 30px; padding: 2px 10px; font-size: 12px; }
    .estatisticas--lista .barra-unica { display: flex; height: 8px; margin: 10px 0; overflow: hidden; border-radius: 10px; background: #eef1f4; }
    .grade-indicadores { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; }
    .estatisticas--lista .indicador { text-align: center; padding: 7px 3px; font-size: 11px; font-weight: 600; }
    .estatisticas--lista .indicador strong { display: block; font-size: 16px; }
    @media (max-width: 950px) {
        .grade-colaboradores { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .estatisticas--lista .busca-lista { flex-basis: 100%; }
    }
    @media (max-width: 580px) {
        .grade-colaboradores { grid-template-columns: minmax(0,1fr); }
    }

    .estatisticas { font-family: "DM Sans", system-ui, sans-serif; color: #21394e; }
    .estatisticas .filtro-periodo { color: #21394e; font-weight: 700; cursor: pointer; }
    .estatisticas .periodos { background: #faf7f2; border: 1px solid #e6d5bf; border-radius: 20px; }
    .estatisticas .busca-rotinas:focus-within { outline: 2px solid #243e53; }

    .estatisticas summary { cursor: pointer; }
    .estatisticas summary::marker { color: #8a9299; }

    .estatisticas .campo {
        border: 1px solid #dedede;
        border-radius: 8px;
        padding: 9px 12px;
        background: white;
        width: 100%;
    }

    .estatisticas .botao {
        border: 1px solid #dedede;
        border-radius: 8px;
        padding: 9px 16px;
        background: white;
    }

    .estatisticas .primario {
        background: #153e56;
        color: white;
        border-color: #153e56;
    }

    .estatisticas .indicador {
        padding: 12px;
        border-radius: 10px;
    }

    .estatisticas .barra {
        height: 5px;
        border-radius: 8px;
        background: #ffffffa0;
        margin-top: 10px;
        overflow: hidden;
    }

    .estatisticas .barra > span {
        display: block;
        height: 100%;
        border-radius: inherit;
    }
    .estatisticas, .estatisticas * { box-sizing: border-box; }
    .estatisticas [hidden] { display: none !important; }
    .estatisticas .colaborador {
        display: block;
        padding: 16px;
        border: 1px solid #e2d5c2;
        border-radius: 16px;
        background: #fff;
        color: inherit;
        text-decoration: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .estatisticas .colaborador:hover {
        border-color: #8c9fa9;
        box-shadow: 0 2px 8px #18394f10;
    }
    .estatisticas a:focus-visible, .estatisticas button:focus-visible,
    .estatisticas input:focus-visible, .estatisticas summary:focus-visible {
        outline: 2px solid #153e56;
        outline-offset: 3px;
    }
    .estatisticas .voltar {
        display: inline-flex;
        gap: 7px;
        align-items: center;
        color: #243e53;
        font-size: 13px;
        text-decoration: none;
    }
    .estatisticas .titulo-colaborador {
        margin: 7px 0 3px;
        font: 600 30px/1.15 Fraunces, Georgia, serif;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }
    .estatisticas .subtitulo-colaborador { margin: 4px 0 16px; color: #6b7783; font-size: 14px; }
    .estatisticas .busca-rotinas {
        display: flex;
        align-items: center;
        min-height: 44px;
        margin: 16px 0 20px;
        border: 1px solid #e6d5bf;
        border-radius: 12px;
        overflow: hidden;
        background: white;
    }
    .estatisticas .busca-rotinas input {
        min-width: 0;
        flex: 1;
        padding: 12px 15px;
        border: 0;
        background: transparent;
        font: inherit;
        font-size: 14px;
        color: #243e53;
    }
    .estatisticas .busca-rotinas input::placeholder { color: #74808b; }
    .estatisticas .busca-rotinas button {
        display: grid;
        place-items: center;
        align-self: stretch;
        width: 45px;
        border: 0;
        background: white;
        color: #7e8b94;
        cursor: pointer;
    }
    .estatisticas .resultado-busca { margin: -8px 0 16px; color: #6b7783; font-size: 13px; }
    .estatisticas .resultado-busca a { color: #153e56; text-decoration: underline; margin-left: 8px; }
    .estatisticas .faixa-sla {
        margin-bottom: 16px;
        overflow: hidden;
        border: 2px solid var(--borda-sla);
        border-radius: 20px;
        background: color-mix(in srgb, var(--fundo-sla) 35%, white);
        box-shadow: 0 1px 2px #243e530c;
    }
    .estatisticas .cabecalho-sla {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 11px 16px;
        background: var(--fundo-sla);
        color: var(--cor-sla);
        border-bottom: 1px solid var(--borda-sla);
    }
    .estatisticas .cabecalho-sla h2 {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 7px;
        margin: 0;
        font-size: 15px;
        font-weight: 600;
    }
    .estatisticas .ponto-sla { width: 10px; height: 10px; border-radius: 50%; background: currentColor; }
    .estatisticas .contagem-sla { font-size: 12px; font-weight: 500; }
    .estatisticas .cabecalho-sla p { margin: 3px 0 0; font-size: 12px; opacity: .85; }
    .estatisticas .percentual-sla {
        flex-shrink: 0;
        padding: 2px 11px;
        border-radius: 20px;
        background: #ffffffdc;
        font-size: 12px;
        font-weight: 600;
    }
    .estatisticas .grade-rotinas {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        padding: 12px;
    }
    .estatisticas .cartao-rotina {
        display: flex;
        min-width: 0;
        flex-direction: column;
        padding: 12px;
        border: 1px solid #e8d7c1;
        border-radius: 17px;
        background: #fff;
        box-shadow: 0 1px 2px #243e5308;
    }
    .estatisticas .topo-rotina { display: flex; align-items: flex-start; gap: 10px; padding-bottom: 12px; }
    .estatisticas .icone-tarefa {
        display: grid;
        flex-shrink: 0;
        place-items: center;
        width: 35px;
        height: 37px;
        border-radius: 9px;
        background: #faf7f2;
        font-size: 23px;
    }
    .estatisticas .nome-tarefa { flex: 1; min-width: 0; }
    .estatisticas .nome-tarefa h3 {
        margin: 0;
        color: #243e53;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }
    .estatisticas .nome-tarefa p { margin: 2px 0 0; color: #6b7783; font-size: 11px; }
    .estatisticas .horario-rotina {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
        padding: 2px 8px;
        border-radius: 20px;
        background: #eaf1f4;
        color: #243e53;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }
    .estatisticas .dados-rotina {
        display: grid;
        gap: 5px;
        margin: auto 0 0;
        padding: 10px;
        border-radius: 13px;
        background: #fafaf8;
        font-size: 12px;
        line-height: 1.35;
    }
    .estatisticas .dados-rotina > div { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px; }
    .estatisticas .dados-rotina dt { color: #6b7783; font-weight: 600; }
    .estatisticas .dados-rotina dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
    .estatisticas .icone-dado { display: inline-block; width: 14px; color: #b58a4b; text-align: center; }
    .estatisticas .faixa-vazia { margin: 0; padding: 20px 16px; color: #7c8790; font-size: 13px; }
    @media (max-width: 1199px) {
        .estatisticas .grade-rotinas { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 639px) {
        .estatisticas .titulo-colaborador { font-size: 27px; }
        .estatisticas .grade-rotinas { grid-template-columns: minmax(0, 1fr); }
    }

</style>

<div class="estatisticas {{ $chaveDetalhe ? 'estatisticas--detalhe' : 'estatisticas--lista' }}">
    @if (session('success'))
        <div role="status"
            class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div id="erros-estatisticas" role="alert"
             class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">
            @foreach ($errors->all() as $erro)
                <p>{{ $erro }}</p>
            @endforeach
        </div>
    @endif

    @if ($chaveDetalhe)
        <a class="voltar" aria-label="Voltar" title="Voltar" href="{{ route('relatorios.estatisticas', $parametros) }}">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H4m8-8-8 8 8 8"/></svg>
        </a>

        @if ($detalhe)
            <h1 class="titulo-colaborador">{{ $detalhe['nome'] }}</h1>
            <p class="subtitulo-colaborador">
                @if ($porProcesso)
                    {{ $detalhe['total'] }} {{ $detalhe['total'] === 1 ? 'tarefa' : 'tarefas' }}
                @else
                    {{ $aba === 'usuarios' ? 'Usuário' : 'Grupo' }} ·
                    {{ $detalhe['total'] }} {{ $detalhe['total'] === 1 ? 'rotina' : 'rotinas' }}
                @endif
            </p>

            <form class="busca-rotinas" method="GET" action="{{ route('relatorios.estatisticas') }}" role="search">
                @foreach ($parametros as $campo => $valor)
                    <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                @endforeach
                <input type="hidden" name="detalhe" value="{{ $chaveDetalhe }}">
                <input type="search" name="busca_rotinas" value="{{ $buscaRotinas }}"
                       maxlength="150" aria-label="Buscar por tarefa, morador, local ou horário"
                       placeholder="Buscar por tarefa, morador, local ou horário...">
                <button type="submit" aria-label="Buscar rotinas">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="10.5" cy="10.5" r="7.5" />
                        <path d="m16 16 5 5" />
                    </svg>
                </button>
            </form>

            @if ($buscaRotinas !== '')
                <p class="resultado-busca" role="status">
                    {{ $rotinasDetalhe->count() }} de {{ $detalhe['total'] }} {{ $tipoItem }}s encontradas.
                    <a href="{{ route('relatorios.estatisticas', array_merge($parametros, ['detalhe' => $chaveDetalhe])) }}">Limpar busca</a>
                </p>
            @endif

            @foreach (['noPrazo', 'expirando', 'atrasado'] as $chave)
                @php
                    $config = $situacoes[$chave];
                    $lista = $rotinasDetalhe->where('situacao', $chave);
                    $percentual = $rotinasDetalhe->isEmpty()
                        ? 0
                        : round($lista->count() * 100 / $rotinasDetalhe->count(), 1);
                @endphp

                <section class="faixa-sla" aria-labelledby="sla-{{ $chave }}"
                         style="--cor-sla: {{ $config['cor'] }}; --fundo-sla: {{ $config['fundo'] }}; --borda-sla: {{ $config['borda'] }}">
                    <header class="cabecalho-sla">
                        <div>
                            <h2 id="sla-{{ $chave }}">
                                <span class="ponto-sla" aria-hidden="true"></span>
                                {{ $config['nome'] }}
                                <span class="contagem-sla">· {{ $lista->count() }} {{ $tipoItem }}{{ $lista->count() === 1 ? '' : 's' }}</span>
                            </h2>
                            <p>{{ $config['descricao'] }}</p>
                        </div>
                        <span class="percentual-sla">{{ number_format($percentual, 0, ',', '.') }}%</span>
                    </header>

                    @if ($lista->isEmpty())
                        <p class="faixa-vazia">Nenhuma {{ $tipoItem }} nesta situação.</p>
                    @else
                        <div class="grade-rotinas">
                            @foreach ($lista as $execucao)
                                @include('relatorios.partials.cartao-estatistica-rotina', [
                                    'execucao' => $execucao, 'statusExecucao' => $statusExecucao, 'dataLocal' => $dataLocal, 'aba' => $aba,
                                ])
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        @else
            <h1 class="titulo-colaborador">Rotinas</h1>
            <p class="subtitulo-colaborador">
                Nenhuma execução disponível para este colaborador ou grupo com os filtros atuais.
                Volte à lista para ajustar os filtros.
            </p>
        @endif
    @else

    <h1 class="text-3xl font-semibold text-[#153e56]"
        style="font-family: Fraunces, Georgia, serif">
        Estatísticas
    </h1>

    <p class="mt-2 text-sm text-gray-500">
        {{ $porProcesso ? 'Percentual de tarefas atrasadas, expirando e no prazo por tipo de processo.' : 'Percentual de rotinas atrasadas, expirando e no prazo.' }}
    </p>

    <form method="GET" action="{{ route('relatorios.estatisticas') }}">
        <input type="hidden" name="aba" value="{{ $aba }}">

        <div class="mt-6 flex flex-wrap items-center justify-between gap-4
                    border-b border-gray-200">
            <div class="flex gap-6">
                @foreach (['usuarios' => 'Usuários', 'grupos' => 'Grupos', 'processos' => 'Processos'] as $chave => $titulo)
                    <button
                        type="submit"
                        onclick="this.form.elements['aba'].value = '{{ $chave }}'"
                        class="border-b-2 pb-3 text-sm font-semibold
                            {{ $aba === $chave
                                ? 'border-[#153e56] text-[#153e56]'
                                : 'border-transparent text-gray-500' }}"
                        @if ($aba === $chave) aria-current="page" @endif
                    >
                        {{ $titulo }}
                    </button>
                @endforeach

            </div>

            <a href="{{ route('relatorios.estatisticas') }}"
               class="mb-2 text-sm text-[#153e56]" aria-label="Atualizar e limpar filtros" title="Atualizar e limpar filtros">
                <span aria-hidden="true" style="font-size:22px">↻</span>
            </a>
        </div>

        <div class="filtros-estatisticas">
            <div class="busca-lista">
                <input id="busca" type="search" name="busca" value="{{ $busca }}"
                       maxlength="150" placeholder="Buscar por nome..." aria-label="Buscar por nome">
                <button type="submit" aria-label="Buscar por nome">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/></svg>
                </button>
            </div>

            <input type="hidden" name="pendentes" value="0">

            <label class="filtro-status text-sm">
                <input type="checkbox"
                    name="pendentes"
                    value="1"
                    @checked($pendentes)>
                Pendente
            </label>

            <input type="hidden" name="andamento" value="0">
            <label class="filtro-status text-sm">
                <input type="checkbox" name="andamento" value="1"
                       @checked($andamento)>
                Incluir em andamento
            </label>

            <input type="hidden" name="finalizados" value="0">
            <label class="filtro-status text-sm">
                <input id="incluir-finalizados" type="checkbox"
                       name="finalizados" value="1"
                       @checked($finalizados)>
                Incluir finalizados
            </label>

            <button type="button" id="abrir-periodos" class="botao filtro-periodo"
                    aria-controls="periodos" aria-expanded="{{ $finalizados ? 'true' : 'false' }}" @if (!$finalizados) hidden @endif>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ab874e" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/></svg>
                Filtrar por período <span aria-hidden="true">⌵</span>
            </button>
        </div>

        <div id="periodos" class="periodos mt-3" @if (!$finalizados) hidden @endif>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['inicio' => 'Data de início do item', 'fim' => 'Período de finalização do item'] as $etapa => $rotulo)
                    <div>
                        <label for="periodo_{{ $etapa }}" class="mb-1 block text-sm font-semibold">{{ $rotulo }}</label>
                        <select id="periodo_{{ $etapa }}" name="periodo_{{ $etapa }}" class="campo" data-periodo>
                            @foreach ($opcoesPeriodo as $opcao => $nome)
                                <option value="{{ $opcao }}" @selected($periodos[$etapa] === $opcao)>{{ $opcao === 'todos' ? 'Todo o período' : $nome }}</option>
                            @endforeach
                        </select>

                        <div class="mt-2 grid grid-cols-2 gap-2" data-datas @if ($periodos[$etapa] !== 'personalizado') hidden @endif>
                            @foreach (['de' => 'De', 'ate' => 'Até'] as $limite => $texto)
                                @php
                                    $campo = $etapa.'_'.$limite;
                                @endphp
                                <label class="text-sm">
                                    {{ $texto }}
                                    <input type="date" name="{{ $campo }}" value="{{ request($campo) }}"
                                           aria-label="{{ $rotulo }} — {{ $texto }}" class="campo">
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="botao primario">Buscar</button>
                <button type="submit" id="limpar-periodos" class="botao">Limpar período</button>
            </div>
        </div>
    </form>

    @if ($semEmpresas)
        <p class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
            Seu usuário não possui empresas vinculadas.
            Vincule uma empresa para consultar as estatísticas.
        </p>
    @endif

    @if ($inconsistentes)
        <p role="alert" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
            {{ $inconsistentes }} execução(ões) fora dos indicadores:
            Datas, duração ou situação da execução inconsistentes.
            Revise esses registros!
        </p>
    @endif

    <div class="mt-6 space-y-5">
        @forelse ($secoesDaLista as $letra => $itens)
            <details open>
                <summary class="letra-resumo">
                    <span>{{ $letra }}</span><span aria-hidden="true">›</span>
                </summary>

                <div class="grade-colaboradores">
                    @foreach ($itens as $item)
                        <a class="colaborador"
                           href="{{ route('relatorios.estatisticas', array_merge($parametros, ['detalhe' => $item['chave']])) }}"
                           aria-label="Ver {{ $tipoItem }}s de {{ $item['nome'] }}">
                            <div class="cabecalho-colaborador">
                                <strong>{{ $item['nome'] }}</strong>
                                <span class="total-colaborador">{{ $item['total'] }} {{ $tipoItem }}{{ $item['total'] === 1 ? '' : 's' }}</span>
                                <span aria-hidden="true" style="color:#ab874e">›</span>
                            </div>
                            <div class="barra-unica" aria-hidden="true">
                                @foreach ($situacoes as $chave => $config)
                                    <span style="flex: {{ $item['indicadores'][$chave]['percentual'] }} 1 0; background: {{ $config['cor'] }}"></span>
                                @endforeach
                            </div>
                            <div class="grade-indicadores">
                                @foreach ($situacoes as $chave => $config)
                                    <div class="indicador" style="background: {{ $config['fundo'] }}; color: {{ $config['cor'] }}">
                                        {{ $config['nome'] }}
                                        <strong>{{ number_format($item['indicadores'][$chave]['percentual'], 0, ',', '.') }}%</strong>
                                    </div>
                                @endforeach
                            </div>
                        </a>
                    @endforeach
                </div>
            </details>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center">
                <p class="font-semibold text-[#153e56]">{{ $porProcesso ? 'Nenhum processo encontrado' : 'Nenhuma execução encontrada' }}</p>
                <p class="mt-2 text-sm text-gray-500">
                    @if (!$pendentes && !$andamento && !$finalizados)
                        Selecione uma situação da execução e clique em Buscar.
                    @else
                        Confira os filtros, as empresas vinculadas e os registros de execução.
                    @endif
                </p>
            </div>
        @endforelse
    </div>
    @endif

</div>

<script>
    (() => {
        const checkbox = document.getElementById('incluir-finalizados');
        const painel = document.getElementById('periodos');
        const abrir = document.getElementById('abrir-periodos');

        if (!checkbox || !painel || !abrir) return;

        function mostrarPainel(aberto) {
            painel.hidden = !aberto;
            abrir.setAttribute('aria-expanded', String(aberto));
        }

        checkbox.addEventListener('change', () => {
            abrir.hidden = !checkbox.checked;
            mostrarPainel(checkbox.checked);
            if (!checkbox.checked) checkbox.form.requestSubmit();
        });

        checkbox.form.querySelectorAll('input[name="pendentes"][type="checkbox"], input[name="andamento"][type="checkbox"]').forEach(filtro => {
            filtro.addEventListener('change', () => filtro.form.requestSubmit());
        });

        abrir.addEventListener('click', () => mostrarPainel(painel.hidden));

        const seletores = painel.querySelectorAll('[data-periodo]');
        seletores.forEach(seletor => {
            const datas = seletor.parentElement.querySelector('[data-datas]');
            function atualizarDatas() {
                datas.hidden = seletor.value !== 'personalizado';
                datas.querySelectorAll('input').forEach(input => input.disabled = datas.hidden);
            }

            seletor.addEventListener('change', atualizarDatas);
            atualizarDatas();
        });

        document.getElementById('limpar-periodos').addEventListener('click', () => {
            seletores.forEach(seletor => {
                seletor.value = 'todos';
                seletor.parentElement.querySelectorAll('input').forEach(input => input.value = '');
                seletor.dispatchEvent(new Event('change'));
            });
        });
    })();
</script>
@endsection
