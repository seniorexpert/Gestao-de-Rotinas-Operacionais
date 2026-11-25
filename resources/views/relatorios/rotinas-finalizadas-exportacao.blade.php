<!DOCTYPE html>
<html lang="pt-BR" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Relatório de Rotinas Finalizadas</title>
    <style>
        table { border-collapse: collapse; }
        td { border: .5pt solid #fbe2d5; color: #17384b; font-family: Calibri, sans-serif; vertical-align: top; white-space: normal; mso-number-format: "\@"; }
        .cabecalho { background-color: #104861; color: #fff; font-weight: bold; mso-pattern: black none; }
        br { mso-data-placement: same-cell; }
    </style>
</head>
<body>
    <table cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                @foreach (['Finalizado em', 'Dia da semana', 'Hora', 'Colaborador(a)', 'Grupo de acesso', 'Tarefa', 'Quem (morador)', 'Onde', 'Tempo de execução', 'Tempo planejado', 'Justificativa'] as $coluna)
                    <td class="cabecalho" x:str>{{ $coluna }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($registros as $registro)
                <tr>
                    <td x:str>{{ $registro->finalizado_em->format('d/m/Y') }}</td>
                    <td x:str>{{ ucfirst($registro->finalizado_em->dayName) }}</td>
                    <td x:str>{{ $registro->finalizado_em->format('H:i') }}</td>
                    <td x:str>{{ $registro->finalizador }}</td>
                    <td x:str>{{ $registro->grupo_finalizador }}</td>
                    <td x:str>{{ $registro->tarefa }}</td>
                    <td x:str>{{ $registro->morador }}</td>
                    <td x:str>{{ $registro->local }}</td>
                    <td x:str>{{ $registro->tempo_execucao }}</td>
                    <td x:str>{{ $registro->tempo_planejado }}</td>
                    <td x:str>{!! nl2br(e($registro->justificativa)) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
