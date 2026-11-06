@extends('layouts.app')
@section('title', 'Cotizaciones y Proformas — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Cotizaciones</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-file-earmark-spreadsheet-fill me-2" style="color:#007AFF;"></i>Bandeja de Cotizaciones y Control de Vigencia
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Seguimiento de proformas comerciales emitidas, control de vencimientos y conversión inmediata a Nota de Venta.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('cotizaciones.create') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-plus-lg me-1"></i>Nueva Cotización
        </a>
        <a href="{{ route('ventas.pos') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.15rem;font-size:0.85rem;">
            <i class="bi bi-cart3 me-1"></i>Ir al Punto de Venta
        </a>
    </div>
</div>

{{-- ═══ MÉTRICAS RÁPIDAS ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Vigentes</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ (int)$metricas->vigentes }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-bag-check"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Convertidas</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ (int)$metricas->convertidas }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#FFFBEB;color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Vencidas</div>
                <div style="font-size:1.45rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ (int)$metricas->vencidas }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#F5F3FF;color:#7C3AED;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Cartera Vigente</div>
                <div style="font-size:1.25rem;font-weight:700;color:#1E293B;line-height:1.2;">Bs. {{ number_format($metricas->monto_vigente, 2) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('cotizaciones.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Buscar Proforma / Cliente</label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="N° cotización, cliente, CI/NIT o teléfono..."
                           value="{{ request('buscar') }}" style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Estado de Vigencia</label>
                <select name="estado" class="form-select form-select-dark">
                    <option value="">Todos los estados</option>
                    <option value="VIGENTE" {{ strtoupper(request('estado')) === 'VIGENTE' ? 'selected' : '' }}>VIGENTE</option>
                    <option value="VENCIDA" {{ strtoupper(request('estado')) === 'VENCIDA' ? 'selected' : '' }}>VENCIDA</option>
                    <option value="CONVERTIDA" {{ strtoupper(request('estado')) === 'CONVERTIDA' ? 'selected' : '' }}>CONVERTIDA</option>
                    <option value="ANULADA" {{ strtoupper(request('estado')) === 'ANULADA' ? 'selected' : '' }}>ANULADA</option>
                </select>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Cliente / Taller</label>
                <select name="id_cliente" class="form-select form-select-dark">
                    <option value="">Todos los clientes</option>
                    @foreach($clientes as $cl)
                        <option value="{{ $cl->id_cliente }}" {{ (string)request('id_cliente') === (string)$cl->id_cliente ? 'selected' : '' }}>
                            {{ $cl->nombre_razon_social }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('cotizaciones.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE COTIZACIONES ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:920px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Folio Proforma</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Cliente / Taller</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Emisión / Vencimiento</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Plazo / Vigencia</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:2px solid #E2E8F0;">Importe Total</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Estado</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cotizaciones as $cot)
                    @php
                        $estVisual = $cot->estado_visual;
                        $dias = (int)$cot->dias_restantes;
                    @endphp
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <a href="{{ route('cotizaciones.show', $cot->id_cotizacion) }}" style="font-weight:700;color:#007AFF;font-size:0.88rem;text-decoration:none;">
                                #COT-{{ str_pad($cot->id_cotizacion, 5, '0', STR_PAD_LEFT) }}
                            </a>
                            <div style="font-size:0.72rem;color:#64748B;">
                                {{ (int)$cot->detalles->count() }} {{ $cot->detalles->count() === 1 ? 'ítem' : 'ítems' }} · {{ $cot->usuario->nombre_completo ?? 'Sistema' }}
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.86rem;">
                                {{ $cot->cliente->nombre_razon_social ?? 'Cliente' }}
                            </div>
                            <div style="font-size:0.73rem;color:#64748B;">
                                CI/NIT: {{ $cot->cliente->ci_nit ?? 'S/N' }} · {{ $cot->cliente->tipo_cliente ?? 'Particular' }}
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;font-size:0.82rem;color:#475569;">
                            <div><i class="bi bi-calendar-check me-1 text-muted"></i>{{ $cot->fecha_emision->format('d/m/Y') }}</div>
                            <div><i class="bi bi-calendar-event me-1 text-muted"></i>Vence: <strong>{{ $cot->fecha_vencimiento->format('d/m/Y') }}</strong></div>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;white-space:nowrap;">
                            @if($estVisual === 'VIGENTE')
                                @if($dias === 0)
                                    <span style="font-size:0.78rem;font-weight:600;color:#D97706;">Vence hoy (0 días)</span>
                                @elseif($dias === 1)
                                    <span style="font-size:0.78rem;font-weight:600;color:#059669;">1 día restante</span>
                                @else
                                    <span style="font-size:0.78rem;font-weight:600;color:#059669;">{{ $dias }} días restantes</span>
                                @endif
                            @elseif($estVisual === 'VENCIDA')
                                <span style="font-size:0.78rem;font-weight:600;color:#DC2626;">
                                    Vencida hace {{ abs($dias) }} {{ abs($dias) === 1 ? 'día' : 'días' }}
                                </span>
                            @elseif($estVisual === 'CONVERTIDA')
                                <span style="font-size:0.76rem;color:#2563EB;font-weight:500;">Facturada en POS</span>
                            @else
                                <span style="font-size:0.76rem;color:#94A3B8;">Sin vigencia</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
                            <span style="font-weight:700;font-size:0.92rem;color:#1E293B;font-family:monospace;">
                                Bs. {{ number_format($cot->monto_total, 2) }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($estVisual === 'VIGENTE')
                                <span class="badge-activo"><i class="bi bi-check-circle me-1"></i>VIGENTE</span>
                            @elseif($estVisual === 'CONVERTIDA')
                                <span style="background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE;font-weight:600;font-size:0.73rem;padding:0.3rem 0.6rem;border-radius:6px;">
                                    <i class="bi bi-bag-check me-1"></i>CONVERTIDA
                                </span>
                            @elseif($estVisual === 'VENCIDA')
                                <span class="badge-stock-bajo"><i class="bi bi-clock-history me-1"></i>VENCIDA</span>
                            @else
                                <span class="badge-inactivo"><i class="bi bi-x-circle me-1"></i>ANULADA</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;white-space:nowrap;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('cotizaciones.show', $cot->id_cotizacion) }}" class="btn btn-sm btn-outline-tuerca" title="Ver e imprimir proforma">
                                    <i class="bi bi-printer"></i>
                                </a>

                                @if($estVisual === 'VIGENTE')
                                    <button type="button" class="btn btn-sm btn-success" style="border-radius:8px;font-size:0.78rem;padding:0.25rem 0.6rem;"
                                            onclick="abrirModalConversion({{ $cot->id_cotizacion }}, '{{ addslashes($cot->cliente->nombre_razon_social ?? 'Cliente') }}', {{ (float)$cot->monto_total }}, {{ (float)($cot->cliente->descuento_frecuente ?? 0) }})"
                                            title="Convertir cotización en Nota de Venta">
                                        <i class="bi bi-cart-check me-1"></i>Convertir a Venta
                                    </button>

                                    <form action="{{ route('cotizaciones.anular', $cot->id_cotizacion) }}" method="POST" onsubmit="return confirm('¿Anular la cotización #COT-{{ str_pad($cot->id_cotizacion, 5, '0', STR_PAD_LEFT) }}?');" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:8px;padding:0.25rem 0.5rem;" title="Anular cotización">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-file-earmark-x fs-2 d-block mb-2 text-muted"></i>
                            No se encontraron cotizaciones registradas con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($cotizaciones->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $cotizaciones->links() }}
        </div>
    @endif
</div>

{{-- ═══ MODAL DE CONVERSIÓN DIRECTA A VENTA ═══ --}}
<div class="modal fade" id="modalConvertirCotizacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-dark">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cart-check-fill text-success me-2"></i>Convertir Proforma en Nota de Venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConvertirCotizacion" method="POST">
                @csrf
                <div class="modal-body">
                    @if(!$sesionAbierta)
                        <div class="alert alert-tuerca-error mb-3">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i>
                            <strong>No hay sesión de caja abierta.</strong> Debe aperturar una sesión de caja antes de procesar cobros.
                            <div class="mt-2">
                                <a href="{{ route('caja.index') }}" class="btn btn-sm btn-danger">Ir a Apertura de Caja</a>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="id_sesion_caja" value="{{ $sesionAbierta->id_sesion }}">
                        <div class="p-3 mb-3 rounded-3" style="background:#F8FAFC;border:1px solid #E2E8F0;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted" style="font-size:0.78rem;">Cotización:</span>
                                <strong id="convFolioTexto" style="color:#007AFF;"></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted" style="font-size:0.78rem;">Cliente:</span>
                                <strong id="convClienteTexto" style="color:#1E293B;"></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size:0.78rem;">Importe Cotizado:</span>
                                <strong id="convMontoTexto" style="font-size:1.15rem;color:#059669;font-family:monospace;"></strong>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Descuento Comercial Adicional (Bs.)</label>
                            <input type="number" step="0.01" min="0" name="descuento_total" id="convDescuentoInput"
                                   class="form-control form-control-dark" value="0.00" oninput="recalcularConversion()">
                            <small class="text-muted" id="convInfoDescuento" style="font-size:0.72rem;"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Modalidad de Cobro <span class="text-danger">*</span></label>
                            <select name="metodo_pago" id="convMetodoPago" class="form-select form-select-dark" required onchange="cambiarMetodoConversion(this.value)">
                                <option value="Efectivo">Efectivo (Billete / Moneda Bs.)</option>
                                <option value="Transferencia/QR">Transferencia Bancaria / QR Simple</option>
                                <option value="Tarjeta Débito/Crédito">Tarjeta Débito / Crédito (POS Bancario)</option>
                                <option value="Mixto">Pago Mixto (Efectivo + QR/Tarjeta)</option>
                            </select>
                        </div>

                        {{-- Bloque Efectivo --}}
                        <div id="bloqueConvEfectivo" class="p-3 rounded-3 mb-2" style="background:#ECFDF5;border:1px solid #A7F3D0;">
                            <div class="row g-2 align-items-center">
                                <div class="col-6">
                                    <label class="form-label-dark mb-1" style="color:#065F46;">Efectivo Recibido (Bs.)</label>
                                    <input type="number" step="0.50" min="0" name="monto_recibido" id="convMontoRecibido"
                                           class="form-control form-control-dark" oninput="recalcularConversion()">
                                </div>
                                <div class="col-6 text-end">
                                    <div style="font-size:0.72rem;color:#065F46;font-weight:600;text-transform:uppercase;">Cambio / Vuelto</div>
                                    <div id="convCambioTexto" style="font-size:1.25rem;font-weight:800;color:#059669;font-family:monospace;">Bs. 0.00</div>
                                </div>
                            </div>
                        </div>

                        {{-- Bloque QR / Tarjeta --}}
                        <div id="bloqueConvReferencia" class="p-3 rounded-3 mb-2 d-none" style="background:#EFF6FF;border:1px solid #BFDBFE;">
                            <label class="form-label-dark mb-1" style="color:#1E40AF;">N° de Referencia / Comprobante Bancario o Voucher</label>
                            <input type="text" name="nro_referencia" class="form-control form-control-dark" placeholder="Ej: QR-9845120 / AUT-4412">
                        </div>

                        {{-- Bloque Mixto --}}
                        <div id="bloqueConvMixto" class="p-3 rounded-3 mb-2 d-none" style="background:#FFFBEB;border:1px solid #FDE68A;">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label-dark mb-1">Parte en Efectivo (Bs.)</label>
                                    <input type="number" step="0.01" min="0" name="monto_efectivo" id="convMixtoEfectivo"
                                           class="form-control form-control-dark" oninput="ajustarMixtoConversion()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label-dark mb-1">Parte por QR / Tarjeta (Bs.)</label>
                                    <input type="number" step="0.01" min="0" name="monto_digital" id="convMixtoDigital"
                                           class="form-control form-control-dark" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3">
                            <span class="fw-bold" style="color:#1E293B;">Total Neto a Cobrar:</span>
                            <span id="convTotalNetoTexto" style="font-size:1.35rem;font-weight:800;color:#007AFF;font-family:monospace;">Bs. 0.00</span>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:10px;">Cancelar</button>
                    @if($sesionAbierta)
                        <button type="submit" class="btn btn-success" style="border-radius:10px;font-weight:600;">
                            <i class="bi bi-check2-circle me-1"></i>Confirmar Cobro y Emitir Nota de Venta
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let montoBrutoConv = 0;

    function abrirModalConversion(idCotizacion, clienteNombre, montoTotal, descFrecuente) {
        montoBrutoConv = parseFloat(montoTotal) || 0;
        const form = document.getElementById('formConvertirCotizacion');
        form.action = `/cotizaciones/${idCotizacion}/convertir`;

        const elFolio = document.getElementById('convFolioTexto');
        if (elFolio) {
            elFolio.textContent = '#COT-' + String(idCotizacion).padStart(5, '0');
            document.getElementById('convClienteTexto').textContent = clienteNombre;
            document.getElementById('convMontoTexto').textContent = 'Bs. ' + montoBrutoConv.toFixed(2);
            document.getElementById('convDescuentoInput').value = '0.00';
            document.getElementById('convInfoDescuento').textContent = descFrecuente > 0
                ? `Cliente con límite sugerido de hasta ${descFrecuente}% (Bs. ${(montoBrutoConv * descFrecuente / 100).toFixed(2)}).`
                : 'Tope máximo comercial: 50% sin perforar costo de adquisición.';
            document.getElementById('convMontoRecibido').value = montoBrutoConv.toFixed(2);
            recalcularConversion();
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConvertirCotizacion'));
        modal.show();
    }

    function cambiarMetodoConversion(metodo) {
        document.getElementById('bloqueConvEfectivo').classList.toggle('d-none', metodo !== 'Efectivo');
        document.getElementById('bloqueConvReferencia').classList.toggle('d-none', metodo === 'Efectivo');
        document.getElementById('bloqueConvMixto').classList.toggle('d-none', metodo !== 'Mixto');
        recalcularConversion();
    }

    function recalcularConversion() {
        const desc = parseFloat(document.getElementById('convDescuentoInput')?.value) || 0;
        const neto = Math.max(0, montoBrutoConv - desc);
        const elNeto = document.getElementById('convTotalNetoTexto');
        if (elNeto) elNeto.textContent = 'Bs. ' + neto.toFixed(2);

        const recibido = parseFloat(document.getElementById('convMontoRecibido')?.value) || 0;
        const cambio = Math.max(0, recibido - neto);
        const elCambio = document.getElementById('convCambioTexto');
        if (elCambio) elCambio.textContent = 'Bs. ' + cambio.toFixed(2);

        ajustarMixtoConversion();
    }

    function ajustarMixtoConversion() {
        const desc = parseFloat(document.getElementById('convDescuentoInput')?.value) || 0;
        const neto = Math.max(0, montoBrutoConv - desc);
        const ef = parseFloat(document.getElementById('convMixtoEfectivo')?.value) || 0;
        const dig = Math.max(0, neto - ef);
        const elDig = document.getElementById('convMixtoDigital');
        if (elDig) elDig.value = dig.toFixed(2);
    }
</script>
@endpush

