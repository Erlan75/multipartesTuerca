<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NotaVenta;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\BitacoraAuditoria;
use Carbon\Carbon;

class VentaController extends Controller
{
    /**
     * Historial paginado de Notas de Venta con filtros, resumen y anulación controlada.
     */
    public function index(Request $request)
    {
        $query = NotaVenta::with(['cliente', 'vendedor', 'sesionCaja', 'detalles.producto']);

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                if (is_numeric($buscar)) {
                    $q->orWhere('id_venta', (int)$buscar);
                }
                $q->orWhereHas('cliente', function ($cq) use ($buscar) {
                    $cq->where('nombre_razon_social', 'ilike', "%{$buscar}%")
                       ->orWhere('ci_nit', 'ilike', "%{$buscar}%");
                });
            });
        }

        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->metodo_pago);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_hora', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_hora', '<=', $request->fecha_hasta);
        }

        $ventas = $query->orderByDesc('id_venta')
            ->paginate(15)
            ->withQueryString();

        // Métricas analíticas desde la función PL/pgSQL fn_obtener_metricas_pos
        $metricasHoy = DB::selectOne("SELECT * FROM fn_obtener_metricas_pos(CURRENT_DATE)");

        $totalHistorico = NotaVenta::where('estado', 'Completada')->sum('total_venta');
        $totalAnuladas = NotaVenta::where('estado', 'Anulada')->count();

        return view('ventas.index', compact('ventas', 'metricasHoy', 'totalHistorico', 'totalAnuladas'));
    }

    /**
     * Pantalla de Punto de Venta (POS) rápida y responsiva.
     */
    public function pos(Request $request)
    {
        $clientes = Cliente::orderBy('id_cliente')->get();

        // Obtener sesión de caja abierta (priorizando la del usuario autenticado si existe)
        $sesionActiva = SesionCaja::where('estado', 'Abierta')
            ->orderByRaw('CASE WHEN id_usuario_cajero = ? THEN 0 ELSE 1 END', [auth()->id()])
            ->orderByDesc('id_sesion')
            ->first();

        // Catálogo de repuestos activos con su costo base mínimo para validación preventiva en UI
        $productos = Producto::with(['categoria', 'codigosAlternativos', 'detallesCompras' => function ($q) {
                $q->whereHas('compra', fn($cq) => $cq->where('estado_pago', '<>', 'Anulado'))
                  ->orderByDesc('id_detalle_compra');
            }])
            ->where('estado', true)
            ->orderBy('nombre_pieza')
            ->get()
            ->map(function ($p) {
                $ultimoCosto = $p->detallesCompras->first()?->costo_unitario;
                $costoBase = $ultimoCosto
                    ? (float)$ultimoCosto
                    : round((float)$p->precio_venta_sugerido / (1 + (((float)$p->margen_ganancia ?: 25.0) / 100.0)), 2);

                return [
                    'id_producto'     => $p->id_producto,
                    'codigo_interno'  => $p->codigo_interno,
                    'codigo_oem'      => $p->codigo_oem,
                    'codigos_alt'     => $p->codigosAlternativos->pluck('codigo_referencia')->implode(', '),
                    'nombre_pieza'    => $p->nombre_pieza,
                    'marca'           => $p->marca_fabricante ?? 'Original',
                    'categoria'       => $p->categoria->nombre ?? 'Repuesto',
                    'precio_sugerido' => (float)$p->precio_venta_sugerido,
                    'costo_minimo'    => $costoBase,
                    'stock_actual'    => (int)$p->stock_actual,
                    'ubicacion'       => trim(($p->pasillo ?? '-') . ' / ' . ($p->estante ?? '-') . ' / ' . ($p->nivel_gaveta ?? '-')),
                ];
            });

        $clientePreseleccionado = $request->input('id_cliente', 1);

        return view('ventas.pos', compact('clientes', 'sesionActiva', 'productos', 'clientePreseleccionado'));
    }

    /**
     * Procesar y registrar Nota de Venta en el POS mediante CALL sp_registrar_venta(...).
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_cliente'          => 'required|integer|exists:clientes,id_cliente',
            'id_sesion_caja'      => 'required|integer|exists:sesiones_cajas,id_sesion',
            'metodo_pago'         => 'required|string|in:Efectivo,Transferencia/QR,Tarjeta Débito/Crédito,Mixto',
            'descuento_total'     => 'nullable|numeric|min:0',
            'items'               => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'    => 'required|integer|min:1',
            'items.*.precio'      => 'required|numeric|min:0.01',
            'monto_recibido'      => 'nullable|numeric|min:0',
            'nro_referencia'      => 'nullable|string|max:100',
            'monto_efectivo'      => 'nullable|numeric|min:0',
            'monto_digital'       => 'nullable|numeric|min:0',
        ], [
            'id_cliente.required'     => 'Seleccione el cliente de la venta.',
            'id_sesion_caja.required' => 'No existe una sesión de caja abierta para procesar el cobro.',
            'metodo_pago.required'    => 'Seleccione la modalidad de cobro.',
            'items.required'          => 'Agregue al menos un repuesto al carrito del POS.',
        ]);

        $itemsJson = [];
        $brutoCalculado = 0;
        foreach ($request->items as $it) {
            $cant = (int)$it['cantidad'];
            $precio = (float)$it['precio'];
            $brutoCalculado += ($cant * $precio);
            $itemsJson[] = [
                'id_producto' => (int)$it['id_producto'],
                'cantidad'    => $cant,
                'precio'      => $precio,
            ];
        }

        $descuentoGlobal = (float)($request->descuento_total ?? 0);
        $netoCalculado = max(0, $brutoCalculado - $descuentoGlobal);
        $montoRecibido = (float)($request->monto_recibido ?? $netoCalculado);
        $cambioDevuelto = $request->metodo_pago === 'Efectivo' ? max(0, $montoRecibido - $netoCalculado) : 0;

        $detallesPago = [
            'metodo_pago'     => $request->metodo_pago,
            'monto_recibido'  => $montoRecibido,
            'cambio_devuelto' => $cambioDevuelto,
            'nro_referencia'  => $request->nro_referencia ? trim($request->nro_referencia) : null,
            'monto_efectivo'  => $request->metodo_pago === 'Mixto' ? (float)($request->monto_efectivo ?? 0) : ($request->metodo_pago === 'Efectivo' ? $netoCalculado : 0),
            'monto_digital'   => $request->metodo_pago === 'Mixto' ? (float)($request->monto_digital ?? 0) : ($request->metodo_pago !== 'Efectivo' ? $netoCalculado : 0),
            'autorizacion_descuento' => $request->autorizacion_descuento ? trim($request->autorizacion_descuento) : null,
        ];

        try {
            $ip = $request->ip() ?? '127.0.0.1';
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ip]);

            $res = DB::select("CALL sp_registrar_venta(?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, NULL)", [
                (int)$request->id_cliente,
                auth()->id(),
                (int)$request->id_sesion_caja,
                $request->metodo_pago,
                $descuentoGlobal,
                json_encode($itemsJson),
                json_encode($detallesPago),
            ]);

            $idVenta = $res[0]->p_id_venta ?? NotaVenta::max('id_venta');

            return redirect()->route('ventas.ticket', $idVenta)
                ->with('success', "Venta #V-" . str_pad($idVenta, 5, '0', STR_PAD_LEFT) . " procesada exitosamente por Bs. " . number_format($netoCalculado, 2) . ".");
        } catch (\Throwable $e) {
            $mensaje = $this->extraerMensajePg($e->getMessage());
            return back()->withInput()->with('error', $mensaje);
        }
    }

    /**
     * Comprobante de venta en dos formatos: Estándar y Ticket Térmico 80mm listo para window.print().
     */
    public function ticket(Request $request, $id)
    {
        $venta = NotaVenta::with(['cliente', 'vendedor.rol', 'sesionCaja.cajero', 'detalles.producto.categoria'])
            ->findOrFail($id);

        // Recuperar desglose de cobro desde bitácora si existe
        $bitacoraVenta = BitacoraAuditoria::where('modulo_afectado', 'Ventas / POS')
            ->where('accion', 'INSERT')
            ->where('registro_id', $venta->id_venta)
            ->orderByDesc('id_bitacora')
            ->first();

        $desgloseCobro = null;
        if ($bitacoraVenta) {
            $decoded = json_decode($bitacoraVenta->detalle_cambio, true);
            $desgloseCobro = $decoded['desglose_cobro'] ?? null;
        }

        $formato = $request->input('formato', 'termico'); // 'termico' (80mm) o 'estandar'

        return view('ventas.ticket', compact('venta', 'desgloseCobro', 'formato'));
    }

    /**
     * Anular una Nota de Venta ejecutando CALL sp_anular_venta(...).
     */
    public function anular(Request $request, $id)
    {
        $request->validate([
            'motivo_anulacion' => 'required|string|min:5|max:500',
        ], [
            'motivo_anulacion.required' => 'Debe especificar el motivo obligatorio de anulación.',
            'motivo_anulacion.min'      => 'La justificación de anulación debe tener al menos 5 caracteres.',
        ]);

        try {
            $ip = $request->ip() ?? '127.0.0.1';
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ip]);

            DB::statement("CALL sp_anular_venta(?, ?, ?)", [
                (int)$id,
                auth()->id(),
                trim($request->motivo_anulacion),
            ]);

            return back()->with('success', "La Nota de Venta #V-" . str_pad($id, 5, '0', STR_PAD_LEFT) . " fue anulada exitosamente. El stock de repuestos fue restituido al almacén y el saldo de caja fue ajustado.");
        } catch (\Throwable $e) {
            $mensaje = $this->extraerMensajePg($e->getMessage());
            return back()->with('error', $mensaje);
        }
    }

    private function extraerMensajePg(string $mensaje): string
    {
        if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $mensaje, $matches)) {
            return trim($matches[1]);
        }
        return $mensaje;
    }
}

