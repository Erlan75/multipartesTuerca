<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportadorUniversalService
{
    /**
     * Despacha la exportación en PDF o Excel (.xlsx) según el parámetro de formato.
     */
    public function exportarLista(
        string $formato,
        string $nombreArchivo,
        string $titulo,
        string|array $subtitulos,
        array $encabezados,
        array $filas,
        array $kpis = [],
        array $totales = [],
        string $orientacion = 'auto',
        ?string $notaPie = null
    ): Response|BinaryFileResponse {
        if (strtolower(trim($formato)) === 'excel') {
            return $this->exportarExcel(
                $nombreArchivo,
                $titulo,
                $subtitulos,
                $encabezados,
                $filas,
                $kpis,
                $totales
            );
        }

        return $this->exportarPdf(
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

    /**
     * Genera y descarga un documento PDF institucional con orientación automática y paginación.
     */
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
        $nombrePdf = str_ends_with(strtolower($nombreArchivo), '.pdf')
            ? $nombreArchivo
            : $nombreArchivo . '.pdf';

        $orientacionReal = in_array($orientacion, ['portrait', 'landscape'], true)
            ? $orientacion
            : (count($encabezados) >= 7 ? 'landscape' : 'portrait');

        $subsNormalizados = $this->normalizarSubtitulos($subtitulos);

        $pdf = Pdf::loadView('reportes.pdf_base', [
            'titulo'      => $titulo,
            'subtitulos'  => $subsNormalizados,
            'encabezados' => $encabezados,
            'filas'       => $filas,
            'kpis'        => $kpis,
            'totales'     => $totales,
            'notaPie'     => $notaPie,
            'generadoEn'  => now()->format('d/m/Y H:i:s'),
            'usuario'     => auth()->user()->nombre_completo ?? 'Sistema',
        ])->setPaper('letter', $orientacionReal);

        return $pdf->download($nombrePdf);
    }

    /**
     * Genera y descarga un libro nativo de Excel (.xlsx) estilizado con OpenSpout.
     */
    public function exportarExcel(
        string $nombreArchivo,
        string $titulo,
        string|array $subtitulos,
        array $encabezados,
        array $filas,
        array $kpis = [],
        array $totales = []
    ): BinaryFileResponse {
        $nombreXlsx = str_ends_with(strtolower($nombreArchivo), '.xlsx')
            ? $nombreArchivo
            : $nombreArchivo . '.xlsx';

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        $subsNormalizados = $this->normalizarSubtitulos($subtitulos);

        // Detectar columnas monetarias según sus encabezados
        $colsMoneda = [];
        foreach (array_values($encabezados) as $idx => $head) {
            $h = mb_strtolower((string) $head);
            $colsMoneda[$idx] = str_contains($h, '(bs')
                || str_contains($h, 'bs.)')
                || str_contains($h, 'monto')
                || str_contains($h, 'precio')
                || str_contains($h, 'costo')
                || str_contains($h, 'subtotal')
                || str_contains($h, 'saldo')
                || str_contains($h, 'descuento')
                || str_contains($h, 'utilidad')
                || str_contains($h, 'ingresos')
                || str_contains($h, 'recaudado')
                || str_contains($h, 'pagado')
                || str_contains($h, 'diferencia');
        }

        // Calcular anchos de columna dinámicos en base a encabezados y contenido
        $numCols = max(1, count($encabezados));
        $anchos = [];
        foreach (array_values($encabezados) as $idx => $head) {
            $anchos[$idx] = max(12, mb_strlen((string) $head) + 4);
        }
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $idx => $val) {
                $len = mb_strlen((string) $val) + 3;
                $anchos[$idx] = min(44, max($anchos[$idx] ?? 14, $len));
            }
        }

        $options = new Options();
        for ($c = 1; $c <= $numCols; $c++) {
            $options->setColumnWidth((float) ($anchos[$c - 1] ?? 18), $c);
        }

        $writer = new Writer($options);
        $writer->openToFile($tmpPath);

        $estiloEmpresa = (new Style())
            ->setFontBold()
            ->setFontSize(13)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('0F172A');

        $estiloTitulo = (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor('0F172A')
            ->setBackgroundColor('E2E8F0');

        $estiloSubtitulo = (new Style())
            ->setFontSize(9)
            ->setFontColor('475569');

        $estiloHeader = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('0F172A');

        $estiloMoneda = (new Style())
            ->setFormat('"Bs. "#,##0.00')
            ->setCellAlignment(CellAlignment::RIGHT);

        $estiloEntero = (new Style())
            ->setFormat('#,##0')
            ->setCellAlignment(CellAlignment::CENTER);

        $estiloTotal = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('0F172A')
            ->setBackgroundColor('F1F5F9');

        $estiloTotalMoneda = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('0F172A')
            ->setBackgroundColor('F1F5F9')
            ->setFormat('"Bs. "#,##0.00')
            ->setCellAlignment(CellAlignment::RIGHT);

        // Encabezado institucional
        $writer->addRow(Row::fromValues(['MULTIPARTES EL TUERCA - SANTA CRUZ'], $estiloEmpresa));
        $writer->addRow(Row::fromValues([mb_strtoupper($titulo)], $estiloTitulo));
        foreach ($subsNormalizados as $sub) {
            $writer->addRow(Row::fromValues([$sub], $estiloSubtitulo));
        }
        $writer->addRow(Row::fromValues([
            'Emitido: ' . now()->format('d/m/Y H:i:s') . ' | Exportado por: ' . (auth()->user()->nombre_completo ?? 'Sistema'),
        ], $estiloSubtitulo));

        // Resumen KPIs opcional
        if (!empty($kpis)) {
            $writer->addRow(Row::fromValues([]));
            foreach ($kpis as $etiqueta => $valor) {
                $writer->addRow(Row::fromValues([(string) $etiqueta, (string) $valor], $estiloTotal));
            }
        }

        // Cabecera de tabla
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(array_values($encabezados), $estiloHeader));

        // Filas de datos con tipado y formato numérico/monetario
        foreach ($filas as $fila) {
            $celdas = [];
            foreach (array_values($fila) as $idx => $v) {
                if (is_int($v)) {
                    $celdas[] = ($colsMoneda[$idx] ?? false)
                        ? Cell::fromValue((float) $v, $estiloMoneda)
                        : Cell::fromValue($v, $estiloEntero);
                } elseif (is_float($v)) {
                    $celdas[] = Cell::fromValue($v, ($colsMoneda[$idx] ?? false) ? $estiloMoneda : null);
                } else {
                    $celdas[] = Cell::fromValue($v === null ? '' : (string) $v);
                }
            }
            $writer->addRow(new Row($celdas));
        }

        // Fila de totales opcional
        if (!empty($totales)) {
            $celdasTotal = [];
            foreach (array_values($totales) as $idx => $v) {
                if ((is_int($v) || is_float($v)) && ($colsMoneda[$idx] ?? false)) {
                    $celdasTotal[] = Cell::fromValue((float) $v, $estiloTotalMoneda);
                } else {
                    $celdasTotal[] = Cell::fromValue($v === null ? '' : $v, $estiloTotal);
                }
            }
            $writer->addRow(new Row($celdasTotal));
        }

        $writer->close();

        return response()->download($tmpPath, $nombreXlsx, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function normalizarSubtitulos(string|array $subtitulos): array
    {
        if (is_string($subtitulos)) {
            return trim($subtitulos) !== '' ? [trim($subtitulos)] : [];
        }

        return array_values(array_filter(array_map(fn($s) => trim((string) $s), $subtitulos), fn($s) => $s !== ''));
    }
}
