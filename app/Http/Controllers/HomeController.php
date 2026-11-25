<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Muestra la vitrina comercial y catálogo público de repuestos (Capa Zero).
     * Optimizado sin consultas N+1.
     */
    public function index(Request $request)
    {
        $buscar             = trim((string) $request->input('buscar', ''));
        $marcaSeleccionada  = trim((string) $request->input('marca', ''));
        $modeloSeleccionado = trim((string) $request->input('modelo', ''));
        $categoriaId        = $request->filled('categoria_id') ? (int) $request->input('categoria_id') : null;
        $vehiculoId         = $request->filled('vehiculo_id') ? (int) $request->input('vehiculo_id') : null;
        $anioSeleccionado   = $request->filled('anio') ? (int) $request->input('anio') : null;
        $soloStock          = $request->boolean('en_stock');

        // 1. Marcas con conteo de modelos y repuestos compatibles en 2 consultas agregadas (sin N+1)
        $modelosPorMarca = DB::table('vehiculos')
            ->select('marca', DB::raw('COUNT(DISTINCT modelo) as modelos_count'))
            ->groupBy('marca')
            ->orderBy('marca')
            ->get();

        $productosPorMarca = DB::table('compatibilidades as c')
            ->join('vehiculos as v', 'v.id_vehiculo', '=', 'c.id_vehiculo')
            ->join('productos as p', 'p.id_producto', '=', 'c.id_producto')
            ->where('p.estado', true)
            ->select('v.marca', DB::raw('COUNT(DISTINCT p.id_producto) as productos_count'))
            ->groupBy('v.marca')
            ->pluck('productos_count', 'marca');

        $marcas = $modelosPorMarca->map(fn($row) => (object) [
            'marca'           => $row->marca,
            'modelos_count'   => (int) $row->modelos_count,
            'productos_count' => (int) ($productosPorMarca[$row->marca] ?? 0),
        ]);

        // 2. Catálogo vehicular completo (para filtro facetado) y modelos de la marca elegida
        $vehiculos = Vehiculo::orderBy('marca')
            ->orderBy('modelo')
            ->orderBy('anio_inicio')
            ->get(['id_vehiculo', 'marca', 'modelo', 'anio_inicio', 'anio_fin', 'motor_cilindrada']);

        $vehiculoSeleccionado = $vehiculoId
            ? $vehiculos->firstWhere('id_vehiculo', $vehiculoId)
            : null;

        $modelos = $marcaSeleccionada !== ''
            ? $vehiculos->where('marca', $marcaSeleccionada)->pluck('modelo')->unique()->values()
            : collect();

        // 3. Categorías con conteo real de productos activos
        $categorias = Categoria::withCount(['productos' => function ($q) use ($marcaSeleccionada, $vehiculoId, $soloStock) {
            $q->where('estado', true);
            if ($soloStock) {
                $q->where('stock_actual', '>', 0);
            }
            if ($marcaSeleccionada !== '') {
                $q->whereHas('vehiculos', fn($vq) => $vq->where('marca', $marcaSeleccionada));
            }
            if ($vehiculoId) {
                $q->whereHas('vehiculos', fn($vq) => $vq->where('vehiculos.id_vehiculo', $vehiculoId));
            }
        }])->orderBy('nombre')->get();

        // 4. Consulta principal de repuestos activos con filtros combinables (CU12 / CU13)
        $query = Producto::with(['categoria', 'vehiculos', 'codigosAlternativos'])
            ->where('estado', true);

        if ($buscar !== '') {
            $searchTerm = '%' . mb_strtolower($buscar) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(nombre_pieza) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(codigo_oem) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(codigo_interno) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(marca_fabricante) LIKE ?', [$searchTerm])
                  ->orWhereHas('codigosAlternativos', function ($ca) use ($searchTerm) {
                      $ca->whereRaw('LOWER(codigo_referencia) LIKE ?', [$searchTerm])
                         ->orWhereRaw('LOWER(marca_alternativa) LIKE ?', [$searchTerm]);
                  })
                  ->orWhereHas('vehiculos', function ($vq) use ($searchTerm) {
                      $vq->whereRaw('LOWER(marca) LIKE ?', [$searchTerm])
                         ->orWhereRaw('LOWER(modelo) LIKE ?', [$searchTerm]);
                  });
            });
        }

        if ($marcaSeleccionada !== '') {
            $query->whereHas('vehiculos', fn($q) => $q->where('marca', $marcaSeleccionada));
        }

        if ($modeloSeleccionado !== '') {
            $query->whereHas('vehiculos', fn($q) => $q->where('modelo', $modeloSeleccionado));
        }

        if ($vehiculoId) {
            $query->whereHas('vehiculos', fn($q) => $q->where('vehiculos.id_vehiculo', $vehiculoId));
        }

        if ($anioSeleccionado) {
            $query->whereHas('vehiculos', function ($q) use ($anioSeleccionado) {
                $q->where('anio_inicio', '<=', $anioSeleccionado)
                  ->where('anio_fin', '>=', $anioSeleccionado);
            });
        }

        if ($categoriaId) {
            $query->where('id_categoria', $categoriaId);
        }

        if ($soloStock) {
            $query->where('stock_actual', '>', 0);
        }

        $productos = $query
            ->orderByRaw('CASE WHEN stock_actual > 0 THEN 0 ELSE 1 END')
            ->orderBy('nombre_pieza', 'asc')
            ->paginate(12)
            ->withQueryString();

        // 5. Índice ligero para el buscador Autocomplete del Hero (1 consulta con eager loading)
        $autocompleteCatalog = Producto::with([
                'categoria:id_categoria,nombre',
                'vehiculos:id_vehiculo,marca,modelo,anio_inicio,anio_fin',
            ])
            ->where('estado', true)
            ->orderByDesc('stock_actual')
            ->get([
                'id_producto',
                'id_categoria',
                'nombre_pieza',
                'codigo_oem',
                'codigo_interno',
                'marca_fabricante',
                'precio_venta_sugerido',
                'stock_actual',
                'imagen_url',
            ])
            ->map(fn($p) => [
                'id_producto' => (int) $p->id_producto,
                'nombre'      => (string) $p->nombre_pieza,
                'oem'         => (string) ($p->codigo_oem ?? ''),
                'sku'         => (string) ($p->codigo_interno ?? ''),
                'marca'       => (string) ($p->marca_fabricante ?? 'OEM'),
                'categoria'   => (string) ($p->categoria?->nombre ?? 'Repuesto'),
                'precio'      => (float) $p->precio_venta_sugerido,
                'stock'       => (int) $p->stock_actual,
                'imagen'      => $p->foto_url,
                'fallback'    => $p->foto_fallback_url,
                'vehiculos'   => $p->vehiculos->map(fn($v) => trim("{$v->marca} {$v->modelo} {$v->anio_inicio}-{$v->anio_fin}"))->implode(' · '),
            ])
            ->values();

        // 6. Métricas reales para Count-up Stats
        $statsRow = DB::table('productos')
            ->where('estado', true)
            ->selectRaw('COUNT(*) as total_productos, COALESCE(SUM(stock_actual), 0) as total_unidades')
            ->first();

        $totalProductos     = (int) ($statsRow->total_productos ?? 0);
        $totalUnidadesStock = (int) ($statsRow->total_unidades ?? 0);
        $totalMarcas        = $marcas->count();
        $totalModelos       = $vehiculos->count();

        $categoriaSeleccionada = $categoriaId
            ? $categorias->firstWhere('id_categoria', $categoriaId)
            : null;

        return view('welcome', compact(
            'marcas',
            'modelos',
            'vehiculos',
            'categorias',
            'productos',
            'buscar',
            'marcaSeleccionada',
            'modeloSeleccionado',
            'categoriaId',
            'categoriaSeleccionada',
            'vehiculoId',
            'vehiculoSeleccionado',
            'soloStock',
            'totalProductos',
            'totalUnidadesStock',
            'totalMarcas',
            'totalModelos',
            'autocompleteCatalog'
        ));
    }
}
