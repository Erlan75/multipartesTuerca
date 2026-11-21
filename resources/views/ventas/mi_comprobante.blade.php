<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante Digital #V-{{ str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT) }} — Multipartes El Tuerca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #F5F7FB; color: #1E293B; }
        .card-clean { background: #fff; border: 1px solid #E2E8F0; border-radius: 18px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); }
        @media print { .no-print { display: none !important; } body { background: #fff; } .card-clean { border: none; box-shadow: none; } }
    </style>
</head>
<body>
@php
    $folio = 'V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT);
    $subtotalBruto = (float)$venta->total_venta + (float)$venta->descuento_total;
@endphp

<div class="container py-4" style="max-width: 880px;">
    @if(session('success'))
        <div class="alert alert-success rounded-3 border-0 shadow-sm mb-3 no-print" style="background:#ECFDF5;color:#065F46;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
        <div class="d-flex gap-2">
            <a href="{{ route('mis-cotizaciones.index') }}" class="btn btn-outline-secondary btn-sm" style="border-radius:10px;font-weight:600;">
                <i class="bi bi-arrow-left me-1"></i>Mis Compras y Cotizaciones
            </a>
            <a href="{{ route('home') }}#catalogo" class="btn btn-outline-primary btn-sm" style="border-radius:10px;font-weight:600;">
                <i class="bi bi-shop me-1"></i>Seguir Comprando
            </a>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('mi-comprobante.pdf', $venta->id_venta) }}" class="btn btn-danger fw-semibold" style="border-radius:10px;">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i>Descargar Comprobante PDF
            </a>
            <button type="button" onclick="window.print()" class="btn btn-dark fw-semibold" style="border-radius:10px;">
                <i class="bi bi-printer me-1"></i>Imprimir
            </button>
        </div>
    </div>

    <div class="card-clean p-4 p-md-5">
        <div class="row border-bottom pb-4 mb-4 align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:42px;height:42px;border-radius:10px;background:#007AFF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold text-dark">MULTIPARTES EL TUERCA</h4>
                        <small class="text-muted fw-semibold text-uppercase">Casa Matriz Santa Cruz · NIT: 4589214018</small>
                    </div>
                </div>
                <div class="small text-secondary">
                    Av. Cristóbal de Mendoza #450 (2do Anillo) · Tel: (591) 3-345-6789 · WhatsApp: 700-12345
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="text-uppercase fw-bold text-muted" style="font-size:0.75rem;">Nota de Venta / Pedido Digital Confirmado</div>
                <div class="fw-bold text-primary" style="font-size:1.6rem;font-family:monospace;">#{{ $folio }}</div>
                <span class="badge {{ $venta->estado === 'Completada' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }}">
                    {{ $venta->estado === 'Completada' ? 'CONFIRMADA / PAGADA' : 'ANULADA' }}
                </span>
            </div>
        </div>

        <div class="row g-3 mb-4 p-3 rounded-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
            <div class="col-md-6">
                <div class="text-uppercase text-muted fw-bold" style="font-size:0.72rem;">Cliente</div>
                <div class="fw-bold text-dark">{{ $venta->cliente->nombre_razon_social ?? auth()->user()->nombre_completo }}</div>
                <div class="small text-secondary">CI / NIT: <strong>{{ $venta->cliente->ci_nit ?: 'S/N' }}</strong></div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="text-uppercase text-muted fw-bold" style="font-size:0.72rem;">Detalles de Transacción</div>
                <div class="small text-secondary">Fecha: <strong>{{ optional($venta->fecha_hora)->format('d/m/Y H:i') }}</strong></div>
                <div class="small text-secondary">Método: <strong>{{ $venta->metodo_pago }}</strong></div>
                @if(!empty($desgloseCobro['nro_referencia']))
                    <div class="small text-secondary">Referencia: <strong>{{ $desgloseCobro['nro_referencia'] }}</strong></div>
                @endif
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Código OEM / SKU</th>
                        <th>Descripción del Repuesto</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-end">P. Unit. (Bs.)</th>
                        <th class="text-end">Subtotal (Bs.)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($venta->detalles as $idx => $det)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>
                                <div class="fw-bold font-monospace">{{ $det->producto->codigo_oem ?? '-' }}</div>
                                <small class="text-muted">SKU: {{ $det->producto->codigo_interno ?? '-' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $det->producto->nombre_pieza ?? 'Repuesto' }}</div>
                                <small class="text-muted">{{ $det->producto->categoria->nombre ?? '' }}</small>
                            </td>
                            <td class="text-center fw-bold">{{ (int)$det->cantidad_vendida }}</td>
                            <td class="text-end font-monospace">Bs. {{ number_format((float)$det->precio_unitario_cobrado, 2) }}</td>
                            <td class="text-end fw-bold font-monospace">Bs. {{ number_format((float)$det->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="border-0"></td>
                        <td class="text-end text-muted fw-semibold">Subtotal Bruto:</td>
                        <td class="text-end font-monospace">Bs. {{ number_format($subtotalBruto, 2) }}</td>
                    </tr>
                    @if($venta->descuento_total > 0)
                    <tr>
                        <td colspan="4" class="border-0"></td>
                        <td class="text-end text-warning-emphasis fw-semibold">Descuento:</td>
                        <td class="text-end font-monospace text-warning-emphasis">-Bs. {{ number_format((float)$venta->descuento_total, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="table-light">
                        <td colspan="4" class="border-0"></td>
                        <td class="text-end fw-bold fs-6">TOTAL PAGADO:</td>
                        <td class="text-end fw-bold fs-5 text-primary font-monospace">Bs. {{ number_format((float)$venta->total_venta, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="pt-3 border-top text-center small text-muted">
            ¡Gracias por tu compra! Presenta este comprobante digital (#{{ $folio }}) al momento de retirar tu pedido o coordinar el envío.
        </div>
    </div>
</div>
</body>
</html>
