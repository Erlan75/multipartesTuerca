<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSqlAssistantService
{
    /**
     * Punto de entrada principal para procesar consultas de voz o texto (Voice-to-SQL).
     * Combina Vía A (Google Gemini 2.0 Flash) con Vía B (Motor Semántico Local Resiliente).
     */
    public function procesarConsulta(string $pregunta, ?object $usuario = null): array
    {
        $preguntaLimpia = trim($pregunta);
        $rolId = (int) ($usuario->id_rol ?? 1);
        $nombreRol = $usuario->rol->nombre_rol ?? $this->obtenerNombreRol($rolId);

        // 1. Verificar permisos de rol antes de ejecutar cualquier consulta
        $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
        if ($bloqueoRol !== null) {
            return $bloqueoRol;
        }

        // 2. Intentar Vía A: Motor Google Gemini 2.0 Flash (Text-to-SQL) si existe API Key
        $apiKey = config('services.gemini.key');
        if (!empty($apiKey)) {
            try {
                $resultadoGemini = $this->consultarViaGemini($preguntaLimpia, $apiKey, $rolId, $nombreRol);
                if ($resultadoGemini !== null) {
                    return $resultadoGemini;
                }
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService: Conmutando a motor semántico local tras excepción en Gemini: ' . $e->getMessage());
            }
        }

        // 3. Vía B: Motor Semántico Local Resiliente (Fallback inmediato y determinista)
        return $this->consultarViaMotorLocal($preguntaLimpia, $rolId, $nombreRol);
    }

    /**
     * Vía A: Invoca Google Gemini 2.0 Flash con timeout estricto de 3.5s para generar SQL Read-Only.
     */
    private function consultarViaGemini(string $pregunta, string $apiKey, int $rolId, string $nombreRol): ?array
    {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

        $systemPrompt = $this->construirSystemPrompt();

        $response = Http::timeout(3.5)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type'   => 'application/json',
            ])
            ->post($endpoint, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [
                            ['text' => "Rol del usuario: {$nombreRol}. Consulta del usuario: {$pregunta}"],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'      => 0.1,
                    'maxOutputTokens'  => 600,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (!$response->successful()) {
            return null;
        }

        $jsonBody = $response->json();
        $rawText = $jsonBody['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (empty($rawText)) {
            return null;
        }

        // Limpiar posibles bloques markdown ```json ... ```
        $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $parsed = json_decode($cleanJson, true);

        if (!is_array($parsed) || empty($parsed['sql'])) {
            return null;
        }

        $sql = trim($parsed['sql']);
        // Eliminar punto y coma final si el modelo lo incluyó al final
        $sql = rtrim($sql, "; \t\n\r\0\x0B");

        // Auditar seguridad del SQL generado y permisos por rol
        if (!$this->esSqlSeguro($sql, $rolId)) {
            Log::warning('AiSqlAssistantService: SQL bloqueado por auditor de seguridad: ' . $sql);
            return null;
        }

        // Ejecutar consulta en PostgreSQL con blindaje total
        try {
            $filas = DB::select($sql);
            $datosArray = array_map(fn($row) => (array) $row, array_slice($filas, 0, 15));

            $resumenPrefijo = $parsed['explicacion'] ?? 'Resultado de la consulta en tiempo real:';
            $textoPantalla = $this->formatearRespuestaEjecutiva($resumenPrefijo, $datosArray);
            $textoVoz = $this->prepararTextoParaVoz($textoPantalla);

            return [
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => 'gemini',
                'texto_pantalla' => $textoPantalla,
                'texto_voz'      => $textoVoz,
                'datos_resumen'  => $datosArray,
            ];
        } catch (\Throwable $e) {
            // Jamás exponer SQLSTATE crudo al usuario; conmutar al motor local
            Log::warning('AiSqlAssistantService: Error al ejecutar SQL de Gemini, usando motor local. Detalle interno: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * System Prompt con el diccionario DDL exacto de Script.sql en PostgreSQL.
     */
    private function construirSystemPrompt(): string
    {
        return <<<PROMPT
Eres el Arquitecto de Base de Datos y Asistente Ejecutivo de "Multipartes El Tuerca" en Santa Cruz, Bolivia.
Tu tarea es traducir preguntas en lenguaje natural a una consulta SQL PostgreSQL de solo lectura (SELECT) sobre el esquema exacto Smart-DB.
La moneda oficial del sistema es Bolivianos (Bs.).

ESQUEMA EXACTO POSTGRESQL (Script.sql):
1. categorias (id_categoria, nombre, descripcion)
2. productos (id_producto, id_categoria, codigo_interno, codigo_oem, nombre_pieza, marca_fabricante, procedencia, precio_venta_sugerido, margen_ganancia, unidad_medida, stock_actual, stock_minimo, stock_seguridad, pasillo, estante, nivel_gaveta, estado)
3. codigos_alternativos (id_codigo_alt, id_producto, codigo_referencia, marca_alternativa)
4. vehiculos (id_vehiculo, marca, modelo, anio_inicio, anio_fin, motor_cilindrada, combustible)
5. compatibilidades (id_compatibilidad, id_producto, id_vehiculo, notas_posicion)
6. ajustes_inventario (id_ajuste, id_producto, id_usuario, cantidad_ajustada, tipo_ajuste, motivo, fecha_ajuste)
7. proveedores (id_proveedor, razon_social, nit, contacto_nombre, telefono, correo, direccion, condicion_comercial, estado)
8. notas_compras (id_compra, id_proveedor, id_usuario_receptor, numero_factura_nota, fecha_compra, monto_total, tipo_pago, estado_pago)
   -- tipo_pago: 'Contado', 'Crédito' | estado_pago: 'Pagado', 'Pendiente', 'Anulado'
9. detalles_compras (id_detalle_compra, id_compra, id_producto, cantidad_recibida, costo_unitario, subtotal)
10. sesiones_cajas (id_sesion, id_usuario_cajero, fecha_hora_apertura, monto_inicial, fecha_hora_cierre, monto_esperado_sistema, monto_real_contado, diferencia, estado)
    -- estado: 'Abierta', 'Cerrada', 'Cuadrada', 'Con Diferencia'
11. movimientos_cajas (id_movimiento, id_sesion, tipo, monto, motivo, hora)
    -- tipo: 'Ingreso Extra', 'Retiro Menor'
12. clientes (id_cliente, nombre_razon_social, ci_nit, telefono, tipo_cliente, descuento_frecuente)
    -- tipo_cliente: 'Particular', 'Taller/Mecánico', 'Empresa/Flota'
13. cotizaciones (id_cotizacion, id_cliente, id_usuario, fecha_emision, fecha_vencimiento, monto_total, estado)
    -- estado: 'Vigente', 'Concretada/Facturada', 'Vencida', 'Anulada'
14. detalles_cotizaciones (id_detalle_cot, id_cotizacion, id_producto, cantidad, precio_unitario_cotizado, subtotal)
15. notas_ventas (id_venta, id_cliente, id_usuario_vendedor, id_sesion_caja, fecha_hora, total_venta, descuento_total, metodo_pago, estado, motivo_anulacion)
    -- metodo_pago: 'Efectivo', 'Transferencia/QR', 'Tarjeta Débito/Crédito', 'Mixto' | estado: 'Completada', 'Anulada'
16. detalles_ventas (id_detalle_venta, id_venta, id_producto, cantidad_vendida, precio_unitario_cobrado, subtotal)
17. usuarios (id_usuario, id_rol, nombre_completo, username, correo, telefono, estado) -- PROHIBIDO consultar password_hash
18. roles (id_rol, nombre_rol, descripcion)
19. bitacoras_auditorias (id_bitacora, id_usuario, fecha_hora, modulo_afectado, accion, registro_id, detalle_cambio, ip_origen)

REGLAS OBLIGATORIAS:
- Genera ÚNICAMENTE una sentencia SELECT válida para PostgreSQL (sin punto y coma al final, límite máximo LIMIT 12).
- Usa siempre ILIKE '%termino%' para búsquedas de texto insensibles a mayúsculas.
- Jamás consultes la columna password_hash.
- Responde EXCLUSIVAMENTE un objeto JSON con las claves:
  {
    "sql": "SELECT ...",
    "explicacion": "Breve introducción ejecutiva en español de lo que se está consultando (en Bs. si aplica)"
  }
PROMPT;
    }

    /**
     * Auditor de Seguridad SQL Read-Only y Permisos por Rol.
     */
    public function esSqlSeguro(string $sql, int $rolId = 1): bool
    {
        $sqlTrim = trim($sql);

        // 1. Debe empezar exclusivamente con SELECT
        if (!preg_match('/^SELECT\b/i', $sqlTrim)) {
            return false;
        }

        // 2. Bloquear múltiples sentencias (;) o comentarios maliciosos
        if (str_contains($sqlTrim, ';') || str_contains($sqlTrim, '--') || str_contains($sqlTrim, '/*')) {
            return false;
        }

        // 3. Bloquear palabras clave mutacionales o de ejecución
        $prohibidas = '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|CALL|EXEC|EXECUTE|COPY|VACUUM|SET|REPLACE|PG_SLEEP)\b/i';
        if (preg_match($prohibidas, $sqlTrim)) {
            return false;
        }

        // 4. Prohibir exposición de password_hash
        if (preg_match('/\bpassword_hash\b/i', $sqlTrim)) {
            return false;
        }

        // 5. Segregación por Rol: Bodeguero (Rol 2) y Vendedor (Rol 3) no pueden consultar bitácora ni cierres de caja
        if (in_array($rolId, [2, 3], true)) {
            if (preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas)\b/i', $sqlTrim)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica preventivamente si la intención del usuario viola los permisos de su rol.
     */
    private function verificarRestriccionRol(string $pregunta, int $rolId, string $nombreRol): ?array
    {
        $norm = $this->normalizarTexto($pregunta);

        // Bodeguero (Rol 2) o Vendedor (Rol 3) intentando consultar arqueos/cierres de caja o bitácora de auditoría
        if (in_array($rolId, [2, 3], true)) {
            if (preg_match('/\b(bitacora|auditoria|arqueo|cierre de caja|sesion de caja|gaveta|caja chica)\b/', $norm)) {
                $msg = "Acceso restringido: Tu perfil de {$nombreRol} no tiene autorización para consultar información de arqueos de tesorería ni bitácoras de seguridad.";
                return [
                    'ok'             => true,
                    'tipo'           => 'restriccion',
                    'origen'         => 'local',
                    'texto_pantalla' => $msg,
                    'texto_voz'      => $this->prepararTextoParaVoz($msg),
                    'datos_resumen'  => [],
                ];
            }
        }

        // Vendedor (Rol 3) intentando consultar deudas de compras a proveedores
        if ($rolId === 3 && preg_match('/\b(cuentas por pagar|deuda.*proveedor|facturas de compra)\b/', $norm)) {
            $msg = "Acceso restringido: Las cuentas por pagar a proveedores corresponden al área de Administración, Tesorería y Almacén.";
            return [
                'ok'             => true,
                'tipo'           => 'restriccion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $this->prepararTextoParaVoz($msg),
                'datos_resumen'  => [],
            ];
        }

        return null;
    }

    /**
     * Vía B: Motor Semántico Local Resiliente en PHP con soporte para las 7 intenciones comerciales.
     */
    private function consultarViaMotorLocal(string $pregunta, int $rolId, string $nombreRol): array
    {
        $norm = $this->normalizarTexto($pregunta);

        try {
            // INTENCIÓN 1: Alertas de stock crítico, bajo mínimo o agotado
            if (preg_match('/\b(stock critico|stock bajo|bajo minimo|alerta.*stock|agotado|reabastecer|faltante.*stock|pocas unidades)\b/', $norm)) {
                return $this->intencionStockCritico();
            }

            // INTENCIÓN 2: Cuentas por pagar y deudas a proveedores (Crédito / Pendiente)
            if (preg_match('/\b(cuentas por pagar|deuda|proveedores|pendiente.*pago|credito.*proveedor|compras a credito|por pagar)\b/', $norm)) {
                return $this->intencionCuentasPorPagar();
            }

            // INTENCIÓN 3: Ventas del día, ingresos por método de pago (Efectivo, QR, Tarjeta) y estado de caja
            if (preg_match('/\b(venta|vendio|vendido|recaudado|ingresos de hoy|caja|efectivo|transferencia|qr|tarjeta|arqueo)\b/', $norm)) {
                return $this->intencionVentasYCaja($norm, $rolId);
            }

            // INTENCIÓN 4: Cotizaciones vigentes o vencidas
            if (preg_match('/\b(cotizacion|cotizaciones|proforma|proformas|vigente|vencida)\b/', $norm)) {
                return $this->intencionCotizaciones($norm);
            }

            // INTENCIÓN 5: Clientes particulares, talleres mecánicos aliados y descuentos frecuentes
            if (preg_match('/\b(cliente|clientes|taller|talleres|mecanico|flota|descuento frecuente)\b/', $norm)) {
                return $this->intencionClientes($norm);
            }

            // INTENCIÓN 6: Compatibilidad vehicular ("qué repuestos le hacen al Corolla / Vitara / Hilux...")
            if ($this->detectarIntencionCompatibilidad($norm)) {
                return $this->intencionCompatibilidadVehicular($norm);
            }

            // INTENCIÓN 7: Búsqueda de repuestos por nombre, código OEM/alternativo, stock, precio y ubicación en bodega
            return $this->intencionBuscarRepuesto($pregunta, $norm);
        } catch (\Throwable $e) {
            Log::error('AiSqlAssistantService (Motor Local): ' . $e->getMessage());
            $msg = 'En este momento no pude procesar los datos solicitados. Por favor intenta reformular tu consulta indicando el nombre del repuesto, código OEM o módulo comercial.';
            return [
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $this->prepararTextoParaVoz($msg),
                'datos_resumen'  => [],
            ];
        }
    }

    /**
     * Intención 1: Alertas de stock crítico (stock_actual <= stock_minimo).
     */
    private function intencionStockCritico(): array
    {
        $items = DB::table('productos as p')
            ->join('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->where('p.estado', true)
            ->whereColumn('p.stock_actual', '<=', 'p.stock_minimo')
            ->select([
                'p.codigo_oem as OEM',
                'p.nombre_pieza as Repuesto',
                'p.marca_fabricante as Marca',
                'p.stock_actual as Stock_Actual',
                'p.stock_minimo as Stock_Minimo',
                DB::raw("CONCAT(COALESCE(p.pasillo,'-'), '-', COALESCE(p.estante,'-'), '-', COALESCE(p.nivel_gaveta,'-')) as \"Ubicacion\""),
                'p.precio_venta_sugerido as Precio_Bs',
            ])
            ->orderBy('p.stock_actual')
            ->limit(12)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $totalCriticos = count($items);

        if ($totalCriticos === 0) {
            $texto = 'Excelente noticia: actualmente todos los repuestos activos superan su nivel de stock mínimo en almacén. No hay alertas críticas pendientes.';
        } else {
            $primeros = array_slice($items, 0, 3);
            $detalles = array_map(
                fn($it) => "{$it['Repuesto']} (OEM {$it['OEM']}, quedan {$it['Stock_Actual']} unidades en ubicación {$it['Ubicacion']})",
                $primeros
            );
            $texto = "Se detectaron {$totalCriticos} repuestos en estado de stock crítico o bajo mínimo. Destacan: " . implode('; ', $detalles) . '.';
        }

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $items,
        ];
    }

    /**
     * Intención 2: Cuentas por pagar y deudas a proveedores (notas_compras a Crédito con estado_pago Pendiente).
     */
    private function intencionCuentasPorPagar(): array
    {
        $deudas = DB::table('notas_compras as nc')
            ->join('proveedores as pr', 'pr.id_proveedor', '=', 'nc.id_proveedor')
            ->where('nc.tipo_pago', 'Crédito')
            ->where('nc.estado_pago', 'Pendiente')
            ->select([
                'nc.numero_factura_nota as Factura',
                'pr.razon_social as Proveedor',
                'pr.condicion_comercial as Condicion',
                'nc.fecha_compra as Fecha_Compra',
                'nc.monto_total as Monto_Deuda_Bs',
            ])
            ->orderBy('nc.fecha_compra')
            ->limit(12)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $sumaDeuda = array_sum(array_column($deudas, 'Monto_Deuda_Bs'));
        $cantidad = count($deudas);

        if ($cantidad === 0) {
            $texto = 'No existen cuentas por pagar ni facturas pendientes a proveedores. Todas las adquisiciones a crédito se encuentran liquidadas.';
        } else {
            $detalleProv = array_map(
                fn($d) => "{$d['Proveedor']} (Factura {$d['Factura']} por Bs. " . number_format((float)$d['Monto_Deuda_Bs'], 2) . ")",
                array_slice($deudas, 0, 3)
            );
            $texto = "Existen {$cantidad} facturas pendientes por pagar a proveedores por un total acumulado de Bs. " . number_format($sumaDeuda, 2) . ". Principales compromisos: " . implode('; ', $detalleProv) . '.';
        }

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $deudas,
        ];
    }

    /**
     * Intención 3: Ventas del día, resumen general de ventas, métodos de pago y estado de sesión de caja.
     */
    private function intencionVentasYCaja(string $norm, int $rolId): array
    {
        $hoy = date('Y-m-d');

        // Ventas de hoy
        $ventasHoyQuery = DB::table('notas_ventas')
            ->where('estado', 'Completada')
            ->whereDate('fecha_hora', $hoy);

        $totalHoy = (float) $ventasHoyQuery->sum('total_venta');
        $cantHoy  = (int) $ventasHoyQuery->count();

        // Desglose general de ventas completadas por método de pago (hoy o global si hoy es 0)
        $filtrarSoloHoy = $cantHoy > 0;
        $desglose = DB::table('notas_ventas')
            ->where('estado', 'Completada')
            ->when($filtrarSoloHoy, fn($q) => $q->whereDate('fecha_hora', $hoy))
            ->select([
                'metodo_pago as Metodo_Pago',
                DB::raw('COUNT(id_venta) as "Operaciones"'),
                DB::raw('ROUND(SUM(total_venta), 2) as "Total_Recaudado_Bs"'),
                DB::raw('ROUND(SUM(descuento_total), 2) as "Descuentos_Bs"'),
            ])
            ->groupBy('metodo_pago')
            ->orderByRaw('SUM(total_venta) DESC')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $totalGlobal = (float) DB::table('notas_ventas')->where('estado', 'Completada')->sum('total_venta');
        $cantGlobal  = (int) DB::table('notas_ventas')->where('estado', 'Completada')->count();

        // Estado de caja abierta (si el rol tiene permiso)
        $infoCaja = '';
        if (in_array($rolId, [1, 4], true)) {
            $cajaAbierta = DB::table('sesiones_cajas as sc')
                ->join('usuarios as u', 'u.id_usuario', '=', 'sc.id_usuario_cajero')
                ->where('sc.estado', 'Abierta')
                ->select('sc.id_sesion', 'u.nombre_completo', 'sc.monto_inicial', 'sc.monto_esperado_sistema')
                ->orderByDesc('sc.id_sesion')
                ->first();

            if ($cajaAbierta) {
                $esperado = number_format((float) ($cajaAbierta->monto_esperado_sistema ?? $cajaAbierta->monto_inicial), 2);
                $infoCaja = " La Sesión de Caja #{$cajaAbierta->id_sesion} a cargo de {$cajaAbierta->nombre_completo} está Abierta con saldo esperado en sistema de Bs. {$esperado}.";
            } else {
                $infoCaja = ' Actualmente no hay ninguna sesión de caja abierta.';
            }
        }

        if ($cantHoy > 0) {
            $texto = "Hoy se han completado {$cantHoy} ventas por un total neto de Bs. " . number_format($totalHoy, 2) . ". En el acumulado histórico se registran {$cantGlobal} ventas completadas por Bs. " . number_format($totalGlobal, 2) . "." . $infoCaja;
        } else {
            $texto = "Hoy aún no se registraron notas de venta. El acumulado histórico del sistema registra {$cantGlobal} ventas completadas por un total de Bs. " . number_format($totalGlobal, 2) . "." . $infoCaja;
        }

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $desglose,
        ];
    }

    /**
     * Intención 4: Cotizaciones vigentes, convertidas o vencidas.
     */
    private function intencionCotizaciones(string $norm): array
    {
        $query = DB::table('cotizaciones as cot')
            ->join('clientes as cli', 'cli.id_cliente', '=', 'cot.id_cliente')
            ->select([
                'cot.id_cotizacion as Folio',
                'cli.nombre_razon_social as Cliente',
                'cot.fecha_vencimiento as Vencimiento',
                'cot.monto_total as Total_Bs',
                'cot.estado as Estado',
            ]);

        if (str_contains($norm, 'vencida')) {
            $query->where(function ($q) {
                $q->where('cot.estado', 'Vencida')
                  ->orWhere(function ($sq) {
                      $sq->where('cot.estado', 'Vigente')->whereDate('cot.fecha_vencimiento', '<', date('Y-m-d'));
                  });
            });
        } elseif (str_contains($norm, 'vigente')) {
            $query->where('cot.estado', 'Vigente')->whereDate('cot.fecha_vencimiento', '>=', date('Y-m-d'));
        }

        $filas = $query->orderByDesc('cot.id_cotizacion')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $totalVigentes = DB::table('cotizaciones')
            ->where('estado', 'Vigente')
            ->whereDate('fecha_vencimiento', '>=', date('Y-m-d'))
            ->count();

        $montoVigentes = (float) DB::table('cotizaciones')
            ->where('estado', 'Vigente')
            ->whereDate('fecha_vencimiento', '>=', date('Y-m-d'))
            ->sum('monto_total');

        $texto = "Se encontraron " . count($filas) . " proformas en la consulta. Actualmente existen {$totalVigentes} cotizaciones vigentes listas para conversión a venta por un importe total de Bs. " . number_format($montoVigentes, 2) . ".";

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $filas,
        ];
    }

    /**
     * Intención 5: Clientes particulares, talleres aliados y flotas.
     */
    private function intencionClientes(string $norm): array
    {
        $query = DB::table('clientes')
            ->select([
                'nombre_razon_social as Cliente',
                'ci_nit as CI_NIT',
                'tipo_cliente as Tipo',
                'telefono as Telefono',
                'descuento_frecuente as Descuento_Max_Pct',
            ]);

        if (str_contains($norm, 'taller') || str_contains($norm, 'mecanico')) {
            $query->where('tipo_cliente', 'Taller/Mecánico');
        } elseif (str_contains($norm, 'empresa') || str_contains($norm, 'flota')) {
            $query->where('tipo_cliente', 'Empresa/Flota');
        }

        $clientes = $query->orderByDesc('descuento_frecuente')
            ->orderBy('nombre_razon_social')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $totalClientes = count($clientes);
        $texto = "Se listan {$totalClientes} clientes registrados en cartera comercial con sus respectivos límites de descuento autorizado.";

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $clientes,
        ];
    }

    /**
     * Detecta si la consulta pregunta por compatibilidad vehicular o modelos de vehículos.
     */
    private function detectarIntencionCompatibilidad(string $norm): bool
    {
        if (preg_match('/\b(compatible|compatibilidad|le hace|sirve para|vehiculo|modelo|toyota|nissan|suzuki|mitsubishi|hyundai|kia|mazda|honda|isuzu|corolla|hilux|vitara|sentra|frontier|tucson|sportage|l200|rav4|yaris|swift|rio|accent|crv|bt50|dmax)\b/', $norm)) {
            return true;
        }
        return false;
    }

    /**
     * Intención 6: Compatibilidad vehicular cruzando productos, compatibilidades y vehiculos.
     */
    private function intencionCompatibilidadVehicular(string $norm): array
    {
        $palabrasClave = $this->extraerTerminosSignificativos($norm);

        $query = DB::table('compatibilidades as comp')
            ->join('productos as p', 'p.id_producto', '=', 'comp.id_producto')
            ->join('vehiculos as v', 'v.id_vehiculo', '=', 'comp.id_vehiculo')
            ->where('p.estado', true)
            ->select([
                'p.codigo_oem as OEM',
                'p.nombre_pieza as Repuesto',
                'p.marca_fabricante as Marca_Pieza',
                DB::raw("CONCAT(v.marca, ' ', v.modelo, ' (', v.anio_inicio, '-', v.anio_fin, ')') as \"Vehiculo_Compatible\""),
                'p.stock_actual as Stock',
                DB::raw("CONCAT(COALESCE(p.pasillo,'-'), '-', COALESCE(p.estante,'-'), '-', COALESCE(p.nivel_gaveta,'-')) as \"Ubicacion\""),
                'p.precio_venta_sugerido as Precio_Bs',
            ]);

        if (!empty($palabrasClave)) {
            $query->where(function ($q) use ($palabrasClave) {
                foreach ($palabrasClave as $term) {
                    $q->orWhere('v.marca', 'ilike', "%{$term}%")
                      ->orWhere('v.modelo', 'ilike', "%{$term}%")
                      ->orWhere('p.nombre_pieza', 'ilike', "%{$term}%")
                      ->orWhere('p.codigo_oem', 'ilike', "%{$term}%");
                }
            });
        }

        // Si existen términos tanto de pieza como de vehículo, priorizar que coincida el vehículo
        $marcasModelos = ['toyota', 'nissan', 'suzuki', 'mitsubishi', 'hyundai', 'kia', 'mazda', 'honda', 'isuzu', 'corolla', 'hilux', 'vitara', 'sentra', 'frontier', 'tucson', 'sportage', 'l200', 'rav4', 'yaris', 'swift', 'rio', 'accent', 'crv', 'bt50', 'dmax'];
        $vehiculosDetectados = array_values(array_intersect($palabrasClave, $marcasModelos));
        $piezasDetectadas = array_values(array_diff($palabrasClave, $marcasModelos));

        if (!empty($vehiculosDetectados)) {
            $query = DB::table('compatibilidades as comp')
                ->join('productos as p', 'p.id_producto', '=', 'comp.id_producto')
                ->join('vehiculos as v', 'v.id_vehiculo', '=', 'comp.id_vehiculo')
                ->where('p.estado', true)
                ->where(function ($q) use ($vehiculosDetectados) {
                    foreach ($vehiculosDetectados as $veh) {
                        $q->orWhere('v.marca', 'ilike', "%{$veh}%")
                          ->orWhere('v.modelo', 'ilike', "%{$veh}%");
                    }
                });

            if (!empty($piezasDetectadas)) {
                $query->where(function ($q) use ($piezasDetectadas) {
                    foreach ($piezasDetectadas as $pz) {
                        $pzRaiz = $this->singularizar($pz);
                        $q->orWhere('p.nombre_pieza', 'ilike', "%{$pzRaiz}%")
                          ->orWhere('p.codigo_oem', 'ilike', "%{$pzRaiz}%");
                    }
                });
            }

            $query->select([
                'p.codigo_oem as OEM',
                'p.nombre_pieza as Repuesto',
                'p.marca_fabricante as Marca_Pieza',
                DB::raw("CONCAT(v.marca, ' ', v.modelo, ' (', v.anio_inicio, '-', v.anio_fin, ')') as \"Vehiculo_Compatible\""),
                'p.stock_actual as Stock',
                DB::raw("CONCAT(COALESCE(p.pasillo,'-'), '-', COALESCE(p.estante,'-'), '-', COALESCE(p.nivel_gaveta,'-')) as \"Ubicacion\""),
                'p.precio_venta_sugerido as Precio_Bs',
            ]);
        }

        $resultados = $query->orderByDesc('p.stock_actual')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        if (empty($resultados)) {
            $texto = 'No se encontraron repuestos compatibles con esa combinación específica en el catálogo vehicular activo.';
        } else {
            $cant = count($resultados);
            $primero = $resultados[0];
            $texto = "Se encontraron {$cant} repuestos compatibles verificados. Por ejemplo: {$primero['Repuesto']} ({$primero['Marca_Pieza']}, OEM {$primero['OEM']}) compatible con {$primero['Vehiculo_Compatible']}, con {$primero['Stock']} unidades disponibles en ubicación {$primero['Ubicacion']} a Bs. " . number_format((float)$primero['Precio_Bs'], 2) . ".";
        }

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $resultados,
        ];
    }

    /**
     * Intención 7: Stock, precios, códigos OEM/alternativo y ubicación física (pasillo, estante, nivel_gaveta).
     */
    private function intencionBuscarRepuesto(string $preguntaOriginal, string $norm): array
    {
        $terminos = $this->extraerTerminosSignificativos($norm);

        $query = DB::table('productos as p')
            ->join('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->where('p.estado', true)
            ->select([
                'p.codigo_interno as SKU',
                'p.codigo_oem as OEM',
                'p.nombre_pieza as Repuesto',
                'p.marca_fabricante as Marca',
                'c.nombre as Categoria',
                'p.stock_actual as Stock',
                DB::raw("CONCAT('Pasillo ', COALESCE(p.pasillo,'-'), ', Estante ', COALESCE(p.estante,'-'), ', Gaveta ', COALESCE(p.nivel_gaveta,'-')) as \"Ubicacion\""),
                'p.precio_venta_sugerido as Precio_Bs',
            ]);

        if (!empty($terminos)) {
            $query->where(function ($q) use ($terminos) {
                foreach ($terminos as $t) {
                    $raiz = $this->singularizar($t);
                    $q->orWhere('p.nombre_pieza', 'ilike', "%{$raiz}%")
                      ->orWhere('p.codigo_oem', 'ilike', "%{$t}%")
                      ->orWhere('p.codigo_interno', 'ilike', "%{$t}%")
                      ->orWhere('p.marca_fabricante', 'ilike', "%{$t}%")
                      ->orWhere('c.nombre', 'ilike', "%{$raiz}%")
                      ->orWhereExists(function ($sq) use ($t) {
                          $sq->select(DB::raw(1))
                             ->from('codigos_alternativos as ca')
                             ->whereColumn('ca.id_producto', 'p.id_producto')
                             ->where('ca.codigo_referencia', 'ilike', "%{$t}%");
                      });
                }
            });
        }

        $productos = $query->orderByDesc('p.stock_actual')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        if (empty($productos)) {
            // Mostrar resumen general de inventario si no hubo coincidencia puntual
            $totalPzs = DB::table('productos')->where('estado', true)->count();
            $totalUnidades = (int) DB::table('productos')->where('estado', true)->sum('stock_actual');
            $texto = "No encontré un repuesto específico con ese término, pero el almacén cuenta actualmente con {$totalPzs} ítems activos y {$totalUnidades} unidades en inventario. Puedes consultar por nombre de pieza, código OEM, marca de vehículo, ventas del día o cuentas por pagar.";
        } else {
            $cant = count($productos);
            $top = $productos[0];
            $texto = "Se encontraron {$cant} coincidencias en inventario. Destaca: {$top['Repuesto']} ({$top['Marca']}, OEM {$top['OEM']}), con {$top['Stock']} unidades en {$top['Ubicacion']} y precio de venta de Bs. " . number_format((float)$top['Precio_Bs'], 2) . ".";
        }

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $productos,
        ];
    }

    /**
     * Formatea la salida ejecutiva para respuestas provenientes de Gemini.
     */
    private function formatearRespuestaEjecutiva(string $explicacion, array $datos): string
    {
        $total = count($datos);
        if ($total === 0) {
            return $explicacion . ' No se encontraron registros que coincidan con los criterios consultados.';
        }

        $primeraFila = $datos[0];
        $pares = [];
        foreach ($primeraFila as $col => $val) {
            if ($val !== null && $val !== '') {
                $etiqueta = ucwords(str_replace('_', ' ', (string) $col));
                if (is_numeric($val) && preg_match('/(precio|monto|total|costo|deuda|saldo|venta|bs)/i', (string) $col)) {
                    $pares[] = "{$etiqueta}: Bs. " . number_format((float) $val, 2);
                } else {
                    $pares[] = "{$etiqueta}: {$val}";
                }
            }
        }

        $resumenUno = implode(', ', array_slice($pares, 0, 5));
        return trim($explicacion) . " Se obtuvieron {$total} registro(s). Principal resultado: {$resumenUno}.";
    }

    /**
     * Limpia símbolos Markdown y convierte abreviaturas ("Bs." -> "bolivianos") para SpeechSynthesisUtterance.
     */
    public function prepararTextoParaVoz(string $texto): string
    {
        $limpio = preg_replace('/[\*\#\_`~>]/', '', $texto);
        $limpio = preg_replace('/\bBs\.?\s*/i', 'bolivianos ', $limpio);
        $limpio = preg_replace('/\b(?:c[óo]digo\s+)?OEM\b/iu', 'código O E M', $limpio);
        $limpio = preg_replace('/\bSKU\b/i', 'código interno', $limpio);
        $limpio = preg_replace('/\s+/', ' ', $limpio);
        return trim($limpio);
    }

    /**
     * Normaliza texto eliminando tildes y convirtiendo a minúsculas.
     */
    private function normalizarTexto(string $texto): string
    {
        $minusculas = mb_strtolower($texto, 'UTF-8');
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        ];
        return strtr($minusculas, $reemplazos);
    }

    /**
     * Extrae palabras significativas descartando stopwords comunes en español.
     */
    private function extraerTerminosSignificativos(string $norm): array
    {
        $limpio = preg_replace('/[^a-z0-9\-ñ\s]/u', ' ', $norm);
        $partes = preg_split('/\s+/', trim($limpio)) ?: [];

        $stopwords = [
            'que', 'cual', 'cuales', 'como', 'donde', 'cuanto', 'cuantos', 'cuantas',
            'hay', 'tiene', 'tienen', 'tenemos', 'mostrar', 'buscar', 'busca', 'dime',
            'consultar', 'consulta', 'ver', 'lista', 'listar', 'del', 'de', 'la', 'el',
            'los', 'las', 'un', 'una', 'unos', 'unas', 'para', 'por', 'con', 'en', 'al',
            'le', 'hacen', 'hace', 'sirve', 'sirven', 'repuesto', 'repuestos', 'pieza',
            'piezas', 'stock', 'ubicacion', 'precio', 'cuesta', 'vale', 'bodega',
            'almacen', 'pasillo', 'estante', 'gaveta', 'disponible', 'disponibles',
            'vehiculo', 'auto', 'camioneta', 'carro', 'compatible', 'compatibles',
            'favor', 'quiero', 'saber', 'necesito', 'está', 'esta', 'estan',
        ];

        $terminos = [];
        foreach ($partes as $p) {
            if (mb_strlen($p) >= 3 && !in_array($p, $stopwords, true)) {
                $terminos[] = $p;
            }
        }

        return array_values(array_unique($terminos));
    }

    /**
     * Convierte plurales simples en español a su raíz singular para búsquedas ILIKE más amplias.
     */
    private function singularizar(string $palabra): string
    {
        if (str_ends_with($palabra, 'dores')) {
            return substr($palabra, 0, -2); // amortiguadores -> amortiguador
        }
        if (str_ends_with($palabra, 'ones')) {
            return substr($palabra, 0, -2);
        }
        if (str_ends_with($palabra, 'as') || str_ends_with($palabra, 'os')) {
            return substr($palabra, 0, -1); // pastillas -> pastilla, filtros -> filtro
        }
        return $palabra;
    }

    private function obtenerNombreRol(int $rolId): string
    {
        return match ($rolId) {
            1 => 'Administrador',
            2 => 'Bodeguero',
            3 => 'Vendedor',
            4 => 'Cajero',
            5 => 'Cliente',
            default => 'Usuario',
        };
    }
}
