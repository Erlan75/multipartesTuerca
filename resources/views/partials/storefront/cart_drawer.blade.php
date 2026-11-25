    <!-- ── Modal de Compatibilidad Vehicular (macOS Light Clean) ── -->
    <div class="modal fade" id="modalCompatibilidad" tabindex="-1" aria-labelledby="modalCompatibilidadLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-mac">
                <div class="modal-header modal-header-mac d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(0, 122, 255, 0.1); color: #007AFF; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-patch-check-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark m-0" id="modalCompatibilidadLabel" style="font-size: 1.05rem;">
                                Ficha Técnica y Compatibilidad Vehicular
                            </h5>
                            <span class="text-muted" style="font-size: 0.75rem;">Verifica la aplicación exacta antes de tu compra</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body modal-body-mac">
                    <!-- Ficha Rápida del Repuesto -->
                    <div class="p-3 mb-4 rounded-3" style="background: #F8FAFC; border: 1px solid var(--mac-border);">
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <div class="text-primary fw-bold text-uppercase" id="modalCategoria" style="font-size: 0.72rem; letter-spacing: 0.05em;"></div>
                                <h4 class="fw-bold text-dark mb-2" id="modalNombrePieza" style="font-size: 1.25rem;"></h4>
                                <div class="d-flex gap-2 flex-wrap mb-2">
                                    <span class="code-pill code-pill-oem" id="modalCodigoOem"></span>
                                    <span class="code-pill" id="modalCodigoInterno"></span>
                                    <span class="badge bg-white text-dark border" id="modalMarcaFabricante"></span>
                                    <span class="badge bg-white text-secondary border" id="modalProcedencia"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-4 text-md-end d-flex flex-column justify-content-center">
                                <span class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 600;">Precio de Venta</span>
                                <div class="fw-bold text-dark" id="modalPrecio" style="font-size: 1.5rem; letter-spacing: -0.02em;"></div>
                                <div class="text-success fw-semibold mt-1" style="font-size: 0.78rem;" id="modalStock">
                                    <i class="bi bi-check-circle-fill me-1"></i> En Stock Santa Cruz
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Vehículos Compatibles -->
                    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 0.92rem;">
                        <i class="bi bi-car-front-fill text-primary"></i>
                        <span>Modelos Compatibles Verificados:</span>
                    </h6>

                    <div class="table-responsive border rounded-3" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light text-muted" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>MARCA Y MODELO</th>
                                    <th>AÑOS</th>
                                    <th>MOTOR / CILINDRADA</th>
                                    <th>COMBUSTIBLE</th>
                                    <th>NOTAS DE MONTAJE / POSICIÓN</th>
                                </tr>
                            </thead>
                            <tbody id="modalVehiculosList">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer modal-footer-mac d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">
                        Cerrar
                    </button>
                    <a id="modalBtnWhatsApp" href="#" target="_blank" class="btn btn-success" style="border-radius: 10px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.25);">
                        <i class="bi bi-whatsapp"></i>
                        <span>Consultar este Repuesto por WhatsApp</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal Carrito y Compra Directa Web ── -->
    <div class="modal fade" id="modalCarritoWeb" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-mac">
                <div class="modal-header modal-header-mac">
                    <h5 class="modal-title fw-bold text-dark m-0">
                        <i class="bi bi-cart3 text-primary me-2"></i>Mi Carrito de Repuestos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form action="{{ route('mi-compra.store') }}" method="POST" onsubmit="localStorage.removeItem('multipartes_carrito_web');">
                    @csrf
                    <div id="inputsCarritoWeb"></div>
                    <div class="modal-body modal-body-mac">
                        <div class="table-responsive border rounded-3 mb-3">
                            <table class="table align-middle mb-0" style="font-size:0.86rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Repuesto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyCarritoWeb"></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background:#EFF6FF;border:1px solid #BFDBFE;">
                            <span class="fw-bold text-dark">Total a Pagar:</span>
                            <span id="totalCarritoWeb" class="fw-bold fs-4 text-primary font-monospace">Bs. 0.00</span>
                        </div>

                        @auth
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">CI / NIT Comprobante</label>
                                <input type="text" name="ci_nit" class="form-control form-control-sm" placeholder="CI o NIT (Opcional)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Método de Pago</label>
                                <select name="metodo_pago" class="form-select form-select-sm" required>
                                    <option value="Transferencia/QR">Transferencia / QR</option>
                                    <option value="Tarjeta Débito/Crédito">Tarjeta Débito/Crédito</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">N° Referencia / Nota</label>
                                <input type="text" name="nro_referencia" class="form-control form-control-sm" placeholder="Ref. QR o comprobante">
                            </div>
                        </div>
                        @endauth
                    </div>
                    <div class="modal-footer modal-footer-mac d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:10px;">Seguir Viendo</button>
                        @auth
                            <button type="submit" id="btnConfirmarCompraWeb" class="btn btn-success fw-semibold" style="border-radius:10px;">
                                <i class="bi bi-check2-circle me-1"></i>Confirmar Pedido y Generar PDF
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="btn-mac-primary text-decoration-none">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar Sesión para Comprar
                            </a>
                        @endauth
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        /**
         * Abre y llena el modal interactivo de compatibilidad técnica
         */
        function verDetalleCompatibilidad(producto) {
            document.getElementById('modalNombrePieza').textContent = producto.nombre_pieza;
            document.getElementById('modalCategoria').textContent = producto.categoria ? producto.categoria.nombre : 'REPUESTO';
            document.getElementById('modalCodigoOem').innerHTML = '<i class="bi bi-upc-scan"></i> OEM: ' + producto.codigo_oem;
            document.getElementById('modalCodigoInterno').textContent = 'SKU: ' + producto.codigo_interno;
            document.getElementById('modalMarcaFabricante').textContent = 'Fabricante: ' + (producto.marca_fabricante || 'Original');
            document.getElementById('modalProcedencia').textContent = 'Procedencia: ' + (producto.procedencia || 'Importación');
            
            const precioFormatted = parseFloat(producto.precio_venta_sugerido).toFixed(2);
            document.getElementById('modalPrecio').textContent = 'Bs. ' + precioFormatted;
            
            document.getElementById('modalStock').innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + 
                producto.stock_actual + ' ' + (producto.unidad_medida || 'unidades') + ' disponibles en Santa Cruz';

            // Tabla de vehículos compatibles
            const tbody = document.getElementById('modalVehiculosList');
            tbody.innerHTML = '';

            if (producto.vehiculos && producto.vehiculos.length > 0) {
                producto.vehiculos.forEach(v => {
                    const tr = document.createElement('tr');
                    const notas = (v.pivot && v.pivot.notas_posicion) ? v.pivot.notas_posicion : 'Compatibilidad estándar según OEM';
                    tr.innerHTML = `
                        <td class="fw-bold text-dark">
                            <span class="badge bg-primary-subtle text-primary me-1">${v.marca}</span> ${v.modelo}
                        </td>
                        <td>${v.anio_inicio} - ${v.anio_fin}</td>
                        <td><code>${v.motor_cilindrada || 'Estándar'}</code></td>
                        <td><span class="badge bg-light text-secondary">${v.combustible || 'Gasolina'}</span></td>
                        <td class="text-muted"><small>${notas}</small></td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="5" class="text-center text-muted py-3">Aplicación universal o compatibilidad directa a verificar con número de chasis.</td>';
                tbody.appendChild(tr);
            }

            // Enlace dinámico de WhatsApp con mensaje precargado
            const mensajeWa = `Hola Multipartes El Tuerca, deseo consultar disponibilidad y precio para el repuesto: ${producto.nombre_pieza} (OEM: ${producto.codigo_oem}, Fabricante: ${producto.marca_fabricante || ''}). ¿Lo tienen para entrega inmediata en Santa Cruz?`;
            const waUrl = 'https://wa.me/59170012345?text=' + encodeURIComponent(mensajeWa);
            document.getElementById('modalBtnWhatsApp').setAttribute('href', waUrl);

            // Mostrar el modal
            const modalEl = document.getElementById('modalCompatibilidad');
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }

        // ── Carrito Web Directo + Compra Online ──
        const STORAGE_KEY_CART = 'multipartes_carrito_web';

        function obtenerCarritoWeb() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY_CART) || '[]');
            } catch (e) {
                return [];
            }
        }

        function guardarCarritoWeb(items) {
            localStorage.setItem(STORAGE_KEY_CART, JSON.stringify(items));
            actualizarBadgeCarritoWeb();
        }

        function actualizarBadgeCarritoWeb() {
            const items = obtenerCarritoWeb();
            const totalQty = items.reduce((s, x) => s + (parseInt(x.cantidad) || 0), 0);
            const badge = document.getElementById('badgeCarritoWeb');
            if (badge) badge.textContent = totalQty;
        }

        function agregarAlCarritoWeb(prod) {
            const items = obtenerCarritoWeb();
            const ex = items.find(x => x.id_producto === prod.id_producto);
            if (ex) {
                if (ex.cantidad < prod.stock) ex.cantidad += 1;
            } else {
                items.push({ id_producto: prod.id_producto, nombre_pieza: prod.nombre_pieza, codigo_oem: prod.codigo_oem, precio: parseFloat(prod.precio), stock: parseInt(prod.stock), cantidad: 1 });
            }
            guardarCarritoWeb(items);
            abrirModalCarritoWeb();
        }

        function cambiarCantCarritoWeb(idx, delta) {
            const items = obtenerCarritoWeb();
            if (!items[idx]) return;
            const nueva = items[idx].cantidad + delta;
            if (nueva <= 0) items.splice(idx, 1);
            else items[idx].cantidad = Math.min(items[idx].stock || 999, nueva);
            guardarCarritoWeb(items);
            renderModalCarritoWeb();
        }

        function abrirModalCarritoWeb() {
            renderModalCarritoWeb();
            const modalEl = document.getElementById('modalCarritoWeb');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function renderModalCarritoWeb() {
            const items = obtenerCarritoWeb();
            const tbody = document.getElementById('tbodyCarritoWeb');
            const inputsContainer = document.getElementById('inputsCarritoWeb');
            const totalEl = document.getElementById('totalCarritoWeb');
            const btnConfirmar = document.getElementById('btnConfirmarCompraWeb');
            tbody.innerHTML = '';
            if (inputsContainer) inputsContainer.innerHTML = '';

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tu carrito está vacío. Agrega repuestos desde el catálogo.</td></tr>';
                totalEl.textContent = 'Bs. 0.00';
                if (btnConfirmar) btnConfirmar.disabled = true;
                return;
            }

            let total = 0;
            items.forEach((it, idx) => {
                const sub = it.cantidad * it.precio;
                total += sub;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="fw-semibold text-dark">${it.nombre_pieza}</div>
                        <small class="text-muted">OEM: ${it.codigo_oem} · Bs. ${it.precio.toFixed(2)}</small>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="cambiarCantCarritoWeb(${idx}, -1)">-</button>
                            <span class="btn btn-light disabled fw-bold">${it.cantidad}</span>
                            <button type="button" class="btn btn-outline-secondary" onclick="cambiarCantCarritoWeb(${idx}, 1)">+</button>
                        </div>
                    </td>
                    <td class="text-end fw-bold font-monospace">Bs. ${sub.toFixed(2)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm text-danger" onclick="cambiarCantCarritoWeb(${idx}, -999)"><i class="bi bi-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);

                if (inputsContainer) {
                    inputsContainer.innerHTML += `<input type="hidden" name="items[${idx}][id_producto]" value="${it.id_producto}">`
                        + `<input type="hidden" name="items[${idx}][cantidad]" value="${it.cantidad}">`;
                }
            });

            totalEl.textContent = 'Bs. ' + total.toFixed(2);
            if (btnConfirmar) btnConfirmar.disabled = false;
        }

        document.addEventListener('DOMContentLoaded', actualizarBadgeCarritoWeb);
    </script>
