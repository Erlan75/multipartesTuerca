@extends('layouts.app')
@section('title', 'Productos — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Productos</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-box-seam me-2" style="color:#007AFF;"></i>Gestión de Repuestos e Inventario
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Administra el catálogo completo de repuestos, precios, stock y ubicación en bodega.
        </p>
    </div>
    <a href="{{ route('productos.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Repuesto
    </a>
</div>

{{-- ═══ FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('productos.index') }}">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-md-5">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.2rem;display:block;">
                    Búsqueda Rápida / Código Cruzado (OEM o Alternativo)
                </label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="Código interno, OEM, código alternativo, pieza..."
                           value="{{ request('buscar') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.2rem;display:block;">Categoría</label>
                <select name="id_categoria" class="form-select form-select-dark">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id_categoria }}" {{ request('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.2rem;display:block;">Estado</label>
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
                <a href="{{ route('productos.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>

        {{-- Filtros Técnicos de Vehículo (CU12) --}}
        <div class="pt-2 border-top" style="border-color:#F1F5F9 !important;">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <span style="font-size:0.75rem;font-weight:600;color:#007AFF;">
                        <i class="bi bi-truck me-1"></i>Filtro Vehicular (CU12):
                    </span>
                </div>
                <div class="col-md-3">
                    <select name="marca_vehiculo" class="form-select form-select-dark form-select-sm" style="font-size:0.8rem;">
                        <option value="">Marca de Vehículo...</option>
                        @foreach($marcasVehiculos as $m)
                            <option value="{{ $m }}" {{ request('marca_vehiculo') == $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="modelo_vehiculo" class="form-control form-control-dark form-control-sm"
                           placeholder="Modelo (ej: Corolla, Hilux)..."
                           value="{{ request('modelo_vehiculo') }}" style="font-size:0.8rem;">
                </div>
                <div class="col-md-2">
                    <input type="number" name="anio_vehiculo" class="form-control form-control-dark form-control-sm"
                           placeholder="Año (ej: 2018)..."
                           value="{{ request('anio_vehiculo') }}" style="font-size:0.8rem;">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary" type="submit" style="font-size:0.75rem;padding:0.35rem 0.7rem;">
                        Aplicar Vehicular
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE PRODUCTOS ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Código Int.</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">OEM</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Pieza / Especificación</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Categoría</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Marca</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Precio (Bs.)</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Stock</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Ubicación</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Estado</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productos as $p)
                <tr style="border-bottom:1px solid #F1F5F9;transition:background 0.15s;" onmouseenter="this.style.background='#F8FAFC'" onmouseleave="this.style.background='transparent'">
                    {{-- Código Interno --}}
                    <td style="padding:0.7rem 1rem;">
                        <span style="font-family:'SF Mono','Fira Code',monospace;font-weight:600;font-size:0.82rem;color:#007AFF;">
                            {{ $p->codigo_interno }}
                        </span>
                    </td>

                    {{-- OEM --}}
                    <td style="padding:0.7rem 1rem;">
                        <span style="font-family:'SF Mono','Fira Code',monospace;font-size:0.8rem;color:#64748B;">
                            {{ $p->codigo_oem ?: '—' }}
                        </span>
                    </td>

                    {{-- Pieza / Especificación --}}
                    <td style="padding:0.7rem 1rem;">
                        <div style="font-weight:500;color:#1E293B;font-size:0.85rem;">{{ Str::limit($p->nombre_pieza, 40) }}</div>
                        @if($p->procedencia)
                            <div style="color:#94A3B8;font-size:0.72rem;">{{ $p->procedencia }} · {{ $p->unidad_medida }}</div>
                        @endif
                    </td>

                    {{-- Categoría --}}
                    <td style="padding:0.7rem 1rem;">
                        <span style="display:inline-block;background:#F5F3FF;color:#7C3AED;font-size:0.72rem;font-weight:600;padding:0.2rem 0.55rem;border-radius:6px;border:1px solid #DDD6FE;">
                            {{ $p->categoria->nombre }}
                        </span>
                    </td>

                    {{-- Marca --}}
                    <td style="padding:0.7rem 1rem;font-size:0.85rem;color:#334155;">
                        {{ $p->marca_fabricante ?? '—' }}
                    </td>

                    {{-- Precio --}}
                    <td style="padding:0.7rem 1rem;text-align:right;">
                        <span style="font-weight:600;color:#1E293B;font-size:0.88rem;">{{ number_format($p->precio_venta_sugerido, 2) }}</span>
                    </td>

                    {{-- Stock --}}
                    <td style="padding:0.7rem 1rem;text-align:center;">
                        @if($p->stock_actual <= $p->stock_minimo)
                            <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#FFFBEB;color:#D97706;font-size:0.75rem;font-weight:600;padding:0.25rem 0.6rem;border-radius:6px;border:1px solid #FDE68A;">
                                <i class="bi bi-exclamation-triangle" style="font-size:0.65rem;"></i>{{ $p->stock_actual }}
                            </span>
                        @else
                            <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#ECFDF5;color:#059669;font-size:0.75rem;font-weight:600;padding:0.25rem 0.6rem;border-radius:6px;border:1px solid #A7F3D0;">
                                {{ $p->stock_actual }}
                            </span>
                        @endif
                    </td>

                    {{-- Ubicación (CU14 / CU15) --}}
                    <td style="padding:0.7rem 1rem;">
                        @if($p->pasillo || $p->estante || $p->nivel_gaveta)
                            <div class="d-flex align-items-center gap-1">
                                <span style="font-size:0.78rem;color:#1E293B;background:#F1F5F9;padding:0.25rem 0.55rem;border-radius:6px;border:1px solid #E2E8F0;font-weight:500;">
                                    <i class="bi bi-geo-alt-fill text-primary" style="font-size:0.7rem;margin-right:0.2rem;"></i>P: {{ $p->pasillo ?? '—' }} | E: {{ $p->estante ?? '—' }} | G: {{ $p->nivel_gaveta ?? '—' }}
                                </span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-primary" title="Editar coordenadas"
                                        onclick="abrirModalUbicacion({{ $p->id_producto }}, '{{ addslashes($p->nombre_pieza) }}', '{{ $p->pasillo }}', '{{ $p->estante }}', '{{ $p->nivel_gaveta }}')">
                                    <i class="bi bi-pencil-square" style="font-size:0.85rem;"></i>
                                </button>
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-1">
                                <span style="font-size:0.72rem;color:#D97706;background:#FFFBEB;padding:0.2rem 0.5rem;border-radius:6px;border:1px solid #FDE68A;font-weight:500;">
                                    <i class="bi bi-geo-alt me-1"></i>Sin zonificar
                                </span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-primary" title="Asignar coordenadas"
                                        onclick="abrirModalUbicacion({{ $p->id_producto }}, '{{ addslashes($p->nombre_pieza) }}', '', '', '')">
                                    <i class="bi bi-plus-circle" style="font-size:0.85rem;"></i>
                                </button>
                            </div>
                        @endif
                    </td>

                    {{-- Estado --}}
                    <td style="padding:0.7rem 1rem;">
                        @if($p->estado)
                            <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#ECFDF5;color:#059669;font-size:0.72rem;font-weight:500;padding:0.25rem 0.65rem;border-radius:20px;border:1px solid #A7F3D0;">
                                <i class="bi bi-circle-fill" style="font-size:0.3rem;"></i>Activo
                            </span>
                        @else
                            <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#FEF2F2;color:#DC2626;font-size:0.72rem;font-weight:500;padding:0.25rem 0.65rem;border-radius:20px;border:1px solid #FECACA;">
                                <i class="bi bi-circle-fill" style="font-size:0.3rem;"></i>Inactivo
                            </span>
                        @endif
                    </td>

                    {{-- Acciones --}}
                    <td style="padding:0.7rem 1rem;text-align:center;">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="{{ route('productos.edit', $p->id_producto) }}"
                               style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;color:#2563EB;text-decoration:none;transition:all 0.15s;"
                               title="Editar"
                               onmouseenter="this.style.background='#DBEAFE'" onmouseleave="this.style.background='#EFF6FF'">
                                <i class="bi bi-pencil" style="font-size:0.8rem;"></i>
                            </a>
                            <form action="{{ route('productos.toggle', $p->id_producto) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:{{ $p->estado ? '#FFFBEB' : '#ECFDF5' }};border:1px solid {{ $p->estado ? '#FDE68A' : '#A7F3D0' }};border-radius:8px;color:{{ $p->estado ? '#D97706' : '#059669' }};cursor:pointer;transition:all 0.15s;"
                                        title="{{ $p->estado ? 'Desactivar' : 'Activar' }}"
                                        onmouseenter="this.style.opacity='0.8'" onmouseleave="this.style.opacity='1'">
                                    <i class="bi bi-{{ $p->estado ? 'toggle-on' : 'toggle-off' }}" style="font-size:0.9rem;"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="padding:3rem;text-align:center;">
                        <div style="color:#94A3B8;">
                            <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;"></i>
                            <p style="font-size:0.9rem;font-weight:500;color:#64748B;margin-bottom:0.25rem;">No se encontraron productos</p>
                            <p style="font-size:0.8rem;color:#94A3B8;margin:0;">Intenta cambiar los filtros o agrega un nuevo repuesto.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if($productos->hasPages())
    <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
        {{ $productos->links() }}
    </div>
    @endif
</div>

{{-- Contador de registros --}}
<div style="margin-top:0.75rem;font-size:0.78rem;color:#94A3B8;text-align:right;">
    Mostrando {{ $productos->firstItem() ?? 0 }}–{{ $productos->lastItem() ?? 0 }} de {{ $productos->total() }} productos
</div>

{{-- Modal Asignar Ubicación Rápida (CU15) --}}
<div class="modal fade" id="modalUbicacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered w-11/12 max-w-lg mx-auto" style="max-width: 520px;">
        <div class="modal-content modal-content-dark p-2">
            <form id="formUbicacion" method="POST" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header py-3 px-3">
                    <h6 class="modal-title fs-6"><i class="bi bi-geo-alt me-2 text-primary"></i>Asignar Coordenadas de Almacén (CU15)</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="min-height:36px;min-width:36px;"></button>
                </div>
                <div class="modal-body p-3">
                    <p style="font-size:0.85rem;color:#64748B;margin-bottom:1rem;">
                        Repuesto: <strong id="modalProdNombre" class="text-dark"></strong>
                    </p>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label-dark">Pasillo</label>
                            <input type="text" name="pasillo" id="modalPasillo" class="form-control form-control-dark" placeholder="Ej: P-01" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Estante</label>
                            <input type="text" name="estante" id="modalEstante" class="form-control form-control-dark" placeholder="Ej: E-03" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Nivel / Gaveta</label>
                            <input type="text" name="nivel_gaveta" id="modalGaveta" class="form-control form-control-dark" placeholder="Ej: G-02" maxlength="20">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="min-height:44px;padding:0.5rem 1.25rem;">Cancelar</button>
                    <button type="submit" class="btn btn-tuerca" style="min-height:44px;padding:0.5rem 1.25rem;">Guardar Coordenada</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function abrirModalUbicacion(id, nombre, pasillo, estante, gaveta) {
    document.getElementById('modalProdNombre').innerText = nombre;
    document.getElementById('modalPasillo').value = pasillo;
    document.getElementById('modalEstante').value = estante;
    document.getElementById('modalGaveta').value = gaveta;
    document.getElementById('formUbicacion').action = `/productos/${id}/ubicacion`;
    new bootstrap.Modal(document.getElementById('modalUbicacion')).show();
}
</script>
@endpush
@endsection

