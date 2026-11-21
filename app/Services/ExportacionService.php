<?php

namespace App\Services;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportacionService
{
    public function __construct(
        private readonly ExportadorUniversalService $universal
    ) {}

    public function exportarPdf(
        string $nombreArchivo,
        string $titulo,
        string|array $subtitulos,
        array $encabezados,
        array $filas,
        array $kpis = [],
        array $totales = [],
        string $orientacion = 'auto',
        ?string $notaPie = null
    ): Response {
        return $this->universal->exportarPdf(
            $nombreArchivo,
            $titulo,
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            $totales,
            $orientacion,
            $notaPie
        );
    }

    public function exportarExcel(
        string $nombreArchivo,
        string $titulo,
        string|array $subtitulos,
        array $encabezados,
        array $filas,
        array $kpis = [],
        array $totales = []
    ): BinaryFileResponse {
        return $this->universal->exportarExcel(
            $nombreArchivo,
            $titulo,
            $subtitulos,
            $encabezados,
            $filas,
            $kpis,
            $totales
        );
    }
}
