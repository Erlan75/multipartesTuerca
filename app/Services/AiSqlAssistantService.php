<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiSqlAssistantService
{
    /**
     * Matriz de permisos para el Modo Transaccional Asistido (CU39).
     * Rol 1: Administrador | Rol 2: Bodeguero | Rol 3: Vendedor | Rol 4: Cajero
     */
    public const PERMISOS_TRANSACCIONALES = [
        1 => ['preparar_venta', 'preparar_cotizacion', 'preparar_compra', 'preparar_ajuste_inventario'],
        2 => ['preparar_ajuste_inventario'],
        3 => ['preparar_venta', 'preparar_cotizacion'],
        4 => ['preparar_venta', 'preparar_cotizacion'],
    ];

    /**
     * Punto de entrada principal para procesar consultas analíticas o intenciones transaccionales
     * por voz o texto (Voice-to-SQL + Function Calling con Confirmación Humana).
     */
    public function procesarConsulta(string $pregunta, ?object $usuario = null): array
    {
        $preguntaLimpia = trim($pregunta);
        $rolId = (int) ($usuario->id_rol ?? 1);
        $nombreRol = $usuario->rol->nombre_rol ?? $this->obtenerNombreRol($rolId);
        $userId = (int) ($usuario->id_usuario ?? 1);

        // 1. Verificar si es una intención transaccional (escritura asistida) antes de aplicar filtros de solo lectura
        $esIntencionEscritura = $this->detectarIntencionEscritura($this->normalizarTexto($preguntaLimpia));

        // 2. Verificar restricciones de rol para consultas analíticas (si no es escritura)
        if (!$esIntencionEscritura) {
            $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
            if ($bloqueoRol !== null) {
                return $bloqueoRol;
            }
        }

        // 3. Vía A: Motor Google Gemini 2.0 Flash (Text-to-SQL + Function Calling) si existe API Key
        $apiKey = config('services.gemini.key');
        if (!empty($apiKey)) {
            try {
                $resultadoGemini = $this->consultarViaGemini($preguntaLimpia, $apiKey, $rolId, $nombreRol, $userId);
                if ($resultadoGemini !== null) {
                    return $resultadoGemini;
                }
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService: Conmutando a motor local tras excepción en Gemini: ' . $e->getMessage());
            }
        }

        // 4. Vía B: Si es intención transaccional, resolver con el parser transaccional local resiliente
        if ($esIntencionEscritura) {
            $resultadoLocalTx = $this->resolverTransaccionLocal($preguntaLimpia, $rolId, $nombreRol, $userId);
            if ($resultadoLocalTx !== null) {
                return $resultadoLocalTx;
            }
        }

        // 5. Verificar bloqueo de rol antes de motor analítico local
        $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
        if ($bloqueoRol !== null) {
            return $bloqueoRol;
        }

        // 6. Vía B (Analítica): Motor Semántico Local Resiliente en PHP
        return $this->consultarViaMotorLocal($preguntaLimpia, $rolId, $nombreRol);
    }

    /**
     * Paso 2 del Modo Transaccional Asistido: Ejecuta atómicamente el SP real de Smart-DB
     * tras confirmación explícita del usuario mediante action_token.
     */
    public function ejecutarAccionConfirmada(array $payload, int $userId, int $rolId, string $nombreRol, string $ipOrigen): array
    {
        $operacion = $payload['operacion'] ?? '';

        if (!$this->rolPuedeEjecutarOperacion($rolId, $operacion)) {
            $msg = "Acceso denegado: Tu rol de {$nombreRol} no tiene autorización para confirmar la operación solicitada.";
            return [
                'ok'             => false,
                'tipo'           => 'restriccion',
                'origen'         => 'smartdb',
                'texto_pantalla' => $msg,
                'texto_voz'      => $this->prepararTextoParaVoz($msg),
                'datos_resumen'  => [],
            ];
        }

        try {
            DB::statement("SELECT set_config('app.id_usuario', ?, false)", [(string) $userId]);
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ipOrigen ?: '127.0.0.1']);

            return match ($operacion) {
                'preparar_venta'             => $this->ejecutarVentaConfirmada($payload, $userId),
                'preparar_cotizacion'        => $this->ejecutarCotizacionConfirmada($payload, $userId),
                'preparar_compra'            => $this->ejecutarCompraConfirmada($payload, $userId),
                'preparar_ajuste_inventario' => $this->ejecutarAjusteConfirmado($payload, $userId),
                default                      => [
                    'ok'             => false,
                    'tipo'           => 'error',
                    'origen'         => 'smartdb',
                    'texto_pantalla' => 'La operación solicitada no es reconocida por el motor transaccional.',
                    'texto_voz'      => 'La operación solicitada no es reconocida por el motor transaccional.',
                    'datos_resumen'  => [],
                ],
            };
        } catch (\Throwable $e) {
            Log::warning('AiSqlAssistantService (Confirmación Smart-DB): ' . $e->getMessage());
            $mensajeExplicado = $this->traducirErrorSmartDb($e->getMessage());
            return [
                'ok'             => false,
                'tipo'           => 'error',
                'origen'         => 'smartdb',
                'texto_pantalla' => $mensajeExplicado,
                'texto_voz'      => $this->prepararTextoParaVoz($mensajeExplicado),
                'datos_resumen'  => [],
            ];
        }
    }

    private function ejecutarVentaConfirmada(array $payload, int $userId): array
    {
        $idSesion = (int) ($payload['id_sesion_caja'] ?? 0);
        if ($idSesion <= 0) {
            $sesion = DB::table('sesiones_cajas')->where('estado', 'Abierta')->orderByDesc('id_sesion')->first();
            if (!$sesion) {
                throw new \RuntimeException('No existe ninguna sesión de caja abierta. Debe aperturar un turno de caja en Tesorería antes de registrar ventas.');
            }
            $idSesion = (int) $sesion->id_sesion;
        }

        $itemsSp = array_map(fn($it) => [
            'id_producto' => (int) $it['id_producto'],
            'cantidad'    => (int) $it['cantidad'],
            'precio'      => (float) $it['precio_unitario'],
        ], $payload['items']);

        $totalNeto = (float) ($payload['total_neto'] ?? 0);
        $metodoPago = (string) ($payload['metodo_pago'] ?? 'Efectivo');
        $descuento = (float) ($payload['descuento_total'] ?? 0);

        $detallesPago = [
            'origen'          => 'ASISTENTE_IA_CONFIRMADO',
            'metodo_pago'     => $metodoPago,
            'monto_recibido'  => $totalNeto,
            'cambio_devuelto' => 0.00,
            'monto_efectivo'  => $metodoPago === 'Efectivo' ? $totalNeto : 0.00,
            'monto_digital'   => $metodoPago !== 'Efectivo' ? $totalNeto : 0.00,
        ];

        $res = DB::select('CALL sp_registrar_venta(?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, NULL)', [
            (int) $payload['id_cliente'],
            $userId,
            $idSesion,
            $metodoPago,
            $descuento,
            json_encode($itemsSp),
            json_encode($detallesPago),
        ]);

        $idVenta = (int) ($res[0]->p_id_venta ?? DB::table('notas_ventas')->max('id_venta'));
        $folio = 'V-' . str_pad((string) $idVenta, 5, '0', STR_PAD_LEFT);
        $texto = "Venta #{$folio} registrada exitosamente en Smart-DB para {$payload['cliente_nombre']} por un total neto de Bs. " . number_format($totalNeto, 2) . " mediante {$metodoPago}. El stock fue descontado y la sesión de caja #{$idSesion} fue actualizada.";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('ventas.ticket', ['id' => $idVenta, 'formato' => 'termico']),
            'etiqueta_enlace'    => "Abrir Ticket Térmico 80mm (#{$folio})",
            'datos_resumen'      => [],
        ];
    }

    private function ejecutarCotizacionConfirmada(array $payload, int $userId): array
    {
        $itemsSp = array_map(fn($it) => [
            'id_producto' => (int) $it['id_producto'],
            'cantidad'    => (int) $it['cantidad'],
            'precio'      => (float) $it['precio_unitario'],
        ], $payload['items']);

        $dias = (int) ($payload['dias_vigencia'] ?? 7);
        $res = DB::select('CALL sp_registrar_cotizacion(?, ?, ?, ?::jsonb, NULL)', [
            (int) $payload['id_cliente'],
            $userId,
            $dias,
            json_encode($itemsSp),
        ]);

        $idCot = (int) ($res[0]->p_id_cotizacion ?? DB::table('cotizaciones')->max('id_cotizacion'));
        $folio = 'COT-' . str_pad((string) $idCot, 5, '0', STR_PAD_LEFT);
        $total = (float) ($payload['total_neto'] ?? 0);
        $texto = "Cotización formal #{$folio} emitida correctamente para {$payload['cliente_nombre']} por Bs. " . number_format($total, 2) . " con vigencia de {$dias} días.";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('cotizaciones.show', $idCot),
            'etiqueta_enlace'    => "Ver e Imprimir Proforma (#{$folio})",
            'datos_resumen'      => [],
        ];
    }

    private function ejecutarCompraConfirmada(array $payload, int $userId): array
    {
        $itemsSp = array_map(fn($it) => [
            'id_producto' => (int) $it['id_producto'],
            'cantidad'    => (int) $it['cantidad'],
            'costo'       => (float) $it['precio_unitario'],
        ], $payload['items']);

        $fechaCompra = $payload['fecha_compra'] ?? Carbon::today()->toDateString();
        $res = DB::select('CALL sp_registrar_compra(?, ?, ?, ?, ?, ?::jsonb, NULL)', [
            (int) $payload['id_proveedor'],
            $userId,
            (string) $payload['numero_factura_nota'],
            $fechaCompra,
            (string) $payload['tipo_pago'],
            json_encode($itemsSp),
        ]);

        $idCompra = (int) ($res[0]->p_id_compra ?? DB::table('notas_compras')->max('id_compra'));
        $total = (float) ($payload['total_neto'] ?? 0);
        $texto = "Nota de Compra #{$idCompra} (Factura {$payload['numero_factura_nota']}) registrada exitosamente para {$payload['proveedor_nombre']} por Bs. " . number_format($total, 2) . " ({$payload['tipo_pago']}). El inventario fue incrementado automáticamente.";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('compras.show', $idCompra),
            'etiqueta_enlace'    => "Ver Nota de Compra (#{$idCompra})",
            'datos_resumen'      => [],
        ];
    }

    private function ejecutarAjusteConfirmado(array $payload, int $userId): array
    {
        $res = DB::select('CALL sp_registrar_ajuste_inventario(?, ?, ?, ?, ?, NULL, NULL)', [
            (int) $payload['id_producto'],
            $userId,
            (string) $payload['tipo_ajuste'],
            (int) $payload['cantidad_ajustada'],
            (string) $payload['motivo'],
        ]);

        $idAjuste = (int) ($res[0]->p_id_ajuste ?? DB::table('ajustes_inventario')->max('id_ajuste'));
        $stockRes = $res[0]->p_stock_resultante ?? null;
        $stockMsg = $stockRes !== null ? " Stock resultante en almacén: {$stockRes} unidades." : '';
        $texto = "Ajuste de inventario #{$idAjuste} ({$payload['tipo_ajuste']}) aplicado exitosamente sobre {$payload['nombre_pieza']}.{$stockMsg}";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('inventario.ajustes'),
            'etiqueta_enlace'    => 'Ver Historial de Ajustes de Inventario',
            'datos_resumen'      => [],
        ];
    }

    /**
     * Vía A: Invoca Google Gemini 2.0 Flash con soporte híbrido de:
     *   - Function Calling (preparar_venta, preparar_cotizacion, preparar_compra, preparar_ajuste_inventario)
     *   - Text-to-SQL Read-Only (SELECT / WITH sobre las 19 tablas + fn_reporte_*)
     */
    private function consultarViaGemini(string $pregunta, string $apiKey, int $rolId, string $nombreRol, int $userId): ?array
    {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

        $response = Http::timeout(4.5)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type'   => 'application/json',
            ])
            ->post($endpoint, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $this->construirSystemPrompt()],
                    ],
                ],
                'tools' => [
                    [
                        'functionDeclarations' => $this->construirFunctionDeclarations(),
                    ],
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [
                            ['text' => "Rol del usuario autenticado: {$nombreRol}. Fecha actual: " . Carbon::today()->toDateString() . ". Solicitud del usuario: {$pregunta}"],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.1,
                    'maxOutputTokens' => 700,
                ],
            ]);

        if (!$response->successful()) {
            return null;
        }

        $parts = $response->json()['candidates'][0]['content']['parts'] ?? [];
        if (empty($parts)) {
            return null;
        }

        // 1. Verificar si Gemini emitió un Function Call (Modo Transaccional Asistido)
        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                $fnName = (string) $part['functionCall']['name'];
                $fnArgs = is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [];
                return $this->construirTarjetaConfirmacion($fnName, $fnArgs, $rolId, $nombreRol, $userId, 'gemini');
            }
        }

        // 2. Modo Analítico: Extraer JSON con SQL SELECT / WITH
        $rawText = '';
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $rawText .= $part['text'];
            }
        }

        if (empty(trim($rawText))) {
            return null;
        }

        $parsed = $this->extraerJsonDeRespuesta($rawText);
        if (!is_array($parsed) || empty($parsed['sql'])) {
            return null;
        }

        $sql = rtrim(trim($parsed['sql']), "; \t\n\r\0\x0B");

        if (!$this->esSqlSeguro($sql, $rolId)) {
            Log::warning('AiSqlAssistantService: SQL bloqueado por auditor de seguridad: ' . $sql);
            return null;
        }

        try {
            $filas = DB::select($sql);
            $datosArray = array_map(fn($row) => (array) $row, array_slice($filas, 0, 15));

            $resumenPrefijo = $parsed['explicacion'] ?? 'Resultado de la consulta en tiempo real:';
            $textoPantalla = $this->formatearRespuestaEjecutiva($resumenPrefijo, $datosArray);

            return [
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => 'gemini',
                'texto_pantalla' => $textoPantalla,
                'texto_voz'      => $this->prepararTextoParaVoz($textoPantalla),
                'datos_resumen'  => $datosArray,
            ];
        } catch (\Throwable $e) {
            Log::warning('AiSqlAssistantService: Error al ejecutar SQL de Gemini, conmutando a motor local: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Declaraciones de herramientas (Function Calling) para operaciones transaccionales asistidas.
     */
    private function construirFunctionDeclarations(): array
    {
        return [
            [
                'name'        => 'preparar_venta',
                'description' => 'Prepara el borrador de una Nota de Venta en el POS para confirmación humana antes de ejecutar sp_registrar_venta.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente' => [
                            'type'        => 'STRING',
                            'description' => 'Nombre, razón social o CI/NIT del cliente. Si no se indica, usar "Cliente General".',
                        ],
                        'items' => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto' => ['type' => 'STRING', 'description' => 'Nombre, código interno o código OEM del repuesto'],
                                    'cantidad' => ['type' => 'INTEGER', 'description' => 'Cantidad entera positiva'],
                                ],
                                'required' => ['producto', 'cantidad'],
                            ],
                        ],
                        'descuento_total' => [
                            'type'        => 'NUMBER',
                            'description' => 'Monto de descuento comercial en Bolivianos (Bs.), 0 si no aplica.',
                        ],
                        'metodo_pago' => [
                            'type'        => 'STRING',
                            'description' => 'Modalidad de cobro exacta: Efectivo, Transferencia/QR, Tarjeta Débito/Crédito o Mixto.',
                        ],
                    ],
                    'required' => ['items'],
                ],
            ],
            [
                'name'        => 'preparar_cotizacion',
                'description' => 'Prepara una proforma o cotización comercial para confirmación humana antes de ejecutar sp_registrar_cotizacion.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente'       => ['type' => 'STRING', 'description' => 'Nombre o CI/NIT del cliente o taller'],
                        'dias_vigencia' => ['type' => 'INTEGER', 'description' => 'Días de validez de la cotización (por defecto 7)'],
                        'items'         => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto' => ['type' => 'STRING'],
                                    'cantidad' => ['type' => 'INTEGER'],
                                ],
                                'required' => ['producto', 'cantidad'],
                            ],
                        ],
                    ],
                    'required' => ['items'],
                ],
            ],
            [
                'name'        => 'preparar_compra',
                'description' => 'Prepara una recepción de compra a proveedor para confirmación humana antes de ejecutar sp_registrar_compra.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'proveedor'           => ['type' => 'STRING', 'description' => 'Nombre o razón social del proveedor'],
                        'numero_factura_nota' => ['type' => 'STRING', 'description' => 'Número de factura o nota de entrega'],
                        'tipo_pago'           => ['type' => 'STRING', 'description' => 'Contado o Crédito'],
                        'items'               => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto'       => ['type' => 'STRING'],
                                    'cantidad'       => ['type' => 'INTEGER'],
                                    'costo_unitario' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['producto', 'cantidad', 'costo_unitario'],
                            ],
                        ],
                    ],
                    'required' => ['proveedor', 'items'],
                ],
            ],
            [
                'name'        => 'preparar_ajuste_inventario',
                'description' => 'Prepara un ajuste manual de inventario o salida por uso interno para confirmación humana antes de ejecutar sp_registrar_ajuste_inventario.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'producto'          => ['type' => 'STRING', 'description' => 'Nombre o código OEM del repuesto'],
                        'cantidad_ajustada' => ['type' => 'INTEGER', 'description' => 'Cantidad a descontar (entero positivo) o stock físico contado si es Conteo Físico'],
                        'tipo_ajuste'       => ['type' => 'STRING', 'description' => 'Exactamente uno de: Merma, Pérdida/Extravío, Conteo Físico, Devolución Dañada, Uso Interno'],
                        'motivo'            => ['type' => 'STRING', 'description' => 'Justificación descriptiva de al menos 10 caracteres'],
                    ],
                    'required' => ['producto', 'cantidad_ajustada', 'tipo_ajuste', 'motivo'],
                ],
            ],
        ];
    }

    /**
     * System Prompt completo con el diccionario DDL de las 19 tablas, CHECK exactos y funciones fn_reporte_*.
     */
    private function construirSystemPrompt(): string
    {
        return <<<PROMPT
Eres el Arquitecto de Base de Datos y Asistente Operativo de "Multipartes El Tuerca" (Santa Cruz, Bolivia).
La moneda oficial del sistema es Bolivianos (Bs.).

TIENES DOS MODOS OPERATIVOS:
1) MODO TRANSACCIONAL ASISTIDO: Si el usuario solicita registrar/crear/procesar una venta, cotización, compra a proveedor o ajuste de inventario (merma, pérdida, uso interno, conteo físico), INVOCA la función declarada correspondiente (preparar_venta, preparar_cotizacion, preparar_compra o preparar_ajuste_inventario).
2) MODO ANALÍTICO READ-ONLY: Si el usuario realiza una pregunta, consulta o reporte, responde EXCLUSIVAMENTE con un objeto JSON válido:
{"sql": "SELECT ...", "explicacion": "Resumen ejecutivo breve en español (en Bs. si aplica)"}

