<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NotaVenta;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\BitacoraAuditoria;
use App\Models\Usuario;
use App\Services\ExportacionService;
use App\Services\ExportadorUniversalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class VentaController extends Controller
{
    /**
     * Historial paginado de Notas de Venta con filtros, resumen y anulación controlada.
     */
    public function index(Request $request, ExportadorUniversalService $exportador)
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

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderByDesc('id_venta')->get();
            $encabezados = ['Folio', 'Fecha y Hora', 'Cliente', 'CI / NIT', 'Vendedor', 'Caja', 'Método Pago', 'Descuento (Bs.)', 'Total Neto (Bs.)', 'Estado'];
            $filas = $lista->map(fn($v) => [
                'V-' . str_pad($v->id_venta, 5, '0', STR_PAD_LEFT),
                optional($v->fecha_hora)->format('d/m/Y H:i') ?? '-',
                $v->cliente->nombre_razon_social ?? 'Mostrador',
                $v->cliente->ci_nit ?: 'S/N',
                $v->vendedor->nombre_completo ?? 'Sistema',
                '#' . ($v->id_sesion_caja ?? '-'),
                $v->metodo_pago,
                round((float) $v->descuento_total, 2),
                round((float) $v->total_venta, 2),
                $v->estado,
            ])->toArray();

            $completadas = $lista->where('estado', 'Completada');
            $totales = [
                'TOTALES (Completadas)',
                '',
                '',
                '',
                '',
                '',
                '',
                round((float) $completadas->sum('descuento_total'), 2),
                round((float) $completadas->sum('total_venta'), 2),
                '',
            ];

            return $exportador->exportarLista(
                $export,
                'historial_ventas_' . now()->format('Ymd_His'),
                'Historial Consolidado de Notas de Venta',
                ['Método: ' . ($request->metodo_pago ?: 'Todos') . ' | Estado: ' . ($request->estado ?: 'Todos')],
                $encabezados,
                $filas,
                ['Ventas Listadas' => (string) $lista->count(), 'Total Completadas' => 'Bs. ' . number_format((float) $completadas->sum('total_venta'), 2)],
                $totales,
                'landscape'
            );
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

        // Catálogo de repuestos activos con su costo base mínimo y compatibilidad vehicular para búsqueda rápida en POS
        $productos = Producto::with(['categoria', 'codigosAlternativos', 'vehiculos', 'detallesCompras' => function ($q) {
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

                $vehiculosStr = $p->vehiculos->map(fn($v) => trim("{$v->marca} {$v->modelo} ({$v->anio_inicio}-{$v->anio_fin})"))->implode(', ');

                return [
                    'id_producto'     => $p->id_producto,
                    'codigo_interno'  => $p->codigo_interno,
                    'codigo_oem'      => $p->codigo_oem,
                    'codigos_alt'     => $p->codigosAlternativos->pluck('codigo_referencia')->implode(', '),
                    'vehiculos'       => $vehiculosStr,
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

        $descuentoGlobal = round((float)($request->descuento_total ?? 0), 2);
        $netoCalculado = round(max(0, $brutoCalculado - $descuentoGlobal), 2);

        if ($netoCalculado <= 0) {
            return back()->withInput()->with('error', 'El importe neto a cobrar debe ser mayor a Bs. 0.00.');
        }

        // Validación estricta de cobertura de cobro multicanal (CU29)
        $montoRecibido = $request->filled('monto_recibido') ? round((float)$request->monto_recibido, 2) : $netoCalculado;
        if ($request->metodo_pago === 'Efectivo' && $montoRecibido + 0.01 < $netoCalculado) {
            return back()->withInput()->with('error', 'El monto recibido en efectivo (Bs. ' . number_format($montoRecibido, 2) . ') es menor al total neto a cobrar (Bs. ' . number_format($netoCalculado, 2) . ').');
        }

        $montoEfectivoMixto = round((float)($request->monto_efectivo ?? 0), 2);
        $montoDigitalMixto = round((float)($request->monto_digital ?? 0), 2);
        if ($request->metodo_pago === 'Mixto') {
            if ($montoEfectivoMixto <= 0 || $montoDigitalMixto <= 0) {
                return back()->withInput()->with('error', 'En el cobro Mixto debe ingresar montos mayores a cero tanto en Efectivo como en QR/Tarjeta.');
            }
            if (abs(($montoEfectivoMixto + $montoDigitalMixto) - $netoCalculado) > 0.05) {
                return back()->withInput()->with('error', 'En el cobro Mixto la suma de Efectivo (Bs. ' . number_format($montoEfectivoMixto, 2) . ') + QR/Tarjeta (Bs. ' . number_format($montoDigitalMixto, 2) . ') debe coincidir con el total neto (Bs. ' . number_format($netoCalculado, 2) . ').');
            }
        }

        $cambioDevuelto = $request->metodo_pago === 'Efectivo' ? round(max(0, $montoRecibido - $netoCalculado), 2) : 0.00;

        $detallesPago = [
            'metodo_pago'     => $request->metodo_pago,
            'monto_recibido'  => $montoRecibido,
            'cambio_devuelto' => $cambioDevuelto,
            'nro_referencia'  => $request->nro_referencia ? trim($request->nro_referencia) : null,
            'monto_efectivo'  => $request->metodo_pago === 'Mixto' ? $montoEfectivoMixto : ($request->metodo_pago === 'Efectivo' ? $netoCalculado : 0),
            'monto_digital'   => $request->metodo_pago === 'Mixto' ? $montoDigitalMixto : ($request->metodo_pago !== 'Efectivo' ? $netoCalculado : 0),
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

            return redirect()->route('ventas.ticket', ['id' => $idVenta, 'autoprint' => 1])
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
     * Descarga el Comprobante de Nota de Venta en PDF institucional (GET /ventas/{id}/pdf).
     */
    public function descargarPdf($id)
    {
        $venta = NotaVenta::with(['cliente', 'vendedor.rol', 'sesionCaja.cajero', 'detalles.producto.categoria'])
            ->findOrFail($id);

        $desgloseCobro = $this->obtenerDesgloseCobro($venta->id_venta);
        $folio = 'V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('ventas.pdf_comprobante', compact('venta', 'desgloseCobro'))
            ->setPaper('letter', 'portrait');

        return $pdf->download("comprobante_venta_{$folio}.pdf");
    }

    /**
     * Procesa compra directa virtual desde la vitrina pública / carrito del Cliente Web (Rol 5).
     */
    public function storeCompraWeb(Request $request)
    {
        $request->validate([
            'ci_nit'              => 'nullable|string|max:30',
            'metodo_pago'         => 'required|string|in:Transferencia/QR,Tarjeta Débito/Crédito',
            'nro_referencia'      => 'nullable|string|max:80',
            'nota_entrega'        => 'nullable|string|max:200',
            'items'               => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'    => 'required|integer|min:1',
        ], [
            'items.required'       => 'Tu carrito está vacío. Agrega al menos un repuesto.',
            'metodo_pago.required' => 'Selecciona un método de pago digital.',
        ]);

        $usuario = auth()->user();
        $cliente = $this->resolverClienteWeb($usuario, $request->ci_nit);

        $itemsVenta = [];
        $totalWeb = 0.0;
        foreach ($request->items as $it) {
            $prod = Producto::findOrFail((int) $it['id_producto']);
            $cant = (int) $it['cantidad'];
            if (!$prod->estado || (int) $prod->stock_actual < $cant) {
                return back()->with('error', "Stock insuficiente para '{$prod->nombre_pieza}' (disponible: {$prod->stock_actual}).");
            }
            $precio = (float) $prod->precio_venta_sugerido;
            if ((float) $cliente->descuento_frecuente > 0) {
                $precio = round($precio * (1 - ((float) $cliente->descuento_frecuente / 100)), 2);
            }
            $totalWeb += ($cant * $precio);
            $itemsVenta[] = [
                'id_producto' => (int) $prod->id_producto,
                'cantidad'    => $cant,
                'precio'      => $precio,
            ];
        }

        $sesionAbierta = SesionCaja::where('estado', 'Abierta')
            ->orderByDesc('id_sesion')
            ->first();

        try {
            $ip = $request->ip() ?? '127.0.0.1';
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ip]);

            if ($sesionAbierta) {
                $vendedorId = $sesionAbierta->id_usuario_cajero ?: Usuario::whereHas('rol', fn($q) => $q->whereIn('nombre_rol', ['Administrador', 'Cajero', 'Vendedor']))->orderBy('id_usuario')->value('id_usuario');
                $detallesPago = [
                    'origen'          => 'TIENDA_VIRTUAL_WEB',
                    'id_usuario_web'  => (int) $usuario->id_usuario,
                    'metodo_pago'     => $request->metodo_pago,
                    'nro_referencia'  => $request->nro_referencia ? trim($request->nro_referencia) : 'WEB-' . now()->format('YmdHis'),
                    'nota_entrega'    => $request->nota_entrega ? trim($request->nota_entrega) : 'Retiro en tienda / Coordinación entrega',
                    'monto_efectivo'  => 0,
                    'monto_digital'   => round($totalWeb, 2),
                ];

                $res = DB::select("CALL sp_registrar_venta(?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, NULL)", [
                    (int) $cliente->id_cliente,
                    (int) $vendedorId,
                    (int) $sesionAbierta->id_sesion,
                    $request->metodo_pago,
                    0.00,
                    json_encode($itemsVenta),
                    json_encode($detallesPago),
                ]);

                $idVenta = $res[0]->p_id_venta ?? NotaVenta::max('id_venta');

                return redirect()->route('mi-comprobante.show', $idVenta)
                    ->with('success', "¡Compra confirmada! Tu Nota de Venta Digital #V-" . str_pad($idVenta, 5, '0', STR_PAD_LEFT) . " está lista para descargar en PDF.");
            }

            // Si la tienda no tiene sesión de caja abierta actualmente, resguardar como proforma reservada vía Smart-DB
            $res = DB::select("CALL sp_registrar_cotizacion(?, ?, ?, ?::jsonb, NULL)", [
                (int) $cliente->id_cliente,
                (int) $usuario->id_usuario,
                7,
                json_encode($itemsVenta),
            ]);
            $idCotizacion = $res[0]->p_id_cotizacion ?? DB::table('cotizaciones')->max('id_cotizacion');

            return redirect()->route('cotizaciones.show', $idCotizacion)
                ->with('success', "Pedido web resguardado como Proforma #COT-" . str_pad($idCotizacion, 5, '0', STR_PAD_LEFT) . " (caja física en cierre). Puedes descargar el PDF ahora.");
        } catch (\Throwable $e) {
            return back()->with('error', $this->extraerMensajePg($e->getMessage()));
        }
    }

    /**
     * Vista de comprobante digital para Cliente Web (protegida por titularidad).
     */
    public function miComprobanteShow($id)
    {
        $venta = NotaVenta::with(['cliente', 'vendedor.rol', 'sesionCaja.cajero', 'detalles.producto.categoria'])
            ->findOrFail($id);

        $desgloseCobro = $this->obtenerDesgloseCobro($venta->id_venta);
        $this->autorizarComprobanteWeb($venta, $desgloseCobro);

        return view('ventas.mi_comprobante', compact('venta', 'desgloseCobro'));
    }

    /**
     * Descarga PDF del comprobante del Cliente Web (GET /mi-comprobante/{id}/pdf).
     */
    public function miComprobantePdf($id)
    {
        $venta = NotaVenta::with(['cliente', 'vendedor.rol', 'sesionCaja.cajero', 'detalles.producto.categoria'])
            ->findOrFail($id);

        $desgloseCobro = $this->obtenerDesgloseCobro($venta->id_venta);
        $this->autorizarComprobanteWeb($venta, $desgloseCobro);

        $folio = 'V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT);
        $pdf = Pdf::loadView('ventas.pdf_comprobante', compact('venta', 'desgloseCobro'))
            ->setPaper('letter', 'portrait');

        return $pdf->download("comprobante_digital_{$folio}.pdf");
    }

    private function obtenerDesgloseCobro(int $idVenta): ?array
    {
        $bitacoraVenta = BitacoraAuditoria::where('modulo_afectado', 'Ventas / POS')
            ->where('accion', 'INSERT')
            ->where('registro_id', $idVenta)
            ->orderByDesc('id_bitacora')
            ->first();

        if (!$bitacoraVenta) {
            return null;
        }

        $decoded = json_decode($bitacoraVenta->detalle_cambio, true);
        return $decoded['desglose_cobro'] ?? null;
    }

    private function buscarClienteWeb($usuario): ?Cliente
    {
        $clientePorNombre = Cliente::whereRaw('LOWER(TRIM(nombre_razon_social)) = LOWER(TRIM(?))', [$usuario->nombre_completo])
            ->first();

        if ($clientePorNombre) {
            return $clientePorNombre;
        }

        $tel = trim((string) ($usuario->telefono ?? ''));
        $esGenerico = $tel === '' || strlen($tel) < 6 || preg_match('/^(0+|s\/n|n\/a|-+)$/i', $tel);
        if (!$esGenerico) {
            return Cliente::where('telefono', $tel)
                ->whereRaw("LOWER(TRIM(nombre_razon_social)) NOT IN ('cliente mostrador', 'consumidor final', 'publico general')")
                ->first();
        }

        return null;
    }

    private function resolverClienteWeb($usuario, ?string $ciNit = null): Cliente
    {
        $cliente = $this->buscarClienteWeb($usuario);

        if (!$cliente) {
            $cliente = Cliente::create([
                'nombre_razon_social' => $usuario->nombre_completo,
                'ci_nit'              => $ciNit ? trim($ciNit) : 'S/N',
                'telefono'            => $usuario->telefono,
                'tipo_cliente'        => 'Particular',
                'descuento_frecuente' => 0.00,
            ]);
        } elseif ($ciNit && ($cliente->ci_nit === 'S/N' || empty($cliente->ci_nit))) {
            $cliente->update(['ci_nit' => trim($ciNit)]);
        }

        return $cliente;
    }

    private function autorizarComprobanteWeb(NotaVenta $venta, ?array $desgloseCobro): void
    {
        $usuario = auth()->user();
        $esPersonalInterno = in_array($usuario->rol?->nombre_rol, ['Administrador', 'Vendedor', 'Cajero'], true)
            || in_array((int) $usuario->id_rol, [1, 2, 3], true);

        if ($esPersonalInterno) {
            return;
        }

        $idUsuarioWeb = (int) ($desgloseCobro['id_usuario_web'] ?? 0);
        if ($idUsuarioWeb > 0 && $idUsuarioWeb === (int) $usuario->id_usuario) {
            return;
        }

        $clienteWeb = $this->buscarClienteWeb($usuario);
        if (!$clienteWeb || (int) $venta->id_cliente !== (int) $clienteWeb->id_cliente) {
            abort(403, 'No tiene permiso para acceder o descargar este comprobante.');
        }
    }

    /**
     * Exporta la Nota de Venta en PDF o Excel (.xlsx).
     */
    public function exportar($id, string $formato, ExportacionService $exportacion)
    {
        $venta = NotaVenta::with(['cliente', 'vendedor.rol', 'sesionCaja.cajero', 'detalles.producto.categoria'])
            ->findOrFail($id);

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

        $encabezados = ['Código Interno', 'Repuesto / Pieza', 'Categoría', 'Cantidad', 'Precio Unit. (Bs.)', 'Subtotal (Bs.)'];

        $filas = $venta->detalles->map(function ($det) {
            return [
                $det->producto->codigo_interno ?? '-',
                $det->producto->nombre_pieza ?? ('Repuesto #' . $det->id_producto),
                $det->producto->categoria->nombre ?? 'General',
                (int) $det->cantidad_vendida,
                round((float) $det->precio_unitario_cobrado, 2),
                round((float) $det->subtotal, 2),
            ];
        })->all();

        $codigo = 'V-' . str_pad($venta->id_venta, 5, '0', STR_PAD_LEFT);
        $kpis = [
            'Comprobante'   => '#' . $codigo . ' (' . $venta->estado . ')',
            'Cliente'       => $venta->cliente->nombre_razon_social ?? 'Público General',
            'Medio de Pago' => $venta->metodo_pago,
            'Descuento'     => 'Bs. ' . number_format((float) $venta->descuento_total, 2),
            'Total Cobrado' => 'Bs. ' . number_format((float) $venta->total_venta, 2),
        ];

        $subtitulos = [
            'Fecha de venta: ' . optional($venta->fecha_hora)->format('d/m/Y H:i')
                . ' | Vendedor: ' . ($venta->vendedor->nombre_completo ?? 'Sistema')
                . ' | Caja #' . ($venta->id_sesion_caja ?? '-'),
            'CI / NIT Cliente: ' . ($venta->cliente->ci_nit ?? '-')
                . ($venta->estado === 'Anulada' && $venta->motivo_anulacion ? ' | Motivo Anulación: ' . $venta->motivo_anulacion : ''),
        ];

        if ($desgloseCobro && is_array($desgloseCobro)) {
            $partes = [];
            if (isset($desgloseCobro['monto_efectivo'])) {
                $partes[] = 'Efectivo: Bs. ' . number_format((float) $desgloseCobro['monto_efectivo'], 2);
            }
            if (isset($desgloseCobro['monto_digital'])) {
                $partes[] = 'Digital: Bs. ' . number_format((float) $desgloseCobro['monto_digital'], 2);
            }
            if (!empty($desgloseCobro['nro_referencia'])) {
                $partes[] = 'Ref.: ' . $desgloseCobro['nro_referencia'];
            }
            if (!empty($partes)) {
                $subtitulos[] = 'Desglose de cobro: ' . implode(' | ', $partes);
            }
        }

        $totales = ['', '', '', (int) $venta->detalles->sum('cantidad_vendida'), 'TOTAL NETO (Bs.):', round((float) $venta->total_venta, 2)];
        $nombreArchivo = 'nota_venta_' . $codigo . '_' . now()->format('Ymd_His');

        if ($formato === 'excel') {
            return $exportacion->exportarExcel(
                $nombreArchivo,
                'NOTA DE VENTA #' . $codigo,
                $subtitulos,
                $encabezados,
                $filas,
                $kpis,
                $totales
            );
        }

        return $exportacion->exportarPdf(
            $nombreArchivo,
            'Nota de Venta #' . $codigo,
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            $totales,
            'portrait',
            'Documento de venta emitido por Multipartes El Tuerca.'
        );
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

