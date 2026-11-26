<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante #V-{{ str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 13mm 12mm 15mm 12mm;
            @bottom-right {
                content: "Pág. " counter(page) " de " counter(pages);
                font-family: DejaVu Sans, sans-serif;
                font-size: 7.5px;
                color: #64748B;
            }
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1E293B;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2.5px solid #0055D4;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header table { width: 100%; border-collapse: collapse; }
        .brand { font-size: 15px; font-weight: bold; color: #0F172A; }
        .brand-sub { font-size: 8.5px; color: #475569; margin-top: 2px; }
        .folio-box {
            border: 1.5px solid #0055D4;
            background: #EFF6FF;
            border-radius: 5px;
            padding: 6px 10px;
            text-align: right;
        }
        .folio-label { font-size: 7.5px; text-transform: uppercase; color: #1E40AF; font-weight: bold; }
        .folio-num { font-size: 14px; font-weight: bold; color: #0F172A; }
        .estado-ok { font-size: 8px; font-weight: bold; color: #065F46; }
        .estado-anulada { font-size: 8px; font-weight: bold; color: #B91C1C; }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            margin-bottom: 12px;
        }
        .meta-grid td {
            padding: 7px 10px;
            vertical-align: top;
            font-size: 9px;
            width: 50%;
            border: 1px solid #E2E8F0;
        }
        .meta-title {
            font-size: 7.5px;
            text-transform: uppercase;
            color: #64748B;
            font-weight: bold;
            margin-bottom: 3px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.items th {
            background: #0F172A;
            color: #FFFFFF;
            font-size: 8px;
            text-transform: uppercase;
            padding: 6px 7px;
            border: 1px solid #0F172A;
            text-align: left;
        }
        table.items td {
            padding: 5.5px 7px;
            border: 1px solid #E2E8F0;
            font-size: 9px;
            vertical-align: top;
        }
        table.items tr:nth-child(even) td { background: #F8FAFC; }

        .summary-wrap {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-wrap td { vertical-align: top; }
        .payment-box {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            padding: 7px 10px;
            font-size: 8.5px;
        }
        table.totals {
            width: 100%;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 4px 8px;
            font-size: 9.5px;
            border: 1px solid #E2E8F0;
        }
        .total-row td {
            background: #0F172A;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 11px;
        }
        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px dashed #CBD5E1;
            font-size: 8px;
            color: #64748B;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $folio = 'V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT);
    $subtotalBruto = (float)$venta->total_venta + (float)$venta->descuento_total;
    $esVirtual = ($desgloseCobro['origen'] ?? '') === 'TIENDA_VIRTUAL_WEB';
@endphp

<div class="header">
    <table>
        <tr>
            <td style="width:62%; vertical-align:top;">
                <div class="brand">MULTIPARTES EL TUERCA</div>
                <div class="brand-sub">Casa Matriz · Av. Cristóbal de Mendoza #450 (2do Anillo) — Santa Cruz de la Sierra, Bolivia</div>
                <div class="brand-sub">NIT: 4589214018 · Tel: (591) 3-345-6789 · WhatsApp: +591 73397587</div>
            </td>
            <td style="width:38%; vertical-align:top;">
                <div class="folio-box">
                    <div class="folio-label">{{ $esVirtual ? 'COMPROBANTE DE COMPRA DIGITAL' : 'NOTA DE VENTA COMERCIAL' }}</div>
                    <div class="folio-num">#{{ $folio }}</div>
                    @if($venta->estado === 'Completada')
                        <div class="estado-ok">PAGADA / CONFIRMADA</div>
                    @else
                        <div class="estado-anulada">ANULADA</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="meta-grid">
    <tr>
        <td>
            <div class="meta-title">Datos del Cliente</div>
            <div><strong>Nombre / Razón Social:</strong> {{ $venta->cliente->nombre_razon_social ?? 'Cliente Mostrador' }}</div>
            <div><strong>CI / NIT:</strong> {{ $venta->cliente->ci_nit ?: 'S/N' }}</div>
            <div><strong>Segmento / Teléfono:</strong> {{ $venta->cliente->tipo_cliente ?? 'Particular' }} {{ $venta->cliente?->telefono ? '· ' . $venta->cliente->telefono : '' }}</div>
        </td>
        <td>
            <div class="meta-title">Datos de Operación</div>
            <div><strong>Fecha y Hora:</strong> {{ $venta->fecha_hora ? $venta->fecha_hora->format('d/m/Y H:i:s') : '-' }}</div>
            <div><strong>Atendido por / Canal:</strong> {{ $esVirtual ? 'Portal Web / Venta Digital' : ($venta->vendedor->nombre_completo ?? 'Mostrador') }}</div>
            <div><strong>Sesión de Caja:</strong> #{{ $venta->id_sesion_caja }} · <strong>Emitido PDF:</strong> {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:4%; text-align:center;">#</th>
            <th style="width:20%;">OEM / SKU</th>
            <th style="width:40%;">Descripción del Repuesto</th>
            <th style="width:8%; text-align:center;">Cant.</th>
            <th style="width:14%; text-align:right;">P. Unit. (Bs.)</th>
            <th style="width:14%; text-align:right;">Subtotal (Bs.)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($venta->detalles as $idx => $det)
            <tr>
                <td style="text-align:center;">{{ $idx + 1 }}</td>
                <td>
                    <strong>{{ $det->producto->codigo_oem ?? '-' }}</strong><br>
                    <span style="font-size:7.5px; color:#64748B;">SKU: {{ $det->producto->codigo_interno ?? '-' }}</span>
                </td>
                <td>
                    <strong>{{ $det->producto->nombre_pieza ?? 'Repuesto' }}</strong><br>
                    <span style="font-size:7.5px; color:#64748B;">{{ $det->producto->categoria->nombre ?? '' }} {{ $det->producto?->marca_fabricante ? '· ' . $det->producto->marca_fabricante : '' }}</span>
                </td>
                <td style="text-align:center; font-weight:bold;">{{ (int)$det->cantidad_vendida }}</td>
                <td style="text-align:right;">{{ number_format((float)$det->precio_unitario_cobrado, 2) }}</td>
                <td style="text-align:right; font-weight:bold;">{{ number_format((float)$det->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="summary-wrap">
    <tr>
        <td style="width:55%; padding-right:10px;">
            <div class="payment-box">
                <div class="meta-title">Desglose de Pago Multicanal</div>
                <div><strong>Modalidad:</strong> {{ $venta->metodo_pago }}</div>
                @if(!empty($desgloseCobro))
                    @if(!empty($desgloseCobro['monto_recibido']) && $venta->metodo_pago === 'Efectivo')
                        <div><strong>Efectivo Recibido:</strong> Bs. {{ number_format((float)$desgloseCobro['monto_recibido'], 2) }}</div>
                        <div><strong>Cambio / Vuelto:</strong> Bs. {{ number_format((float)($desgloseCobro['cambio_devuelto'] ?? 0), 2) }}</div>
                    @endif
                    @if(!empty($desgloseCobro['nro_referencia']))
                        <div><strong>Referencia / Transacción:</strong> {{ $desgloseCobro['nro_referencia'] }}</div>
                    @endif
                    @if($venta->metodo_pago === 'Mixto')
                        <div><strong>Porción Efectivo:</strong> Bs. {{ number_format((float)($desgloseCobro['monto_efectivo'] ?? 0), 2) }}</div>
                        <div><strong>Porción QR / Tarjeta:</strong> Bs. {{ number_format((float)($desgloseCobro['monto_digital'] ?? 0), 2) }}</div>
                    @endif
                    @if(!empty($desgloseCobro['nota_entrega']))
                        <div><strong>Entrega / Observación:</strong> {{ $desgloseCobro['nota_entrega'] }}</div>
                    @endif
                @endif
                @if($venta->estado === 'Anulada' && $venta->motivo_anulacion)
                    <div style="color:#B91C1C; margin-top:4px;"><strong>Motivo Anulación:</strong> {{ $venta->motivo_anulacion }}</div>
                @endif
            </div>
        </td>
        <td style="width:45%;">
            <table class="totals">
                <tr>
                    <td>Subtotal Bruto</td>
                    <td style="text-align:right;">Bs. {{ number_format($subtotalBruto, 2) }}</td>
                </tr>
                <tr>
                    <td>Descuento Comercial</td>
                    <td style="text-align:right;">- Bs. {{ number_format((float)$venta->descuento_total, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL PAGADO (Bs.)</td>
                    <td style="text-align:right;">Bs. {{ number_format((float)$venta->total_venta, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer">
    ¡Gracias por su preferencia! Verifique el código OEM antes de abrir el empaque del repuesto.<br>
    Multipartes El Tuerca · Santa Cruz de la Sierra, Bolivia · Comprobante generado digitalmente.
</div>
</body>
</html>
