@extends('layouts.app')
@section('title', 'Balance de Medios de Pago y Caja — Multipartes El Tuerca')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Balance de Medios de Pago</li>
@endsection

@push('styles')
<style>
    .ticket-balance-80mm {
        width: 320px;
        max-width: 100%;
        margin: 0 auto;
        background: #ffffff;
        color: #000000;
        padding: 18px 14px;
        border: 1px dashed #CBD5E1;
        border-radius: 10px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        line-height: 1.38;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
    }
    .ticket-balance-80mm .t-div {
        border-top: 1px dashed #000;
        margin: 8px 0;
    }
    .ticket-balance-80mm table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11.5px;
    }
    .ticket-balance-80mm th, .ticket-balance-80mm td {
        padding: 3px 0;
    }
    @media print {
        .ticket-balance-80mm {
            width: 78mm !important;
            border: none !important;
            box-shadow: none !important;
            padding: 2mm !important;
            margin: 0 auto !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 no-print">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-amber-500 text-white shadow-sm">
                <i class="fa-solid fa-scale-balanced text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-0">Balance por Medios de Pago y Turnos de Caja</h1>
                <p class="text-sm text-slate-500 mb-0">Conciliación de recaudación por Efectivo, Transferencia/QR, Tarjeta y Mixto por turno y cajero</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl overflow-hidden border border-slate-200">
                <a href="{{ request()->fullUrlWithQuery(['formato' => 'estandar']) }}"
                   class="px-3 py-2 text-xs font-bold text-decoration-none {{ $formato !== 'termico' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-table mr-1"></i> Estándar A4
                </a>
                <a href="{{ request()->fullUrlWithQuery(['formato' => 'termico']) }}"
                   class="px-3 py-2 text-xs font-bold text-decoration-none {{ $formato === 'termico' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-receipt mr-1"></i> Ticket 80mm
                </a>
            </div>
            @include('partials.export_buttons')
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition border-0">
                <i class="fa-solid fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm no-print">
        <form method="GET" action="{{ route('reportes.balance') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <input type="hidden" name="formato" value="{{ $formato }}">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="{{ $fechaFin }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Sesión / Turno de Caja</label>
                <select name="id_sesion" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas las sesiones</option>
                    @foreach($sesiones as $s)
                        <option value="{{ $s->id_sesion }}" {{ (string)$idSesion === (string)$s->id_sesion ? 'selected' : '' }}>
                            Caja #{{ $s->id_sesion }} ({{ $s->estado }}) — {{ $s->cajero->nombre_completo ?? 'Cajero' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Cajero Responsable</label>
                <select name="id_cajero" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos los cajeros</option>
                    @foreach($cajeros as $caj)
                        <option value="{{ $caj->id_usuario }}" {{ (string)$idCajero === (string)$caj->id_usuario ? 'selected' : '' }}>
                            {{ $caj->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button type="submit" class="w-full py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl border-0">
                    <i class="fa-solid fa-filter mr-1"></i> Filtrar
                </button>
                <a href="{{ route('reportes.balance') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl text-decoration-none">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    @if($formato === 'termico')
        <!-- VISTA TICKET TÉRMICO 80MM DE CIERRE / BALANCE DE MEDIOS DE PAGO -->
        <div class="ticket-balance-80mm">
            <div class="text-center">
                <div style="font-weight:bold;font-size:14px;">MULTIPARTES EL TUERCA</div>
                <div style="font-size:11px;">BALANCE DE MEDIOS DE PAGO</div>
                <div style="font-size:10.5px;">Santa Cruz de la Sierra - Bolivia</div>
            </div>
            <div class="t-div"></div>
            <div>
                <div><strong>PERÍODO:</strong> {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</div>
                <div><strong>SESIÓN:</strong> {{ $idSesion ? '#' . $idSesion : 'TODAS' }}</div>
                <div><strong>EMITIDO:</strong> {{ now()->format('d/m/Y H:i') }}</div>
            </div>
            <div class="t-div"></div>
            <div style="font-weight:bold;text-align:center;">RESUMEN POR CANAL</div>
            <table>
                <thead>
                    <tr style="border-bottom:1px dashed #000;">
                        <th style="text-align:left;">CANAL</th>
                        <th style="text-align:center;">TKT</th>
                        <th style="text-align:right;">NETO Bs.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resumenCanales as $rc)
                        <tr>
                            <td>{{ $rc->metodo_pago }}</td>
                            <td style="text-align:center;">{{ $rc->tickets }}</td>
                            <td style="text-align:right;font-weight:bold;">{{ number_format($rc->recaudado_neto, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;">Sin movimientos</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="t-div"></div>
            <table>
                <tr>
                    <td>TOTAL TICKETS:</td>
                    <td style="text-align:right;font-weight:bold;">{{ $kpis->total_tickets }}</td>
                </tr>
                <tr>
                    <td>TOTAL DESCUENTOS:</td>
                    <td style="text-align:right;">Bs. {{ number_format($kpis->total_descuento, 2) }}</td>
                </tr>
                <tr>
                    <td>TICKET PROMEDIO:</td>
                    <td style="text-align:right;">Bs. {{ number_format($kpis->ticket_promedio, 2) }}</td>
                </tr>
                <tr style="font-size:13.5px;font-weight:bold;">
                    <td>TOTAL NETO:</td>
                    <td style="text-align:right;">Bs. {{ number_format($kpis->total_recaudado, 2) }}</td>
                </tr>
            </table>
            <div class="t-div"></div>
            <div style="font-size:10.5px;text-align:center;">
                <div>Documento de control de tesorería</div>
                <div class="mt-3">____________________________</div>
                <div>Firma Cajero / Responsable</div>
            </div>
        </div>
    @else
        <!-- KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-slate-900 to-blue-950 p-5 rounded-2xl text-white shadow-md">
                <div class="text-xs font-bold uppercase tracking-wider text-sky-300 mb-1">Total Recaudado Neto</div>
                <p class="text-2xl font-black mb-0">Bs. {{ number_format($kpis->total_recaudado, 2) }}</p>
                <p class="text-xs text-slate-300 mt-1 mb-0">{{ $kpis->total_tickets }} tickets · Promedio Bs. {{ number_format($kpis->ticket_promedio, 2) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-emerald-200 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Efectivo (Bs.)</div>
                <p class="text-2xl font-black text-emerald-700 mb-0">Bs. {{ number_format($kpis->total_efectivo, 2) }}</p>
                <p class="text-xs text-slate-500 mt-1 mb-0">Ingreso directo a gaveta física</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-blue-200 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-blue-600 mb-1">Medios Digitales (QR / Tarjeta)</div>
                <p class="text-2xl font-black text-blue-700 mb-0">Bs. {{ number_format($kpis->total_digital, 2) }}</p>
                <p class="text-xs text-slate-500 mt-1 mb-0">Conciliación bancaria / POS</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-amber-200 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-amber-600 mb-1">Mixto y Descuentos</div>
                <p class="text-xl font-black text-slate-900 mb-0">Bs. {{ number_format($kpis->total_mixto, 2) }}</p>
                <p class="text-xs text-amber-700 mt-1 mb-0">Descuentos otorgados: Bs. {{ number_format($kpis->total_descuento, 2) }}</p>
            </div>
        </div>

        <!-- Resumen Consolidado por Canal -->
        @if($resumenCanales->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                @foreach($resumenCanales as $rc)
                    <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-500 uppercase">{{ $rc->metodo_pago }}</div>
                            <div class="text-lg font-extrabold text-slate-900 font-mono">Bs. {{ number_format($rc->recaudado_neto, 2) }}</div>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <div class="font-bold text-slate-700">{{ $rc->tickets }} tickets</div>
                            <div>Prom. Bs. {{ number_format($rc->ticket_promedio, 2) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Gráficos Analíticos (CU38 — solo en modo Estándar A4) -->
        @if($filas->isEmpty())
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm text-center text-slate-400 text-sm">
                <i class="fa-solid fa-chart-pie text-2xl text-slate-300 mb-2 block"></i>
                Sin recaudaciones completadas para graficar en el período y filtros seleccionados.
            </div>
        @else
            @php
                $sesionesAgrupadas = $filas->groupBy('id_sesion')->map(function ($grupo, $idSes) {
                    $primero = $grupo->first();
                    return [
                        'id'       => (int) $idSes,
                        'label'    => 'Caja #' . $idSes,
                        'cajero'   => (string) ($primero->cajero_responsable ?? 'Cajero'),
                        'neto'     => round((float) $grupo->sum('total_neto_recaudado'), 2),
                        'tickets'  => (int) $grupo->sum('cantidad_tickets'),
                    ];
                })->sortBy('id')->take(10)->values();

                $chartBalanceData = [
                    'canalLabels'   => $resumenCanales->pluck('metodo_pago')->all(),
                    'canalValues'   => $resumenCanales->map(fn($c) => round((float) $c->recaudado_neto, 2))->all(),
                    'canalTickets'  => $resumenCanales->map(fn($c) => (int) $c->tickets)->all(),
                    'sesionLabels'  => $sesionesAgrupadas->pluck('label')->all(),
                    'sesionCajeros' => $sesionesAgrupadas->pluck('cajero')->all(),
                    'sesionNetos'   => $sesionesAgrupadas->pluck('neto')->all(),
                    'sesionTickets' => $sesionesAgrupadas->pluck('tickets')->all(),
                ];
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-5 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm" style="break-inside: avoid;">
                    <div class="mb-3">
                        <h2 class="text-sm font-bold text-slate-900 mb-0">Recaudación por Canal de Pago (Bs.)</h2>
                        <p class="text-xs text-slate-500 mb-0">Efectivo, Transferencia/QR, Tarjeta y Mixto</p>
                    </div>
                    <div class="relative h-72">
                        <canvas id="chartBalanceCanales"></canvas>
                    </div>
                </div>

                <div class="lg:col-span-7 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm" style="break-inside: avoid;">
                    <div class="mb-3">
                        <h2 class="text-sm font-bold text-slate-900 mb-0">Ingresos Netos por Sesión / Turno de Caja (Bs.)</h2>
                        <p class="text-xs text-slate-500 mb-0">Comparativo de recaudación neta por turno</p>
                    </div>
                    <div class="relative h-72">
                        <canvas id="chartBalanceSesiones"></canvas>
                    </div>
                </div>
            </div>
        @endif

        <!-- Tabla Detallada por Turno / Cajero / Medio de Pago -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-4">Sesión Caja</th>
                            <th class="py-3.5 px-4">Cajero Responsable</th>
                            <th class="py-3.5 px-4">Medio de Pago</th>
                            <th class="py-3.5 px-4 text-center">Tickets</th>
                            <th class="py-3.5 px-4 text-right">Descuentos (Bs.)</th>
                            <th class="py-3.5 px-4 text-right">Ticket Promedio</th>
                            <th class="py-3.5 px-4 text-right">Recaudado Neto (Bs.)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($filas as $r)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <a href="{{ route('caja.show', $r->id_sesion) }}" class="font-bold text-blue-700 hover:underline">
                                        Caja #{{ $r->id_sesion }}
                                    </a>
                                    <span class="block text-[11px] text-slate-400">
                                        {{ $r->estado_sesion }} · {{ \Carbon\Carbon::parse($r->fecha_apertura)->format('d/m/Y H:i') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-800">{{ $r->cajero_responsable }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $r->metodo_pago === 'Efectivo' ? 'bg-emerald-100 text-emerald-800' : ($r->metodo_pago === 'Transferencia/QR' ? 'bg-blue-100 text-blue-800' : ($r->metodo_pago === 'Tarjeta Débito/Crédito' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800')) }}">
                                        {{ $r->metodo_pago }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ (int)$r->cantidad_tickets }}</td>
                                <td class="py-3 px-4 text-right font-mono text-amber-700">Bs. {{ number_format((float)$r->total_descuento, 2) }}</td>
                                <td class="py-3 px-4 text-right font-mono text-slate-600">Bs. {{ number_format((float)$r->ticket_promedio, 2) }}</td>
                                <td class="py-3 px-4 text-right font-mono font-extrabold text-slate-900">Bs. {{ number_format((float)$r->total_neto_recaudado, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-400 text-sm">
                                    No se encontraron recaudaciones completadas para los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@if($formato !== 'termico' && $filas->isNotEmpty())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const data = @json($chartBalanceData);
    const fmtBs = (v) => 'Bs. ' + Number(v || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const mapaColorCanal = (nombre) => {
        const n = String(nombre || '').toLowerCase();
        if (n.includes('efectivo')) return '#059669';
        if (n.includes('qr') || n.includes('transf')) return '#2563EB';
        if (n.includes('tarjeta')) return '#0F172A';
        return '#D97706';
    };

    const ctxCanales = document.getElementById('chartBalanceCanales');
    if (ctxCanales) {
        const total = data.canalValues.reduce((a, b) => a + b, 0) || 1;
        new Chart(ctxCanales, {
            type: 'doughnut',
            data: {
                labels: data.canalLabels,
                datasets: [{
                    data: data.canalValues,
                    backgroundColor: data.canalLabels.map(mapaColorCanal),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 11, font: { size: 10.5 } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const pct = ((ctx.parsed / total) * 100).toFixed(1);
                                const tkts = data.canalTickets[ctx.dataIndex] ?? 0;
                                return `${ctx.label}: ${fmtBs(ctx.parsed)} (${pct}% · ${tkts} tkt)`;
                            }
                        }
                    }
                }
            }
        });
    }

    const ctxSesiones = document.getElementById('chartBalanceSesiones');
    if (ctxSesiones) {
        new Chart(ctxSesiones, {
            type: 'bar',
            data: {
                labels: data.sesionLabels,
                datasets: [{
                    label: 'Ingresos Netos (Bs.)',
                    data: data.sesionNetos,
                    backgroundColor: '#2563EB',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const idx = items[0].dataIndex;
                                return `${data.sesionLabels[idx]} — ${data.sesionCajeros[idx]}`;
                            },
                            label: (ctx) => {
                                const tkts = data.sesionTickets[ctx.dataIndex] ?? 0;
                                return `Recaudado: ${fmtBs(ctx.parsed.y)} (${tkts} tickets)`;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10.5 } } },
                    y: { beginAtZero: true, ticks: { callback: (v) => 'Bs. ' + v, font: { size: 10 } } }
                }
            }
        });
    }
});
</script>
@endpush
@endif
