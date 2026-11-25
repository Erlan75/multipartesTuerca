<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para Google Gemini (gemini-2.5-flash con respaldo gemini-2.0-flash),
 * systemInstruction con esquema completo de las 19 tablas y function declarations (CU39).
 */
class AiGeminiClient
{
    public function __construct(
        private readonly AiTextNormalizer $text
    ) {}

    /**
     * Consulta la API de Gemini con historial multi-turno y herramientas transaccionales.
     * Retorna una estructura tipada ('function_call' | 'sql' | 'texto') o null para activar respaldo local.
     */
    public function solicitarRespuesta(
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $apiKey,
        string $nombreRol,
        array $historial
    ): ?array {
        $modelos = array_values(array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            config('services.gemini.fallback_model', 'gemini-2.0-flash'),
        ])));

        $body = [
            'system_instruction' => [
                'parts' => [['text' => $this->construirSystemPrompt()]],
            ],
            'tools' => [
                ['functionDeclarations' => $this->construirFunctionDeclarations()],
            ],
            'contents' => $this->construirContentsMultiTurno($preguntaOriginal, $preguntaLimpia, $nombreRol, $historial),
            'generationConfig' => [
                'temperature'     => 0.25,
                'maxOutputTokens' => 800,
            ],
        ];

        $responseJson = null;
        foreach ($modelos as $modelo) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent";
            try {
                $response = Http::timeout(6.5)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type'   => 'application/json',
                    ])
                    ->post($endpoint, $body);

                if ($response->successful()) {
                    $responseJson = $response->json();
                    break;
                }
                Log::warning("AiGeminiClient: Gemini modelo {$modelo} devolvió HTTP {$response->status()}: " . mb_substr((string) $response->body(), 0, 220));
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

        // 1. Function Call -> será validado por la máquina de estados local
        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                return [
                    'tipo' => 'function_call',
                    'name' => (string) $part['functionCall']['name'],
                    'args' => is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [],
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

        // 2. Consulta analítica SQL read-only
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
                'name'        => 'preparar_venta',
                'description' => 'Inicia o actualiza el registro de una venta en mostrador. Invócala incluso con datos parciales para que el flujo pregunte lo faltante paso a paso.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente'         => ['type' => 'STRING', 'description' => 'Nombre del cliente/taller solo si el usuario lo mencionó'],
                        'items'           => [
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
                        'descuento_total' => ['type' => 'NUMBER', 'description' => 'Solo si el usuario pidió un descuento explícito en Bs.'],
                        'metodo_pago'     => ['type' => 'STRING', 'description' => 'Solo si el usuario lo mencionó: Efectivo, Transferencia/QR, Tarjeta Débito/Crédito o Mixto'],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_cotizacion',
                'description' => 'Inicia o actualiza una cotización o proforma comercial.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente'       => ['type' => 'STRING'],
                        'dias_vigencia' => ['type' => 'INTEGER'],
                        'items'         => [
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

MAPA FUNCIONAL COMPLETO DE LA WEB "MULTIPARTES EL TUERCA":
- Panel Principal (Dashboard): muestra resumen ejecutivo y permite exportar catálogo activo a PDF y Excel.
- Seguridad y Acceso (solo Administrador):
  * Usuarios (/usuarios): alta, edición y activación/desactivación de empleados.
  * Roles (/roles): 5 perfiles oficiales — Administrador (control total), Bodeguero (catálogo, almacén, alertas, ajustes, proveedores, compras, rotación y compras por distribuidor), Vendedor (clientes, cotizaciones, ventas/POS y consulta de catálogo), Cajero (apertura/cierre/arqueo de caja, ventas/POS, cotizaciones, cuentas por pagar y balance por medios de pago) y Cliente Web (vitrina pública, carrito en línea, sus cotizaciones y descarga PDF de sus comprobantes).
  * Bitácora de Auditoría (/auditoria): historial de todas las acciones importantes.
- Catálogo y Compatibilidad: Productos/Repuestos (/productos, con OEM, códigos alternativos, precio sugerido, stock y pasillo/estante/gaveta), Categorías (/categorias) y Catálogo Vehicular (/vehiculos).
- Almacén e Inventario: Alertas de Stock Crítico (/inventario/alertas) y Ajustes/Mermas/Uso Interno (/inventario/ajustes).
- Compras y Proveedores: Proveedores (/proveedores), Recepción de Compras (/compras), Cuentas por Pagar a Crédito (/compras/cuentas/por-pagar) e Historial de Costos (/compras/historial/costos).
- Gestión Comercial y POS: Clientes y Talleres (/clientes), Cotizaciones/Proformas (/cotizaciones, con conversión directa a venta), Terminal POS (/ventas/pos) e Historial de Ventas (/ventas) con Ticket Térmico 80mm y PDF descargable.
- Tesorería y Caja (/caja): apertura de turno con fondo inicial, ingresos extra / retiros menores, y cierre con arqueo físico de billetes y monedas.
- Reportes Analíticos con gráficos interactivos y exportación PDF/Excel: Rotación de Repuestos (/reportes/rotacion), Rentabilidad y Márgenes (/reportes/rentabilidad), Compras por Distribuidor (/reportes/compras-distribuidor) y Balance por Medios de Pago (/reportes/balance-medios-pago). Todos los listados del sistema tienen botones Exportar PDF y Exportar Excel arriba a la derecha.

MODOS DE RESPUESTA:
1) OPERACIONES (venta, cotización, compra, ajuste/merma o registrar cliente nuevo): INVOCA la función declarada correspondiente con los datos que el usuario mencionó (no inventes campos que aún no dijo).
2) CONSULTAS DE DATOS DE LA BASE DE DATOS: responde EXCLUSIVAMENTE JSON válido:
   {"sql": "SELECT ...", "explicacion": "Respuesta clara y cercana en español (Bs. si aplica)"}
3) PREGUNTAS SOBRE CÓMO USAR EL SISTEMA O CHARLA GENERAL: responde directamente en texto cálido y breve (2-4 líneas) sin JSON ni SQL.

ESQUEMA POSTGRESQL (19 TABLAS — SOLO PARA SELECT/WITH):
1. roles (id_rol, nombre_rol, descripcion)
2. usuarios (id_usuario, id_rol, nombre_completo, username, correo, telefono, estado, created_at) -- NUNCA password_hash
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
Solo SELECT o WITH de solo lectura, sin punto y coma final, máximo LIMIT 12, usa ILIKE para búsquedas, jamás password_hash.
PROMPT;
    }
}
