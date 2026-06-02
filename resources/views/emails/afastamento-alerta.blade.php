<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        h2 { color: #2c3e50; }
        h3 { margin-top: 24px; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        th { background-color: #2c3e50; color: #fff; padding: 8px 12px; text-align: left; }
        td { padding: 8px 12px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) td { background-color: #f9f9f9; }
        .badge-success { color: #27ae60; font-weight: bold; }
        .badge-error { color: #e74c3c; font-weight: bold; }
        .error-item { background: #fdecea; border-left: 4px solid #e74c3c; padding: 8px 12px; margin: 4px 0; }
    </style>
</head>
<body>
    <h2>Alerta do envio de afastamento</h2>

    @if(count($sucessos) > 0)
        <h3 class="badge-success">✔ Lançamentos enviados com sucesso ({{ count($sucessos) }})</h3>
        <table>
            <thead>
                <tr>
                    <th>Matrícula</th>
                    <th>Empresa</th>
                    <th>Data Ocorrência</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sucessos as $item)
                <tr>
                    <td>{{ $item['matricula'] }}</td>
                    <td>{{ $item['empresa'] }}</td>
                    <td>{{ $item['data_ocorrencia'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($erros) > 0)
        <h3 class="badge-error">✖ Erros no envio ({{ count($erros) }})</h3>
        @foreach($erros as $erro)
            <div class="error-item">{{ $erro }}</div>
        @endforeach
    @endif

    @if(count($sucessos) === 0 && count($erros) === 0)
        <p>Nenhum afastamento para processar.</p>
    @endif
</body>
</html>
