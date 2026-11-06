@extends('layouts.app')
@section('title', 'Punto de Venta (POS) — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ventas.index') }}">Notas de Venta</a></li>
    <li class="breadcrumb-item active">Punto de Venta (POS)</li>
@endsection

@section('content')
{{-- ═══ CABECERA POS ═══ --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.15rem;">
            <i class="bi bi-cart3 me-2" style="color:#007AFF;"></i>Terminal Punto de Venta (POS)
        </h4>
        <p style="color:#64748B;font-size:0.83rem;margin:0;">
            Facturación directa en mostrador, control de stock en tiempo real, descuentos autorizados y cobro multicanal.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @if($sesionActiva)
            <span class="badge-activo d-inline-flex align-items-center gap-1 py-2 px-3" style="font-size:0.8rem;">
                <i class="bi bi-unlock-fill"></i>
                Caja #{{ $sesionActiva->id_sesion }} Abierta · Saldo: Bs. {{ number_format($sesionActiva->monto_esperado_sistema, 2) }}
            </span>
        @else
            <a href="{{ route('caja.index') }}" class="btn btn-sm btn-danger" style="border-radius:10px;font-weight:600;">
                <i class="bi bi-lock-fill me-1"></i>Caja Cerrada — Aperturar Turno Ahora
            </a>
        @endif
        <a href="{{ route('ventas.index') }}" class="btn btn-outline-tuerca">
            <i class="bi bi-receipt me-1"></i>Historial de Ventas
        </a>
    </div>
</div>

@if(!$sesionActiva)
    <div class="alert alert-tuerca-error d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <strong>Atención:</strong> No existe una sesión de caja abierta en este momento. La base de datos bloqueará cualquier cobro hasta que aperture un turno de caja.
        </div>
        <a href="{{ route('caja.index') }}" class="btn btn-sm btn-danger">
            <i class="bi bi-box-arrow-in-right me-1"></i>Ir a Tesorería y Caja
        </a>
    </div>
@endif

<form action="{{ route('ventas.store') }}" method="POST" id="formPosVenta">
    @csrf
    <input type="hidden" name="id_sesion_caja" value="{{ $sesionActiva->id_sesion ?? '' }}">

    <div class="row g-3">
        {{-- ═══ COLUMNA IZQUIERDA: CATÁLOGO Y BUSCADOR INSTANTÁNEO ═══ --}}
        <div class="col-lg-7">
            <div class="card-light h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-upc-scan me-2 text-primary"></i>Buscador Instantáneo de Repuestos</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-light text-secondary border" id="contadorCatalogoPos">{{ count($productos) }} repuestos</span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-primary"></i></span>
                                <input type="text" id="inputBuscarPos" class="form-control form-control-dark"
                                       placeholder="Buscar por código OEM (ej. 04465), alternativo, SKU o descripción..."
                                       autocomplete="off" autofocus oninput="filtrarRepuestosPos()">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('inputBuscarPos').value='';filtrarRepuestosPos();" title="Limpiar">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select id="filtroStockPos" class="form-select form-select-dark" onchange="filtrarRepuestosPos()">
                                <option value="disponibles">Solo con Stock (> 0)</option>
                                <option value="todos">Todos los Repuestos</option>
                            </select>
                        </div>
                    </div>

                    <div style="max-height:510px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:12px;">
                        <table class="table table-dark-custom mb-0">
                            <thead style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th>OEM / SKU</th>
                                    <th>Repuesto / Ubicación</th>
                                    <th class="text-center">Existencia</th>
                                    <th class="text-end">Precio (Bs.)</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCatalogoPos">
                                {{-- Poblado dinámico JS --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ COLUMNA DERECHA: CLIENTE, CARRITO, DESCUENTO Y COBRO MULTICANAL ═══ --}}
        <div class="col-lg-5">
            <div class="card-light">
                {{-- 1. Selección de Cliente --}}
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-person-Vcard me-2 text-primary"></i>Cliente y Facturación</h6>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-tuerca" onclick="seleccionarMostrador()">
                            <i class="bi bi-lightning-charge me-1"></i>Mostrador S/N
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalClientePos">
                            <i class="bi bi-person-plus"></i> Nuevo
                        </button>
                    </div>
                </div>

                <div class="card-body p-3">
                    <div class="mb-3">
                        <select name="id_cliente" id="selectClientePos" class="form-select form-select-dark" required onchange="alCambiarClientePos()">
                            @foreach($clientes as $cl)
                                <option value="{{ $cl->id_cliente }}"
                                        data-tipo="{{ $cl->tipo_cliente }}"
                                        data-desc="{{ (float)$cl->descuento_frecuente }}"
                                        data-nit="{{ $cl->ci_nit }}"
                                        {{ (string)old('id_cliente', $clientePreseleccionado) === (string)$cl->id_cliente ? 'selected' : '' }}>
                                    {{ $cl->nombre_razon_social }} — NIT/CI: {{ $cl->ci_nit ?: 'S/N' }} ({{ $cl->tipo_cliente }})
                                </option>
                            @endforeach
                        </select>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small id="infoClientePos" class="text-muted" style="font-size:0.74rem;"></small>
                            <button type="button" id="btnAplicarDescCliente" class="btn btn-link btn-sm p-0 text-decoration-none d-none" style="font-size:0.75rem;font-weight:600;" onclick="aplicarDescuentoFrecuenteCliente()">
                                <i class="bi bi-percent"></i> Aplicar descuento de taller/aliado
                            </button>
                        </div>
                    </div>

                    {{-- 2. Carrito de Venta --}}
                    <div class="table-responsive border rounded-3 mb-3" style="max-height:230px;overflow-y:auto;">
                        <table class="table table-dark-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Pieza / OEM</th>
                                    <th style="width:72px;" class="text-center">Cant.</th>
                                    <th style="width:102px;" class="text-end">P. Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCarritoPos">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-cart-plus fs-4 d-block mb-1"></i>
                                        Agregue repuestos desde el buscador izquierdo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Alerta de Validación de Margen / Costo --}}
                    <div id="alertaMargenCosto" class="alert alert-danger py-2 px-3 mb-3 d-none" style="font-size:0.78rem;border-radius:10px;">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        <span id="textoAlertaMargen"></span>
                    </div>

                    {{-- 3. Descuento Comercial Autorizado --}}
                    <div class="p-3 rounded-3 mb-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="font-size:0.82rem;color:#64748B;font-weight:600;">Subtotal Bruto:</span>
                            <strong id="posSubtotalBruto" style="font-family:monospace;font-size:0.95rem;">Bs. 0.00</strong>
                        </div>

                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-5">
                                <label class="form-label-dark mb-0" style="font-size:0.75rem;">
                                    <i class="bi bi-tag-fill text-warning me-1"></i>Descuento Comercial
                                </label>
                            </div>
                            <div class="col-3">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.5" min="0" max="50" id="posDescuentoPct"
                                           class="form-control form-control-dark text-end" placeholder="0"
                                           oninput="calcularDesdePorcentaje(this.value)">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Bs.</span>
                                    <input type="number" step="0.50" min="0" name="descuento_total" id="posDescuentoBs"
                                           class="form-control form-control-dark text-end" value="0.00"
                                           oninput="calcularDesdeMontoBs(this.value)">
                                </div>
                            </div>
                        </div>

                        <div id="bloqueAutorizacionDesc" class="mt-2 d-none">
                            <input type="text" name="autorizacion_descuento" class="form-control form-control-sm form-control-dark"
                                   placeholder="Motivo / Autorización de descuento especial (ej. Convenio Taller Aliado)">
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-2">
                            <span style="font-size:0.95rem;font-weight:800;color:#1E293B;">TOTAL NETO A COBRAR:</span>
                            <span id="posTotalNeto" style="font-size:1.45rem;font-weight:800;color:#007AFF;font-family:monospace;">Bs. 0.00</span>
                        </div>
                    </div>

                    {{-- 4. Modalidad de Cobro Multicanal --}}
                    <div class="mb-3">
                        <label class="form-label-dark">Modalidad de Cobro Multicanal <span class="text-danger">*</span></label>
                        <div class="row g-2" id="selectorCanalesPago">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="metodo_pago" id="pagoEfectivo" value="Efectivo" checked onchange="cambiarCanalPago(this.value)">
                                <label class="btn btn-outline-tuerca w-100 text-start py-2" for="pagoEfectivo">
                                    <i class="bi bi-cash-coin me-1 text-success"></i> Efectivo (Bs.)
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="metodo_pago" id="pagoQr" value="Transferencia/QR" onchange="cambiarCanalPago(this.value)">
                                <label class="btn btn-outline-tuerca w-100 text-start py-2" for="pagoQr">
                                    <i class="bi bi-qr-code-scan me-1 text-primary"></i> Transferencia / QR
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="metodo_pago" id="pagoTarjeta" value="Tarjeta Débito/Crédito" onchange="cambiarCanalPago(this.value)">
                                <label class="btn btn-outline-tuerca w-100 text-start py-2" for="pagoTarjeta">
                                    <i class="bi bi-credit-card-2-front me-1 text-info"></i> Tarjeta Débito/Créd.
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="metodo_pago" id="pagoMixto" value="Mixto" onchange="cambiarCanalPago(this.value)">
                                <label class="btn btn-outline-tuerca w-100 text-start py-2" for="pagoMixto">
                                    <i class="bi bi-layers-half me-1 text-warning"></i> Cobro Mixto
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Panel Efectivo: Calculadora de Recibido y Vuelto --}}
                    <div id="panelPosEfectivo" class="p-3 rounded-3 mb-3" style="background:#ECFDF5;border:1px solid #A7F3D0;">
                        <div class="row g-2 align-items-center">
                            <div class="col-6">
                                <label class="form-label-dark mb-1" style="color:#065F46;">Monto Recibido (Bs.)</label>
                                <input type="number" step="0.50" min="0" name="monto_recibido" id="posMontoRecibido"
                                       class="form-control form-control-dark fw-bold" oninput="recalcularVueltoPos()">
                                <div class="d-flex gap-1 mt-1 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size:0.7rem;" onclick="fijarBillete(50)">Bs.50</button>
                                    <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size:0.7rem;" onclick="fijarBillete(100)">Bs.100</button>
                                    <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size:0.7rem;" onclick="fijarBillete(200)">Bs.200</button>
                                    <button type="button" class="btn btn-sm btn-light border py-0 px-2" style="font-size:0.7rem;" onclick="fijarExacto()">Exacto</button>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <div style="font-size:0.72rem;font-weight:700;color:#065F46;text-transform:uppercase;">Cambio / Vuelto</div>
                                <div id="posVueltoTexto" style="font-size:1.35rem;font-weight:800;color:#059669;font-family:monospace;">Bs. 0.00</div>
                            </div>
                        </div>
                    </div>

                    {{-- Panel QR / Transferencia / Tarjeta --}}
                    <div id="panelPosReferencia" class="p-3 rounded-3 mb-3 d-none" style="background:#EFF6FF;border:1px solid #BFDBFE;">
                        <label class="form-label-dark mb-1" style="color:#1E40AF;">N° de Referencia / Comprobante QR o Voucher</label>
                        <input type="text" name="nro_referencia" class="form-control form-control-dark"
                               placeholder="Ej: QR-77891234 / Voucher POS #4912">
                    </div>

                    {{-- Panel Pago Mixto --}}
                    <div id="panelPosMixto" class="p-3 rounded-3 mb-3 d-none" style="background:#FFFBEB;border:1px solid #FDE68A;">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label-dark mb-1">Monto en Efectivo (Bs.)</label>
                                <input type="number" step="0.50" min="0" name="monto_efectivo" id="posMixtoEfectivo"
                                       class="form-control form-control-dark" oninput="recalcularMixtoPos()">
                            </div>
                            <div class="col-6">
                                <label class="form-label-dark mb-1">Monto por QR / Tarjeta (Bs.)</label>
                                <input type="number" step="0.50" min="0" name="monto_digital" id="posMixtoDigital"
                                       class="form-control form-control-dark" readonly>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btnProcesarVentaPos" class="btn btn-tuerca w-100 py-2" style="font-size:0.95rem;font-weight:700;" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Procesar Cobro y Emitir Comprobante
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Modal de Creación Rápida de Cliente en POS --}}
<div class="modal fade" id="modalClientePos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill text-primary me-2"></i>Nuevo Cliente / Taller en POS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="errModalCliPos" class="alert alert-danger d-none py-2" style="font-size:0.82rem;"></div>
                <div class="mb-3">
                    <label class="form-label-dark">Nombre o Razón Social <span class="text-danger">*</span></label>
                    <input type="text" id="posCliNombre" class="form-control form-control-dark" placeholder="Ej: Taller Mecánico Japón">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label-dark">CI / NIT</label>
                        <input type="text" id="posCliNit" class="form-control form-control-dark" placeholder="Ej: 5891234015">
                    </div>
                    <div class="col-6">
                        <label class="form-label-dark">Teléfono / WhatsApp</label>
                        <input type="text" id="posCliTel" class="form-control form-control-dark" placeholder="Ej: 77011223">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="form-label-dark">Categoría</label>
                        <select id="posCliTipo" class="form-select form-select-dark">
                            <option value="Particular">Particular</option>
                            <option value="Taller/Mecánico">Taller / Mecánico Aliado</option>
                            <option value="Empresa/Flota">Empresa / Flota</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label-dark">Descuento (%)</label>
                        <input type="number" step="0.5" min="0" max="50" id="posCliDesc" class="form-control form-control-dark" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-tuerca" onclick="guardarClienteDesdePos()">
                    <i class="bi bi-check2 me-1"></i>Registrar y Seleccionar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const catalogoPos = @json($productos);
    const sesionCajaAbierta = {{ $sesionActiva ? 'true' : 'false' }};
    let carritoPos = [];

    function filtrarRepuestosPos() {
        const q = (document.getElementById('inputBuscarPos').value || '').toLowerCase().trim();
        const modoStock = document.getElementById('filtroStockPos').value;
        const tbody = document.getElementById('tbodyCatalogoPos');
        tbody.innerHTML = '';

        const filtrados = catalogoPos.filter(p => {
            if (modoStock === 'disponibles' && p.stock_actual <= 0) return false;
            if (!q) return true;
            return p.nombre_pieza.toLowerCase().includes(q)
                || p.codigo_oem.toLowerCase().includes(q)
                || p.codigo_interno.toLowerCase().includes(q)
                || (p.codigos_alt && p.codigos_alt.toLowerCase().includes(q))
                || p.marca.toLowerCase().includes(q);
        });

        document.getElementById('contadorCatalogoPos').textContent = `${filtrados.length} repuestos`;

        if (filtrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No se encontraron repuestos con esos criterios.</td></tr>';
            return;
        }

        filtrados.forEach(p => {
            const sinStock = p.stock_actual <= 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div style="font-family:monospace;font-weight:700;color:#007AFF;font-size:0.8rem;">${p.codigo_oem}</div>
                    <small class="text-muted">${p.codigo_interno}</small>
                </td>
                <td>
                    <div style="font-weight:600;color:#1E293B;font-size:0.83rem;">${p.nombre_pieza}</div>
                    <small class="text-muted">${p.marca} · <i class="bi bi-geo-alt"></i> ${p.ubicacion}</small>
                </td>
                <td class="text-center">
                    <span class="${p.stock_actual > 3 ? 'badge-activo' : (p.stock_actual > 0 ? 'badge-stock-bajo' : 'badge-inactivo')}">
                        ${p.stock_actual} uds
                    </span>
                </td>
                <td class="text-end">
                    <div class="fw-bold" style="font-family:monospace;color:#1E293B;">Bs. ${p.precio_sugerido.toFixed(2)}</div>
                    <small class="text-muted" style="font-size:0.68rem;">Piso: Bs. ${p.costo_minimo.toFixed(2)}</small>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm ${sinStock ? 'btn-outline-secondary' : 'btn-tuerca'}"
                            ${sinStock ? 'disabled' : ''} onclick="agregarAlCarritoPos(${p.id_producto})">
                        <i class="bi bi-cart-plus"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function agregarAlCarritoPos(idProd) {
        const prod = catalogoPos.find(p => p.id_producto === idProd);
        if (!prod || prod.stock_actual <= 0) return;

        const existente = carritoPos.find(i => i.id_producto === idProd);
        if (existente) {
            if (existente.cantidad + 1 > prod.stock_actual) {
                alert(`Stock máximo disponible para "${prod.nombre_pieza}": ${prod.stock_actual} unidades.`);
                return;
            }
            existente.cantidad += 1;
        } else {
            carritoPos.push({
                id_producto: prod.id_producto,
                codigo_oem: prod.codigo_oem,
                nombre_pieza: prod.nombre_pieza,
                stock_max: prod.stock_actual,
                costo_minimo: prod.costo_minimo,
                cantidad: 1,
                precio: prod.precio_sugerido
            });
        }
        renderizarCarritoPos();
    }

    function quitarDelCarritoPos(idx) {
        carritoPos.splice(idx, 1);
        renderizarCarritoPos();
    }

    function cambiarLineaPos(idx, campo, valor) {
        const item = carritoPos[idx];
        if (!item) return;

        if (campo === 'cantidad') {
            let cant = parseInt(valor) || 1;
            if (cant < 1) cant = 1;
            if (cant > item.stock_max) {
                alert(`Stock insuficiente: solo hay ${item.stock_max} unidades en almacén.`);
                cant = item.stock_max;
            }
            item.cantidad = cant;
        } else if (campo === 'precio') {
            let pr = parseFloat(valor) || item.costo_minimo;
            item.precio = Math.max(0.01, pr);
        }
        renderizarCarritoPos();
    }

    function obtenerBrutoCarrito() {
        return carritoPos.reduce((acc, it) => acc + (it.cantidad * it.precio), 0);
    }

    function calcularDesdePorcentaje(pctVal) {
        const bruto = obtenerBrutoCarrito();
        let pct = parseFloat(pctVal) || 0;
        if (pct < 0) pct = 0;
        if (pct > 50) pct = 50;
        const montoDesc = (bruto * (pct / 100));
        document.getElementById('posDescuentoBs').value = montoDesc.toFixed(2);
        actualizarTotalesPos();
    }

    function calcularDesdeMontoBs(bsVal) {
        const bruto = obtenerBrutoCarrito();
        let desc = parseFloat(bsVal) || 0;
        if (desc < 0) desc = 0;
        const pct = bruto > 0 ? ((desc / bruto) * 100) : 0;
        document.getElementById('posDescuentoPct').value = pct > 0 ? pct.toFixed(1) : '';
        actualizarTotalesPos();
    }

    function aplicarDescuentoFrecuenteCliente() {
        const sel = document.getElementById('selectClientePos');
        const opt = sel.options[sel.selectedIndex];
        const desc = parseFloat(opt?.getAttribute('data-desc')) || 0;
        document.getElementById('posDescuentoPct').value = desc;
        calcularDesdePorcentaje(desc);
    }

    function renderizarCarritoPos() {
        const tbody = document.getElementById('tbodyCarritoPos');
        tbody.innerHTML = '';

        if (carritoPos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-cart-plus fs-4 d-block mb-1"></i>
                        Agregue repuestos desde el buscador izquierdo.
                    </td>
                </tr>`;
            actualizarTotalesPos();
            return;
        }

        carritoPos.forEach((it, idx) => {
            const sub = it.cantidad * it.precio;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="hidden" name="items[${idx}][id_producto]" value="${it.id_producto}">
                    <div style="font-weight:600;font-size:0.8rem;color:#1E293B;">${it.nombre_pieza}</div>
                    <small style="font-family:monospace;color:#64748B;">OEM: ${it.codigo_oem} · Costo: Bs.${it.costo_minimo.toFixed(2)}</small>
                </td>
                <td>
                    <input type="number" min="1" max="${it.stock_max}" name="items[${idx}][cantidad]" value="${it.cantidad}"
                           class="form-control form-control-sm form-control-dark text-center"
                           onchange="cambiarLineaPos(${idx}, 'cantidad', this.value)">
                </td>
                <td>
                    <input type="number" step="0.50" min="0.01" name="items[${idx}][precio]" value="${it.precio.toFixed(2)}"
                           class="form-control form-control-sm form-control-dark text-end"
                           onchange="cambiarLineaPos(${idx}, 'precio', this.value)">
                </td>
                <td class="text-end fw-bold" style="font-family:monospace;font-size:0.82rem;">
                    Bs. ${sub.toFixed(2)}
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm text-danger p-1" onclick="quitarDelCarritoPos(${idx})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        actualizarTotalesPos();
    }

    function actualizarTotalesPos() {
        const bruto = obtenerBrutoCarrito();
        const desc = parseFloat(document.getElementById('posDescuentoBs').value) || 0;
        const neto = Math.max(0, bruto - desc);

        document.getElementById('posSubtotalBruto').textContent = 'Bs. ' + bruto.toFixed(2);
        document.getElementById('posTotalNeto').textContent = 'Bs. ' + neto.toFixed(2);
        document.getElementById('bloqueAutorizacionDesc').classList.toggle('d-none', desc <= 0);

        // Validar que ningún ítem perfore su costo de adquisición ni supere el 50%
        const alerta = document.getElementById('alertaMargenCosto');
        const textoAlerta = document.getElementById('textoAlertaMargen');
        let errorMargen = null;

        if (bruto > 0 && desc > bruto * 0.50) {
            errorMargen = `El descuento (Bs. ${desc.toFixed(2)}) supera el tope comercial máximo permitido del 50%.`;
        } else if (bruto > 0) {
            const factor = neto / bruto;
            for (const it of carritoPos) {
                const precioEfectivo = Math.round(it.precio * factor * 100) / 100;
                if (precioEfectivo < it.costo_minimo) {
                    errorMargen = `El precio neto de "${it.nombre_pieza}" (Bs. ${precioEfectivo.toFixed(2)}) queda por debajo de su costo de adquisición (Bs. ${it.costo_minimo.toFixed(2)}).`;
                    break;
                }
            }
        }

        if (errorMargen) {
            textoAlerta.textContent = errorMargen;
            alerta.classList.remove('d-none');
        } else {
            alerta.classList.add('d-none');
        }

        const inpRecibido = document.getElementById('posMontoRecibido');
        if (!inpRecibido.dataset.editadoManualmente) {
            inpRecibido.value = neto.toFixed(2);
        }

        recalcularVueltoPos();
        recalcularMixtoPos();

        document.getElementById('btnProcesarVentaPos').disabled = (carritoPos.length === 0 || !sesionCajaAbierta || !!errorMargen);
    }

    function cambiarCanalPago(metodo) {
        document.getElementById('panelPosEfectivo').classList.toggle('d-none', metodo !== 'Efectivo');
        document.getElementById('panelPosReferencia').classList.toggle('d-none', metodo === 'Efectivo');
        document.getElementById('panelPosMixto').classList.toggle('d-none', metodo !== 'Mixto');
        recalcularVueltoPos();
        recalcularMixtoPos();
    }

    function fijarBillete(monto) {
        const inp = document.getElementById('posMontoRecibido');
        inp.dataset.editadoManualmente = '1';
        inp.value = parseFloat(monto).toFixed(2);
        recalcularVueltoPos();
    }

    function fijarExacto() {
        const bruto = obtenerBrutoCarrito();
        const desc = parseFloat(document.getElementById('posDescuentoBs').value) || 0;
        const neto = Math.max(0, bruto - desc);
        const inp = document.getElementById('posMontoRecibido');
        delete inp.dataset.editadoManualmente;
        inp.value = neto.toFixed(2);
        recalcularVueltoPos();
    }

    function recalcularVueltoPos() {
        const bruto = obtenerBrutoCarrito();
        const desc = parseFloat(document.getElementById('posDescuentoBs').value) || 0;
        const neto = Math.max(0, bruto - desc);
        const recibido = parseFloat(document.getElementById('posMontoRecibido').value) || 0;
        const vuelto = Math.max(0, recibido - neto);
        document.getElementById('posVueltoTexto').textContent = 'Bs. ' + vuelto.toFixed(2);
    }

    function recalcularMixtoPos() {
        const bruto = obtenerBrutoCarrito();
        const desc = parseFloat(document.getElementById('posDescuentoBs').value) || 0;
        const neto = Math.max(0, bruto - desc);
        const ef = parseFloat(document.getElementById('posMixtoEfectivo').value) || 0;
        const dig = Math.max(0, neto - ef);
        document.getElementById('posMixtoDigital').value = dig.toFixed(2);
    }

    function seleccionarMostrador() {
        const sel = document.getElementById('selectClientePos');
        sel.value = '1';
        alCambiarClientePos();
    }

    function alCambiarClientePos() {
        const sel = document.getElementById('selectClientePos');
        const opt = sel.options[sel.selectedIndex];
        const info = document.getElementById('infoClientePos');
        const btnDesc = document.getElementById('btnAplicarDescCliente');

        if (!opt) return;
        const tipo = opt.getAttribute('data-tipo');
        const desc = parseFloat(opt.getAttribute('data-desc')) || 0;

        info.innerHTML = `Segmento: <strong>${tipo}</strong> · Descuento autorizado: <strong>${desc}%</strong>`;
        if (desc > 0) {
            btnDesc.classList.remove('d-none');
        } else {
            btnDesc.classList.add('d-none');
        }
    }

    async function guardarClienteDesdePos() {
        const nombre = document.getElementById('posCliNombre').value.trim();
        const nit = document.getElementById('posCliNit').value.trim();
        const tel = document.getElementById('posCliTel').value.trim();
        const tipo = document.getElementById('posCliTipo').value;
        const desc = document.getElementById('posCliDesc').value;
        const err = document.getElementById('errModalCliPos');

        err.classList.add('d-none');
        if (nombre.length < 3) {
            err.textContent = 'El nombre o razón social debe tener al menos 3 caracteres.';
            err.classList.remove('d-none');
            return;
        }

        try {
            const resp = await fetch("{{ route('clientes.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    nombre_razon_social: nombre,
                    ci_nit: nit,
                    telefono: tel,
                    tipo_cliente: tipo,
                    descuento_frecuente: desc
                })
            });

            const data = await resp.json();
            if (!resp.ok) {
                err.textContent = data.error || data.message || 'No se pudo registrar el cliente.';
                err.classList.remove('d-none');
                return;
            }

            const sel = document.getElementById('selectClientePos');
            const opt = document.createElement('option');
            opt.value = data.cliente.id_cliente;
            opt.textContent = `${data.cliente.nombre_razon_social} — NIT/CI: ${data.cliente.ci_nit || 'S/N'} (${data.cliente.tipo_cliente})`;
            opt.setAttribute('data-tipo', data.cliente.tipo_cliente);
            opt.setAttribute('data-desc', data.cliente.descuento_frecuente);
            opt.selected = true;
            sel.appendChild(opt);
            alCambiarClientePos();

            bootstrap.Modal.getInstance(document.getElementById('modalClientePos')).hide();
        } catch (e) {
            err.textContent = 'Error de red al guardar cliente.';
            err.classList.remove('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        filtrarRepuestosPos();
        alCambiarClientePos();
    });
</script>
@endpush

