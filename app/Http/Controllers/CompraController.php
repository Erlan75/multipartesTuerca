<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NotaCompra;
use App\Models\DetalleCompra;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Services\ExportacionService;
use App\Services\ExportadorUniversalService;
use Carbon\Carbon;

class CompraController extends Controller
{
    /**
     * Listado general de compras recibidas.
     */
    public function index(Request $request, ExportadorUniversalService $exportador)
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

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderBy('fecha_compra', 'desc')->orderBy('id_compra', 'desc')->get();
            $encabezados = ['ID', 'Fecha Compra', 'Factura / Nota', 'Proveedor', 'Receptor', 'Tipo Pago', 'Estado Pago', 'Monto Total (Bs.)'];
            $filas = $lista->map(fn($c) => [
                'C-' . str_pad($c->id_compra, 5, '0', STR_PAD_LEFT),
                Carbon::parse($c->fecha_compra)->format('d/m/Y'),
                $c->numero_factura_nota,
                $c->proveedor->razon_social ?? '-',
                $c->usuarioReceptor->nombre_completo ?? 'Sistema',
                $c->tipo_pago,
                $c->estado_pago,
                round((float) $c->monto_total, 2),
            ])->toArray();

            $subtitulos = [
                'Rango: ' . ($request->fecha_desde ?: 'Inicio') . ' al ' . ($request->fecha_hasta ?: 'Actual')
                    . ' | Pago: ' . ($request->tipo_pago ?: 'Todos')
                    . ' | Estado: ' . ($request->estado_pago ?: 'Todos'),
            ];

            return $exportador->exportarLista(
                $export,
                'compras_recepcion_' . now()->format('Ymd_His'),
                'Historial de Notas de Compra y Recepción',
                $subtitulos,
                $encabezados,
                $filas,
                ['Notas de Compra' => (string) $lista->count(), 'Total Adquirido' => 'Bs. ' . number_format((float) $lista->sum('monto_total'), 2)],
                ['TOTALES', '', '', '', '', '', '', round((float) $lista->sum('monto_total'), 2)],
                'landscape'
            );
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
     * Exporta el comprobante de recepción de compra en PDF o Excel (.xlsx).
     */
    public function exportar($id, string $formato, ExportacionService $exportacion)
    {
        $compra = NotaCompra::with(['proveedor', 'usuarioReceptor.rol', 'detalles.producto.categoria'])
            ->findOrFail($id);

        $encabezados = ['Código Interno', 'Repuesto / Pieza', 'Categoría', 'Cantidad', 'Costo Unit. (Bs.)', 'Subtotal (Bs.)'];

        $filas = $compra->detalles->map(function ($det) {
            return [
                $det->producto->codigo_interno ?? '-',
                $det->producto->nombre_pieza ?? ('Repuesto #' . $det->id_producto),
                $det->producto->categoria->nombre ?? 'General',
                (int) $det->cantidad_recibida,
                round((float) $det->costo_unitario, 2),
                round((float) $det->subtotal, 2),
            ];
        })->all();

        $codigo = 'C-' . str_pad($compra->id_compra, 5, '0', STR_PAD_LEFT);
        $kpis = [
            'Comprobante'    => '#' . $codigo,
            'Proveedor'      => $compra->proveedor->razon_social ?? '-',
            'Factura / Nota' => $compra->numero_factura_nota ?? '-',
            'Pago / Estado'  => $compra->tipo_pago . ' (' . $compra->estado_pago . ')',
            'Monto Total'    => 'Bs. ' . number_format((float) $compra->monto_total, 2),
        ];

        $subtitulos = [
            'Fecha de compra: ' . \Carbon\Carbon::parse($compra->fecha_compra)->format('d/m/Y')
                . ' | Receptor: ' . ($compra->usuarioReceptor->nombre_completo ?? 'Sistema'),
            'NIT Proveedor: ' . ($compra->proveedor->nit ?? '-'),
        ];

        $totales = ['', '', '', (int) $compra->detalles->sum('cantidad_recibida'), 'TOTAL COMPRA (Bs.):', round((float) $compra->monto_total, 2)];
        $nombreArchivo = 'recepcion_compra_' . $codigo . '_' . now()->format('Ymd_His');

        if ($formato === 'excel') {
            return $exportacion->exportarExcel(
                $nombreArchivo,
                'COMPROBANTE DE RECEPCIÓN DE COMPRA #' . $codigo,
                $subtitulos,
                $encabezados,
                $filas,
                $kpis,
                $totales
            );
        }

        return $exportacion->exportarPdf(
            $nombreArchivo,
            'Recepción de Compra #' . $codigo,
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            $totales,
            'portrait',
            'Comprobante de ingreso a almacén — Multipartes El Tuerca.'
        );
    }

