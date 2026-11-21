<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page {
            margin: 14mm 12mm 16mm 12mm;
            @bottom-right {
                content: "Pág. " counter(page) " de " counter(pages);
                font-family: DejaVu Sans, sans-serif;
                font-size: 7.5px;
                color: #64748B;
            }
            @bottom-left {
                content: "Multipartes El Tuerca - Santa Cruz, Bolivia";
                font-family: DejaVu Sans, sans-serif;
                font-size: 7.5px;
                color: #64748B;
            }
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            color: #1E293B;
            line-height: 1.38;
        }
        .header-box {
            border-bottom: 2px solid #0055D4;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand {
            font-size: 14px;
            font-weight: bold;
            color: #0F172A;
            letter-spacing: 0.4px;
        }
        .brand-sub {
            font-size: 8.5px;
            color: #64748B;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 12.5px;
            font-weight: bold;
            color: #0055D4;
            text-align: right;
        }
        .doc-meta {
            font-size: 8px;
            color: #475569;
            text-align: right;
        }
        .sub-box {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 10px;
            font-size: 8.5px;
            color: #334155;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .kpi-cell {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            padding: 5px 7px;
            vertical-align: top;
        }
        .kpi-label {
            font-size: 7.5px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kpi-val {
            font-size: 10.5px;
            font-weight: bold;
            color: #0F172A;
            margin-top: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data-table th {
            background: #0F172A;
            color: #FFFFFF;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #0F172A;
            text-align: left;
        }
        table.data-table td {
            padding: 4.5px 6px;
            border: 1px solid #E2E8F0;
            font-size: 8.5px;
            vertical-align: top;
        }
        table.data-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        table.data-table tfoot td {
            background: #E2E8F0;
            font-weight: bold;
            color: #0F172A;
            border-top: 2px solid #0F172A;
        }
        .footer-note {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px dashed #CBD5E1;
            font-size: 8px;
            color: #64748B;
        }
    </style>
</head>
<body>
    <div class="header-box">
        <table class="header-table">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="brand">MULTIPARTES EL TUERCA - SANTA CRUZ</div>
                    <div class="brand-sub">Repuestos Japoneses y Coreanos · Av. Cristóbal de Mendoza #450 · NIT: 4589214018</div>
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <div class="doc-title">{{ $titulo }}</div>
                    <div class="doc-meta">Emitido: {{ $generadoEn }} · Usuario: {{ $usuario }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($subtitulos))
        <div class="sub-box">
            @foreach($subtitulos as $sub)
                <div>{{ $sub }}</div>
            @endforeach
        </div>
    @endif

    @if(!empty($kpis))
        <table class="kpi-table">
            <tr>
                @foreach($kpis as $etiqueta => $valor)
                    <td class="kpi-cell">
                        <div class="kpi-label">{{ $etiqueta }}</div>
                        <div class="kpi-val">{{ $valor }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                @foreach($encabezados as $th)
                    <th>{{ $th }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $fila)
                <tr>
                    @foreach($fila as $celda)
                        <td>{{ $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($encabezados) }}" style="text-align: center; padding: 12px; color: #64748B;">
                        No existen registros para mostrar con los criterios seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(!empty($totales))
            <tfoot>
                <tr>
                    @foreach($totales as $tot)
                        <td>{{ $tot }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer-note">
        {{ $notaPie ?? 'Documento generado por el Sistema de Gestión Comercial Multipartes El Tuerca (Arquitectura Smart-DB PostgreSQL).' }}
    </div>
</body>
</html>
