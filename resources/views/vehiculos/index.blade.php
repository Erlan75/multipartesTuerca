@extends('layouts.app')
@section('title', 'Catálogo Vehicular — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Catálogo Vehicular</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-truck me-2" style="color:#007AFF;"></i>Catálogo Vehicular y Compatibilidades
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Gestión de marcas, modelos y motorizaciones para la consulta y vinculación técnica de repuestos automotrices.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-tuerca" data-bs-toggle="modal" data-bs-target="#modalVehiculo" onclick="limpiarModalV()" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Vehículo
        </button>
        <a href="{{ route('productos.index') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-box-seam me-1"></i>Catálogo de Repuestos
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
@if($errors->any())
    <div class="alert alert-danger mb-4" role="alert" style="border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
        <ul class="mb-0" style="font-size:0.85rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ═══ FORMULARIO DE FILTRADO ESTRUCTURADO (CU12) ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('vehiculos.index') }}">
        <div class="row g-2 align-items-end">
            {{-- Selector / Autocompletado de MARCA --}}
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.2rem;display:block;">
                    Marca Vehicular
                </label>
                <select name="marca" class="form-select form-select-dark">
                    <option value="">Todas las marcas</option>
                    @foreach($marcas as $m)
                        <option value="{{ $m }}" {{ request('marca') == $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Selector / Input de MODELO --}}
            <div class="col-md-4">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.2rem;display:block;">
                    Modelo
                </label>
                <div style="position:relative;">
                    <i class="bi bi-car-front" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="modelo" class="form-control form-control-dark"
                           placeholder="Ej: Corolla, New Vitara, Tiida..."
                           value="{{ request('modelo') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>

            {{-- Input / Selector de AÑO --}}
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.2rem;display:block;">
                    Año Vehicular
                </label>
                <input type="number" name="anio" class="form-control form-control-dark"
                       placeholder="Ej: 2018"
                       min="1950" max="2040"
                       value="{{ request('anio') }}">
            </div>

            {{-- Botones de Filtrar y Limpiar --}}
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-funnel me-1"></i>Filtrar Vehículos
                </button>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.85rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE VEHÍCULOS ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">#</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Marca</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Modelo</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;text-align:center;">Años Vigencia</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Motor / Cilindrada</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;text-align:center;">Combustible</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;text-align:center;">Repuestos Compatibles</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehiculos as $v)
                    @php
                        $count = $v->compatibilidades_count;
                        $vehiculoData = [
                            'marca' => $v->marca,
                            'modelo' => $v->modelo,
                            'anio_inicio' => $v->anio_inicio,
                            'anio_fin' => $v->anio_fin,
                            'motor_cilindrada' => $v->motor_cilindrada ?? 'N/A',
                            'combustible' => $v->combustible,
                            'repuestos' => $v->productos->map(function($p) {
                                return [
                                    'codigo_interno' => $p->codigo_interno,
                                    'codigo_oem' => $p->codigo_oem ?? '—',
                                    'nombre_pieza' => $p->nombre_pieza,
                                    'marca_fabricante' => $p->marca_fabricante ?? '—',
                                    'precio_venta_sugerido' => number_format((float)$p->precio_venta_sugerido, 2),
                                    'stock_actual' => (int)$p->stock_actual,
                                    'pasillo' => $p->pasillo ?? '—',
                                    'estante' => $p->estante ?? '—',
                                    'nivel_gaveta' => $p->nivel_gaveta ?? '—',
                                ];
                            })->values()
                        ];
                    @endphp
                    <tr style="border-bottom:1px solid #F1F5F9;transition:background 0.15s ease;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                        <td style="padding:0.75rem 1rem;font-size:0.8rem;color:#94A3B8;">{{ $v->id_vehiculo }}</td>
                        <td style="padding:0.75rem 1rem;font-weight:700;color:#007AFF;">{{ $v->marca }}</td>
                        <td style="padding:0.75rem 1rem;font-weight:600;color:#1E293B;">{{ $v->modelo }}</td>
                        <td style="padding:0.75rem 1rem;text-align:center;white-space:nowrap;">
                            <span class="badge" style="background:#F1F5F9;color:#334155;border:1px solid #E2E8F0;font-size:0.75rem;">
                                {{ $v->anio_inicio }} – {{ $v->anio_fin }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#475569;">
                            {{ $v->motor_cilindrada ?? '—' }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <span class="badge" style="background:rgba(0,122,255,0.08);color:#007AFF;font-size:0.72rem;">
                                {{ $v->combustible }}
                            </span>
                        </td>
                        {{-- ═══ COLUMNA INTERACTIVA DE COMPATIBILIDADES (CU12) ═══ --}}
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <button type="button"
                                    class="btn btn-sm {{ $count > 0 ? 'btn-primary-subtle border border-primary-subtle text-primary' : 'btn-light border text-muted' }} d-inline-flex align-items-center gap-1"
                                    style="border-radius:8px;padding:0.28rem 0.75rem;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                    onclick="abrirModalRepuestos(this)"
                                    data-vehiculo='@json($vehiculoData)'
                                    title="Consultar repuestos compatibles vinculados">
                                <i class="bi {{ $count > 0 ? 'bi-eye-fill' : 'bi-eye' }}"></i>
                                <span>{{ $count }} {{ Str::plural('repuesto', $count) }}</span>
                            </button>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-tuerca" title="Editar vehículo"
                                        style="border-radius:8px;padding:0.25rem 0.55rem;font-size:0.78rem;"
                                        onclick="editarVehiculo({{ $v->id_vehiculo }}, '{{ addslashes($v->marca) }}', '{{ addslashes($v->modelo) }}', {{ $v->anio_inicio }}, {{ $v->anio_fin }}, '{{ addslashes($v->motor_cilindrada ?? '') }}', '{{ $v->combustible }}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('vehiculos.destroy', $v->id_vehiculo) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar {{ $v->marca }} {{ $v->modelo }}? Se eliminarán todas sus compatibilidades técnicas vinculadas.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar vehículo"
                                            style="border-radius:8px;padding:0.25rem 0.55rem;font-size:0.78rem;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:3.5rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-truck d-block mb-2" style="font-size:2.2rem;color:#CBD5E1;"></i>
                            <div class="fw-semibold text-dark">No se encontraron vehículos</div>
                            <div style="font-size:0.82rem;">Intente ajustando los filtros de marca, modelo o año vehicular.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Paginación --}}
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <div style="font-size:0.8rem;color:#64748B;">
        Mostrando {{ $vehiculos->firstItem() ?? 0 }} a {{ $vehiculos->lastItem() ?? 0 }} de {{ $vehiculos->total() }} vehículos registrados
    </div>
    <div>{{ $vehiculos->links() }}</div>
