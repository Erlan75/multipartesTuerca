<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BitacoraAuditoria;
use App\Services\ExportadorUniversalService;
use Carbon\Carbon;

class AuditoriaController extends Controller
{
    /**
     * CU06: Consultar bitácora de auditoría con filtros.
     */
    public function index(Request $request, ExportadorUniversalService $exportador)
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

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $lista = $query->orderBy('fecha_hora', 'desc')->limit(1000)->get();
            $encabezados = ['ID', 'Fecha/Hora', 'Usuario', 'Módulo', 'Acción', 'Reg. ID', 'Detalle', 'IP'];
            $filas = $lista->map(fn($b) => [
                (int) $b->id_bitacora,
                Carbon::parse($b->fecha_hora)->format('d/m/Y H:i:s'),
                $b->usuario->nombre_completo ?? 'Sistema',
                $b->modulo_afectado,
                $b->accion,
                $b->registro_id ?? '-',
                mb_strimwidth((string) ($b->detalle_cambio ?? '-'), 0, 120, '...'),
                $b->ip_origen ?? '-',
            ])->toArray();

            $subtitulos = [
                'Módulo: ' . ($request->modulo ?: 'Todos') . ' | Acción: ' . ($request->accion ?: 'Todas'),
                'Rango: ' . ($request->fecha_desde ?: 'Inicio') . ' al ' . ($request->fecha_hasta ?: 'Actual') . ' (' . $lista->count() . ' eventos)',
            ];

            return $exportador->exportarLista(
                $export,
                'bitacora_auditoria_' . now()->format('Ymd_His'),
                'Bitácora de Auditoría del Sistema',
                $subtitulos,
                $encabezados,
                $filas,
                [],
                [],
                'landscape'
            );
        }

        $bitacoras = $query->orderBy('fecha_hora', 'desc')->paginate(20)->withQueryString();

        // Obtener valores únicos para los filtros
        $modulos = BitacoraAuditoria::select('modulo_afectado')
            ->distinct()
            ->orderBy('modulo_afectado')
            ->pluck('modulo_afectado');

        $acciones = BitacoraAuditoria::select('accion')
            ->distinct()
            ->orderBy('accion')
            ->pluck('accion');

        return view('auditoria.index', compact('bitacoras', 'modulos', 'acciones'));
    }
}
