<!-- ── Side Drawer Deslizante del Carrito (420px desktop / 100% móvil) ── -->
<div id="czCartBackdrop" class="cz-drawer-backdrop" onclick="cerrarDrawerCarritoWeb()" aria-hidden="true"></div>

<aside id="czCartDrawer" class="cz-drawer" role="dialog" aria-modal="true" aria-labelledby="czDrawerTitle" aria-hidden="true">
    <div class="cz-drawer-header">
        <div>
            <h2 id="czDrawerTitle" class="m-0 fw-bold" style="font-size: 1.08rem;">
                <i class="bi bi-bag-check-fill text-success me-2"></i>Tu Carrito de Repuestos
            </h2>
            <div style="font-size: 0.74rem; color: var(--cz-slate-400);">
                Stock reservado al confirmar pedido o proforma
            </div>
        </div>
        <button type="button" class="cz-drawer-close" onclick="cerrarDrawerCarritoWeb()" aria-label="Cerrar carrito">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <form id="formCarritoWeb"
          action="{{ route('mi-compra.store') }}"
          method="POST"
          class="d-flex flex-column flex-grow-1 overflow-hidden"
          onsubmit="localStorage.removeItem('multipartes_carrito_web');">
        @csrf
        <div id="inputsCarritoWeb"></div>

        <div class="cz-drawer-body">
            <!-- Selector de modo: Compra Directa vs Solicitar Proforma Formal -->
            <div class="cz-mode-switch" role="tablist" aria-label="Tipo de operación">
                <button type="button"
                        id="btnModoCompra"
                        class="cz-mode-btn is-active"
                        onclick="cambiarModoCarritoWeb('compra')">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Compra Directa
                </button>
                <button type="button"
                        id="btnModoProforma"
                        class="cz-mode-btn"
                        onclick="cambiarModoCarritoWeb('proforma')">
                    <i class="bi bi-file-earmark-text me-1"></i>Solicitar Proforma
                </button>
            </div>

            <!-- Lista dinámica de ítems -->
            <div id="tbodyCarritoWeb"></div>

            <!-- Campos de checkout según modo seleccionado -->
            @auth
                <div id="czCheckoutFields" class="mt-3 pt-3 border-top" style="border-color: var(--cz-border) !important;">
                    <div class="mb-2">
                        <label for="czCiNit" class="form-label mb-1 fw-bold" style="font-size: 0.74rem; color: var(--cz-muted); text-transform: uppercase;">
                            CI / NIT para comprobante
                        </label>
                        <input type="text" id="czCiNit" name="ci_nit" class="form-control" style="height: 44px; border-radius: 10px;" placeholder="Ej. 5348912 (Opcional)">
                    </div>

                    <!-- Campos exclusivos de Compra Directa (POST /mi-compra) -->
                    <div id="czCamposCompra">
                        <div class="mb-2">
                            <label for="czMetodoPago" class="form-label mb-1 fw-bold" style="font-size: 0.74rem; color: var(--cz-muted); text-transform: uppercase;">
                                Método de Pago Digital
                            </label>
                            <select id="czMetodoPago" name="metodo_pago" class="form-select" style="height: 44px; border-radius: 10px;" required>
                                <option value="Transferencia/QR">Transferencia / QR</option>
                                <option value="Tarjeta Débito/Crédito">Tarjeta Débito/Crédito</option>
                            </select>
                        </div>
                        <div class="mb-1">
                            <label for="czNroRef" class="form-label mb-1 fw-bold" style="font-size: 0.74rem; color: var(--cz-muted); text-transform: uppercase;">
                                N° Referencia o Nota de Retiro
                            </label>
                            <input type="text" id="czNroRef" name="nro_referencia" class="form-control" style="height: 44px; border-radius: 10px;" placeholder="Ref. QR o retiro en tienda">
                        </div>
                    </div>

                    <!-- Campos exclusivos de Proforma Formal (POST /mis-cotizaciones) -->
                    <div id="czCamposProforma" style="display: none;">
                        <div class="mb-1">
                            <label for="czDiasVigencia" class="form-label mb-1 fw-bold" style="font-size: 0.74rem; color: var(--cz-muted); text-transform: uppercase;">
                                Vigencia de la Proforma
                            </label>
                            <select id="czDiasVigencia" name="dias_vigencia" class="form-select" style="height: 44px; border-radius: 10px;" disabled>
                                <option value="7">7 días calendario</option>
                                <option value="15">15 días calendario (Talleres/Flotas)</option>
                            </select>
                        </div>
                    </div>
                </div>
            @endauth
        </div>

        <!-- Pie del Drawer: Total + CTA + Trust Badges -->
        <div class="cz-drawer-footer">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold" style="color: var(--cz-body); font-size: 0.92rem;">Subtotal estimado</span>
                <span id="totalCarritoWeb" class="font-mono-cz fw-bold" style="font-size: 1.4rem; color: var(--cz-ink);">Bs. 0.00</span>
            </div>

            @auth
                <button type="submit" id="btnConfirmarCompraWeb" class="cz-btn-emerald w-100">
                    <i class="bi bi-check2-circle"></i>
                    <span id="lblBtnConfirmarWeb">Confirmar Pedido y Generar PDF</span>
                </button>
            @else
                <div class="d-grid gap-2">
                    <a href="{{ route('login') }}" class="cz-btn-emerald w-100">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Ingresar para Confirmar</span>
                    </a>
                    <a href="{{ route('register') }}" class="cz-btn-outline w-100">
                        <i class="bi bi-person-plus"></i>
                        <span>Crear Cuenta de Cliente</span>
                    </a>
                </div>
            @endauth

            <!-- Trust Badges debajo del botón de confirmar -->
            <div class="cz-trust-badges">
                <div class="cz-trust-badge">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>Stock verificado en tienda</span>
                </div>
                <div class="cz-trust-badge">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Retiro rápido Av. Cristóbal de Mendoza #450</span>
                </div>
                <div class="cz-trust-badge">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>Pago Efectivo / QR</span>
                </div>
            </div>
        </div>
    </form>
