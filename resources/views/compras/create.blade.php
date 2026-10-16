@extends('layouts.app')
@section('title', 'Recepción de Compra — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('compras.index') }}">Compras</a></li>
    <li class="breadcrumb-item active">Nueva Recepción</li>
@endsection

@section('content')
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
                <i class="bi bi-box-arrow-in-down me-2" style="color:#007AFF;"></i>Recepción y Entrada de Mercadería
            </h4>
            <p style="color:#64748B;font-size:0.85rem;margin:0;">
                Ingrese los repuestos adquiridos para aumentar automáticamente el stock en almacén mediante SP Smart-DB.
            </p>
        </div>
        <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;font-size:0.85rem;">
            <i class="bi bi-arrow-left me-1"></i>Volver a Compras
        </a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div style="font-size:0.9rem;">{{ session('error') }}</div>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger mb-4" style="border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i>Por favor verifique los siguientes errores:</div>
        <ul class="mb-0 ps-3" style="font-size:0.85rem;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('compras.store') }}" method="POST" id="formCompra">
    @csrf

    {{-- ═══ CABECERA DE LA COMPRA ═══ --}}
    <div class="card-light p-4 mb-4">
        <h6 style="color:#1E293B;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
            <i class="bi bi-file-earmark-text text-primary"></i>Datos de la Factura y Distribuidor
        </h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                    Proveedor Mayorista <span class="text-danger">*</span>
                </label>
                <select name="id_proveedor" class="form-select form-select-dark" required>
                    <option value="">-- Seleccionar distribuidor --</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id_proveedor }}" {{ old('id_proveedor') == $prov->id_proveedor ? 'selected' : '' }}>
                            {{ $prov->razon_social }} ({{ $prov->condicion_comercial }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                    N° Factura / Nota Entrega <span class="text-danger">*</span>
                </label>
                <input type="text" name="numero_factura_nota" class="form-control form-control-dark"
                       value="{{ old('numero_factura_nota') }}" placeholder="Ej: F001-002849" required>
            </div>

            <div class="col-md-2">
                <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                    Fecha de Compra <span class="text-danger">*</span>
                </label>
                <input type="date" name="fecha_compra" class="form-control form-control-dark"
                       value="{{ old('fecha_compra', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3">
                <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#475569;">
                    Modalidad de Pago <span class="text-danger">*</span>
                </label>
                <select name="tipo_pago" class="form-select form-select-dark" required>
                    <option value="Contado" {{ old('tipo_pago') == 'Contado' ? 'selected' : '' }}>Contado (Asentado como Pagado)</option>
                    <option value="Crédito" {{ old('tipo_pago') == 'Crédito' ? 'selected' : '' }}>Crédito (Pasa a Cuentas por Pagar)</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ═══ DETALLE DE REPUESTOS INGRESADOS ═══ --}}
    <div class="card-light p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 style="color:#1E293B;font-weight:700;margin:0;display:flex;align-items:center;gap:0.5rem;">
                    <i class="bi bi-box-seam text-primary"></i>Repuestos a Ingresar en Almacén
                </h6>
                <small style="color:#64748B;font-size:0.78rem;">
                    Agregue las líneas de repuestos recibidos indicando cantidad física y costo unitario pactado en factura.
                </small>
            </div>
            <button type="button" class="btn btn-sm btn-tuerca" id="btnAgregarFila" style="padding:0.4rem 0.9rem;">
                <i class="bi bi-plus-circle me-1"></i>Agregar Repuesto
            </button>
        </div>

        {{-- Cabecera visible sólo en escritorio --}}
        <div class="d-none d-md-flex align-items-center gap-2 px-3 py-2 mb-2 bg-light rounded-3 text-muted fw-semibold" style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px;">
            <div class="flex-grow-1">Repuesto del Catálogo</div>
            <div style="width: 150px;">Ubicación / Stock</div>
            <div style="width: 110px;" class="text-center">Cantidad</div>
            <div style="width: 130px;" class="text-end">Costo Unit. (Bs.)</div>
            <div style="width: 130px;" class="text-end">Subtotal (Bs.)</div>
            <div style="width: 44px;"></div>
        </div>

        {{-- Contenedor dinámico de filas (se apilan verticalmente en móviles flex-column y se alinean en horizontal en escritorio flex-md-row) --}}
        <div id="cuerpoDetalles">
            {{-- Las filas se insertan dinámicamente con JavaScript --}}
        </div>

        {{-- Resumen de totales adaptativo para móvil y escritorio --}}
        <div class="card p-3 mt-3" style="background:#FAFBFC;border:1px solid #E2E8F0;border-radius:12px;">
            <div class="row g-2 align-items-center text-center text-md-start">
                <div class="col-6 col-md-3">
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;">Total de Ítems:</small>
                    <div class="fw-bold fs-5 text-dark" id="lblTotalItems">0</div>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;">Unidades Totales:</small>
                    <div class="fw-bold fs-5 text-dark" id="lblTotalCantidad">0</div>
                </div>
                <div class="col-12 col-md-6 text-md-end mt-2 mt-md-0 pt-2 pt-md-0 border-top border-md-0">
                    <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Importe Total de Adquisición:</small>
                    <span class="fw-bolder fs-4 font-monospace text-primary" id="lblMontoTotal">
                        Bs. 0.00
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ BOTONES DE ACCIÓN ═══ --}}
    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mb-5">
        <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;padding:0.6rem 1.5rem;font-size:0.85rem;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">
            Cancelar
        </a>
        <button type="submit" class="btn btn-tuerca" style="border-radius:10px;padding:0.6rem 1.8rem;font-size:0.85rem;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">
            <i class="bi bi-check2-circle me-1"></i>Asentar Recepción en Almacén
        </button>
    </div>
