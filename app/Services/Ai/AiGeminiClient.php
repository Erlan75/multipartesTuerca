<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para Google Gemini con cascada automática de modelos vigentes,
 * diagnóstico claro en logs, function declarations multi-slot y Text-to-SQL (CU39).
 */
class AiGeminiClient
{
    private const MODELOS_RETIRADOS = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-2.5-flash-lite',
        'gemini-1.5-flash',
        'gemini-1.5-pro',
    ];

    public function __construct(
        private readonly AiTextNormalizer $text
    ) {}

    /**
     * Consulta la API de Gemini con historial multi-turno y herramientas transaccionales / Text-to-SQL.
     * Retorna una estructura tipada ('function_call' | 'sql' | 'texto') o null para activar respaldo local.
     */
    public function solicitarRespuesta(
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $apiKey,
        string $nombreRol,
        array $historial
    ): ?array {
        if (trim($apiKey) === '') {
            Log::warning('AiGeminiClient: GEMINI_API_KEY no configurada; usando respaldo local.');
            return null;
        }

        $modelos = $this->resolverListaModelosCandidatos();
        if (empty($modelos)) {
            Log::warning('AiGeminiClient: Todos los modelos Gemini candidatos están temporalmente omitidos en caché (404); usando respaldo local.');
            return null;
        }

        $body = [
            'system_instruction' => [
                'parts' => [['text' => $this->construirSystemPrompt()]],
            ],
            'tools' => [
                ['functionDeclarations' => $this->construirFunctionDeclarations()],
            ],
            'contents' => $this->construirContentsMultiTurno($preguntaOriginal, $preguntaLimpia, $nombreRol, $historial),
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 1024,
            ],
        ];

        $responseJson = null;
        foreach ($modelos as $modelo) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent";
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type'   => 'application/json',
                    ])
                    ->post($endpoint, $body);

                if ($response->successful()) {
                    $responseJson = $response->json();
                    break;
                }

                $status = $response->status();
                $errMsg = (string) ($response->json('error.message') ?? preg_replace('/\s+/', ' ', mb_substr((string) $response->body(), 0, 260)));
                Log::warning("AiGeminiClient: modelo {$modelo} devolvió HTTP {$status}: {$errMsg}");

                if ($status === 404) {
                    Cache::put("ai_gemini_404_{$modelo}", true, 3600);
                    continue;
                }

                if ($status === 401 || $status === 403) {
                    Log::warning("AiGeminiClient: clave Gemini inválida o sin permisos (HTTP {$status}). Revisa GEMINI_API_KEY.");
                    break;
                }

                // 429 (cuota) o 5xx (sobrecarga): probar siguiente modelo de respaldo
            } catch (\Throwable $e) {
                Log::warning("AiGeminiClient: Excepción de conexión con Gemini {$modelo}: " . $e->getMessage());
            }
        }

        if (!is_array($responseJson)) {
            return null;
        }

        $parts = $responseJson['candidates'][0]['content']['parts'] ?? [];
        if (empty($parts)) {
            return null;
        }

        // 1. Function Call -> Text-to-SQL directo o máquina de estados transaccional
        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                $fnName = (string) $part['functionCall']['name'];
                $args = is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [];

                if ($fnName === 'consultar_base_datos') {
                    $sql = rtrim(trim((string) ($args['sql'] ?? '')), "; \t\n\r\0\x0B");
                    if ($sql !== '') {
                        return [
                            'tipo'        => 'sql',
                            'sql'         => $sql,
                            'explicacion' => (string) ($args['explicacion'] ?? 'Aquí tienes lo que encontré:'),
                        ];
                    }
                    return null;
                }

                return [
                    'tipo' => 'function_call',
                    'name' => $fnName,
                    'args' => $args,
                ];
            }
        }

        $rawText = '';
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $rawText .= $part['text'];
            }
        }
        $rawText = trim($rawText);
        if ($rawText === '') {
            return null;
        }

        // 2. Consulta analítica SQL read-only en formato JSON
        $parsed = $this->text->extraerJsonDeRespuesta($rawText);
        if (is_array($parsed) && !empty($parsed['sql'])) {
            return [
                'tipo'        => 'sql',
                'sql'         => rtrim(trim((string) $parsed['sql']), "; \t\n\r\0\x0B"),
                'explicacion' => (string) ($parsed['explicacion'] ?? 'Aquí tienes lo que encontré:'),
            ];
        }

        if (is_array($parsed) && !empty($parsed['respuesta'])) {
            return [
                'tipo'  => 'texto',
                'texto' => $this->text->limpiarTecnicismosDeRespuesta((string) $parsed['respuesta']),
            ];
        }

        if (!str_contains(strtoupper($rawText), 'SELECT ') && !str_contains($rawText, '{')) {
            return [
                'tipo'  => 'texto',
                'texto' => $this->text->limpiarTecnicismosDeRespuesta($rawText),
            ];
        }

        return null;
    }

    private function resolverListaModelosCandidatos(): array
    {
        $configured = trim((string) config('services.gemini.model', 'gemini-3.5-flash'));
        $fallbackConfigured = trim((string) config('services.gemini.fallback_model', 'gemini-3.5-flash-lite'));
        $fallbacks = config('services.gemini.fallback_models', [
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-flash-latest',
            'gemini-3.8-flash',
        ]);
        if (!is_array($fallbacks)) {
            $fallbacks = ['gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-flash-latest', 'gemini-3.8-flash'];
        }

        $lista = in_array($configured, self::MODELOS_RETIRADOS, true)
            ? array_merge($fallbacks, [$fallbackConfigured])
            : array_merge([$configured, $fallbackConfigured], $fallbacks);

        $unicos = array_values(array_unique(array_filter($lista, fn($m) => is_string($m) && trim($m) !== '')));
        $activos = [];
        foreach ($unicos as $modelo) {
            if (in_array($modelo, self::MODELOS_RETIRADOS, true)) {
                continue;
            }
            if (!Cache::get("ai_gemini_404_{$modelo}")) {
                $activos[] = $modelo;
            }
        }

        return $activos;
    }

    public function construirContentsMultiTurno(
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $nombreRol,
        array $historial
    ): array {
        $contents = [];
        foreach ($historial as $msg) {
            $role = ($msg['role'] ?? 'user') === 'model' ? 'model' : 'user';
            $text = trim((string) ($msg['text'] ?? ''));
            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }

        $ctx = "Rol actual: {$nombreRol}. Fecha de hoy: " . Carbon::today()->toDateString() . '.';
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => "[Contexto: {$ctx}]\nMensaje del usuario: {$preguntaLimpia}" . ($preguntaLimpia !== $preguntaOriginal ? " (original: {$preguntaOriginal})" : '')]],
        ];

        return $contents;
    }

    public function construirFunctionDeclarations(): array
    {
        return [
            [
                'name'        => 'consultar_base_datos',
                'description' => 'Ejecuta una consulta PostgreSQL de solo lectura (SELECT o WITH) sobre las 19 tablas de Multipartes El Tuerca para responder cualquier pregunta de conteo, listado, totales, saldos, compatibilidad o búsqueda.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'sql'         => ['type' => 'STRING', 'description' => 'Consulta SQL SELECT o WITH válida para PostgreSQL 16, usando ILIKE, sin punto y coma final y jamás leyendo password_hash'],
                        'explicacion' => ['type' => 'STRING', 'description' => 'Frase breve y natural en español explicando lo encontrado (sin tecnicismos ni SQL)'],
                    ],
                    'required' => ['sql', 'explicacion'],
                ],
            ],
            [
                'name'        => 'preparar_venta',
                'description' => 'Inicia o actualiza el registro de una venta en mostrador extrayendo TODOS los datos presentes en la frase del usuario desde el primer mensaje.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente'              => ['type' => 'STRING', 'description' => 'Nombre exacto del cliente o taller tal como lo dijo el usuario (incluyendo nombres nuevos que aún no estén en BD)'],
                        'items'                => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto' => ['type' => 'STRING'],
                                    'cantidad' => ['type' => 'INTEGER'],
                                ],
                                'required' => ['producto'],
                            ],
                        ],
                        'descuento_total'      => ['type' => 'NUMBER', 'description' => 'Monto fijo de descuento en Bs. si el usuario lo dio en dinero'],
                        'descuento_porcentaje' => ['type' => 'NUMBER', 'description' => 'Porcentaje de descuento (0 a 50) si el usuario dijo por ejemplo "descuento del 10%"'],
                        'sin_descuento'        => ['type' => 'BOOLEAN', 'description' => 'True si el usuario aclaró sin descuento o precio normal'],
                        'metodo_pago'          => ['type' => 'STRING', 'description' => 'Solo si el usuario lo mencionó: Efectivo, Transferencia/QR, Tarjeta Débito/Crédito o Mixto'],
                        'monto_recibido'       => ['type' => 'NUMBER', 'description' => 'Con cuánto pagó el cliente si pagó en efectivo'],
                        'nro_referencia'       => ['type' => 'STRING', 'description' => 'Número de referencia o comprobante si pagó por QR/tarjeta'],
                        'sin_mas_productos'    => ['type' => 'BOOLEAN', 'description' => 'True si dijo "y nada más", "solo eso" o "eso es todo"'],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_cotizacion',
                'description' => 'Inicia o actualiza una cotización o proforma comercial con todos los datos presentes en el mensaje.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente'           => ['type' => 'STRING'],
                        'dias_vigencia'     => ['type' => 'INTEGER'],
                        'sin_mas_productos' => ['type' => 'BOOLEAN'],
                        'items'             => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto' => ['type' => 'STRING'],
                                    'cantidad' => ['type' => 'INTEGER'],
                                ],
                                'required' => ['producto'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_compra',
                'description' => 'Inicia o actualiza el registro de una compra/recepción de mercadería a proveedor.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'proveedor'           => ['type' => 'STRING'],
                        'numero_factura_nota' => ['type' => 'STRING'],
                        'tipo_pago'           => ['type' => 'STRING'],
                        'items'               => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto'       => ['type' => 'STRING'],
                                    'cantidad'       => ['type' => 'INTEGER'],
                                    'costo_unitario' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['producto'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_ajuste_inventario',
                'description' => 'Inicia o actualiza un ajuste de inventario (merma, uso interno, pérdida, devolución dañada o conteo físico).',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'producto'          => ['type' => 'STRING'],
                        'cantidad_ajustada' => ['type' => 'INTEGER'],
                        'tipo_ajuste'       => ['type' => 'STRING'],
                        'motivo'            => ['type' => 'STRING'],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_cliente',
                'description' => 'Registra un nuevo cliente particular, taller mecánico o empresa/flota en la cartera comercial.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'nombre_razon_social' => ['type' => 'STRING'],
                        'tipo_cliente'        => ['type' => 'STRING', 'description' => 'Particular, Taller/Mecánico o Empresa/Flota'],
                        'ci_nit'              => ['type' => 'STRING'],
                        'telefono'            => ['type' => 'STRING'],
                        'descuento_frecuente' => ['type' => 'NUMBER'],
                    ],
                ],
            ],
        ];
    }

    /**
     * System Prompt con conocimiento integral de las 19 tablas + mapa funcional completo de la plataforma.
     */
    public function construirSystemPrompt(): string
    {
        return <<<PROMPT
Eres el colaborador de confianza en el mostrador de "Multipartes El Tuerca" (Santa Cruz de la Sierra, Bolivia).
Tu dueño no está acostumbrado a sistemas informáticos complejos: háblale siempre con calidez, paciencia, respeto y palabras cotidianas (como un amigo que trabaja a su lado).
La moneda oficial es Bolivianos (Bs.).

PROHIBIDO USAR TECNICISMOS: Nunca menciones SQL, PostgreSQL, tablas, columnas, JSON, arrays, IDs internos, constraints, triggers ni códigos de error.

REGLAS DE ORO PARA RESPONDER:
1) CONSULTAS SOBRE DATOS DEL NEGOCIO (conteos, cantidades, listados, usuarios por rol, productos, categorías, vehículos, clientes, proveedores, ventas, compras, caja, auditoría, reportes):
   - SIEMPRE invoca la función `consultar_base_datos` con una sentencia PostgreSQL 16 `SELECT` o `WITH` válida (sin `;` final, usando `ILIKE` y límite máximo de 50 filas si no es agregación escalar).
   - Nunca inventes conteos ni nombres: consúltalos siempre desde la BD.
