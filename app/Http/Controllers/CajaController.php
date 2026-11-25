<?php

namespace App\Http\Controllers;

use App\Models\BitacoraAuditoria;
use App\Models\MovimientoCaja;
use App\Models\NotaVenta;
use App\Models\SesionCaja;
use App\Services\ExportadorUniversalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    /**
     * Panel principal de Tesorería y Sesiones de Caja (CU32, CU33, CU34).
     */
    public function index(Request $request, ExportadorUniversalService $exportador)
    {
        $user = Auth::user();

        // Buscar la sesión de caja abierta actualmente (priorizando la del usuario actual)
        $sesionAbierta = SesionCaja::with(['cajero', 'movimientos'])
            ->where('estado', 'Abierta')
            ->orderByRaw('CASE WHEN id_usuario_cajero = ? THEN 0 ELSE 1 END', [$user->id_usuario])
            ->orderByDesc('id_sesion')
            ->first();

        // Calcular el desglose financiero en tiempo real para la sesión abierta (CU34)
        $arqueo = null;
        if ($sesionAbierta) {
            $arqueo = $this->calcularDesgloseSesion($sesionAbierta);
        }

        // Historial paginado de sesiones de caja
        $querySesiones = SesionCaja::with(['cajero'])
            ->withCount(['ventas as total_operaciones_venta' => function ($q) {
                $q->where('estado', 'Completada');
            }]);

        if ($request->filled('estado')) {
            $querySesiones->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $querySesiones->whereDate('fecha_hora_apertura', '>=', $request->fecha_desde);
        }

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $querySesiones->orderByDesc('id_sesion')->get();
            $encabezados = ['Sesión', 'Cajero', 'Apertura', 'Cierre', 'Base Inicial (Bs.)', 'Esperado Sistema (Bs.)', 'Contado Real (Bs.)', 'Diferencia (Bs.)', 'Ventas', 'Estado'];
            $filas = $lista->map(fn($s) => [
                'CAJA-' . str_pad($s->id_sesion, 4, '0', STR_PAD_LEFT),
                $s->cajero->nombre_completo ?? 'Sistema',
                optional($s->fecha_hora_apertura)->format('d/m/Y H:i') ?? '-',
                $s->fecha_hora_cierre ? $s->fecha_hora_cierre->format('d/m/Y H:i') : 'En curso',
                round((float) $s->monto_inicial, 2),
                round((float) $s->monto_esperado_sistema, 2),
                $s->monto_real_contado !== null ? round((float) $s->monto_real_contado, 2) : '-',
                $s->diferencia !== null ? round((float) $s->diferencia, 2) : '-',
                (int) $s->total_operaciones_venta,
                $s->estado,
            ])->toArray();

            return $exportador->exportarLista(
                $export,
                'sesiones_caja_' . now()->format('Ymd_His'),
                'Historial de Sesiones de Caja y Arqueos',
                ['Estado: ' . ($request->estado ?: 'Todas') . ' | Desde: ' . ($request->fecha_desde ?: 'Inicio')],
                $encabezados,
                $filas,
                ['Total Sesiones' => (string) $lista->count(), 'Total Esperado Sistema' => 'Bs. ' . number_format((float) $lista->sum('monto_esperado_sistema'), 2)],
                ['TOTALES', '', '', '', round((float) $lista->sum('monto_inicial'), 2), round((float) $lista->sum('monto_esperado_sistema'), 2), '', '', (int) $lista->sum('total_operaciones_venta'), ''],
                'landscape'
            );
        }

        $sesiones = $querySesiones->orderByDesc('id_sesion')
            ->paginate(10)
            ->withQueryString();

        return view('caja.index', compact('sesionAbierta', 'arqueo', 'sesiones'));
    }

    /**
     * Ver el detalle completo y acta de arqueo de una sesión de caja específica.
     */
    public function show($id)
    {
        $sesion = SesionCaja::with([
            'cajero',
            'movimientos',
            'ventas.cliente',
            'ventas.vendedor',
        ])->findOrFail($id);

        $arqueo = $this->calcularDesgloseSesion($sesion);

        // Recuperar observaciones del cierre desde bitácora si existen
        $bitacoraCierre = BitacoraAuditoria::where('modulo_afectado', 'Tesorería / Caja')
            ->where('accion', 'CIERRE_CAJA')
            ->where('registro_id', $sesion->id_sesion)
            ->orderByDesc('id_bitacora')
            ->first();

        $observacionesCierre = null;
        if ($bitacoraCierre) {
            $datos = json_decode($bitacoraCierre->detalle_cambio, true);
            $observacionesCierre = $datos['observaciones'] ?? null;
        }

        return view('caja.show', compact('sesion', 'arqueo', 'observacionesCierre'));
    }

    /**
     * Exporta el Acta de Arqueo y Movimientos de una sesión de caja en PDF o Excel (.xlsx).
     */
    public function exportar($id, string $formato, ExportadorUniversalService $exportacion)
    {
        $sesion = SesionCaja::with([
            'cajero',
            'movimientos',
            'ventas.cliente',
            'ventas.vendedor',
        ])->findOrFail($id);

        $arqueo = $this->calcularDesgloseSesion($sesion);

        $bitacoraCierre = BitacoraAuditoria::where('modulo_afectado', 'Tesorería / Caja')
            ->where('accion', 'CIERRE_CAJA')
            ->where('registro_id', $sesion->id_sesion)
            ->orderByDesc('id_bitacora')
            ->first();

        $observacionesCierre = null;
        if ($bitacoraCierre) {
            $datos = json_decode($bitacoraCierre->detalle_cambio, true);
            $observacionesCierre = $datos['observaciones'] ?? null;
        }

        $encabezados = ['Hora', 'Tipo / Operación', 'Referencia / Detalle', 'Medio / Categoría', 'Estado', 'Monto (Bs.)'];

        $filas = [
            [
                optional($sesion->fecha_hora_apertura)->format('d/m/Y H:i') ?? '-',
                'Apertura de Caja',
                'Fondo inicial de gaveta',
                'Efectivo',
                'Registrado',
                round($arqueo['base_inicial'], 2),
            ],
        ];

        foreach ($sesion->ventas->sortBy('fecha_hora') as $v) {
            $filas[] = [
                optional($v->fecha_hora)->format('d/m/Y H:i') ?? '-',
                'Nota de Venta',
                '#V-' . str_pad($v->id_venta, 5, '0', STR_PAD_LEFT) . ' - ' . ($v->cliente->nombre_razon_social ?? 'Público General'),
                $v->metodo_pago,
                $v->estado,
                round((float) $v->total_venta, 2),
            ];
        }

        foreach ($sesion->movimientos->sortBy('hora') as $m) {
            $signo = $m->tipo === 'Retiro Menor' ? -1 : 1;
            $filas[] = [
                optional($m->hora)->format('d/m/Y H:i') ?? '-',
                $m->tipo,
                $m->motivo ?? '-',
                'Caja Menor',
                'Aplicado',
                round($signo * (float) $m->monto, 2),
            ];
        }

        $kpis = [
            'Sesión'             => '#CAJA-' . str_pad($sesion->id_sesion, 4, '0', STR_PAD_LEFT) . ' (' . $sesion->estado . ')',
            'Cajero'             => $sesion->cajero->nombre_completo ?? 'Sistema',
            'Base Inicial'       => 'Bs. ' . number_format($arqueo['base_inicial'], 2),
            'Ventas Efectivo'    => 'Bs. ' . number_format($arqueo['ventas_efectivo'], 2),
            'Digitales (QR/Tarj.)' => 'Bs. ' . number_format($arqueo['total_digitales'], 2),
            'Esperado Gaveta'    => 'Bs. ' . number_format($arqueo['efectivo_esperado_gaveta'], 2),
        ];

        $subtitulos = [
            'Apertura: ' . (optional($sesion->fecha_hora_apertura)->format('d/m/Y H:i') ?? '-')
                . ' | Cierre: ' . ($sesion->fecha_hora_cierre ? $sesion->fecha_hora_cierre->format('d/m/Y H:i') : 'En curso'),
            'Ingresos Extra: Bs. ' . number_format($arqueo['ingresos_extra'], 2)
                . ' | Retiros Menores: Bs. ' . number_format($arqueo['retiros_menores'], 2)
                . ($observacionesCierre ? ' | Obs. Cierre: ' . $observacionesCierre : ''),
        ];

        $totales = ['', '', '', '', 'ESPERADO EN GAVETA (EFEC.):', round($arqueo['efectivo_esperado_gaveta'], 2)];
        $codigo = str_pad($sesion->id_sesion, 4, '0', STR_PAD_LEFT);
        $nombreArchivo = 'acta_arqueo_caja_' . $codigo . '_' . now()->format('Ymd_His');

        if ($formato === 'excel') {
            return $exportacion->exportarExcel(
                $nombreArchivo,
                'ACTA DE ARQUEO Y MOVIMIENTOS DE CAJA #CAJA-' . $codigo,
                $subtitulos,
                $encabezados,
                $filas,
                $kpis,
                $totales
            );
        }

        return $exportacion->exportarPdf(
            $nombreArchivo,
            'Acta de Arqueo de Caja #CAJA-' . $codigo,
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            $totales,
            'landscape',
            'Acta de Arqueo emitida por el módulo de Tesorería — Multipartes El Tuerca.'
        );
    }

    /**
     * CU32: Aperturar sesión de caja invocando CALL sp_abrir_caja en PostgreSQL.
     */
    public function abrir(Request $request)
    {
        $validated = $request->validate([
            'monto_inicial' => 'required|numeric|min:0|max:999999.99',
        ], [
            'monto_inicial.required' => 'Debe ingresar el monto base inicial en efectivo (Bs.).',
            'monto_inicial.min'      => 'El monto inicial de apertura no puede ser negativo.',
        ]);

        try {
            $idUsuario = Auth::id();
            $ipOrigen  = $request->ip() ?? '127.0.0.1';

            DB::statement("SELECT set_config('app.id_usuario', ?, false)", [(string) $idUsuario]);
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [(string) $ipOrigen]);

            $res = DB::select('CALL sp_abrir_caja(?, ?, NULL)', [
                (int) $idUsuario,
                round((float) $validated['monto_inicial'], 2),
            ]);

            $idSesionOut = $res[0]->p_id_sesion ?? SesionCaja::max('id_sesion');

            return redirect()->route('caja.index')
                ->with('success', 'Sesión de caja #' . $idSesionOut . ' aperturada exitosamente con base inicial de Bs. ' . number_format($validated['monto_inicial'], 2) . '.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $this->extraerMensajePgsql($e->getMessage()));
        }
    }

    /**
     * CU33: Registrar movimiento menor de caja (Ingreso Extra o Retiro Menor)
     * invocando CALL sp_registrar_movimiento_caja en PostgreSQL.
     */
    public function registrarMovimiento(Request $request)
    {
        $validated = $request->validate([
            'id_sesion'       => 'required|exists:sesiones_cajas,id_sesion',
            'tipo'            => 'required|in:Ingreso Extra,Retiro Menor',
            'categoria'       => 'nullable|string|max:50',
            'comprobante'     => 'nullable|string|max:60',
            'motivo'          => 'required|string|min:4|max:180',
            'monto'           => 'required|numeric|min:0.50|max:99999.99',
        ], [
            'motivo.required' => 'El concepto o motivo del movimiento de caja es obligatorio.',
            'monto.min'       => 'El monto del movimiento debe ser mayor a cero.',
        ]);

        try {
            $idUsuario = Auth::id();
            $ipOrigen  = $request->ip() ?? '127.0.0.1';

            $sesion = SesionCaja::findOrFail($validated['id_sesion']);
            if ($sesion->estado !== 'Abierta') {
                return back()->withInput()->with('error', 'La sesión de caja seleccionada ya no se encuentra abierta.');
            }

            if ($validated['tipo'] === 'Retiro Menor') {
                $arqueoActual = $this->calcularDesgloseSesion($sesion);
                $efectivoDisponible = (float)$arqueoActual['efectivo_esperado_gaveta'];
                if (round((float)$validated['monto'], 2) > $efectivoDisponible + 0.001) {
                    return back()->withInput()->with('error', 'Efectivo físico insuficiente en gaveta para realizar el retiro. Disponible en efectivo: Bs. ' . number_format($efectivoDisponible, 2) . '.');
                }
            }

            $motivoFinal = trim($validated['motivo']);
            if (!empty($validated['categoria'])) {
                $motivoFinal = '[' . strtoupper(trim($validated['categoria'])) . '] ' . $motivoFinal;
            }
            if (!empty($validated['comprobante'])) {
                $motivoFinal .= ' (Ref/Recibo: ' . trim($validated['comprobante']) . ')';
            }
            $motivoFinal = mb_substr($motivoFinal, 0, 250);

            DB::statement("SELECT set_config('app.id_usuario', ?, false)", [(string) $idUsuario]);
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [(string) $ipOrigen]);

            DB::select('CALL sp_registrar_movimiento_caja(?, ?, ?, ?, ?, NULL)', [
                (int) $validated['id_sesion'],
                (string) $validated['tipo'],
                round((float) $validated['monto'], 2),
                (string) $motivoFinal,
                (int) $idUsuario,
            ]);

            return redirect()->route('caja.index')
                ->with('success', $validated['tipo'] . ' por Bs. ' . number_format($validated['monto'], 2) . ' registrado y conciliado en el saldo esperado de caja.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $this->extraerMensajePgsql($e->getMessage()));
        }
    }

    /**
     * CU34: Ejecutar arqueo y cierre de sesión de caja invocando CALL sp_cerrar_caja en PostgreSQL.
     */
    public function cerrar(Request $request, $id)
    {
        $sesion = SesionCaja::findOrFail($id);

        $validated = $request->validate([
            'efectivo_fisico_contado' => 'required|numeric|min:0',
            'observaciones_arqueo'    => 'nullable|string|max:500',
            'detalle_billetes'        => 'nullable|string|max:300',
        ], [
            'efectivo_fisico_contado.required' => 'Debe declarar el monto físico de efectivo contado en gaveta.',
        ]);

        try {
            $idUsuario = Auth::id();
            $ipOrigen  = $request->ip() ?? '127.0.0.1';

            $arqueo = $this->calcularDesgloseSesion($sesion);
            $efectivoFisico = round((float) $validated['efectivo_fisico_contado'], 2);

            // El sistema acumula en monto_esperado_sistema el total global (efectivo + digitales).
            // Sumamos los cobros digitales verificados al efectivo físico contado en gaveta
            // para que la diferencia refleje exactamente el sobrante/faltante físico de billetes y monedas.
            $montoRealTotalDeclarado = round($efectivoFisico + $arqueo['total_digitales'], 2);

            $obsParts = [];
            $obsParts[] = 'Efectivo físico en gaveta: Bs. ' . number_format($efectivoFisico, 2)
                        . ' (Esperado en gaveta: Bs. ' . number_format($arqueo['efectivo_esperado_gaveta'], 2) . ')';
            if ($arqueo['total_digitales'] > 0) {
                $obsParts[] = 'Cobros digitales conciliados (QR/Tarjeta): Bs. ' . number_format($arqueo['total_digitales'], 2);
            }
            if (!empty($validated['detalle_billetes'])) {
                $obsParts[] = 'Conteo: ' . trim($validated['detalle_billetes']);
            }
            if (!empty($validated['observaciones_arqueo'])) {
                $obsParts[] = 'Nota Cajero: ' . trim($validated['observaciones_arqueo']);
            }
            $observacionesFinal = implode(' | ', $obsParts);

            DB::statement("SELECT set_config('app.id_usuario', ?, false)", [(string) $idUsuario]);
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [(string) $ipOrigen]);

            DB::statement('CALL sp_cerrar_caja(?, ?, ?, ?)', [
                (int) $sesion->id_sesion,
                $montoRealTotalDeclarado,
                (string) $observacionesFinal,
                (int) $idUsuario,
            ]);

            return redirect()->route('caja.show', $sesion->id_sesion)
                ->with('success', 'Arqueo y cierre de la sesión de caja #' . $sesion->id_sesion . ' ejecutado correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $this->extraerMensajePgsql($e->getMessage()));
        }
    }

    /**
     * Calcula el desglose detallado de una sesión de caja:
     * - Base inicial + Ventas en Efectivo + Ingresos menores - Retiros menores = Efectivo Esperado en Gaveta
     * - Cobros por Transferencia / QR y Tarjeta Débito/Crédito
     */
    private function calcularDesgloseSesion(SesionCaja $sesion): array
    {
        $ventasCompletadas = NotaVenta::where('id_sesion_caja', $sesion->id_sesion)
            ->where('estado', 'Completada')
            ->get();

        $ventasAnuladas = NotaVenta::where('id_sesion_caja', $sesion->id_sesion)
            ->where('estado', 'Anulada')
            ->count();

        $ventasEfectivo = 0.0;
        $ventasQrTransferencia = 0.0;
        $ventasTarjeta = 0.0;
        $cantidadEfectivo = 0;
        $cantidadQr = 0;
        $cantidadTarjeta = 0;
        $cantidadMixto = 0;

        foreach ($ventasCompletadas as $venta) {
            $total = (float) $venta->total_venta;

            if ($venta->metodo_pago === 'Efectivo') {
                $ventasEfectivo += $total;
                $cantidadEfectivo++;
            } elseif ($venta->metodo_pago === 'Transferencia/QR') {
                $ventasQrTransferencia += $total;
                $cantidadQr++;
            } elseif ($venta->metodo_pago === 'Tarjeta Débito/Crédito') {
                $ventasTarjeta += $total;
                $cantidadTarjeta++;
            } elseif ($venta->metodo_pago === 'Mixto') {
                $cantidadMixto++;
                // Buscar desglose en bitácora si existe
                $bitacora = BitacoraAuditoria::where('modulo_afectado', 'Ventas / POS')
                    ->where('accion', 'INSERT')
                    ->where('registro_id', $venta->id_venta)
                    ->orderByDesc('id_bitacora')
                    ->first();
                $desglose = $bitacora ? (json_decode($bitacora->detalle_cambio, true)['desglose_cobro'] ?? null) : null;
                if ($desglose && isset($desglose['monto_efectivo'])) {
                    $parteEfec = (float) $desglose['monto_efectivo'];
                    $ventasEfectivo += $parteEfec;
                    $ventasQrTransferencia += max(0, $total - $parteEfec);
                } else {
                    $ventasEfectivo += $total;
                }
            } else {
                $ventasEfectivo += $total;
            }
        }

        $ingresosExtra = (float) MovimientoCaja::where('id_sesion', $sesion->id_sesion)
            ->where('tipo', 'Ingreso Extra')
            ->sum('monto');

        $retirosMenores = (float) MovimientoCaja::where('id_sesion', $sesion->id_sesion)
            ->where('tipo', 'Retiro Menor')
            ->sum('monto');

        $baseInicial = (float) $sesion->monto_inicial;
        $efectivoEsperadoGaveta = round($baseInicial + $ventasEfectivo + $ingresosExtra - $retirosMenores, 2);
        $totalDigitales = round($ventasQrTransferencia + $ventasTarjeta, 2);
        $totalVentasBruto = round($ventasEfectivo + $totalDigitales, 2);

        return [
            'base_inicial'             => $baseInicial,
            'ventas_efectivo'          => round($ventasEfectivo, 2),
            'ventas_qr_transferencia'  => round($ventasQrTransferencia, 2),
            'ventas_tarjeta'           => round($ventasTarjeta, 2),
            'total_digitales'          => $totalDigitales,
            'total_ventas_netas'       => $totalVentasBruto,
            'ingresos_extra'           => round($ingresosExtra, 2),
            'retiros_menores'          => round($retirosMenores, 2),
            'efectivo_esperado_gaveta' => $efectivoEsperadoGaveta,
            'total_esperado_sistema'   => (float) $sesion->monto_esperado_sistema,
            'cantidad_ventas'          => $ventasCompletadas->count(),
            'cantidad_anuladas'        => $ventasAnuladas,
            'cantidad_efectivo'        => $cantidadEfectivo,
            'cantidad_qr'              => $cantidadQr,
            'cantidad_tarjeta'         => $cantidadTarjeta,
            'cantidad_mixto'           => $cantidadMixto,
        ];
    }

    private function extraerMensajePgsql(string $rawError): string
    {
        if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $rawError, $matches)) {
            return trim($matches[1]);
        }
        return 'Error en operación de tesorería: ' . $rawError;
    }
}