ESQUEMA EXACTO POSTGRESQL (19 TABLAS — Script.sql):
1. roles (id_rol, nombre_rol, descripcion)
2. usuarios (id_usuario, id_rol, nombre_completo, username, correo, telefono, estado, created_at) -- PROHIBIDO consultar password_hash
3. categorias (id_categoria, nombre, descripcion)
4. productos (id_producto, id_categoria, codigo_interno, codigo_oem, nombre_pieza, marca_fabricante, procedencia, precio_venta_sugerido, margen_ganancia, unidad_medida, stock_actual, stock_minimo, stock_seguridad, pasillo, estante, nivel_gaveta, estado)
5. codigos_alternativos (id_codigo_alt, id_producto, codigo_referencia, marca_alternativa)
6. vehiculos (id_vehiculo, marca, modelo, anio_inicio, anio_fin, motor_cilindrada, combustible)
   -- CHECK combustible: 'Gasolina', 'Diésel', 'GNV', 'Híbrido', 'Eléctrico'
7. compatibilidades (id_compatibilidad, id_producto, id_vehiculo, notas_posicion)
8. ajustes_inventario (id_ajuste, id_producto, id_usuario, cantidad_ajustada, tipo_ajuste, motivo, fecha_ajuste)
   -- CHECK tipo_ajuste: 'Merma', 'Pérdida/Extravío', 'Conteo Físico', 'Devolución Dañada', 'Uso Interno'