2) OPERACIONES COMERCIALES (venta, cotización, compra, ajuste/merma o registrar cliente nuevo):
   - INVOCA la función declarada correspondiente extrayendo TODOS los datos presentes en la frase del usuario desde el primer mensaje (producto, cantidad, cliente incluso si es un nombre nuevo no registrado aún, descuento en porcentaje o Bs., forma de pago, etc.).
   - Jamás descartes un nombre de cliente aunque parezca nuevo.
3) PREGUNTAS SOBRE CÓMO USAR LA PLATAFORMA O CHARLA GENERAL:
   - Responde directamente en texto cálido y breve (2-4 líneas).

MAPA FUNCIONAL COMPLETO DE LA WEB "MULTIPARTES EL TUERCA":
- Panel Principal (Dashboard): resumen ejecutivo y exportación PDF/Excel.
- Seguridad y Acceso (solo Administrador): Usuarios (/usuarios), Roles (/roles: Administrador, Bodeguero, Vendedor, Cajero y Cliente), Bitácora (/auditoria).
- Catálogo y Compatibilidad: Productos (/productos), Categorías (/categorias), Vehículos (/vehiculos).
- Almacén e Inventario: Alertas (/inventario/alertas) y Ajustes/Mermas/Uso Interno (/inventario/ajustes).
- Compras y Proveedores: Proveedores (/proveedores), Compras (/compras), Cuentas por Pagar (/compras/cuentas/por-pagar) e Historial de Costos (/compras/historial/costos).
- Gestión Comercial y POS: Clientes (/clientes), Cotizaciones (/cotizaciones), Terminal POS (/ventas/pos) e Historial de Ventas (/ventas) con Ticket 80mm y PDF.
- Tesorería y Caja (/caja): apertura, movimientos menores y arqueo físico de cierre.
- Reportes Analíticos con Chart.js y exportación PDF/Excel: Rotación (/reportes/rotacion), Rentabilidad (/reportes/rentabilidad), Compras por Distribuidor (/reportes/compras-distribuidor) y Balance por Medios de Pago (/reportes/balance-medios-pago).