</div>

{{-- ═══ MODAL INTERACTIVO DE REPUESTOS COMPATIBLES (CU12) ═══ --}}
<div class="modal fade" id="modalRepuestosCompatibles" tabindex="-1" aria-labelledby="modalRepuestosTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered w-11/12 mx-auto" style="max-width: 960px;">
        <div class="modal-content" style="border-radius:18px;border:1px solid #E2E8F0;box-shadow:0 20px 45px rgba(15,23,42,0.15);">
            <div class="modal-header py-3 px-3" style="border-bottom:1px solid #F1F5F9;background:#FAFBFC;">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(0,122,255,0.10);color:#007AFF;">
                        <i class="bi bi-box-seam fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0" id="modalRepuestosTitle" style="font-weight:700;color:#1E293B;font-size:0.95rem;">
                            Repuestos Compatibles
                        </h6>
                        <small class="text-muted" id="modalRepuestosSubtitle" style="font-size:0.75rem;">Consulta técnica de piezas vinculadas al catálogo vehicular</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                {{-- Contenedor de Tabla con Repuestos --}}
                <div id="modalRepuestosTablaContainer" class="table-responsive-container" style="border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;display:none;">
                    <table class="table table-hover mb-0" style="font-size:0.83rem;">
                        <thead style="background:#F8FAFC;">
                            <tr>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;">Código Int.</th>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;">Código OEM</th>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;">Denominación / Pieza</th>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;">Marca Repuesto</th>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;text-align:right;">Precio Venta</th>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;text-align:center;">Stock Actual</th>
                                <th style="padding:0.7rem 0.85rem;color:#64748B;font-size:0.7rem;text-transform:uppercase;font-weight:600;border-bottom:2px solid #E2E8F0;text-align:center;">Ubicación Bodega</th>
                            </tr>
                        </thead>
                        <tbody id="modalRepuestosTbody">
                            {{-- Rellenado dinámico por JS --}}
                        </tbody>
                    </table>
                </div>

                {{-- Empty State para 0 compatibilidades --}}
                <div id="modalRepuestosEmpty" class="text-center py-5" style="display:none;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:#F1F5F9;color:#94A3B8;">
                        <i class="bi bi-inbox fs-2"></i>
                    </div>
                    <h6 class="text-dark fw-bold mb-1">Sin compatibilidades vinculadas</h6>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">
                        No se registran repuestos compatibles vinculados a este modelo vehicular.
                    </p>
                </div>
            </div>
            <div class="modal-footer py-2 px-3" style="border-top:1px solid #F1F5F9;background:#FAFBFC;">
                <span class="text-muted me-auto" id="modalRepuestosFooterInfo" style="font-size:0.75rem;"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px;padding:0.35rem 0.9rem;">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MODAL CREAR / EDITAR VEHÍCULO ═══ --}}
