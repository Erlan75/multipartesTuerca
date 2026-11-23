@extends('layouts.app')
@section('title', 'Rotación de Repuestos e Inventario — Multipartes El Tuerca')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Rotación de Repuestos</li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-blue-600 text-white shadow-sm">
                <i class="fa-solid fa-arrows-rotate text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-0">Rotación de Repuestos y Baja Salida</h1>
                <p class="text-sm text-slate-500 mb-0">Clasificación de desplazamiento comercial del inventario e identificación de piezas sin movimiento</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 no-print">
            @include('partials.export_buttons')
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-sm transition border-0">
                <i class="fa-solid fa-print"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <!-- KPIs Resumen -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Alta Rotación</div>
            <p class="text-2xl font-black text-slate-900 mb-0">{{ $kpis->alta_rotacion }} <span class="text-xs font-semibold text-slate-400">ítems</span></p>
            <p class="text-xs text-slate-500 mt-1 mb-0">{{ number_format($kpis->unidades_vendidas) }} uds vendidas en período</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-blue-600 mb-1">Rotación Media</div>
            <p class="text-2xl font-black text-slate-900 mb-0">{{ $kpis->rotacion_media }} <span class="text-xs font-semibold text-slate-400">ítems</span></p>
            <p class="text-xs text-slate-500 mt-1 mb-0">Desplazamiento regular en mostrador</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-rose-200/80 bg-rose-50/30 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-rose-600 mb-1">Baja Rotación / Hueso</div>
            <p class="text-2xl font-black text-rose-700 mb-0">{{ $kpis->baja_rotacion }} <span class="text-xs font-semibold text-rose-400">ítems</span></p>
            <p class="text-xs text-slate-500 mt-1 mb-0">{{ number_format($kpis->stock_inmovilizado) }} uds inmovilizadas</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Catálogo Evaluado</div>
            <p class="text-2xl font-black text-slate-900 mb-0">{{ $kpis->total_items }} <span class="text-xs font-semibold text-slate-400">activos</span></p>
            <p class="text-xs text-slate-500 mt-1 mb-0">Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</p>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm no-print">
        <form method="GET" action="{{ route('reportes.rotacion') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="{{ $fechaFin }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 mb-1">Categoría</label>
                <select name="id_categoria" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id_categoria }}" {{ (string)$idCategoria === (string)$cat->id_categoria ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 mb-1">Clasificación</label>
                <select name="clasificacion" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    <option value="Alta Rotación" {{ $clasif === 'Alta Rotación' ? 'selected' : '' }}>Alta Rotación</option>
                    <option value="Rotación Media" {{ $clasif === 'Rotación Media' ? 'selected' : '' }}>Rotación Media</option>
                    <option value="Baja Rotación / Hueso" {{ $clasif === 'Baja Rotación / Hueso' ? 'selected' : '' }}>Baja Rotación / Hueso</option>
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button type="submit" class="w-full py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl border-0">
                    <i class="fa-solid fa-filter mr-1"></i> Consultar
                </button>
                <a href="{{ route('reportes.rotacion') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl text-decoration-none">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Gráficos Analíticos (CU35) -->
    @if($filas->isEmpty())
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm text-center text-slate-400 text-sm">
            <i class="fa-solid fa-chart-pie text-2xl text-slate-300 mb-2 block"></i>
            Sin datos suficientes para generar gráficos de rotación con los filtros actuales.
        </div>
    @else
        @php
            $topRot = $filas->sortByDesc(fn($r) => (int) $r->unidades_vendidas)->take(8)->values();
            $chartRotData = [
                'distLabels'   => ['Alta Rotación', 'Rotación Media', 'Baja Rotación / Hueso'],
                'distValues'   => [(int) $kpis->alta_rotacion, (int) $kpis->rotacion_media, (int) $kpis->baja_rotacion],
                'topLabels'    => $topRot->map(fn($r) => \Illuminate\Support\Str::limit($r->nombre_pieza, 24))->all(),
                'topFullNames' => $topRot->map(fn($r) => (string) $r->nombre_pieza)->all(),
                'vendidas'     => $topRot->map(fn($r) => (int) $r->unidades_vendidas)->all(),
                'stock'        => $topRot->map(fn($r) => (int) $r->stock_actual)->all(),
            ];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <div class="lg:col-span-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm" style="break-inside: avoid;">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-slate-900 mb-0">Distribución del Catálogo por Rotación</h2>
                    <p class="text-xs text-slate-500 mb-0">Clasificación de desplazamiento de piezas activas</p>
                </div>
                <div class="relative h-72">
                    <canvas id="chartRotacionDist"></canvas>
                </div>
            </div>

            <div class="lg:col-span-8 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm" style="break-inside: avoid;">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-slate-900 mb-0">Top 8 Repuestos Más Vendidos: Unidades Vendidas vs. Stock Actual</h2>
                    <p class="text-xs text-slate-500 mb-0">Comparativo de demanda del período frente a existencia actual</p>
                </div>
                <div class="relative h-72">
                    <canvas id="chartRotacionTop"></canvas>
                </div>
            </div>
        </div>
    @endif

    <!-- Tabla Analítica -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4">SKU / OEM</th>
                        <th class="py-3.5 px-4">Repuesto / Categoría</th>
                        <th class="py-3.5 px-4 text-center">Stock / Mín.</th>
                        <th class="py-3.5 px-4 text-center">Uds. Vendidas</th>
                        <th class="py-3.5 px-4 text-center">Frecuencia</th>
                        <th class="py-3.5 px-4 text-right">Índice Rotación</th>
                        <th class="py-3.5 px-4 text-center">Días s/ Mov.</th>
                        <th class="py-3.5 px-4 text-center">Clasificación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($filas as $r)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-mono font-bold text-blue-700 text-xs">{{ $r->codigo_interno }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $r->codigo_oem }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900">{{ $r->nombre_pieza }}</div>
                                <div class="text-xs text-slate-500">{{ $r->categoria }}</div>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="font-bold {{ $r->stock_actual <= $r->stock_minimo ? 'text-rose-600' : 'text-slate-800' }}">
                                    {{ (int)$r->stock_actual }}
                                </span>
                                <span class="text-xs text-slate-400">/ {{ (int)$r->stock_minimo }}</span>
                            </td>
                            <td class="py-3 px-4 text-center font-extrabold text-slate-900">
                                {{ (int)$r->unidades_vendidas }}
                            </td>
                            <td class="py-3 px-4 text-center text-slate-600">
                                {{ (int)$r->frecuencia_ventas }} notas
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-800">
                                {{ number_format((float)$r->indice_rotacion * 100, 1) }}%
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-xs font-semibold {{ $r->dias_sin_movimiento > 45 ? 'text-rose-600' : 'text-slate-600' }}">
                                    {{ (int)$r->dias_sin_movimiento }} días
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if($r->clasificacion === 'Alta Rotación')
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Alta Rotación</span>
                                @elseif($r->clasificacion === 'Rotación Media')
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800">Rotación Media</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">Baja Rotación / Hueso</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-slate-400 text-sm">
                                No se encontraron repuestos activos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@if($filas->isNotEmpty())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const data = @json($chartRotData);

    const ctxDist = document.getElementById('chartRotacionDist');
    if (ctxDist) {
        const total = data.distValues.reduce((a, b) => a + b, 0) || 1;
        new Chart(ctxDist, {
            type: 'doughnut',
            data: {
                labels: data.distLabels,
                datasets: [{
                    data: data.distValues,
                    backgroundColor: ['#059669', '#2563EB', '#E11D48'],
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
                                return `${ctx.label}: ${ctx.parsed} ítems (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    const ctxTop = document.getElementById('chartRotacionTop');
    if (ctxTop) {
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: data.topLabels,
                datasets: [
                    { label: 'Unidades Vendidas', data: data.vendidas, backgroundColor: '#2563EB', borderRadius: 6 },
                    { label: 'Stock Actual', data: data.stock, backgroundColor: '#0F172A', borderRadius: 6 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => data.topFullNames[items[0].dataIndex] || items[0].label,
                            label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} uds.`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } }
                }
            }
        });
    }
});
</script>
@endpush
@endif
