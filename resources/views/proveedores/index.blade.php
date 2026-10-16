@extends('layouts.app')
@section('title', 'Proveedores — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Proveedores</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-truck-flatbed me-2" style="color:#007AFF;"></i>Proveedores e Importadores Mayoristas
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Gestión comercial de empresas distribuidoras de repuestos, condiciones de pago y control de estado.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('proveedores.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Proveedor
        </a>
        <a href="{{ route('compras.index') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-journal-text me-1"></i>Notas de Compra
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
    <form method="GET" action="{{ route('proveedores.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Búsqueda</label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="Razón social, NIT, nombre de contacto o teléfono..."
                           value="{{ request('buscar') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Condición Comercial</label>
                <select name="condicion" class="form-select form-select-dark">
                    <option value="">Todas las condiciones</option>
                    <option value="Contado" {{ request('condicion') == 'Contado' ? 'selected' : '' }}>Contado</option>
                    <option value="Crédito 15 días" {{ request('condicion') == 'Crédito 15 días' ? 'selected' : '' }}>Crédito 15 días</option>
                    <option value="Crédito 30 días" {{ request('condicion') == 'Crédito 30 días' ? 'selected' : '' }}>Crédito 30 días</option>
                    <option value="Crédito 60 días" {{ request('condicion') == 'Crédito 60 días' ? 'selected' : '' }}>Crédito 60 días</option>
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Estado</label>
                <select name="estado" class="form-select form-select-dark">
                    <option value="">Todos</option>
                    <option value="activo" {{ request('estado') == 'activo' ? 'selected' : '' }}>Activos</option>
                    <option value="inactivo" {{ request('estado') == 'inactivo' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="{{ route('proveedores.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE PROVEEDORES ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Razón Social</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">NIT</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Contacto / Vendedor</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Teléfono / Correo</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Condición Comercial</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Estado</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proveedores as $prov)
                    <tr style="border-bottom:1px solid #F1F5F9;{{ !$prov->estado ? 'background:#F8FAFC;opacity:0.8;' : '' }}">
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.87rem;">{{ $prov->razon_social }}</div>
                            @if($prov->direccion)
                                <div style="font-size:0.75rem;color:#64748B;"><i class="bi bi-geo-alt me-1"></i>{{ $prov->direccion }}</div>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-family:monospace;font-size:0.82rem;color:#475569;">
                                {{ $prov->nit ?? 'S/N' }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.85rem;color:#334155;">
                            {{ $prov->contacto_nombre ?? '—' }}
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;">
                            @if($prov->telefono)
                                <div style="color:#1E293B;"><i class="bi bi-telephone me-1 text-muted"></i>{{ $prov->telefono }}</div>
                            @endif
                            @if($prov->correo)
                                <div style="color:#64748B;font-size:0.75rem;"><i class="bi bi-envelope me-1 text-muted"></i>{{ $prov->correo }}</div>
                            @endif
                            @if(!$prov->telefono && !$prov->correo)
                                <span style="color:#94A3B8;">—</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="display:inline-block;padding:0.25rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:600;background:#F1F5F9;color:#334155;border:1px solid #E2E8F0;">
                                {{ $prov->condicion_comercial }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($prov->estado)
                                <span class="badge-activo">Activo</span>
                            @else
                                <span class="badge-inactivo">Inactivo</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('compras.index', ['id_proveedor' => $prov->id_proveedor]) }}" class="btn btn-sm btn-outline-secondary" title="Ver compras a este proveedor">
                                    <i class="bi bi-bag"></i>
                                </a>
                                <a href="{{ route('proveedores.edit', $prov->id_proveedor) }}" class="btn btn-sm btn-outline-tuerca" title="Editar datos">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('proveedores.toggle-status', $prov->id_proveedor) }}" method="POST" onsubmit="return confirm('¿Está seguro de {{ $prov->estado ? 'desactivar' : 'reactivar' }} al proveedor {{ addslashes($prov->razon_social) }}?');" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $prov->estado ? 'btn-outline-danger' : 'btn-outline-success' }}" style="border-radius:8px;padding:0.25rem 0.5rem;" title="{{ $prov->estado ? 'Desactivar proveedor' : 'Reactivar proveedor' }}">
                                        <i class="bi {{ $prov->estado ? 'bi-slash-circle' : 'bi-check2' }}"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-truck-flatbed fs-2 d-block mb-2 text-muted"></i>
                            No se encontraron proveedores registrados con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($proveedores->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $proveedores->links() }}
        </div>
    @endif
</div>
@endsection