9. proveedores (id_proveedor, razon_social, nit, contacto_nombre, telefono, correo, direccion, condicion_comercial, estado)
   -- CHECK condicion_comercial: 'Contado', 'Crédito 15 días', 'Crédito 30 días', 'Crédito 60 días'
10. notas_compras (id_compra, id_proveedor, id_usuario_receptor, numero_factura_nota, fecha_compra, monto_total, tipo_pago, estado_pago)
    -- CHECK tipo_pago: 'Contado', 'Crédito' | CHECK estado_pago: 'Pagado', 'Pendiente', 'Anulado'
11. detalles_compras (id_detalle_compra, id_compra, id_producto, cantidad_recibida, costo_unitario, subtotal)
12. sesiones_cajas (id_sesion, id_usuario_cajero, fecha_hora_apertura, monto_inicial, fecha_hora_cierre, monto_esperado_sistema, monto_real_contado, diferencia, estado)
    -- CHECK estado: 'Abierta', 'Cerrada', 'Cuadrada', 'Con Diferencia'
13. movimientos_cajas (id_movimiento, id_sesion, tipo, monto, motivo, hora)
    -- CHECK tipo: 'Ingreso Extra', 'Retiro Menor'
14. clientes (id_cliente, nombre_razon_social, ci_nit, telefono, tipo_cliente, descuento_frecuente)
    -- CHECK tipo_cliente: 'Particular', 'Taller/Mecánico', 'Empresa/Flota'
15. cotizaciones (id_cotizacion, id_cliente, id_usuario, fecha_emision, fecha_vencimiento, monto_total, estado)
    -- CHECK estado: 'Vigente', 'Concretada/Facturada', 'Vencida', 'Anulada'
16. detalles_cotizaciones (id_detalle_cot, id_cotizacion, id_producto, cantidad, precio_unitario_cotizado, subtotal)
17. notas_ventas (id_venta, id_cliente, id_usuario_vendedor, id_sesion_caja, fecha_hora, total_venta, descuento_total, metodo_pago, estado, motivo_anulacion)
    -- CHECK metodo_pago: 'Efectivo', 'Transferencia/QR', 'Tarjeta Débito/Crédito', 'Mixto' | CHECK estado: 'Completada', 'Anulada'
18. detalles_ventas (id_detalle_venta, id_venta, id_producto, cantidad_vendida, precio_unitario_cobrado, subtotal)
19. bitacoras_auditorias (id_bitacora, id_usuario, fecha_hora, modulo_afectado, accion, registro_id, detalle_cambio, ip_origen)

FUNCIONES ANALÍTICAS SMART-DB DISPONIBLES (Úsalas en SELECT * FROM ...):
- fn_reporte_rotacion_repuestos(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_categoria INT DEFAULT NULL)
- fn_reporte_rentabilidad(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_categoria INT DEFAULT NULL)
- fn_reporte_compras_distribuidor(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_proveedor INT DEFAULT NULL)
- fn_reporte_balance_medios_pago(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_sesion INT DEFAULT NULL, p_id_cajero INT DEFAULT NULL)
- fn_obtener_metricas_pos(p_fecha DATE DEFAULT CURRENT_DATE)

