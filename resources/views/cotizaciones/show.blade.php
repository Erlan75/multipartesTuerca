@extends('layouts.app')
@section('title', 'Proforma #COT-' . str_pad($cotizacion->id_cotizacion, 5, '0', STR_PAD_LEFT) . ' — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    @if(auth()->user()->id_rol !== 5)
        <li class="breadcrumb-item"><a href="{{ route('cotizaciones.index') }}">Cotizaciones</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('mis-cotizaciones.index') }}">Mis Cotizaciones</a></li>
    @endif
    <li class="breadcrumb-item active">#COT-{{ str_pad($cotizacion->id_cotizacion, 5, '0', STR_PAD_LEFT) }}</li>
@endsection

@push('styles')
<style>
    @media print {
        .sidebar, .top-navbar, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; width: 100% !important; }
        .page-content { padding: 0 !important; }
        .proforma-sheet { box-shadow: none !important; border: 1px solid #000 !important; }
        body { background: #fff !important; }
    }
</style>
@endpush

@section('content')
@php
    $estVisual = $cotizacion->estado_visual;
    $dias = (int)$cotizacion->dias_restantes;
    $folio = 'COT-' . str_pad($cotizacion->id_cotizacion, 5, '0', STR_PAD_LEFT);
    $textoWa = "Hola *{$cotizacion->cliente->nombre_razon_social}*, le enviamos desde *Multipartes El Tuerca (Santa Cruz)* su Proforma *#{$folio}* por un total de *Bs. " . number_format($cotizacion->monto_total, 2) . "* (Válida hasta el " . $cotizacion->fecha_vencimiento->format('d/m/Y') . ").";
    $telCliente = preg_replace('/[^0-9]/', '', $cotizacion->cliente->telefono ?? '');
    $urlWa = $telCliente && $telCliente !== '00000000'
        ? "https://wa.me/591{$telCliente}?text=" . urlencode($textoWa)
        : "https://wa.me/?text=" . urlencode($textoWa);
@endphp

{{-- Barra de acciones (No se imprime) --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
    <div>
        @if(auth()->user()->id_rol === 5)
            <a href="{{ route('mis-cotizaciones.index') }}" class="btn btn-outline-tuerca">
                <i class="bi bi-arrow-left me-1"></i>Volver a Mis Cotizaciones
            </a>
        @else
            <a href="{{ route('cotizaciones.index') }}" class="btn btn-outline-tuerca">
                <i class="bi bi-arrow-left me-1"></i>Volver a Bandeja
            </a>
        @endif
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" onclick="window.print()" class="btn btn-outline-tuerca">
            <i class="bi bi-printer me-1"></i>Imprimir / Guardar PDF
        </button>
        <a href="{{ $urlWa }}" target="_blank" class="btn btn-success" style="border-radius:10px;font-size:0.85rem;font-weight:600;">
            <i class="bi bi-whatsapp me-1"></i>Enviar Proforma por WhatsApp
        </a>
        @if($estVisual === 'VIGENTE' && in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Vendedor', 'Cajero']))
            <button type="button" class="btn btn-tuerca" data-bs-toggle="modal" data-bs-target="#modalConvertirShow">
                <i class="bi bi-cart-check me-1"></i>Convertir en Nota de Venta
            </button>
        @endif
    </div>
</div>

{{-- Hoja de Proforma --}}
<div class="card-light p-4 p-md-5 proforma-sheet" style="max-width:920px;margin:0 auto;">
    {{-- Encabezado Comercial --}}
    <div class="row border-bottom pb-4 mb-4 align-items-center">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="width:42px;height:42px;border-radius:10px;background:#007AFF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div>
                    <h4 class="mb-0" style="font-weight:800;color:#0F172A;letter-spacing:-0.02em;">MULTIPARTES EL TUERCA</h4>
                    <small style="color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Especialistas en Autopartes Japonesas y Coreanas</small>
                </div>
            </div>
            <div style="font-size:0.8rem;color:#475569;line-height:1.5;">
                <div><i class="bi bi-geo-alt me-1"></i>Av. Cristóbal de Mendoza #450 (2do Anillo), Santa Cruz de la Sierra - Bolivia</div>
                <div><i class="bi bi-telephone me-1"></i>Tel: (591) 3-345-6789 · WhatsApp Comercial: (+591) 700-12345</div>
            </div>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <div style="font-size:0.75rem;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.08em;">PROFORMA COMERCIAL</div>
            <div style="font-size:1.6rem;font-weight:800;color:#007AFF;font-family:monospace;">#{{ $folio }}</div>
            <div class="mt-1">
                @if($estVisual === 'VIGENTE')
                    <span class="badge-activo">VIGENTE ({{ $dias === 0 ? 'Vence hoy' : $dias . ' días restantes' }})</span>
                @elseif($estVisual === 'CONVERTIDA')
                    <span class="badge" style="background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE;">CONVERTIDA EN VENTA</span>
                @elseif($estVisual === 'VENCIDA')
                    <span class="badge-stock-bajo">VENCIDA (hace {{ abs($dias) }} días)</span>
                @else
                    <span class="badge-inactivo">ANULADA</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Datos del Cliente y Vigencia --}}
    <div class="row g-3 mb-4 p-3 rounded-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
        <div class="col-md-6">
            <div style="font-size:0.72rem;color:#64748B;text-transform:uppercase;font-weight:700;">Datos del Cliente / Taller</div>
            <div style="font-size:1rem;font-weight:700;color:#1E293B;">{{ $cotizacion->cliente->nombre_razon_social }}</div>
            <div style="font-size:0.82rem;color:#475569;">
                CI / NIT: <strong>{{ $cotizacion->cliente->ci_nit ?: 'S/N' }}</strong> · Categoría: <strong>{{ $cotizacion->cliente->tipo_cliente }}</strong>
            </div>
            <div style="font-size:0.82rem;color:#475569;">
                Teléfono / WhatsApp: <strong>{{ $cotizacion->cliente->telefono ?: 'No especificado' }}</strong>
            </div>
        </div>
        <div class="col-md-6 text-md-end">
            <div style="font-size:0.72rem;color:#64748B;text-transform:uppercase;font-weight:700;">Condiciones de Emisión</div>
            <div style="font-size:0.83rem;color:#334155;">
                Fecha de Emisión: <strong>{{ $cotizacion->fecha_emision->format('d/m/Y H:i') }}</strong>
            </div>
            <div style="font-size:0.83rem;color:#334155;">
                Válida hasta: <strong class="text-danger">{{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}</strong>
            </div>
            <div style="font-size:0.83rem;color:#334155;">
                Asesor Comercial: <strong>{{ $cotizacion->usuario->nombre_completo ?? 'Mostrador' }}</strong>
            </div>
        </div>
    </div>

    {{-- Detalle de Ítems Cotizados --}}
    <div class="table-responsive mb-4">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código OEM / SKU</th>
                    <th>Descripción Técnica del Repuesto</th>
                    <th>Marca / Procedencia</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-end">Precio Unit. (Bs.)</th>
                    <th class="text-end">Subtotal (Bs.)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detalles as $idx => $det)
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
                        <td>
                            {{ $det->producto->marca_fabricante ?? '-' }}
                            @if($det->producto && $det->producto->procedencia)
                                <small class="text-muted d-block">{{ $det->producto->procedencia }}</small>
                            @endif
                        </td>
                        <td class="text-center fw-bold">{{ (int)$det->cantidad }}</td>
                        <td class="text-end" style="font-family:monospace;">Bs. {{ number_format($det->precio_unitario_cotizado, 2) }}</td>
                        <td class="text-end fw-bold" style="font-family:monospace;">Bs. {{ number_format($det->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;">
                    <td colspan="5" class="border-0"></td>
                    <td class="text-end fw-bold" style="font-size:0.95rem;color:#1E293B;">TOTAL PRESUPUESTADO:</td>
                    <td class="text-end fw-bold" style="font-size:1.15rem;color:#007AFF;font-family:monospace;">
                        Bs. {{ number_format($cotizacion->monto_total, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Notas al pie --}}
    <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.78rem;color:#64748B;">
        <div>
            <strong>Nota:</strong> Los precios expresados en Bolivianos (Bs.) se mantienen garantizados hasta la fecha de vencimiento ({{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}), sujetos a disponibilidad física al momento de confirmar el pedido.
        </div>
        <div class="text-end">
            Multipartes El Tuerca · Santa Cruz
        </div>
    </div>
</div>

{{-- Modal de Conversión a Venta desde la vista Show --}}
@if($estVisual === 'VIGENTE' && in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Vendedor', 'Cajero']))
<div class="modal fade no-print" id="modalConvertirShow" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cart-check-fill text-success me-2"></i>Convertir #{{ $folio }} en Nota de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('cotizaciones.convertir', $cotizacion->id_cotizacion) }}" method="POST">
                @csrf
                <div class="modal-body">
                    @if(!$sesionAbierta)
                        <div class="alert alert-tuerca-error">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            No existe una sesión de caja abierta actualmente. Debe aperturar caja antes de facturar.
                        </div>
                    @else
                        <input type="hidden" name="id_sesion_caja" value="{{ $sesionAbierta->id_sesion }}">
                        <div class="mb-3">
                            <label class="form-label-dark">Modalidad de Cobro <span class="text-danger">*</span></label>
                            <select name="metodo_pago" class="form-select form-select-dark" required>
                                <option value="Efectivo">Efectivo (Bs.)</option>
                                <option value="Transferencia/QR">Transferencia Bancaria / QR Simple</option>
                                <option value="Tarjeta Débito/Crédito">Tarjeta Débito / Crédito</option>
                                <option value="Mixto">Pago Mixto</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-dark">Descuento Comercial Adicional (Bs.)</label>
                            <input type="number" step="0.01" min="0" name="descuento_total" class="form-control form-control-dark" value="0.00">
                        </div>
                        <div class="mb-2">
                            <label class="form-label-dark">N° de Referencia / Observación de Cobro (Opcional)</label>
                            <input type="text" name="nro_referencia" class="form-control form-control-dark" placeholder="Ej: Comprobante QR o Efectivo en mostrador">
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    @if($sesionAbierta)
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>Confirmar Conversión y Cobro
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
