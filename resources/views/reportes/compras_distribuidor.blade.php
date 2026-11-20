@extends('layouts.app')
@section('title', 'Compras por Distribuidor y Proveedores — Multipartes El Tuerca')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Compras por Distribuidor</li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-600 text-white shadow-sm">
                <i class="fa-solid fa-truck-field text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-0">Compras y Obligaciones por Distribuidor</h1>
                <p class="text-sm text-slate-500 mb-0">Consolidado de abastecimiento, volumen recibido, montos liquidados y saldos pendientes a crédito</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 no-print">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition text-decoration-none">
                <i class="fa-solid fa-file-csv"></i> Exportar CSV
            </a>
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-sm transition border-0">
                <i class="fa-solid fa-print"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-1">Total Adquirido</div>
            <p class="text-2xl font-black text-slate-900 mb-0">Bs. {{ number_format($kpis->monto_total, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1 mb-0">{{ $kpis->total_ordenes }} órdenes · {{ number_format($kpis->unidades_compradas) }} unidades</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Monto Pagado</div>
            <p class="text-2xl font-black text-emerald-700 mb-0">Bs. {{ number_format($kpis->monto_pagado, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1 mb-0">Facturas canceladas / contado</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-rose-200 bg-rose-50/30 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-rose-600 mb-1">Saldo Pendiente Crédito</div>
            <p class="text-2xl font-black text-rose-700 mb-0">Bs. {{ number_format($kpis->saldo_pendiente, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1 mb-0">Compromisos abiertos por liquidar</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Distribuidores en Período</div>
            <p class="text-2xl font-black text-slate-900 mb-0">{{ $kpis->proveedores_activos }}</p>
            <p class="text-xs text-slate-500 mt-1 mb-0">Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm no-print">
        <form method="GET" action="{{ route('reportes.compras') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="{{ $fechaFin }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs font-bold text-slate-600 mb-1">Proveedor / Importadora</label>
                <select name="id_proveedor" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos los proveedores</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id_proveedor }}" {{ (string)$idProveedor === (string)$prov->id_proveedor ? 'selected' : '' }}>
                            {{ $prov->razon_social }} ({{ $prov->condicion_comercial }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button type="submit" class="w-full py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl border-0">
                    <i class="fa-solid fa-filter mr-1"></i> Consultar
                </button>
                <a href="{{ route('reportes.compras') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl text-decoration-none">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4">Distribuidor / NIT</th>
                        <th class="py-3.5 px-4">Condición Comercial</th>
                        <th class="py-3.5 px-4 text-center">Órdenes</th>
                        <th class="py-3.5 px-4 text-center">Unidades</th>
                        <th class="py-3.5 px-4 text-right">Total Comprado (Bs.)</th>
                        <th class="py-3.5 px-4 text-right">Pagado (Bs.)</th>
                        <th class="py-3.5 px-4 text-right">Saldo Crédito (Bs.)</th>
                        <th class="py-3.5 px-4 text-center">Última Compra</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($filas as $r)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900">{{ $r->razon_social }}</div>
                                <div class="text-xs font-mono text-slate-500">NIT: {{ $r->nit ?: 'S/N' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $r->condicion_comercial }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">{{ (int)$r->total_ordenes }}</td>
                            <td class="py-3 px-4 text-center text-slate-700">{{ number_format((int)$r->unidades_compradas) }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">Bs. {{ number_format((float)$r->monto_total_comprado, 2) }}</td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-emerald-700">Bs. {{ number_format((float)$r->monto_pagado, 2) }}</td>
                            <td class="py-3 px-4 text-right font-mono font-extrabold {{ (float)$r->saldo_pendiente_credito > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                Bs. {{ number_format((float)$r->saldo_pendiente_credito, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center text-xs text-slate-600 whitespace-nowrap">
                                {{ $r->ultima_compra ? \Carbon\Carbon::parse($r->ultima_compra)->format('d/m/Y') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-slate-400 text-sm">
                                No se registraron compras a distribuidores en el período consultado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($filas->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-900 text-white font-bold text-sm">
                            <td colspan="2" class="py-3.5 px-4">TOTALES CONSOLIDADOS</td>
                            <td class="py-3.5 px-4 text-center">{{ (int)$kpis->total_ordenes }}</td>
                            <td class="py-3.5 px-4 text-center">{{ number_format($kpis->unidades_compradas) }}</td>
                            <td class="py-3.5 px-4 text-right font-mono">Bs. {{ number_format($kpis->monto_total, 2) }}</td>
                            <td class="py-3.5 px-4 text-right font-mono text-emerald-300">Bs. {{ number_format($kpis->monto_pagado, 2) }}</td>
                            <td class="py-3.5 px-4 text-right font-mono text-rose-300">Bs. {{ number_format($kpis->saldo_pendiente, 2) }}</td>
                            <td class="py-3.5 px-4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
