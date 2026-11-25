<!DOCTYPE html>
<html lang="pt-BR" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Relatório de Rotinas - Excel</title>
    <style>
        table { border-collapse: collapse; table-layout: fixed; }
        tr { mso-height-source: auto; }
        br { mso-data-placement: same-cell; }
        th, td {
            color: #17384b;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            vertical-align: top;
            white-space: normal;
            mso-number-format: "\@";
        }
        .cabecalho, .horario, .tarefa { border: .5pt solid #fbe2d5; }
        .cabecalho {
            background-color: #104861;
            color: #fff;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            mso-pattern: black none;
        }
        .horario {
            background-color: #dae9f8;
            text-align: center;
            font-weight: bold;
            mso-pattern: black none;
        }
        .tarefa { text-align: left; padding-left: 15px; mso-char-indent-count: 1; }
        .detalhes { color: #536b7b; }
        .dias { color: #855f33; }
    </style>
</head>
<body>
    <table cellpadding="0" cellspacing="0">
        @if ($colaboradores->isNotEmpty())
            <col width="40" style="width: 30pt; mso-width-source: userset;">
            @foreach ($colaboradores as $colaborador)
                <col width="220" style="width: 165pt; mso-width-source: userset;">
            @endforeach
        @endif
        <thead>
            <tr>
                <th class="cabecalho" x:str>Hora</th>
                @foreach ($colaboradores as $colaborador)
                    <th class="cabecalho" x:str>{{ $colaborador->usuario }}<br>{{ $colaborador->resumo }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($horarios as $horario)
                <tr>
                    <td class="horario" x:str>{{ $horario }}</td>
                    @foreach ($colaboradores as $colaborador)
                        <td class="tarefa" x:str>
                            @foreach ($tarefasPorHorario->get($horario)->get($colaborador->id_usuario, []) as $tarefa)
                                <x-icone-tarefa :icone="$tarefa->icone" :cor="$tarefa->cor_icone" :planilha="true" />
                                <strong>{{ $tarefa->tarefa }}</strong><br>
                                @if ($tarefa->detalhes !== '')
                                    <span class="detalhes">{{ $tarefa->detalhes }}</span><br>
                                @endif
                                <span class="dias">{{ $tarefa->dias_formatados }}</span>
                                @unless ($loop->last)<br><br>@endunless
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td x:str>Nenhuma tarefa encontrada</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="{{ $colaboradores->count() + 1 }}" x:str>{{ $textoTotal }}</td></tr>
        </tfoot>
    </table>
</body>
</html>
