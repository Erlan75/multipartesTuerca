@extends('layouts.app')

@section('title', 'Acta de Arqueo de Caja #' . $sesion->id_sesion)

@section('content')
<div class="space-y-6">
    <!-- Barra Superior -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 print:hidden">
        <div class="flex items-center gap-3">
            <a href="{{ route('caja.index') }}" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition text-decoration-none">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900 mb-0">Acta de Arqueo — Sesión de Caja #{{ $sesion->id_sesion }}</h1>
                <p class="text-xs text-slate-500 mb-0">
                    Responsable: <strong>{{ $sesion->cajero->nombre_completo ?? 'Cajero' }}</strong> ·
                    Estado: <strong class="{{ $sesion->estado === 'Abierta' ? 'text-emerald-600' : 'text-slate-700' }}">{{ $sesion->estado }}</strong>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-sm transition border-0">
                <i class="fa-solid fa-print"></i> Imprimir Acta de Arqueo
            </button>
        </div>
    </div>

    <!-- Documento Principal de Arqueo -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
        <!-- Encabezado Imprimible -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 pb-5 gap-4">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-widest text-emerald-600 mb-0">MULTIPARTES EL TUERCA · SANTA CRUZ</p>
                <h2 class="text-2xl font-black text-slate-900 mt-0.5 mb-0">ACTA DE CONCILIACIÓN Y ARQUEO DE CAJA #{{ $sesion->id_sesion }}</h2>
                <p class="text-xs text-slate-500 mt-1 mb-0">
                    Apertura: {{ $sesion->fecha_hora_apertura ? $sesion->fecha_hora_apertura->format('d/m/Y H:i:s') : '-' }}
                    @if($sesion->fecha_hora_cierre)
                        | Cierre: {{ $sesion->fecha_hora_cierre->format('d/m/Y H:i:s') }}
                    @endif
                </p>
            </div>
            <div class="text-left sm:text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold {{ $sesion->estado === 'Abierta' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-900 text-white' }}">
                    SESIÓN {{ strtoupper($sesion->estado) }}
                </span>
                <p class="text-xs text-slate-500 mt-1.5 mb-0">Cajero: <strong>{{ $sesion->cajero->nombre_completo ?? 'N/D' }}</strong></p>
            </div>
        </div>

        <!-- Cuadro de Conciliación Financiera -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Columna 1: Flujo de Efectivo en Gaveta -->
            <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 border-b border-slate-200 pb-2">
                    1. Flujo de Efectivo Físico en Gaveta
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Base Inicial de Apertura:</span>
                        <span class="font-bold text-slate-900">Bs. {{ number_format($arqueo['base_inicial'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">(+) Ventas Cobradas en Efectivo ({{ $arqueo['cantidad_efectivo'] + $arqueo['cantidad_mixto'] }}):</span>
                        <span class="font-bold text-emerald-700">+ Bs. {{ number_format($arqueo['ventas_efectivo'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">(+) Ingresos Menores Extra:</span>
                        <span class="font-bold text-emerald-700">+ Bs. {{ number_format($arqueo['ingresos_extra'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">(-) Retiros Menores de Caja Chica:</span>
                        <span class="font-bold text-rose-600">- Bs. {{ number_format($arqueo['retiros_menores'], 2) }}</span>
                    </div>
                    <div class="pt-2 border-t border-slate-300 flex justify-between items-center">
                        <span class="font-extrabold text-slate-900">EFECTIVO ESPERADO EN GAVETA:</span>
                        <span class="text-lg font-black text-emerald-700">Bs. {{ number_format($arqueo['efectivo_esperado_gaveta'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Columna 2: Cobros Digitales y Conciliación Final -->
            <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 border-b border-slate-200 pb-2">
                    2. Medios Digitales y Resultado del Arqueo
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Cobros por Transferencia / QR ({{ $arqueo['cantidad_qr'] }}):</span>
                        <span class="font-bold text-sky-700">Bs. {{ number_format($arqueo['ventas_qr_transferencia'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Cobros con Tarjeta Débito/Crédito ({{ $arqueo['cantidad_tarjeta'] }}):</span>
                        <span class="font-bold text-indigo-700">Bs. {{ number_format($arqueo['ventas_tarjeta'], 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2">
                        <span class="text-slate-700 font-semibold">Saldo Global Esperado por Sistema:</span>
                        <span class="font-bold text-slate-900">Bs. {{ number_format($sesion->monto_esperado_sistema, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-700 font-semibold">Saldo Total Declarado al Cierre:</span>
                        <span class="font-bold text-slate-900">
                            {{ $sesion->estado !== 'Abierta' ? 'Bs. ' . number_format($sesion->monto_real_contado, 2) : 'Pendiente de cierre' }}
                        </span>
                    </div>
                    @if($sesion->estado !== 'Abierta')
                        @php $dif = (float) $sesion->diferencia; @endphp
                        <div class="pt-2 border-t border-slate-300 flex justify-between items-center">
                            <span class="font-extrabold text-slate-900">DIFERENCIA DE ARQUEO:</span>
                            <span class="text-lg font-black {{ abs($dif) < 0.01 ? 'text-emerald-700' : ($dif > 0 ? 'text-sky-700' : 'text-rose-600') }}">
                                {{ $dif > 0 ? '+' : '' }}Bs. {{ number_format($dif, 2) }}
                                ({{ abs($dif) < 0.01 ? 'CUADRE EXACTO' : ($dif > 0 ? 'SOBRANTE' : 'FALTANTE') }})
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if(!empty($observacionesCierre))
            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200 text-xs text-slate-700">
                <strong class="text-amber-900 uppercase tracking-wider block mb-1">Observaciones y Detalle de Cierre:</strong>
                {{ $observacionesCierre }}
            </div>
        @endif

        <!-- Detalle de Movimientos Menores de Caja Chica -->
        <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Movimientos Menores de Caja Chica ({{ $sesion->movimientos->count() }})</h3>
            @if($sesion->movimientos->isEmpty())
                <p class="text-xs text-slate-400 italic">Sin movimientos menores registrados en esta sesión.</p>
            @else
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase">
                            <tr>
                                <th class="py-2.5 px-4">Hora</th>
                                <th class="py-2.5 px-4">Tipo</th>
                                <th class="py-2.5 px-4">Concepto / Comprobante</th>
                                <th class="py-2.5 px-4 text-right">Monto (Bs.)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($sesion->movimientos as $m)
                                <tr>
                                    <td class="py-2.5 px-4 text-slate-500">{{ $m->hora ? $m->hora->format('d/m/Y H:i') : '-' }}</td>
                                    <td class="py-2.5 px-4 font-bold {{ $m->tipo === 'Ingreso Extra' ? 'text-emerald-700' : 'text-rose-600' }}">
                                        {{ $m->tipo }}
                                    </td>
                                    <td class="py-2.5 px-4 text-slate-800">{{ $m->motivo }}</td>
                                    <td class="py-2.5 px-4 text-right font-bold">
                                        {{ $m->tipo === 'Ingreso Extra' ? '+' : '-' }} Bs. {{ number_format($m->monto, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Detalle de Notas de Venta del Turno -->
        <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Notas de Venta Asociadas al Turno ({{ $sesion->ventas->count() }})</h3>
            @if($sesion->ventas->isEmpty())
                <p class="text-xs text-slate-400 italic">No se emitieron notas de venta en esta sesión.</p>
            @else
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase">
                            <tr>
                                <th class="py-2.5 px-4">Folio</th>
                                <th class="py-2.5 px-4">Hora</th>
                                <th class="py-2.5 px-4">Cliente</th>
                                <th class="py-2.5 px-4">Método de Pago</th>
                                <th class="py-2.5 px-4 text-center">Estado</th>
                                <th class="py-2.5 px-4 text-right">Total Neto (Bs.)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($sesion->ventas as $v)
                                <tr class="{{ $v->estado === 'Anulada' ? 'bg-rose-50/40 line-through text-slate-400' : '' }}">
                                    <td class="py-2.5 px-4 font-bold text-slate-900">
                                        <a href="{{ route('ventas.ticket', $v->id_venta) }}" class="hover:underline text-emerald-700">
                                            #V-{{ str_pad($v->id_venta, 5, '0', STR_PAD_LEFT) }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-4">{{ $v->fecha_hora ? $v->fecha_hora->format('H:i') : '-' }}</td>
                                    <td class="py-2.5 px-4">{{ $v->cliente->nombre_razon_social ?? 'Cliente Mostrador' }}</td>
                                    <td class="py-2.5 px-4 font-semibold">{{ $v->metodo_pago }}</td>
                                    <td class="py-2.5 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $v->estado === 'Completada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ strtoupper($v->estado) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-bold">Bs. {{ number_format($v->total_venta, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Firmas para Impresión -->
        <div class="hidden print:grid grid-cols-2 gap-12 pt-16 text-center text-xs text-slate-600">
            <div class="border-t border-slate-400 pt-2">
                <p class="font-bold text-slate-900 mb-0">{{ $sesion->cajero->nombre_completo ?? 'Cajero Responsable' }}</p>
                <p class="mb-0">Firma de Cajero — Entrega de Conformidad</p>
            </div>
            <div class="border-t border-slate-400 pt-2">
                <p class="font-bold text-slate-900 mb-0">Administración / Supervisión</p>
                <p class="mb-0">Firma de Recepción y Verificación de Arqueo</p>
            </div>
        </div>
    </div>
</div>
@endsection
