<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cotizaciones — Multipartes El Tuerca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #F5F7FB; color: #334155; }
        .navbar-clean { background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid #E2E8F0; padding: 14px 0; }
        .card-clean { background: #fff; border: 1px solid #E2E8F0; border-radius: 16px; box-shadow: 0 2px 8px rgba(15,23,42,0.04); }
        .btn-mac { background: linear-gradient(135deg, #007AFF, #0055D4); color: #fff; border: none; border-radius: 10px; font-weight: 600; padding: 0.5rem 1.15rem; font-size: 0.86rem; }
        .btn-mac:hover { color: #fff; opacity: 0.95; }
    </style>
</head>
<body>
    <header class="navbar-clean sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <div style="width:40px;height:40px;border-radius:10px;background:#007AFF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div>
                    <div style="font-weight:800;color:#0F172A;font-size:1.05rem;line-height:1.1;">MULTIPARTES EL TUERCA</div>
                    <small style="color:#64748B;font-size:0.7rem;text-transform:uppercase;">Portal de Clientes · Santa Cruz</small>
                </div>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('home') }}#catalogo" class="btn btn-sm btn-outline-primary" style="border-radius:10px;font-weight:600;">
                    <i class="bi bi-shop me-1"></i>Catálogo de Repuestos
                </a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:10px;font-weight:600;">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="container py-4">
        @if(session('success'))
            <div class="alert alert-success rounded-3 border-0 shadow-sm mb-4" style="background:#ECFDF5;color:#065F46;">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger rounded-3 border-0 shadow-sm mb-4" style="background:#FEF2F2;color:#991B1B;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            </div>
        @endif

        <div class="row g-4">
            {{-- Solicitar Nueva Proforma --}}
            <div class="col-lg-5">
                <div class="card-clean p-4">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-file-earmark-plus text-primary me-2"></i>Solicitar Nueva Cotización
                    </h5>
                    <p class="text-muted mb-3" style="font-size:0.83rem;">
                        Selecciona los repuestos que necesitas y genera tu proforma oficial al instante.
                    </p>

                    <form action="{{ route('mis-cotizaciones.store') }}" method="POST">
                        @csrf
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small fw-semibold text-secondary">CI / NIT para la Proforma</label>
                                <input type="text" name="ci_nit" class="form-control form-control-sm" value="{{ $cliente->ci_nit !== 'S/N' ? $cliente->ci_nit : '' }}" placeholder="Tu CI o NIT (Opcional)">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold text-secondary">Plazo de Validez</label>
                                <select name="dias_vigencia" class="form-select form-select-sm">
                                    <option value="7">7 días</option>
                                    <option value="15">15 días</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Agregar Repuesto del Catálogo</label>
                            <div class="input-group input-group-sm">
                                <select id="selectProdWeb" class="form-select">
                                    <option value="">-- Selecciona un repuesto --</option>
                                    @foreach($productos as $p)
                                        <option value="{{ $p->id_producto }}"
                                                data-nombre="{{ $p->nombre_pieza }}"
                                                data-oem="{{ $p->codigo_oem }}"
                                                data-precio="{{ $p->precio_venta_sugerido }}"
                                                {{ (string)$productoPreseleccionado === (string)$p->id_producto ? 'selected' : '' }}>
                                            {{ $p->nombre_pieza }} (OEM: {{ $p->codigo_oem }}) - Bs. {{ number_format($p->precio_venta_sugerido, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-primary" onclick="agregarRepuestoWeb()">
                                    <i class="bi bi-plus-lg"></i> Añadir
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive border rounded-3 mb-3">
                            <table class="table table-sm align-middle mb-0" style="font-size:0.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Repuesto</th>
                                        <th style="width:70px;" class="text-center">Cant.</th>
                                        <th class="text-end">Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="listaItemsWeb">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Sin repuestos agregados aún.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
                            <span class="fw-bold small text-dark">Total Estimado:</span>
                            <span id="totalWebTexto" class="fw-bold fs-5 text-primary" style="font-family:monospace;">Bs. 0.00</span>
                        </div>

                        <button type="submit" id="btnGenerarWeb" class="btn-mac w-100 py-2" disabled>
                            <i class="bi bi-check2-circle me-1"></i>Generar Proforma Oficial
                        </button>
                    </form>
                </div>
            </div>

            {{-- Historial de Mis Cotizaciones --}}
            <div class="col-lg-7">
                <div class="card-clean p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="bi bi-receipt-cutoff text-primary me-2"></i>Mis Proformas y Estado de Vigencia
                            </h5>
                            <small class="text-muted">Cliente: <strong>{{ $cliente->nombre_razon_social }}</strong> ({{ $cliente->tipo_cliente }})</small>
                        </div>
                        <span class="badge bg-light text-secondary border">{{ $cotizaciones->total() }} proformas</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle" style="font-size:0.84rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>N° Proforma</th>
                                    <th>Emisión</th>
                                    <th>Vigencia</th>
                                    <th class="text-end">Total (Bs.)</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-end">Ver</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cotizaciones as $cot)
                                    @php
                                        $est = $cot->estado_visual;
                                        $dias = (int)$cot->dias_restantes;
                                    @endphp
                                    <tr>
                                        <td class="fw-bold text-primary">#COT-{{ str_pad($cot->id_cotizacion, 5, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $cot->fecha_emision->format('d/m/Y') }}</td>
                                        <td>
                                            @if($est === 'VIGENTE')
                                                <span class="text-success fw-semibold">{{ $dias === 0 ? 'Vence hoy' : $dias . ' días restantes' }}</span>
                                            @elseif($est === 'VENCIDA')
                                                <span class="text-danger">Vencida hace {{ abs($dias) }} días</span>
                                            @else
                                                <span class="text-muted">{{ $cot->fecha_vencimiento->format('d/m/Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold" style="font-family:monospace;">Bs. {{ number_format($cot->monto_total, 2) }}</td>
                                        <td class="text-center">
                                            @if($est === 'VIGENTE')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">VIGENTE</span>
                                            @elseif($est === 'CONVERTIDA')
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">CONVERTIDA</span>
                                            @elseif($est === 'VENCIDA')
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">VENCIDA</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">ANULADA</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('cotizaciones.show', $cot->id_cotizacion) }}" class="btn btn-sm btn-outline-primary" style="border-radius:8px;">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Aún no has generado cotizaciones. Utiliza el panel izquierdo para crear tu primera proforma.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($cotizaciones->hasPages())
                        <div class="mt-3">
                            {{ $cotizaciones->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        let itemsWeb = [];

        function agregarRepuestoWeb() {
            const sel = document.getElementById('selectProdWeb');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;

            const id = parseInt(opt.value);
            const nombre = opt.getAttribute('data-nombre');
            const oem = opt.getAttribute('data-oem');
            const precio = parseFloat(opt.getAttribute('data-precio'));

            const ex = itemsWeb.find(x => x.id_producto === id);
            if (ex) {
                ex.cantidad += 1;
            } else {
                itemsWeb.push({ id_producto: id, nombre, oem, precio, cantidad: 1 });
            }
            renderWeb();
        }

        function quitarWeb(idx) {
            itemsWeb.splice(idx, 1);
            renderWeb();
        }

        function cambiarCantWeb(idx, val) {
            itemsWeb[idx].cantidad = Math.max(1, parseInt(val) || 1);
            renderWeb();
        }

        function renderWeb() {
            const tbody = document.getElementById('listaItemsWeb');
            tbody.innerHTML = '';
            if (itemsWeb.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Sin repuestos agregados aún.</td></tr>';
                document.getElementById('totalWebTexto').textContent = 'Bs. 0.00';
                document.getElementById('btnGenerarWeb').disabled = true;
                return;
            }
            let total = 0;
            itemsWeb.forEach((it, idx) => {
                const sub = it.cantidad * it.precio;
                total += sub;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="hidden" name="items[${idx}][id_producto]" value="${it.id_producto}">
                        <div class="fw-semibold text-dark">${it.nombre}</div>
                        <small class="text-muted">OEM: ${it.oem} · Bs. ${it.precio.toFixed(2)}</small>
                    </td>
                    <td>
                        <input type="number" min="1" name="items[${idx}][cantidad]" value="${it.cantidad}"
                               class="form-control form-control-sm text-center" onchange="cambiarCantWeb(${idx}, this.value)">
                    </td>
                    <td class="text-end fw-bold" style="font-family:monospace;">Bs. ${sub.toFixed(2)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm text-danger p-0" onclick="quitarWeb(${idx})"><i class="bi bi-x-circle"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('totalWebTexto').textContent = 'Bs. ' + total.toFixed(2);
            document.getElementById('btnGenerarWeb').disabled = false;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sel = document.getElementById('selectProdWeb');
            if (sel && sel.value) {
                agregarRepuestoWeb();
            }
        });
    </script>
</body>
</html>

