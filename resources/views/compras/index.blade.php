@extends('layouts.app')
@section('title', 'Notas de Compra — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Compras</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-cart-check me-2" style="color:#007AFF;"></i>Recepción de Compras y Abastecimiento
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Historial de notas y facturas de adquisición procesadas en almacén mediante SP Smart-DB.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('compras.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-plus-lg me-1"></i>Nueva Recepción de Compra
        </a>
        <a href="{{ route('compras.cuentas-por-pagar') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-wallet2 me-1"></i>Cuentas por Pagar (CU22)
        </a>
        <a href="{{ route('compras.historial-costos') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;padding:0.55rem 1.1rem;">
            <i class="bi bi-graph-up me-1"></i>Historial de Costos (CU21)
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

{{-- ═══ BARRA DE FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('compras.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">N° Factura / Nota</label>
                <div style="position:relative;">
                    <i class="bi bi-receipt" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar_factura" class="form-control form-control-dark"
                           placeholder="Buscar número..."
                           value="{{ request('buscar_factura') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Proveedor</label>
                <select name="id_proveedor" class="form-select form-select-dark">
                    <option value="">Todos los distribuidores</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id_proveedor }}" {{ request('id_proveedor') == $prov->id_proveedor ? 'selected' : '' }}>
                            {{ $prov->razon_social }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Modalidad</label>
                <select name="tipo_pago" class="form-select form-select-dark">
                    <option value="">Todas</option>
                    <option value="Contado" {{ request('tipo_pago') == 'Contado' ? 'selected' : '' }}>Contado</option>
                    <option value="Crédito" {{ request('tipo_pago') == 'Crédito' ? 'selected' : '' }}>Crédito</option>
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Estado de Pago</label>
                <select name="estado_pago" class="form-select form-select-dark">
                    <option value="">Todos</option>
                    <option value="Pagado" {{ request('estado_pago') == 'Pagado' ? 'selected' : '' }}>Pagado</option>
                    <option value="Pendiente" {{ request('estado_pago') == 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('compras.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE NOTAS DE COMPRA ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Folio / Factura</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Fecha Emisión</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Proveedor</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Receptor</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Modalidad</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:2px solid #E2E8F0;">Total (Bs.)</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Estado</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($compras as $c)
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-weight:700;color:#1E293B;font-size:0.87rem;">#C-{{ str_pad($c->id_compra, 5, '0', STR_PAD_LEFT) }}</span>
                            <div style="font-size:0.75rem;color:#007AFF;font-family:monospace;font-weight:600;">
                                Factura: {{ $c->numero_factura_nota }}
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;font-size:0.82rem;color:#475569;">
                            {{ \Carbon\Carbon::parse($c->fecha_compra)->format('d/m/Y') }}
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.85rem;">
                                {{ $c->proveedor->razon_social ?? 'Proveedor Eliminado' }}
                            </div>
                            @if($c->proveedor && $c->proveedor->nit)
                                <div style="font-size:0.72rem;color:#64748B;">NIT: {{ $c->proveedor->nit }}</div>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#334155;">
                            {{ $c->usuarioReceptor->nombre ?? 'Sistema' }}
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="display:inline-block;padding:0.25rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:600;{{ $c->tipo_pago === 'Contado' ? 'background:#EFF6FF;color:#007AFF;' : 'background:#FFFBEB;color:#D97706;' }}">
                                {{ $c->tipo_pago }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
                            <span style="font-weight:700;font-size:0.9rem;color:#1E293B;font-family:monospace;">
                                Bs. {{ number_format($c->monto_total, 2) }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($c->estado_pago === 'Pagado')
                                <span class="badge-activo"><i class="bi bi-check2-circle me-1"></i>Pagado</span>
                            @elseif($c->estado_pago === 'Pendiente')
                                <span class="badge-stock-bajo"><i class="bi bi-clock-history me-1"></i>Pendiente</span>
                            @else
                                <span class="badge-inactivo">{{ $c->estado_pago }}</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('compras.show', $c->id_compra) }}" class="btn btn-sm btn-outline-tuerca" title="Ver detalle de factura y repuestos">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($c->tipo_pago === 'Crédito' && $c->estado_pago === 'Pendiente')
                                    <form action="{{ route('compras.liquidar-pago', $c->id_compra) }}" method="POST" onsubmit="return confirm('¿Confirmar liquidación total de la compra #{{ $c->id_compra }} por Bs. {{ number_format($c->monto_total, 2) }}?');" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" style="border-radius:8px;padding:0.25rem 0.55rem;font-size:0.78rem;" title="Liquidar pago a distribuidor (CU22)">
                                            <i class="bi bi-cash-stack me-1"></i>Pagar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-cart-x fs-2 d-block mb-2 text-muted"></i>
                            No se encontraron recepciones de compra registradas con los filtros indicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($compras->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $compras->links() }}
        </div>
    @endif
</div>
@endsection
