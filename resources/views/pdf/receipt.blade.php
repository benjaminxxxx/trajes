<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $code }}</title>
    <style>
        @page { margin: 26px 28px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9.5px; color: #0f172a; margin: 0; }
        .muted { color: #64748b; }
        .brand-blue { color: #1e6bff; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .title { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .code { font-size: 12px; font-weight: bold; color: #1e6bff; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; }
        .label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .value { font-size: 10px; font-weight: bold; }
        .items th { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; text-align: left; border-bottom: 1.5px solid #0f172a; padding: 5px 4px; }
        .items td { padding: 6px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .right { text-align: right; }
        .total-row td { padding: 8px 4px; font-size: 13px; font-weight: bold; }
        .total-box { background: #0f172a; color: #ffffff; border-radius: 6px; }
        .stamp { position: fixed; top: 38%; left: 0; right: 0; text-align: center; font-size: 60px; font-weight: bold;
                 color: rgba(220, 38, 38, 0.18); transform: rotate(-24deg); letter-spacing: 6px; }
        .footer { position: fixed; bottom: -8px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body>
    @if ($is_cancelled)
        <div class="stamp">ANULADO</div>
    @endif

    <!-- Encabezado -->
    <table class="header">
        <tr>
            <td style="width: 55%;">
                <img src="{{ $logo }}" style="height: 34px;" alt="Kipusoft Alquiler">
                <div style="margin-top: 6px;" class="muted">
                    <strong style="color: #0f172a;">{{ $business['name'] }}</strong>
                    @if ($business['ruc'])<br>RUC {{ $business['ruc'] }}@endif
                    @if ($business['address'])<br>{{ $business['address'] }}@endif
                    @if ($business['phone'])<br>Tel. {{ $business['phone'] }}@endif
                </div>
            </td>
            <td style="width: 45%;" class="right">
                <div class="title">{{ $title }}</div>
                <div class="code">{{ $code }}</div>
                <div class="muted" style="margin-top: 3px;">{{ $date?->format('d/m/Y H:i') }}</div>
                @if ($document)
                    <div class="muted" style="margin-top: 3px;">Ref.: {{ $document }}</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Cliente -->
    <table style="margin-top: 14px;">
        <tr>
            <td class="box" style="width: 60%;">
                <div class="label">Cliente</div>
                <div class="value">{{ $client?->display_name ?? 'Cliente varios' }}</div>
                @if ($client)
                    <div class="muted">{{ $client->document_type }} {{ $client->document_number }}@if ($client->mobile) · {{ $client->mobile }}@endif</div>
                @endif
            </td>
            <td style="width: 3%;"></td>
            <td class="box" style="width: 37%;">
                <div class="label">Atendido por</div>
                <div class="value">{{ $seller ?? '—' }}</div>
            </td>
        </tr>
    </table>

    @if ($rental)
        <!-- Fechas del alquiler -->
        <table style="margin-top: 8px;">
            <tr>
                <td class="box" style="width: 32%;">
                    <div class="label">Salida</div>
                    <div class="value">{{ $rental['pickup']?->format('d/m/Y H:i') }}</div>
                </td>
                <td style="width: 2%;"></td>
                <td class="box" style="width: 32%;">
                    <div class="label">Devolución prevista</div>
                    <div class="value brand-blue">{{ $rental['expected_return']?->format('d/m/Y H:i') }}</div>
                </td>
                <td style="width: 2%;"></td>
                <td class="box" style="width: 32%;">
                    <div class="label">{{ $rental['actual_return'] ? 'Devuelto' : 'Garantía' }}</div>
                    <div class="value">
                        {{ $rental['actual_return'] ? $rental['actual_return']->format('d/m/Y H:i') : 'S/ '.number_format($rental['deposit'], 2) }}
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <!-- Detalle -->
    <table class="items" style="margin-top: 14px;">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="right" style="width: 10%;">Cant.</th>
                <th class="right" style="width: 18%;">P. unit.</th>
                <th class="right" style="width: 18%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>
                        <strong>{{ $line['description'] }}</strong>
                        @if ($line['detail'])<br><span class="muted">{{ $line['detail'] }}</span>@endif
                    </td>
                    <td class="right">{{ $line['quantity'] }}</td>
                    <td class="right">{{ number_format($line['unit_price'], 2) }}</td>
                    <td class="right">{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Total (sin desglose de IGV: es lo que pagó el cliente) -->
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 50%;"></td>
            <td style="width: 50%;">
                @if ($rental && $rental['penalty'] > 0)
                    <table>
                        <tr>
                            <td class="muted" style="padding: 3px 10px;">Cobro por daños</td>
                            <td class="right" style="padding: 3px 10px;">S/ {{ number_format($rental['penalty'], 2) }}</td>
                        </tr>
                    </table>
                @endif
                <table class="total-box">
                    <tr class="total-row">
                        <td style="padding-left: 10px;">TOTAL</td>
                        <td class="right" style="padding-right: 10px;">S/ {{ number_format($total + ($rental['penalty'] ?? 0), 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($rental)
        <div class="box" style="margin-top: 14px; background: #f8fafc;">
            <div class="label">Condiciones del alquiler</div>
            <div style="margin-top: 3px; line-height: 1.5;">
                Las prendas deben devolverse en la fecha indicada y en el mismo estado en que se entregaron.
                La garantía se devuelve al recibir las prendas; los daños o pérdidas se cobran aparte.
            </div>
        </div>
    @endif

    @if ($notes)
        <div style="margin-top: 10px;"><span class="label">Notas:</span> {{ $notes }}</div>
    @endif

    <div class="footer">
        {{ $business['receipt_footer'] }} · Documento sin valor tributario · Generado por Kipusoft Alquiler
    </div>
</body>
</html>
