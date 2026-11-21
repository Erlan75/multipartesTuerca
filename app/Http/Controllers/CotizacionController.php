<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\NotaVenta;
use App\Helpers\Bitacora;
use App\Services\ExportacionService;
use App\Services\ExportadorUniversalService;
use Carbon\Carbon;

class CotizacionController extends Controller
{
    /**
     * Bandeja general de cotizaciones con validación de vigencia y filtros.
     */
    public function index(Request $request, ExportadorUniversalService $exportador)
    {
        // Sincronizar automáticamente cotizaciones cuya fecha_vencimiento ya pasó
        Cotizacion::where('estado', 'Vigente')
            ->whereDate('fecha_vencimiento', '<', Carbon::today())
            ->update(['estado' => 'Vencida']);

        $query = Cotizacion::with(['cliente', 'usuario', 'detalles.producto']);

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                if (is_numeric($buscar)) {
                    $q->orWhere('id_cotizacion', (int)$buscar);
                }
                $q->orWhereHas('cliente', function ($cq) use ($buscar) {
                    $cq->where('nombre_razon_social', 'ilike', "%{$buscar}%")
                       ->orWhere('ci_nit', 'ilike', "%{$buscar}%")
                       ->orWhere('telefono', 'ilike', "%{$buscar}%");
                });
            });
        }

        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', $request->id_cliente);
        }

        if ($request->filled('estado')) {
            $est = strtoupper(trim($request->estado));
            if ($est === 'VIGENTE') {
                $query->where('estado', 'Vigente')->whereDate('fecha_vencimiento', '>=', Carbon::today());
            } elseif ($est === 'VENCIDA') {
                $query->where(function ($q) {
                    $q->where('estado', 'Vencida')
                      ->orWhere(function ($sub) {
                          $sub->where('estado', 'Vigente')->whereDate('fecha_vencimiento', '<', Carbon::today());
                      });
                });
            } elseif (in_array($est, ['CONVERTIDA', 'CONCRETADA/FACTURADA'])) {
                $query->where('estado', 'Concretada/Facturada');
            } elseif ($est === 'ANULADA') {
                $query->where('estado', 'Anulada');
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->fecha_hasta);
        }

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderByDesc('id_cotizacion')->get();
            $encabezados = ['N° Proforma', 'Emisión', 'Vencimiento', 'Cliente', 'CI / NIT', 'Asesor', 'Estado', 'Monto Total (Bs.)'];
            $filas = $lista->map(fn($c) => [
                'COT-' . str_pad($c->id_cotizacion, 5, '0', STR_PAD_LEFT),
                optional($c->fecha_emision)->format('d/m/Y H:i') ?? '-',
                Carbon::parse($c->fecha_vencimiento)->format('d/m/Y'),
                $c->cliente->nombre_razon_social ?? '-',
                $c->cliente->ci_nit ?: 'S/N',
                $c->usuario->nombre_completo ?? 'Web',
                $c->estado_visual,
                round((float) $c->monto_total, 2),
            ])->toArray();

            return $exportador->exportarLista(
                $export,
                'cotizaciones_' . now()->format('Ymd_His'),
                'Listado de Cotizaciones y Proformas Comerciales',
                ['Estado: ' . ($request->estado ?: 'Todos') . ' | Búsqueda: ' . ($request->buscar ?: 'Todas')],
                $encabezados,
                $filas,
                ['Total Proformas' => (string) $lista->count()],
                ['TOTALES', '', '', '', '', '', '', round((float) $lista->sum('monto_total'), 2)],
                'landscape'
            );
        }

        $cotizaciones = $query->orderByDesc('id_cotizacion')
            ->paginate(15)
            ->withQueryString();

        $clientes = Cliente::orderBy('nombre_razon_social')->get();

        // Sesión de caja abierta para permitir conversión directa a venta
        $sesionAbierta = SesionCaja::where('estado', 'Abierta')
            ->orderByDesc('id_sesion')
            ->first();

        $metricas = (object)[
            'vigentes'    => Cotizacion::where('estado', 'Vigente')->whereDate('fecha_vencimiento', '>=', Carbon::today())->count(),
            'convertidas' => Cotizacion::where('estado', 'Concretada/Facturada')->count(),
            'vencidas'    => Cotizacion::where('estado', 'Vencida')->count(),
            'monto_vigente' => Cotizacion::where('estado', 'Vigente')->whereDate('fecha_vencimiento', '>=', Carbon::today())->sum('monto_total'),
        ];

        return view('cotizaciones.index', compact('cotizaciones', 'clientes', 'sesionAbierta', 'metricas'));
    }

    /**
     * Formulario ágil para emitir nueva cotización / proforma formal.
     */
    public function create(Request $request)
    {
        $clientes = Cliente::orderBy('nombre_razon_social')->get();

        $productos = Producto::with(['categoria', 'codigosAlternativos', 'vehiculos'])
            ->where('estado', true)
            ->orderBy('nombre_pieza')
            ->get()
            ->map(function ($p) {
                $altCodes = $p->codigosAlternativos->pluck('codigo_referencia')->implode(', ');
                $vehiculosStr = $p->vehiculos->map(fn($v) => trim("{$v->marca} {$v->modelo} ({$v->anio_inicio}-{$v->anio_fin})"))->implode(', ');
                return [
                    'id_producto'     => $p->id_producto,
                    'codigo_interno'  => $p->codigo_interno,
                    'codigo_oem'      => $p->codigo_oem,
                    'codigos_alt'     => $altCodes,
                    'vehiculos'       => $vehiculosStr,
                    'nombre_pieza'    => $p->nombre_pieza,
                    'marca'           => $p->marca_fabricante ?? 'Genérico',
                    'categoria'       => $p->categoria->nombre ?? 'Repuesto',
                    'precio_sugerido' => (float)$p->precio_venta_sugerido,
                    'stock_actual'    => (int)$p->stock_actual,
                    'ubicacion'       => trim(($p->pasillo ?? '-') . ' / ' . ($p->estante ?? '-')),
                ];
            });

        $clientePreseleccionado = $request->input('id_cliente');

        return view('cotizaciones.create', compact('clientes', 'productos', 'clientePreseleccionado'));
    }

    /**
     * Guardar cotización invocando CALL sp_registrar_cotizacion(...) en PostgreSQL.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_cliente'          => 'required|integer|exists:clientes,id_cliente',
            'dias_vigencia'       => 'required|integer|min:1|max:90',
            'items'               => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'    => 'required|integer|min:1',
            'items.*.precio'      => 'required|numeric|min:0.01',
        ], [
            'id_cliente.required'    => 'Seleccione el cliente o taller destinatario de la cotización.',
            'dias_vigencia.required' => 'Indique el plazo de vigencia en días.',
            'items.required'         => 'Debe incluir al menos un repuesto en la proforma.',
            'items.min'              => 'Debe incluir al menos un repuesto en la proforma.',
        ]);

        $itemsJson = [];
        foreach ($request->items as $item) {
            $itemsJson[] = [
                'id_producto' => (int)$item['id_producto'],
                'cantidad'    => (int)$item['cantidad'],
                'precio'      => (float)$item['precio'],
            ];
        }

        try {
            $ip = $request->ip() ?? '127.0.0.1';
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ip]);

            $res = DB::select("CALL sp_registrar_cotizacion(?, ?, ?, ?::jsonb, NULL)", [
                (int)$request->id_cliente,
                auth()->id(),
                (int)$request->dias_vigencia,
                json_encode($itemsJson),
            ]);

            $idCotizacion = $res[0]->p_id_cotizacion ?? Cotizacion::max('id_cotizacion');

            return redirect()->route('cotizaciones.show', $idCotizacion)
                ->with('success', "Cotización #COT-" . str_pad($idCotizacion, 5, '0', STR_PAD_LEFT) . " emitida exitosamente con vigencia de " . (int)$request->dias_vigencia . " días.");
        } catch (\Throwable $e) {
            $mensaje = $this->extraerMensajePg($e->getMessage());
            return back()->withInput()->with('error', $mensaje);
        }
    }

    /**
     * Ver proforma / cotización lista para impresión, PDF o envío por WhatsApp.
     */
    public function show($id)
    {
        $cotizacion = Cotizacion::with(['cliente', 'usuario.rol', 'detalles.producto.categoria'])
            ->findOrFail($id);

        // Si el usuario es Cliente Web (Rol 5), verificar que la cotización le pertenezca
        if (auth()->user()->id_rol === 5) {
            $clienteWeb = $this->obtenerOCrearClienteParaUsuarioWeb(auth()->user());
            if ($cotizacion->id_usuario !== auth()->id() && $cotizacion->id_cliente !== $clienteWeb->id_cliente) {
                abort(403, 'No tiene permiso para visualizar esta cotización.');
            }
        }

        $sesionAbierta = SesionCaja::where('estado', 'Abierta')
            ->orderByDesc('id_sesion')
            ->first();

        return view('cotizaciones.show', compact('cotizacion', 'sesionAbierta'));
    }

    /**
     * Exporta la proforma / cotización comercial en PDF o Excel (.xlsx).
     */
    public function exportar($id, string $formato, ExportacionService $exportacion)
    {
        $cotizacion = Cotizacion::with(['cliente', 'usuario.rol', 'detalles.producto.categoria'])
            ->findOrFail($id);

        if (auth()->user()->id_rol === 5) {
            $clienteWeb = $this->obtenerOCrearClienteParaUsuarioWeb(auth()->user());
            if ($cotizacion->id_usuario !== auth()->id() && $cotizacion->id_cliente !== $clienteWeb->id_cliente) {
                abort(403, 'No tiene permiso para exportar esta cotización.');
            }
        }

        $encabezados = ['Código Interno', 'Repuesto / Pieza', 'Categoría', 'Cantidad', 'Precio Unit. (Bs.)', 'Subtotal (Bs.)'];

        $filas = $cotizacion->detalles->map(function ($det) {
            return [
                $det->producto->codigo_interno ?? '-',
                $det->producto->nombre_pieza ?? ('Repuesto #' . $det->id_producto),
                $det->producto->categoria->nombre ?? 'General',
                (int) $det->cantidad,
                round((float) $det->precio_unitario_cotizado, 2),
                round((float) $det->subtotal, 2),
            ];
        })->all();

        $codigo = 'COT-' . str_pad($cotizacion->id_cotizacion, 5, '0', STR_PAD_LEFT);
        $kpis = [
            'Proforma'      => '#' . $codigo,
            'Cliente'       => $cotizacion->cliente->nombre_razon_social ?? '-',
            'Estado'        => $cotizacion->estado_visual,
            'Válida Hasta'  => Carbon::parse($cotizacion->fecha_vencimiento)->format('d/m/Y'),
            'Total (Bs.)'   => 'Bs. ' . number_format((float) $cotizacion->monto_total, 2),
        ];

        $subtitulos = [
            'Emitida el: ' . optional($cotizacion->fecha_emision)->format('d/m/Y H:i')
                . ' | Asesor: ' . ($cotizacion->usuario->nombre_completo ?? 'Sistema'),
            'CI / NIT Cliente: ' . ($cotizacion->cliente->ci_nit ?? '-')
                . ' | Teléfono: ' . ($cotizacion->cliente->telefono ?? '-'),
        ];

        $totales = ['', '', '', (int) $cotizacion->detalles->sum('cantidad'), 'TOTAL PROFORMA (Bs.):', round((float) $cotizacion->monto_total, 2)];
        $nombreArchivo = 'proforma_' . $codigo . '_' . now()->format('Ymd_His');

        if ($formato === 'excel') {
            return $exportacion->exportarExcel(
                $nombreArchivo,
                'PROFORMA COMERCIAL #' . $codigo,
                $subtitulos,
                $encabezados,
                $filas,
                $kpis,
                $totales
            );
        }

        return $exportacion->exportarPdf(
            $nombreArchivo,
            'Proforma Comercial #' . $codigo,
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            $totales,
            'portrait',
            'Cotización referencial sujeta a disponibilidad de stock hasta la fecha de vencimiento indicada.'
        );
    }

    /**
     * Convertir cotización vigente en Nota de Venta real invocando CALL sp_convertir_cotizacion_a_venta(...).
     */
    public function convertirAVenta(Request $request, $id)
    {
        $request->validate([
            'id_sesion_caja'   => 'required|integer|exists:sesiones_cajas,id_sesion',
            'metodo_pago'      => 'required|string|in:Efectivo,Transferencia/QR,Tarjeta Débito/Crédito,Mixto',
            'descuento_total'  => 'nullable|numeric|min:0',
            'monto_recibido'   => 'nullable|numeric|min:0',
            'nro_referencia'   => 'nullable|string|max:80',
            'monto_efectivo'   => 'nullable|numeric|min:0',
            'monto_digital'    => 'nullable|numeric|min:0',
        ], [
            'id_sesion_caja.required' => 'Se requiere una sesión de caja abierta para procesar el cobro.',
            'metodo_pago.required'    => 'Seleccione la modalidad de cobro.',
        ]);

        $cotizacion = Cotizacion::with('detalles.producto')->findOrFail($id);

        // Prevalidación de vigencia y sesión de caja abierta (CU26)
        if ($cotizacion->estado !== 'Vigente' || Carbon::parse($cotizacion->fecha_vencimiento)->lt(Carbon::today())) {
            if ($cotizacion->estado === 'Vigente' && Carbon::parse($cotizacion->fecha_vencimiento)->lt(Carbon::today())) {
                $cotizacion->update(['estado' => 'Vencida']);
            }
            return back()->with('error', 'La cotización seleccionada no se encuentra vigente y no puede convertirse en venta.');
        }

        $sesionCaja = SesionCaja::find($request->id_sesion_caja);
        if (!$sesionCaja || $sesionCaja->estado !== 'Abierta') {
            return back()->with('error', 'No existe una sesión de caja abierta válida para procesar la conversión.');
        }

        // Prevalidación preventiva de existencias antes de invocar el SP transaccional
        foreach ($cotizacion->detalles as $det) {
            if (!$det->producto || !$det->producto->estado) {
                return back()->with('error', 'Uno de los repuestos de la cotización se encuentra inactivo en el catálogo.');
            }
            if ((int)$det->producto->stock_actual < (int)$det->cantidad) {
                return back()->with('error', "Stock insuficiente para '{$det->producto->nombre_pieza}': solicitado {$det->cantidad}, disponible en almacén {$det->producto->stock_actual}.");
            }
        }

        $descuento = round((float)($request->descuento_total ?? 0), 2);
        $netoCobrar = round(max(0, (float)$cotizacion->monto_total - $descuento), 2);
        if ($netoCobrar <= 0) {
            return back()->with('error', 'El importe neto a cobrar debe ser mayor a Bs. 0.00.');
        }

        $montoRecibido = $request->filled('monto_recibido') ? round((float)$request->monto_recibido, 2) : $netoCobrar;
        if ($request->metodo_pago === 'Efectivo' && $montoRecibido + 0.01 < $netoCobrar) {
            return back()->with('error', 'El efectivo recibido (Bs. ' . number_format($montoRecibido, 2) . ') es insuficiente para cubrir el total neto (Bs. ' . number_format($netoCobrar, 2) . ').');
        }

        $montoEfectivoMixto = round((float)($request->monto_efectivo ?? 0), 2);
        $montoDigitalMixto = round((float)($request->monto_digital ?? 0), 2);
        if ($request->metodo_pago === 'Mixto') {
            if ($montoEfectivoMixto <= 0 || $montoDigitalMixto <= 0) {
                return back()->with('error', 'En pago Mixto debe indicar montos mayores a cero tanto en Efectivo como en QR/Tarjeta.');
            }
            if (abs(($montoEfectivoMixto + $montoDigitalMixto) - $netoCobrar) > 0.05) {
                return back()->with('error', 'En pago Mixto la suma de Efectivo y QR/Tarjeta debe igualar el total neto (Bs. ' . number_format($netoCobrar, 2) . ').');
            }
        }

        $cambioDevuelto = $request->metodo_pago === 'Efectivo' ? round(max(0, $montoRecibido - $netoCobrar), 2) : 0.00;

        $detallesPago = [
            'origen'          => 'CONVERSION_COTIZACION',
            'id_cotizacion'   => (int)$id,
            'metodo_pago'     => $request->metodo_pago,
            'monto_recibido'  => $montoRecibido,
            'cambio_devuelto' => $cambioDevuelto,
            'nro_referencia'  => $request->nro_referencia ? trim($request->nro_referencia) : null,
            'monto_efectivo'  => $request->metodo_pago === 'Mixto' ? $montoEfectivoMixto : ($request->metodo_pago === 'Efectivo' ? $netoCobrar : 0),
            'monto_digital'   => $request->metodo_pago === 'Mixto' ? $montoDigitalMixto : ($request->metodo_pago !== 'Efectivo' ? $netoCobrar : 0),
        ];

        try {
            $ip = $request->ip() ?? '127.0.0.1';
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ip]);

            $res = DB::select("CALL sp_convertir_cotizacion_a_venta(?, ?, ?, ?, ?, ?::jsonb, NULL)", [
                (int)$id,
                auth()->id(),
                (int)$request->id_sesion_caja,
                $request->metodo_pago,
                (float)($request->descuento_total ?? 0),
                json_encode($detallesPago),
            ]);

            $idVenta = $res[0]->p_id_venta ?? DB::table('notas_ventas')->max('id_venta');

            return redirect()->route('ventas.ticket', ['id' => $idVenta, 'autoprint' => 1])
                ->with('success', "Cotización #COT-" . str_pad($id, 5, '0', STR_PAD_LEFT) . " convertida exitosamente en la Nota de Venta #V-" . str_pad($idVenta, 5, '0', STR_PAD_LEFT) . ".");
        } catch (\Throwable $e) {
            $mensaje = $this->extraerMensajePg($e->getMessage());
            return back()->with('error', $mensaje);
        }
    }

    /**
     * Anular una cotización vigente.
     */
    public function anular($id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        if ($cotizacion->estado === 'Concretada/Facturada') {
            return back()->with('error', 'No se puede anular una cotización que ya fue convertida en venta.');
        }

        $cotizacion->estado = 'Anulada';
        $cotizacion->save();

        Bitacora::registrar(
            'Ventas / Cotizaciones',
            'ANULACION',
            $cotizacion->id_cotizacion,
            json_encode([
                'id_cotizacion' => $cotizacion->id_cotizacion,
                'monto_total'   => $cotizacion->monto_total,
                'evento'        => 'Anulación manual de cotización',
            ])
        );

        return back()->with('success', "Cotización #COT-" . str_pad($cotizacion->id_cotizacion, 5, '0', STR_PAD_LEFT) . " anulada correctamente.");
    }

    /**
     * Portal para Rol 5 (Cliente Web): Ver y solicitar cotizaciones en /mis-cotizaciones.
     */
    public function misCotizaciones(Request $request)
    {
        $usuario = auth()->user();
        $cliente = $this->obtenerOCrearClienteParaUsuarioWeb($usuario);

        Cotizacion::where('estado', 'Vigente')
            ->whereDate('fecha_vencimiento', '<', Carbon::today())
            ->update(['estado' => 'Vencida']);

        $cotizaciones = Cotizacion::with(['detalles.producto'])
            ->where(function ($q) use ($usuario, $cliente) {
                $q->where('id_usuario', $usuario->id_usuario)
                  ->orWhere('id_cliente', $cliente->id_cliente);
            })
            ->orderByDesc('id_cotizacion')
            ->paginate(10);

        $productos = Producto::with('categoria')
            ->where('estado', true)
            ->where('stock_actual', '>', 0)
            ->orderBy('nombre_pieza')
            ->get();

        $productoPreseleccionado = $request->input('id_producto');

        $misCompras = NotaVenta::with(['detalles.producto'])
            ->where('id_cliente', $cliente->id_cliente)
            ->orderByDesc('id_venta')
            ->limit(25)
            ->get();

        return view('cotizaciones.mis_cotizaciones', compact('cotizaciones', 'cliente', 'productos', 'productoPreseleccionado', 'misCompras'));
    }

    /**
     * Guardar solicitud de cotización desde el portal de Cliente Web (Rol 5).
     */
    public function storeMiCotizacion(Request $request)
    {
        $request->validate([
            'ci_nit'              => 'nullable|string|max:30',
            'dias_vigencia'       => 'nullable|integer|in:7,15',
            'items'               => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'    => 'required|integer|min:1',
        ], [
            'items.required' => 'Seleccione al menos un repuesto del catálogo para generar su proforma.',
        ]);

        $usuario = auth()->user();
        $cliente = $this->obtenerOCrearClienteParaUsuarioWeb($usuario, $request->ci_nit);

        $itemsJson = [];
        foreach ($request->items as $it) {
            $prod = Producto::findOrFail($it['id_producto']);
            $precio = (float)$prod->precio_venta_sugerido;
            if ($cliente->descuento_frecuente > 0) {
                $precio = round($precio * (1 - ($cliente->descuento_frecuente / 100)), 2);
            }
            $itemsJson[] = [
                'id_producto' => (int)$prod->id_producto,
                'cantidad'    => (int)$it['cantidad'],
                'precio'      => $precio,
            ];
        }

        try {
            $diasVigencia = (int)($request->dias_vigencia ?: 7);
            $ip = $request->ip() ?? '127.0.0.1';
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ip]);

            $res = DB::select("CALL sp_registrar_cotizacion(?, ?, ?, ?::jsonb, NULL)", [
                (int)$cliente->id_cliente,
                (int)$usuario->id_usuario,
                $diasVigencia,
                json_encode($itemsJson),
            ]);

            $idCotizacion = $res[0]->p_id_cotizacion ?? Cotizacion::max('id_cotizacion');

            return redirect()->route('mis-cotizaciones.index')
                ->with('success', "¡Tu proforma #COT-" . str_pad($idCotizacion, 5, '0', STR_PAD_LEFT) . " ha sido generada con éxito! Tiene vigencia por {$diasVigencia} días.");
        } catch (\Throwable $e) {
            $mensaje = $this->extraerMensajePg($e->getMessage());
            return back()->withInput()->with('error', $mensaje);
        }
    }

    /**
     * Obtiene o crea automáticamente la ficha de Cliente para un Usuario Web (Rol 5).
     */
    private function obtenerOCrearClienteParaUsuarioWeb($usuario, ?string $ciNit = null): Cliente
    {
        $cliente = Cliente::whereRaw('LOWER(TRIM(nombre_razon_social)) = LOWER(TRIM(?))', [$usuario->nombre_completo])
            ->orWhere(function ($q) use ($usuario) {
                if (!empty($usuario->telefono)) {
                    $q->where('telefono', trim($usuario->telefono));
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->first();

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

    private function extraerMensajePg(string $mensaje): string
    {
        if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $mensaje, $matches)) {
            return trim($matches[1]);
        }
        return $mensaje;
    }
}

