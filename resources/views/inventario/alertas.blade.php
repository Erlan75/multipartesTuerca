@extends('layouts.app')
@section('title', 'Monitoreo de Alertas de Stock — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('productos.index') }}">Inventario</a></li>
    <li class="breadcrumb-item active">Alertas de Stock</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-shield-exclamation me-2" style="color:#FF3B30;"></i>Monitoreo y Alertas de Stock Crítico
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Supervisión continua de repuestos con existencia por debajo de stock mínimo o seguridad para prevenir quiebres de inventario.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('compras.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-cart-plus me-1"></i>Generar Compra
        </a>
        <a href="{{ route('inventario.ajustes') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-arrow-left-right me-1"></i>Ajustes de Inventario
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

{{-- ═══ TARJETAS DE MÉTRICAS (SEMÁFORO) ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #FF3B30 !important;">
            <div style="width:48px;height:48px;border-radius:12px;background:#FEE2E2;color:#DC2626;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                <i class="bi bi-x-octagon-fill"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Agotados (Stock 0)</small>
                <div style="font-size:1.5rem;font-weight:700;color:#DC2626;line-height:1.2;">{{ $totalAgotados }}</div>
                <small style="color:#94A3B8;font-size:0.75rem;">Quiebre total de inventario</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #FF9500 !important;">
            <div style="width:48px;height:48px;border-radius:12px;background:#FEF3C7;color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Críticos (≤ Mínimo)</small>
                <div style="font-size:1.5rem;font-weight:700;color:#D97706;line-height:1.2;">{{ $totalCriticos }}</div>
                <small style="color:#94A3B8;font-size:0.75rem;">Requieren reposición inmediata</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #F59E0B !important;">
            <div style="width:48px;height:48px;border-radius:12px;background:#FFFBEB;color:#B45309;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                <i class="bi bi-shield-alert"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Zona de Seguridad</small>
                <div style="font-size:1.5rem;font-weight:700;color:#B45309;line-height:1.2;">{{ $totalSeguridad }}</div>
                <small style="color:#94A3B8;font-size:0.75rem;">Entre mínimo y seguridad</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-light p-3 d-flex align-items-center gap-3" style="border-left:4px solid #007AFF !important;">
            <div style="width:48px;height:48px;border-radius:12px;background:#EFF6FF;color:#007AFF;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                <i class="bi bi-boxes"></i>
            </div>
            <div>
                <small style="color:#64748B;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Total en Alerta</small>
                <div style="font-size:1.5rem;font-weight:700;color:#007AFF;line-height:1.2;">{{ $totalRiesgo }}</div>
                <small style="color:#94A3B8;font-size:0.75rem;">Catálogo activo vulnerable</small>
            </div>
        </div>
    </div>
</div>

{{-- ═══ BARRA DE FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('inventario.alertas') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Búsqueda de Repuesto</label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="Código interno, OEM, denominación de pieza..."
                           value="{{ request('buscar') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Nivel de Severidad</label>
                <select name="nivel" class="form-select form-select-dark">
                    <option value="">Todas las alertas de stock</option>
                    <option value="agotado" {{ request('nivel') == 'agotado' ? 'selected' : '' }}>⛔ Agotados (Stock = 0)</option>
                    <option value="critico" {{ request('nivel') == 'critico' ? 'selected' : '' }}>⚠️ Críticos (Stock ≤ Mínimo)</option>
                    <option value="seguridad" {{ request('nivel') == 'seguridad' ? 'selected' : '' }}>🟡 En Seguridad (≤ Seguridad)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Categoría</label>
                <select name="id_categoria" class="form-select form-select-dark">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id_categoria }}" {{ request('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('inventario.alertas') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE ALERTAS ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Severidad</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Códigos</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Repuesto</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Categoría</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Ubicación Bodega</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Márgenes (Mín / Seg)</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Stock Actual</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productosEnAlerta as $p)
                    @php
                        if ($p->stock_actual == 0) {
                            $badgeClass = 'background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;';
                            $icono = 'bi-x-octagon-fill';
                            $estadoTexto = 'AGOTADO';
                            $rowBg = 'background:rgba(254, 242, 242, 0.4);';
                        } elseif ($p->stock_actual <= $p->stock_minimo) {
                            $badgeClass = 'background:#FEF3C7;color:#D97706;border:1px solid #FDE68A;';
                            $icono = 'bi-exclamation-triangle-fill';
                            $estadoTexto = 'CRÍTICO';
                            $rowBg = '';
                        } else {
                            $badgeClass = 'background:#FFFBEB;color:#B45309;border:1px solid #FDE68A;';
                            $icono = 'bi-shield-alert';
                            $estadoTexto = 'SEGURIDAD';
                            $rowBg = '';
                        }
                    @endphp
                    <tr style="border-bottom:1px solid #F1F5F9;{{ $rowBg }}">
                        <td style="padding:0.75rem 1rem;">
                            <span style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.6rem;border-radius:20px;font-size:0.72rem;font-weight:700;letter-spacing:0.5px;{{ $badgeClass }}">
                                <i class="bi {{ $icono }}"></i>{{ $estadoTexto }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-family:monospace;font-weight:600;color:#007AFF;background:#F1F5F9;padding:0.2rem 0.45rem;border-radius:6px;font-size:0.8rem;">
                                {{ $p->codigo_interno }}
                            </span>
                            @if($p->codigo_oem)
                                <div style="font-size:0.72rem;color:#64748B;margin-top:0.2rem;">
                                    OEM: <span style="font-family:monospace;">{{ $p->codigo_oem }}</span>
                                </div>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.85rem;">{{ $p->nombre_pieza }}</div>
                            <div style="font-size:0.75rem;color:#94A3B8;">{{ Str::limit($p->descripcion, 50) }}</div>
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#475569;">
                            {{ $p->categoria->nombre ?? 'Sin categoría' }}
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.8rem;">
                            @if($p->pasillo || $p->estante || $p->nivel_gaveta)
                                <span style="display:inline-flex;align-items:center;gap:0.25rem;background:#F8FAFC;border:1px solid #E2E8F0;padding:0.25rem 0.5rem;border-radius:6px;color:#334155;">
                                    <i class="bi bi-geo-alt-fill text-primary"></i>
                                    {{ $p->pasillo ?? '-' }} / {{ $p->estante ?? '-' }} / {{ $p->nivel_gaveta ?? '-' }}
                                </span>
                            @else
                                <span style="color:#94A3B8;font-style:italic;font-size:0.75rem;">Sin ubicación</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;font-size:0.8rem;color:#64748B;">
                            <span title="Stock Mínimo" style="font-weight:600;color:#D97706;">{{ $p->stock_minimo }}</span>
                            /
                            <span title="Stock Seguridad" style="color:#64748B;">{{ $p->stock_seguridad }}</span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <span style="display:inline-block;padding:0.25rem 0.65rem;border-radius:8px;font-size:0.95rem;font-weight:700;font-family:monospace;{{ $p->stock_actual == 0 ? 'background:#FEE2E2;color:#DC2626;' : 'background:#FEF3C7;color:#B45309;' }}">
                                {{ $p->stock_actual }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('compras.create') }}" class="btn btn-sm btn-outline-tuerca" title="Pedir Repuesto a Proveedor">
                                    <i class="bi bi-cart-plus me-1"></i>Comprar
                                </a>
                                <a href="{{ route('inventario.ajustes', ['buscar' => $p->codigo_interno]) }}" class="btn btn-sm btn-outline-secondary" title="Revisar / Ajustar Físico">
                                    <i class="bi bi-sliders"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:3.5rem 1rem;text-align:center;">
                            <div style="max-width:360px;margin:0 auto;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.8rem;">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h6 style="color:#1E293B;font-weight:600;margin-bottom:0.25rem;">¡Excelente estado de almacén!</h6>
                                <p style="color:#64748B;font-size:0.82rem;margin:0;">
                                    No se encontraron repuestos con existencia por debajo de sus umbrales de stock mínimo o seguridad.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($productosEnAlerta->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $productosEnAlerta->links() }}
        </div>
    @endif
</div>
@endsection
