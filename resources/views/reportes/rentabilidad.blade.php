@extends('layouts.app')
@section('title', 'Rentabilidad y Márgenes Reales — Multipartes El Tuerca')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Rentabilidad y Márgenes</li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-600 text-white shadow-sm">
                <i class="fa-solid fa-chart-line text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-0">Rentabilidad y Margen Bruto Real</h1>
                <p class="text-sm text-slate-500 mb-0">Comparativo entre ingresos netos de ventas completadas y costos reales de reposición por repuesto</p>
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

    <!-- KPIs Financieros -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-blue-600 mb-1">Ingresos Netos</div>
            <p class="text-2xl font-black text-slate-900 mb-0">Bs. {{ number_format($kpis->ingresos_netos, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1 mb-0">{{ number_format($kpis->unidades_vendidas) }} uds vendidas ({{ $kpis->items_analizados }} ítems)</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-amber-600 mb-1">Costo Total Estimado</div>
            <p class="text-2xl font-black text-slate-900 mb-0">Bs. {{ number_format($kpis->costo_estimado, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1 mb-0">Según último costo de adquisición</p>
        </div>
        <div class="bg-gradient-to-br from-emerald-600 to-teal-700 p-5 rounded-2xl text-white shadow-md">
            <div class="text-xs font-bold uppercase tracking-wider text-emerald-100 mb-1">Utilidad Bruta Real</div>
            <p class="text-2xl font-black mb-0">Bs. {{ number_format($kpis->utilidad_bruta, 2) }}</p>
            <p class="text-xs text-emerald-100 mt-1 mb-0">Descontando rebajas comerciales</p>
        </div>
        <div class="bg-slate-900 p-5 rounded-2xl text-white shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-sky-400 mb-1">Margen Real Promedio</div>
            <p class="text-2xl font-black mb-0">{{ number_format($kpis->margen_global, 2) }}%</p>
            <p class="text-xs text-slate-300 mt-1 mb-0">Sobre ingresos netos facturados</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm no-print">
        <form method="GET" action="{{ route('reportes.rentabilidad') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="{{ $fechaFin }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs font-bold text-slate-600 mb-1">Categoría de Repuesto</label>
                <select name="id_categoria" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id_categoria }}" {{ (string)$idCategoria === (string)$cat->id_categoria ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button type="submit" class="w-full py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl border-0">
                    <i class="fa-solid fa-filter mr-1"></i> Analizar
                </button>
                <a href="{{ route('reportes.rentabilidad') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl text-decoration-none">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla de Rentabilidad -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4">SKU</th>
                        <th class="py-3.5 px-4">Repuesto / Categoría</th>
                        <th class="py-3.5 px-4 text-center">Cant. Vendida</th>
                        <th class="py-3.5 px-4 text-right">Ingresos Netos (Bs.)</th>
                        <th class="py-3.5 px-4 text-right">Costo Estimado (Bs.)</th>
                        <th class="py-3.5 px-4 text-right">Utilidad Bruta (Bs.)</th>
                        <th class="py-3.5 px-4 text-center">Margen Real (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($filas as $r)
                        @php $margen = (float)$r->margen_real_porcentaje; @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4 font-mono font-bold text-blue-700 text-xs whitespace-nowrap">{{ $r->codigo_interno }}</td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900">{{ $r->nombre_pieza }}</div>
                                <div class="text-xs text-slate-500">{{ $r->categoria }}</div>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">{{ (int)$r->cantidad_vendida }}</td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-slate-900">Bs. {{ number_format((float)$r->ingresos_netos, 2) }}</td>
                            <td class="py-3 px-4 text-right font-mono text-slate-600">Bs. {{ number_format((float)$r->costo_total_estimado, 2) }}</td>
                            <td class="py-3 px-4 text-right font-mono font-extrabold {{ (float)$r->utilidad_bruta >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                Bs. {{ number_format((float)$r->utilidad_bruta, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $margen >= 30 ? 'bg-emerald-100 text-emerald-800' : ($margen >= 15 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                    {{ number_format($margen, 2) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400 text-sm">
                                No se registraron ventas completadas en el rango de fechas seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($filas->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-900 text-white font-bold text-sm">
                            <td colspan="2" class="py-3.5 px-4">TOTALES CONSOLIDADOS DEL PERÍODO</td>
                            <td class="py-3.5 px-4 text-center">{{ (int)$kpis->unidades_vendidas }}</td>
                            <td class="py-3.5 px-4 text-right font-mono">Bs. {{ number_format($kpis->ingresos_netos, 2) }}</td>
                            <td class="py-3.5 px-4 text-right font-mono">Bs. {{ number_format($kpis->costo_estimado, 2) }}</td>
                            <td class="py-3.5 px-4 text-right font-mono text-emerald-300">Bs. {{ number_format($kpis->utilidad_bruta, 2) }}</td>
                            <td class="py-3.5 px-4 text-center text-sky-300">{{ number_format($kpis->margen_global, 2) }}%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
