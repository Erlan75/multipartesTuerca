<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehiculo;
use App\Models\Categoria;
use App\Models\Producto;

class HomeController extends Controller
{
    /**
     * Muestra la vitrina comercial y catálogo público de repuestos.
     */
    public function index(Request $request)
    {
        $buscar = trim($request->input('buscar', ''));
        $marcaSeleccionada = trim($request->input('marca', ''));
        $modeloSeleccionado = trim($request->input('modelo', ''));
        $categoriaId = $request->input('categoria_id');
        $anioSeleccionado = $request->input('anio');

        // 1. Marcas con conteo de modelos y repuestos compatibles
        $marcas = Vehiculo::select('marca')
            ->distinct()
            ->orderBy('marca')
            ->get()
            ->map(function ($item) {
                $countModelos = Vehiculo::where('marca', $item->marca)->distinct('modelo')->count('modelo');
                $countProductos = Producto::where('estado', true)
                    ->whereHas('vehiculos', fn($q) => $q->where('marca', $item->marca))
                    ->count();

                return (object)[
                    'marca' => $item->marca,
                    'modelos_count' => $countModelos,
                    'productos_count' => $countProductos,
                ];
            });

        // 2. Modelos para la marca seleccionada (si aplica)
        $modelos = collect();
        if ($marcaSeleccionada) {
            $modelos = Vehiculo::where('marca', $marcaSeleccionada)
                ->select('modelo')
                ->distinct()
                ->orderBy('modelo')
                ->pluck('modelo');
        }

        // 3. Categorías con conteo real de productos activos
        $categorias = Categoria::withCount(['productos' => function ($q) use ($marcaSeleccionada) {
            $q->where('estado', true);
            if ($marcaSeleccionada) {
                $q->whereHas('vehiculos', fn($vq) => $vq->where('marca', $marcaSeleccionada));
            }
        }])->orderBy('nombre')->get();

        // 4. Consulta de repuestos activos con filtros (CU12 y CU13)
        $query = Producto::with(['categoria', 'vehiculos', 'codigosAlternativos'])
            ->where('estado', true);

        if (!empty($buscar)) {
            $searchTerm = '%' . mb_strtolower($buscar) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(nombre_pieza) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(codigo_oem) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(codigo_interno) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(marca_fabricante) LIKE ?', [$searchTerm])
                  ->orWhereHas('codigosAlternativos', function ($ca) use ($searchTerm) {
                      $ca->whereRaw('LOWER(codigo_referencia) LIKE ?', [$searchTerm])
                         ->orWhereRaw('LOWER(marca_alternativa) LIKE ?', [$searchTerm]);
                  });
            });
        }

        if (!empty($marcaSeleccionada)) {
            $query->whereHas('vehiculos', function ($q) use ($marcaSeleccionada) {
                $q->where('marca', $marcaSeleccionada);
            });
        }

        if (!empty($modeloSeleccionado)) {
            $query->whereHas('vehiculos', function ($q) use ($modeloSeleccionado) {
                $q->where('modelo', $modeloSeleccionado);
            });
        }

        if (!empty($anioSeleccionado)) {
            $query->whereHas('vehiculos', function ($q) use ($anioSeleccionado) {
                $q->where('anio_inicio', '<=', $anioSeleccionado)
                  ->where('anio_fin', '>=', $anioSeleccionado);
            });
        }

        if (!empty($categoriaId)) {
            $query->where('id_categoria', $categoriaId);
        }

        // Repuestos destacados / filtrados
        $productos = $query->orderBy('nombre_pieza', 'asc')
            ->paginate(12)
            ->withQueryString();

        // Estadísticas generales para el Hero
        $totalProductos = Producto::where('estado', true)->count();
        $totalMarcas = $marcas->count();
        $totalModelos = Vehiculo::count();

        // Categoría seleccionada actualmente (si hay filtro)
        $categoriaSeleccionada = null;
        if (!empty($categoriaId)) {
            $categoriaSeleccionada = Categoria::find($categoriaId);
        }

        return view('welcome', compact(
            'marcas',
            'modelos',
            'categorias',
            'productos',
            'buscar',
            'marcaSeleccionada',
            'modeloSeleccionado',
            'categoriaId',
            'categoriaSeleccionada',
            'totalProductos',
            'totalMarcas',
            'totalModelos'
        ));
    }
}
