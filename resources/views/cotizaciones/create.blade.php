@extends('layouts.app')
@section('title', 'Emitir Cotización Formal — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cotizaciones.index') }}">Cotizaciones</a></li>
    <li class="breadcrumb-item active">Nueva Cotización</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-file-earmark-plus-fill me-2" style="color:#007AFF;"></i>Emisión de Cotización / Proforma Formal
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Presupuesto de repuestos con cálculo automático de vigencia y precios garantizados para talleres y clientes.
        </p>
    </div>
    <a href="{{ route('cotizaciones.index') }}" class="btn btn-outline-tuerca">
        <i class="bi bi-arrow-left me-1"></i>Volver a Bandeja
    </a>
</div>

<form action="{{ route('cotizaciones.store') }}" method="POST" id="formCotizacion">
    @csrf
    <div class="row g-4">
        {{-- Columna Izquierda: Buscador de Repuestos --}}
        <div class="col-lg-7">
            <div class="card-light mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-search me-2 text-primary"></i>Buscador Técnico de Repuestos (OEM / Alternativo / Nombre)</h6>
                    <span class="badge bg-light text-secondary border">{{ count($productos) }} repuestos activos</span>
                </div>
                <div class="card-body p-3">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-primary"></i></span>
                        <input type="text" id="buscadorRepuesto" class="form-control form-control-dark"
                               placeholder="Escriba código OEM (ej. 04465), código interno, marca o nombre de la pieza..."
                               autocomplete="off" oninput="filtrarCatalogoCotizacion()">
                    </div>

                    <div style="max-height:360px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:12px;">
                        <table class="table table-dark-custom mb-0">
                            <thead style="position:sticky;top:0;z-index:2;">
                                <tr>
                                    <th>Código OEM / SKU</th>
                                    <th>Pieza / Marca</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-end">Precio (Bs.)</th>
                                    <th class="text-center">Agregar</th>
                                </tr>
                            </thead>
                            <tbody id="tablaCatalogoCotizacion">
                                {{-- Poblado dinámico JS --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna Derecha: Datos del Cliente y Detalle de Proforma --}}
        <div class="col-lg-5">
            <div class="card-light">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Datos de la Proforma</h6>
                    <button type="button" class="btn btn-sm btn-outline-tuerca" data-bs-toggle="modal" data-bs-target="#modalClienteRapido">
                        <i class="bi bi-person-plus me-1"></i>Crear Cliente Rápido
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label-dark">Cliente o Taller Destinatario <span class="text-danger">*</span></label>
                            <select name="id_cliente" id="selectClienteCot" class="form-select form-select-dark" required onchange="actualizarInfoClienteCot()">
                                <option value="">-- Seleccione un cliente --</option>
                                @foreach($clientes as $cl)
                                    <option value="{{ $cl->id_cliente }}"
                                            data-tipo="{{ $cl->tipo_cliente }}"
                                            data-desc="{{ $cl->descuento_frecuente }}"
                                            data-nit="{{ $cl->ci_nit }}"
                                            {{ (string)old('id_cliente', $clientePreseleccionado) === (string)$cl->id_cliente ? 'selected' : '' }}>
                                        {{ $cl->nombre_razon_social }} ({{ $cl->ci_nit ?: 'S/N' }})
                                    </option>
                                @endforeach
                            </select>
                            <small id="badgeInfoClienteCot" class="text-muted d-block mt-1" style="font-size:0.74rem;"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Validez (Días) <span class="text-danger">*</span></label>
                            <select name="dias_vigencia" class="form-select form-select-dark" required>
                                <option value="7" {{ old('dias_vigencia', 7) == 7 ? 'selected' : '' }}>7 días</option>
                                <option value="15" {{ old('dias_vigencia') == 15 ? 'selected' : '' }}>15 días</option>
                                <option value="30" {{ old('dias_vigencia') == 30 ? 'selected' : '' }}>30 días</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive border rounded-3 mb-3" style="max-height:280px;overflow-y:auto;">
                        <table class="table table-dark-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Repuesto</th>
                                    <th style="width:75px;" class="text-center">Cant.</th>
                                    <th style="width:110px;" class="text-end">P. Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="carritoCotizacionBody">
                                <tr id="filaVaciaCot">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox d-block fs-4 mb-1"></i>
                                        Seleccione repuestos del buscador para armar la proforma.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 rounded-3 mb-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-size:0.9rem;font-weight:700;color:#1E293B;">IMPORTE TOTAL PRESUPUESTADO:</span>
                            <span id="totalCotizacionTexto" style="font-size:1.4rem;font-weight:800;color:#007AFF;font-family:monospace;">Bs. 0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-tuerca w-100 py-2" id="btnGuardarCotizacion" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Emitir Cotización Formal
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Modal de Creación Rápida de Cliente --}}
<div class="modal fade" id="modalClienteRapido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill text-primary me-2"></i>Registro Rápido de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="errorClienteRapido" class="alert alert-danger d-none py-2" style="font-size:0.82rem;"></div>
                <div class="mb-3">
                    <label class="form-label-dark">Nombre o Razón Social <span class="text-danger">*</span></label>
                    <input type="text" id="rapidoNombre" class="form-control form-control-dark" placeholder="Ej: Taller Automotriz Oriental">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label-dark">CI / NIT</label>
                        <input type="text" id="rapidoNit" class="form-control form-control-dark" placeholder="S/N">
                    </div>
                    <div class="col-6">
                        <label class="form-label-dark">Teléfono / WhatsApp</label>
                        <input type="text" id="rapidoTelefono" class="form-control form-control-dark" placeholder="70000000">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="form-label-dark">Tipo de Cliente</label>
                        <select id="rapidoTipo" class="form-select form-select-dark">
                            <option value="Particular">Particular</option>
                            <option value="Taller/Mecánico">Taller / Mecánico Aliado</option>
                            <option value="Empresa/Flota">Empresa / Flota</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label-dark">Descuento Máx. (%)</label>
                        <input type="number" step="0.5" min="0" max="50" id="rapidoDescuento" class="form-control form-control-dark" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-tuerca" onclick="guardarClienteRapido()">
                    <i class="bi bi-save me-1"></i>Guardar y Seleccionar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const catalogoProductos = @json($productos);
    let carritoCot = [];

    function filtrarCatalogoCotizacion() {
        const q = (document.getElementById('buscadorRepuesto').value || '').toLowerCase().trim();
        const tbody = document.getElementById('tablaCatalogoCotizacion');
        tbody.innerHTML = '';

        const filtrados = catalogoProductos.filter(p => {
            if (!q) return true;
            return p.nombre_pieza.toLowerCase().includes(q)
                || p.codigo_oem.toLowerCase().includes(q)
                || p.codigo_interno.toLowerCase().includes(q)
                || (p.codigos_alt && p.codigos_alt.toLowerCase().includes(q))
                || p.marca.toLowerCase().includes(q);
        });

        if (filtrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No se encontraron repuestos coincidentes.</td></tr>';
            return;
        }

        filtrados.forEach(p => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div style="font-family:monospace;font-weight:700;color:#007AFF;font-size:0.8rem;">${p.codigo_oem}</div>
                    <small class="text-muted">${p.codigo_interno}</small>
                </td>
                <td>
                    <div style="font-weight:600;color:#1E293B;font-size:0.83rem;">${p.nombre_pieza}</div>
                    <small class="text-muted">${p.marca} · Ubic: ${p.ubicacion}</small>
                </td>
                <td class="text-center">
                    <span class="${p.stock_actual > 0 ? 'badge-activo' : 'badge-inactivo'}">${p.stock_actual}</span>
                </td>
                <td class="text-end fw-bold" style="font-family:monospace;">Bs. ${p.precio_sugerido.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-tuerca" onclick="agregarAlCarritoCot(${p.id_producto})">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function agregarAlCarritoCot(idProd) {
        const prod = catalogoProductos.find(p => p.id_producto === idProd);
        if (!prod) return;

        const existente = carritoCot.find(i => i.id_producto === idProd);
        if (existente) {
            existente.cantidad += 1;
        } else {
            carritoCot.push({
                id_producto: prod.id_producto,
                codigo_oem: prod.codigo_oem,
                nombre_pieza: prod.nombre_pieza,
                cantidad: 1,
                precio: prod.precio_sugerido
            });
        }
        renderizarCarritoCot();
    }

    function eliminarItemCot(idx) {
        carritoCot.splice(idx, 1);
        renderizarCarritoCot();
    }

    function actualizarItemCot(idx, campo, valor) {
        const val = parseFloat(valor);
        if (campo === 'cantidad') {
            carritoCot[idx].cantidad = Math.max(1, parseInt(valor) || 1);
        } else if (campo === 'precio') {
            carritoCot[idx].precio = Math.max(0.01, val || 0.01);
        }
        renderizarCarritoCot();
    }

    function renderizarCarritoCot() {
        const tbody = document.getElementById('carritoCotizacionBody');
        tbody.innerHTML = '';

        if (carritoCot.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-inbox d-block fs-4 mb-1"></i>
                        Seleccione repuestos del buscador para armar la proforma.
                    </td>
                </tr>`;
            document.getElementById('totalCotizacionTexto').textContent = 'Bs. 0.00';
            document.getElementById('btnGuardarCotizacion').disabled = true;
            return;
        }

        let total = 0;
        carritoCot.forEach((item, idx) => {
            const sub = item.cantidad * item.precio;
            total += sub;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="hidden" name="items[${idx}][id_producto]" value="${item.id_producto}">
                    <div style="font-weight:600;font-size:0.8rem;color:#1E293B;">${item.nombre_pieza}</div>
                    <small style="font-family:monospace;color:#64748B;">OEM: ${item.codigo_oem}</small>
                </td>
                <td>
                    <input type="number" min="1" name="items[${idx}][cantidad]" value="${item.cantidad}"
                           class="form-control form-control-sm form-control-dark text-center"
                           onchange="actualizarItemCot(${idx}, 'cantidad', this.value)">
                </td>
                <td>
                    <input type="number" step="0.01" min="0.01" name="items[${idx}][precio]" value="${item.precio.toFixed(2)}"
                           class="form-control form-control-sm form-control-dark text-end"
                           onchange="actualizarItemCot(${idx}, 'precio', this.value)">
                </td>
                <td class="text-end fw-bold" style="font-family:monospace;font-size:0.82rem;">
                    Bs. ${sub.toFixed(2)}
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm text-danger p-1" onclick="eliminarItemCot(${idx})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('totalCotizacionTexto').textContent = 'Bs. ' + total.toFixed(2);
        document.getElementById('btnGuardarCotizacion').disabled = false;
    }

    function actualizarInfoClienteCot() {
        const sel = document.getElementById('selectClienteCot');
        const opt = sel.options[sel.selectedIndex];
        const info = document.getElementById('badgeInfoClienteCot');
        if (!opt || !opt.value) {
            info.textContent = '';
            return;
        }
        const tipo = opt.getAttribute('data-tipo');
        const desc = parseFloat(opt.getAttribute('data-desc')) || 0;
        info.innerHTML = `Segmento: <strong>${tipo}</strong> · Descuento frecuente autorizado: <strong>${desc}%</strong>`;
    }

    async function guardarClienteRapido() {
        const nombre = document.getElementById('rapidoNombre').value.trim();
        const nit = document.getElementById('rapidoNit').value.trim();
        const telefono = document.getElementById('rapidoTelefono').value.trim();
        const tipo = document.getElementById('rapidoTipo').value;
        const descuento = document.getElementById('rapidoDescuento').value;
        const errBox = document.getElementById('errorClienteRapido');

        errBox.classList.add('d-none');
        if (nombre.length < 3) {
            errBox.textContent = 'Ingrese un nombre o razón social válido (mínimo 3 caracteres).';
            errBox.classList.remove('d-none');
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
                    telefono: telefono,
                    tipo_cliente: tipo,
                    descuento_frecuente: descuento
                })
            });

            const data = await resp.json();
            if (!resp.ok) {
                errBox.textContent = data.error || data.message || 'Error al registrar cliente.';
                errBox.classList.remove('d-none');
                return;
            }

            const sel = document.getElementById('selectClienteCot');
            const opt = document.createElement('option');
            opt.value = data.cliente.id_cliente;
            opt.textContent = `${data.cliente.nombre_razon_social} (${data.cliente.ci_nit || 'S/N'})`;
            opt.setAttribute('data-tipo', data.cliente.tipo_cliente);
            opt.setAttribute('data-desc', data.cliente.descuento_frecuente);
            opt.selected = true;
            sel.appendChild(opt);
            actualizarInfoClienteCot();

            bootstrap.Modal.getInstance(document.getElementById('modalClienteRapido')).hide();
        } catch (e) {
            errBox.textContent = 'Error de conexión al guardar cliente.';
            errBox.classList.remove('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        filtrarCatalogoCotizacion();
        actualizarInfoClienteCot();
    });
</script>
@endpush