ESQUEMA POSTGRESQL (19 TABLAS — SOLO PARA SELECT/WITH):
1. roles (id_rol, nombre_rol, descripcion)
2. usuarios (id_usuario, id_rol, nombre_completo, username, correo, telefono, estado, created_at) -- JAMÁS password_hash
3. categorias (id_categoria, nombre, descripcion)
4. productos (id_producto, id_categoria, codigo_interno, codigo_oem, nombre_pieza, marca_fabricante, procedencia, precio_venta_sugerido, margen_ganancia, unidad_medida, stock_actual, stock_minimo, stock_seguridad, pasillo, estante, nivel_gaveta, imagen_url, estado)
5. codigos_alternativos (id_codigo_alt, id_producto, codigo_referencia, marca_alternativa)
6. vehiculos (id_vehiculo, marca, modelo, anio_inicio, anio_fin, motor_cilindrada, combustible)
7. compatibilidades (id_compatibilidad, id_producto, id_vehiculo, notas_posicion)
8. ajustes_inventario (id_ajuste, id_producto, id_usuario, cantidad_ajustada, tipo_ajuste, motivo, fecha_ajuste)
9. proveedores (id_proveedor, razon_social, nit, contacto_nombre, telefono, correo, direccion, condicion_comercial, estado)
10. notas_compras (id_compra, id_proveedor, id_usuario_receptor, numero_factura_nota, fecha_compra, monto_total, tipo_pago, estado_pago)
11. detalles_compras (id_detalle_compra, id_compra, id_producto, cantidad_recibida, costo_unitario, subtotal)
12. sesiones_cajas (id_sesion, id_usuario_cajero, fecha_hora_apertura, monto_inicial, fecha_hora_cierre, monto_esperado_sistema, monto_real_contado, diferencia, estado)
13. movimientos_cajas (id_movimiento, id_sesion, tipo, monto, motivo, hora)
14. clientes (id_cliente, nombre_razon_social, ci_nit, telefono, tipo_cliente, descuento_frecuente)
15. cotizaciones (id_cotizacion, id_cliente, id_usuario, fecha_emision, fecha_vencimiento, monto_total, estado)
16. detalles_cotizaciones (id_detalle_cot, id_cotizacion, id_producto, cantidad, precio_unitario_cotizado, subtotal)
17. notas_ventas (id_venta, id_cliente, id_usuario_vendedor, id_sesion_caja, fecha_hora, total_venta, descuento_total, metodo_pago, estado, motivo_anulacion)
18. detalles_ventas (id_detalle_venta, id_venta, id_producto, cantidad_vendida, precio_unitario_cobrado, subtotal)
19. bitacoras_auditorias (id_bitacora, id_usuario, fecha_hora, modulo_afectado, accion, registro_id, detalle_cambio, ip_origen)

FUNCIONES ANALÍTICAS: fn_reporte_rotacion_repuestos, fn_reporte_rentabilidad, fn_reporte_compras_distribuidor, fn_reporte_balance_medios_pago, fn_obtener_metricas_pos.
Solo SELECT o WITH de solo lectura, sin punto y coma final, máximo LIMIT 50, usa ILIKE para búsquedas, jamás password_hash.
PROMPT;
    }
}