    /**
     * CU21: Consultar historial cronológico de costos de adquisición por repuesto.
     */
    public function historialCostos(Request $request, ExportadorUniversalService $exportador)
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

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $encabezados = ['Fecha Compra', 'Factura / Nota', 'Proveedor', 'Cant. Recibida', 'Costo Unit. (Bs.)', 'Subtotal (Bs.)', 'Variación (%)'];
            $filas = $historial->map(fn($item) => [
                Carbon::parse($item->compra->fecha_compra)->format('d/m/Y'),
                $item->compra->numero_factura_nota ?? '-',
                $item->compra->proveedor->razon_social ?? '-',
                (int) $item->cantidad_recibida,
                round((float) $item->costo_unitario, 2),
                round((float) $item->subtotal, 2),
                $item->variacion_pct !== null ? number_format((float) $item->variacion_pct, 2) . '%' : 'Base',
            ])->toArray();

            $repuestoTxt = $productoSeleccionado
                ? "[{$productoSeleccionado->codigo_interno}] {$productoSeleccionado->nombre_pieza}"
                : 'Sin repuesto seleccionado';

            return $exportador->exportarLista(
                $export,
                'historial_costos_' . now()->format('Ymd_His'),
                'Historial de Costos de Adquisición',
                ['Repuesto: ' . $repuestoTxt, 'Desde: ' . ($request->fecha_desde ?: 'Inicio') . ' al ' . ($request->fecha_hasta ?: 'Actual')],
                $encabezados,
                $filas,
                $estadisticas ? ['Costo Promedio' => 'Bs. ' . number_format((float) $estadisticas->costo_promedio, 2), 'Último Costo' => 'Bs. ' . number_format((float) $estadisticas->ultimo_costo, 2)] : [],
                [],
                'landscape'
            );
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
    public function cuentasPorPagar(Request $request, ExportadorUniversalService $exportador)
    {
        $query = NotaCompra::with(['proveedor', 'usuarioReceptor'])
            ->where('tipo_pago', 'Crédito')
            ->where('estado_pago', 'Pendiente');

        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', $request->id_proveedor);
        }

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderBy('fecha_compra', 'asc')->get();
            $encabezados = ['Comprobante', 'Fecha Compra', 'Factura / Nota', 'Proveedor', 'NIT', 'Receptor', 'Saldo Pendiente (Bs.)'];
            $filas = $lista->map(fn($c) => [
                'C-' . str_pad($c->id_compra, 5, '0', STR_PAD_LEFT),
                Carbon::parse($c->fecha_compra)->format('d/m/Y'),
                $c->numero_factura_nota,
                $c->proveedor->razon_social ?? '-',
                $c->proveedor->nit ?? '-',
                $c->usuarioReceptor->nombre_completo ?? 'Sistema',
                round((float) $c->monto_total, 2),
            ])->toArray();

            return $exportador->exportarLista(
                $export,
                'cuentas_por_pagar_' . now()->format('Ymd_His'),
                'Cuentas por Pagar a Proveedores (Crédito Pendiente)',
                ['Facturas pendientes: ' . $lista->count()],
                $encabezados,
                $filas,
                ['Total Adeudado' => 'Bs. ' . number_format((float) $lista->sum('monto_total'), 2)],
                ['TOTAL ADEUDADO', '', '', '', '', '', round((float) $lista->sum('monto_total'), 2)],
                'landscape'
            );
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
