<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Helpers\Bitacora;
use Carbon\Carbon;

class CotizacionController extends Controller
{
    /**
     * Bandeja general de cotizaciones con validación de vigencia y filtros.
     */
    public function index(Request $request)
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

        $productos = Producto::with(['categoria', 'codigosAlternativos'])
            ->where('estado', true)
            ->orderBy('nombre_pieza')
            ->get()
            ->map(function ($p) {
                $altCodes = $p->codigosAlternativos->pluck('codigo_referencia')->implode(', ');
                return [
                    'id_producto'     => $p->id_producto,
                    'codigo_interno'  => $p->codigo_interno,
                    'codigo_oem'      => $p->codigo_oem,
                    'codigos_alt'     => $altCodes,
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

        $detallesPago = [
            'origen'          => 'CONVERSION_COTIZACION',
            'id_cotizacion'   => (int)$id,
            'metodo_pago'     => $request->metodo_pago,
            'monto_recibido'  => (float)($request->monto_recibido ?? 0),
            'nro_referencia'  => $request->nro_referencia ? trim($request->nro_referencia) : null,
            'monto_efectivo'  => (float)($request->monto_efectivo ?? 0),
            'monto_digital'   => (float)($request->monto_digital ?? 0),
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

            return redirect()->route('ventas.ticket', $idVenta)
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

        return view('cotizaciones.mis_cotizaciones', compact('cotizaciones', 'cliente', 'productos', 'productoPreseleccionado'));
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

