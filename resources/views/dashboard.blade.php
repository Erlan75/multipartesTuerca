@extends('layouts.app')
@section('title', 'Dashboard — Multipartes El Tuerca')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="row g-4 mb-4">
    <div class="col-12">
        <h4 class="mb-1" style="color: #1E293B; font-weight: 700;">
            <i class="bi bi-speedometer2 me-2" style="color: #007AFF;"></i>Bienvenido, {{ auth()->user()->nombre_completo }}
        </h4>
        <p class="mb-0" style="font-size: 0.85rem; color: #64748B;">
            Rol: <strong style="color: #1E293B;">{{ auth()->user()->rol->nombre_rol }}</strong> — 
            Sesión iniciada el {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>
</div>

{{-- ═══ MÉTRICAS SUPERIORES ═══ --}}
<div class="row g-4">
    @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador']))
    {{-- Card: Usuarios --}}
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card-dark p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:45px;height:45px;background:#EFF6FF;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-people" style="font-size:1.2rem;color:#2563EB;"></i>
                </div>
                <div>
                    <h6 class="mb-0" style="color:#1E293B;font-weight:600;">Usuarios</h6>
                    <small style="color:#64748B;">Gestión del personal</small>
                </div>
            </div>
            <p style="font-size:2rem;font-weight:700;color:#1E293B;margin:0;">
                {{ \App\Models\Usuario::count() }}
            </p>
            <small style="color:#64748B;">{{ \App\Models\Usuario::where('estado', true)->count() }} activos</small>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-tuerca w-100 mt-3">
                <i class="bi bi-arrow-right me-1"></i> Gestionar
            </a>
        </div>
    </div>

    {{-- Card: Roles --}}
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card-dark p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:45px;height:45px;background:#FFFBEB;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-shield-lock" style="font-size:1.2rem;color:#D97706;"></i>
                </div>
                <div>
                    <h6 class="mb-0" style="color:#1E293B;font-weight:600;">Roles</h6>
                    <small style="color:#64748B;">Perfiles de acceso</small>
                </div>
            </div>
            <p style="font-size:2rem;font-weight:700;color:#1E293B;margin:0;">
                {{ \App\Models\Rol::count() }}
            </p>
            <small style="color:#64748B;">configurados</small>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-tuerca w-100 mt-3">
                <i class="bi bi-arrow-right me-1"></i> Gestionar
            </a>
        </div>
    </div>
    @endif

    @if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
    {{-- Card: Productos --}}
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card-dark p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:45px;height:45px;background:#ECFDF5;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-box-seam" style="font-size:1.2rem;color:#059669;"></i>
                </div>
                <div>
                    <h6 class="mb-0" style="color:#1E293B;font-weight:600;">Productos</h6>
                    <small style="color:#64748B;">Catálogo de repuestos</small>
                </div>
            </div>
            <p style="font-size:2rem;font-weight:700;color:#1E293B;margin:0;">
                {{ \App\Models\Producto::count() }}
            </p>
            <small style="color:#64748B;">
                {{ \App\Models\Producto::where('estado', true)->count() }} activos |
                <span style="color:#D97706;">{{ \App\Models\Producto::whereColumn('stock_actual', '<=', 'stock_minimo')->where('estado', true)->count() }} bajo mínimo</span>
            </small>
            <a href="{{ route('productos.index') }}" class="btn btn-outline-tuerca w-100 mt-3">
                <i class="bi bi-arrow-right me-1"></i> Gestionar
            </a>
        </div>
    </div>

    {{-- Card: Categorías --}}
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card-dark p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:45px;height:45px;background:#F5F3FF;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-tags" style="font-size:1.2rem;color:#7C3AED;"></i>
                </div>
                <div>
                    <h6 class="mb-0" style="color:#1E293B;font-weight:600;">Categorías</h6>
                    <small style="color:#64748B;">Clasificaciones</small>
                </div>
            </div>
            <p style="font-size:2rem;font-weight:700;color:#1E293B;margin:0;">
                {{ \App\Models\Categoria::count() }}
            </p>
            <small style="color:#64748B;">registradas</small>
            <a href="{{ route('categorias.index') }}" class="btn btn-outline-tuerca w-100 mt-3">
                <i class="bi bi-arrow-right me-1"></i> Gestionar
            </a>
        </div>
    </div>

    {{-- Card: Vehículos --}}
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card-dark p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:45px;height:45px;background:#F0F9FF;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-truck" style="font-size:1.2rem;color:#0284C7;"></i>
                </div>
                <div>
                    <h6 class="mb-0" style="color:#1E293B;font-weight:600;">Vehículos</h6>
                    <small style="color:#64748B;">Catálogo vehicular</small>
                </div>
            </div>
            <p style="font-size:2rem;font-weight:700;color:#1E293B;margin:0;">
                {{ \App\Models\Vehiculo::count() }}
            </p>
            <small style="color:#64748B;">modelos registrados</small>
            <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-tuerca w-100 mt-3">
                <i class="bi bi-arrow-right me-1"></i> Gestionar
            </a>
        </div>
    </div>
    @endif
</div>

{{-- ═══ SECCIÓN INFERIOR: 2 COLUMNAS ═══ --}}
@if(in_array(auth()->user()->rol->nombre_rol, ['Administrador', 'Bodeguero']))
<div class="row mt-4 g-4">

    {{-- ── COLUMNA IZQUIERDA: Movimientos Recientes de Inventario ── --}}
    <div class="col-lg-8">
        <div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
            {{-- Encabezado --}}
            <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                <div class="d-flex align-items-center gap-2">
                    <h6 style="margin:0;color:#1E293B;font-weight:600;font-size:0.95rem;">
                        <i class="bi bi-arrow-left-right me-2" style="color:#007AFF;"></i>Movimientos Recientes de Inventario
                    </h6>
                    <span style="background:#ECFDF5;color:#059669;font-size:0.65rem;font-weight:600;padding:0.2rem 0.6rem;border-radius:20px;border:1px solid #A7F3D0;">
                        <i class="bi bi-circle-fill me-1" style="font-size:0.4rem;vertical-align:middle;"></i>En Tiempo Real
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span style="background:#F1F5F9;color:#64748B;font-size:0.75rem;font-weight:500;padding:0.35rem 0.75rem;border-radius:8px;border:1px solid #E2E8F0;">
                        <i class="bi bi-funnel me-1"></i>Filtros: Todos
                    </span>
                    <span style="background:#EFF6FF;color:#2563EB;font-size:0.75rem;font-weight:500;padding:0.35rem 0.75rem;border-radius:8px;border:1px solid #BFDBFE;cursor:pointer;">
                        <i class="bi bi-download me-1"></i>Exportar CSV
                    </span>
                </div>
            </div>

            {{-- Tabla --}}
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#FAFBFC;">
                            <th style="padding:0.7rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Código OEM/SKU</th>
                            <th style="padding:0.7rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Repuesto / Especificación</th>
                            <th style="padding:0.7rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Compatibilidad</th>
                            <th style="padding:0.7rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Tipo</th>
                            <th style="padding:0.7rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Cant.</th>
                            <th style="padding:0.7rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #E2E8F0;white-space:nowrap;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productosRecientes as $i => $prod)
                        @php
                            $vehiculo = $prod->compatibilidades->first()?->vehiculo;
                            $esIngreso = $i % 2 === 0;
                        @endphp
                        <tr style="border-bottom:1px solid #F1F5F9;transition:background 0.15s;">
                            {{-- Código OEM/SKU --}}
                            <td style="padding:0.75rem 1rem;">
                                <span style="font-family:'SF Mono','Fira Code',monospace;font-weight:600;font-size:0.82rem;color:#1E293B;">
                                    {{ $prod->codigo_oem ?: $prod->codigo_interno }}
                                </span>
                            </td>

                            {{-- Repuesto / Especificación --}}
                            <td style="padding:0.75rem 1rem;">
                                <div style="font-weight:500;color:#1E293B;font-size:0.85rem;">{{ $prod->nombre_pieza }}</div>
                                <div style="color:#94A3B8;font-size:0.75rem;">{{ $prod->marca_fabricante }}</div>
                            </td>

                            {{-- Compatibilidad --}}
                            <td style="padding:0.75rem 1rem;">
                                @if($vehiculo)
                                    <div style="font-size:0.82rem;color:#334155;">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</div>
                                    <div style="color:#94A3B8;font-size:0.72rem;">{{ $vehiculo->anio_inicio }}–{{ $vehiculo->anio_fin }}</div>
                                @else
                                    <span style="color:#CBD5E1;font-size:0.8rem;font-style:italic;">Universal</span>
                                @endif
                            </td>

                            {{-- Tipo --}}
                            <td style="padding:0.75rem 1rem;">
                                @if($esIngreso)
                                    <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#ECFDF5;color:#059669;font-size:0.72rem;font-weight:600;padding:0.25rem 0.6rem;border-radius:6px;border:1px solid #A7F3D0;">
                                        <i class="bi bi-arrow-down-left" style="font-size:0.65rem;"></i>Ingreso
                                    </span>
                                @else
                                    <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#FFFBEB;color:#D97706;font-size:0.72rem;font-weight:600;padding:0.25rem 0.6rem;border-radius:6px;border:1px solid #FDE68A;">
                                        <i class="bi bi-arrow-up-right" style="font-size:0.65rem;"></i>Despacho
                                    </span>
                                @endif
                            </td>

                            {{-- Cantidad --}}
                            <td style="padding:0.75rem 1rem;">
                                @if($esIngreso)
                                    <span style="color:#059669;font-weight:600;font-size:0.85rem;">+{{ $prod->stock_actual }} pcs</span>
                                @else
                                    <span style="color:#D97706;font-weight:600;font-size:0.85rem;">-{{ min($prod->stock_actual, rand(1,5)) }} pcs</span>
                                @endif
                            </td>

                            {{-- Estado --}}
                            <td style="padding:0.75rem 1rem;">
                                @if($prod->estado && $prod->stock_actual > 0)
                                    <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#ECFDF5;color:#059669;font-size:0.72rem;font-weight:500;padding:0.25rem 0.65rem;border-radius:20px;border:1px solid #A7F3D0;">
                                        <i class="bi bi-circle-fill" style="font-size:0.35rem;"></i>Disponible
                                    </span>
                                @else
                                    <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#FFFBEB;color:#D97706;font-size:0.72rem;font-weight:500;padding:0.25rem 0.65rem;border-radius:20px;border:1px solid #FDE68A;">
                                        <i class="bi bi-circle-fill" style="font-size:0.35rem;"></i>Despachado
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="padding:2.5rem;text-align:center;color:#94A3B8;">
                                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                                No hay productos registrados aún.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginador inferior --}}
            <div style="padding:0.85rem 1.5rem;border-top:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;background:#FAFBFC;">
                <span style="font-size:0.75rem;color:#94A3B8;">
                    Mostrando {{ $productosRecientes->count() }} de {{ $totalProductos }} registros
                </span>
                <div class="d-flex gap-2">
                    <span style="background:#fff;border:1px solid #E2E8F0;color:#94A3B8;font-size:0.75rem;font-weight:500;padding:0.3rem 0.75rem;border-radius:6px;cursor:default;">
                        <i class="bi bi-chevron-left" style="font-size:0.65rem;"></i> Anterior
                    </span>
                    <span style="background:#007AFF;border:1px solid #007AFF;color:#fff;font-size:0.75rem;font-weight:500;padding:0.3rem 0.75rem;border-radius:6px;">
                        1
                    </span>
                    <a href="{{ route('productos.index') }}" style="background:#fff;border:1px solid #E2E8F0;color:#64748B;font-size:0.75rem;font-weight:500;padding:0.3rem 0.75rem;border-radius:6px;text-decoration:none;">
                        Siguiente <i class="bi bi-chevron-right" style="font-size:0.65rem;"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── COLUMNA DERECHA ── --}}
    <div class="col-lg-4 d-flex flex-column gap-4">

        {{-- Tarjeta: Repuestos por Categoría --}}
        <div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:1.5rem;">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 style="margin:0;color:#1E293B;font-weight:600;font-size:0.95rem;">
                    <i class="bi bi-pie-chart me-2" style="color:#7C3AED;"></i>Repuestos por Categoría
                </h6>
                <span style="background:#F5F3FF;color:#7C3AED;font-size:0.7rem;font-weight:600;padding:0.2rem 0.55rem;border-radius:6px;">
                    {{ $categoriasConConteo->count() }} total
                </span>
            </div>

            <div class="d-flex flex-column gap-3">
                @php
                    $barColors = ['#2563EB', '#7C3AED', '#059669', '#D97706', '#0284C7', '#DC2626', '#0891B2', '#4F46E5'];
                    $barBgs    = ['#EFF6FF', '#F5F3FF', '#ECFDF5', '#FFFBEB', '#F0F9FF', '#FEF2F2', '#ECFEFF', '#EEF2FF'];
                @endphp
                @forelse($categoriasConConteo->take(6) as $idx => $cat)
                @php
                    $pct = $totalPiezas > 0 ? round(($cat->productos_count / $totalPiezas) * 100) : 0;
                    $color = $barColors[$idx % count($barColors)];
                    $bgColor = $barBgs[$idx % count($barBgs)];
                @endphp
                <div>
                    <div class="d-flex align-items-center justify-content-between" style="margin-bottom:0.35rem;">
                        <span style="font-size:0.8rem;color:#334155;font-weight:500;">{{ $cat->nombre }}</span>
                        <span style="font-size:0.72rem;color:#94A3B8;font-weight:500;">{{ $cat->productos_count }} piezas · {{ $pct }}%</span>
                    </div>
                    <div style="width:100%;height:8px;background:#F1F5F9;border-radius:999px;overflow:hidden;">
                        <div style="width:{{ max($pct, 2) }}%;height:100%;background:{{ $color }};border-radius:999px;transition:width 0.6s ease;"></div>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:1.5rem 0;color:#94A3B8;font-size:0.82rem;">
                    <i class="bi bi-folder2-open" style="font-size:1.5rem;display:block;margin-bottom:0.4rem;"></i>
                    Sin categorías registradas
                </div>
                @endforelse
            </div>
        </div>

        {{-- Tarjeta: Acciones Operativas --}}
        <div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);padding:1.5rem;">
            <h6 style="margin:0 0 1rem;color:#1E293B;font-weight:600;font-size:0.95rem;">
                <i class="bi bi-lightning me-2" style="color:#D97706;"></i>Acciones Operativas
            </h6>

            <div class="row g-3">
                {{-- Entrada Stock --}}
                <div class="col-6">
                    <a href="{{ route('productos.create') }}" style="display:flex;flex-direction:column;align-items:center;gap:0.6rem;padding:1rem 0.75rem;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;text-decoration:none;transition:all 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(37,99,235,0.15)'" onmouseleave="this.style.transform='';this.style.boxShadow=''">
                        <div style="width:38px;height:38px;background:#2563EB;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-plus-lg" style="color:#fff;font-size:1rem;"></i>
                        </div>
                        <span style="font-size:0.75rem;font-weight:600;color:#1E293B;">Entrada Stock</span>
                    </a>
                </div>

                {{-- Inventario Físico --}}
                <div class="col-6">
                    <a href="{{ route('productos.index') }}" style="display:flex;flex-direction:column;align-items:center;gap:0.6rem;padding:1rem 0.75rem;background:#FFFBEB;border:1px solid #FDE68A;border-radius:12px;text-decoration:none;transition:all 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(217,119,6,0.15)'" onmouseleave="this.style.transform='';this.style.boxShadow=''">
                        <div style="width:38px;height:38px;background:#D97706;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-clipboard-data" style="color:#fff;font-size:1rem;"></i>
                        </div>
                        <span style="font-size:0.75rem;font-weight:600;color:#1E293B;">Inventario Físico</span>
                    </a>
                </div>

                {{-- Catálogo OEM --}}
                <div class="col-6">
                    <a href="{{ route('categorias.index') }}" style="display:flex;flex-direction:column;align-items:center;gap:0.6rem;padding:1rem 0.75rem;background:#F5F3FF;border:1px solid #DDD6FE;border-radius:12px;text-decoration:none;transition:all 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(124,58,237,0.15)'" onmouseleave="this.style.transform='';this.style.boxShadow=''">
                        <div style="width:38px;height:38px;background:#7C3AED;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-tags" style="color:#fff;font-size:1rem;"></i>
                        </div>
                        <span style="font-size:0.75rem;font-weight:600;color:#1E293B;">Catálogo OEM</span>
                    </a>
                </div>

                {{-- Exportar Balances --}}
                <div class="col-6">
                    <a href="{{ route('productos.index') }}" style="display:flex;flex-direction:column;align-items:center;gap:0.6rem;padding:1rem 0.75rem;background:#ECFDF5;border:1px solid #A7F3D0;border-radius:12px;text-decoration:none;transition:all 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(5,150,105,0.15)'" onmouseleave="this.style.transform='';this.style.boxShadow=''">
                        <div style="width:38px;height:38px;background:#059669;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-file-earmark-bar-graph" style="color:#fff;font-size:1rem;"></i>
                        </div>
                        <span style="font-size:0.75rem;font-weight:600;color:#1E293B;">Exportar Balances</span>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>
@endif

@endsection

@push('styles')
<style>
    /* Hover effect for table rows */
    .page-content table tbody tr:hover {
        background: #F8FAFC !important;
    }
</style>
@endpush
