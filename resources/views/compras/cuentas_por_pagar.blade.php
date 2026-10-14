@extends('layouts.app')
@section('title', 'Cuentas por Pagar a Proveedores — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('compras.index') }}">Compras</a></li>
    <li class="breadcrumb-item active">Cuentas por Pagar (CU22)</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-wallet2 me-2" style="color:#007AFF;"></i>Cuentas por Pagar a Distribuidores Mayoristas (CU22)
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Control y liquidación de facturas de mercadería ingresadas bajo modalidad de crédito mediante SP Smart-DB blindado.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('compras.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-plus-lg me-1"></i>Nueva Compra
        </a>
        <a href="{{ route('compras.index') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-journal-text me-1"></i>Todas las Compras
        </a>
    </div>
</div>

{{-- ═══ ALERTAS FLASH ═══ --}}
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

{{-- ═══ TARJETAS DE DEUDA ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #FF9500 !important;">
            <div style="width:50px;height:50px;border-radius:12px;background:#FEF3C7;color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Saldo Total Adeudado</small>
                <div style="font-size:1.6rem;font-weight:800;color:#D97706;font-family:monospace;line-height:1.2;">
                    Bs. {{ number_format($totalAdeudado, 2) }}
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Compromisos pendientes a distribuidores</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #007AFF !important;">
            <div style="width:50px;height:50px;border-radius:12px;background:#EFF6FF;color:#007AFF;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Facturas Pendientes</small>
                <div style="font-size:1.6rem;font-weight:800;color:#007AFF;font-family:monospace;line-height:1.2;">
                    {{ $totalFacturasPendientes }}
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Notas de crédito sin liquidar</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #34C759 !important;">
            <div style="width:50px;height:50px;border-radius:12px;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Proveedores Acreedores</small>
                <div style="font-size:1.6rem;font-weight:800;color:#059669;font-family:monospace;line-height:1.2;">
                    {{ $proveedores->count() }}
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Empresas con balance abierto</small>
            </div>
        </div>
    </div>
</div>

{{-- ═══ BARRA DE FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('compras.cuentas-por-pagar') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-8">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Filtrar por Distribuidor</label>
                <select name="id_proveedor" class="form-select form-select-dark">
                    <option value="">Todos los distribuidores con deudas pendientes</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id_proveedor }}" {{ request('id_proveedor') == $prov->id_proveedor ? 'selected' : '' }}>
                            {{ $prov->razon_social }} (Condición: {{ $prov->condicion_comercial }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-funnel me-1"></i>Filtrar Cuentas
                </button>
                <a href="{{ route('compras.cuentas-por-pagar') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE CUENTAS POR PAGAR ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Factura / Folio</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Distribuidor / Proveedor</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Fecha Recepción</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:center;border-bottom:2px solid #E2E8F0;">Antigüedad</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Condición Pactada</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:right;border-bottom:2px solid #E2E8F0;">Monto Pendiente (Bs.)</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:center;border-bottom:2px solid #E2E8F0;">Acción de Pago (CU22)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cuentasPendientes as $cp)
                    @php
                        $dias = \Carbon\Carbon::parse($cp->fecha_compra)->diffInDays(now());
                    @endphp
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-weight:700;color:#1E293B;font-size:0.87rem;">#C-{{ str_pad($cp->id_compra, 5, '0', STR_PAD_LEFT) }}</span>
                            <div style="font-size:0.78rem;color:#007AFF;font-family:monospace;font-weight:600;">
                                Fact: {{ $cp->numero_factura_nota }}
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.87rem;">
                                {{ $cp->proveedor->razon_social ?? 'Proveedor Eliminado' }}
                            </div>
                            <div style="font-size:0.75rem;color:#64748B;">
                                Contacto: {{ $cp->proveedor->contacto_nombre ?? '—' }} ({{ $cp->proveedor->telefono ?? 'S/T' }})
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;font-size:0.83rem;color:#475569;">
                            {{ \Carbon\Carbon::parse($cp->fecha_compra)->format('d/m/Y') }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($dias > 30)
                                <span style="display:inline-block;padding:0.2rem 0.55rem;background:#FEE2E2;color:#DC2626;border-radius:6px;font-size:0.75rem;font-weight:700;">
                                    {{ $dias }} días
                                </span>
                            @elseif($dias > 15)
                                <span style="display:inline-block;padding:0.2rem 0.55rem;background:#FEF3C7;color:#D97706;border-radius:6px;font-size:0.75rem;font-weight:700;">
                                    {{ $dias }} días
                                </span>
                            @else
                                <span style="display:inline-block;padding:0.2rem 0.55rem;background:#F1F5F9;color:#334155;border-radius:6px;font-size:0.75rem;font-weight:600;">
                                    {{ $dias }} días
                                </span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#475569;">
                            {{ $cp->proveedor->condicion_comercial ?? 'Crédito' }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
                            <span style="font-weight:800;font-size:0.95rem;color:#D97706;font-family:monospace;">
                                Bs. {{ number_format($cp->monto_total, 2) }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('compras.show', $cp->id_compra) }}" class="btn btn-sm btn-outline-secondary" title="Ver detalle de factura">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-success"
                                        onclick="abrirModalPago({{ $cp->id_compra }}, '{{ addslashes($cp->numero_factura_nota) }}', '{{ number_format($cp->monto_total, 2) }}', '{{ addslashes($cp->proveedor->razon_social ?? 'Proveedor') }}')"
                                        style="border-radius:8px;padding:0.35rem 0.75rem;font-size:0.8rem;font-weight:600;">
                                    <i class="bi bi-check2-circle me-1"></i>Liquidar Pago
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:3.5rem 1rem;text-align:center;">
                            <div style="max-width:360px;margin:0 auto;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.8rem;">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <h6 style="color:#1E293B;font-weight:600;margin-bottom:0.25rem;">¡Sin compromisos pendientes!</h6>
                                <p style="color:#64748B;font-size:0.82rem;margin:0;">
                                    Todas las compras a crédito recibidas han sido debidamente liquidadas a los distribuidores.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($cuentasPendientes->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $cuentasPendientes->links() }}
        </div>
    @endif
</div>

{{-- Modal Confirmación de Liquidación de Pago (CU22) --}}
<div class="modal fade" id="modalConfirmarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered w-11/12 max-w-lg mx-auto" style="max-width: 500px;">
        <div class="modal-content" style="border-radius:18px;border:1px solid #E2E8F0;box-shadow:0 20px 45px rgba(15,23,42,0.15);">
            <form id="formConfirmarPago" method="POST" action="">
                @csrf
                <div class="modal-header py-3 px-3" style="border-bottom:1px solid #F1F5F9;">
                    <h6 class="modal-title fs-6" style="font-weight:700;color:#1E293B;">
                        <i class="bi bi-wallet2 me-2 text-success"></i>Confirmar Liquidación de Pago (CU22)
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" style="min-height:36px;min-width:36px;"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="p-3 mb-3" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;">
                        <div class="mb-2">
                            <small class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;">Distribuidor Mayorista:</small>
                            <div class="fw-bold text-dark" id="modalPagoProveedor" style="font-size:0.95rem;">-</div>
                        </div>
                        <div class="row g-2 pt-2 border-top">
                            <div class="col-6">
                                <small class="text-muted" style="font-size:0.72rem;">N° Factura:</small>
                                <div class="fw-bold font-monospace text-primary" id="modalPagoFactura">-</div>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted" style="font-size:0.72rem;">Monto a Liquidar:</small>
                                <div class="fw-bold font-monospace text-success fs-5" id="modalPagoMonto">-</div>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1 text-primary"></i>Esta operación registrará el egreso contable y asentará la nota como <strong>PAGADA</strong> mediante SP Smart-DB.
                    </p>
                </div>
                <div class="modal-footer py-2 px-3" style="border-top:1px solid #F1F5F9;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="min-height:44px;padding:0.5rem 1.25rem;">Cancelar</button>
                    <button type="submit" class="btn btn-success" style="min-height:44px;padding:0.5rem 1.25rem;">
                        <i class="bi bi-check2-circle me-1"></i>Confirmar Liquidación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function abrirModalPago(id, factura, total, proveedor) {
    document.getElementById('modalPagoProveedor').textContent = proveedor;
    document.getElementById('modalPagoFactura').textContent = factura;
    document.getElementById('modalPagoMonto').textContent = `Bs. ${total}`;
    document.getElementById('formConfirmarPago').action = `/compras/${id}/liquidar-pago`;
    new bootstrap.Modal(document.getElementById('modalConfirmarPago')).show();
}
</script>
@endpush
@endsection
