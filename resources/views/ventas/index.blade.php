@extends('layouts.app')
@section('title', 'Historial de Notas de Venta — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Notas de Venta</li>
@endsection

@section('content')
{{-- ═══ CABECERA ═══ --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 style="color:#1E293B;font-weight:700;margin-bottom:0.25rem;">
            <i class="bi bi-receipt-cutoff me-2" style="color:#007AFF;"></i>Historial de Notas de Venta y Facturación POS
        </h4>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            Registro consolidado de transacciones de mostrador, emisión de tickets térmicos y anulaciones controladas.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('ventas.pos') }}" class="btn btn-tuerca" style="padding:0.55rem 1.25rem;font-size:0.85rem;">
            <i class="bi bi-cart-plus me-1"></i>Nueva Venta en POS
        </a>
        <a href="{{ route('cotizaciones.index') }}" class="btn btn-outline-tuerca" style="padding:0.55rem 1.15rem;font-size:0.85rem;">
            <i class="bi bi-file-earmark-text me-1"></i>Cotizaciones
        </a>
    </div>
</div>

{{-- ═══ MÉTRICAS DEL DÍA ═══ --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Recaudado Hoy</div>
                <div style="font-size:1.3rem;font-weight:700;color:#1E293B;line-height:1.2;">Bs. {{ number_format($metricasHoy->total_recaudado_neto ?? 0, 2) }}</div>
                <small class="text-muted">{{ (int)($metricasHoy->cantidad_transacciones ?? 0) }} ventas hoy</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#FFFBEB;color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-tag"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Descuentos Hoy</div>
                <div style="font-size:1.3rem;font-weight:700;color:#1E293B;line-height:1.2;">Bs. {{ number_format($metricasHoy->total_descuentos ?? 0, 2) }}</div>
                <small class="text-muted">Rebajas autorizadas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Acumulado General</div>
                <div style="font-size:1.3rem;font-weight:700;color:#1E293B;line-height:1.2;">Bs. {{ number_format($totalHistorico, 2) }}</div>
                <small class="text-muted">Ventas completadas</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-light p-3 h-100 d-flex flex-row align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:12px;background:#FEF2F2;color:#DC2626;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                <i class="bi bi-arrow-counterclockwise"></i>
            </div>
            <div>
                <div style="font-size:0.74rem;color:#64748B;font-weight:600;text-transform:uppercase;">Notas Anuladas</div>
                <div style="font-size:1.3rem;font-weight:700;color:#1E293B;line-height:1.2;">{{ (int)$totalAnuladas }}</div>
                <small class="text-muted">Con reversión de stock</small>
            </div>
        </div>
    </div>
</div>

{{-- ═══ FILTROS ═══ --}}
<div style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,0.03);">
    <form method="GET" action="{{ route('ventas.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Folio o Cliente</label>
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.85rem;"></i>
                    <input type="text" name="buscar" class="form-control form-control-dark"
                           placeholder="N° venta, cliente o CI/NIT..."
                           value="{{ request('buscar') }}" style="padding-left:2.2rem;">
                </div>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Método de Cobro</label>
                <select name="metodo_pago" class="form-select form-select-dark">
                    <option value="">Todos</option>
                    <option value="Efectivo" {{ request('metodo_pago') === 'Efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="Transferencia/QR" {{ request('metodo_pago') === 'Transferencia/QR' ? 'selected' : '' }}>Transferencia / QR</option>
                    <option value="Tarjeta Débito/Crédito" {{ request('metodo_pago') === 'Tarjeta Débito/Crédito' ? 'selected' : '' }}>Tarjeta Débito/Crédito</option>
                    <option value="Mixto" {{ request('metodo_pago') === 'Mixto' ? 'selected' : '' }}>Mixto</option>
                </select>
            </div>
            <div class="col-md-2">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Estado</label>
                <select name="estado" class="form-select form-select-dark">
                    <option value="">Todos</option>
                    <option value="Completada" {{ request('estado') === 'Completada' ? 'selected' : '' }}>Completadas</option>
                    <option value="Anulada" {{ request('estado') === 'Anulada' ? 'selected' : '' }}>Anuladas</option>
                </select>
            </div>
            <div class="col-md-3">
                <label style="font-size:0.75rem;font-weight:600;color:#64748B;margin-bottom:0.25rem;display:block;">Fecha Desde / Hasta</label>
                <div class="d-flex gap-1">
                    <input type="date" name="fecha_desde" class="form-control form-control-dark" value="{{ request('fecha_desde') }}">
                    <input type="date" name="fecha_hasta" class="form-control form-control-dark" value="{{ request('fecha_hasta') }}">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-tuerca w-100" type="submit" style="padding:0.5rem 0.75rem;">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('ventas.index') }}" class="btn btn-outline-tuerca" style="padding:0.5rem 0.75rem;" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ═══ TABLA DE VENTAS ═══ --}}
<div class="table-responsive-container" style="background:#fff;border:1px solid rgba(226,232,240,0.80);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04);overflow:hidden;">
    <div class="overflow-x-auto">
        <table style="width:100%;min-width:940px;border-collapse:collapse;">
            <thead>
                <tr style="background:#FAFBFC;">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Folio Venta</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Fecha y Hora</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Cliente / Taller</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Vendedor / Caja</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #E2E8F0;">Método Cobro</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:2px solid #E2E8F0;">Descuento</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:2px solid #E2E8F0;">Total Cobrado</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Estado</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;text-align:center;border-bottom:2px solid #E2E8F0;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                    <tr style="border-bottom:1px solid #F1F5F9;{{ $v->estado === 'Anulada' ? 'background:#FEF2F2;opacity:0.85;' : '' }}">
                        <td style="padding:0.75rem 1rem;white-space:nowrap;">
                            <a href="{{ route('ventas.ticket', $v->id_venta) }}" style="font-weight:700;color:#007AFF;font-size:0.88rem;text-decoration:none;">
                                #V-{{ str_pad($v->id_venta, 5, '0', STR_PAD_LEFT) }}
                            </a>
                            <div style="font-size:0.72rem;color:#64748B;">{{ (int)$v->detalles->sum('cantidad_vendida') }} pzs</div>
                        </td>
                        <td style="padding:0.75rem 1rem;white-space:nowrap;font-size:0.82rem;color:#475569;">
                            {{ $v->fecha_hora->format('d/m/Y H:i') }}
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <div style="font-weight:600;color:#1E293B;font-size:0.85rem;">{{ $v->cliente->nombre_razon_social ?? 'Cliente Mostrador' }}</div>
                            <div style="font-size:0.72rem;color:#64748B;">NIT/CI: {{ $v->cliente->ci_nit ?? 'S/N' }}</div>
                        </td>
                        <td style="padding:0.75rem 1rem;font-size:0.82rem;color:#334155;">
                            <div>{{ $v->vendedor->nombre_completo ?? 'Personal' }}</div>
                            <small class="text-muted">Sesión Caja #{{ $v->id_sesion_caja }}</small>
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            @if($v->metodo_pago === 'Efectivo')
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.6rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#ECFDF5;color:#059669;">
                                    <i class="bi bi-cash"></i> Efectivo
                                </span>
                            @elseif($v->metodo_pago === 'Transferencia/QR')
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.6rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#EFF6FF;color:#007AFF;">
                                    <i class="bi bi-qr-code"></i> Transferencia/QR
                                </span>
                            @elseif($v->metodo_pago === 'Tarjeta Débito/Crédito')
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.6rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#F5F3FF;color:#7C3AED;">
                                    <i class="bi bi-credit-card"></i> Tarjeta
                                </span>
                            @else
                                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.6rem;border-radius:6px;font-size:0.74rem;font-weight:600;background:#FFFBEB;color:#D97706;">
                                    <i class="bi bi-layers-half"></i> Mixto
                                </span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;font-family:monospace;font-size:0.83rem;">
                            @if($v->descuento_total > 0)
                                <span class="text-warning-emphasis fw-semibold">-Bs. {{ number_format($v->descuento_total, 2) }}</span>
                            @else
                                <span class="text-muted">Bs. 0.00</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
                            <span style="font-weight:700;font-size:0.92rem;color:#1E293B;font-family:monospace;">
                                Bs. {{ number_format($v->total_venta, 2) }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;">
                            @if($v->estado === 'Completada')
                                <span class="badge-activo"><i class="bi bi-check2-circle me-1"></i>Completada</span>
                            @else
                                <span class="badge-inactivo" title="Motivo: {{ $v->motivo_anulacion }}"><i class="bi bi-x-circle me-1"></i>Anulada</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:center;white-space:nowrap;">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('ventas.ticket', ['id' => $v->id_venta, 'formato' => 'termico']) }}" class="btn btn-sm btn-outline-tuerca" title="Ticket Térmico 80mm">
                                    <i class="bi bi-receipt"></i>
                                </a>
                                <a href="{{ route('ventas.ticket', ['id' => $v->id_venta, 'formato' => 'estandar']) }}" class="btn btn-sm btn-outline-secondary" title="Comprobante Estándar">
                                    <i class="bi bi-printer"></i>
                                </a>
                                @if($v->estado === 'Completada')
                                    <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:8px;padding:0.25rem 0.5rem;"
                                            onclick="abrirModalAnularVenta({{ $v->id_venta }}, '{{ addslashes($v->cliente->nombre_razon_social ?? 'Cliente') }}', {{ (float)$v->total_venta }})"
                                            title="Anular Nota de Venta y restituir stock">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding:3rem 1rem;text-align:center;color:#94A3B8;">
                            <i class="bi bi-receipt fs-2 d-block mb-2 text-muted"></i>
                            No se encontraron notas de venta con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($ventas->hasPages())
        <div style="padding:0.85rem 1.25rem;border-top:1px solid #E2E8F0;background:#FAFBFC;">
            {{ $ventas->links() }}
        </div>
    @endif
</div>

{{-- ═══ MODAL DE ANULACIÓN DE VENTA ═══ --}}
<div class="modal fade" id="modalAnularVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-dark">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-octagon-fill me-2"></i>Anular Nota de Venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAnularVenta" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="p-3 rounded-3 mb-3" style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;font-size:0.83rem;">
                        <div><strong>Folio a Anular:</strong> <span id="anularFolioTexto"></span></div>
                        <div><strong>Cliente:</strong> <span id="anularClienteTexto"></span></div>
                        <div><strong>Importe a Revertir:</strong> <span id="anularMontoTexto" class="fw-bold"></span></div>
                        <hr class="my-2">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>Al confirmar, el motor PostgreSQL devolverá automáticamente los repuestos al inventario físico y descontará el monto del saldo de la sesión de caja.
                        </small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label-dark">Motivo Obligatorio de Anulación <span class="text-danger">*</span></label>
                        <textarea name="motivo_anulacion" rows="3" class="form-control form-control-dark" required minlength="5"
                                  placeholder="Indique detalladamente la razón de la anulación o devolución (mínimo 5 caracteres)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Confirmar Anulación y Revertir Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function abrirModalAnularVenta(idVenta, cliente, monto) {
        document.getElementById('formAnularVenta').action = `/ventas/${idVenta}/anular`;
        document.getElementById('anularFolioTexto').textContent = '#V-' + String(idVenta).padStart(5, '0');
        document.getElementById('anularClienteTexto').textContent = cliente;
        document.getElementById('anularMontoTexto').textContent = 'Bs. ' + parseFloat(monto).toFixed(2);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAnularVenta')).show();
    }
</script>
@endpush

