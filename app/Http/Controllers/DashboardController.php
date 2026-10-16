<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Vehiculo;
use App\Models\Usuario;
use App\Models\Rol;

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
}
