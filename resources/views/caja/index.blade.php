@extends('layouts.app')

@section('title', 'Tesorería, Arqueo y Sesiones de Caja')

@section('content')
<div class="space-y-6">
    <!-- Encabezado Principal -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-600 text-white shadow-sm">
                    <i class="fa-solid fa-cash-register text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Tesorería y Sesiones de Caja</h1>
                    <p class="text-sm text-slate-500">Control de apertura de turno, movimientos de caja chica y arqueo físico de efectivo</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($sesionAbierta)
                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    CAJA #{{ $sesionAbierta->id_sesion }} ABIERTA ({{ $sesionAbierta->cajero->nombre_completo ?? 'Cajero' }})
                </span>
                <a href="{{ route('ventas.pos') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl shadow-sm transition text-decoration-none">
                    <i class="fa-solid fa-cart-shopping"></i> Ir al Punto de Venta (POS)
                </a>
            @else
                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold">
                    <i class="fa-solid fa-lock"></i> SIN SESIÓN DE CAJA ACTIVA
                </span>
            @endif
        </div>
    </div>

    @if(!$sesionAbierta)
        <!-- TARJETA DE APERTURA DE SESIÓN DE CAJA -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 p-6 sm:p-8 text-white">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 mb-3">
                        <i class="fa-solid fa-key"></i> Inicio de Turno Operativo
                    </span>
                    <h2 class="text-xl sm:text-2xl font-bold">Aperturar Nueva Sesión de Caja</h2>
                    <p class="text-slate-300 text-sm mt-1">
                        Para habilitar cobros en el Punto de Venta (POS), conversión de proformas y movimientos de efectivo, declare la base inicial en gaveta al comenzar el turno.
                    </p>
                </div>
            </div>

            <form action="{{ route('caja.abrir') }}" method="POST" class="p-6 sm:p-8">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                    <div class="md:col-span-5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                            Cajero / Responsable de Turno
                        </label>
                        <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm">
                            <i class="fa-solid fa-user-shield text-emerald-600"></i>
                            <span>{{ auth()->user()->nombre_completo }}</span>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <label for="monto_inicial" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Base Inicial en Efectivo (Bs.) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center font-bold text-slate-400">Bs.</span>
                            <input type="number" step="0.50" min="0" name="monto_inicial" id="monto_inicial"
                                   value="{{ old('monto_inicial', '500.00') }}" required
                                   class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-lg font-extrabold text-slate-900">
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-xs text-slate-400">Bases frecuentes:</span>
                            <button type="button" onclick="document.getElementById('monto_inicial').value='200.00'" class="text-xs px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium border-0">Bs. 200</button>
                            <button type="button" onclick="document.getElementById('monto_inicial').value='500.00'" class="text-xs px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium border-0">Bs. 500</button>
                            <button type="button" onclick="document.getElementById('monto_inicial').value='1000.00'" class="text-xs px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium border-0">Bs. 1.000</button>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <button type="submit" class="w-full py-3.5 px-6 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 border-0">
                            <i class="fa-solid fa-unlock-keyhole"></i> Aperturar Caja Ahora
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @else
        <!-- ECUACIÓN FINANCIERA EN TIEMPO REAL DE LA SESIÓN ABIERTA -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Base Inicial -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                    <span>Base Inicial</span>
                    <i class="fa-solid fa-vault text-slate-400 text-sm"></i>
                </div>
                <p class="text-2xl font-extrabold text-slate-800 mb-0">Bs. {{ number_format($arqueo['base_inicial'], 2) }}</p>
                <p class="text-xs text-slate-500 mt-1 mb-0">Apertura: {{ $sesionAbierta->fecha_hora_apertura ? $sesionAbierta->fecha_hora_apertura->format('d/m/Y H:i') : '-' }}</p>
            </div>

            <!-- Ventas en Efectivo -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">
                    <span>+ Ventas Efectivo</span>
                    <i class="fa-solid fa-money-bill-wave text-emerald-500 text-sm"></i>
                </div>
                <p class="text-2xl font-extrabold text-emerald-700 mb-0">+ Bs. {{ number_format($arqueo['ventas_efectivo'], 2) }}</p>
                <p class="text-xs text-slate-500 mt-1 mb-0">{{ $arqueo['cantidad_efectivo'] + $arqueo['cantidad_mixto'] }} cobros con efectivo</p>
            </div>

            <!-- Movimientos Menores (Ingresos - Retiros) -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-amber-600 mb-1">
                    <span>± Caja Chica</span>
                    <i class="fa-solid fa-arrows-up-down text-amber-500 text-sm"></i>
                </div>
                <div class="flex items-baseline gap-3">
                    <span class="text-sm font-bold text-emerald-600">+{{ number_format($arqueo['ingresos_extra'], 2) }}</span>
                    <span class="text-sm font-bold text-rose-600">-{{ number_format($arqueo['retiros_menores'], 2) }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-1.5 mb-0">{{ $sesionAbierta->movimientos->count() }} movimientos registrados</p>
            </div>

            <!-- EFECTIVO ESPERADO EN GAVETA -->
            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 p-5 rounded-2xl text-white shadow-md">
                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-emerald-100 mb-1">
                    <span>= Efectivo en Gaveta</span>
                    <i class="fa-solid fa-coins text-emerald-200 text-sm"></i>
                </div>
                <p class="text-2xl font-black tracking-tight mb-0">Bs. {{ number_format($arqueo['efectivo_esperado_gaveta'], 2) }}</p>
                <p class="text-[11px] text-emerald-100 mt-1 mb-0">Billetes y monedas físicos esperados</p>
            </div>

            <!-- Cobros Digitales (QR / Transferencia / Tarjeta) -->
            <div class="bg-slate-900 p-5 rounded-2xl text-white shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-sky-400 mb-1">
                    <span>Cobros Digitales</span>
                    <i class="fa-solid fa-qrcode text-sky-400 text-sm"></i>
                </div>
                <p class="text-2xl font-extrabold text-white mb-0">Bs. {{ number_format($arqueo['total_digitales'], 2) }}</p>
                <div class="flex items-center justify-between text-[11px] text-slate-300 mt-1">
                    <span>QR/Transf: Bs. {{ number_format($arqueo['ventas_qr_transferencia'], 2) }}</span>
                    <span>Tarjeta: Bs. {{ number_format($arqueo['ventas_tarjeta'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- PANEL CENTRAL: MOVIMIENTOS MENORES DE CAJA CHICA + ARQUEO Y CIERRE -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- COLUMNA IZQUIERDA (5 cols): MOVIMIENTOS MENORES DE CAJA CHICA -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                        <div>
                            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2 mb-0">
                                <i class="fa-solid fa-receipt text-amber-600"></i>
                                Registrar Movimiento Menor de Caja
                            </h3>
                            <p class="text-xs text-slate-500 mb-0">Gastos operativos menores, transporte, refrigerios o ingreso de sencillo</p>
                        </div>
                    </div>

                    <form action="{{ route('caja.movimientos.store') }}" method="POST" class="p-6 space-y-4">
                        @csrf
                        <input type="hidden" name="id_sesion" value="{{ $sesionAbierta->id_sesion }}">

                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo" value="Retiro Menor" class="peer sr-only" checked>
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-rose-500 peer-checked:bg-rose-50/70 text-center transition">
                                    <i class="fa-solid fa-arrow-trend-down text-rose-600 mb-1"></i>
                                    <p class="text-xs font-bold text-slate-800 mb-0">Egreso / Retiro Menor</p>
                                    <p class="text-[11px] text-slate-500 mb-0">Resta efectivo de gaveta</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="tipo" value="Ingreso Extra" class="peer sr-only">
                                <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50/70 text-center transition">
                                    <i class="fa-solid fa-arrow-trend-up text-emerald-600 mb-1"></i>
                                    <p class="text-xs font-bold text-slate-800 mb-0">Ingreso Extra / Sencillo</p>
                                    <p class="text-[11px] text-slate-500 mb-0">Suma efectivo a gaveta</p>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Categoría Operativa</label>
                                <select name="categoria" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500">
                                    <option value="TRANSPORTE / FLETE">Transporte / Flete</option>
                                    <option value="REFRIGERIO">Refrigerio / Alimentación</option>
                                    <option value="INSUMOS / PAPELERIA">Insumos / Papelería / Limpieza</option>
                                    <option value="CAMBIO / SENCILLO">Dotación de Cambio / Sencillo</option>
                                    <option value="SERVICIOS">Servicios / Imprevistos</option>
                                    <option value="OTRO">Otro Movimiento</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Monto en Efectivo (Bs.) *</label>
                                <input type="number" step="0.50" min="0.50" name="monto" required placeholder="0.00"
                                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Concepto / Justificación Detallada *</label>
                            <input type="text" name="motivo" required maxlength="180"
                                   placeholder="Ej. Pago de mototaxi por envío urgente de pastillas de freno"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">N° Recibo / Vale / Comprobante (Opcional)</label>
                            <input type="text" name="comprobante" maxlength="60"
                                   placeholder="Ej. Vale Interno #014 o Factura #882"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500">
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center justify-center gap-2 border-0">
                            <i class="fa-solid fa-plus-circle"></i> Registrar Movimiento en Caja
                        </button>
                    </form>

                    <!-- Bitácora de Movimientos Menores de la Sesión Actual -->
                    <div class="border-t border-slate-100 px-6 py-4 bg-slate-50/40">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                            Movimientos de Caja Chica en este Turno ({{ $sesionAbierta->movimientos->count() }})
                        </h4>
                        @if($sesionAbierta->movimientos->isEmpty())
                            <p class="text-xs text-slate-400 italic py-3 text-center mb-0">No se registraron ingresos extra ni retiros menores en este turno.</p>
                        @else
                            <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                @foreach($sesionAbierta->movimientos->sortByDesc('id_movimiento') as $mov)
                                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200/80 text-xs">
                                        <div class="pr-2">
                                            <div class="flex items-center gap-1.5">
                                                @if($mov->tipo === 'Ingreso Extra')
                                                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">INGRESO</span>
                                                @else
                                                    <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 font-bold text-[10px]">RETIRO</span>
                                                @endif
                                                <span class="font-semibold text-slate-800">{{ $mov->motivo }}</span>
                                            </div>
                                            <p class="text-[11px] text-slate-400 mt-0.5 mb-0">
                                                {{ $mov->hora ? $mov->hora->format('H:i') : '' }}
                                            </p>
                                        </div>
                                        <span class="font-extrabold whitespace-nowrap {{ $mov->tipo === 'Ingreso Extra' ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $mov->tipo === 'Ingreso Extra' ? '+' : '-' }} Bs. {{ number_format($mov->monto, 2) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA (7 cols): ARQUEO FÍSICO Y CIERRE DE SESIÓN DE CAJA -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-slate-50/60">
                        <div>
                            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2 mb-0">
                                <i class="fa-solid fa-scale-balanced text-emerald-600"></i>
                                Panel de Arqueo y Cierre de Caja
                            </h3>
                            <p class="text-xs text-slate-500 mb-0">Cuente los billetes y monedas en gaveta para conciliar con el saldo esperado</p>
                        </div>
                        <button type="button" onclick="toggleCalculadoraBilletes()" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-calculator"></i> Calculadora de Cortes (Bs.)
                        </button>
                    </div>

                    <form action="{{ route('caja.cerrar', $sesionAbierta->id_sesion) }}" method="POST" id="formCierreCaja" class="p-6 space-y-5">
                        @csrf

                        <!-- Resumen de la Fórmula de Efectivo Esperado -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/90 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span>Base Inicial de Apertura:</span>
                                <span class="font-semibold text-slate-800">Bs. {{ number_format($arqueo['base_inicial'], 2) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>(+) Cobros de Ventas en Efectivo:</span>
                                <span class="font-semibold text-emerald-700">+ Bs. {{ number_format($arqueo['ventas_efectivo'], 2) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>(+) Ingresos Menores Extra:</span>
                                <span class="font-semibold text-emerald-700">+ Bs. {{ number_format($arqueo['ingresos_extra'], 2) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>(-) Retiros Menores de Caja Chica:</span>
                                <span class="font-semibold text-rose-600">- Bs. {{ number_format($arqueo['retiros_menores'], 2) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex justify-between items-center text-sm">
                                <span class="font-bold text-slate-900">EFECTIVO ESPERADO EN GAVETA:</span>
                                <span class="font-black text-emerald-700 text-base">Bs. {{ number_format($arqueo['efectivo_esperado_gaveta'], 2) }}</span>
                            </div>
                        </div>

                        <!-- Calculadora Opcional de Billetes y Monedas de Bolivianos -->
                        <div id="panelCalculadoraBilletes" class="hidden p-4 rounded-xl bg-emerald-50/50 border border-emerald-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-emerald-900">
                                    <i class="fa-solid fa-money-bill-1-wave mr-1"></i> Desglose de Billetes y Monedas (Bs.)
                                </span>
                                <button type="button" onclick="limpiarCalculadoraBilletes()" class="text-xs text-rose-600 hover:underline font-medium bg-transparent border-0">Limpiar conteo</button>
                            </div>
                            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2.5">
                                @foreach([200, 100, 50, 20, 10, 5, 2, 1, 0.5] as $corte)
                                    <div class="bg-white p-2 rounded-lg border border-slate-200">
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                            {{ $corte >= 10 ? 'Billete' : 'Moneda' }} Bs. {{ $corte == 0.5 ? '0.50' : $corte }}
                                        </label>
                                        <input type="number" min="0" value="0" data-corte="{{ $corte }}"
                                               oninput="recalcularDesdeBilletes()"
                                               class="input-corte-bs w-full rounded border border-slate-300 px-2 py-1 text-xs font-bold text-slate-800 text-center">
                                    </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="detalle_billetes" id="detalle_billetes" value="">
                        </div>

                        <!-- Input Principal: Efectivo Físico Contado y Diferencia Automática -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
                            <div class="p-4 rounded-xl border-2 border-slate-300 focus-within:border-emerald-600 bg-white">
                                <label for="efectivo_fisico_contado" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Efectivo Físico Contado en Gaveta (Bs.) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative mt-1">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">Bs.</span>
                                    <input type="number" step="0.01" min="0" name="efectivo_fisico_contado" id="efectivo_fisico_contado"
                                           value="{{ old('efectivo_fisico_contado', number_format($arqueo['efectivo_esperado_gaveta'], 2, '.', '')) }}"
                                           oninput="actualizarDiferenciaArqueo()" required
                                           class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-300 text-xl font-black text-slate-900 focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1.5 mb-0">Ingrese la suma real de billetes y monedas contados</p>
                            </div>

                            <!-- Indicador Dinámico de Diferencia (Cuadre / Sobrante / Faltante) -->
                            <div id="cardResultadoArqueo" class="p-4 rounded-xl border-2 border-emerald-300 bg-emerald-50 flex flex-col justify-between">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800" id="labelEstadoArqueo">
                                        CUADRE PERFECTO
                                    </span>
                                    <i id="iconoEstadoArqueo" class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                                </div>
                                <div class="my-1">
                                    <p id="montoDiferenciaArqueo" class="text-2xl font-black text-emerald-800 mb-0">Bs. 0.00</p>
                                </div>
                                <p id="subtextoEstadoArqueo" class="text-[11px] text-emerald-700 font-medium mb-0">
                                    El efectivo físico coincide exactamente con el esperado.
                                </p>
                            </div>
                        </div>

                        <!-- Resumen informativo de cobros digitales verificados -->
                        <div class="px-4 py-3 rounded-xl bg-sky-50 border border-sky-200 flex flex-wrap items-center justify-between gap-2 text-xs text-sky-900">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-building-columns text-sky-600"></i>
                                <span><strong>Cobros Digitales en Bancos/POS:</strong> QR/Transferencias (Bs. {{ number_format($arqueo['ventas_qr_transferencia'], 2) }}) + Tarjetas (Bs. {{ number_format($arqueo['ventas_tarjeta'], 2) }})</span>
                            </div>
                            <span class="font-extrabold">Total Digital: Bs. {{ number_format($arqueo['total_digitales'], 2) }}</span>
                        </div>

                        <div>
                            <label for="observaciones_arqueo" class="block text-xs font-bold text-slate-700 mb-1">
                                Observaciones del Arqueo / Justificación de Diferencias
                            </label>
                            <textarea name="observaciones_arqueo" id="observaciones_arqueo" rows="2"
                                      placeholder="Opcional si el arqueo cuadra. Recomendado explicar si existe sobrante o faltante de efectivo..."
                                      class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">{{ old('observaciones_arqueo') }}</textarea>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" onclick="return confirm('¿Confirma ejecutar el arqueo definitivo y cerrar la Sesión de Caja #{{ $sesionAbierta->id_sesion }}?')"
                                    class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2 border-0">
                                <i class="fa-solid fa-lock"></i> Ejecutar Arqueo y Cerrar Turno de Caja
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- HISTORIAL DE SESIONES Y ARQUEOS DE CAJA -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 mb-0">Historial de Turnos y Arqueos de Caja</h3>
                <p class="text-xs text-slate-500 mb-0">Registro auditable de aperturas, cierres, saldos esperados y diferencias declaradas</p>
            </div>

            <form method="GET" action="{{ route('caja.index') }}" class="flex flex-wrap items-center gap-2">
                <select name="estado" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                    <option value="">Todos los estados</option>
                    <option value="Abierta" {{ request('estado') === 'Abierta' ? 'selected' : '' }}>Abierta</option>
                    <option value="Cuadrada" {{ request('estado') === 'Cuadrada' ? 'selected' : '' }}>Cuadrada</option>
                    <option value="Con Diferencia" {{ request('estado') === 'Con Diferencia' ? 'selected' : '' }}>Con Diferencia</option>
                </select>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-700">
                <button type="submit" class="px-3.5 py-2 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 border-0">
                    <i class="fa-solid fa-filter mr-1"></i> Filtrar
                </button>
                @if(request()->hasAny(['estado', 'fecha_desde']))
                    <a href="{{ route('caja.index') }}" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-200 text-decoration-none">
                        Limpiar
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4">Sesión</th>
                        <th class="py-3.5 px-4">Cajero Responsable</th>
                        <th class="py-3.5 px-4">Apertura / Cierre</th>
                        <th class="py-3.5 px-4 text-right">Base Inicial</th>
                        <th class="py-3.5 px-4 text-right">Saldo Esperado</th>
                        <th class="py-3.5 px-4 text-right">Total Declarado</th>
                        <th class="py-3.5 px-4 text-right">Diferencia</th>
                        <th class="py-3.5 px-4 text-center">Estado</th>
                        <th class="py-3.5 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($sesiones as $s)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                #{{ $s->id_sesion }}
                                <span class="block text-[11px] font-normal text-slate-400">{{ $s->total_operaciones_venta }} ventas</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-semibold text-slate-800">{{ $s->cajero->nombre_completo ?? 'Cajero' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600">
                                <div><i class="fa-regular fa-clock text-emerald-600 mr-1"></i> {{ $s->fecha_hora_apertura ? $s->fecha_hora_apertura->format('d/m/Y H:i') : '-' }}</div>
                                @if($s->fecha_hora_cierre)
                                    <div class="text-slate-400 mt-0.5"><i class="fa-solid fa-flag-checkered mr-1"></i> {{ $s->fecha_hora_cierre->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-medium text-slate-700">
                                Bs. {{ number_format($s->monto_inicial, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-900">
                                Bs. {{ number_format($s->monto_esperado_sistema, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-800">
                                @if($s->estado !== 'Abierta')
                                    Bs. {{ number_format($s->monto_real_contado, 2) }}
                                @else
                                    <span class="text-xs text-slate-400 italic">En curso</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($s->estado !== 'Abierta')
                                    @php $dif = (float) $s->diferencia; @endphp
                                    @if(abs($dif) < 0.01)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            Bs. 0.00 (Exacto)
                                        </span>
                                    @elseif($dif > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800">
                                            + Bs. {{ number_format($dif, 2) }} (Sobrante)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                            Bs. {{ number_format($dif, 2) }} (Faltante)
                                        </span>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($s->estado === 'Abierta')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Abierta
                                    </span>
                                @elseif($s->estado === 'Cuadrada')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Cuadrada
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        {{ $s->estado }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="{{ route('caja.show', $s->id_sesion) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition text-decoration-none">
                                    <i class="fa-solid fa-file-invoice-dollar"></i> Acta de Arqueo
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-10 text-center text-slate-400 text-sm">
                                No se encontraron sesiones de caja registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sesiones->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $sesiones->links() }}
            </div>
        @endif
    </div>
</div>

@if($sesionAbierta)
<script>
    const efectivoEsperadoGaveta = {{ round($arqueo['efectivo_esperado_gaveta'], 2) }};

    function toggleCalculadoraBilletes() {
        const panel = document.getElementById('panelCalculadoraBilletes');
        panel.classList.toggle('hidden');
    }

    function limpiarCalculadoraBilletes() {
        document.querySelectorAll('.input-corte-bs').forEach(inp => inp.value = 0);
        document.getElementById('detalle_billetes').value = '';
        recalcularDesdeBilletes();
    }

    function recalcularDesdeBilletes() {
        let suma = 0;
        let desglose = [];
        document.querySelectorAll('.input-corte-bs').forEach(inp => {
            const cant = parseInt(inp.value || '0', 10);
            const corte = parseFloat(inp.getAttribute('data-corte'));
            if (cant > 0) {
                suma += cant * corte;
                desglose.push(cant + 'xBs.' + corte);
            }
        });
        document.getElementById('efectivo_fisico_contado').value = suma.toFixed(2);
        document.getElementById('detalle_billetes').value = desglose.join(', ');
        actualizarDiferenciaArqueo();
    }

    function actualizarDiferenciaArqueo() {
        const inputContado = document.getElementById('efectivo_fisico_contado');
        const contado = parseFloat(inputContado.value || '0');
        const diff = +(contado - efectivoEsperadoGaveta).toFixed(2);

        const card = document.getElementById('cardResultadoArqueo');
        const label = document.getElementById('labelEstadoArqueo');
        const icono = document.getElementById('iconoEstadoArqueo');
        const monto = document.getElementById('montoDiferenciaArqueo');
        const subtexto = document.getElementById('subtextoEstadoArqueo');

        if (Math.abs(diff) < 0.01) {
            card.className = 'p-4 rounded-xl border-2 border-emerald-300 bg-emerald-50 flex flex-col justify-between';
            label.className = 'text-xs font-bold uppercase tracking-wider text-emerald-800';
            label.textContent = 'CUADRE PERFECTO';
            icono.className = 'fa-solid fa-circle-check text-emerald-600 text-lg';
            monto.className = 'text-2xl font-black text-emerald-800 mb-0';
            monto.textContent = 'Bs. 0.00';
            subtexto.className = 'text-[11px] text-emerald-700 font-medium mb-0';
            subtexto.textContent = 'El efectivo físico coincide exactamente con el esperado.';
        } else if (diff > 0) {
            card.className = 'p-4 rounded-xl border-2 border-sky-300 bg-sky-50 flex flex-col justify-between';
            label.className = 'text-xs font-bold uppercase tracking-wider text-sky-800';
            label.textContent = 'SOBRANTE EN CAJA';
            icono.className = 'fa-solid fa-circle-arrow-up text-sky-600 text-lg';
            monto.className = 'text-2xl font-black text-sky-800 mb-0';
            monto.textContent = '+ Bs. ' + diff.toFixed(2);
            subtexto.className = 'text-[11px] text-sky-700 font-medium mb-0';
            subtexto.textContent = 'Existe más efectivo físico que el registrado por el sistema.';
        } else {
            card.className = 'p-4 rounded-xl border-2 border-rose-300 bg-rose-50 flex flex-col justify-between';
            label.className = 'text-xs font-bold uppercase tracking-wider text-rose-800';
            label.textContent = 'FALTANTE EN CAJA';
            icono.className = 'fa-solid fa-triangle-exclamation text-rose-600 text-lg';
            monto.className = 'text-2xl font-black text-rose-800 mb-0';
            monto.textContent = '- Bs. ' + Math.abs(diff).toFixed(2);
            subtexto.className = 'text-[11px] text-rose-700 font-medium mb-0';
            subtexto.textContent = 'Atención: Falta efectivo respecto al saldo esperado en gaveta.';
        }
    }
</script>
@endif
@endsection
