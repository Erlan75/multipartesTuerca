@extends('layouts.app')
@section('title', 'Historial de Costos de Adquisición — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('compras.index') }}">Compras</a></li>
    <li class="breadcrumb-item active">Historial de Costos</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-graph-up me-2" style="color:#007AFF;"></i>Historial y Análisis de Costos de Adquisición
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Consulta cronológica de precios de compra directamente desde el histórico de recepciones por repuesto para auditoría y fijación de márgenes.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('compras.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-cart-plus me-1"></i>Nueva Compra
        </a>
        <a href="{{ route('compras.index') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-journal-text me-1"></i>Listado de Compras
        </a>
    </div>
</div>

{{-- ═══ FILTRO DE PRODUCTO ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.5rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('compras.historial-costos') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">
                    Seleccionar Repuesto para Analizar <span class="text-danger">*</span>
                </label>
                <select name="id_producto" class="form-select form-select-dark" required onchange="this.form.submit()">
                    <option value="">-- Elija un repuesto del catálogo --</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id_producto }}" {{ request('id_producto') == $p->id_producto ? 'selected' : '' }}>
                            [{{ $p->codigo_interno }}] {{ $p->nombre_pieza }} {{ $p->codigo_oem ? '— OEM: ' . $p->codigo_oem : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-dark" value="{{ request('fecha_desde') }}">
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-dark" value="{{ request('fecha_hasta') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-search me-1"></i>Consultar
                </button>
                <a href="{{ route('compras.historial-costos') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

@if($productoSeleccionado)
    {{-- ═══ TARJETAS RESUMEN DE COSTOS DEL PRODUCTO ═══ --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-light p-3" style="border-left:4px solid #007AFF !important;">
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Último Costo de Compra</small>
                <div style="font-size:1.4rem;font-weight:800;color:#007AFF;font-family:monospace;margin-top:0.25rem;">
                    Bs. {{ number_format($estadisticas->ultimo_costo ?? 0, 2) }}
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Referencia de reposición vigente</small>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-light p-3" style="border-left:4px solid #34C759 !important;">
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Costo Promedio Ponderado</small>
                <div style="font-size:1.4rem;font-weight:800;color:#059669;font-family:monospace;margin-top:0.25rem;">
                    Bs. {{ number_format($estadisticas->costo_promedio ?? 0, 2) }}
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Media histórica de adquisiciones</small>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-light p-3" style="border-left:4px solid #FF9500 !important;">
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Rango Histórico (Mín / Máx)</small>
                <div style="font-size:1.15rem;font-weight:700;color:#1E293B;font-family:monospace;margin-top:0.35rem;">
                    Bs. {{ number_format($estadisticas->costo_min ?? 0, 2) }} - {{ number_format($estadisticas->costo_max ?? 0, 2) }}
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Fluctuación registrada</small>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-light p-3" style="border-left:4px solid #64748B !important;">
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Total Unidades / Compras</small>
                <div style="font-size:1.4rem;font-weight:800;color:#1E293B;font-family:monospace;margin-top:0.25rem;">
                    {{ $estadisticas->unidades_totales ?? 0 }} <span style="font-size:0.9rem;font-weight:500;color:#64748B;">u. ({{ $estadisticas->total_compras ?? 0 }} fact.)</span>
                </div>
                <small style="color:#94A3B8;font-size:0.72rem;">Volumen total recibido</small>
            </div>
        </div>
    </div>

    {{-- ═══ TABLA CRONOLÓGICA DE DETALLES DE COMPRA ═══ --}}
    <div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
        <div style="padding:1rem 1.25rem;background:#FAFBFC;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;">
            <h6 style="color:#1E293B;font-weight:700;margin:0;">
                <i class="bi bi-clock-history me-2 text-primary"></i>Cronología de Adquisiciones: {{ $productoSeleccionado->nombre_pieza }}
            </h6>
            <span style="font-family:monospace;font-size:0.8rem;background:#F1F5F9;padding:0.2rem 0.5rem;border-radius:6px;color:#007AFF;">
                {{ $productoSeleccionado->codigo_interno }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table style="width:100%;min-width:850px;border-collapse:collapse;">
                <thead>
                    <tr style="background:#FAFBFC;">
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Fecha</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">N° Factura / Nota</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;border-bottom:2px solid #E2E8F0;">Distribuidor / Proveedor</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:center;border-bottom:2px solid #E2E8F0;">Cantidad</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:right;border-bottom:2px solid #E2E8F0;">Costo Unitario</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:center;border-bottom:2px solid #E2E8F0;">Variación %</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:right;border-bottom:2px solid #E2E8F0;">Subtotal</th>
                        <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;text-align:center;border-bottom:2px solid #E2E8F0;">Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historial as $h)
                        <tr style="border-bottom:1px solid #F1F5F9;">
                            <td style="padding:0.75rem 1rem;white-space:nowrap;font-size:0.82rem;color:#475569;">
                                {{ \Carbon\Carbon::parse($h->compra->fecha_compra)->format('d/m/Y') }}
                            </td>
                            <td style="padding:0.75rem 1rem;white-space:nowrap;">
                                <span style="font-family:monospace;font-weight:600;font-size:0.82rem;color:#1E293B;">
                                    {{ $h->compra->numero_factura_nota }}
                                </span>
                            </td>
                            <td style="padding:0.75rem 1rem;font-size:0.85rem;color:#1E293B;">
                                {{ $h->compra->proveedor->razon_social ?? 'Proveedor Eliminado' }}
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:center;">
                                <span style="display:inline-block;padding:0.2rem 0.5rem;background:#F1F5F9;color:#334155;font-weight:700;border-radius:6px;font-size:0.85rem;font-family:monospace;">
                                    {{ $h->cantidad_recibida }}
                                </span>
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:right;font-family:monospace;font-weight:700;font-size:0.9rem;color:#1E293B;">
                                Bs. {{ number_format($h->costo_unitario, 2) }}
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:center;white-space:nowrap;">
                                @if($h->variacion_pct === null)
                                    <span style="font-size:0.75rem;color:#94A3B8;font-style:italic;">Inicial</span>
                                @elseif($h->variacion_pct > 0)
                                    <span style="display:inline-flex;align-items:center;gap:0.2rem;padding:0.2rem 0.5rem;background:#FEE2E2;color:#DC2626;border-radius:6px;font-size:0.75rem;font-weight:700;font-family:monospace;">
                                        <i class="bi bi-arrow-up-right"></i>+{{ number_format($h->variacion_pct, 1) }}%
                                    </span>
                                @elseif($h->variacion_pct < 0)
                                    <span style="display:inline-flex;align-items:center;gap:0.2rem;padding:0.2rem 0.5rem;background:#ECFDF5;color:#059669;border-radius:6px;font-size:0.75rem;font-weight:700;font-family:monospace;">
                                        <i class="bi bi-arrow-down-right"></i>{{ number_format($h->variacion_pct, 1) }}%
                                    </span>
                                @else
                                    <span style="font-size:0.75rem;color:#64748B;font-family:monospace;">0.0%</span>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:right;font-family:monospace;font-size:0.85rem;color:#475569;">
                                Bs. {{ number_format($h->subtotal, 2) }}
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:center;">
                                <a href="{{ route('compras.show', $h->id_compra) }}" class="btn btn-sm btn-outline-tuerca" title="Ver comprobante de compra">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                No existen recepciones de compra registradas para este repuesto en el rango indicado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    {{-- ═══ ESTADO VACÍO (SIN SELECCIÓN) ═══ --}}
    <div style="background:#fff;border:1px dashed #CBD5E1;border-radius:16px;padding:4rem 1.5rem;text-align:center;">
        <div style="width:64px;height:64px;border-radius:50%;background:#EFF6FF;color:#007AFF;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.8rem;">
            <i class="bi bi-search"></i>
        </div>
        <h5 style="color:#1E293B;font-weight:700;margin-bottom:0.35rem;">Seleccione un repuesto del selector</h5>
        <p style="color:#64748B;font-size:0.85rem;max-width:440px;margin:0 auto;">
            Elija cualquier pieza del catálogo de Multipartes para consultar su histórico de compras, fluctuación de precios de importadores y estadísticas de costo.
        </p>
    </div>
@endif
@endsection
