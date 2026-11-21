<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\AjusteInventario;
use App\Services\ExportadorUniversalService;
use Carbon\Carbon;

class InventarioController extends Controller
{
    /**
     * CU16: Monitorear alertas de stock mínimo y crítico.
     */
    public function alertas(Request $request, ExportadorUniversalService $exportador)
    {
        $query = Producto::with(['categoria', 'codigosAlternativos'])
            ->where('estado', true)
            ->whereRaw('stock_actual <= stock_seguridad');

        if ($request->filled('id_categoria')) {
            $query->where('id_categoria', $request->id_categoria);
        }

        if ($request->filled('nivel')) {
            $nivel = $request->nivel;
            if ($nivel === 'agotado') {
                $query->where('stock_actual', 0);
            } elseif ($nivel === 'critico') {
                $query->where('stock_actual', '>', 0)
                      ->whereRaw('stock_actual <= stock_minimo');
            } elseif ($nivel === 'seguridad') {
                $query->whereRaw('stock_actual > stock_minimo')
                      ->whereRaw('stock_actual <= stock_seguridad');
            }
        }

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_interno', 'ilike', "%{$buscar}%")
                  ->orWhere('codigo_oem', 'ilike', "%{$buscar}%")
                  ->orWhere('nombre_pieza', 'ilike', "%{$buscar}%");
            });
        }

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderBy('stock_actual', 'asc')->orderBy('nombre_pieza', 'asc')->get();
            $encabezados = ['SKU', 'Código OEM', 'Repuesto', 'Categoría', 'Stock Actual', 'Stock Mín.', 'Stock Seg.', 'Nivel Alerta', 'Precio (Bs.)'];
            $filas = $lista->map(function ($p) {
                $nivelTexto = (int) $p->stock_actual === 0
                    ? 'AGOTADO'
                    : ((int) $p->stock_actual <= (int) $p->stock_minimo ? 'CRÍTICO' : 'SEGURIDAD');
                return [
                    $p->codigo_interno,
                    $p->codigo_oem,
                    $p->nombre_pieza,
                    $p->categoria->nombre ?? 'General',
                    (int) $p->stock_actual,
                    (int) $p->stock_minimo,
                    (int) $p->stock_seguridad,
                    $nivelTexto,
                    round((float) $p->precio_venta_sugerido, 2),
                ];
            })->toArray();

            return $exportador->exportarLista(
                $export,
                'alertas_stock_critico_' . now()->format('Ymd_His'),
                'Monitoreo y Alertas de Stock Crítico',
                ['Nivel: ' . ($request->nivel ?: 'Todos') . ' | Búsqueda: ' . ($request->buscar ?: 'Todas')],
                $encabezados,
                $filas,
                ['Productos en Alerta' => (string) $lista->count()],
                [],
                'landscape'
            );
        }

        $productosEnAlerta = $query->orderBy('stock_actual', 'asc')
            ->orderBy('nombre_pieza', 'asc')
            ->paginate(15)
            ->withQueryString();

        // Métricas de semáforo general
        $totalAgotados = Producto::where('estado', true)->where('stock_actual', 0)->count();
        $totalCriticos = Producto::where('estado', true)
            ->where('stock_actual', '>', 0)
            ->whereRaw('stock_actual <= stock_minimo')
            ->count();
        $totalSeguridad = Producto::where('estado', true)
            ->whereRaw('stock_actual > stock_minimo')
            ->whereRaw('stock_actual <= stock_seguridad')
            ->count();
        $totalRiesgo = $totalAgotados + $totalCriticos + $totalSeguridad;

        $categorias = Categoria::orderBy('nombre')->get();

        return view('inventario.alertas', compact(
            'productosEnAlerta',
            'totalAgotados',
            'totalCriticos',
            'totalSeguridad',
            'totalRiesgo',
            'categorias'
        ));
    }

    /**
     * CU17 / CU18: Listar historial de ajustes y formulario para registrar nuevos.
     */
    public function ajustes(Request $request, ExportadorUniversalService $exportador)
    {
        $query = AjusteInventario::with(['producto.categoria', 'usuario.rol']);

        if ($request->filled('tipo_ajuste')) {
            $query->where('tipo_ajuste', $request->tipo_ajuste);
        }

        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            $query->whereHas('producto', function ($q) use ($buscar) {
                $q->where('codigo_interno', 'ilike', "%{$buscar}%")
                  ->orWhere('codigo_oem', 'ilike', "%{$buscar}%")
                  ->orWhere('nombre_pieza', 'ilike', "%{$buscar}%");
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_ajuste', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_ajuste', '<=', $request->fecha_hasta);
        }

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderBy('fecha_ajuste', 'desc')->orderBy('id_ajuste', 'desc')->get();
            $encabezados = ['ID', 'Fecha', 'SKU', 'Repuesto', 'Tipo Ajuste', 'Cantidad', 'Motivo', 'Responsable'];
            $filas = $lista->map(fn($a) => [
                (int) $a->id_ajuste,
                optional($a->fecha_ajuste)->format('d/m/Y H:i') ?? '-',
                $a->producto->codigo_interno ?? '-',
                $a->producto->nombre_pieza ?? '-',
                $a->tipo_ajuste,
                (int) $a->cantidad_ajustada,
                $a->motivo ?? '-',
                $a->usuario->nombre_completo ?? 'Sistema',
            ])->toArray();

            return $exportador->exportarLista(
                $export,
                'ajustes_inventario_' . now()->format('Ymd_His'),
                'Historial de Ajustes de Inventario y Salidas Internas',
                ['Tipo: ' . ($request->tipo_ajuste ?: 'Todos') . ' | Rango: ' . ($request->fecha_desde ?: 'Inicio') . ' al ' . ($request->fecha_hasta ?: 'Actual')],
                $encabezados,
                $filas,
                ['Total Ajustes' => (string) $lista->count()],
                [],
                'landscape'
            );
        }

        $ajustes = $query->orderBy('fecha_ajuste', 'desc')
            ->orderBy('id_ajuste', 'desc')
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::where('estado', true)
            ->orderBy('nombre_pieza', 'asc')
            ->get(['id_producto', 'codigo_interno', 'codigo_oem', 'nombre_pieza', 'stock_actual']);

        return view('inventario.ajustes', compact('ajustes', 'productos'));
    }

    /**
     * CU17 / CU18: Registrar ajuste manual o salida por uso interno mediante Smart-DB SP.
     */
    public function storeAjuste(Request $request)
    {
        $request->validate([
            'id_producto' => 'required|integer|exists:productos,id_producto',
            'tipo_ajuste' => 'required|string|in:Merma,Pérdida/Extravío,Conteo Físico,Devolución Dañada,Uso Interno',
            'cantidad'    => 'required|integer',
            'motivo'      => 'required|string|min:10',
        ], [
            'id_producto.required' => 'Debe seleccionar un repuesto para el ajuste.',
            'id_producto.exists'   => 'El repuesto seleccionado no existe.',
            'tipo_ajuste.in'       => 'El tipo de ajuste seleccionado no es válido.',
            'cantidad.required'    => 'La cantidad o conteo es obligatoria.',
            'motivo.min'           => 'El motivo debe tener al menos 10 caracteres obligatorios.',
        ]);

        try {
            DB::statement("CALL sp_registrar_ajuste_inventario(?, ?, ?, ?, ?)", [
                (int)$request->id_producto,
                auth()->id(),
                $request->tipo_ajuste,
                (int)$request->cantidad,
                trim($request->motivo),
            ]);

            return back()->with('success', "Ajuste ({$request->tipo_ajuste}) registrado exitosamente en el catálogo.");
        } catch (\Throwable $e) {
            $mensaje = $e->getMessage();
            if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $mensaje, $matches)) {
                $mensaje = trim($matches[1]);
            }
            return back()->withInput()->with('error', $mensaje);
        }
    }
}
