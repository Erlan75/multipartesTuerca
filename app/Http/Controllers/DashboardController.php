<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Vehiculo;
use App\Models\Usuario;
use App\Models\Rol;
use App\Services\ExportacionService;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard principal con datos dinámicos.
     */
    public function index()
    {
        // Blindaje contra acceso de clientes al panel administrativo
        if (auth()->check() && (auth()->user()->id_rol === 5 || auth()->user()->rol?->nombre_rol === 'Cliente')) {
            return redirect()->route('home')->with('warning', 'Área exclusiva para personal administrativo.');
        }

        // ── Últimos productos registrados (simulan movimientos recientes) ──
        $productosRecientes = Producto::with(['categoria', 'compatibilidades.vehiculo'])
            ->where('estado', true)
            ->orderByDesc('id_producto')
            ->take(5)
            ->get();

        $totalProductos = Producto::count();

        // ── Categorías con conteo de productos ──
        $categoriasConConteo = Categoria::withCount('productos')
            ->orderByDesc('productos_count')
            ->get();

        $totalPiezas = $categoriasConConteo->sum('productos_count');

        return view('dashboard', compact(
            'productosRecientes',
            'totalProductos',
            'categoriasConConteo',
            'totalPiezas'
        ));
    }

    /**
     * Exporta el resumen ejecutivo del catálogo activo en PDF o Excel (.xlsx).
     */
    public function exportar(string $formato, ExportacionService $exportacion)
    {
        if (auth()->check() && (auth()->user()->id_rol === 5 || auth()->user()->rol?->nombre_rol === 'Cliente')) {
            abort(403, 'Área exclusiva para personal administrativo.');
        }

        $productos = Producto::with(['categoria'])
            ->where('estado', true)
            ->orderBy('nombre_pieza')
            ->get();

        $encabezados = [
            'Código Interno',
            'Código OEM',
            'Nombre de Pieza',
            'Categoría',
            'Marca',
            'Ubicación',
            'Stock Actual',
            'Stock Mín.',
            'Precio Sugerido (Bs.)',
        ];

        $filas = $productos->map(function ($p) {
            $ubicacion = trim(($p->pasillo ?? '-') . ' / ' . ($p->estante ?? '-'));
            return [
                $p->codigo_interno ?? '-',
                $p->codigo_oem ?? '-',
                $p->nombre_pieza,
                $p->categoria?->nombre ?? 'General',
                $p->marca_fabricante ?? '-',
                $ubicacion,
                (int) $p->stock_actual,
                (int) $p->stock_minimo,
                round((float) $p->precio_venta_sugerido, 2),
            ];
        })->all();

        $stockCritico = $productos->filter(fn($p) => (int) $p->stock_actual <= (int) $p->stock_minimo)->count();
        $kpis = [
            'Repuestos Activos' => (string) $productos->count(),
            'Unidades en Stock' => number_format((int) $productos->sum('stock_actual')),
            'En Stock Crítico'  => (string) $stockCritico,
        ];

        $subtitulos = [
            'Resumen de inventario activo registrado en el sistema.',
            'Generado desde el Panel Principal (Dashboard).',
        ];

        $nombreArchivo = 'resumen_catalogo_' . now()->format('Ymd_His');

        if ($formato === 'excel') {
            return $exportacion->exportarExcel(
                $nombreArchivo,
                'RESUMEN DE INVENTARIO Y CATÁLOGO ACTIVO',
                $subtitulos,
                $encabezados,
                $filas,
                $kpis
            );
        }

        return $exportacion->exportarPdf(
            $nombreArchivo,
            'Resumen de Inventario y Catálogo Activo',
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            [],
            'landscape',
            'Documento emitido desde el Dashboard Administrativo de Multipartes El Tuerca.'
        );
    }
}
