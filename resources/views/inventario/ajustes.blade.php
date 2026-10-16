@extends('layouts.app')
@section('title', 'Ajustes de Inventario — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('productos.index') }}">Inventario</a></li>
    <li class="breadcrumb-item active">Ajustes y Salidas</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-arrow-left-right me-2" style="color:#007AFF;"></i>Ajustes de Inventario y Salidas Internas
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Auditoría de mermas, extravíos, rectificaciones por conteo físico y salidas para uso interno procesadas vía Stored Procedure Smart-DB.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-tuerca" data-bs-toggle="modal" data-bs-target="#modalNuevoAjuste" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-plus-lg me-1"></i>Registrar Ajuste
        </button>
        <a href="{{ route('inventario.alertas') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.2rem;font-size:0.85rem;">
            <i class="bi bi-shield-exclamation me-1"></i>Ver Alertas
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
@if($errors->any())
    <div class="alert alert-danger mb-4" style="border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i>Por favor corrija los siguientes errores:</div>
        <ul class="mb-0 ps-3" style="font-size:0.85rem;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ═══ BARRA DE FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('inventario.ajustes') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Búsqueda por Repuesto</label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="Código interno, OEM o pieza..."
                           value="{{ request('buscar') }}"
                           style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Tipo de Ajuste</label>
                <select name="tipo_ajuste" class="form-select form-select-dark">
                    <option value="">Todos los tipos</option>
                    <option value="Merma" {{ request('tipo_ajuste') == 'Merma' ? 'selected' : '' }}>Merma</option>
                    <option value="Pérdida/Extravío" {{ request('tipo_ajuste') == 'Pérdida/Extravío' ? 'selected' : '' }}>Pérdida / Extravío</option>
                    <option value="Conteo Físico" {{ request('tipo_ajuste') == 'Conteo Físico' ? 'selected' : '' }}>Conteo Físico (Auditoría)</option>
                    <option value="Devolución Dañada" {{ request('tipo_ajuste') == 'Devolución Dañada' ? 'selected' : '' }}>Devolución Dañada</option>
                    <option value="Uso Interno" {{ request('tipo_ajuste') == 'Uso Interno' ? 'selected' : '' }}>Uso Interno (Taller/Local)</option>
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
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-tuerca w-100" type="submit" title="Filtrar" style="padding:0.5rem 0.5rem;">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('inventario.ajustes') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.6rem;" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE AJUSTES ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Folio / Fecha</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Usuario</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Repuesto / Código</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Tipo de Ajuste</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Variación Stock</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Justificación / Motivo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ajustes as $aj)
                    @php
                        $badgeStyle = match($aj->tipo_ajuste) {
                            'Merma'            => 'background:#FEF3C7;color:#D97706;border:1px solid #FDE68A;',
                            'Pérdida/Extravío' => 'background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;',
                            'Conteo Físico'    => 'background:#EFF6FF;color:#007AFF;border:1px solid #BFDBFE;',
                            'Devolución Dañada'=> 'background:#F1F5F9;color:#475569;border:1px solid #CBD5E1;',
                            'Uso Interno'      => 'background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;',
                            default            => 'background:#F1F5F9;color:#334155;border:1px solid #CBD5E1;'
                        };

                        $delta = (int)$aj->cantidad_ajustada;
                    @endphp
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <span style="font-weight:700;color:#1E293B;font-size:0.82rem;">#AJ-{{ str_pad($aj->id_ajuste, 5, '0', STR_PAD_LEFT) }}</span>
                            <div style="font-size:0.75rem;color:#64748B;">
                                {{ \Carbon\Carbon::parse($aj->fecha_ajuste)->format('d/m/Y H:i') }}
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <div style="font-size:0.83rem;font-weight:600;color:#1E293B;">
                                {{ $aj->usuario->nombre ?? 'Sistema' }}
                            </div>
                            <span style="font-size:0.7rem;color:#64748B;">{{ $aj->usuario->rol->nombre_rol ?? 'Usuario' }}</span>
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.85rem;">
                                {{ $aj->producto->nombre_pieza ?? 'Producto eliminado' }}
                            </div>
                            <div style="font-size:0.75rem;color:#64748B;font-family:monospace;">
                                {{ $aj->producto->codigo_interno ?? '' }}
                                @if($aj->producto && $aj->producto->codigo_oem)
                                    | OEM: {{ $aj->producto->codigo_oem }}
                                @endif
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="display:inline-block;padding:0.25rem 0.65rem;border-radius:20px;font-size:0.75rem;font-weight:600;{{ $badgeStyle }}">
                                {{ $aj->tipo_ajuste }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;white-space:nowrap;">
                            @if($delta > 0)
                                <span style="display:inline-flex;align-items:center;gap:0.2rem;padding:0.25rem 0.6rem;background:#ECFDF5;color:#059669;font-weight:700;border-radius:8px;font-size:0.85rem;font-family:monospace;">
                                    <i class="bi bi-arrow-up"></i>+{{ $delta }}
                                </span>
                            @elseif($delta < 0)
                                <span style="display:inline-flex;align-items:center;gap:0.2rem;padding:0.25rem 0.6rem;background:#FEF2F2;color:#DC2626;font-weight:700;border-radius:8px;font-size:0.85rem;font-family:monospace;">
                                    <i class="bi bi-arrow-down"></i>{{ $delta }}
                                </span>
                            @else
                                <span style="padding:0.25rem 0.6rem;background:#F1F5F9;color:#64748B;font-weight:600;border-radius:8px;font-size:0.85rem;font-family:monospace;">
                                    0
                                </span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#475569;">
                            {{ $aj->motivo }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                            No se han registrado movimientos de ajuste con los filtros indicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($ajustes->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $ajustes->links() }}
        </div>
    @endif
</div>

{{-- ═══ MODAL REGISTRAR AJUSTE ═══ --}}
<div class="modal fade" id="modalNuevoAjuste" tabindex="-1" aria-labelledby="modalNuevoAjusteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered w-11/12 max-w-lg mx-auto" style="max-width: 520px;">
        <div class="modal-content" style="border-radius:18px;border:1px solid #E2E8F0;box-shadow:0 20px 45px rgba(15,23,42,0.15);">
            <div class="modal-header py-3 px-3" style="border-bottom:1px solid #F1F5F9;">
                <h5 class="modal-title fs-6" id="modalNuevoAjusteLabel" style="font-weight:700;color:#1E293B;">
                    <i class="bi bi-sliders me-2" style="color:#007AFF;"></i>Registrar Ajuste de Inventario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" style="min-height:36px;min-width:36px;"></button>
            </div>
            <form action="{{ route('inventario.ajustes.store') }}" method="POST" id="formAjuste">
                @csrf
                <div class="modal-body p-3">
                    {{-- Selector de Repuesto --}}
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Repuesto del Catálogo <span class="text-danger">*</span>
                        </label>
                        <select name="id_producto" id="selectProductoAjuste" class="form-select form-select-dark" required>
                            <option value="">-- Seleccionar repuesto --</option>
                            @foreach($productos as $prod)
                                <option value="{{ $prod->id_producto }}"
                                        data-stock="{{ $prod->stock_actual }}"
                                        {{ old('id_producto') == $prod->id_producto ? 'selected' : '' }}>
                                    [{{ $prod->codigo_interno }}] {{ $prod->nombre_pieza }} (Stock: {{ $prod->stock_actual }})
                                </option>
                            @endforeach
                        </select>
                        <div id="infoStockActual" class="mt-1" style="font-size:0.75rem;color:#007AFF;font-weight:600;display:none;">
                            Existencia actual en bodega: <span id="lblStockActualVal">0</span> unidades
                        </div>
                    </div>

                    {{-- Tipo de Ajuste --}}
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Tipo de Operación <span class="text-danger">*</span>
                        </label>
                        <select name="tipo_ajuste" id="selectTipoAjuste" class="form-select form-select-dark" required>
                            <option value="Merma" {{ old('tipo_ajuste') == 'Merma' ? 'selected' : '' }}>Merma (Deterioro / Desgaste)</option>
                            <option value="Pérdida/Extravío" {{ old('tipo_ajuste') == 'Pérdida/Extravío' ? 'selected' : '' }}>Pérdida / Extravío</option>
                            <option value="Conteo Físico" {{ old('tipo_ajuste') == 'Conteo Físico' ? 'selected' : '' }}>Conteo Físico (Auditoría en Estante)</option>
                            <option value="Devolución Dañada" {{ old('tipo_ajuste') == 'Devolución Dañada' ? 'selected' : '' }}>Devolución Dañada (Baja)</option>
                            <option value="Uso Interno" {{ old('tipo_ajuste') == 'Uso Interno' ? 'selected' : '' }}>Uso Interno (Taller / Mantenimiento Local)</option>
                        </select>
                    </div>

                    {{-- Cantidad --}}
                    <div class="mb-3">
                        <label class="form-label" id="lblCantidadAjuste" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Unidades a Descontar <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="cantidad" id="inputCantidadAjuste" class="form-control form-control-dark"
                               value="{{ old('cantidad') }}" min="1" required>
                        <small id="helpCantidadAjuste" class="form-text text-muted" style="font-size:0.75rem;">
                            Indique el número positivo de unidades que saldrán de bodega.
                        </small>
                    </div>

                    {{-- Motivo / Justificación --}}
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                            Justificación / Motivo Detallado <span class="text-danger">*</span>
                        </label>
                        <textarea name="motivo" class="form-control form-control-dark" rows="3"
                                  placeholder="Detalle la causa técnica, informe de auditoría o destino del repuesto (mínimo 10 caracteres)..."
                                  required minlength="10">{{ old('motivo') }}</textarea>
                        <small class="form-text text-muted" style="font-size:0.72rem;">
                            Requerimiento de auditoría Smart-DB: el motivo debe tener al menos 10 caracteres.
                        </small>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3" style="border-top:1px solid #F1F5F9;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:10px;font-size:0.85rem;min-height:44px;padding:0.5rem 1.25rem;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-tuerca" style="border-radius:10px;font-size:0.85rem;min-height:44px;padding:0.5rem 1.25rem;">
                        <i class="bi bi-check2-circle me-1"></i>Confirmar Ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectProd = document.getElementById('selectProductoAjuste');
    const infoStock = document.getElementById('infoStockActual');
    const lblStockVal = document.getElementById('lblStockActualVal');
    const selectTipo = document.getElementById('selectTipoAjuste');
    const lblCantidad = document.getElementById('lblCantidadAjuste');
    const inputCantidad = document.getElementById('inputCantidadAjuste');
    const helpCantidad = document.getElementById('helpCantidadAjuste');

    function actualizarStockInfo() {
        const selected = selectProd.options[selectProd.selectedIndex];
        if (selected && selected.dataset.stock !== undefined) {
            lblStockVal.textContent = selected.dataset.stock;
            infoStock.style.display = 'block';
        } else {
            infoStock.style.display = 'none';
        }
    }

    function actualizarModoTipo() {
        const tipo = selectTipo.value;
        if (tipo === 'Conteo Físico') {
            lblCantidad.innerHTML = 'Stock Total Contado en Estantería <span class="text-danger">*</span>';
            inputCantidad.min = 0;
            helpCantidad.textContent = 'Ingrese el total físico real que contó en bodega. El SP Smart-DB calculará la diferencia automáticamente.';
        } else {
            lblCantidad.innerHTML = 'Unidades a Descontar <span class="text-danger">*</span>';
            inputCantidad.min = 1;
            helpCantidad.textContent = 'Indique el número positivo de unidades a retirar de bodega.';
        }
    }

    selectProd.addEventListener('change', actualizarStockInfo);
    selectTipo.addEventListener('change', actualizarModoTipo);

    actualizarStockInfo();
    actualizarModoTipo();
});
</script>
@endsection