</aside>

<!-- ── Sticky Bottom Bar en Móvil cuando hay productos en el carrito ── -->
<div id="czStickyCartBar" class="cz-sticky-cart-bar" role="region" aria-label="Resumen de carrito móvil">
    <div class="d-flex align-items-center gap-3">
        <span id="czStickyCartCount" class="cz-cart-badge">0</span>
        <div>
            <div style="font-size: 0.72rem; color: var(--cz-slate-400);">Total en carrito</div>
            <div id="czStickyCartTotal" class="font-mono-cz fw-bold text-white" style="font-size: 1rem;">Bs. 0.00</div>
        </div>
    </div>
    <button type="button" class="cz-btn-emerald" style="min-height: 44px; padding: 0 18px;" onclick="abrirModalCarritoWeb()">
        <i class="bi bi-bag-check"></i>
        <span>Ver carrito</span>
    </button>
</div>

<!-- ── Modal de Compatibilidad Vehicular (Capa Zero) ── -->
<div class="modal fade" id="modalCompatibilidad" tabindex="-1" aria-labelledby="modalCompatibilidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content cz-modal-content">
            <div class="modal-header cz-modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <span class="cz-brand-mark" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-patch-check-fill"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-white m-0" id="modalCompatibilidadLabel" style="font-size: 1.05rem;">
                            Ficha Técnica y Compatibilidad Vehicular
                        </h5>
                        <span style="font-size: 0.75rem; color: var(--cz-slate-400);">Verifica la aplicación exacta antes de tu compra</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4" style="background-color: #FFFFFF;">
                <div class="p-3 mb-4 rounded-3" style="background: var(--cz-canvas); border: 1px solid var(--cz-border);">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-8">
                            <div class="d-flex align-items-center gap-3">
                                <img id="modalProductoFoto"
                                     src="{{ asset('images/repuestos/motor_transmision.jpg') }}"
                                     alt="Repuesto seleccionado"
                                     class="cz-modal-thumb">
                                <div class="min-w-0">
                                    <div class="fw-bold text-uppercase mb-1" id="modalCategoria" style="font-size: 0.72rem; letter-spacing: 0.06em; color: var(--cz-emerald-deep);"></div>
                                    <h4 class="fw-bold mb-2" id="modalNombrePieza" style="font-size: 1.15rem; color: var(--cz-ink);"></h4>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="cz-oem-pill" id="modalCodigoOem"></span>
                                        <span class="cz-oem-pill" id="modalCodigoInterno"></span>
                                        <span class="badge bg-white text-dark border" id="modalMarcaFabricante"></span>
                                        <span class="badge bg-white text-secondary border" id="modalProcedencia"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 text-md-end">
                            <span class="text-uppercase d-block" style="font-size: 0.68rem; font-weight: 700; color: var(--cz-muted);">Precio en Tienda</span>
                            <div class="font-mono-cz fw-bold" id="modalPrecio" style="font-size: 1.5rem; color: var(--cz-ink);"></div>
                            <div class="fw-semibold mt-1" style="font-size: 0.78rem; color: var(--cz-emerald-deep);" id="modalStock">
                                <i class="bi bi-check-circle-fill me-1"></i>En Stock Santa Cruz
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="font-size: 0.92rem; color: var(--cz-ink);">
                    <i class="bi bi-car-front-fill text-success"></i>
                    <span>Modelos Compatibles Verificados</span>
                </h6>

                <div class="table-responsive border rounded-3" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>MARCA Y MODELO</th>
                                <th>AÑOS</th>
                                <th>MOTOR</th>
                                <th>COMBUSTIBLE</th>
                                <th>POSICIÓN / NOTAS</th>
                            </tr>
                        </thead>
                        <tbody id="modalVehiculosList"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer d-flex justify-content-between flex-wrap gap-2 px-4 py-3" style="background-color: var(--cz-canvas); border-top: 1px solid var(--cz-border);">
                <button type="button" class="cz-btn-outline" data-bs-dismiss="modal">Cerrar</button>
                <a id="modalBtnWhatsApp" href="#" target="_blank" class="cz-btn-emerald">
                    <i class="bi bi-whatsapp"></i>
                    <span>Consultar Repuesto por WhatsApp</span>
                </a>
            </div>
        </div>
    </div>