REGLAS SQL:
- Solo sentencias SELECT o WITH de solo lectura, sin punto y coma final, máximo LIMIT 12.
- Usa ILIKE '%termino%' para búsquedas de texto.
- Jamás consultes password_hash.
PROMPT;
    }

    /**
     * Paso 1: Construye la Tarjeta de Confirmación Humana resolviendo IDs reales en PostgreSQL
     * y almacenando un action_token seguro de un solo uso en la sesión del usuario.
     */
    private function construirTarjetaConfirmacion(
        string $operacion,
        array $args,
        int $rolId,
        string $nombreRol,
        int $userId,
        string $origen
    ): array {
        if (!$this->rolPuedeEjecutarOperacion($rolId, $operacion)) {
            $msg = "Acceso denegado: Tu perfil de {$nombreRol} no está autorizado para ejecutar la operación '{$this->etiquetaOperacion($operacion)}'.";
            return [
                'ok'             => true,
                'tipo'           => 'restriccion',
                'origen'         => $origen,
                'texto_pantalla' => $msg,
                'texto_voz'      => $this->prepararTextoParaVoz($msg),
                'datos_resumen'  => [],
            ];
        }

        return match ($operacion) {
            'preparar_venta'             => $this->armarPreviewVenta($args, $userId, $origen),
            'preparar_cotizacion'        => $this->armarPreviewCotizacion($args, $userId, $origen),
            'preparar_compra'            => $this->armarPreviewCompra($args, $userId, $origen),
            'preparar_ajuste_inventario' => $this->armarPreviewAjuste($args, $userId, $origen),
            default                      => [
                'ok'             => true,
                'tipo'           => 'informacion',
                'origen'         => $origen,
                'texto_pantalla' => 'No pude identificar una operación transaccional válida. Puedes pedirme registrar una venta, cotización, compra o ajuste de inventario.',
                'texto_voz'      => 'No pude identificar una operación transaccional válida.',
                'datos_resumen'  => [],
            ],
        };
    }

    private function armarPreviewVenta(array $args, int $userId, string $origen): array
    {
        $cliente = $this->resolverCliente($args['cliente'] ?? null);
        $itemsResueltos = $this->resolverItemsProductos($args['items'] ?? [], false);

        if (empty($itemsResueltos['items'])) {
            $msg = $itemsResueltos['error'] ?? 'No encontré los repuestos indicados en el catálogo activo. Especifica el nombre o código OEM exacto.';
            return $this->respuestaInformativa($msg, $origen);
        }

        $sesionAbierta = DB::table('sesiones_cajas')
            ->where('estado', 'Abierta')
            ->orderByRaw('CASE WHEN id_usuario_cajero = ? THEN 0 ELSE 1 END', [$userId])
            ->orderByDesc('id_sesion')
            ->first();

        $bruto = round(array_sum(array_column($itemsResueltos['items'], 'subtotal')), 2);
        $descuento = round(max(0.0, (float) ($args['descuento_total'] ?? 0)), 2);
        $neto = round(max(0.0, $bruto - $descuento), 2);
        $metodoPago = $this->normalizarMetodoPago($args['metodo_pago'] ?? 'Efectivo');

        $advertencias = $itemsResueltos['advertencias'];
        if (!$sesionAbierta) {
            $advertencias[] = 'Actualmente no hay una sesión de caja abierta; PostgreSQL bloqueará el cobro hasta aperturar caja.';
        }

        $payload = [
            'operacion'       => 'preparar_venta',
            'user_id'         => $userId,
            'created_at'      => now()->timestamp,
            'id_cliente'      => (int) $cliente->id_cliente,
            'cliente_nombre'  => $cliente->nombre_razon_social,
            'id_sesion_caja'  => $sesionAbierta ? (int) $sesionAbierta->id_sesion : null,
            'metodo_pago'     => $metodoPago,
            'descuento_total' => $descuento,
            'subtotal_bruto'  => $bruto,
            'total_neto'      => $neto,
            'items'           => $itemsResueltos['items'],
        ];

        $token = $this->guardarAccionPendiente($payload);
        $resumenItems = implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $itemsResueltos['items']));
        $texto = "He preparado el borrador de Nota de Venta para {$cliente->nombre_razon_social}: {$resumenItems}. Total neto: Bs. " . number_format($neto, 2) . " ({$metodoPago}). Revisa la tarjeta y presiona Confirmar y Ejecutar para asentarla en Smart-DB.";

        return [
            'ok'               => true,
            'tipo'             => 'confirmacion',
            'origen'           => $origen,
            'texto_pantalla'   => $texto,
            'texto_voz'        => $this->prepararTextoParaVoz($texto),
            'datos_resumen'    => [],
            'accion_pendiente' => [
                'action_token' => $token,
                'operacion'    => 'preparar_venta',
                'titulo'       => 'Confirmar Registro de Venta en POS',
                'badge'        => 'Venta POS · sp_registrar_venta',
                'resumen'      => [
                    'Cliente'      => $cliente->nombre_razon_social . ' (CI/NIT: ' . ($cliente->ci_nit ?: 'S/N') . ')',
                    'Sesión Caja'  => $sesionAbierta ? "Caja #{$sesionAbierta->id_sesion} (Abierta)" : 'Sin caja abierta',
                    'Medio Pago'   => $metodoPago,
                    'Descuento'    => 'Bs. ' . number_format($descuento, 2),
                    'Total Neto'   => 'Bs. ' . number_format($neto, 2),
                ],
                'items'        => $itemsResueltos['items'],
                'total_neto'   => $neto,
                'advertencia'  => !empty($advertencias) ? implode(' ', $advertencias) : null,
            ],
        ];
    }

    private function armarPreviewCotizacion(array $args, int $userId, string $origen): array
    {
        $cliente = $this->resolverCliente($args['cliente'] ?? null);
        $itemsResueltos = $this->resolverItemsProductos($args['items'] ?? [], false);

        if (empty($itemsResueltos['items'])) {
            $msg = $itemsResueltos['error'] ?? 'No encontré los repuestos indicados para la proforma. Indica el nombre o código OEM del repuesto.';
            return $this->respuestaInformativa($msg, $origen);
        }

        $diasVigencia = max(1, min(90, (int) ($args['dias_vigencia'] ?? 7)));
        $total = round(array_sum(array_column($itemsResueltos['items'], 'subtotal')), 2);

        $payload = [
            'operacion'      => 'preparar_cotizacion',
            'user_id'        => $userId,
            'created_at'     => now()->timestamp,
            'id_cliente'     => (int) $cliente->id_cliente,
            'cliente_nombre' => $cliente->nombre_razon_social,
            'dias_vigencia'  => $diasVigencia,
            'total_neto'     => $total,
            'items'          => $itemsResueltos['items'],
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "He preparado el borrador de Cotización Formal para {$cliente->nombre_razon_social} con vigencia de {$diasVigencia} días por un importe total de Bs. " . number_format($total, 2) . ". Confirma la tarjeta para emitirla.";

        return [
            'ok'               => true,
            'tipo'             => 'confirmacion',
            'origen'           => $origen,
            'texto_pantalla'   => $texto,
            'texto_voz'        => $this->prepararTextoParaVoz($texto),
            'datos_resumen'    => [],
            'accion_pendiente' => [
                'action_token' => $token,
                'operacion'    => 'preparar_cotizacion',
                'titulo'       => 'Confirmar Emisión de Cotización Formal',
                'badge'        => 'Proforma · sp_registrar_cotizacion',
                'resumen'      => [
                    'Cliente'          => $cliente->nombre_razon_social,
                    'Vigencia'         => "{$diasVigencia} días (vence " . Carbon::today()->addDays($diasVigencia)->format('d/m/Y') . ')',
                    'Total Cotizado'   => 'Bs. ' . number_format($total, 2),
                ],
                'items'        => $itemsResueltos['items'],
                'total_neto'   => $total,
                'advertencia'  => !empty($itemsResueltos['advertencias']) ? implode(' ', $itemsResueltos['advertencias']) : null,
            ],
        ];
    }

    private function armarPreviewCompra(array $args, int $userId, string $origen): array
    {
        $proveedor = $this->resolverProveedor($args['proveedor'] ?? null);
        if (!$proveedor) {
            return $this->respuestaInformativa('No encontré un proveedor activo coincidente en el sistema. Indica el nombre del proveedor registrado (por ejemplo, Toyosa, Imcruz, etc.).', $origen);
        }

        $itemsResueltos = $this->resolverItemsProductos($args['items'] ?? [], true);
        if (empty($itemsResueltos['items'])) {
            $msg = $itemsResueltos['error'] ?? 'No encontré los repuestos indicados para la nota de compra.';
            return $this->respuestaInformativa($msg, $origen);
        }

        $factura = trim((string) ($args['numero_factura_nota'] ?? ''));
        if (mb_strlen($factura) < 2) {
            $factura = 'FAC-IA-' . date('YmdHis');
        }
        $tipoPagoRaw = $this->normalizarTexto((string) ($args['tipo_pago'] ?? 'Contado'));
        $tipoPago = str_contains($tipoPagoRaw, 'cred') ? 'Crédito' : 'Contado';
        $total = round(array_sum(array_column($itemsResueltos['items'], 'subtotal')), 2);

        $payload = [
            'operacion'           => 'preparar_compra',
            'user_id'             => $userId,
            'created_at'          => now()->timestamp,
            'id_proveedor'        => (int) $proveedor->id_proveedor,
            'proveedor_nombre'    => $proveedor->razon_social,
            'numero_factura_nota' => $factura,
            'fecha_compra'        => Carbon::today()->toDateString(),
            'tipo_pago'           => $tipoPago,
            'total_neto'          => $total,
            'items'               => $itemsResueltos['items'],
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "He preparado el borrador de Nota de Compra para {$proveedor->razon_social} (Factura {$factura}, {$tipoPago}) por un total de Bs. " . number_format($total, 2) . ". Confirma para ingresar las unidades al almacén.";

        return [
            'ok'               => true,
            'tipo'             => 'confirmacion',
            'origen'           => $origen,
            'texto_pantalla'   => $texto,
            'texto_voz'        => $this->prepararTextoParaVoz($texto),
            'datos_resumen'    => [],
            'accion_pendiente' => [
                'action_token' => $token,
                'operacion'    => 'preparar_compra',
                'titulo'       => 'Confirmar Recepción de Compra a Proveedor',
                'badge'        => 'Compra · sp_registrar_compra',
                'resumen'      => [
                    'Proveedor'       => $proveedor->razon_social,
                    'Factura / Nota'  => $factura,
                    'Modalidad Pago'  => $tipoPago . ($tipoPago === 'Crédito' ? ' (Pendiente)' : ' (Pagado)'),
                    'Total Compra'    => 'Bs. ' . number_format($total, 2),
                ],
                'items'        => $itemsResueltos['items'],
                'total_neto'   => $total,
                'advertencia'  => null,
            ],
        ];
    }

    private function armarPreviewAjuste(array $args, int $userId, string $origen): array
    {
        $prodQuery = trim((string) ($args['producto'] ?? ''));
        $producto = $this->buscarProductoPorTexto($prodQuery);
        if (!$producto) {
            return $this->respuestaInformativa("No encontré el repuesto '{$prodQuery}' en el catálogo activo para aplicar el ajuste de inventario.", $origen);
        }

        $tipoAjuste = $this->normalizarTipoAjuste((string) ($args['tipo_ajuste'] ?? 'Merma'));
        $cantidad = abs((int) ($args['cantidad_ajustada'] ?? 1));
        if ($tipoAjuste !== 'Conteo Físico' && $cantidad <= 0) {
            $cantidad = 1;
        }

        $motivo = trim((string) ($args['motivo'] ?? ''));
        if (mb_strlen($motivo) < 10) {
            $motivo = "Ajuste por {$tipoAjuste} registrado vía asistente operativo para {$producto->nombre_pieza}.";
        }

        $advertencia = null;
        if ($tipoAjuste !== 'Conteo Físico' && $cantidad > (int) $producto->stock_actual) {
            $advertencia = "Stock actual ({$producto->stock_actual} uds) es menor al descuento solicitado ({$cantidad} uds); Smart-DB rechazará la operación si no corriges la cantidad.";
        }

        $payload = [
            'operacion'         => 'preparar_ajuste_inventario',
            'user_id'           => $userId,
            'created_at'        => now()->timestamp,
            'id_producto'       => (int) $producto->id_producto,
            'codigo_oem'        => $producto->codigo_oem,
            'nombre_pieza'      => $producto->nombre_pieza,
            'stock_actual'      => (int) $producto->stock_actual,
            'tipo_ajuste'       => $tipoAjuste,
            'cantidad_ajustada' => $cantidad,
            'motivo'            => $motivo,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $accionDesc = $tipoAjuste === 'Conteo Físico'
            ? "fijar conteo físico en {$cantidad} unidades (actual: {$producto->stock_actual})"
            : "descontar {$cantidad} unidad(es) por {$tipoAjuste} (actual: {$producto->stock_actual})";
        $texto = "He preparado el ajuste de inventario sobre {$producto->nombre_pieza} (OEM {$producto->codigo_oem}): {$accionDesc}. Presiona Confirmar y Ejecutar para aplicarlo en PostgreSQL.";

        return [
            'ok'               => true,
            'tipo'             => 'confirmacion',
            'origen'           => $origen,
            'texto_pantalla'   => $texto,
            'texto_voz'        => $this->prepararTextoParaVoz($texto),
            'datos_resumen'    => [],
            'accion_pendiente' => [
                'action_token' => $token,
                'operacion'    => 'preparar_ajuste_inventario',
                'titulo'       => 'Confirmar Ajuste de Inventario',
                'badge'        => 'Almacén · sp_registrar_ajuste_inventario',
                'resumen'      => [
                    'Repuesto'      => "{$producto->nombre_pieza} (OEM {$producto->codigo_oem})",
                    'Tipo Ajuste'   => $tipoAjuste,
                    'Cantidad'      => $tipoAjuste === 'Conteo Físico' ? "{$cantidad} uds (Conteo)" : "-{$cantidad} uds",
                    'Stock Actual'  => "{$producto->stock_actual} uds",
                    'Motivo'        => $motivo,
                ],
                'items'        => [],
                'total_neto'   => null,
                'advertencia'  => $advertencia,
            ],
        ];
    }

    /**
     * Parser transaccional local resiliente para voz/texto cuando Gemini no está disponible.
     */
    private function resolverTransaccionLocal(string $pregunta, int $rolId, string $nombreRol, int $userId): ?array
    {
        $norm = $this->normalizarTexto($pregunta);

        // 1. Ajuste de inventario (merma, pérdida, devolución dañada, uso interno, conteo físico)
        if (preg_match('/\b(registra|registrar|aplica|aplicar|reporta|reportar|anota|anotar|da de baja|descuenta|ajusta|ajustar)\b.*\b(merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|ajuste)\b/', $norm)
            || preg_match('/\b(merma|uso interno|conteo fisico|devolucion danada|perdida)\s+de\s+\d+/', $norm)
        ) {
            $tipo = 'Merma';
            if (str_contains($norm, 'uso interno') || str_contains($norm, 'autoconsumo')) {
                $tipo = 'Uso Interno';
            } elseif (str_contains($norm, 'conteo')) {
                $tipo = 'Conteo Físico';
            } elseif (str_contains($norm, 'devolucion') || str_contains($norm, 'danad')) {
                $tipo = 'Devolución Dañada';
            } elseif (str_contains($norm, 'perdida') || str_contains($norm, 'extravio')) {
                $tipo = 'Pérdida/Extravío';
            }

            $cantidad = 1;
            if (preg_match('/\b(\d+)\b/', $norm, $mCant)) {
                $cantidad = max(1, (int) $mCant[1]);
            }

            $motivo = $pregunta;
            if (preg_match('/\b(?:por|motivo|porque)\s+(.+)$/i', $pregunta, $mMot)) {
                $motivo = trim($mMot[1]);
            }

            $productoTexto = $this->extraerFragmentoProductoDeFrase($norm);
            return $this->construirTarjetaConfirmacion('preparar_ajuste_inventario', [
                'producto'          => $productoTexto,
                'cantidad_ajustada' => $cantidad,
                'tipo_ajuste'       => $tipo,
                'motivo'            => $motivo,
            ], $rolId, $nombreRol, $userId, 'local');
        }

        // 2. Compra a proveedor
        if (preg_match('/\b(registra|registrar|ingresa|ingresar|recepciona|recepcionar|compra|comprar)\b.*\b(compra|proveedor|factura)\b/', $norm)) {
            $factura = 'FAC-IA-' . date('His');
            if (preg_match('/\bfactura\s+(?:n[°º#]?\s*)?([a-z0-9\-]+)/i', $pregunta, $mFac)) {
                $factura = strtoupper($mFac[1]);
            }
            $tipoPago = str_contains($norm, 'credito') ? 'Crédito' : 'Contado';

            $proveedorTexto = '';
            if (preg_match('/\b(?:proveedor|a|de)\s+([a-záéíóúñ0-9\.\s]{3,40}?)(?:\s+(?:al|con|factura|por|de\s+\d)|\s*$)/iu', $pregunta, $mProv)) {
                $proveedorTexto = trim($mProv[1]);
            }
            if (!$this->resolverProveedor($proveedorTexto)) {
                // Buscar cualquier proveedor que aparezca mencionado en la frase
                $todosProv = DB::table('proveedores')->where('estado', true)->get();
                foreach ($todosProv as $pr) {
                    $palabras = array_filter(explode(' ', $this->normalizarTexto($pr->razon_social)), fn($w) => mb_strlen($w) >= 4);
                    foreach ($palabras as $w) {
                        if (str_contains($norm, $w)) {
                            $proveedorTexto = $pr->razon_social;
                            break 2;
                        }
                    }
                }
            }

            $cantidad = 1;
            if (preg_match('/\bde\s+(\d+)\s+/i', $norm, $mC)) {
                $cantidad = max(1, (int) $mC[1]);
            } elseif (preg_match('/\b(\d+)\s+(?:unidades|uds|piezas|pzs)?\s*[a-z]{3,}/i', $norm, $mC)) {
                $cantidad = max(1, (int) $mC[1]);
            }

            $costo = null;
            if (preg_match('/\ba\s+(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)?/i', $norm, $mCosto)) {
                $costo = (float) str_replace(',', '.', $mCosto[1]);
            }

            $prodTexto = $this->extraerFragmentoProductoDeFrase($norm);
            return $this->construirTarjetaConfirmacion('preparar_compra', [
                'proveedor'           => $proveedorTexto,
                'numero_factura_nota' => $factura,
                'tipo_pago'           => $tipoPago,
                'items'               => [[
                    'producto'       => $prodTexto,
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo,
                ]],
            ], $rolId, $nombreRol, $userId, 'local');
        }

        // 3. Cotización formal
        if (preg_match('/\b(cotiza|cotizar|prepara|genera|crea|emite)\b.*\b(cotizacion|proforma)\b/', $norm)
            || preg_match('/^\s*cotiza(?:r|me)?\s+\d+/i', $norm)
        ) {
            $dias = 7;
            if (preg_match('/\b(\d+)\s+dias\b/', $norm, $mDias)) {
                $dias = max(1, min(90, (int) $mDias[1]));
            }
            $clienteTexto = $this->extraerClienteDeFrase($pregunta, $norm);
            $items = $this->extraerItemsDeFrase($norm);

            return $this->construirTarjetaConfirmacion('preparar_cotizacion', [
                'cliente'       => $clienteTexto,
                'dias_vigencia' => $dias,
                'items'         => $items,
            ], $rolId, $nombreRol, $userId, 'local');
        }

        // 4. Venta en POS
        if (preg_match('/\b(vende|vender|factura|facturar|cobra|cobrar|registra(?:r)?\s+(?:una\s+)?venta|prepara(?:r)?\s+(?:una\s+)?venta)\b/', $norm)) {
            $metodo = 'Efectivo';
            if (str_contains($norm, 'mixto')) {
                $metodo = 'Mixto';
            } elseif (str_contains($norm, 'qr') || str_contains($norm, 'transferencia')) {
                $metodo = 'Transferencia/QR';
            } elseif (str_contains($norm, 'tarjeta')) {
                $metodo = 'Tarjeta Débito/Crédito';
            }

            $descuento = 0.0;
            if (preg_match('/descuento\s+(?:de\s+)?(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)?/i', $norm, $mDesc)) {
                $descuento = (float) str_replace(',', '.', $mDesc[1]);
            }

            $clienteTexto = $this->extraerClienteDeFrase($pregunta, $norm);
            $items = $this->extraerItemsDeFrase($norm);

            return $this->construirTarjetaConfirmacion('preparar_venta', [
                'cliente'         => $clienteTexto,
                'items'           => $items,
                'descuento_total' => $descuento,
                'metodo_pago'     => $metodo,
            ], $rolId, $nombreRol, $userId, 'local');
        }

        return null;
    }

    private function detectarIntencionEscritura(string $norm): bool
    {
        return (bool) preg_match(
            '/\b(vende|vender|facturar|registra(?:r)?\s+(?:una\s+)?(?:venta|cotizacion|proforma|compra|merma|perdida|ajuste)|prepara(?:r)?\s+(?:una\s+)?(?:venta|cotizacion|proforma|compra|ajuste)|genera(?:r)?\s+(?:una\s+)?(?:cotizacion|proforma)|emite\s+(?:una\s+)?(?:cotizacion|proforma)|cotiza(?:r|me)?\s+\d+|da de baja|descuenta\s+\d+.*(?:merma|uso interno|perdida))\b/',
            $norm
        );
    }

    /**
     * Auditor de Seguridad SQL Read-Only y Permisos por Rol (CU39).
     */
    public function esSqlSeguro(string $sql, int $rolId = 1): bool
    {
        $sqlTrim = trim($sql);

        // 1. Debe empezar exclusivamente con SELECT o WITH
        if (!preg_match('/^(SELECT|WITH)\b/i', $sqlTrim)) {
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

        // 5. Segregación estricta por Rol (CU39):
        // Bodeguero (Rol 2): NO rentabilidad, NO caja/arqueo, NO bitácoras de seguridad
        if ($rolId === 2) {
            if (preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas|fn_reporte_rentabilidad|fn_reporte_balance_medios_pago)\b/i', $sqlTrim)) {
                return false;
            }
        }

        // Vendedor (Rol 3): NO costos de compra, NO rentabilidad/márgenes, NO caja/arqueo, NO bitácora
        if ($rolId === 3) {
            if (preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas|notas_compras|detalles_compras|costo_unitario|margen_ganancia|fn_reporte_rentabilidad|fn_reporte_compras_distribuidor|fn_reporte_balance_medios_pago)\b/i', $sqlTrim)) {
                return false;
            }
        }

        // Cajero (Rol 4): NO costos de compra, NO rentabilidad/márgenes, NO bitácora de seguridad
        if ($rolId === 4) {
            if (preg_match('/\b(bitacoras_auditorias|detalles_compras|costo_unitario|margen_ganancia|fn_reporte_rentabilidad|fn_reporte_compras_distribuidor)\b/i', $sqlTrim)) {
                return false;
            }
        }

        // Cliente Web (Rol 5): solo catálogo público
        if ($rolId === 5) {
            if (preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas|notas_compras|detalles_compras|notas_ventas|detalles_ventas|usuarios|clientes|proveedores|fn_reporte_)\b/i', $sqlTrim)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica preventivamente si la intención analítica viola la segregación de roles (CU39).
     */
    private function verificarRestriccionRol(string $pregunta, int $rolId, string $nombreRol): ?array
    {
        $norm = $this->normalizarTexto($pregunta);

        // Bodeguero (Rol 2): no rentabilidad ni montos globales de caja / auditoría
        if ($rolId === 2) {
            if (preg_match('/\b(rentabilidad|utilidad|ganancia|margen real|arqueo|cierre de caja|sesion de caja|estado de caja|balance.*pago|recaudado|bitacora|auditoria)\b/', $norm)) {
                return $this->respuestaRestriccion("Acceso restringido: Tu perfil de {$nombreRol} no tiene autorización para consultar reportes de rentabilidad ni saldos globales de caja o auditoría.");
            }
        }

        // Vendedor (Rol 3): no costos de compra, no márgenes/rentabilidad, no arqueos de caja ni bitácora
        if ($rolId === 3) {
            if (preg_match('/\b(rentabilidad|utilidad|margen|costo.*compra|costo unitario|compras.*proveedor|compras.*distribuidor|cuentas por pagar|deuda.*proveedor|arqueo|cierre de caja|sesion de caja|bitacora|auditoria)\b/', $norm)) {
                return $this->respuestaRestriccion("Acceso restringido: Tu perfil de {$nombreRol} no tiene autorización para consultar costos de compra, márgenes de ganancia ni cierres de caja.");
            }
        }

        // Cajero (Rol 4): no costos de compra ni márgenes de ganancia / rentabilidad
        if ($rolId === 4) {
            if (preg_match('/\b(rentabilidad|utilidad|margen|costo.*compra|costo unitario|compras.*distribuidor|bitacora|auditoria)\b/', $norm)) {
                return $this->respuestaRestriccion("Acceso restringido: Tu perfil de {$nombreRol} no tiene autorización para consultar costos de adquisición ni márgenes de ganancia.");
            }
        }

        return null;
    }

    /**
     * Vía B (Analítica): Motor Semántico Local Resiliente en PHP con soporte para los 4 reportes del Ciclo 4
     * + las 7 intenciones operativas de los Ciclos 1-3.
     */
    private function consultarViaMotorLocal(string $pregunta, int $rolId, string $nombreRol): array
    {
        $norm = $this->normalizarTexto($pregunta);

        try {
            // REPORTE CU35: Rotación de repuestos y productos hueso / baja rotación
            if (preg_match('/\b(rotacion|hueso|baja salida|sin movimiento|mas vendido|mas vendidos|menos vendido|desplazamiento)\b/', $norm)) {
                return $this->intencionRotacionRepuestos($norm);
            }

            // REPORTE CU36: Rentabilidad y márgenes brutos reales
            if (preg_match('/\b(rentabilidad|margen|margenes|utilidad|ganancia|rentable|rentables)\b/', $norm)) {
                return $this->intencionRentabilidad();
            }

            // REPORTE CU37: Compras por distribuidor / proveedor
            if (preg_match('/\b(compras.*proveedor|compras.*distribuidor|distribuidor|distribuidores|resumen de compras|comprado a)\b/', $norm)) {
                return $this->intencionComprasDistribuidor();
            }

            // REPORTE CU38: Balance de medios de pago (Efectivo, QR, Tarjeta, Mixto)
            if (preg_match('/\b(balance.*pago|medios de pago|metodos de pago|desglose.*pago|recaudacion por canal|por metodo)\b/', $norm)) {
                return $this->intencionBalanceMediosPago();
            }

            // INTENCIÓN 1: Alertas de stock crítico, bajo mínimo o agotado
            if (preg_match('/\b(stock critico|stock bajo|bajo minimo|alerta.*stock|agotado|reabastecer|faltante.*stock|pocas unidades)\b/', $norm)) {
                return $this->intencionStockCritico();
            }

            // INTENCIÓN 2: Cuentas por pagar y deudas a proveedores (Crédito / Pendiente)
            if (preg_match('/\b(cuentas por pagar|deuda|proveedores|pendiente.*pago|credito.*proveedor|compras a credito|por pagar)\b/', $norm)) {
                return $this->intencionCuentasPorPagar();
            }

            // INTENCIÓN 3: Ventas del día, ingresos por método de pago y estado de caja
            if (preg_match('/\b(venta|vendio|vendido|recaudado|ingresos de hoy|caja|efectivo|transferencia|qr|tarjeta|arqueo)\b/', $norm)) {
                return $this->intencionVentasYCaja($norm, $rolId);
            }

            // INTENCIÓN 4: Cotizaciones vigentes o vencidas
            if (preg_match('/\b(cotizacion|cotizaciones|proforma|proformas|vigente|vencida)\b/', $norm)) {
                return $this->intencionCotizaciones($norm);
            }

            // INTENCIÓN 5: Clientes particulares, talleres aliados y flotas
            if (preg_match('/\b(cliente|clientes|taller|talleres|mecanico|flota|descuento frecuente)\b/', $norm)) {
                return $this->intencionClientes($norm);
            }

            // INTENCIÓN 6: Compatibilidad vehicular
            if ($this->detectarIntencionCompatibilidad($norm)) {
                return $this->intencionCompatibilidadVehicular($norm);
            }

            // INTENCIÓN 7: Búsqueda de repuestos por nombre, código OEM/alternativo, stock, precio y ubicación
            return $this->intencionBuscarRepuesto($pregunta, $norm);
        } catch (\Throwable $e) {
            Log::error('AiSqlAssistantService (Motor Local): ' . $e->getMessage());
            $msg = 'En este momento no pude procesar los datos solicitados. Intenta reformular tu consulta indicando el repuesto, código OEM, reporte o acción deseada.';
            return $this->respuestaInformativa($msg, 'local');
        }
    }

    /**
     * Reporte Analítico CU35 vía fn_reporte_rotacion_repuestos.
     */
    private function intencionRotacionRepuestos(string $norm): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();

        $filas = collect(DB::select('SELECT * FROM fn_reporte_rotacion_repuestos(?, ?, NULL)', [$inicio, $fin]));

        if (str_contains($norm, 'hueso') || str_contains($norm, 'baja') || str_contains($norm, 'sin movimiento')) {
            $filtradas = $filas->where('clasificacion', 'Baja Rotación / Hueso')->take(10)->values();
            $enfoque = 'de baja rotación / hueso';
        } else {
            $filtradas = $filas->take(10)->values();
            $enfoque = 'con mayor rotación comercial';
        }

        $resumen = $filtradas->map(fn($r) => [
            'SKU'           => $r->codigo_interno,
            'Repuesto'      => $r->nombre_pieza,
            'Stock'         => (int) $r->stock_actual,
            'Vendidas'      => (int) $r->unidades_vendidas,
            'Rotacion_Pct'  => number_format((float) $r->indice_rotacion * 100, 1) . '%',
            'Clasificacion' => $r->clasificacion,
        ])->toArray();

        $cant = count($resumen);
        $top = $resumen[0] ?? null;
        $texto = $top
            ? "Análisis de rotación de los últimos 90 días ({$enfoque}): se listan {$cant} repuestos. Destaca {$top['Repuesto']} ({$top['SKU']}) con {$top['Vendidas']} unidades vendidas, {$top['Stock']} en stock y clasificación {$top['Clasificacion']}."
            : 'No se encontraron repuestos activos para calcular rotación en el período.';

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $resumen,
        ];
    }

    /**
     * Reporte Analítico CU36 vía fn_reporte_rentabilidad.
     */
    private function intencionRentabilidad(): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();

        $filas = collect(DB::select('SELECT * FROM fn_reporte_rentabilidad(?, ?, NULL)', [$inicio, $fin]));
        $ingresos = (float) $filas->sum('ingresos_netos');
        $utilidad = (float) $filas->sum('utilidad_bruta');
        $margen   = $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 2) : 0.0;

        $resumen = $filas->take(10)->map(fn($r) => [
            'SKU'            => $r->codigo_interno,
            'Repuesto'       => $r->nombre_pieza,
            'Vendidas'       => (int) $r->cantidad_vendida,
            'Ingresos_Bs'    => number_format((float) $r->ingresos_netos, 2),
            'Utilidad_Bs'    => number_format((float) $r->utilidad_bruta, 2),
            'Margen_Real_Pct'=> number_format((float) $r->margen_real_porcentaje, 1) . '%',
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'No se registran ventas completadas en los últimos 90 días para calcular rentabilidad.'
            : 'En los últimos 90 días se generaron ingresos netos de Bs. ' . number_format($ingresos, 2) . ' y una utilidad bruta estimada de Bs. ' . number_format($utilidad, 2) . " (margen real promedio de {$margen}%). El repuesto más rentable es {$resumen[0]['Repuesto']} con Bs. {$resumen[0]['Utilidad_Bs']} de utilidad.";

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $resumen,
        ];
    }

    /**
     * Reporte Analítico CU37 vía fn_reporte_compras_distribuidor.
     */
    private function intencionComprasDistribuidor(): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();

        $filas = collect(DB::select('SELECT * FROM fn_reporte_compras_distribuidor(?, ?, NULL)', [$inicio, $fin]));
        $totalComprado = (float) $filas->sum('monto_total_comprado');
        $saldoPend     = (float) $filas->sum('saldo_pendiente_credito');

        $resumen = $filas->take(10)->map(fn($r) => [
            'Distribuidor'  => $r->razon_social,
            'Ordenes'       => (int) $r->total_ordenes,
            'Unidades'      => (int) $r->unidades_compradas,
            'Comprado_Bs'   => number_format((float) $r->monto_total_comprado, 2),
            'Pendiente_Bs'  => number_format((float) $r->saldo_pendiente_credito, 2),
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'No se registraron órdenes de compra a distribuidores en los últimos 90 días.'
            : 'Resumen de compras por distribuidor (últimos 90 días): total adquirido de Bs. ' . number_format($totalComprado, 2) . ' entre ' . count($resumen) . ' proveedores, con saldo pendiente a crédito de Bs. ' . number_format($saldoPend, 2) . '.';

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $resumen,
        ];
    }

    /**
     * Reporte Analítico CU38 vía fn_reporte_balance_medios_pago.
     */
    private function intencionBalanceMediosPago(): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();

        $filas = collect(DB::select('SELECT * FROM fn_reporte_balance_medios_pago(?, ?, NULL, NULL)', [$inicio, $fin]));
        $totalNeto = (float) $filas->sum('total_neto_recaudado');
        $tickets   = (int) $filas->sum('cantidad_tickets');

        $resumen = $filas->take(10)->map(fn($r) => [
            'Caja'          => '#' . $r->id_sesion . ' (' . $r->estado_sesion . ')',
            'Cajero'        => $r->cajero_responsable,
            'Medio_Pago'    => $r->metodo_pago,
            'Tickets'       => (int) $r->cantidad_tickets,
            'Recaudado_Bs'  => number_format((float) $r->total_neto_recaudado, 2),
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'No existen ventas completadas en los últimos 90 días para balance de medios de pago.'
            : "Balance consolidado por medios de pago (últimos 90 días): {$tickets} tickets completados por un neto recaudado de Bs. " . number_format($totalNeto, 2) . '.';

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $resumen,
        ];
    }

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
                fn($d) => "{$d['Proveedor']} (Factura {$d['Factura']} por Bs. " . number_format((float) $d['Monto_Deuda_Bs'], 2) . ')',
                array_slice($deudas, 0, 3)
            );
            $texto = "Existen {$cantidad} facturas pendientes por pagar a proveedores por un total acumulado de Bs. " . number_format($sumaDeuda, 2) . '. Principales compromisos: ' . implode('; ', $detalleProv) . '.';
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

    private function intencionVentasYCaja(string $norm, int $rolId): array
    {
        $hoy = date('Y-m-d');

        $ventasHoyQuery = DB::table('notas_ventas')
            ->where('estado', 'Completada')
            ->whereDate('fecha_hora', $hoy);

        $totalHoy = (float) $ventasHoyQuery->sum('total_venta');
        $cantHoy  = (int) $ventasHoyQuery->count();

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
            $texto = "Hoy se han completado {$cantHoy} ventas por un total neto de Bs. " . number_format($totalHoy, 2) . ". En el acumulado histórico se registran {$cantGlobal} ventas completadas por Bs. " . number_format($totalGlobal, 2) . '.' . $infoCaja;
        } else {
            $texto = "Hoy aún no se registraron notas de venta. El acumulado histórico del sistema registra {$cantGlobal} ventas completadas por un total de Bs. " . number_format($totalGlobal, 2) . '.' . $infoCaja;
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

        $texto = 'Se encontraron ' . count($filas) . " proformas en la consulta. Actualmente existen {$totalVigentes} cotizaciones vigentes listas para conversión a venta por un importe total de Bs. " . number_format($montoVigentes, 2) . '.';

        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => 'local',
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $filas,
        ];
    }

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

    private function detectarIntencionCompatibilidad(string $norm): bool
    {
        return (bool) preg_match('/\b(compatible|compatibilidad|le hace|sirve para|vehiculo|modelo|toyota|nissan|suzuki|mitsubishi|hyundai|kia|mazda|honda|isuzu|corolla|hilux|vitara|sentra|frontier|tucson|sportage|l200|rav4|yaris|swift|rio|accent|crv|bt50|dmax)\b/', $norm);
    }

    private function intencionCompatibilidadVehicular(string $norm): array
    {
        $palabrasClave = $this->extraerTerminosSignificativos($norm);
        $marcasModelos = ['toyota', 'nissan', 'suzuki', 'mitsubishi', 'hyundai', 'kia', 'mazda', 'honda', 'isuzu', 'corolla', 'hilux', 'vitara', 'sentra', 'frontier', 'tucson', 'sportage', 'l200', 'rav4', 'yaris', 'swift', 'rio', 'accent', 'crv', 'bt50', 'dmax'];
        $vehiculosDetectados = array_values(array_intersect($palabrasClave, $marcasModelos));
        $piezasDetectadas = array_values(array_diff($palabrasClave, $marcasModelos));

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

        if (!empty($vehiculosDetectados)) {
            $query->where(function ($q) use ($vehiculosDetectados) {
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
        } elseif (!empty($palabrasClave)) {
            $query->where(function ($q) use ($palabrasClave) {
                foreach ($palabrasClave as $term) {
                    $q->orWhere('v.marca', 'ilike', "%{$term}%")
                      ->orWhere('v.modelo', 'ilike', "%{$term}%")
                      ->orWhere('p.nombre_pieza', 'ilike', "%{$term}%")
                      ->orWhere('p.codigo_oem', 'ilike', "%{$term}%");
                }
            });
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
            $texto = "Se encontraron {$cant} repuestos compatibles verificados. Por ejemplo: {$primero['Repuesto']} ({$primero['Marca_Pieza']}, OEM {$primero['OEM']}) compatible con {$primero['Vehiculo_Compatible']}, con {$primero['Stock']} unidades disponibles en ubicación {$primero['Ubicacion']} a Bs. " . number_format((float) $primero['Precio_Bs'], 2) . '.';
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
            $totalPzs = DB::table('productos')->where('estado', true)->count();
            $totalUnidades = (int) DB::table('productos')->where('estado', true)->sum('stock_actual');
            $texto = "No encontré un repuesto específico con ese término, pero el almacén cuenta actualmente con {$totalPzs} ítems activos y {$totalUnidades} unidades en inventario. Puedes consultar por nombre de pieza, código OEM, vehículo, rotación, rentabilidad o solicitar registrar una operación.";
        } else {
            $cant = count($productos);
            $top = $productos[0];
            $texto = "Se encontraron {$cant} coincidencias en inventario. Destaca: {$top['Repuesto']} ({$top['Marca']}, OEM {$top['OEM']}), con {$top['Stock']} unidades en {$top['Ubicacion']} y precio de venta de Bs. " . number_format((float) $top['Precio_Bs'], 2) . '.';
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

    // ─── Helpers de Resolución Transaccional y Seguridad ───

    private function guardarAccionPendiente(array $payload): string
    {
        $token = (string) Str::uuid();
        session()->put("ai_pending_action.{$token}", $payload);
        return $token;
    }

    public function rolPuedeEjecutarOperacion(int $rolId, string $operacion): bool
    {
        $permitidas = self::PERMISOS_TRANSACCIONALES[$rolId] ?? [];
        return in_array($operacion, $permitidas, true);
    }

    private function resolverCliente(?string $busqueda): object
    {
        $texto = trim((string) $busqueda);
        if ($texto !== '' && !preg_match('/^(cliente general|mostrador|s\/n|consumidor final)$/i', $texto)) {
            $encontrado = DB::table('clientes')
                ->where('nombre_razon_social', 'ilike', "%{$texto}%")
                ->orWhere('ci_nit', 'ilike', "%{$texto}%")
                ->first();
            if ($encontrado) {
                return $encontrado;
            }
        }

        return DB::table('clientes')->orderBy('id_cliente')->first()
            ?? (object) ['id_cliente' => 1, 'nombre_razon_social' => 'Cliente General', 'ci_nit' => 'S/N'];
    }

    private function resolverProveedor(?string $busqueda): ?object
    {
        $texto = trim((string) $busqueda);
        if ($texto === '') {
            return null;
        }
        return DB::table('proveedores')
            ->where('estado', true)
            ->where(function ($q) use ($texto) {
                $q->where('razon_social', 'ilike', "%{$texto}%")
                  ->orWhere('nit', 'ilike', "%{$texto}%");
            })
            ->first();
    }

    private function resolverItemsProductos(array $rawItems, bool $esCompra): array
    {
        $items = [];
        $advertencias = [];

        foreach ($rawItems as $raw) {
            $nombre = trim((string) ($raw['producto'] ?? ''));
            $cantidad = max(1, (int) ($raw['cantidad'] ?? 1));
            $prod = $this->buscarProductoPorTexto($nombre);

            if (!$prod) {
                return [
                    'items'        => [],
                    'advertencias' => [],
                    'error'        => "No encontré el repuesto '{$nombre}' en el catálogo activo. Verifica el nombre o código OEM.",
                ];
            }

            if ($esCompra) {
                $costo = isset($raw['costo_unitario']) && (float) $raw['costo_unitario'] > 0
                    ? round((float) $raw['costo_unitario'], 2)
                    : round((float) $prod->precio_venta_sugerido / (1 + (((float) $prod->margen_ganancia ?: 25.0) / 100.0)), 2);
                $precioUnitario = $costo;
            } else {
                $precioUnitario = round((float) $prod->precio_venta_sugerido, 2);
                if ($cantidad > (int) $prod->stock_actual) {
                    $advertencias[] = "Stock actual de {$prod->nombre_pieza}: {$prod->stock_actual} uds (solicitado: {$cantidad}).";
                }
            }

            $items[] = [
                'id_producto'     => (int) $prod->id_producto,
                'codigo_oem'      => $prod->codigo_oem,
                'nombre_pieza'    => $prod->nombre_pieza,
                'cantidad'        => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal'        => round($cantidad * $precioUnitario, 2),
                'stock_actual'    => (int) $prod->stock_actual,
            ];
        }

        return ['items' => $items, 'advertencias' => $advertencias, 'error' => null];
    }

    private function buscarProductoPorTexto(string $texto): ?object
    {
        $limpio = trim($texto);
        if ($limpio === '') {
            return null;
        }

        // 1. Coincidencia exacta o parcial directa por OEM / SKU / nombre
        $exacto = DB::table('productos')
            ->where('estado', true)
            ->where(function ($q) use ($limpio) {
                $q->where('codigo_oem', 'ilike', $limpio)
                  ->orWhere('codigo_interno', 'ilike', $limpio)
                  ->orWhere('nombre_pieza', 'ilike', "%{$limpio}%");
            })
            ->orderByDesc('stock_actual')
            ->first();

        if ($exacto) {
            return $exacto;
        }

        // 2. Búsqueda por términos significativos con puntuación de coincidencias
        $terminos = $this->extraerTerminosSignificativos($this->normalizarTexto($limpio));
        if (empty($terminos)) {
            return null;
        }

        $candidatos = DB::table('productos')->where('estado', true)->get();
        $mejor = null;
        $mejorPuntaje = 0;

        foreach ($candidatos as $p) {
            $haystack = $this->normalizarTexto("{$p->nombre_pieza} {$p->codigo_oem} {$p->codigo_interno} {$p->marca_fabricante}");
            $puntaje = 0;
            foreach ($terminos as $t) {
                $raiz = $this->singularizar($t);
                if (str_contains($haystack, $t)) {
                    $puntaje += 2;
                } elseif (str_contains($haystack, $raiz)) {
                    $puntaje += 1;
                }
            }
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $p;
            }
        }

        return $mejorPuntaje > 0 ? $mejor : null;
    }

    private function extraerClienteDeFrase(string $original, string $norm): string
    {
        if (preg_match('/\b(?:cliente|para|al\s+taller|taller)\s+([a-záéíóúñ\s]{3,40}?)(?:\s+(?:en\s+efectivo|por\s+qr|por\s+transferencia|con\s+tarjeta|pago\s+mixto|con\s+descuento|por\s+\d+\s+d[ií]as)|\s*$)/iu', $original, $m)) {
            return trim($m[1]);
        }
        return 'Cliente General';
    }

    private function extraerItemsDeFrase(string $norm): array
    {
        $cantidad = 1;
        if (preg_match('/\b(\d+)\s+(?:unidades\s+de\s+|uds\s+de\s+|pzs\s+de\s+)?([a-z0-9\-ñ\s]{3,45}?)(?:\s+(?:al|para|cliente|en\s+efectivo|por\s+qr|con\s+tarjeta|con\s+descuento|por\s+\d+)|\s*$)/u', $norm, $m)) {
            $cantidad = max(1, (int) $m[1]);
            return [['producto' => trim($m[2]), 'cantidad' => $cantidad]];
        }

        return [['producto' => $this->extraerFragmentoProductoDeFrase($norm), 'cantidad' => $cantidad]];
    }

    private function extraerFragmentoProductoDeFrase(string $norm): string
    {
        $sinVerbos = preg_replace('/\b(registra|registrar|prepara|preparar|vende|vender|cotiza|cotizar|compra|comprar|aplica|aplicar|merma|perdida|extravio|uso interno|conteo fisico|devolucion danada|ajuste|de|del|la|el|los|las|un|una|por|para|al|factura|proveedor|en|efectivo|qr|tarjeta|mixto|credito|contado|\d+)\b/u', ' ', $norm);
        return trim(preg_replace('/\s+/', ' ', (string) $sinVerbos));
    }

    private function normalizarMetodoPago(string $raw): string
    {
        $n = $this->normalizarTexto($raw);
        if (str_contains($n, 'mixt')) {
            return 'Mixto';
        }
        if (str_contains($n, 'qr') || str_contains($n, 'transf')) {
            return 'Transferencia/QR';
        }
        if (str_contains($n, 'tarj') || str_contains($n, 'deb') || str_contains($n, 'cred')) {
            return 'Tarjeta Débito/Crédito';
        }
        return 'Efectivo';
    }

    private function normalizarTipoAjuste(string $raw): string
    {
        $n = $this->normalizarTexto($raw);
        if (str_contains($n, 'uso') || str_contains($n, 'interno')) {
            return 'Uso Interno';
        }
        if (str_contains($n, 'conteo') || str_contains($n, 'fisico')) {
            return 'Conteo Físico';
        }
        if (str_contains($n, 'devol') || str_contains($n, 'danad')) {
            return 'Devolución Dañada';
        }
        if (str_contains($n, 'perd') || str_contains($n, 'extrav')) {
            return 'Pérdida/Extravío';
        }
        return 'Merma';
    }

    private function traducirErrorSmartDb(string $raw): string
    {
        if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|$)/s', $raw, $m)) {
            $limpio = trim($m[1]);
            $limpio = preg_replace('/^(Regla Smart-DB:|Error Smart-DB:|Operación rechazada:|Ajuste denegado:)\s*/i', '', $limpio);
            return 'Smart-DB rechazó la operación: ' . $limpio;
        }
        if (!str_contains($raw, 'SQLSTATE')) {
            return $raw;
        }
        return 'No se pudo completar la transacción porque se incumplió una regla de integridad en la base de datos (verifica sesión de caja abierta, stock disponible o márgenes mínimos).';
    }

    private function extraerJsonDeRespuesta(string $raw): ?array
    {
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/im', '', trim($raw));
        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{[\s\S]*\}/', $clean, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function respuestaRestriccion(string $msg): array
    {
        return [
            'ok'             => true,
            'tipo'           => 'restriccion',
            'origen'         => 'local',
            'texto_pantalla' => $msg,
            'texto_voz'      => $this->prepararTextoParaVoz($msg),
            'datos_resumen'  => [],
        ];
    }

    private function respuestaInformativa(string $msg, string $origen = 'local'): array
    {
        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => $origen,
            'texto_pantalla' => $msg,
            'texto_voz'      => $this->prepararTextoParaVoz($msg),
            'datos_resumen'  => [],
        ];
    }

    private function etiquetaOperacion(string $op): string
    {
        return match ($op) {
            'preparar_venta'             => 'Registrar Venta en POS',
            'preparar_cotizacion'        => 'Emitir Cotización Formal',
            'preparar_compra'            => 'Registrar Compra a Proveedor',
            'preparar_ajuste_inventario' => 'Registrar Ajuste de Inventario',
            default                      => $op,
        };
    }

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
                if (is_numeric($val) && preg_match('/(precio|monto|total|costo|deuda|saldo|venta|utilidad|ingreso|bs)/i', (string) $col)) {
                    $pares[] = "{$etiqueta}: Bs. " . number_format((float) $val, 2);
                } else {
                    $pares[] = "{$etiqueta}: {$val}";
                }
            }
        }

        $resumenUno = implode(', ', array_slice($pares, 0, 5));
        return trim($explicacion) . " Se obtuvieron {$total} registro(s). Principal resultado: {$resumenUno}.";
    }

    public function prepararTextoParaVoz(string $texto): string
    {
        $limpio = preg_replace('/[\*\#\_`~>]/', '', $texto);
        $limpio = preg_replace('/\bBs\.?\s*/i', 'bolivianos ', $limpio);
        $limpio = preg_replace('/\b(?:c[óo]digo\s+)?OEM\b/iu', 'código O E M', $limpio);
        $limpio = preg_replace('/\bSKU\b/i', 'código interno', $limpio);
        $limpio = preg_replace('/\s+/', ' ', $limpio);
        return trim($limpio);
    }

    private function normalizarTexto(string $texto): string
    {
        $minusculas = mb_strtolower($texto, 'UTF-8');
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        ];
        return strtr($minusculas, $reemplazos);
    }

    private function extraerTerminosSignificativos(string $norm): array
    {
        $limpio = preg_replace('/[^a-z0-9\-ñ\s]/u', ' ', $norm);
        $partes = preg_split('/\s+/', trim((string) $limpio)) ?: [];

        $stopwords = [
            'que', 'cual', 'cuales', 'como', 'donde', 'cuanto', 'cuantos', 'cuantas',
            'hay', 'tiene', 'tienen', 'tenemos', 'mostrar', 'buscar', 'busca', 'dime',
            'consultar', 'consulta', 'ver', 'lista', 'listar', 'del', 'de', 'la', 'el',
            'los', 'las', 'un', 'una', 'unos', 'unas', 'para', 'por', 'con', 'en', 'al',
            'le', 'hacen', 'hace', 'sirve', 'sirven', 'repuesto', 'repuestos', 'pieza',
            'piezas', 'stock', 'ubicacion', 'precio', 'cuesta', 'vale', 'bodega',
            'almacen', 'pasillo', 'estante', 'gaveta', 'disponible', 'disponibles',
            'vehiculo', 'auto', 'camioneta', 'carro', 'compatible', 'compatibles',
            'favor', 'quiero', 'saber', 'necesito', 'esta', 'estan',
        ];

        $terminos = [];
        foreach ($partes as $p) {
            if (mb_strlen($p) >= 3 && !in_array($p, $stopwords, true)) {
                $terminos[] = $p;
            }
        }

        return array_values(array_unique($terminos));
    }

    private function singularizar(string $palabra): string
    {
        if (str_ends_with($palabra, 'dores')) {
            return substr($palabra, 0, -2);
        }
        if (str_ends_with($palabra, 'ones')) {
            return substr($palabra, 0, -2);
        }
        if (str_ends_with($palabra, 'as') || str_ends_with($palabra, 'os')) {
            return substr($palabra, 0, -1);
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
