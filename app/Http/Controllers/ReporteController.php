<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Services\ExportacionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ExportacionService $exportService
    ) {}

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

        $kpis = (object) [
            'total_items'        => $filas->count(),
            'alta_rotacion'      => $filas->where('clasificacion', 'Alta Rotación')->count(),
            'rotacion_media'     => $filas->where('clasificacion', 'Rotación Media')->count(),
            'baja_rotacion'      => $filas->where('clasificacion', 'Baja Rotación / Hueso')->count(),
            'unidades_vendidas'  => (int) $filas->sum('unidades_vendidas'),
            'stock_inmovilizado' => (int) $filas->where('clasificacion', 'Baja Rotación / Hueso')->sum('stock_actual'),
        ];

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $encabezados = ['SKU', 'Código OEM', 'Repuesto', 'Categoría', 'Stock', 'Mín.', 'Uds. Vendidas', 'Notas', 'Rotación (%)', 'Días s/ Mov.', 'Clasificación'];
            $datosExport = $filas->map(fn($r) => [
                $r->codigo_interno,
                $r->codigo_oem,
                $r->nombre_pieza,
                $r->categoria,
                (int) $r->stock_actual,
                (int) $r->stock_minimo,
                (int) $r->unidades_vendidas,
                (int) $r->frecuencia_ventas,
                number_format((float) $r->indice_rotacion * 100, 1) . '%',
                (int) $r->dias_sin_movimiento,
                $r->clasificacion,
            ])->toArray();

            $subtitulos = [
                'Período evaluado: ' . Carbon::parse($fechaInicio)->format('d/m/Y') . ' al ' . Carbon::parse($fechaFin)->format('d/m/Y'),
                'Clasificación: ' . ($clasif ?: 'Todas') . ' | Categoría: ' . ($idCategoria ? (Categoria::find($idCategoria)?->nombre ?? 'Filtrada') : 'Todas'),
            ];
            $kpisExport = [
                'Alta Rotación'      => $kpis->alta_rotacion . ' ítems',
                'Rotación Media'     => $kpis->rotacion_media . ' ítems',
                'Baja Rotación'      => $kpis->baja_rotacion . ' ítems',
                'Unidades Vendidas'  => number_format($kpis->unidades_vendidas),
            ];
            $nombreBase = "reporte_rotacion_repuestos_{$fechaInicio}_a_{$fechaFin}";

            return $export === 'pdf'
                ? $this->exportService->exportarPdf($nombreBase, 'REPORTE DE ROTACIÓN DE REPUESTOS', $subtitulos, $encabezados, $datosExport, $kpisExport, [], 'landscape')
                : $this->exportService->exportarExcel($nombreBase, 'REPORTE DE ROTACIÓN DE REPUESTOS', $subtitulos, $encabezados, $datosExport, $kpisExport);
        }

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

        $totalIngresos = (float) $filas->sum('ingresos_netos');
        $totalCostos   = (float) $filas->sum('costo_total_estimado');
        $totalUtilidad = round($totalIngresos - $totalCostos, 2);
        $margenGlobal  = $totalIngresos > 0 ? round(($totalUtilidad / $totalIngresos) * 100, 2) : 0.00;

        $kpis = (object) [
            'ingresos_netos'    => $totalIngresos,
            'costo_estimado'    => $totalCostos,
            'utilidad_bruta'    => $totalUtilidad,
            'margen_global'     => $margenGlobal,
            'unidades_vendidas' => (int) $filas->sum('cantidad_vendida'),
            'items_analizados'  => $filas->count(),
        ];

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $encabezados = ['SKU', 'Repuesto', 'Categoría', 'Cant. Vendida', 'Ingresos Netos (Bs.)', 'Costo Estimado (Bs.)', 'Utilidad Bruta (Bs.)', 'Margen Real (%)'];
            $datosExport = $filas->map(fn($r) => [
                $r->codigo_interno,
                $r->nombre_pieza,
                $r->categoria,
                (int) $r->cantidad_vendida,
                $export === 'excel' ? round((float) $r->ingresos_netos, 2) : number_format((float) $r->ingresos_netos, 2),
                $export === 'excel' ? round((float) $r->costo_total_estimado, 2) : number_format((float) $r->costo_total_estimado, 2),
                $export === 'excel' ? round((float) $r->utilidad_bruta, 2) : number_format((float) $r->utilidad_bruta, 2),
                number_format((float) $r->margen_real_porcentaje, 2) . '%',
            ])->toArray();

            $subtitulos = [
                'Período analizado: ' . Carbon::parse($fechaInicio)->format('d/m/Y') . ' al ' . Carbon::parse($fechaFin)->format('d/m/Y'),
                'Categoría: ' . ($idCategoria ? (Categoria::find($idCategoria)?->nombre ?? 'Filtrada') : 'Todas las categorías'),
            ];
            $kpisExport = [
                'Ingresos Netos'  => 'Bs. ' . number_format($totalIngresos, 2),
                'Costo Estimado'  => 'Bs. ' . number_format($totalCostos, 2),
                'Utilidad Bruta'  => 'Bs. ' . number_format($totalUtilidad, 2),
                'Margen Promedio' => number_format($margenGlobal, 2) . '%',
            ];
            $totales = [
                'TOTALES',
                '',
                '',
                $kpis->unidades_vendidas,
                $export === 'excel' ? round($totalIngresos, 2) : 'Bs. ' . number_format($totalIngresos, 2),
                $export === 'excel' ? round($totalCostos, 2) : 'Bs. ' . number_format($totalCostos, 2),
                $export === 'excel' ? round($totalUtilidad, 2) : 'Bs. ' . number_format($totalUtilidad, 2),
                number_format($margenGlobal, 2) . '%',
            ];
            $nombreBase = "reporte_rentabilidad_{$fechaInicio}_a_{$fechaFin}";

            return $export === 'pdf'
                ? $this->exportService->exportarPdf($nombreBase, 'REPORTE DE RENTABILIDAD Y MARGEN REAL', $subtitulos, $encabezados, $datosExport, $kpisExport, $totales, 'landscape')
                : $this->exportService->exportarExcel($nombreBase, 'REPORTE DE RENTABILIDAD Y MARGEN REAL', $subtitulos, $encabezados, $datosExport, $kpisExport, $totales);
        }

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

        $kpis = (object) [
            'proveedores_activos' => $filas->count(),
            'total_ordenes'       => (int) $filas->sum('total_ordenes'),
            'unidades_compradas'  => (int) $filas->sum('unidades_compradas'),
            'monto_total'         => (float) $filas->sum('monto_total_comprado'),
            'monto_pagado'        => (float) $filas->sum('monto_pagado'),
            'saldo_pendiente'     => (float) $filas->sum('saldo_pendiente_credito'),
        ];

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $encabezados = ['Distribuidor / Razón Social', 'NIT', 'Condición Comercial', 'Órdenes', 'Unidades', 'Total Comprado (Bs.)', 'Pagado (Bs.)', 'Saldo Crédito (Bs.)', 'Última Compra'];
            $datosExport = $filas->map(fn($r) => [
                $r->razon_social,
                $r->nit ?: 'S/N',
                $r->condicion_comercial,
                (int) $r->total_ordenes,
                (int) $r->unidades_compradas,
                $export === 'excel' ? round((float) $r->monto_total_comprado, 2) : number_format((float) $r->monto_total_comprado, 2),
                $export === 'excel' ? round((float) $r->monto_pagado, 2) : number_format((float) $r->monto_pagado, 2),
                $export === 'excel' ? round((float) $r->saldo_pendiente_credito, 2) : number_format((float) $r->saldo_pendiente_credito, 2),
                $r->ultima_compra ? Carbon::parse($r->ultima_compra)->format('d/m/Y') : '-',
            ])->toArray();

            $subtitulos = [
                'Período consultado: ' . Carbon::parse($fechaInicio)->format('d/m/Y') . ' al ' . Carbon::parse($fechaFin)->format('d/m/Y'),
                'Distribuidor: ' . ($idProveedor ? (Proveedor::find($idProveedor)?->razon_social ?? 'Filtrado') : 'Todos los distribuidores'),
            ];
            $kpisExport = [
                'Total Adquirido' => 'Bs. ' . number_format($kpis->monto_total, 2),
                'Monto Pagado'    => 'Bs. ' . number_format($kpis->monto_pagado, 2),
                'Saldo Crédito'   => 'Bs. ' . number_format($kpis->saldo_pendiente, 2),
                'Órdenes / Uds.'  => $kpis->total_ordenes . ' / ' . number_format($kpis->unidades_compradas),
            ];
            $totales = [
                'TOTALES CONSOLIDADOS',
                '',
                '',
                $kpis->total_ordenes,
                $kpis->unidades_compradas,
                $export === 'excel' ? round($kpis->monto_total, 2) : 'Bs. ' . number_format($kpis->monto_total, 2),
                $export === 'excel' ? round($kpis->monto_pagado, 2) : 'Bs. ' . number_format($kpis->monto_pagado, 2),
                $export === 'excel' ? round($kpis->saldo_pendiente, 2) : 'Bs. ' . number_format($kpis->saldo_pendiente, 2),
                '',
            ];
            $nombreBase = "reporte_compras_distribuidor_{$fechaInicio}_a_{$fechaFin}";

            return $export === 'pdf'
                ? $this->exportService->exportarPdf($nombreBase, 'COMPRAS Y OBLIGACIONES POR DISTRIBUIDOR', $subtitulos, $encabezados, $datosExport, $kpisExport, $totales, 'landscape')
                : $this->exportService->exportarExcel($nombreBase, 'COMPRAS Y OBLIGACIONES POR DISTRIBUIDOR', $subtitulos, $encabezados, $datosExport, $kpisExport, $totales);
        }

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
        $formato     = $request->input('formato', 'estandar');

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $filas = collect(DB::select(
            'SELECT * FROM fn_reporte_balance_medios_pago(?, ?, ?, ?)',
            [$fechaInicio, $fechaFin, $idSesion, $idCajero]
        ));

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

        $export = strtolower((string) $request->input('export', ''));
        if (in_array($export, ['pdf', 'excel'], true)) {
            $encabezados = ['Sesión Caja', 'Estado', 'Apertura', 'Cajero Responsable', 'Medio de Pago', 'Tickets', 'Descuentos (Bs.)', 'Ticket Prom. (Bs.)', 'Recaudado Neto (Bs.)'];
            $datosExport = $filas->map(fn($r) => [
                '#' . $r->id_sesion,
                $r->estado_sesion,
                Carbon::parse($r->fecha_apertura)->format('d/m/Y H:i'),
                $r->cajero_responsable,
                $r->metodo_pago,
                (int) $r->cantidad_tickets,
                $export === 'excel' ? round((float) $r->total_descuento, 2) : number_format((float) $r->total_descuento, 2),
                $export === 'excel' ? round((float) $r->ticket_promedio, 2) : number_format((float) $r->ticket_promedio, 2),
                $export === 'excel' ? round((float) $r->total_neto_recaudado, 2) : number_format((float) $r->total_neto_recaudado, 2),
            ])->toArray();

            $subtitulos = [
                'Período: ' . Carbon::parse($fechaInicio)->format('d/m/Y') . ' al ' . Carbon::parse($fechaFin)->format('d/m/Y'),
                'Sesión: ' . ($idSesion ? "Caja #{$idSesion}" : 'Todas') . ' | Cajero: ' . ($idCajero ? (Usuario::find($idCajero)?->nombre_completo ?? 'Filtrado') : 'Todos'),
            ];
            $kpisExport = [
                'Total Neto Recaudado' => 'Bs. ' . number_format($totalNeto, 2),
                'Efectivo'             => 'Bs. ' . number_format($totalEfectivo, 2),
                'Digital (QR/Tarjeta)' => 'Bs. ' . number_format($totalDigital, 2),
                'Mixto / Tickets'      => 'Bs. ' . number_format($totalMixto, 2) . " ({$totalTickets} tkt)",
            ];
            $totales = [
                'TOTALES',
                '',
                '',
                '',
                '',
                $totalTickets,
                $export === 'excel' ? round($totalDesc, 2) : 'Bs. ' . number_format($totalDesc, 2),
                $export === 'excel' ? round($kpis->ticket_promedio, 2) : 'Bs. ' . number_format($kpis->ticket_promedio, 2),
                $export === 'excel' ? round($totalNeto, 2) : 'Bs. ' . number_format($totalNeto, 2),
            ];
            $nombreBase = "reporte_balance_medios_pago_{$fechaInicio}_a_{$fechaFin}";

            return $export === 'pdf'
                ? $this->exportService->exportarPdf($nombreBase, 'BALANCE POR MEDIOS DE PAGO Y TURNOS', $subtitulos, $encabezados, $datosExport, $kpisExport, $totales, 'landscape')
                : $this->exportService->exportarExcel($nombreBase, 'BALANCE POR MEDIOS DE PAGO Y TURNOS', $subtitulos, $encabezados, $datosExport, $kpisExport, $totales);
        }

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
}
