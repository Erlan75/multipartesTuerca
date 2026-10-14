@extends('layouts.app')
@section('title', 'Detalle de Compra #' . $compra->id_compra . ' — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('compras.index') }}">Compras</a></li>
    <li class="breadcrumb-item active">Factura #{{ $compra->numero_factura_nota }}</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-receipt me-2" style="color:#007AFF;"></i>Recepción de Compra #C-{{ str_pad($compra->id_compra, 5, '0', STR_PAD_LEFT) }}
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Factura / Nota de Entrega: <strong class="text-dark">{{ $compra->numero_factura_nota }}</strong> | Registrada el {{ \Carbon\Carbon::parse($compra->fecha_compra)->format('d/m/Y') }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()" style="border-radius:10px;font-size:0.85rem;">
            <i class="bi bi-printer me-1"></i>Imprimir Comprobante
        </button>
        <a href="{{ route('compras.index') }}" class="btn btn-outline-tuerca" style="border-radius:10px;font-size:0.85rem;">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="border-radius:12px;background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div style="font-size:0.9rem;">{{ session('success') }}</div>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div style="font-size:0.9rem;">{{ session('error') }}</div>
    </div>
@endif

{{-- ═══ TARJETAS DE INFORMACIÓN COMERCIAL ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card-light p-4 h-100">
            <h6 style="color:#1E293B;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
                <i class="bi bi-building text-primary"></i>Datos del Distribuidor / Proveedor
            </h6>
            <div class="d-flex flex-column gap-2" style="font-size:0.85rem;">
                <div>
                    <span style="color:#64748B;">Razón Social:</span>
                    <strong style="color:#1E293B;font-size:0.95rem;">{{ $compra->proveedor->razon_social ?? 'No especificado' }}</strong>
                </div>
                <div>
                    <span style="color:#64748B;">NIT:</span>
                    <span style="font-family:monospace;font-weight:600;">{{ $compra->proveedor->nit ?? 'S/N' }}</span>
                </div>
                <div>
                    <span style="color:#64748B;">Contacto:</span>
                    <span>{{ $compra->proveedor->contacto_nombre ?? '—' }} ({{ $compra->proveedor->telefono ?? 'Sin teléfono' }})</span>
                </div>
                <div>
                    <span style="color:#64748B;">Dirección:</span>
                    <span>{{ $compra->proveedor->direccion ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-light p-4 h-100">
            <h6 style="color:#1E293B;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
                <i class="bi bi-info-circle text-primary"></i>Estado de Recepción y Pago
            </h6>
            <div class="d-flex flex-column gap-2" style="font-size:0.85rem;">
                <div>
                    <span style="color:#64748B;">Usuario Receptor:</span>
                    <strong>{{ $compra->usuarioReceptor->nombre ?? 'Sistema' }}</strong> ({{ $compra->usuarioReceptor->rol->nombre_rol ?? 'Bodeguero' }})
                </div>
                <div>
                    <span style="color:#64748B;">Modalidad de Pago:</span>
                    <span class="badge bg-light text-dark border">{{ $compra->tipo_pago }}</span>
                </div>
                <div>
                    <span style="color:#64748B;">Estado de Factura:</span>
                    @if($compra->estado_pago === 'Pagado')
                        <span class="badge-activo"><i class="bi bi-check-circle me-1"></i>Liquidado / Pagado</span>
                    @elseif($compra->estado_pago === 'Pendiente')
                        <span class="badge-stock-bajo"><i class="bi bi-clock-history me-1"></i>Crédito Pendiente de Pago</span>
                    @else
                        <span class="badge-inactivo">{{ $compra->estado_pago }}</span>
                    @endif
                </div>
                @if($compra->tipo_pago === 'Crédito' && $compra->estado_pago === 'Pendiente')
                    <div class="pt-2 mt-1 border-top">
                        <form action="{{ route('compras.liquidar-pago', $compra->id_compra) }}" method="POST" onsubmit="return confirm('¿Confirmar liquidación total de la compra #{{ $compra->id_compra }} por Bs. {{ number_format($compra->monto_total, 2) }}?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success" style="border-radius:8px;padding:0.4rem 1rem;font-size:0.82rem;">
                                <i class="bi bi-cash-stack me-1"></i>Liquidar Deuda Ahora (CU22)
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ═══ TABLA DE REPUESTOS INGRESADOS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div style="padding:1rem 1.25rem;background:#FAFBFC;border-bottom:1px solid #E2E8F0;">
        <h6 style="color:#1E293B;font-weight:700;margin:0;">
            <i class="bi bi-box-seam me-2 text-primary"></i>Repuestos Recibidos en Bodega ({{ $compra->detalles->count() }} ítems)
        </h6>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">#</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Código Int. / OEM</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Descripción de Repuesto</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Categoría</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:center;border-bottom:2px solid #E2E8F0;">Cantidad Recibida</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:right;border-bottom:2px solid #E2E8F0;">Costo Unit. (Bs.)</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:right;border-bottom:2px solid #E2E8F0;">Subtotal (Bs.)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->detalles as $idx => $d)
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:0.75rem 1rem;font-size:0.8rem;color:#94A3B8;">{{ $idx + 1 }}</td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-family:monospace;font-weight:600;color:#007AFF;background:#F1F5F9;padding:0.2rem 0.45rem;border-radius:6px;font-size:0.8rem;">
                                {{ $d->producto->codigo_interno ?? 'S/C' }}
                            </span>
                            @if($d->producto && $d->producto->codigo_oem)
                                <div style="font-size:0.72rem;color:#64748B;">OEM: {{ $d->producto->codigo_oem }}</div>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.85rem;">{{ $d->producto->nombre_pieza ?? 'Repuesto eliminado' }}</div>
                            <div style="font-size:0.75rem;color:#94A3B8;">
                                Ubicación: {{ $d->producto->pasillo ?? '-' }} / {{ $d->producto->estante ?? '-' }} / {{ $d->producto->nivel_gaveta ?? '-' }}
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#475569;">
                            {{ $d->producto->categoria->nombre ?? '—' }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <span style="display:inline-block;padding:0.2rem 0.6rem;background:#ECFDF5;color:#059669;font-weight:700;border-radius:8px;font-size:0.88rem;font-family:monospace;">
                                +{{ $d->cantidad_recibida }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-family:monospace;font-size:0.85rem;color:#475569;">
                            Bs. {{ number_format($d->costo_unitario, 2) }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-family:monospace;font-weight:700;font-size:0.9rem;color:#1E293B;">
                            Bs. {{ number_format($d->subtotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #E2E8F0;background:#FAFBFC;">
                    <td colspan="4" style="padding:0.85rem 1rem;font-weight:600;color:#64748B;">
                        Total Unidades Recibidas: <strong class="text-dark">{{ $compra->detalles->sum('cantidad_recibida') }}</strong>
                    </td>
                    <td colspan="2" style="padding:0.85rem 1rem;text-align:right;font-weight:700;color:#64748B;">
                        TOTAL DE COMPRA:
                    </td>
                    <td style="padding:0.85rem 1rem;text-align:right;">
                        <span style="font-weight:800;font-size:1.15rem;color:#007AFF;font-family:monospace;">
                            Bs. {{ number_format($compra->monto_total, 2) }}
                        </span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
