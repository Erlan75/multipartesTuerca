<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NotaCompra;
use App\Models\DetalleCompra;
use App\Models\Proveedor;
use App\Models\Producto;

class CompraController extends Controller
{
    /**
     * Listado general de compras recibidas.
     */
    public function index(Request $request)
    {
        $query = NotaCompra::with(['proveedor', 'usuarioReceptor', 'detalles.producto']);

        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', $request->id_proveedor);
        }

        if ($request->filled('tipo_pago')) {
            $query->where('tipo_pago', $request->tipo_pago);
        }

        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->estado_pago);
        }

        if ($request->filled('buscar_factura')) {
            $query->where('numero_factura_nota', 'ilike', '%' . trim($request->buscar_factura) . '%');
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_compra', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_compra', '<=', $request->fecha_hasta);
        }

        $compras = $query->orderBy('fecha_compra', 'desc')
            ->orderBy('id_compra', 'desc')
            ->paginate(15)
            ->withQueryString();

        $proveedores = Proveedor::orderBy('razon_social')->get();

        return view('compras.index', compact('compras', 'proveedores'));
    }

    /**
     * CU20: Formulario para registrar recepción de compras.
     */
    public function create()
    {
        $proveedores = Proveedor::where('estado', true)
            ->orderBy('razon_social')
            ->get();

        $productos = Producto::where('estado', true)
            ->orderBy('nombre_pieza')
            ->get(['id_producto', 'codigo_interno', 'codigo_oem', 'nombre_pieza', 'stock_actual', 'pasillo', 'estante', 'nivel_gaveta']);

        return view('compras.create', compact('proveedores', 'productos'));
    }

    /**
     * CU20: Registrar recepción de mercadería mediante SP Smart-DB.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_proveedor'        => 'required|integer|exists:proveedores,id_proveedor',
            'numero_factura_nota' => 'required|string|min:2|max:60',
            'fecha_compra'        => 'required|date|before_or_equal:today',
            'tipo_pago'           => 'required|string|in:Contado,Crédito',
            'items'               => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'    => 'required|integer|min:1',
            'items.*.costo'       => 'required|numeric|min:0.01',
        ], [
            'id_proveedor.required'        => 'Seleccione la empresa importadora o distribuidora.',
            'numero_factura_nota.required' => 'El número de factura o nota de entrega es obligatorio.',
            'fecha_compra.before_or_equal' => 'La fecha de compra no puede ser futura.',
            'items.min'                    => 'Debe agregar al menos un repuesto a la recepción.',
            'items.*.cantidad.min'         => 'La cantidad de cada repuesto debe ser mayor a 0.',
            'items.*.costo.min'            => 'El costo unitario debe ser mayor a 0.',
        ]);

        $itemsArray = [];
        foreach ($request->items as $it) {
            $itemsArray[] = [
                'id_producto' => (int)$it['id_producto'],
                'cantidad'    => (int)$it['cantidad'],
                'costo'       => (float)$it['costo'],
            ];
        }

        try {
            DB::statement("CALL sp_registrar_compra(?, ?, ?, ?, ?, ?::jsonb)", [
                (int)$request->id_proveedor,
                auth()->id(),
                trim($request->numero_factura_nota),
                $request->fecha_compra,
                $request->tipo_pago,
                json_encode($itemsArray)
            ]);

            return redirect()->route('compras.index')
                ->with('success', "Recepción de compra '{$request->numero_factura_nota}' registrada e ingresada a almacén exitosamente.");
        } catch (\Throwable $e) {
            $mensaje = $e->getMessage();
            if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $mensaje, $matches)) {
                $mensaje = trim($matches[1]);
            }
            return back()->withInput()->with('error', $mensaje);
        }
    }

    /**
     * Ver detalle completo de una nota de compra.
     */
    public function show($id)
    {
        $compra = NotaCompra::with(['proveedor', 'usuarioReceptor.rol', 'detalles.producto.categoria'])
            ->findOrFail($id);

        return view('compras.show', compact('compra'));
    }

    /**
     * CU21: Consultar historial cronológico de costos de adquisición por repuesto.
     */
    public function historialCostos(Request $request)
    {
        $productos = Producto::where('estado', true)
            ->orderBy('nombre_pieza', 'asc')
            ->get(['id_producto', 'codigo_interno', 'codigo_oem', 'nombre_pieza']);

        $idProducto = $request->input('id_producto');
        $productoSeleccionado = null;
        $historial = collect();
        $estadisticas = null;

        if ($idProducto) {
            $productoSeleccionado = Producto::find($idProducto);

            if ($productoSeleccionado) {
                $query = DetalleCompra::with(['compra.proveedor'])
                    ->where('detalles_compras.id_producto', $idProducto)
                    ->join('notas_compras', 'detalles_compras.id_compra', '=', 'notas_compras.id_compra')
                    ->where('notas_compras.estado_pago', '<>', 'Anulado');

                if ($request->filled('fecha_desde')) {
                    $query->whereDate('notas_compras.fecha_compra', '>=', $request->fecha_desde);
                }
                if ($request->filled('fecha_hasta')) {
                    $query->whereDate('notas_compras.fecha_compra', '<=', $request->fecha_hasta);
                }

                $items = $query->orderBy('notas_compras.fecha_compra', 'desc')
                    ->orderBy('detalles_compras.id_detalle_compra', 'desc')
                    ->select('detalles_compras.*')
                    ->get();

                // Calcular variación porcentual con respecto a la compra cronológicamente anterior
                $itemsAsc = $items->reverse()->values();
                $prevCosto = null;
                $itemsConVariacion = $itemsAsc->map(function ($item) use (&$prevCosto) {
                    $variacion = null;
                    if ($prevCosto !== null && $prevCosto > 0) {
                        $variacion = (($item->costo_unitario - $prevCosto) / $prevCosto) * 100;
                    }
                    $prevCosto = (float)$item->costo_unitario;
                    $item->variacion_pct = $variacion;
                    return $item;
                })->reverse()->values();

                $historial = $itemsConVariacion;

                if ($items->count() > 0) {
                    $estadisticas = (object)[
                        'total_compras' => $items->count(),
                        'unidades_totales' => $items->sum('cantidad_recibida'),
                        'costo_min' => $items->min('costo_unitario'),
                        'costo_max' => $items->max('costo_unitario'),
                        'costo_promedio' => $items->avg('costo_unitario'),
                        'ultimo_costo' => $items->first()->costo_unitario,
                    ];
                }
            }
        }

        return view('compras.historial_costos', compact(
            'productos',
            'productoSeleccionado',
            'historial',
            'estadisticas'
        ));
    }

    /**
     * CU22: Gestión de Cuentas por Pagar (Compras a crédito pendientes).
     */
    public function cuentasPorPagar(Request $request)
    {
        $query = NotaCompra::with(['proveedor', 'usuarioReceptor'])
            ->where('tipo_pago', 'Crédito')
            ->where('estado_pago', 'Pendiente');

        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', $request->id_proveedor);
        }

        $cuentasPendientes = $query->orderBy('fecha_compra', 'asc')
            ->paginate(15)
            ->withQueryString();

        $totalAdeudado = NotaCompra::where('tipo_pago', 'Crédito')
            ->where('estado_pago', 'Pendiente')
            ->sum('monto_total');

        $totalFacturasPendientes = NotaCompra::where('tipo_pago', 'Crédito')
            ->where('estado_pago', 'Pendiente')
            ->count();

        $proveedores = Proveedor::whereHas('compras', function ($q) {
            $q->where('tipo_pago', 'Crédito')->where('estado_pago', 'Pendiente');
        })->get();

        return view('compras.cuentas_por_pagar', compact(
            'cuentasPendientes',
            'totalAdeudado',
            'totalFacturasPendientes',
            'proveedores'
        ));
    }

    /**
     * CU22: Liquidar pago de compra a crédito mediante SP Smart-DB blindado.
     */
    public function liquidarPago($id)
    {
        try {
            DB::statement("CALL sp_liquidar_pago_compra(?, ?)", [
                (int)$id,
                auth()->id()
            ]);

            return back()->with('success', 'Pago a distribuidor liquidado exitosamente. La factura fue asentada como Pagada.');
        } catch (\Throwable $e) {
            $mensaje = $e->getMessage();
            if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $mensaje, $matches)) {
                $mensaje = trim($matches[1]);
            }
            return back()->with('error', $mensaje);
        }
    }
}