<div class="modal fade" id="modalVehiculo" tabindex="-1" aria-labelledby="modalVTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered w-11/12 max-w-lg mx-auto" style="max-width: 620px;">
        <div class="modal-content" style="border-radius:18px;border:1px solid #E2E8F0;box-shadow:0 20px 45px rgba(15,23,42,0.15);">
            <form id="formVehiculo" method="POST" action="{{ route('vehiculos.store') }}">
                @csrf
                <div id="methodFieldV"></div>
                <div class="modal-header py-3 px-3" style="border-bottom:1px solid #F1F5F9;">
                    <h6 class="modal-title fs-6" id="modalVTitle" style="font-weight:700;color:#1E293B;">
                        Nuevo Vehículo
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">Marca *</label>
                            <input type="text" name="marca" id="v_marca" class="form-control form-control-dark" placeholder="Ej: Toyota" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">Modelo *</label>
                            <input type="text" name="modelo" id="v_modelo" class="form-control form-control-dark" placeholder="Ej: Corolla" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">Año Inicio *</label>
                            <input type="number" name="anio_inicio" id="v_anio_inicio" class="form-control form-control-dark" min="1950" max="2040" placeholder="2010" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">Año Fin *</label>
                            <input type="number" name="anio_fin" id="v_anio_fin" class="form-control form-control-dark" min="1950" max="2040" placeholder="2019" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">Motor / Cilindrada</label>
                            <input type="text" name="motor_cilindrada" id="v_motor" class="form-control form-control-dark" placeholder="Ej: 1.8L 2ZR-FE">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">Combustible *</label>
                            <select name="combustible" id="v_combustible" class="form-select form-select-dark" required>
                                <option value="Gasolina">Gasolina</option>
                                <option value="Diésel">Diésel</option>
                                <option value="GNV">GNV</option>
                                <option value="Híbrido">Híbrido</option>
                                <option value="Eléctrico">Eléctrico</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3" style="border-top:1px solid #F1F5F9;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-tuerca" id="btnSubmitV" style="border-radius:8px;padding:0.4rem 1rem;">Crear Vehículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
/**
 * Abre el modal interactivo de repuestos compatibles con datos del vehículo
 */
