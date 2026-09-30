<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BitacoraAuditoria;

class AuditoriaController extends Controller
{
    /**
     * CU06: Consultar bitácora de auditoría con filtros.
     */
    public function index(Request $request)
    {
        $query = BitacoraAuditoria::with('usuario');

        // Filtro por módulo
        if ($request->filled('modulo')) {
            $query->where('modulo_afectado', 'ilike', '%' . $request->modulo . '%');
        }

        // Filtro por acción
        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }

        // Filtro por fecha desde
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_hora', '>=', $request->fecha_desde);
        }

        // Filtro por fecha hasta
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_hora', '<=', $request->fecha_hasta);
        }

        $bitacoras = $query->orderBy('fecha_hora', 'desc')->paginate(20)->withQueryString();

        // Obtener valores únicos para los filtros
        $modulos = BitacoraAuditoria::select('modulo_afectado')
            ->distinct()
            ->orderBy('modulo_afectado')
            ->pluck('modulo_afectado');

        $acciones = ['INSERT', 'UPDATE', 'DELETE', 'ANULACION', 'LOGIN', 'LOGOUT'];

        return view('auditoria.index', compact('bitacoras', 'modulos', 'acciones'));
    }
}