</form>

{{-- Catálogo de productos serializado para inyección dinámica rápida --}}
<script>
const CATALOGO_PRODUCTOS = @json($productos);

let contadorFilas = 0;

function crearFila(index, idProd = '', cant = 1, costo = '') {
    const div = document.createElement('div');
    div.id = `fila-${index}`;
    div.className = 'fila-repuesto p-3 mb-2 bg-white border rounded-3 shadow-sm';

    let opciones = '<option value="">-- Seleccionar repuesto --</option>';
    CATALOGO_PRODUCTOS.forEach(p => {
        const ubi = (p.pasillo || p.estante || p.nivel_gaveta) 
            ? `${p.pasillo || '-'}/${p.estante || '-'}/${p.nivel_gaveta || '-'}`
            : 'Sin asignar';
        opciones += `<option value="${p.id_producto}" data-stock="${p.stock_actual}" data-ubi="${ubi}" ${p.id_producto == idProd ? 'selected' : ''}>[${p.codigo_interno}] ${p.nombre_pieza}</option>`;
    });

    div.innerHTML = `
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2">
            <!-- Repuesto -->
            <div class="flex-grow-1">
                <label class="d-md-none text-muted small fw-semibold mb-1">Repuesto:</label>
                <select name="items[${index}][id_producto]" class="form-select form-select-dark select-producto" required onchange="actualizarInfoProducto(${index})">
                    ${opciones}
                </select>
                <div id="ubi-${index}" class="mt-1" style="font-size:0.75rem;color:#64748B;">
                    <span class="badge bg-light text-secondary border">Seleccione repuesto</span>
                </div>
            </div>

            <!-- Cantidad -->
            <div style="min-width: 110px;">
                <label class="d-md-none text-muted small fw-semibold mb-1">Cantidad:</label>
                <input type="number" name="items[${index}][cantidad]" class="form-control form-control-dark text-center input-cantidad"
                       value="${cant}" min="1" required oninput="calcularTotales()" placeholder="Cant.">
            </div>

            <!-- Costo Unitario -->
            <div style="min-width: 130px;">
                <label class="d-md-none text-muted small fw-semibold mb-1">Costo Unit. (Bs.):</label>
                <input type="number" step="0.01" name="items[${index}][costo]" class="form-control form-control-dark text-end input-costo"
                       value="${costo}" min="0.01" placeholder="0.00" required oninput="calcularTotales()">
            </div>

            <!-- Subtotal y Eliminar -->
            <div class="d-flex justify-content-between align-items-center gap-2 pt-2 pt-md-0 border-top border-md-0" style="min-width: 180px;">
                <div class="text-md-end flex-grow-1">
                    <small class="d-md-none text-muted fw-semibold">Subtotal: </small>
                    <span class="subtotal-fila fw-bold fs-6 font-monospace text-dark">
                        Bs. 0.00
                    </span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center"
                            onclick="eliminarFila(${index})" style="min-height:44px;min-width:44px;border-radius:8px;" title="Eliminar fila">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

    document.getElementById('cuerpoDetalles').appendChild(div);
    actualizarInfoProducto(index);
    calcularTotales();
}

function actualizarInfoProducto(index) {
    const fila = document.getElementById(`fila-${index}`);
    if (!fila) return;
    const select = fila.querySelector('.select-producto');
    const ubiDiv = document.getElementById(`ubi-${index}`);
    const selected = select.options[select.selectedIndex];

    if (selected && selected.value) {
        const stock = selected.dataset.stock;
        const ubi = selected.dataset.ubi;
        ubiDiv.innerHTML = `
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Stock: ${stock} u.</span>
                <span class="text-secondary" style="font-size:0.72rem;"><i class="bi bi-geo-alt me-1"></i>${ubi}</span>
            </div>
        `;
    } else {
        ubiDiv.innerHTML = '<span class="badge bg-light text-secondary border">Seleccione repuesto</span>';
    }
}

function eliminarFila(index) {
    const fila = document.getElementById(`fila-${index}`);
    if (fila) {
        fila.remove();
        calcularTotales();
    }
}

function calcularTotales() {
    let totalItems = 0;
    let totalCantidad = 0;
    let granTotal = 0;

    const filas = document.querySelectorAll('#cuerpoDetalles .fila-repuesto');
    filas.forEach(f => {
        const cantInput = f.querySelector('.input-cantidad');
        const costoInput = f.querySelector('.input-costo');
        const subtotalSpan = f.querySelector('.subtotal-fila');

        const cant = parseFloat(cantInput?.value) || 0;
        const costo = parseFloat(costoInput?.value) || 0;
        const subtotal = cant * costo;

        if (subtotalSpan) {
            subtotalSpan.textContent = `Bs. ${subtotal.toFixed(2)}`;
        }

        if (cant > 0) {
            totalItems++;
            totalCantidad += cant;
            granTotal += subtotal;
        }
    });

    document.getElementById('lblTotalItems').textContent = totalItems;
    document.getElementById('lblTotalCantidad').textContent = totalCantidad;
    document.getElementById('lblMontoTotal').textContent = `Bs. ${granTotal.toFixed(2)}`;
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnAgregarFila').addEventListener('click', function () {
        contadorFilas++;
        crearFila(contadorFilas);
    });

    // Iniciar con al menos una fila
    crearFila(contadorFilas);
});
</script>
@endsection
