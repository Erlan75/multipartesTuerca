@extends('layouts.app')
@section('title', 'Historial Comercial del Cliente — Multipartes El Tuerca')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes y Talleres</a></li>
    <li class="breadcrumb-item active">{{ $cliente->nombre_razon_social }}</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 style="color:#1E293B;font-weight:700;margin:0;">{{ $cliente->nombre_razon_social }}</h4>
            <span class="badge-activo">{{ $cliente->tipo_cliente }}</span>
            @if($cliente->descuento_frecuente > 0)
                <span class="badge" style="background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;">
                    Descuento Autorizado: {{ number_format($cliente->descuento_frecuente, 0) }}%
                </span>
            @endif
        </div>
        <p style="color:#64748B;font-size:0.85rem;margin:0;">
            CI/NIT: <strong>{{ $cliente->ci_nit ?: 'S/N' }}</strong> ·
            Teléfono/WhatsApp: <strong>{{ $cliente->telefono ?: 'No registrado' }}</strong>
            @if($cuentaWeb)
                · <span style="color:#007AFF;"><i class="bi bi-globe2 me-1"></i>Usuario Web vinculado: <strong>{{ $cuentaWeb->username }}</strong> ({{ $cuentaWeb->correo }})</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('cotizaciones.create', ['id_cliente' => $cliente->id_cliente]) }}" class="btn btn-tuerca">
            <i class="bi bi-file-earmark-plus me-1"></i>Nueva Cotización
        </a>
        <a href="{{ route('ventas.pos', ['id_cliente' => $cliente->id_cliente]) }}" class="btn btn-gold">
            <i class="bi bi-cart-check me-1"></i>Vender en POS
        </a>
        <a href="{{ route('clientes.edit', $cliente->id_cliente) }}" class="btn btn-outline-tuerca">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
    </div>
</div>

{{-- Resumen de Actividad --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-light p-3">
            <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Compras Efectivas</div>
            <div style="font-size:1.45rem;font-weight:700;color:#1E293B;">Bs. {{ number_format($resumen->total_compras, 2) }}</div>
            <small class="text-muted">{{ (int)$resumen->cantidad_ventas }} notas de venta</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-light p-3">
            <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Ahorro por Descuentos</div>
            <div style="font-size:1.45rem;font-weight:700;color:#059669;">Bs. {{ number_format($resumen->total_descuentos, 2) }}</div>
            <small class="text-muted">Beneficio comercial otorgado</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-light p-3">
            <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Cotizaciones Emitidas</div>
            <div style="font-size:1.45rem;font-weight:700;color:#1E293B;">{{ (int)$resumen->total_cotizaciones }}</div>
            <small class="text-muted">{{ (int)$resumen->cotizaciones_vigentes }} vigentes actualmente</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-light p-3">
            <div style="font-size:0.75rem;color:#64748B;font-weight:600;text-transform:uppercase;">Canal Digital</div>
            <div style="font-size:1.05rem;font-weight:700;color:#1E293B;" class="mt-1">
                @if($cuentaWeb)
                    <span class="text-primary"><i class="bi bi-check-circle-fill me-1"></i>Activo ({{ $cuentaWeb->username }})</span>
                @else
                    <span class="text-muted">Mostrador Presencial</span>
                @endif
            </div>
            <small class="text-muted">{{ $cuentaWeb ? $cuentaWeb->correo : 'Sin cuenta web vinculada' }}</small>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Historial de Ventas --}}
    <div class="col-lg-6">
        <div class="card-light h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Historial de Compras en POS</h6>
                <span class="badge bg-light text-secondary border">{{ $cliente->ventas->count() }} registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-custom mb-0">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Pago</th>
                            <th class="text-end">Total (Bs.)</th>
                            <th class="text-center">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cliente->ventas as $v)
                            <tr>
                                <td class="fw-bold">#V-{{ str_pad($v->id_venta, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $v->fecha_hora->format('d/m/Y H:i') }}</td>
                                <td><small>{{ $v->metodo_pago }}</small></td>
                                <td class="text-end fw-bold" style="font-family:monospace;">Bs. {{ number_format($v->total_venta, 2) }}</td>
                                <td class="text-center">
                                    @if($v->estado === 'Completada')
                                        <span class="badge-activo">Completada</span>
                                    @else
                                        <span class="badge-inactivo">Anulada</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('ventas.ticket', $v->id_venta) }}" class="btn btn-sm btn-outline-tuerca" title="Ver comprobante">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Sin compras registradas para este cliente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Historial de Cotizaciones --}}
    <div class="col-lg-6">
        <div class="card-light h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-warning"></i>Proformas y Cotizaciones</h6>
                <span class="badge bg-light text-secondary border">{{ $cliente->cotizaciones->count() }} registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-custom mb-0">
                    <thead>
                        <tr>
                            <th>Proforma</th>
                            <th>Emisión</th>
                            <th>Vigencia</th>
                            <th class="text-end">Total (Bs.)</th>
                            <th class="text-center">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cliente->cotizaciones as $cot)
                            <tr>
                                <td class="fw-bold">#COT-{{ str_pad($cot->id_cotizacion, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $cot->fecha_emision->format('d/m/Y') }}</td>
                                <td>
                                    @php $dias = $cot->dias_restantes; @endphp
                                    @if($cot->estado_visual === 'VIGENTE')
                                        <small class="text-success fw-semibold">{{ $dias === 0 ? 'Vence hoy' : 'Quedan ' . $dias . ' días' }}</small>
                                    @elseif($cot->estado_visual === 'VENCIDA')
                                        <small class="text-danger">Vencida hace {{ abs($dias) }} días</small>
                                    @else
                                        <small class="text-muted">{{ $cot->fecha_vencimiento->format('d/m/Y') }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold" style="font-family:monospace;">Bs. {{ number_format($cot->monto_total, 2) }}</td>
                                <td class="text-center">
                                    @if($cot->estado_visual === 'VIGENTE')
                                        <span class="badge-activo">VIGENTE</span>
                                    @elseif($cot->estado_visual === 'CONVERTIDA')
                                        <span class="badge" style="background:#EFF6FF;color:#007AFF;border:1px solid #BFDBFE;">CONVERTIDA</span>
                                    @elseif($cot->estado_visual === 'VENCIDA')
                                        <span class="badge-stock-bajo">VENCIDA</span>
                                    @else
                                        <span class="badge-inactivo">ANULADA</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('cotizaciones.show', $cot->id_cotizacion) }}" class="btn btn-sm btn-outline-tuerca" title="Ver proforma">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Sin cotizaciones registradas para este cliente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