</div>

@php
    $czRoutesCart = [
        'compra'   => route('mi-compra.store'),
        'proforma' => route('mis-cotizaciones.store'),
        'home'     => route('home'),
    ];
@endphp

<script>
    const CZ_ROUTES = {{ \Illuminate\Support\Js::from($czRoutesCart) }};
    const CZ_CATALOGO_AUTOCOMPLETE = {{ \Illuminate\Support\Js::from($autocompleteCatalog ?? []) }};
    const CZ_FALLBACK_IMG = {{ \Illuminate\Support\Js::from(asset('images/repuestos/motor_transmision.jpg')) }};
    const STORAGE_KEY_CART = 'multipartes_carrito_web';
    let czModoActual = 'compra';

    // ── 1. Sticky Navbar Shrink on Scroll (80px -> 64px) ──
    (function initNavbarScroll() {
        const nav = document.getElementById('czNavbar');
        if (!nav) return;
        const onScroll = () => {
            if (window.scrollY > 24) nav.classList.add('is-scrolled');
            else nav.classList.remove('is-scrolled');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();

    // ── 2. Menú Móvil Overlay Completo ──
    function toggleMobileOverlay(open) {
        const overlay = document.getElementById('czMobileOverlay');
        const btn = document.getElementById('czBtnMobileMenu');
        if (!overlay) return;
        overlay.classList.toggle('is-open', open);
        overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    // ── 3. Hero Autocomplete Search Instantáneo ──
    (function initHeroAutocomplete() {
        const input = document.getElementById('czHeroSearchInput');
        const panel = document.getElementById('czAutocompletePanel');
        if (!input || !panel || !Array.isArray(CZ_CATALOGO_AUTOCOMPLETE)) return;

        function escapar(str) {
            return String(str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        }

        function renderSugerencias(termino) {
            const q = termino.trim().toLowerCase();
            if (q.length < 2) {
                panel.classList.remove('is-open');
                panel.innerHTML = '';
                return;
            }

            const matches = CZ_CATALOGO_AUTOCOMPLETE.filter(it =>
                (it.nombre || '').toLowerCase().includes(q) ||
                (it.oem || '').toLowerCase().includes(q) ||
                (it.sku || '').toLowerCase().includes(q) ||
                (it.marca || '').toLowerCase().includes(q) ||
                (it.vehiculos || '').toLowerCase().includes(q)
            ).slice(0, 6);

            if (matches.length === 0) {
                panel.innerHTML = `<div class="p-3 text-muted small"><i class="bi bi-info-circle me-1"></i>Presiona Buscar para revisar coincidencias o consúltanos por WhatsApp.</div>`;
                panel.classList.add('is-open');
                return;
            }

            panel.innerHTML = matches.map(it => {
                const url = `${CZ_ROUTES.home}?buscar=${encodeURIComponent(it.oem || it.nombre)}#catalogo`;
                const stockColor = it.stock > 0 ? '#047857' : '#64748B';
                const stockTxt = it.stock > 0 ? `Stock: ${it.stock}` : 'Agotado';
                const imgSrc = escapar(it.imagen || it.fallback || CZ_FALLBACK_IMG);
                const imgFallback = escapar(it.fallback || CZ_FALLBACK_IMG);
                return `
                    <a href="${url}" class="cz-ac-item" role="option">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <img src="${imgSrc}"
                                 alt="${escapar(it.nombre)}"
                                 class="cz-ac-thumb"
                                 onerror="this.onerror=null;this.src='${imgFallback}';">
                            <div class="min-w-0">
                                <div class="cz-ac-title text-truncate">${escapar(it.nombre)}</div>
                                <div class="cz-ac-meta">
                                    <span class="font-mono-cz fw-semibold text-dark">OEM ${escapar(it.oem)}</span>
                                    <span>&middot;</span>
                                    <span>${escapar(it.marca)}</span>
                                    ${it.vehiculos ? `<span>&middot;</span><span class="text-truncate" style="max-width:220px;">${escapar(it.vehiculos)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="cz-ac-price">Bs. ${Number(it.precio || 0).toFixed(2)}</div>
                            <div style="font-size:0.72rem; font-weight:700; color:${stockColor};">${stockTxt}</div>
                        </div>
                    </a>
                `;
            }).join('');
            panel.classList.add('is-open');
        }

        input.addEventListener('input', e => renderSugerencias(e.target.value));
        input.addEventListener('focus', e => { if (e.target.value.trim().length >= 2) renderSugerencias(e.target.value); });
        document.addEventListener('click', e => {
            if (!panel.contains(e.target) && e.target !== input) {
                panel.classList.remove('is-open');
            }
        });
    })();

    // ── 4. Count-up Stats Animados al Entrar en Viewport ──
    (function initCountUp() {
        const els = document.querySelectorAll('.cz-countup');
        if (!els.length) return;

        function animate(el) {
            const target = parseInt(el.getAttribute('data-target') || '0', 10);
            if (isNaN(target) || target <= 0) {
                el.textContent = '0';
                return;
            }
            const duration = 900;
            const start = performance.now();
            function tick(now) {
                const p = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * eased).toLocaleString('es-BO');
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        if ('IntersectionObserver' in window) {
            const obs = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animate(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.3 });
            els.forEach(el => obs.observe(el));
        } else {
            els.forEach(animate);
        }
    })();

    // ── 5. FAQ Accordion Suave ──
    function toggleFaqItem(btn) {
        const item = btn.closest('.cz-faq-item');
        if (!item) return;
        const willOpen = !item.classList.contains('is-open');
        document.querySelectorAll('.cz-faq-item.is-open').forEach(el => {
            el.classList.remove('is-open');
            const t = el.querySelector('.cz-faq-trigger');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
        if (willOpen) {
            item.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    // ── 6. Modal de Compatibilidad Técnica (intacto) ──
    function verDetalleCompatibilidad(producto) {
        const modalFoto = document.getElementById('modalProductoFoto');
        if (modalFoto) {
            const fallbackUrl = producto.foto_fallback_url || CZ_FALLBACK_IMG;
            modalFoto.onerror = function () { this.onerror = null; this.src = fallbackUrl; };
            modalFoto.src = producto.foto_url || fallbackUrl;
            modalFoto.alt = producto.nombre_pieza || 'Repuesto';
        }
        document.getElementById('modalNombrePieza').textContent = producto.nombre_pieza;
        document.getElementById('modalCategoria').textContent = producto.categoria ? producto.categoria.nombre : 'REPUESTO';
        document.getElementById('modalCodigoOem').innerHTML = '<i class="bi bi-upc-scan"></i> OEM: ' + producto.codigo_oem;
        document.getElementById('modalCodigoInterno').textContent = 'SKU: ' + producto.codigo_interno;
        document.getElementById('modalMarcaFabricante').textContent = 'Fabricante: ' + (producto.marca_fabricante || 'Original');
        document.getElementById('modalProcedencia').textContent = 'Procedencia: ' + (producto.procedencia || 'Importación');

        const precioFormatted = parseFloat(producto.precio_venta_sugerido).toFixed(2);
        document.getElementById('modalPrecio').textContent = 'Bs. ' + precioFormatted;
        document.getElementById('modalStock').innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>' +
            producto.stock_actual + ' ' + (producto.unidad_medida || 'unidades') + ' disponibles en Santa Cruz';

        const tbody = document.getElementById('modalVehiculosList');
        tbody.innerHTML = '';

        if (producto.vehiculos && producto.vehiculos.length > 0) {
            producto.vehiculos.forEach(v => {
                const tr = document.createElement('tr');
                const notas = (v.pivot && v.pivot.notas_posicion) ? v.pivot.notas_posicion : 'Compatibilidad estándar según OEM';
                tr.innerHTML = `
                    <td class="fw-bold text-dark">
                        <span class="badge bg-dark text-white me-1">${v.marca}</span> ${v.modelo}
                    </td>
                    <td class="font-mono-cz">${v.anio_inicio} - ${v.anio_fin}</td>
                    <td><code class="font-mono-cz">${v.motor_cilindrada || 'Estándar'}</code></td>
                    <td><span class="badge bg-light text-secondary border">${v.combustible || 'Gasolina'}</span></td>
                    <td class="text-muted"><small>${notas}</small></td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Aplicación universal o compatibilidad directa a verificar con número de chasis.</td></tr>';
        }

        const mensajeWa = `Hola Multipartes El Tuerca, deseo consultar disponibilidad y precio para el repuesto: ${producto.nombre_pieza} (OEM: ${producto.codigo_oem}, Fabricante: ${producto.marca_fabricante || ''}). ¿Lo tienen para entrega inmediata en Santa Cruz?`;
        document.getElementById('modalBtnWhatsApp').setAttribute('href', 'https://wa.me/59173397587?text=' + encodeURIComponent(mensajeWa));

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCompatibilidad')).show();
    }

    // ── 7. Carrito Web + Side Drawer + Sticky Bottom Bar ──
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
        const totalQty = items.reduce((s, x) => s + (parseInt(x.cantidad, 10) || 0), 0);
        const totalBs = items.reduce((s, x) => s + ((parseInt(x.cantidad, 10) || 0) * (parseFloat(x.precio) || 0)), 0);

        const badge = document.getElementById('badgeCarritoWeb');
        if (badge) badge.textContent = totalQty;

        const stickyBar = document.getElementById('czStickyCartBar');
        const stickyCount = document.getElementById('czStickyCartCount');
        const stickyTotal = document.getElementById('czStickyCartTotal');
        if (stickyBar && stickyCount && stickyTotal) {
            stickyCount.textContent = totalQty;
            stickyTotal.textContent = 'Bs. ' + totalBs.toFixed(2);
            stickyBar.classList.toggle('is-visible', totalQty > 0);
        }
    }

    function agregarAlCarritoWeb(prod) {
        const items = obtenerCarritoWeb();
        const ex = items.find(x => x.id_producto === prod.id_producto);
        if (ex) {
            if (ex.cantidad < prod.stock) ex.cantidad += 1;
            if (!ex.imagen && prod.imagen) ex.imagen = prod.imagen;
            if (!ex.fallback && prod.fallback) ex.fallback = prod.fallback;
        } else {
            items.push({
                id_producto: prod.id_producto,
                nombre_pieza: prod.nombre_pieza,
                codigo_oem: prod.codigo_oem,
                precio: parseFloat(prod.precio),
                stock: parseInt(prod.stock, 10),
                imagen: prod.imagen || prod.fallback || CZ_FALLBACK_IMG,
                fallback: prod.fallback || CZ_FALLBACK_IMG,
                cantidad: 1
            });
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

    function cambiarModoCarritoWeb(modo) {
        czModoActual = modo === 'proforma' ? 'proforma' : 'compra';
        const form = document.getElementById('formCarritoWeb');
        const btnCompra = document.getElementById('btnModoCompra');
        const btnProforma = document.getElementById('btnModoProforma');
        const camposCompra = document.getElementById('czCamposCompra');
        const camposProforma = document.getElementById('czCamposProforma');
        const selMetodo = document.getElementById('czMetodoPago');
        const selDias = document.getElementById('czDiasVigencia');
        const lblBtn = document.getElementById('lblBtnConfirmarWeb');

        if (btnCompra) btnCompra.classList.toggle('is-active', czModoActual === 'compra');
        if (btnProforma) btnProforma.classList.toggle('is-active', czModoActual === 'proforma');

        if (form) {
            form.setAttribute('action', czModoActual === 'proforma' ? CZ_ROUTES.proforma : CZ_ROUTES.compra);
        }
        if (camposCompra) camposCompra.style.display = czModoActual === 'compra' ? 'block' : 'none';
        if (camposProforma) camposProforma.style.display = czModoActual === 'proforma' ? 'block' : 'none';
        if (selMetodo) selMetodo.disabled = (czModoActual !== 'compra');
        if (selDias) selDias.disabled = (czModoActual !== 'proforma');
        if (lblBtn) {
            lblBtn.textContent = czModoActual === 'proforma'
                ? 'Generar Proforma Formal PDF'
                : 'Confirmar Pedido y Generar PDF';
        }
    }

    function abrirModalCarritoWeb() {
        renderModalCarritoWeb();
        const drawer = document.getElementById('czCartDrawer');
        const backdrop = document.getElementById('czCartBackdrop');
        if (drawer && backdrop) {
            drawer.classList.add('is-open');
            backdrop.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
    }

    function cerrarDrawerCarritoWeb() {
        const drawer = document.getElementById('czCartDrawer');
        const backdrop = document.getElementById('czCartBackdrop');
        if (drawer && backdrop) {
            drawer.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    }

    function renderModalCarritoWeb() {
        const items = obtenerCarritoWeb();
        const container = document.getElementById('tbodyCarritoWeb');
        const inputsContainer = document.getElementById('inputsCarritoWeb');
        const totalEl = document.getElementById('totalCarritoWeb');
        const btnConfirmar = document.getElementById('btnConfirmarCompraWeb');
        if (!container) return;

        container.innerHTML = '';
        if (inputsContainer) inputsContainer.innerHTML = '';

        if (items.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5 px-3 rounded-3" style="background-color: var(--cz-canvas); border: 1px dashed var(--cz-border-strong);">
                    <i class="bi bi-bag fs-2 text-muted d-block mb-2"></i>
                    <div class="fw-bold text-dark mb-1" style="font-size: 0.94rem;">Tu carrito está vacío</div>
                    <p class="text-muted small mb-0">Agrega repuestos desde el catálogo para comprar directo o generar una proforma.</p>
                </div>
            `;
            if (totalEl) totalEl.textContent = 'Bs. 0.00';
            if (btnConfirmar) btnConfirmar.disabled = true;
            return;
        }

        let total = 0;
        items.forEach((it, idx) => {
            const sub = it.cantidad * it.precio;
            total += sub;

            const card = document.createElement('div');
            card.className = 'cz-drawer-item';
            const imgUrl = it.imagen || it.fallback || CZ_FALLBACK_IMG;
            const imgFallback = it.fallback || CZ_FALLBACK_IMG;
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <img src="${imgUrl}"
                             alt="${it.nombre_pieza}"
                             class="cz-drawer-thumb"
                             onerror="this.onerror=null;this.src='${imgFallback}';">
                        <div class="min-w-0">
                            <div class="fw-bold text-dark" style="font-size: 0.88rem; line-height: 1.3;">${it.nombre_pieza}</div>
                            <div class="font-mono-cz text-muted" style="font-size: 0.72rem;">OEM ${it.codigo_oem} &middot; Bs. ${it.precio.toFixed(2)}</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm p-1 text-danger border-0 flex-shrink-0" onclick="cambiarCantCarritoWeb(${idx}, -999)" aria-label="Quitar repuesto">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="cz-qty-group">
                        <button type="button" class="cz-qty-btn" onclick="cambiarCantCarritoWeb(${idx}, -1)" aria-label="Disminuir cantidad">
                            <i class="bi bi-dash"></i>
                        </button>
                        <span class="cz-qty-val">${it.cantidad}</span>
                        <button type="button" class="cz-qty-btn" onclick="cambiarCantCarritoWeb(${idx}, 1)" aria-label="Aumentar cantidad">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <span class="font-mono-cz fw-bold text-dark" style="font-size: 0.95rem;">Bs. ${sub.toFixed(2)}</span>
                </div>
            `;
            container.appendChild(card);

            if (inputsContainer) {
                inputsContainer.innerHTML +=
                    `<input type="hidden" name="items[${idx}][id_producto]" value="${it.id_producto}">` +
                    `<input type="hidden" name="items[${idx}][cantidad]" value="${it.cantidad}">`;
            }
        });

        if (totalEl) totalEl.textContent = 'Bs. ' + total.toFixed(2);
        if (btnConfirmar) btnConfirmar.disabled = false;
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            cerrarDrawerCarritoWeb();
            toggleMobileOverlay(false);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        actualizarBadgeCarritoWeb();
    });
</script>