function abrirModalRepuestos(btn) {
    try {
        const raw = btn.getAttribute('data-vehiculo');
        const data = JSON.parse(raw);

        const modalTitle = document.getElementById('modalRepuestosTitle');
        const modalSubtitle = document.getElementById('modalRepuestosSubtitle');
        const tableContainer = document.getElementById('modalRepuestosTablaContainer');
        const emptyState = document.getElementById('modalRepuestosEmpty');
        const tbody = document.getElementById('modalRepuestosTbody');
        const footerInfo = document.getElementById('modalRepuestosFooterInfo');

        // Configurar título exacto exigido:
        // "Repuestos Compatibles para: [Marca] [Modelo] ([Año Inicio] - [Año Fin]) - Motor: [Motor]"
        modalTitle.textContent = `Repuestos Compatibles para: ${data.marca} ${data.modelo} (${data.anio_inicio} - ${data.anio_fin}) - Motor: ${data.motor_cilindrada}`;
        modalSubtitle.textContent = `Vehículo ${data.marca} ${data.modelo} · Combustible: ${data.combustible}`;

        tbody.innerHTML = '';

        if (data.repuestos && data.repuestos.length > 0) {
            data.repuestos.forEach(item => {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #F1F5F9';

                // Badge de Stock
                let stockBadge = '';
                if (item.stock_actual > 0) {
                    stockBadge = `<span class="badge" style="background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;font-size:0.75rem;"><i class="bi bi-check-circle me-1"></i>${item.stock_actual} disp.</span>`;
                } else {
                    stockBadge = `<span class="badge" style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;font-size:0.75rem;"><i class="bi bi-x-circle me-1"></i>Agotado (0)</span>`;
                }

                // Ubicación en bodega (Pasillo, Estante, Gaveta)
                let ubicacionHtml = '<span class="text-muted">—</span>';
                if ((item.pasillo && item.pasillo !== '—') || (item.estante && item.estante !== '—') || (item.nivel_gaveta && item.nivel_gaveta !== '—')) {
                    ubicacionHtml = `<span class="badge" style="background:#F1F5F9;color:#0F172A;border:1px solid #CBD5E1;font-family:monospace;font-size:0.75rem;">${item.pasillo || '-'} / ${item.estante || '-'} / ${item.nivel_gaveta || '-'}</span>`;
                }

                tr.innerHTML = `
                    <td style="padding:0.65rem 0.85rem;"><code style="color:#007AFF;font-weight:600;">${escapeHtml(item.codigo_interno)}</code></td>
                    <td style="padding:0.65rem 0.85rem;color:#475569;font-family:monospace;font-size:0.8rem;">${escapeHtml(item.codigo_oem)}</td>
                    <td style="padding:0.65rem 0.85rem;font-weight:600;color:#1E293B;">${escapeHtml(item.nombre_pieza)}</td>
                    <td style="padding:0.65rem 0.85rem;color:#475569;">${escapeHtml(item.marca_fabricante)}</td>
                    <td style="padding:0.65rem 0.85rem;text-align:right;font-weight:700;color:#0F172A;">Bs. ${item.precio_venta_sugerido}</td>
                    <td style="padding:0.65rem 0.85rem;text-align:center;">${stockBadge}</td>
                    <td style="padding:0.65rem 0.85rem;text-align:center;">${ubicacionHtml}</td>
                `;
                tbody.appendChild(tr);
            });

            tableContainer.style.display = 'block';
            emptyState.style.display = 'none';
            footerInfo.textContent = `Total repuestos vinculados: ${data.repuestos.length}`;
        } else {
            tableContainer.style.display = 'none';
            emptyState.style.display = 'block';
            footerInfo.textContent = '0 repuestos vinculados';
        }

        const modalEl = document.getElementById('modalRepuestosCompatibles');
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    } catch (e) {
        console.error('Error al abrir modal de repuestos compatibles:', e);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function limpiarModalV() {
    document.getElementById('formVehiculo').action = '{{ route("vehiculos.store") }}';
    document.getElementById('methodFieldV').innerHTML = '';
    document.getElementById('modalVTitle').textContent = 'Nuevo Vehículo';
    document.getElementById('btnSubmitV').textContent = 'Crear Vehículo';
    document.getElementById('v_marca').value = '';
    document.getElementById('v_modelo').value = '';
    document.getElementById('v_anio_inicio').value = '';
    document.getElementById('v_anio_fin').value = '';
    document.getElementById('v_motor').value = '';
    document.getElementById('v_combustible').value = 'Gasolina';
}

function editarVehiculo(id, marca, modelo, anioI, anioF, motor, combustible) {
    document.getElementById('formVehiculo').action = '/vehiculos/' + id;
    document.getElementById('methodFieldV').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('modalVTitle').textContent = 'Editar Vehículo';
    document.getElementById('btnSubmitV').textContent = 'Guardar Cambios';
    document.getElementById('v_marca').value = marca;
    document.getElementById('v_modelo').value = modelo;
    document.getElementById('v_anio_inicio').value = anioI;
    document.getElementById('v_anio_fin').value = anioF;
    document.getElementById('v_motor').value = motor;
    document.getElementById('v_combustible').value = combustible;

    const modalEl = document.getElementById('modalVehiculo');
    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalInstance.show();
}
</script>
@endpush
@endsection
