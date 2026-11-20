<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    /**
     * Reporte de Rotación de Repuestos e Identificación de Productos de Baja Salida.
     * Roles: Administrador, Bodeguero.
     */
    public function rotacion(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subDays(90)->toDateString());
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->toDateString());
        $idCategoria = $request->filled('id_categoria') ? (int) $request->input('id_categoria') : null;
        $clasif      = $request->input('clasificacion');

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $filas = collect(DB::select(
            'SELECT * FROM fn_reporte_rotacion_repuestos(?, ?, ?)',
            [$fechaInicio, $fechaFin, $idCategoria]
        ));

        if (!empty($clasif)) {
            $filas = $filas->filter(fn($f) => $f->clasificacion === $clasif)->values();
        }

        if ($request->input('export') === 'csv') {
            return $this->exportarCsv(
                "reporte_rotacion_repuestos_{$fechaInicio}_a_{$fechaFin}.csv",
                ['SKU', 'Código OEM', 'Repuesto', 'Categoría', 'Stock Actual', 'Stock Mínimo', 'Unidades Vendidas', 'Frecuencia Ventas', 'Índice Rotación', 'Días sin Movimiento', 'Clasificación'],
                $filas->map(fn($r) => [
                    $r->codigo_interno,
                    $r->codigo_oem,
                    $r->nombre_pieza,
                    $r->categoria,
                    (int) $r->stock_actual,
                    (int) $r->stock_minimo,
                    (int) $r->unidades_vendidas,
                    (int) $r->frecuencia_ventas,
                    number_format((float) $r->indice_rotacion, 4, '.', ''),
                    (int) $r->dias_sin_movimiento,
                    $r->clasificacion,
                ])->toArray()
            );
        }

        $kpis = (object) [
            'total_items'       => $filas->count(),
            'alta_rotacion'     => $filas->where('clasificacion', 'Alta Rotación')->count(),
            'rotacion_media'    => $filas->where('clasificacion', 'Rotación Media')->count(),
            'baja_rotacion'     => $filas->where('clasificacion', 'Baja Rotación / Hueso')->count(),
            'unidades_vendidas' => (int) $filas->sum('unidades_vendidas'),
            'stock_inmovilizado'=> (int) $filas->where('clasificacion', 'Baja Rotación / Hueso')->sum('stock_actual'),
        ];

        $categorias = Categoria::orderBy('nombre')->get();

        return view('reportes.rotacion', compact(
            'filas',
            'kpis',
            'categorias',
            'fechaInicio',
            'fechaFin',
            'idCategoria',
            'clasif'
        ));
    }

    /**
     * Reporte de Rentabilidad y Márgenes Reales por Repuesto.
     * Rol exclusivo: Administrador.
     */
    public function rentabilidad(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subDays(90)->toDateString());
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->toDateString());
        $idCategoria = $request->filled('id_categoria') ? (int) $request->input('id_categoria') : null;

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $filas = collect(DB::select(
            'SELECT * FROM fn_reporte_rentabilidad(?, ?, ?)',
            [$fechaInicio, $fechaFin, $idCategoria]
        ));

        if ($request->input('export') === 'csv') {
            return $this->exportarCsv(
                "reporte_rentabilidad_{$fechaInicio}_a_{$fechaFin}.csv",
                ['SKU', 'Repuesto', 'Categoría', 'Cantidad Vendida', 'Ingresos Netos (Bs.)', 'Costo Estimado (Bs.)', 'Utilidad Bruta (Bs.)', 'Margen Real (%)'],
                $filas->map(fn($r) => [
                    $r->codigo_interno,
                    $r->nombre_pieza,
                    $r->categoria,
                    (int) $r->cantidad_vendida,
                    number_format((float) $r->ingresos_netos, 2, '.', ''),
                    number_format((float) $r->costo_total_estimado, 2, '.', ''),
                    number_format((float) $r->utilidad_bruta, 2, '.', ''),
                    number_format((float) $r->margen_real_porcentaje, 2, '.', ''),
                ])->toArray()
            );
        }

        $totalIngresos = (float) $filas->sum('ingresos_netos');
        $totalCostos   = (float) $filas->sum('costo_total_estimado');
        $totalUtilidad = round($totalIngresos - $totalCostos, 2);
        $margenGlobal  = $totalIngresos > 0 ? round(($totalUtilidad / $totalIngresos) * 100, 2) : 0.00;

        $kpis = (object) [
            'ingresos_netos'   => $totalIngresos,
            'costo_estimado'   => $totalCostos,
            'utilidad_bruta'   => $totalUtilidad,
            'margen_global'    => $margenGlobal,
            'unidades_vendidas'=> (int) $filas->sum('cantidad_vendida'),
            'items_analizados' => $filas->count(),
        ];

        $categorias = Categoria::orderBy('nombre')->get();

        return view('reportes.rentabilidad', compact(
            'filas',
            'kpis',
            'categorias',
            'fechaInicio',
            'fechaFin',
            'idCategoria'
        ));
    }

    /**
     * Reporte de Compras y Obligaciones por Distribuidor / Proveedor.
     * Roles: Administrador, Bodeguero.
     */
    public function comprasDistribuidor(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subDays(90)->toDateString());
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->toDateString());
        $idProveedor = $request->filled('id_proveedor') ? (int) $request->input('id_proveedor') : null;

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $filas = collect(DB::select(
            'SELECT * FROM fn_reporte_compras_distribuidor(?, ?, ?)',
            [$fechaInicio, $fechaFin, $idProveedor]
        ));

        if ($request->input('export') === 'csv') {
            return $this->exportarCsv(
                "reporte_compras_distribuidor_{$fechaInicio}_a_{$fechaFin}.csv",
                ['Proveedor / Razón Social', 'NIT', 'Condición Comercial', 'Órdenes', 'Unidades Compradas', 'Total Comprado (Bs.)', 'Monto Pagado (Bs.)', 'Saldo Pendiente Crédito (Bs.)', 'Última Compra'],
                $filas->map(fn($r) => [
                    $r->razon_social,
                    $r->nit ?: 'S/N',
                    $r->condicion_comercial,
                    (int) $r->total_ordenes,
                    (int) $r->unidades_compradas,
                    number_format((float) $r->monto_total_comprado, 2, '.', ''),
                    number_format((float) $r->monto_pagado, 2, '.', ''),
                    number_format((float) $r->saldo_pendiente_credito, 2, '.', ''),
                    $r->ultima_compra ?: '-',
                ])->toArray()
            );
        }

        $kpis = (object) [
            'proveedores_activos' => $filas->count(),
            'total_ordenes'       => (int) $filas->sum('total_ordenes'),
            'unidades_compradas'  => (int) $filas->sum('unidades_compradas'),
            'monto_total'         => (float) $filas->sum('monto_total_comprado'),
            'monto_pagado'        => (float) $filas->sum('monto_pagado'),
            'saldo_pendiente'     => (float) $filas->sum('saldo_pendiente_credito'),
        ];

        $proveedores = Proveedor::orderBy('razon_social')->get();

        return view('reportes.compras_distribuidor', compact(
            'filas',
            'kpis',
            'proveedores',
            'fechaInicio',
            'fechaFin',
            'idProveedor'
        ));
    }

    /**
     * Reporte de Balance de Medios de Pago y Turnos de Caja (incluye formato Ticket 80mm).
     * Roles: Administrador, Cajero.
     */
    public function balanceMediosPago(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subDays(90)->toDateString());
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->toDateString());
        $idSesion    = $request->filled('id_sesion') ? (int) $request->input('id_sesion') : null;
        $idCajero    = $request->filled('id_cajero') ? (int) $request->input('id_cajero') : null;
        $formato     = $request->input('formato', 'estandar'); // 'estandar' | 'termico'

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $filas = collect(DB::select(
            'SELECT * FROM fn_reporte_balance_medios_pago(?, ?, ?, ?)',
            [$fechaInicio, $fechaFin, $idSesion, $idCajero]
        ));

        if ($request->input('export') === 'csv') {
            return $this->exportarCsv(
                "reporte_balance_medios_pago_{$fechaInicio}_a_{$fechaFin}.csv",
                ['Sesión Caja', 'Estado Sesión', 'Apertura', 'Cajero Responsable', 'Método de Pago', 'Tickets', 'Descuentos (Bs.)', 'Recaudado Neto (Bs.)', 'Ticket Promedio (Bs.)'],
                $filas->map(fn($r) => [
                    '#' . $r->id_sesion,
                    $r->estado_sesion,
                    $r->fecha_apertura,
                    $r->cajero_responsable,
                    $r->metodo_pago,
                    (int) $r->cantidad_tickets,
                    number_format((float) $r->total_descuento, 2, '.', ''),
                    number_format((float) $r->total_neto_recaudado, 2, '.', ''),
                    number_format((float) $r->ticket_promedio, 2, '.', ''),
                ])->toArray()
            );
        }

        // Consolidado global por medio de pago
        $resumenCanales = $filas->groupBy('metodo_pago')->map(function ($grupo, $metodo) {
            $tickets = (int) $grupo->sum('cantidad_tickets');
            $neto    = (float) $grupo->sum('total_neto_recaudado');
            return (object) [
                'metodo_pago'     => $metodo,
                'tickets'         => $tickets,
                'descuentos'      => (float) $grupo->sum('total_descuento'),
                'recaudado_neto'  => $neto,
                'ticket_promedio' => $tickets > 0 ? round($neto / $tickets, 2) : 0.00,
            ];
        })->sortByDesc('recaudado_neto')->values();

        $totalNeto     = (float) $filas->sum('total_neto_recaudado');
        $totalTickets  = (int) $filas->sum('cantidad_tickets');
        $totalDesc     = (float) $filas->sum('total_descuento');
        $totalEfectivo = (float) $filas->where('metodo_pago', 'Efectivo')->sum('total_neto_recaudado');
        $totalDigital  = (float) $filas->whereIn('metodo_pago', ['Transferencia/QR', 'Tarjeta Débito/Crédito'])->sum('total_neto_recaudado');
        $totalMixto    = (float) $filas->where('metodo_pago', 'Mixto')->sum('total_neto_recaudado');

        $kpis = (object) [
            'total_recaudado' => $totalNeto,
            'total_descuento' => $totalDesc,
            'total_tickets'   => $totalTickets,
            'ticket_promedio' => $totalTickets > 0 ? round($totalNeto / $totalTickets, 2) : 0.00,
            'total_efectivo'  => $totalEfectivo,
            'total_digital'   => $totalDigital,
            'total_mixto'     => $totalMixto,
        ];

        $sesiones = SesionCaja::with('cajero')->orderByDesc('id_sesion')->limit(50)->get();
        $cajeros  = Usuario::whereHas('rol', fn($q) => $q->whereIn('nombre_rol', ['Administrador', 'Cajero', 'Vendedor']))
            ->orderBy('nombre_completo')
            ->get();

        return view('reportes.balance_medios_pago', compact(
            'filas',
            'resumenCanales',
            'kpis',
            'sesiones',
            'cajeros',
            'fechaInicio',
            'fechaFin',
            'idSesion',
            'idCajero',
            'formato'
        ));
    }

    /**
     * Genera respuesta descargable CSV con BOM UTF-8 para compatibilidad directa con Microsoft Excel.
     */
    private function exportarCsv(string $nombreArchivo, array $encabezados, array $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para tildes y eñes en Excel
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $encabezados, ';');
            foreach ($filas as $fila) {
                fputcsv($handle, $fila, ';');
            }
            fclose($handle);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
