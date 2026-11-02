@extends('layouts.app')
@section('title', 'Comprobante #V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT) . ' — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ventas.index') }}">Notas de Venta</a></li>
    <li class="breadcrumb-item active">#V-{{ str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT) }}</li>
@endsection

@push('styles')
<style>
    /* Ticket Térmico 80mm */
    .ticket-80mm {
        width: 315px;
        max-width: 100%;
        margin: 0 auto;
        background: #ffffff;
        color: #000000;
        padding: 18px 14px;
        border: 1px dashed #CBD5E1;
        border-radius: 8px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        line-height: 1.35;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
    }
    .ticket-80mm .t-divider {
        border-top: 1px dashed #000;
        margin: 8px 0;
    }
    .ticket-80mm table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11.5px;
    }
    .ticket-80mm th, .ticket-80mm td {
        padding: 3px 0;
        vertical-align: top;
    }

    @media print {
        .sidebar, .top-navbar, .no-print, .alert { display: none !important; }
        .main-content { margin-left: 0 !important; width: 100% !important; }
        .page-content { padding: 0 !important; }
        body { background: #fff !important; }
        .ticket-80mm {
            width: 78mm !important;
            border: none !important;
            box-shadow: none !important;
            padding: 2mm !important;
            margin: 0 auto !important;
        }
        .comprobante-estandar {
            border: 1px solid #000 !important;
            box-shadow: none !important;
        }
    }
</style>
@endpush

@section('content')
@php
    $folio = 'V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT);
    $subtotalBruto = (float)$venta->total_venta + (float)$venta->descuento_total;
@endphp

{{-- Controles Superiores (No se imprimen) --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
    <div class="d-flex gap-2">
        <a href="{{ route('ventas.pos') }}" class="btn btn-tuerca">
            <i class="bi bi-cart-plus me-1"></i>Nueva Venta en POS
        </a>
        <a href="{{ route('ventas.index') }}" class="btn btn-outline-tuerca">
            <i class="bi bi-list-ul me-1"></i>Historial de Ventas
        </a>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="btn-group" role="group">
            <a href="{{ route('ventas.ticket', ['id' => $venta->id_venta, 'formato' => 'termico']) }}"
               class="btn btn-sm {{ $formato === 'termico' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="bi bi-receipt me-1"></i>Ticket Térmico 80mm
            </a>
            <a href="{{ route('ventas.ticket', ['id' => $venta->id_venta, 'formato' => 'estandar']) }}"
               class="btn btn-sm {{ $formato === 'estandar' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="bi bi-file-earmark-text me-1"></i>Nota de Venta Estándar
            </a>
        </div>

        <button type="button" onclick="window.print()" class="btn btn-gold">
            <i class="bi bi-printer-fill me-1"></i>Imprimir Comprobante
        </button>
    </div>
</div>

@if($venta->estado === 'Anulada')
    <div class="alert alert-danger text-center fw-bold mb-4">
        <i class="bi bi-x-octagon-fill me-1"></i>ESTA NOTA DE VENTA SE ENCUENTRA ANULADA — MOTIVO: {{ $venta->motivo_anulacion }}
    </div>
@endif

@if($formato === 'termico')
    {{-- ═══ FORMATO 1: TICKET TÉRMICO 80MM ═══ --}}
    <div class="ticket-80mm">
        <div class="text-center">
            <div style="font-weight:bold;font-size:15px;">MULTIPARTES EL TUERCA</div>
            <div style="font-size:11px;">Repuestos Japoneses y Coreanos</div>
            <div style="font-size:11px;">Av. Cristóbal de Mendoza #450 (2do Anillo)</div>
            <div style="font-size:11px;">Santa Cruz de la Sierra - Bolivia</div>
            <div style="font-size:11px;">Tel: 3-345-6789 / WhatsApp: 700-12345</div>
        </div>

        <div class="t-divider"></div>

        <div class="text-center" style="font-weight:bold;font-size:13px;">
            NOTA DE VENTA #{{ $folio }}
            @if($venta->estado === 'Anulada')
                <div>*** ANULADA ***</div>
            @endif
        </div>

        <div class="t-divider"></div>

        <div>
            <div><strong>FECHA:</strong> {{ $venta->fecha_hora->format('d/m/Y H:i:s') }}</div>
            <div><strong>CLIENTE:</strong> {{ $venta->cliente->nombre_razon_social ?? 'Cliente Mostrador' }}</div>
            <div><strong>CI/NIT:</strong> {{ $venta->cliente->ci_nit ?: 'S/N' }}</div>
            <div><strong>VENDEDOR:</strong> {{ $venta->vendedor->nombre_completo ?? 'Mostrador' }}</div>
            <div><strong>SESIÓN CAJA:</strong> #{{ $venta->id_sesion_caja }}</div>
        </div>

        <div class="t-divider"></div>

        <table>
            <thead>
                <tr style="border-bottom:1px dashed #000;">
                    <th style="text-align:left;">CANT / DESCRIPCIÓN (OEM)</th>
                    <th style="text-align:right;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $det)
                    <tr>
                        <td colspan="2" style="padding-top:4px;font-weight:bold;">
                            {{ $det->producto->nombre_pieza ?? 'Repuesto' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            {{ (int)$det->cantidad_vendida }} x Bs. {{ number_format($det->precio_unitario_cobrado, 2) }}
                            <br><span style="font-size:10.5px;">OEM: {{ $det->producto->codigo_oem ?? '-' }}</span>
                        </td>
                        <td style="text-align:right;font-weight:bold;">
                            {{ number_format($det->subtotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="t-divider"></div>

        <table>
            <tr>
                <td>SUBTOTAL BRUTO:</td>
                <td style="text-align:right;">Bs. {{ number_format($subtotalBruto, 2) }}</td>
            </tr>
            @if($venta->descuento_total > 0)
            <tr>
                <td>DESCUENTO COMERCIAL:</td>
                <td style="text-align:right;">-Bs. {{ number_format($venta->descuento_total, 2) }}</td>
            </tr>
            @endif
            <tr style="font-size:14px;font-weight:bold;">
                <td>TOTAL EN Bs.:</td>
                <td style="text-align:right;">Bs. {{ number_format($venta->total_venta, 2) }}</td>
            </tr>
        </table>

        <div class="t-divider"></div>

        <div>
            <div><strong>MÉTODO DE PAGO:</strong> {{ $venta->metodo_pago }}</div>
            @if($desgloseCobro)
                @if(!empty($desgloseCobro['monto_recibido']) && $venta->metodo_pago === 'Efectivo')
                    <div><strong>EFECTIVO RECIBIDO:</strong> Bs. {{ number_format((float)$desgloseCobro['monto_recibido'], 2) }}</div>
                    <div><strong>CAMBIO / VUELTO:</strong> Bs. {{ number_format((float)($desgloseCobro['cambio_devuelto'] ?? 0), 2) }}</div>
                @endif
                @if(!empty($desgloseCobro['nro_referencia']))
                    <div><strong>REF / COMPROBANTE:</strong> {{ $desgloseCobro['nro_referencia'] }}</div>
                @endif
                @if($venta->metodo_pago === 'Mixto')
                    <div><strong>PORCIÓN EFECTIVO:</strong> Bs. {{ number_format((float)($desgloseCobro['monto_efectivo'] ?? 0), 2) }}</div>
                    <div><strong>PORCIÓN QR/TARJETA:</strong> Bs. {{ number_format((float)($desgloseCobro['monto_digital'] ?? 0), 2) }}</div>
                @endif
            @endif
        </div>

        <div class="t-divider"></div>

        <div class="text-center" style="font-size:10.5px;">
            <div>¡Gracias por su preferencia!</div>
            <div>Verifique el código OEM antes de abrir el empaque.</div>
            <div class="mt-1">Santa Cruz - Bolivia</div>
        </div>
    </div>
@else
    {{-- ═══ FORMATO 2: COMPROBANTE ESTÁNDAR A4/CARTA ═══ --}}
    <div class="card-light p-4 p-md-5 comprobante-estandar" style="max-width:900px;margin:0 auto;">
        <div class="row border-bottom pb-4 mb-4 align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:42px;height:42px;border-radius:10px;background:#007AFF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <div>
                        <h4 class="mb-0" style="font-weight:800;color:#0F172A;">MULTIPARTES EL TUERCA</h4>
                        <small style="color:#64748B;font-weight:600;text-transform:uppercase;">Santa Cruz de la Sierra — Bolivia</small>
                    </div>
                </div>
                <div style="font-size:0.8rem;color:#475569;">
                    Av. Cristóbal de Mendoza #450 (Segundo Anillo) · Tel: (591) 3-345-6789 · WhatsApp: (+591) 700-12345
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div style="font-size:0.75rem;font-weight:700;color:#64748B;text-transform:uppercase;">NOTA DE VENTA COMERCIAL</div>
                <div style="font-size:1.65rem;font-weight:800;color:#007AFF;font-family:monospace;">#{{ $folio }}</div>
                <div>
                    @if($venta->estado === 'Completada')
                        <span class="badge-activo">PAGADA / COMPLETADA</span>
                    @else
                        <span class="badge-inactivo">ANULADA</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4 p-3 rounded-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
            <div class="col-md-6">
                <div style="font-size:0.72rem;color:#64748B;text-transform:uppercase;font-weight:700;">Cliente / Razón Social</div>
                <div style="font-size:1rem;font-weight:700;color:#1E293B;">{{ $venta->cliente->nombre_razon_social ?? 'Cliente Mostrador' }}</div>
                <div style="font-size:0.82rem;color:#475569;">
                    CI / NIT: <strong>{{ $venta->cliente->ci_nit ?: 'S/N' }}</strong> · Segmento: <strong>{{ $venta->cliente->tipo_cliente ?? 'Particular' }}</strong>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div style="font-size:0.72rem;color:#64748B;text-transform:uppercase;font-weight:700;">Datos de Transacción</div>
                <div style="font-size:0.82rem;color:#334155;">Fecha y Hora: <strong>{{ $venta->fecha_hora->format('d/m/Y H:i:s') }}</strong></div>
                <div style="font-size:0.82rem;color:#334155;">Modalidad de Cobro: <strong>{{ $venta->metodo_pago }}</strong></div>
                <div style="font-size:0.82rem;color:#334155;">Atendido por: <strong>{{ $venta->vendedor->nombre_completo ?? 'Mostrador' }}</strong> (Caja #{{ $venta->id_sesion_caja }})</div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-dark-custom">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código OEM / SKU</th>
                        <th>Descripción del Repuesto</th>
                        <th>Fabricante</th>
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
                                <div style="font-family:monospace;font-weight:700;color:#0F172A;">{{ $det->producto->codigo_oem ?? '-' }}</div>
                                <small class="text-muted">SKU: {{ $det->producto->codigo_interno ?? '-' }}</small>
                            </td>
                            <td>
                                <div style="font-weight:600;color:#1E293B;">{{ $det->producto->nombre_pieza ?? 'Repuesto' }}</div>
                                <small class="text-muted">{{ $det->producto->categoria->nombre ?? '' }}</small>
                            </td>
                            <td>{{ $det->producto->marca_fabricante ?? '-' }}</td>
                            <td class="text-center fw-bold">{{ (int)$det->cantidad_vendida }}</td>
                            <td class="text-end" style="font-family:monospace;">Bs. {{ number_format($det->precio_unitario_cobrado, 2) }}</td>
                            <td class="text-end fw-bold" style="font-family:monospace;">Bs. {{ number_format($det->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="border-0"></td>
                        <td class="text-end text-muted fw-semibold">Subtotal Bruto:</td>
                        <td class="text-end fw-semibold" style="font-family:monospace;">Bs. {{ number_format($subtotalBruto, 2) }}</td>
                    </tr>
                    @if($venta->descuento_total > 0)
                    <tr>
                        <td colspan="5" class="border-0"></td>
                        <td class="text-end text-warning-emphasis fw-semibold">Descuento Comercial:</td>
                        <td class="text-end text-warning-emphasis fw-semibold" style="font-family:monospace;">-Bs. {{ number_format($venta->descuento_total, 2) }}</td>
                    </tr>
                    @endif
                    <tr style="background:#F8FAFC;">
                        <td colspan="5" class="border-0"></td>
                        <td class="text-end fw-bold" style="font-size:1rem;color:#1E293B;">TOTAL PAGADO:</td>
                        <td class="text-end fw-bold" style="font-size:1.25rem;color:#007AFF;font-family:monospace;">Bs. {{ number_format($venta->total_venta, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.78rem;color:#64748B;">
            <div>
                Comprobante emitido por el sistema de gestión comercial <strong>Multipartes El Tuerca</strong>.
            </div>
            <div>Santa Cruz de la Sierra, Bolivia</div>
        </div>
    </div>
@endif
@endsection
