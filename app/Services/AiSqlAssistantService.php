<?php

namespace App\Services;

use App\Helpers\Bitacora;
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
        1 => ['preparar_venta', 'preparar_cotizacion', 'preparar_compra', 'preparar_ajuste_inventario', 'preparar_cliente'],
        2 => ['preparar_compra', 'preparar_ajuste_inventario'],
        3 => ['preparar_venta', 'preparar_cotizacion', 'preparar_cliente'],
        4 => ['preparar_venta', 'preparar_cotizacion', 'preparar_cliente'],
    ];

    private const SESSION_HISTORY_KEY   = 'ai_chat_history';
    private const SESSION_DRAFT_KEY     = 'ai_tx_draft';
    private const SESSION_CANCELLED_KEY = 'ai_last_cancelled_draft';
    private const MAX_HISTORY_TURNS     = 8;

    /**
     * Punto de entrada principal conversacional del Asistente IA (CU39).
     */
    public function procesarConsulta(string $pregunta, ?object $usuario = null): array
    {
        $preguntaOriginal = trim($pregunta);
        $preguntaLimpia = $this->limpiarMuletillasVoz($preguntaOriginal);
        $norm = $this->normalizarFonetica($this->normalizarTexto($preguntaLimpia));

        $rolId = (int) ($usuario->id_rol ?? 1);
        $nombreRol = $usuario->rol->nombre_rol ?? $this->obtenerNombreRol($rolId);
        $userId = (int) ($usuario->id_usuario ?? 1);

        // 0. Comandos explícitos de cancelación o reinicio
        if ($this->esComandoCancelacion($norm)) {
            $reinicioTotal = (bool) preg_match('/\b(empezar de nuevo|nueva charla|reiniciar|borrar todo|desde cero)\b/', $norm);
            if ($reinicioTotal) {
                $this->limpiarConversacionCompleta();
            } else {
                $this->limpiarBorradorOperacion();
            }
            $msg = '¡Listo, sin problema! Dejamos eso a un lado. ¿En qué te ayudo ahora?';
            $resp = $this->respuestaInformativa($msg, 'local');
            $this->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        // Frases vacías o puro ruido de micrófono
        if ($this->esFraseVaciaORuido($norm)) {
            $msg = 'Perdón, no te entendí bien esa parte. ¿Me lo puedes repetir con otras palabras?';
            $resp = $this->respuestaAclaracion($msg, 'local');
            $this->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        // Recuperar borrador activo (15 min)
        $draft = $this->obtenerBorradorActivo($userId);

        // 1. Recuperación inteligente tras cancelar una tarjeta de confirmación (5 min)
        if ($draft === null) {
            $cancelado = $this->obtenerBorradorCancelado($userId);
            if ($cancelado !== null && $this->esModificacionDeBorradorCancelado($norm, $cancelado)) {
                session()->forget(self::SESSION_CANCELLED_KEY);
                $respEdit = $this->aplicarEdicionSobreBorradorRecuperado(
                    $cancelado,
                    $preguntaOriginal,
                    $preguntaLimpia,
                    $norm,
                    $rolId,
                    $nombreRol,
                    $userId
                );
                $this->registrarTurnoHistorial($preguntaOriginal, $respEdit['texto_pantalla'] ?? '');
                return $respEdit;
            }
        }

        // Saludos o agradecimientos cuando no hay borrador activo
        if (!$draft && $this->esSaludoOConversacionBasica($norm)) {
            $resp = $this->responderSaludoAmigable($norm, $usuario);
            $this->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        // Si hay borrador activo y el usuario cambia claramente a una consulta general/analítica
        if ($draft && $this->esConsultaGeneralOAnaliticaExplicita($norm) && !$this->esRespuestaAlBorradorActual($draft, $norm)) {
            $this->limpiarBorradorOperacion();
            $draft = null;
        }

        // 2. Continuar borrador conversacional en curso (máquina de estados por campo + subflujo producto nuevo)
        if ($draft !== null) {
            $respDraft = $this->continuarBorradorConversacional(
                $draft,
                $preguntaOriginal,
                $preguntaLimpia,
                $norm,
                $rolId,
                $nombreRol,
                $userId
            );
            $this->registrarTurnoHistorial($preguntaOriginal, $respDraft['texto_pantalla'] ?? '');
            return $respDraft;
        }

        // 3. Detectar intención de operación transaccional (venta, cotización, compra, ajuste, cliente)
        $operacionDetectada = $this->detectarOperacionTransaccional($norm);
        $esIntencionEscritura = $operacionDetectada !== null;

        // 4. Restricciones preventivas por rol para consultas analíticas
        if (!$esIntencionEscritura) {
            $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
            if ($bloqueoRol !== null) {
                $this->registrarTurnoHistorial($preguntaOriginal, $bloqueoRol['texto_pantalla']);
                return $bloqueoRol;
            }
        }

        // 5. Vía A: Motor Google Gemini (gemini-2.5-flash -> fallback gemini-2.0-flash) con historial multi-turno
        $apiKey = config('services.gemini.key');
        if (!empty($apiKey)) {
            try {
                $resultadoGemini = $this->consultarViaGemini(
                    $preguntaOriginal,
                    $preguntaLimpia,
                    $apiKey,
                    $rolId,
                    $nombreRol,
                    $userId,
                    null
                );
                if ($resultadoGemini !== null) {
                    $this->registrarTurnoHistorial($preguntaOriginal, $resultadoGemini['texto_pantalla'] ?? '');
                    return $resultadoGemini;
                }
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService: Conmutando a motor local tras excepción en Gemini: ' . $e->getMessage());
            }
        }

        // 6. Vía B (Transaccional local con máquina de estados completa)
        if ($esIntencionEscritura) {
            $resultadoLocalTx = $this->iniciarBorradorDesdeLocal(
                $operacionDetectada,
                $preguntaOriginal,
                $preguntaLimpia,
                $norm,
                $rolId,
                $nombreRol,
                $userId
            );
            if ($resultadoLocalTx !== null) {
                $this->registrarTurnoHistorial($preguntaOriginal, $resultadoLocalTx['texto_pantalla'] ?? '');
                return $resultadoLocalTx;
            }
        }

        // 7. Restricción por rol antes del motor local analítico
        $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
        if ($bloqueoRol !== null) {
            $this->registrarTurnoHistorial($preguntaOriginal, $bloqueoRol['texto_pantalla']);
            return $bloqueoRol;
        }

        // 8. Vía B (Conocimiento del sistema + consultas sobre todas las tablas)
        $respLocal = $this->consultarViaMotorLocal($preguntaLimpia, $norm, $rolId, $nombreRol);
        $this->registrarTurnoHistorial($preguntaOriginal, $respLocal['texto_pantalla'] ?? '');
        return $respLocal;
    }

    public function limpiarBorradorOperacion(): void
    {
        session()->forget(self::SESSION_DRAFT_KEY);
    }

    public function limpiarConversacionCompleta(): void
    {
        session()->forget([self::SESSION_HISTORY_KEY, self::SESSION_DRAFT_KEY, self::SESSION_CANCELLED_KEY]);
    }

    /**
     * Conserva el borrador cancelado durante 5 minutos para permitir ediciones rápidas.
     */
    public function guardarBorradorCancelado(array $payload): void
    {
        $snapshot = $payload['draft_snapshot'] ?? null;
        if (is_array($snapshot)) {
            $snapshot['cancelled_at'] = now()->timestamp;
            $snapshot['esperando'] = null;
            session()->put(self::SESSION_CANCELLED_KEY, $snapshot);
        }
    }

    private function obtenerBorradorCancelado(int $userId): ?array
    {
        $cancelado = session()->get(self::SESSION_CANCELLED_KEY);
        if (!is_array($cancelado)) {
            return null;
        }
        if ((int) ($cancelado['user_id'] ?? 0) !== $userId) {
            return null;
        }
        // Expirar tras 5 minutos (300 segundos)
        if ((now()->timestamp - (int) ($cancelado['cancelled_at'] ?? 0)) > 300) {
            session()->forget(self::SESSION_CANCELLED_KEY);
            return null;
        }
        return $cancelado;
    }

    /**
     * Limpia muletillas de voz frecuentes y puntuación errática de la Web Speech API.
     */
    public function limpiarMuletillasVoz(string $texto): string
    {
        $limpio = trim($texto);
        $limpio = (string) preg_replace('/[“”"\'´`]+/u', ' ', $limpio);
        $limpio = (string) preg_replace('/\.{2,}|,{2,}|!{2,}|\?{2,}/u', ' ', $limpio);

        $muletillas = [
            'hazme un favor', 'haceme un favor', 'por favor', 'porfa', 'quiero que',
            'o sea', 'osea', 'a ver', 'aver', 'como se dice', 'asi nomas', 'así nomás',
            'hermanito', 'hermano', 'disculpa', 'perdón', 'perdon', 'escucha',
            'digamos', 'bueno', 'pucha', 'pues', 'este', 'mira', 'oye', 'che',
            'ehhh', 'ehh', 'eh', 'mmmm', 'mmm', 'mm',
        ];

        foreach ($muletillas as $m) {
            $patron = '/(?<=^|[\s,.;:!?¿¡])' . preg_quote($m, '/') . '(?=$|[\s,.;:!?¿¡])/iu';
            $limpio = (string) preg_replace($patron, ' ', $limpio);
        }

        $limpio = (string) preg_replace('/\s*[,;]+\s*/u', ' ', $limpio);
        $limpio = (string) preg_replace('/\s+/u', ' ', $limpio);
        $limpio = trim($limpio, " \t\n\r\0\x0B,.;:");

        return $limpio !== '' ? $limpio : trim($texto);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PASO 2: EJECUCIÓN CONFIRMADA EN POSTGRESQL (5 OPERACIONES)
    // ═════════════════════════════════════════════════════════════════════════

    public function ejecutarAccionConfirmada(array $payload, int $userId, int $rolId, string $nombreRol, string $ipOrigen): array
    {
        $operacion = $payload['operacion'] ?? '';
        $this->limpiarBorradorOperacion();
        session()->forget(self::SESSION_CANCELLED_KEY);

        if (!$this->rolPuedeEjecutarOperacion($rolId, $operacion)) {
            $msg = "Tu cuenta de {$nombreRol} no tiene permiso para completar esta operación. Si la necesitas, pídele apoyo al administrador.";
            return [
                'ok'             => false,
                'tipo'           => 'restriccion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $this->prepararTextoParaVoz($msg),
                'datos_resumen'  => [],
            ];
        }

        try {
            DB::statement("SELECT set_config('app.id_usuario', ?, false)", [(string) $userId]);
            DB::statement("SELECT set_config('app.ip_origen', ?, false)", [$ipOrigen ?: '127.0.0.1']);

            $res = match ($operacion) {
                'preparar_venta'             => $this->ejecutarVentaConfirmada($payload, $userId),
                'preparar_cotizacion'        => $this->ejecutarCotizacionConfirmada($payload, $userId),
                'preparar_compra'            => $this->ejecutarCompraConfirmada($payload, $userId),
                'preparar_ajuste_inventario' => $this->ejecutarAjusteConfirmado($payload, $userId),
                'preparar_cliente'           => $this->ejecutarClienteConfirmado($payload, $userId),
                default                      => [
                    'ok'             => false,
                    'tipo'           => 'error',
                    'origen'         => 'local',
                    'texto_pantalla' => 'No reconocí esa operación para guardarla. ¿La armamos de nuevo?',
                    'texto_voz'      => 'No reconocí esa operación para guardarla.',
                    'datos_resumen'  => [],
                ],
            };

            $this->registrarTurnoHistorial('[Confirmación de operación]', $res['texto_pantalla'] ?? '');
            return $res;
        } catch (\Throwable $e) {
            Log::warning('AiSqlAssistantService (Confirmación): ' . $e->getMessage());
            $traducido = $this->traducirErrorAmigable($e->getMessage());
            return [
                'ok'                 => false,
                'tipo'               => 'error',
                'origen'             => 'local',
                'texto_pantalla'     => $traducido['mensaje'],
                'texto_voz'          => $this->prepararTextoParaVoz($traducido['mensaje']),
                'enlace_comprobante' => $traducido['enlace'] ?? null,
                'etiqueta_enlace'    => $traducido['etiqueta'] ?? null,
                'datos_resumen'      => [],
            ];
        }
    }

    private function ejecutarVentaConfirmada(array $payload, int $userId): array
    {
        $idSesion = (int) ($payload['id_sesion_caja'] ?? 0);
        if ($idSesion <= 0) {
            $sesion = DB::table('sesiones_cajas')->where('estado', 'Abierta')->orderByDesc('id_sesion')->first();
            if (!$sesion) {
                throw new \RuntimeException('CAJA_CERRADA');
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
        $montoRecibido = (float) ($payload['monto_recibido'] ?? $totalNeto);
        $vuelto = $metodoPago === 'Efectivo' ? round(max(0.0, $montoRecibido - $totalNeto), 2) : 0.0;
        $referencia = $payload['nro_referencia'] ?? null;
        $montoEfec = (float) ($payload['monto_efectivo'] ?? ($metodoPago === 'Efectivo' ? $totalNeto : 0.0));
        $montoDig  = (float) ($payload['monto_digital'] ?? ($metodoPago !== 'Efectivo' ? $totalNeto : 0.0));

        $detallesPago = [
            'origen'          => 'ASISTENTE_IA_CONFIRMADO',
            'metodo_pago'     => $metodoPago,
            'monto_recibido'  => $montoRecibido,
            'cambio_devuelto' => $vuelto,
            'nro_referencia'  => $referencia,
            'monto_efectivo'  => $montoEfec,
            'monto_digital'   => $montoDig,
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
        $vueltoMsg = $vuelto > 0 ? ' Vuelto a entregar: Bs. ' . number_format($vuelto, 2) . '.' : '';
        $texto = "¡Listo! Quedó registrada la venta #{$folio} para {$payload['cliente_nombre']} por Bs. " . number_format($totalNeto, 2) . " ({$metodoPago}).{$vueltoMsg} El stock ya fue descontado y la caja #{$idSesion} actualizada. ¿Imprimimos el ticket?";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('ventas.ticket', ['id' => $idVenta, 'formato' => 'termico', 'autoprint' => 1]),
            'etiqueta_enlace'    => "Imprimir Ticket 80mm (#{$folio})",
            'enlace_pdf'         => route('ventas.pdf', $idVenta),
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
        $texto = "¡Perfecto! Ya emití la cotización #{$folio} para {$payload['cliente_nombre']} por Bs. " . number_format($total, 2) . ", válida por {$dias} días.";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('cotizaciones.show', $idCot),
            'etiqueta_enlace'    => "Abrir Proforma (#{$folio})",
            'enlace_pdf'         => route('cotizaciones.exportar', [$idCot, 'pdf']),
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
        $texto = "¡Excelente! Ya registré la compra #{$idCompra} a {$payload['proveedor_nombre']} (Nota {$payload['numero_factura_nota']}, {$payload['tipo_pago']}) por Bs. " . number_format($total, 2) . ". Las unidades ya entraron al inventario.";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('compras.show', $idCompra),
            'etiqueta_enlace'    => "Ver Nota de Compra (#{$idCompra})",
            'enlace_pdf'         => route('compras.exportar', [$idCompra, 'pdf']),
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
        $stockMsg = $stockRes !== null ? " Ahora quedan {$stockRes} unidades en almacén." : '';
        $texto = "¡Anotado! Quedó registrado el ajuste #{$idAjuste} ({$payload['tipo_ajuste']}) para {$payload['nombre_pieza']}.{$stockMsg}";

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('inventario.ajustes'),
            'etiqueta_enlace'    => 'Ver Historial de Ajustes',
            'datos_resumen'      => [],
        ];
    }

    private function ejecutarClienteConfirmado(array $payload, int $userId): array
    {
        $nombre = trim((string) $payload['nombre_razon_social']);
        $ciNit = trim((string) ($payload['ci_nit'] ?? '0'));
        $telefono = trim((string) ($payload['telefono'] ?? '')) ?: null;
        $tipo = (string) ($payload['tipo_cliente'] ?? 'Particular');
        $descuento = round(max(0.0, min(50.0, (float) ($payload['descuento_frecuente'] ?? 0))), 2);

        if ($ciNit !== '' && $ciNit !== '0' && $ciNit !== '0000000' && strtoupper($ciNit) !== 'S/N') {
            $existe = DB::table('clientes')->whereRaw('LOWER(TRIM(ci_nit)) = LOWER(TRIM(?))', [$ciNit])->first();
            if ($existe) {
                throw new \RuntimeException("Ya existe un cliente registrado con ese CI/NIT ({$existe->nombre_razon_social}).");
            }
        }

        $idCliente = (int) DB::table('clientes')->insertGetId([
            'nombre_razon_social' => $nombre,
            'ci_nit'              => $ciNit !== '' ? $ciNit : '0',
            'telefono'            => $telefono,
            'tipo_cliente'        => $tipo,
            'descuento_frecuente' => $descuento,
        ], 'id_cliente');

        Bitacora::registrar(
            'Ventas / Clientes',
            'INSERT',
            $idCliente,
            json_encode([
                'origen'              => 'ASISTENTE_IA_CONFIRMADO',
                'nombre_razon_social' => $nombre,
                'tipo_cliente'        => $tipo,
                'descuento_frecuente' => $descuento,
            ])
        );

        $texto = "¡Listo! Ya registré a {$nombre} ({$tipo}) en tu cartera de clientes" . ($descuento > 0 ? " con {$descuento}% de descuento frecuente." : '.');

        return [
            'ok'                 => true,
            'tipo'               => 'ejecucion_exitosa',
            'origen'             => 'smartdb',
            'texto_pantalla'     => $texto,
            'texto_voz'          => $this->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('clientes.show', $idCliente),
            'etiqueta_enlace'    => 'Ver Ficha del Cliente',
            'datos_resumen'      => [],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MÁQUINA DE ESTADOS POR CAMPO (pendiente | completado | omitido_por_usuario)
    // ═════════════════════════════════════════════════════════════════════════

    private function obtenerBorradorActivo(int $userId): ?array
    {
        $draft = session()->get(self::SESSION_DRAFT_KEY);
        if (!is_array($draft) || (int) ($draft['user_id'] ?? 0) !== $userId) {
            return null;
        }
        if ((now()->timestamp - (int) ($draft['updated_at'] ?? 0)) > 900) {
            $this->limpiarBorradorOperacion();
            return null;
        }
        return $draft;
    }

    private function guardarBorrador(array $draft): void
    {
        $draft['updated_at'] = now()->timestamp;
        session()->put(self::SESSION_DRAFT_KEY, $draft);
    }

    private function campoVacio($valor = null): array
    {
        return ['estado' => 'pendiente', 'valor' => $valor];
    }

    private function setCampo(array &$draft, string $campo, string $estado, $valor = null): void
    {
        $draft['campos'][$campo] = ['estado' => $estado, 'valor' => $valor];
    }

    private function estadoCampo(array $draft, string $campo): string
    {
        return (string) ($draft['campos'][$campo]['estado'] ?? 'pendiente');
    }

    private function valorCampo(array $draft, string $campo, $default = null)
    {
        return $draft['campos'][$campo]['valor'] ?? $default;
    }

    private function crearBorradorVacio(string $operacion, int $userId): array
    {
        $campos = match ($operacion) {
            'preparar_venta' => [
                'items'        => $this->campoVacio([]),
                'cliente'      => $this->campoVacio(null),
                'descuento'    => $this->campoVacio(0.0),
                'metodo_pago'  => $this->campoVacio(null),
                'pago_detalle' => $this->campoVacio(null),
            ],
            'preparar_cotizacion' => [
                'items'         => $this->campoVacio([]),
                'cliente'       => $this->campoVacio(null),
                'dias_vigencia' => $this->campoVacio(null),
            ],
            'preparar_compra' => [
                'proveedor'           => $this->campoVacio(null),
                'items'               => $this->campoVacio([]),
                'costo_unitario'      => $this->campoVacio(null),
                'numero_factura_nota' => $this->campoVacio(null),
                'tipo_pago'           => $this->campoVacio(null),
            ],
            'preparar_ajuste_inventario' => [
                'producto'    => $this->campoVacio(null),
                'tipo_ajuste' => $this->campoVacio(null),
                'cantidad'    => $this->campoVacio(null),
                'motivo'      => $this->campoVacio(null),
            ],
            'preparar_cliente' => [
                'nombre_razon_social' => $this->campoVacio(null),
                'tipo_cliente'        => $this->campoVacio(null),
                'ci_nit'              => $this->campoVacio(null),
                'telefono'            => $this->campoVacio(null),
                'descuento_frecuente' => $this->campoVacio(null),
            ],
            default => [],
        };

        return [
            'operacion'            => $operacion,
            'user_id'              => $userId,
            'updated_at'           => now()->timestamp,
            'campos'               => $campos,
            'esperando'            => null,
            'opciones_pendientes'  => [],
            'proveedores_opciones' => [],
            'pending_cantidad'     => null,
            'pending_costo'        => null,
            'descuento_pct_pend'   => null,
            'nuevo_producto_draft' => null,
            'es_lista_dudosa'      => false,
        ];
    }

    private function iniciarBorradorDesdeLocal(
        string $operacion,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): ?array {
        if (!$this->rolPuedeEjecutarOperacion($rolId, $operacion)) {
            $msg = "Tu cuenta de {$nombreRol} no tiene permiso para {$this->etiquetaOperacionAmigable($operacion)}. Si la necesitas, pídele apoyo al administrador.";
            return $this->respuestaRestriccion($msg);
        }

        $draft = $this->crearBorradorVacio($operacion, $userId);
        return $this->absorberMensajeEnBorrador($draft, $preguntaOriginal, $preguntaLimpia, $norm, $userId, 'local', true);
    }

    private function continuarBorradorConversacional(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        // Si está dentro del subflujo de alta de producto nuevo en compra
        if (!empty($draft['nuevo_producto_draft'])) {
            return $this->procesarSubflujoProductoNuevo($draft, $preguntaOriginal, $preguntaLimpia, $norm, $userId, 'local');
        }

        // Permitir cambiar explícitamente a otra operación si no estamos respondiendo un campo puntual
        $nuevaOp = $this->detectarOperacionTransaccional($norm);
        if ($nuevaOp !== null && $nuevaOp !== $draft['operacion'] && empty($draft['opciones_pendientes']) && $draft['esperando'] !== 'motivo') {
            if (!$this->rolPuedeEjecutarOperacion($rolId, $nuevaOp)) {
                $this->limpiarBorradorOperacion();
                return $this->respuestaRestriccion("Tu cuenta de {$nombreRol} no tiene permiso para {$this->etiquetaOperacionAmigable($nuevaOp)}.");
            }
            $this->limpiarBorradorOperacion();
            $nuevoDraft = $this->crearBorradorVacio($nuevaOp, $userId);
            return $this->absorberMensajeEnBorrador($nuevoDraft, $preguntaOriginal, $preguntaLimpia, $norm, $userId, 'local', true);
        }

        return $this->absorberMensajeEnBorrador($draft, $preguntaOriginal, $preguntaLimpia, $norm, $userId, 'local', false);
    }

    /**
     * Recupera el borrador cancelado recientemente, aplica la corrección solicitada y vuelve a mostrar la tarjeta.
     */
    private function aplicarEdicionSobreBorradorRecuperado(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        $op = $draft['operacion'];

        // Aplicar ediciones puntuales sobre campos ya completados
        if ($op === 'preparar_venta') {
            $metodo = $this->detectarMetodoPagoExplicito($norm, false);
            if ($metodo !== null) {
                $this->setCampo($draft, 'metodo_pago', 'completado', $metodo);
                $this->setCampo($draft, 'pago_detalle', 'omitido_por_usuario', null);
            }
            if (preg_match('/sin\s+descuento|quita(?:r|le)?\s+(?:el\s+)?descuento/u', $norm)) {
                $this->setCampo($draft, 'descuento', 'completado', 0.0);
                $draft['descuento_pct_pend'] = null;
                $draft['descuento_pct_aplicado'] = null;
            } elseif (preg_match('/(?:descuento(?:\s+de)?|rebaja(?:\s+de)?|ponle|aplica(?:le)?)\s*(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento|bs|bolivianos)?/u', $norm, $mD)) {
                $val = (float) str_replace(',', '.', $mD[1]);
                $items = $this->valorCampo($draft, 'items', []);
                $bruto = round(array_sum(array_column($items, 'subtotal')), 2);
                $esPct = !empty($mD[2]) && (str_contains($mD[2], '%') || str_contains($mD[2], 'ciento'));
                $monto = $esPct ? round($bruto * ($val / 100.0), 2) : round($val, 2);
                $draft['descuento_pct_aplicado'] = $esPct ? $val : null;
                $this->setCampo($draft, 'descuento', 'completado', $monto);
            } elseif (str_contains($norm, 'descuento')) {
                $this->setCampo($draft, 'descuento', 'pendiente', 0.0);
                $draft['descuento_pct_aplicado'] = null;
            }
        }

        if ($op === 'preparar_compra') {
            if (str_contains($norm, 'credito') || str_contains($norm, 'fiado')) {
                $this->setCampo($draft, 'tipo_pago', 'completado', 'Crédito');
            } elseif (str_contains($norm, 'contado')) {
                $this->setCampo($draft, 'tipo_pago', 'completado', 'Contado');
            }
            $costoNuevo = $this->extraerCostoDeTexto($norm, false);
            if ($costoNuevo !== null && $costoNuevo > 0) {
                $items = $this->valorCampo($draft, 'items', []);
                if (!empty($items)) {
                    $last = count($items) - 1;
                    $items[$last]['precio_unitario'] = round($costoNuevo, 2);
                    $items[$last]['subtotal'] = round($items[$last]['cantidad'] * $costoNuevo, 2);
                    $items[$last]['costo_confirmado'] = true;
                    $this->setCampo($draft, 'items', 'completado', $items);
                    $this->setCampo($draft, 'costo_unitario', 'completado', round($costoNuevo, 2));
                }
            }
        }

        if ($op === 'preparar_cotizacion' && preg_match('/\b(\d{1,2})\s*dias?\b/u', $norm, $mDias)) {
            $this->setCampo($draft, 'dias_vigencia', 'completado', max(1, min(90, (int) $mDias[1])));
        }

        // Cambio de cantidad en cualquier operación con ítems o ajuste
        if (preg_match('/\b(?:cambia(?:r|le)?\s+(?:la\s+)?cantidad\s+a\s+|mejor\s+(?:son\s+|que\s+sean\s+)?|ponle\s+|que\s+sean\s+)(\d{1,3})\b/u', $norm, $mQ)
            || preg_match('/\b(\d{1,3})\s*(?:unidades|uds|juegos|piezas|pzs)\b/u', $norm, $mQ)
        ) {
            $nuevaCant = max(1, (int) $mQ[1]);
            if ($op === 'preparar_ajuste_inventario') {
                $this->setCampo($draft, 'cantidad', 'completado', $nuevaCant);
            } else {
                $items = $this->valorCampo($draft, 'items', []);
                if (!empty($items)) {
                    $last = count($items) - 1;
                    $items[$last]['cantidad'] = $nuevaCant;
                    $items[$last]['subtotal'] = round($nuevaCant * (float) $items[$last]['precio_unitario'], 2);
                    $this->setCampo($draft, 'items', 'completado', $items);
                    if ($op === 'preparar_venta' && !empty($draft['descuento_pct_aplicado'])) {
                        $nuevoBruto = round(array_sum(array_column($items, 'subtotal')), 2);
                        $this->setCampo($draft, 'descuento', 'completado', round($nuevoBruto * ((float) $draft['descuento_pct_aplicado'] / 100.0), 2));
                    }
                }
            }
        }

        // Cambio de cliente en venta / cotización
        if (in_array($op, ['preparar_venta', 'preparar_cotizacion'], true)) {
            $cli = $this->detectarClienteRegistradoEnTexto($preguntaLimpia, $norm);
            if ($cli) {
                $this->setCampo($draft, 'cliente', 'completado', $this->formatearClienteArray($cli));
            }
        }

        return $this->evaluarEstadoBorrador($draft, $userId, 'local');
    }

    /**
     * Extrae simultáneamente TODOS los datos presentes en la frase del usuario,
     * detecta "no sé / no recuerdo" para marcar omitido_por_usuario sin insistir,
     * y avanza al siguiente campo o a la Tarjeta de Confirmación.
     */
    private function absorberMensajeEnBorrador(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $userId,
        string $origen,
        bool $esPrimerTurno
    ): array {
        $op = $draft['operacion'];
        $esperando = $draft['esperando'] ?? null;

        // ─── A. Detectar "no sé / no me acuerdo / cualquiera / normal" para el campo actualmente preguntado ───
        // Nota: cuando estamos en desambiguación de producto y el usuario dice "ninguno", se maneja en el bloque B.
        $esNingunoEnSeleccion = ($esperando === 'seleccion_producto' && !empty($draft['opciones_pendientes']) && $this->esRespuestaNinguno($norm));
        if (!$esPrimerTurno && !$esNingunoEnSeleccion && $esperando !== null && $this->esDesconocimientoONegativa($norm, $esperando)) {
            if ($esperando === 'seleccion_producto') {
                $draft['opciones_pendientes'] = [];
                $draft['esperando'] = null;
                $this->setCampo($draft, $op === 'preparar_ajuste_inventario' ? 'producto' : 'items', 'omitido_por_usuario', null);
                return $this->evaluarEstadoBorrador($draft, $userId, $origen);
            }

            if ($esperando === 'seleccion_proveedor') {
                $draft['proveedores_opciones'] = [];
                $draft['esperando'] = null;
                $this->setCampo($draft, 'proveedor', 'omitido_por_usuario', null);
                return $this->evaluarEstadoBorrador($draft, $userId, $origen);
            }

            $this->setCampo($draft, $esperando, 'omitido_por_usuario', null);
            $draft['esperando'] = null;
            return $this->evaluarEstadoBorrador($draft, $userId, $origen);
        }

        // ─── B. Selección entre opciones de productos (ambiguo o dudoso) ───
        if ($esperando === 'seleccion_producto' && !empty($draft['opciones_pendientes'])) {
            if ($this->esRespuestaNinguno($norm)) {
                $draft['opciones_pendientes'] = [];
                $draft['esperando'] = null;
                $resto = trim((string) preg_replace('/\b(ninguno|ninguna|no es ninguno|no es ninguna|otro|otra|repito|te repito)\b/u', ' ', $norm));
                if (mb_strlen($resto) >= 3) {
                    $norm = $resto;
                    $preguntaLimpia = $resto;
                } else {
                    if ($op === 'preparar_compra') {
                        $draft['nuevo_producto_draft'] = $this->iniciarNuevoProductoDraft('', $draft['pending_cantidad'], $draft['pending_costo']);
                        $draft['esperando'] = 'confirmar_producto_nuevo';
                        $this->guardarBorrador($draft);
                        return $this->respuestaAclaracion(
                            'Entendido, no es ninguno de esos. ¿Es un producto nuevo que quieres registrar ahora mismo para agregarlo a esta compra, o prefieres repetirme el nombre?',
                            $origen
                        );
                    }
                    $this->guardarBorrador($draft);
                    return $this->respuestaAclaracion('¡Sin problema! Dime de nuevo qué repuesto es o para qué vehículo lo buscas.', $origen);
                }
            } else {
                $seleccionado = $this->resolverSeleccionDeOpciones($draft['opciones_pendientes'], $norm);
                if ($seleccionado !== null) {
                    $cant = $this->extraerCantidadDeTexto($norm, true) ?? ($draft['pending_cantidad'] ?: 1);
                    $costo = $this->extraerCostoDeTexto($norm, false) ?? $draft['pending_costo'];

                    if ($op === 'preparar_ajuste_inventario') {
                        $this->setCampo($draft, 'producto', 'completado', $seleccionado);
                        if ($this->extraerCantidadDeTexto($norm, true) !== null || $draft['pending_cantidad'] !== null) {
                            $this->setCampo($draft, 'cantidad', 'completado', $cant);
                        }
                    } else {
                        $items = $this->valorCampo($draft, 'items', []);
                        $items[] = $this->armarItemDesdeProducto($seleccionado, $cant, $op === 'preparar_compra', $costo);
                        $this->setCampo($draft, 'items', 'completado', $items);
                        if ($op === 'preparar_compra' && $costo !== null && $costo > 0) {
                            $this->setCampo($draft, 'costo_unitario', 'completado', round($costo, 2));
                        }
                    }

                    $draft['opciones_pendientes'] = [];
                    $draft['pending_cantidad'] = null;
                    $draft['pending_costo'] = null;
                    $draft['esperando'] = null;
                }
            }
        }

        // ─── C. Extracción simultánea para CLIENTE NUEVO (`preparar_cliente`) ───
        if ($op === 'preparar_cliente') {
            $this->extraerCamposClienteNuevo($draft, $preguntaLimpia, $norm, $esperando, $esPrimerTurno);
            return $this->evaluarEstadoBorrador($draft, $userId, $origen);
        }

        // ─── D. Extracción simultánea de PROVEEDOR (Compras) ───
        if ($op === 'preparar_compra' && $this->estadoCampo($draft, 'proveedor') === 'pendiente') {
            $prov = null;
            if (!empty($draft['proveedores_opciones'])) {
                $idxProv = $this->extraerIndiceOrdinal($norm);
                if ($idxProv !== null && isset($draft['proveedores_opciones'][$idxProv - 1])) {
                    $prov = (object) $draft['proveedores_opciones'][$idxProv - 1];
                }
            }
            if (!$prov) {
                $prov = $this->detectarProveedorEnTexto($preguntaLimpia, $norm);
            }
            if ($prov) {
                $this->setCampo($draft, 'proveedor', 'completado', [
                    'id_proveedor' => (int) $prov->id_proveedor,
                    'razon_social' => $prov->razon_social,
                ]);
                $draft['proveedores_opciones'] = [];
                if ($esperando === 'seleccion_proveedor' || $esperando === 'proveedor') {
                    $draft['esperando'] = null;
                }
            }
        }

        // ─── E. Extracción simultánea específica de VENTA ───
        if ($op === 'preparar_venta') {
            // Descuento explícito o respuesta directa al campo descuento
            if ($esperando === 'descuento') {
                $cliArr = $this->valorCampo($draft, 'cliente');
                $pctFrec = (float) ($cliArr['descuento_frecuente'] ?? 0);
                if ($this->esRespuestaNegativa($norm) || preg_match('/\b(precio normal|normal|cero|0)\b/u', $norm)) {
                    $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    $draft['esperando'] = null;
                } elseif ($pctFrec > 0 && $this->esRespuestaAfirmativa($norm)) {
                    $draft['descuento_pct_pend'] = $pctFrec;
                    $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    $draft['esperando'] = null;
                } elseif (preg_match('/(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento|bs|bolivianos)?/u', $norm, $mD)) {
                    $val = (float) str_replace(',', '.', $mD[1]);
                    if (!empty($mD[2]) && (str_contains($mD[2], '%') || str_contains($mD[2], 'ciento'))) {
                        $draft['descuento_pct_pend'] = $val;
                        $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    } else {
                        $this->setCampo($draft, 'descuento', 'completado', round($val, 2));
                    }
                    $draft['esperando'] = null;
                }
            } elseif ($this->estadoCampo($draft, 'descuento') === 'pendiente') {
                if (preg_match('/sin\s+descuento/u', $norm)) {
                    $this->setCampo($draft, 'descuento', 'completado', 0.0);
                } elseif (preg_match('/(?:con\s+)?(?:un\s+)?(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento)\s*(?:de\s+)?descuento|descuento\s+(?:de(?:l)?\s+)?(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento|bs|bolivianos)?/u', $norm, $mDesc)) {
                    if (!empty($mDesc[1])) {
                        $draft['descuento_pct_pend'] = (float) str_replace(',', '.', $mDesc[1]);
                        $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    } elseif (!empty($mDesc[3])) {
                        $val = (float) str_replace(',', '.', $mDesc[3]);
                        if (!empty($mDesc[4]) && (str_contains($mDesc[4], '%') || str_contains($mDesc[4], 'ciento'))) {
                            $draft['descuento_pct_pend'] = $val;
                            $this->setCampo($draft, 'descuento', 'completado', 0.0);
                        } else {
                            $this->setCampo($draft, 'descuento', 'completado', round($val, 2));
                        }
                    }
                }
            }

            // Forma de pago
            $metodo = $this->detectarMetodoPagoExplicito($norm, $esperando === 'metodo_pago');
            if ($metodo !== null) {
                $this->setCampo($draft, 'metodo_pago', 'completado', $metodo);
                if ($esperando === 'metodo_pago') {
                    $draft['esperando'] = null;
                }
            }

            // Detalle de pago (vuelto / referencia / mixto)
            if ($esperando === 'pago_detalle') {
                $metActual = (string) $this->valorCampo($draft, 'metodo_pago', 'Efectivo');
                if (preg_match('/\b(exacto|cabal|justo|sin vuelto|no tengo|ninguno)\b/u', $norm)) {
                    $this->setCampo($draft, 'pago_detalle', 'omitido_por_usuario', null);
                    $draft['esperando'] = null;
                } elseif ($metActual === 'Efectivo' && preg_match('/\b(\d+(?:[\.,]\d+)?)\b/', $norm, $mRec)) {
                    $this->setCampo($draft, 'pago_detalle', 'completado', ['monto_recibido' => round((float) str_replace(',', '.', $mRec[1]), 2)]);
                    $draft['esperando'] = null;
                } elseif ($metActual === 'Mixto' && preg_match_all('/\b(\d+(?:[\.,]\d+)?)\b/', $norm, $mMix) && count($mMix[1]) >= 2) {
                    $this->setCampo($draft, 'pago_detalle', 'completado', [
                        'monto_efectivo' => round((float) str_replace(',', '.', $mMix[1][0]), 2),
                        'monto_digital'  => round((float) str_replace(',', '.', $mMix[1][1]), 2),
                    ]);
                    $draft['esperando'] = null;
                } elseif (in_array($metActual, ['Transferencia/QR', 'Tarjeta Débito/Crédito'], true) && mb_strlen(trim($preguntaLimpia)) >= 2) {
                    $this->setCampo($draft, 'pago_detalle', 'completado', ['nro_referencia' => trim($preguntaLimpia)]);
                    $draft['esperando'] = null;
                }
            }
        }

        // ─── F. Extracción simultánea específica de COTIZACIÓN ───
        if ($op === 'preparar_cotizacion') {
            if (preg_match('/\b(\d{1,2})\s*dias?\b/u', $norm, $mDias)) {
                $this->setCampo($draft, 'dias_vigencia', 'completado', max(1, min(90, (int) $mDias[1])));
                if ($esperando === 'dias_vigencia') {
                    $draft['esperando'] = null;
                }
            } elseif ($esperando === 'dias_vigencia' && preg_match('/^\s*(\d{1,2})\s*$/', $norm, $mDiasSolo)) {
                $this->setCampo($draft, 'dias_vigencia', 'completado', max(1, min(90, (int) $mDiasSolo[1])));
                $draft['esperando'] = null;
            }
        }

        // ─── G. Extracción simultánea específica de COMPRA ───
        if ($op === 'preparar_compra') {
            if (preg_match('/\bfactura\s+(?:n[°º#]?\s*)?([a-z0-9\-]{2,30})/i', $preguntaLimpia, $mFac)) {
                $this->setCampo($draft, 'numero_factura_nota', 'completado', strtoupper($mFac[1]));
                if ($esperando === 'numero_factura_nota') {
                    $draft['esperando'] = null;
                }
            } elseif ($esperando === 'numero_factura_nota' && mb_strlen(trim($preguntaLimpia)) >= 2) {
                $this->setCampo($draft, 'numero_factura_nota', 'completado', strtoupper(trim($preguntaLimpia)));
                $draft['esperando'] = null;
            }

            if (str_contains($norm, 'credito') || str_contains($norm, 'fiado')) {
                $this->setCampo($draft, 'tipo_pago', 'completado', 'Crédito');
                if ($esperando === 'tipo_pago') {
                    $draft['esperando'] = null;
                }
            } elseif (str_contains($norm, 'contado') || str_contains($norm, 'efectivo')) {
                $this->setCampo($draft, 'tipo_pago', 'completado', 'Contado');
                if ($esperando === 'tipo_pago') {
                    $draft['esperando'] = null;
                }
            }

            if ($esperando === 'costo_unitario') {
                $costo = $this->extraerCostoDeTexto($norm, true);
                if ($costo !== null && $costo > 0) {
                    $items = $this->valorCampo($draft, 'items', []);
                    if (!empty($items)) {
                        $last = count($items) - 1;
                        $items[$last]['precio_unitario'] = round($costo, 2);
                        $items[$last]['subtotal'] = round($items[$last]['cantidad'] * $costo, 2);
                        $items[$last]['costo_confirmado'] = true;
                        $this->setCampo($draft, 'items', 'completado', $items);
                    }
                    $this->setCampo($draft, 'costo_unitario', 'completado', round($costo, 2));
                    $draft['esperando'] = null;
                }
            }
        }

        // ─── H. Extracción simultánea específica de AJUSTE ───
        if ($op === 'preparar_ajuste_inventario') {
            $tipoAj = $this->detectarTipoAjusteExplicito($norm);
            if ($tipoAj !== null && $this->estadoCampo($draft, 'tipo_ajuste') === 'pendiente') {
                $this->setCampo($draft, 'tipo_ajuste', 'completado', $tipoAj);
                if ($esperando === 'tipo_ajuste') {
                    $draft['esperando'] = null;
                }
            }
            if ($esperando === 'cantidad' && $this->estadoCampo($draft, 'cantidad') === 'pendiente') {
                $c = $this->extraerCantidadDeTexto($norm, false);
                if ($c !== null) {
                    $this->setCampo($draft, 'cantidad', 'completado', $c);
                    $draft['esperando'] = null;
                }
            }
            if ($this->estadoCampo($draft, 'motivo') === 'pendiente') {
                if (preg_match('/\b(?:porque|motivo|se\s+rompi|se\s+dañ|se\s+dan|se\s+perdi|extravi|para\s+el\s+taller|uso\s+de\s+tienda|golpead|quebrad|fallad)(.*)$/iu', $preguntaLimpia, $mMot)) {
                    $this->setCampo($draft, 'motivo', 'completado', trim($mMot[0]));
                    if ($esperando === 'motivo') {
                        $draft['esperando'] = null;
                    }
                } elseif (!$esPrimerTurno && $esperando === 'motivo' && mb_strlen(trim($preguntaLimpia)) >= 2) {
                    $this->setCampo($draft, 'motivo', 'completado', trim($preguntaLimpia));
                    $draft['esperando'] = null;
                }
            }
        }

        // ─── I. Cliente en Venta / Cotización ───
        if (in_array($op, ['preparar_venta', 'preparar_cotizacion'], true) && $this->estadoCampo($draft, 'cliente') === 'pendiente') {
            if ($this->indicaClientePorDefecto($norm)) {
                $this->setCampo($draft, 'cliente', 'omitido_por_usuario', null);
                if ($esperando === 'cliente') {
                    $draft['esperando'] = null;
                }
            } else {
                $cli = $this->detectarClienteRegistradoEnTexto($preguntaLimpia, $norm);
                if ($cli) {
                    $this->setCampo($draft, 'cliente', 'completado', $this->formatearClienteArray($cli));
                    if ($esperando === 'cliente') {
                        $draft['esperando'] = null;
                    }
                } elseif ($esperando === 'cliente' && !$esPrimerTurno && mb_strlen(trim($preguntaLimpia)) >= 3) {
                    // El usuario escribió el nombre de un cliente que no está registrado aún -> registrar o avisar con opción mostrador
                    $cliMostrador = $this->obtenerClienteOcasionalMostrador();
                    $this->setCampo($draft, 'cliente', 'completado', [
                        'id_cliente'          => (int) $cliMostrador->id_cliente,
                        'nombre_razon_social' => $cliMostrador->nombre_razon_social . ' (Ref: ' . trim($preguntaLimpia) . ')',
                        'ci_nit'              => $cliMostrador->ci_nit ?: 'S/N',
                        'tipo_cliente'        => 'Particular',
                        'descuento_frecuente' => 0.0,
                    ]);
                    $draft['esperando'] = null;
                }
            }
        }

        // ─── J. Buscar Producto(s) si todavía está pendiente ───
        $campoProd = $op === 'preparar_ajuste_inventario' ? 'producto' : 'items';
        $necesitaProd = $this->estadoCampo($draft, $campoProd) === 'pendiente' && empty($draft['opciones_pendientes']);

        if ($necesitaProd) {
            $cantDet = $this->extraerCantidadDeTexto($norm, false);
            if ($cantDet !== null) {
                $draft['pending_cantidad'] = $cantDet;
                if ($op === 'preparar_ajuste_inventario' && $this->estadoCampo($draft, 'cantidad') === 'pendiente') {
                    $this->setCampo($draft, 'cantidad', 'completado', $cantDet);
                }
            }
            if ($op === 'preparar_compra') {
                $costoDet = $this->extraerCostoDeTexto($norm, false);
                if ($costoDet !== null && $costoDet > 0) {
                    $draft['pending_costo'] = $costoDet;
                    $this->setCampo($draft, 'costo_unitario', 'completado', round($costoDet, 2));
                }
            }

            $fragProd = $this->extraerFragmentoProductoLimpio($norm, $op);
            if ($fragProd !== '') {
                $busqueda = $this->buscarCandidatosProducto($fragProd);
                if ($busqueda['estado'] === 'unico') {
                    $prod = $busqueda['producto'];
                    $cant = $draft['pending_cantidad'] ?: 1;
                    if ($op === 'preparar_ajuste_inventario') {
                        $this->setCampo($draft, 'producto', 'completado', $prod);
                    } else {
                        $costoUso = $draft['pending_costo'] ?? $this->valorCampo($draft, 'costo_unitario');
                        $items = [$this->armarItemDesdeProducto($prod, $cant, $op === 'preparar_compra', $costoUso ? (float) $costoUso : null)];
                        $this->setCampo($draft, 'items', 'completado', $items);
                    }
                    $draft['pending_cantidad'] = null;
                    $draft['pending_costo'] = null;
                    if ($esperando === 'items' || $esperando === 'producto') {
                        $draft['esperando'] = null;
                    }
                } elseif ($busqueda['estado'] === 'ambiguo') {
                    $draft['opciones_pendientes'] = $busqueda['opciones'];
                    $draft['es_lista_dudosa'] = (bool) ($busqueda['dudoso'] ?? false);
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->construirMensajeDesambiguacion($busqueda['opciones'], $draft['es_lista_dudosa'], $origen);
                } elseif ($busqueda['estado'] === 'ninguno') {
                    // PRODUCTO NO ENCONTRADO DURANTE COMPRA -> SUBFLUJO ALTA DE PRODUCTO NUEVO
                    if ($op === 'preparar_compra') {
                        $draft['nuevo_producto_draft'] = $this->iniciarNuevoProductoDraft(
                            $fragProd,
                            $draft['pending_cantidad'],
                            $draft['pending_costo'] ?? $this->valorCampo($draft, 'costo_unitario')
                        );
                        $draft['esperando'] = 'confirmar_producto_nuevo';
                        $this->guardarBorrador($draft);
                        $msg = "No encontré ningún repuesto parecido a \"{$fragProd}\" en nuestro catálogo. ¿Es un producto nuevo que quieres registrar ahora mismo para agregarlo a esta compra?";
                        return $this->respuestaAclaracion($msg, $origen);
                    }

                    if (!$esPrimerTurno && in_array($esperando, ['items', 'producto'], true)) {
                        $this->guardarBorrador($draft);
                        return $this->respuestaAclaracion(
                            "No encontré ningún repuesto parecido a \"{$preguntaLimpia}\" en el inventario activo. ¿Me lo puedes repetir con otro nombre o decirme para qué vehículo es?",
                            $origen
                        );
                    }
                }
            }
        }

        return $this->evaluarEstadoBorrador($draft, $userId, $origen);
    }

    /**
     * Evalúa qué campo falta por preguntar, valida mínimos obligatorios si el usuario omitió alguno,
     * y arma la Tarjeta de Confirmación al completar todos los pasos.
     */
    private function evaluarEstadoBorrador(array $draft, int $userId, string $origen): array
    {
        $op = $draft['operacion'];

        // ═══ 1. VENTA ═══
        if ($op === 'preparar_venta') {
            $sesionAbierta = DB::table('sesiones_cajas')
                ->where('estado', 'Abierta')
                ->orderByRaw('CASE WHEN id_usuario_cajero = ? THEN 0 ELSE 1 END', [$userId])
                ->orderByDesc('id_sesion')
                ->first();

            if (!$sesionAbierta) {
                $this->limpiarBorradorOperacion();
                $msg = 'Ojo, todavía no abrimos la caja de hoy. ¿Quieres que vayamos a abrirla primero o te ayudo con otra cosa?';
                return [
                    'ok'                 => true,
                    'tipo'               => 'aclaracion',
                    'origen'             => $origen,
                    'texto_pantalla'     => $msg,
                    'texto_voz'          => $this->prepararTextoParaVoz($msg),
                    'enlace_comprobante' => route('caja.index'),
                    'etiqueta_enlace'    => 'Ir a Abrir Caja',
                    'datos_resumen'      => [],
                ];
            }

            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            // Campo 1 (OBLIGATORIO): Repuesto(s) + Cantidad
            if ($this->estadoCampo($draft, 'items') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'items', 'pendiente', []);
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    'Para poder registrar la venta necesito sí o sí saber qué repuesto sale y cuántas unidades son. Sin ese dato no puedo guardarla. Cuando me digas el repuesto y la cantidad, seguimos enseguida.',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'items') === 'pendiente' || empty($this->valorCampo($draft, 'items', []))) {
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('¡Con gusto armamos la venta! ¿Qué repuesto vas a vender (o para qué vehículo) y cuántas unidades?', $origen);
            }

            // Campo 2 (Opcional -> pedir siempre, respetar "no sé"): Cliente o Taller
            if ($this->estadoCampo($draft, 'cliente') === 'pendiente') {
                $items = $this->valorCampo($draft, 'items', []);
                $listaTxt = implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $items));
                $draft['esperando'] = 'cliente';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "¡Genial! Anoté {$listaTxt}. ¿A nombre de qué cliente o taller va esta venta? (Si es cliente de paso o no recuerdas el nombre, dime 'no sé' o 'normal').",
                    $origen
                );
            }

            // Resolver cliente final (o default si fue omitido)
            $cliArr = $this->estadoCampo($draft, 'cliente') === 'completado'
                ? $this->valorCampo($draft, 'cliente')
                : $this->formatearClienteArray($this->obtenerClienteOcasionalMostrador());

            // Campo 3 (Opcional -> pedir siempre, avisando si el taller tiene descuento frecuente)
            if ($this->estadoCampo($draft, 'descuento') === 'pendiente') {
                $items = $this->valorCampo($draft, 'items', []);
                $bruto = round(array_sum(array_column($items, 'subtotal')), 2);
                $pctFrec = (float) ($cliArr['descuento_frecuente'] ?? 0);
                $draft['esperando'] = 'descuento';
                $this->guardarBorrador($draft);

                if ($pctFrec > 0) {
                    $montoDesc = round($bruto * ($pctFrec / 100.0), 2);
                    return $this->respuestaAclaracion(
                        "Vi que {$cliArr['nombre_razon_social']} tiene un {$pctFrec}% de descuento frecuente (serían Bs. " . number_format($montoDesc, 2) . " menos). ¿Se lo aplico o va a precio normal?",
                        $origen
                    );
                }
                return $this->respuestaAclaracion(
                    "Anotado para {$cliArr['nombre_razon_social']}. ¿Aplicamos algún descuento (en % o Bs.) o va a precio normal?",
                    $origen
                );
            }

            // Calcular descuento porcentual si se guardó en %
            $items = $this->valorCampo($draft, 'items', []);
            $bruto = round(array_sum(array_column($items, 'subtotal')), 2);
            if (!empty($draft['descuento_pct_pend'])) {
                $pctAplicado = (float) $draft['descuento_pct_pend'];
                $calcDesc = round($bruto * ($pctAplicado / 100.0), 2);
                $this->setCampo($draft, 'descuento', 'completado', $calcDesc);
                $draft['descuento_pct_aplicado'] = $pctAplicado;
                $draft['descuento_pct_pend'] = null;
            }
            $descuento = $this->estadoCampo($draft, 'descuento') === 'completado'
                ? round((float) $this->valorCampo($draft, 'descuento', 0.0), 2)
                : 0.0;

            // Campo 4 (Opcional -> pedir siempre, default Efectivo si omite): Forma de pago
            if ($this->estadoCampo($draft, 'metodo_pago') === 'pendiente') {
                $neto = round(max(0.0, $bruto - $descuento), 2);
                $draft['esperando'] = 'metodo_pago';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "El total queda en Bs. " . number_format($neto, 2) . ". ¿Cómo va a pagar: en efectivo, transferencia/QR, tarjeta o mixto?",
                    $origen
                );
            }

            $metodoPago = $this->estadoCampo($draft, 'metodo_pago') === 'completado'
                ? (string) $this->valorCampo($draft, 'metodo_pago', 'Efectivo')
                : 'Efectivo';

            // Campo 5 (Opcional -> monto recibido para vuelto o referencia):
            if ($this->estadoCampo($draft, 'pago_detalle') === 'pendiente') {
                $neto = round(max(0.0, $bruto - $descuento), 2);
                $draft['esperando'] = 'pago_detalle';
                $this->guardarBorrador($draft);
                if ($metodoPago === 'Efectivo') {
                    return $this->respuestaAclaracion(
                        "Perfecto, pago en Efectivo por Bs. " . number_format($neto, 2) . ". ¿Con cuánto paga el cliente para calcularte el vuelto? (O dime 'exacto' / 'no sé').",
                        $origen
                    );
                }
                if ($metodoPago === 'Mixto') {
                    return $this->respuestaAclaracion(
                        "Pago Mixto por Bs. " . number_format($neto, 2) . ". ¿Cuánto entrega en efectivo y cuánto por QR/tarjeta? (Si no tienes el desglose, dime 'no sé').",
                        $origen
                    );
                }
                return $this->respuestaAclaracion(
                    "Perfecto, pago por {$metodoPago}. ¿Tienes el número de referencia o comprobante? (Si no lo tienes, dime 'no tengo').",
                    $origen
                );
            }

            $pagoDetalle = $this->estadoCampo($draft, 'pago_detalle') === 'completado'
                ? (array) $this->valorCampo($draft, 'pago_detalle', [])
                : [];

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaVenta($draft, (object) $cliArr, $items, $descuento, $metodoPago, $pagoDetalle, $sesionAbierta, $userId, $origen);
        }

        // ═══ 2. COTIZACIÓN ═══
        if ($op === 'preparar_cotizacion') {
            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            if ($this->estadoCampo($draft, 'items') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'items', 'pendiente', []);
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    'Para armar la cotización necesito sí o sí el repuesto y la cantidad. Sin eso no puedo emitirla. ¿Qué repuesto cotizamos y cuántas unidades?',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'items') === 'pendiente' || empty($this->valorCampo($draft, 'items', []))) {
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('¡Claro que sí! Armemos la proforma. ¿Qué repuesto necesitas cotizar y cuántas unidades?', $origen);
            }

            if ($this->estadoCampo($draft, 'cliente') === 'pendiente') {
                $draft['esperando'] = 'cliente';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿A nombre de qué cliente o taller emitimos la cotización? (Si no tienes el dato, dime 'no sé' y la dejo a Cliente Ocasional Mostrador).", $origen);
            }

            if ($this->estadoCampo($draft, 'dias_vigencia') === 'pendiente') {
                $draft['esperando'] = 'dias_vigencia';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Por cuántos días de vigencia quieres esta proforma? (Por defecto son 7 días; dime el número o 'normal').", $origen);
            }

            $cliArr = $this->estadoCampo($draft, 'cliente') === 'completado'
                ? $this->valorCampo($draft, 'cliente')
                : $this->formatearClienteArray($this->obtenerClienteOcasionalMostrador());
            $dias = $this->estadoCampo($draft, 'dias_vigencia') === 'completado'
                ? (int) $this->valorCampo($draft, 'dias_vigencia', 7)
                : 7;

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaCotizacion($draft, (object) $cliArr, $this->valorCampo($draft, 'items', []), $dias, $userId, $origen);
        }

        // ═══ 3. COMPRA / RECEPCIÓN DE MERCADERÍA ═══
        if ($op === 'preparar_compra') {
            // Campo 1 (OBLIGATORIO): Proveedor
            if ($this->estadoCampo($draft, 'proveedor') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'proveedor', 'pendiente', null);
                $draft['esperando'] = 'seleccion_proveedor';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    'Para registrar una compra necesito obligatoriamente el proveedor o distribuidor. Sin ese dato no puedo asentar el ingreso. Dime cuál proveedor es cuando lo tengas.',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'proveedor') === 'pendiente') {
                $provs = DB::table('proveedores')->where('estado', true)->orderBy('razon_social')->limit(8)->get();
                $opcionesProv = [];
                $lineas = [];
                foreach ($provs as $idx => $p) {
                    $num = $idx + 1;
                    $opcionesProv[] = ['id_proveedor' => (int) $p->id_proveedor, 'razon_social' => $p->razon_social];
                    $lineas[] = "{$num}. {$p->razon_social}";
                }
                $draft['proveedores_opciones'] = $opcionesProv;
                $draft['esperando'] = 'seleccion_proveedor';
                $this->guardarBorrador($draft);
                $msg = !empty($lineas)
                    ? "¡Con gusto registramos la compra! ¿A qué proveedor le compraste?\n" . implode("\n", $lineas) . "\nPuedes decirme el número o el nombre."
                    : '¡Con gusto registramos la compra! ¿Cuál es el nombre del proveedor?';
                return $this->respuestaAclaracion($msg, $origen);
            }

            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            // Campo 2 (OBLIGATORIO): Repuesto(s) + Cantidad
            if ($this->estadoCampo($draft, 'items') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'items', 'pendiente', []);
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    'Para registrar la compra necesito sí o sí el repuesto y cuántas unidades llegaron. Sin eso no puedo ingresar la mercadería al almacén. ¿Qué repuesto ingresó y cuántas unidades?',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'items') === 'pendiente' || empty($this->valorCampo($draft, 'items', []))) {
                $provArr = $this->valorCampo($draft, 'proveedor');
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "Perfecto, proveedor {$provArr['razon_social']}. ¿Qué repuesto llegó, cuántas unidades y a qué costo unitario en Bs.?",
                    $origen
                );
            }

            // Campo 3 (OBLIGATORIO): Costo unitario > 0
            $items = $this->valorCampo($draft, 'items', []);
            $ultimo = $items[count($items) - 1];
            if ($this->estadoCampo($draft, 'costo_unitario') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'costo_unitario', 'pendiente', null);
                $draft['esperando'] = 'costo_unitario';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "Para guardar la compra necesito obligatoriamente el costo unitario (en Bs.) de {$ultimo['nombre_pieza']}. Sin el costo no podemos registrar el ingreso. ¿A cuánto salió cada unidad?",
                    $origen
                );
            }
            if (empty($ultimo['costo_confirmado']) || (float) $ultimo['precio_unitario'] <= 0) {
                $draft['esperando'] = 'costo_unitario';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "Anoté {$ultimo['cantidad']} unidad(es) de {$ultimo['nombre_pieza']}. ¿Cuál fue el costo unitario de compra (en Bs.)?",
                    $origen
                );
            }

            // Campo 4 (Opcional -> default S/F-YYYYMMDD-HHMM): Factura / Nota
            if ($this->estadoCampo($draft, 'numero_factura_nota') === 'pendiente') {
                $draft['esperando'] = 'numero_factura_nota';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "¿Tienes el número de factura o nota de entrega? (Si no la recuerdas o no tiene factura, dime 'no sé' y genero un código interno automático).",
                    $origen
                );
            }

            // Campo 5 (Opcional -> default Contado): Tipo de pago
            if ($this->estadoCampo($draft, 'tipo_pago') === 'pendiente') {
                $draft['esperando'] = 'tipo_pago';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion(
                    "¿Esta compra fue al Contado o a Crédito? (Si no estás seguro, dime 'no sé' y la pongo al Contado).",
                    $origen
                );
            }

            $factura = $this->estadoCampo($draft, 'numero_factura_nota') === 'completado'
                ? trim((string) $this->valorCampo($draft, 'numero_factura_nota'))
                : '';
            if (mb_strlen($factura) < 2) {
                $factura = 'S/F-' . date('Ymd-Hi');
            }
            $tipoPago = $this->estadoCampo($draft, 'tipo_pago') === 'completado'
                ? (string) $this->valorCampo($draft, 'tipo_pago', 'Contado')
                : 'Contado';

            $provObj = (object) $this->valorCampo($draft, 'proveedor');
            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaCompra($draft, $provObj, $items, $factura, $tipoPago, $userId, $origen);
        }

        // ═══ 4. AJUSTE DE INVENTARIO / MERMA / USO INTERNO ═══
        if ($op === 'preparar_ajuste_inventario') {
            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            if ($this->estadoCampo($draft, 'producto') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'producto', 'pendiente', null);
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('Para hacer el ajuste necesito sí o sí saber qué repuesto es. ¿Cuál repuesto vamos a ajustar?', $origen);
            }
            if ($this->estadoCampo($draft, 'producto') === 'pendiente') {
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('¡Claro! Vamos a registrar el ajuste en almacén. ¿De qué repuesto se trata y cuántas unidades?', $origen);
            }

            $prodArr = $this->valorCampo($draft, 'producto');

            if ($this->estadoCampo($draft, 'tipo_ajuste') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'tipo_ajuste', 'pendiente', null);
                $draft['esperando'] = 'tipo_ajuste';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("Necesito saber el tipo de ajuste para {$prodArr['nombre_pieza']}: ¿es Merma, Uso Interno, Pérdida/Extravío, Devolución Dañada o Conteo Físico?", $origen);
            }
            if ($this->estadoCampo($draft, 'tipo_ajuste') === 'pendiente') {
                $draft['esperando'] = 'tipo_ajuste';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Qué tipo de ajuste es para {$prodArr['nombre_pieza']}: Merma (dañado), Uso Interno, Pérdida, Devolución Dañada o Conteo Físico?", $origen);
            }

            if ($this->estadoCampo($draft, 'cantidad') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'cantidad', 'pendiente', null);
                $draft['esperando'] = 'cantidad';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("Necesito saber cuántas unidades de {$prodArr['nombre_pieza']} ajustamos para poder registrarlo. ¿Cuántas son?", $origen);
            }
            if ($this->estadoCampo($draft, 'cantidad') === 'pendiente') {
                $draft['esperando'] = 'cantidad';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Cuántas unidades de {$prodArr['nombre_pieza']} anotamos para este ajuste de {$this->valorCampo($draft, 'tipo_ajuste')}?", $origen);
            }

            if ($this->estadoCampo($draft, 'motivo') === 'pendiente') {
                $draft['esperando'] = 'motivo';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Cuál es el motivo de este ajuste? (Por ejemplo: 'se rompió en almacén', o dime 'no sé' para anotar uno general).", $origen);
            }

            $tipoAj = (string) $this->valorCampo($draft, 'tipo_ajuste');
            $cant = max(1, (int) $this->valorCampo($draft, 'cantidad', 1));
            $motivoRaw = $this->estadoCampo($draft, 'motivo') === 'completado' ? (string) $this->valorCampo($draft, 'motivo', '') : '';
            $motivo = $this->formatearMotivoAjuste($tipoAj, $motivoRaw, $prodArr['nombre_pieza']);

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaAjuste($draft, (object) $prodArr, $tipoAj, $cant, $motivo, $userId, $origen);
        }

        // ═══ 5. REGISTRAR CLIENTE / TALLER RÁPIDO ═══
        if ($op === 'preparar_cliente') {
            if ($this->estadoCampo($draft, 'nombre_razon_social') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'nombre_razon_social', 'pendiente', null);
                $draft['esperando'] = 'nombre_razon_social';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('Para registrar un cliente o taller necesito obligatoriamente su nombre o razón social (mínimo 3 letras). ¿Cómo se llama?', $origen);
            }
            if ($this->estadoCampo($draft, 'nombre_razon_social') === 'pendiente') {
                $draft['esperando'] = 'nombre_razon_social';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('¡Con gusto lo registramos! ¿Cuál es el nombre completo del cliente o nombre del taller?', $origen);
            }

            $nomCli = (string) $this->valorCampo($draft, 'nombre_razon_social');

            if ($this->estadoCampo($draft, 'tipo_cliente') === 'pendiente') {
                $draft['esperando'] = 'tipo_cliente';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("Perfecto, anoté a {$nomCli}. ¿Es un cliente Particular, Taller/Mecánico o Empresa/Flota? (Si no sabes, dime 'normal').", $origen);
            }
            if ($this->estadoCampo($draft, 'ci_nit') === 'pendiente') {
                $draft['esperando'] = 'ci_nit';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Tienes el CI o NIT de {$nomCli}? (Si no lo tienes, dime 'no sé').", $origen);
            }
            if ($this->estadoCampo($draft, 'telefono') === 'pendiente') {
                $draft['esperando'] = 'telefono';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Tienes su número de teléfono o WhatsApp? (Si no, dime 'no tengo').", $origen);
            }
            if ($this->estadoCampo($draft, 'descuento_frecuente') === 'pendiente') {
                $draft['esperando'] = 'descuento_frecuente';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion("¿Le autorizamos algún porcentaje de descuento frecuente (por ejemplo 5% o 10%) o lo dejamos en 0%?", $origen);
            }

            $tipo = $this->estadoCampo($draft, 'tipo_cliente') === 'completado' ? (string) $this->valorCampo($draft, 'tipo_cliente') : 'Particular';
            $ciNit = $this->estadoCampo($draft, 'ci_nit') === 'completado' ? (string) $this->valorCampo($draft, 'ci_nit') : '0';
            $tel = $this->estadoCampo($draft, 'telefono') === 'completado' ? (string) $this->valorCampo($draft, 'telefono') : '';
            $desc = $this->estadoCampo($draft, 'descuento_frecuente') === 'completado' ? (float) $this->valorCampo($draft, 'descuento_frecuente') : 0.0;

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaCliente($draft, $nomCli, $tipo, $ciNit, $tel, $desc, $userId, $origen);
        }

        $this->limpiarBorradorOperacion();
        return $this->respuestaInformativa('¿Me repites qué te gustaría hacer? Puedo ayudarte con ventas, cotizaciones, compras, ajustes, clientes o consultas del negocio.', $origen);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SUBFLUJO DE ALTA DE PRODUCTO NUEVO DURANTE UNA COMPRA
    // ═════════════════════════════════════════════════════════════════════════

    private function iniciarNuevoProductoDraft(string $textoPropuesto, ?int $cantidad, ?float $costo): array
    {
        $nombreLimpio = mb_convert_case(trim($textoPropuesto), MB_CASE_TITLE, 'UTF-8');
        return [
            'nombre_propuesto' => $nombreLimpio,
            'cantidad'         => max(1, (int) ($cantidad ?: 1)),
            'costo_compra'     => ($costo !== null && $costo > 0) ? round($costo, 2) : null,
            'nombre_pieza'     => null,
            'id_categoria'     => null,
            'categoria_nombre' => null,
            'categorias_opts'  => [],
            'codigo'           => null,
            'precio_venta'     => null,
            'margen'           => 30.0,
            'pasillo'          => null,
            'estante'          => null,
            'ubicacion_resuelta' => false,
        ];
    }

    private function procesarSubflujoProductoNuevo(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $userId,
        string $origen
    ): array {
        $np = $draft['nuevo_producto_draft'];
        $esperando = $draft['esperando'] ?? 'confirmar_producto_nuevo';

        // 1. Confirmación inicial ("¿Es un producto nuevo que quieres registrar?")
        if ($esperando === 'confirmar_producto_nuevo') {
            if ($this->esRespuestaNegativa($norm)) {
                $draft['nuevo_producto_draft'] = null;
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->respuestaAclaracion('¡Sin problema! Dime de nuevo qué repuesto del catálogo llegó en esta compra.', $origen);
            }
            if ($this->esRespuestaAfirmativa($norm) || str_contains($norm, 'nuevo') || str_contains($norm, 'registrar')) {
                $draft['esperando'] = 'nuevo_producto_nombre';
                $this->guardarBorrador($draft);
                $prop = $np['nombre_propuesto'] ?? '';
                if (mb_strlen($prop) >= 3) {
                    return $this->respuestaAclaracion(
                        "¡Perfecto, lo damos de alta ahora! ¿Con qué nombre exacto lo guardamos en el catálogo? (Puedes decirme el nombre o responder 'así está bien' para usar \"{$prop}\").",
                        $origen
                    );
                }
                return $this->respuestaAclaracion('¡Perfecto, lo damos de alta ahora! ¿Cuál es el nombre exacto del repuesto nuevo?', $origen);
            }
            $this->guardarBorrador($draft);
            return $this->respuestaAclaracion('¿Quieres que registremos ese repuesto como producto nuevo ahora mismo? Respóndeme "sí" o "no".', $origen);
        }

        // 2. Nombre exacto del producto nuevo
        if ($esperando === 'nuevo_producto_nombre') {
            if ($this->esDesconocimientoONegativa($norm, 'nombre') && mb_strlen((string) $np['nombre_propuesto']) < 3) {
                return $this->respuestaAclaracion('Necesito al menos el nombre del repuesto nuevo (mínimo 3 letras) para poder crearlo. ¿Cómo se llama?', $origen);
            }
            if (preg_match('/\b(si|asi esta bien|ese|igual|el mismo|dale|perfecto)\b/u', $norm) && mb_strlen((string) $np['nombre_propuesto']) >= 3) {
                $np['nombre_pieza'] = $np['nombre_propuesto'];
            } else {
                $nombreDado = trim($preguntaLimpia);
                if (mb_strlen($nombreDado) < 3) {
                    return $this->respuestaAclaracion('El nombre es muy cortito; dame al menos 3 letras para identificar bien el repuesto.', $origen);
                }
                $np['nombre_pieza'] = $nombreDado;
            }
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        // 3. Categoría (lista numerada)
        if ($esperando === 'nuevo_producto_categoria') {
            $opts = $np['categorias_opts'] ?? [];
            $elegida = null;
            $idx = $this->extraerIndiceOrdinal($norm);
            if ($idx !== null && isset($opts[$idx - 1])) {
                $elegida = $opts[$idx - 1];
            } elseif (preg_match('/^\s*(\d{1,2})\s*$/', $norm, $mN) && isset($opts[(int) $mN[1] - 1])) {
                $elegida = $opts[(int) $mN[1] - 1];
            } else {
                foreach ($opts as $c) {
                    if (str_contains($this->normalizarTexto($c['nombre']), $norm) || str_contains($norm, $this->normalizarTexto($c['nombre']))) {
                        $elegida = $c;
                        break;
                    }
                }
            }
            if (!$elegida && $this->esDesconocimientoONegativa($norm, 'categoria') && !empty($opts)) {
                $elegida = $opts[0];
            }
            if (!$elegida) {
                return $this->respuestaAclaracion('No ubiqué esa categoría. Dime el número de la lista (por ejemplo "1" o "la 2").', $origen);
            }
            $np['id_categoria'] = (int) $elegida['id_categoria'];
            $np['categoria_nombre'] = $elegida['nombre'];
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        // 4. Código OEM / Interno
        if ($esperando === 'nuevo_producto_codigo') {
            if ($this->esDesconocimientoONegativa($norm, 'codigo')) {
                $np['codigo'] = $this->generarCodigoInternoUnico();
            } else {
                $cod = strtoupper(preg_replace('/[^A-Z0-9\-_]/i', '', trim($preguntaLimpia)));
                if (mb_strlen($cod) < 2) {
                    $cod = $this->generarCodigoInternoUnico();
                }
                $np['codigo'] = $cod;
            }
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        // 5. Costo unitario de compra (si todavía no se dio, necesario para compra y para calcular precio 30%)
        if ($esperando === 'nuevo_producto_costo') {
            $costo = $this->extraerCostoDeTexto($norm, true);
            if ($costo === null || $costo <= 0) {
                return $this->respuestaAclaracion('Para agregarlo a la compra necesito el costo de compra en Bs. (un número mayor a 0). ¿A cuánto te salió cada unidad?', $origen);
            }
            $np['costo_compra'] = round($costo, 2);
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        // 6. Precio sugerido de venta (si dice "no sé" -> 30% de margen sobre costo)
        if ($esperando === 'nuevo_producto_precio') {
            $costoRef = (float) ($np['costo_compra'] ?: 100.0);
            if ($this->esDesconocimientoONegativa($norm, 'precio')) {
                $np['precio_venta'] = round($costoRef * 1.30, 2);
                $np['margen'] = 30.00;
            } else {
                $precio = $this->extraerCostoDeTexto($norm, true);
                if ($precio === null || $precio <= 0) {
                    return $this->respuestaAclaracion("Dime el precio de venta en Bs. (por ejemplo '250') o dime 'no sé' y lo calculo con 30% de ganancia.", $origen);
                }
                $np['precio_venta'] = round($precio, 2);
                $np['margen'] = $costoRef > 0 ? round(max(0.0, (($precio - $costoRef) / $costoRef) * 100.0), 2) : 30.00;
            }
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        // 7. Ubicación física (pasillo / estante - opcional)
        if ($esperando === 'nuevo_producto_ubicacion') {
            if (!$this->esDesconocimientoONegativa($norm, 'ubicacion')) {
                if (preg_match('/pasillo\s*([a-z0-9\-]+)/i', $preguntaLimpia, $mP)) {
                    $np['pasillo'] = strtoupper($mP[1]);
                }
                if (preg_match('/estante\s*([a-z0-9\-]+)/i', $preguntaLimpia, $mE)) {
                    $np['estante'] = strtoupper($mE[1]);
                }
                if ($np['pasillo'] === null && $np['estante'] === null) {
                    $partesUb = preg_split('/[\s,\-\/]+/', trim($preguntaLimpia)) ?: [];
                    $np['pasillo'] = isset($partesUb[0]) ? strtoupper(mb_substr($partesUb[0], 0, 20)) : null;
                    $np['estante'] = isset($partesUb[1]) ? strtoupper(mb_substr($partesUb[1], 0, 20)) : null;
                }
            }
            $np['ubicacion_resuelta'] = true;
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
    }

    private function avanzarSubflujoProductoNuevo(array $draft, int $userId, string $origen): array
    {
        $np = $draft['nuevo_producto_draft'];

        if (empty($np['id_categoria'])) {
            $cats = DB::table('categorias')->orderBy('id_categoria')->limit(12)->get();
            $opts = [];
            $lineas = [];
            foreach ($cats as $idx => $c) {
                $num = $idx + 1;
                $opts[] = ['id_categoria' => (int) $c->id_categoria, 'nombre' => $c->nombre];
                $lineas[] = "{$num}. {$c->nombre}";
            }
            $np['categorias_opts'] = $opts;
            $draft['nuevo_producto_draft'] = $np;
            $draft['esperando'] = 'nuevo_producto_categoria';
            $this->guardarBorrador($draft);
            return $this->respuestaAclaracion(
                "Anotado \"{$np['nombre_pieza']}\". ¿En qué categoría lo guardamos?\n" . implode("\n", $lineas) . "\nDime el número o el nombre.",
                $origen
            );
        }

        if (empty($np['codigo'])) {
            $draft['esperando'] = 'nuevo_producto_codigo';
            $this->guardarBorrador($draft);
            return $this->respuestaAclaracion(
                "Categoría {$np['categoria_nombre']}. ¿Tienes el código OEM o código interno del repuesto? (Si no lo tienes, dime 'no sé' y genero un código automático).",
                $origen
            );
        }

        if (empty($np['costo_compra'])) {
            $draft['esperando'] = 'nuevo_producto_costo';
            $this->guardarBorrador($draft);
            return $this->respuestaAclaracion(
                "Código {$np['codigo']}. ¿A cuánto te costó cada unidad en esta compra (en Bs.)?",
                $origen
            );
        }

        if (empty($np['precio_venta'])) {
            $sugerido30 = number_format(round((float) $np['costo_compra'] * 1.30, 2), 2);
            $draft['esperando'] = 'nuevo_producto_precio';
            $this->guardarBorrador($draft);
            return $this->respuestaAclaracion(
                "¿A qué precio de venta sugerido (en Bs.) lo venderemos al público? (Si me dices 'no sé', lo calculo automático con 30% de margen: Bs. {$sugerido30}).",
                $origen
            );
        }

        if (empty($np['ubicacion_resuelta'])) {
            $draft['esperando'] = 'nuevo_producto_ubicacion';
            $this->guardarBorrador($draft);
            return $this->respuestaAclaracion(
                "Precio de venta: Bs. " . number_format((float) $np['precio_venta'], 2) . ". ¿En qué pasillo y estante lo vas a guardar? (Opcional: si aún no decides, dime 'no sé').",
                $origen
            );
        }

        // Todos los datos listos -> Insertar nuevo producto en PostgreSQL con stock_actual = 0 y auditar
        $codInterno = $np['codigo'];
        if (DB::table('productos')->whereRaw('LOWER(TRIM(codigo_interno)) = LOWER(TRIM(?))', [$codInterno])->exists()) {
            $codInterno = $this->generarCodigoInternoUnico();
        }
        $codOem = $np['codigo'];

        $idProducto = (int) DB::table('productos')->insertGetId([
            'id_categoria'          => (int) $np['id_categoria'],
            'codigo_interno'        => $codInterno,
            'codigo_oem'            => $codOem,
            'nombre_pieza'          => $np['nombre_pieza'],
            'marca_fabricante'      => 'Estándar',
            'procedencia'           => 'Importación',
            'precio_venta_sugerido' => round((float) $np['precio_venta'], 2),
            'margen_ganancia'       => round(min(999.99, max(0.0, (float) $np['margen'])), 2),
            'unidad_medida'         => 'Unidad',
            'stock_actual'          => 0,
            'stock_minimo'          => 3,
            'stock_seguridad'       => 5,
            'pasillo'               => $np['pasillo'],
            'estante'               => $np['estante'],
            'nivel_gaveta'          => null,
            'estado'                => true,
        ], 'id_producto');

        Bitacora::registrar(
            'Catálogo / Productos',
            'INSERT',
            $idProducto,
            json_encode([
                'origen'         => 'ASISTENTE_IA_COMPRA_PRODUCTO_NUEVO',
                'codigo_interno' => $codInterno,
                'nombre_pieza'   => $np['nombre_pieza'],
                'precio_venta'   => $np['precio_venta'],
            ])
        );

        // Agregar automáticamente al borrador de la compra y continuar flujo de compra
        $cant = max(1, (int) $np['cantidad']);
        $costo = round((float) $np['costo_compra'], 2);
        $nuevoItem = [
            'id_producto'      => $idProducto,
            'codigo_oem'       => $codOem,
            'nombre_pieza'     => $np['nombre_pieza'],
            'cantidad'         => $cant,
            'precio_unitario'  => $costo,
            'subtotal'         => round($cant * $costo, 2),
            'stock_actual'     => 0,
            'costo_confirmado' => true,
        ];

        $this->setCampo($draft, 'items', 'completado', [$nuevoItem]);
        $this->setCampo($draft, 'costo_unitario', 'completado', $costo);
        $draft['nuevo_producto_draft'] = null;
        $draft['esperando'] = null;

        $siguiente = $this->evaluarEstadoBorrador($draft, $userId, $origen);
        $prefijo = "¡Listo! Registré \"{$np['nombre_pieza']}\" (código {$codInterno}, venta Bs. " . number_format((float) $np['precio_venta'], 2) . ") en tu catálogo y lo sumé a esta compra ({$cant}x a Bs. " . number_format($costo, 2) . ").\n";
        $siguiente['texto_pantalla'] = $prefijo . ($siguiente['texto_pantalla'] ?? '');
        $siguiente['texto_voz'] = $this->prepararTextoParaVoz($siguiente['texto_pantalla']);
        return $siguiente;
    }

    private function generarCodigoInternoUnico(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $cand = 'REP-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            if (!DB::table('productos')->whereRaw('LOWER(TRIM(codigo_interno)) = LOWER(TRIM(?))', [$cand])->exists()) {
                return $cand;
            }
        }
        return 'REP-' . date('His');
    }

    private function extraerCamposClienteNuevo(array &$draft, string $preguntaLimpia, string $norm, ?string $esperando, bool $esPrimerTurno): void
    {
        // Segmento
        if (str_contains($norm, 'taller') || str_contains($norm, 'mecanico')) {
            $this->setCampo($draft, 'tipo_cliente', 'completado', 'Taller/Mecánico');
            if ($esperando === 'tipo_cliente') {
                $draft['esperando'] = null;
            }
        } elseif (str_contains($norm, 'empresa') || str_contains($norm, 'flota')) {
            $this->setCampo($draft, 'tipo_cliente', 'completado', 'Empresa/Flota');
            if ($esperando === 'tipo_cliente') {
                $draft['esperando'] = null;
            }
        } elseif (str_contains($norm, 'particular') || ($esperando === 'tipo_cliente' && str_contains($norm, 'normal'))) {
            $this->setCampo($draft, 'tipo_cliente', 'completado', 'Particular');
            if ($esperando === 'tipo_cliente') {
                $draft['esperando'] = null;
            }
        }

        // Teléfono
        if (preg_match('/\b(?:tel[eé]fono|cel(?:ular)?|whatsapp|wsp)\s*[:#]?\s*([\d\-\+\s]{7,15})/iu', $preguntaLimpia, $mTel)) {
            $this->setCampo($draft, 'telefono', 'completado', trim($mTel[1]));
            if ($esperando === 'telefono') {
                $draft['esperando'] = null;
            }
        } elseif ($esperando === 'telefono' && preg_match('/\b(\d{7,12})\b/', $norm, $mTelSolo)) {
            $this->setCampo($draft, 'telefono', 'completado', $mTelSolo[1]);
            $draft['esperando'] = null;
        }

        // CI / NIT
        if (preg_match('/\b(?:ci|nit|ci\/nit|carnet)\s*[:#]?\s*([a-z0-9\-]{4,20})/iu', $preguntaLimpia, $mCi)) {
            $this->setCampo($draft, 'ci_nit', 'completado', strtoupper(trim($mCi[1])));
            if ($esperando === 'ci_nit') {
                $draft['esperando'] = null;
            }
        } elseif ($esperando === 'ci_nit' && preg_match('/\b([0-9]{4,15}(?:\-[a-z0-9]{1,3})?)\b/i', $norm, $mCiSolo)) {
            $this->setCampo($draft, 'ci_nit', 'completado', strtoupper($mCiSolo[1]));
            $draft['esperando'] = null;
        }

        // Descuento frecuente
        if (preg_match('/(?:descuento(?:\s+frecuente)?(?:\s+de(?:l)?)?)\s*(\d+(?:[\.,]\d+)?)\s*%?/u', $norm, $mDesc)) {
            $val = min(50.0, max(0.0, (float) str_replace(',', '.', $mDesc[1])));
            $this->setCampo($draft, 'descuento_frecuente', 'completado', $val);
            if ($esperando === 'descuento_frecuente') {
                $draft['esperando'] = null;
            }
        } elseif ($esperando === 'descuento_frecuente') {
            if ($this->esRespuestaNegativa($norm) || preg_match('/\b(cero|0|sin|normal)\b/u', $norm)) {
                $this->setCampo($draft, 'descuento_frecuente', 'completado', 0.0);
                $draft['esperando'] = null;
            } elseif (preg_match('/(\d+(?:[\.,]\d+)?)/', $norm, $mDSolo)) {
                $val = min(50.0, max(0.0, (float) str_replace(',', '.', $mDSolo[1])));
                $this->setCampo($draft, 'descuento_frecuente', 'completado', $val);
                $draft['esperando'] = null;
            }
        }

        // Nombre / Razón Social
        if ($this->estadoCampo($draft, 'nombre_razon_social') === 'pendiente') {
            if ($esperando === 'nombre_razon_social' && !$esPrimerTurno) {
                $nom = trim($preguntaLimpia);
                if (mb_strlen($nom) >= 3) {
                    $this->setCampo($draft, 'nombre_razon_social', 'completado', $nom);
                    $draft['esperando'] = null;
                }
            } elseif (preg_match('/\b(?:cliente|taller|empresa|llamado|nombre)\s+([a-záéíóúñ\s]{3,60}?)(?:\s+(?:con\s+ci|con\s+nit|ci\b|nit\b|tel[eé]fono|cel|whatsapp|con\s+\d|descuento)|\s*$)/iu', $preguntaLimpia, $mNom)) {
                $cand = trim($mNom[1]);
                if (!preg_match('/^(nuevo|nueva|rapido|particular|mecanico|flota)$/i', $cand) && mb_strlen($cand) >= 3) {
                    $this->setCampo($draft, 'nombre_razon_social', 'completado', $cand);
                }
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TARJETAS DE CONFIRMACIÓN (CON SNAPSHOT PARA RECUPERACIÓN TRAS CANCELAR)
    // ═════════════════════════════════════════════════════════════════════════

    private function generarTarjetaVenta(array $draft, object $cliente, array $items, float $descuento, string $metodoPago, array $pagoDetalle, object $sesionAbierta, int $userId, string $origen): array
    {
        $bruto = round(array_sum(array_column($items, 'subtotal')), 2);
        $descuento = round(max(0.0, min($bruto, $descuento)), 2);
        $neto = round(max(0.0, $bruto - $descuento), 2);
        $metodoPago = $this->normalizarMetodoPago($metodoPago);

        $montoRecibido = isset($pagoDetalle['monto_recibido']) ? max($neto, (float) $pagoDetalle['monto_recibido']) : $neto;
        $vuelto = $metodoPago === 'Efectivo' ? round(max(0.0, $montoRecibido - $neto), 2) : 0.0;
        $referencia = $pagoDetalle['nro_referencia'] ?? null;
        $montoEfec = $metodoPago === 'Mixto'
            ? (float) ($pagoDetalle['monto_efectivo'] ?? round($neto / 2, 2))
            : ($metodoPago === 'Efectivo' ? $neto : 0.0);
        $montoDig = $metodoPago === 'Mixto'
            ? (float) ($pagoDetalle['monto_digital'] ?? round($neto - $montoEfec, 2))
            : ($metodoPago !== 'Efectivo' ? $neto : 0.0);

        $advertencias = [];
        foreach ($items as $it) {
            if ((int) $it['cantidad'] > (int) $it['stock_actual']) {
                $advertencias[] = "Ojo: de {$it['nombre_pieza']} solo nos quedan {$it['stock_actual']} en almacén y estás vendiendo {$it['cantidad']}.";
            }
        }

        $payload = [
            'operacion'       => 'preparar_venta',
            'user_id'         => $userId,
            'created_at'      => now()->timestamp,
            'id_cliente'      => (int) $cliente->id_cliente,
            'cliente_nombre'  => $cliente->nombre_razon_social,
            'id_sesion_caja'  => (int) $sesionAbierta->id_sesion,
            'metodo_pago'     => $metodoPago,
            'descuento_total' => $descuento,
            'subtotal_bruto'  => $bruto,
            'total_neto'      => $neto,
            'monto_recibido'  => $montoRecibido,
            'nro_referencia'  => $referencia,
            'monto_efectivo'  => $montoEfec,
            'monto_digital'   => $montoDig,
            'items'           => $items,
            'draft_snapshot'  => $draft,
        ];

        $resumen = [
            'Cliente'    => $cliente->nombre_razon_social . ' (' . ($cliente->ci_nit ?: 'S/N') . ')',
            'Caja'       => "Caja #{$sesionAbierta->id_sesion} (Abierta)",
            'Forma Pago' => $metodoPago,
            'Descuento'  => 'Bs. ' . number_format($descuento, 2),
            'Total'      => 'Bs. ' . number_format($neto, 2),
        ];
        if ($metodoPago === 'Efectivo' && $vuelto > 0) {
            $resumen['Recibido / Vuelto'] = 'Bs. ' . number_format($montoRecibido, 2) . ' (Vuelto: Bs. ' . number_format($vuelto, 2) . ')';
        } elseif (!empty($referencia)) {
            $resumen['Referencia'] = $referencia;
        } elseif ($metodoPago === 'Mixto') {
            $resumen['Desglose Mixto'] = 'Efec. Bs. ' . number_format($montoEfec, 2) . ' / Dig. Bs. ' . number_format($montoDig, 2);
        }

        $token = $this->guardarAccionPendiente($payload);
        $resumenItems = implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $items));
        $texto = "¡Todo listo! Te armé esta nota de venta para {$cliente->nombre_razon_social}: {$resumenItems}, por Bs. " . number_format($neto, 2) . " ({$metodoPago}). Revisa la tarjeta y dale a Confirmar y Ejecutar.";

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
                'titulo'       => 'Resumen de Nota de Venta',
                'badge'        => 'Venta en Mostrador',
                'resumen'      => $resumen,
                'items'        => $items,
                'total_neto'   => $neto,
                'advertencia'  => !empty($advertencias) ? implode(' ', $advertencias) : null,
            ],
        ];
    }

    private function generarTarjetaCotizacion(array $draft, object $cliente, array $items, int $diasVigencia, int $userId, string $origen): array
    {
        $diasVigencia = max(1, min(90, $diasVigencia));
        $total = round(array_sum(array_column($items, 'subtotal')), 2);

        $payload = [
            'operacion'      => 'preparar_cotizacion',
            'user_id'        => $userId,
            'created_at'     => now()->timestamp,
            'id_cliente'     => (int) $cliente->id_cliente,
            'cliente_nombre' => $cliente->nombre_razon_social,
            'dias_vigencia'  => $diasVigencia,
            'total_neto'     => $total,
            'items'          => $items,
            'draft_snapshot' => $draft,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "¡Listo! Preparé la cotización para {$cliente->nombre_razon_social} por Bs. " . number_format($total, 2) . ", válida por {$diasVigencia} días. Dale a Confirmar y Ejecutar para emitirla.";

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
                'titulo'       => 'Resumen de Cotización / Proforma',
                'badge'        => 'Proforma',
                'resumen'      => [
                    'Cliente'  => $cliente->nombre_razon_social,
                    'Vigencia' => "{$diasVigencia} días (hasta " . Carbon::today()->addDays($diasVigencia)->format('d/m/Y') . ')',
                    'Total'    => 'Bs. ' . number_format($total, 2),
                ],
                'items'        => $items,
                'total_neto'   => $total,
                'advertencia'  => null,
            ],
        ];
    }

    private function generarTarjetaCompra(array $draft, object $proveedor, array $items, string $factura, string $tipoPago, int $userId, string $origen): array
    {
        $tipoPago = str_contains($this->normalizarTexto($tipoPago), 'cred') ? 'Crédito' : 'Contado';
        $total = round(array_sum(array_column($items, 'subtotal')), 2);

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
            'items'               => $items,
            'draft_snapshot'      => $draft,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "¡Perfecto! Preparé el ingreso de compra a {$proveedor->razon_social} (Nota {$factura}, {$tipoPago}) por Bs. " . number_format($total, 2) . ". Confirma la tarjeta para ingresar las unidades al almacén.";

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
                'titulo'       => 'Resumen de Ingreso por Compra',
                'badge'        => 'Compra a Proveedor',
                'resumen'      => [
                    'Proveedor'      => $proveedor->razon_social,
                    'Factura / Nota' => $factura,
                    'Pago'           => $tipoPago . ($tipoPago === 'Crédito' ? ' (Por pagar)' : ' (Pagado)'),
                    'Total Compra'   => 'Bs. ' . number_format($total, 2),
                ],
                'items'        => $items,
                'total_neto'   => $total,
                'advertencia'  => null,
            ],
        ];
    }

    private function generarTarjetaAjuste(array $draft, object $producto, string $tipoAjuste, int $cantidad, string $motivo, int $userId, string $origen): array
    {
        $advertencia = null;
        if ($tipoAjuste !== 'Conteo Físico' && $cantidad > (int) $producto->stock_actual) {
            $advertencia = "Ojo: hoy tenemos {$producto->stock_actual} unidades en almacén y estás descontando {$cantidad}.";
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
            'draft_snapshot'    => $draft,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "¡Listo! Preparé el ajuste ({$tipoAjuste}) sobre {$producto->nombre_pieza} por {$cantidad} unidad(es). Si está todo bien, dale a Confirmar y Ejecutar.";

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
                'titulo'       => 'Resumen de Ajuste de Almacén',
                'badge'        => 'Almacén',
                'resumen'      => [
                    'Repuesto'     => "{$producto->nombre_pieza} ({$producto->codigo_oem})",
                    'Tipo'         => $tipoAjuste,
                    'Cantidad'     => $tipoAjuste === 'Conteo Físico' ? "{$cantidad} uds (Conteo)" : "-{$cantidad} uds",
                    'Stock Actual' => "{$producto->stock_actual} uds",
                    'Motivo'       => $motivo,
                ],
                'items'        => [],
                'total_neto'   => null,
                'advertencia'  => $advertencia,
            ],
        ];
    }

    private function generarTarjetaCliente(array $draft, string $nombre, string $tipo, string $ciNit, string $telefono, float $descuento, int $userId, string $origen): array
    {
        $payload = [
            'operacion'           => 'preparar_cliente',
            'user_id'             => $userId,
            'created_at'          => now()->timestamp,
            'nombre_razon_social' => $nombre,
            'tipo_cliente'        => $tipo,
            'ci_nit'              => $ciNit,
            'telefono'            => $telefono,
            'descuento_frecuente' => $descuento,
            'draft_snapshot'      => $draft,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "¡Listo! Preparé la ficha del nuevo cliente {$nombre} ({$tipo}). Revisa los datos y dale a Confirmar y Ejecutar.";

        return [
            'ok'               => true,
            'tipo'             => 'confirmacion',
            'origen'           => $origen,
            'texto_pantalla'   => $texto,
            'texto_voz'        => $this->prepararTextoParaVoz($texto),
            'datos_resumen'    => [],
            'accion_pendiente' => [
                'action_token' => $token,
                'operacion'    => 'preparar_cliente',
                'titulo'       => 'Confirmar Registro de Cliente / Taller',
                'badge'        => 'Cartera de Clientes',
                'resumen'      => [
                    'Nombre / Razón Social' => $nombre,
                    'Segmento'              => $tipo,
                    'CI / NIT'              => $ciNit ?: '0',
                    'Teléfono'              => $telefono ?: 'No registrado',
                    'Descuento Frecuente'   => number_format($descuento, 1) . '%',
                ],
                'items'        => [],
                'total_neto'   => null,
                'advertencia'  => null,
            ],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // VÍA A: GOOGLE GEMINI (gemini-2.5-flash -> fallback gemini-2.0-flash)
    // ═════════════════════════════════════════════════════════════════════════

    private function consultarViaGemini(
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $apiKey,
        int $rolId,
        string $nombreRol,
        int $userId,
        ?array $draftActivo
    ): ?array {
        $modelos = array_values(array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            config('services.gemini.fallback_model', 'gemini-2.0-flash'),
        ])));

        $contents = $this->construirContentsMultiTurno($preguntaOriginal, $preguntaLimpia, $nombreRol, $draftActivo);
        $body = [
            'system_instruction' => [
                'parts' => [['text' => $this->construirSystemPrompt()]],
            ],
            'tools' => [
                ['functionDeclarations' => $this->construirFunctionDeclarations()],
            ],
            'contents' => $contents,
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
                Log::warning("AiSqlAssistantService: Gemini modelo {$modelo} devolvió HTTP {$response->status()}: " . mb_substr((string) $response->body(), 0, 220));
            } catch (\Throwable $e) {
                Log::warning("AiSqlAssistantService: Excepción de conexión con Gemini {$modelo}: " . $e->getMessage());
            }
        }

        if (!is_array($responseJson)) {
            return null;
        }

        $parts = $responseJson['candidates'][0]['content']['parts'] ?? [];
        if (empty($parts)) {
            return null;
        }

        // 1. Function Call -> pasa siempre por nuestra máquina de estados y validaciones
        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                $fnName = (string) $part['functionCall']['name'];
                $fnArgs = is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [];
                return $this->procesarFunctionCallGemini($fnName, $fnArgs, $draftActivo, $rolId, $nombreRol, $userId);
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
        $parsed = $this->extraerJsonDeRespuesta($rawText);
        if (is_array($parsed) && !empty($parsed['sql'])) {
            $sql = rtrim(trim((string) $parsed['sql']), "; \t\n\r\0\x0B");
            if (!$this->esSqlSeguro($sql, $rolId)) {
                Log::warning('AiSqlAssistantService: SQL bloqueado por auditor de seguridad: ' . $sql);
                return null;
            }
            try {
                $filas = DB::select($sql);
                $datosArray = array_map(fn($row) => (array) $row, array_slice($filas, 0, 15));
                $resumenPrefijo = $parsed['explicacion'] ?? 'Aquí tienes lo que encontré:';
                $textoPantalla = $this->formatearRespuestaAmigable($resumenPrefijo, $datosArray);
                return $this->respuestaConDatos($textoPantalla, $datosArray, 'gemini');
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService: Error ejecutando SQL de Gemini: ' . $e->getMessage());
                return null;
            }
        }

        if (is_array($parsed) && !empty($parsed['respuesta'])) {
            return $this->respuestaInformativa($this->limpiarTecnicismosDeRespuesta((string) $parsed['respuesta']), 'gemini');
        }

        if (!str_contains(strtoupper($rawText), 'SELECT ') && !str_contains($rawText, '{')) {
            return $this->respuestaInformativa($this->limpiarTecnicismosDeRespuesta($rawText), 'gemini');
        }

        return null;
    }

    private function procesarFunctionCallGemini(
        string $fnName,
        array $args,
        ?array $draftActivo,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        if (!$this->rolPuedeEjecutarOperacion($rolId, $fnName)) {
            return $this->respuestaRestriccion("Tu cuenta de {$nombreRol} no tiene permiso para {$this->etiquetaOperacionAmigable($fnName)}.");
        }

        $draft = ($draftActivo && ($draftActivo['operacion'] ?? '') === $fnName)
            ? $draftActivo
            : $this->crearBorradorVacio($fnName, $userId);

        if ($fnName === 'preparar_cliente') {
            if (!empty($args['nombre_razon_social'])) {
                $this->setCampo($draft, 'nombre_razon_social', 'completado', trim((string) $args['nombre_razon_social']));
            }
            if (!empty($args['tipo_cliente'])) {
                $this->setCampo($draft, 'tipo_cliente', 'completado', trim((string) $args['tipo_cliente']));
            }
            if (!empty($args['ci_nit'])) {
                $this->setCampo($draft, 'ci_nit', 'completado', trim((string) $args['ci_nit']));
            }
            if (!empty($args['telefono'])) {
                $this->setCampo($draft, 'telefono', 'completado', trim((string) $args['telefono']));
            }
            if (isset($args['descuento_frecuente'])) {
                $this->setCampo($draft, 'descuento_frecuente', 'completado', (float) $args['descuento_frecuente']);
            }
            return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
        }

        // Cliente en venta / cotización
        if (!empty($args['cliente']) && in_array($fnName, ['preparar_venta', 'preparar_cotizacion'], true)) {
            $cliTxt = trim((string) $args['cliente']);
            if ($this->indicaClientePorDefecto($this->normalizarTexto($cliTxt))) {
                $this->setCampo($draft, 'cliente', 'omitido_por_usuario', null);
            } else {
                $cli = $this->buscarClientePorTexto($cliTxt);
                if ($cli) {
                    $this->setCampo($draft, 'cliente', 'completado', $this->formatearClienteArray($cli));
                }
            }
        }

        if ($fnName === 'preparar_venta') {
            if (!empty($args['metodo_pago'])) {
                $this->setCampo($draft, 'metodo_pago', 'completado', $this->normalizarMetodoPago((string) $args['metodo_pago']));
            }
            if (isset($args['descuento_total']) && (float) $args['descuento_total'] > 0) {
                $this->setCampo($draft, 'descuento', 'completado', round((float) $args['descuento_total'], 2));
            }
        }

        if ($fnName === 'preparar_cotizacion' && !empty($args['dias_vigencia'])) {
            $this->setCampo($draft, 'dias_vigencia', 'completado', max(1, min(90, (int) $args['dias_vigencia'])));
        }

        if ($fnName === 'preparar_compra') {
            if (!empty($args['proveedor'])) {
                $prov = $this->resolverProveedor((string) $args['proveedor']);
                if ($prov) {
                    $this->setCampo($draft, 'proveedor', 'completado', ['id_proveedor' => (int) $prov->id_proveedor, 'razon_social' => $prov->razon_social]);
                }
            }
            if (!empty($args['numero_factura_nota'])) {
                $this->setCampo($draft, 'numero_factura_nota', 'completado', strtoupper(trim((string) $args['numero_factura_nota'])));
            }
            if (!empty($args['tipo_pago'])) {
                $this->setCampo($draft, 'tipo_pago', 'completado', str_contains($this->normalizarTexto((string) $args['tipo_pago']), 'cred') ? 'Crédito' : 'Contado');
            }
        }

        if ($fnName === 'preparar_ajuste_inventario') {
            if (!empty($args['tipo_ajuste'])) {
                $this->setCampo($draft, 'tipo_ajuste', 'completado', $this->normalizarTipoAjuste((string) $args['tipo_ajuste']));
            }
            if (!empty($args['cantidad_ajustada'])) {
                $this->setCampo($draft, 'cantidad', 'completado', abs((int) $args['cantidad_ajustada']));
            }
            if (!empty($args['motivo'])) {
                $this->setCampo($draft, 'motivo', 'completado', trim((string) $args['motivo']));
            }
            if (!empty($args['producto'])) {
                $busq = $this->buscarCandidatosProducto((string) $args['producto']);
                if ($busq['estado'] === 'unico') {
                    $this->setCampo($draft, 'producto', 'completado', $busq['producto']);
                } elseif ($busq['estado'] === 'ambiguo') {
                    $draft['opciones_pendientes'] = $busq['opciones'];
                    $draft['es_lista_dudosa'] = (bool) ($busq['dudoso'] ?? false);
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->construirMensajeDesambiguacion($busq['opciones'], $draft['es_lista_dudosa'], 'gemini');
                }
            }
            return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
        }

        // Ítems en venta, cotización o compra
        if (!empty($args['items']) && is_array($args['items'])) {
            $resueltos = [];
            foreach ($args['items'] as $raw) {
                $pTxt = trim((string) ($raw['producto'] ?? ''));
                $cant = max(1, (int) ($raw['cantidad'] ?? 1));
                $costo = isset($raw['costo_unitario']) && (float) $raw['costo_unitario'] > 0 ? (float) $raw['costo_unitario'] : null;
                if ($pTxt === '') {
                    continue;
                }
                $busq = $this->buscarCandidatosProducto($pTxt);
                if ($busq['estado'] === 'unico') {
                    $resueltos[] = $this->armarItemDesdeProducto($busq['producto'], $cant, $fnName === 'preparar_compra', $costo);
                    if ($fnName === 'preparar_compra' && $costo !== null) {
                        $this->setCampo($draft, 'costo_unitario', 'completado', round($costo, 2));
                    }
                } elseif ($busq['estado'] === 'ambiguo') {
                    $draft['pending_cantidad'] = $cant;
                    $draft['pending_costo'] = $costo;
                    $draft['opciones_pendientes'] = $busq['opciones'];
                    $draft['es_lista_dudosa'] = (bool) ($busq['dudoso'] ?? false);
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->construirMensajeDesambiguacion($busq['opciones'], $draft['es_lista_dudosa'], 'gemini');
                } elseif ($fnName === 'preparar_compra') {
                    $draft['nuevo_producto_draft'] = $this->iniciarNuevoProductoDraft($pTxt, $cant, $costo);
                    $draft['esperando'] = 'confirmar_producto_nuevo';
                    $this->guardarBorrador($draft);
                    return $this->respuestaAclaracion(
                        "No encontré ningún repuesto parecido a \"{$pTxt}\" en nuestro catálogo. ¿Es un producto nuevo que quieres registrar ahora mismo para agregarlo a esta compra?",
                        'gemini'
                    );
                } else {
                    $this->guardarBorrador($draft);
                    return $this->respuestaAclaracion("No encontré un repuesto parecido a \"{$pTxt}\" en tu inventario. ¿Me lo repites o me dices para qué vehículo es?", 'gemini');
                }
            }
            if (!empty($resueltos)) {
                $this->setCampo($draft, 'items', 'completado', $resueltos);
            }
        }

        return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
    }

    private function construirContentsMultiTurno(string $preguntaOriginal, string $preguntaLimpia, string $nombreRol, ?array $draftActivo): array
    {
        $historial = session()->get(self::SESSION_HISTORY_KEY, []);
        $contents = [];
        foreach (array_slice($historial, -self::MAX_HISTORY_TURNS) as $msg) {
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

    private function registrarTurnoHistorial(string $mensajeUsuario, string $respuestaModelo): void
    {
        $historial = session()->get(self::SESSION_HISTORY_KEY, []);
        $historial[] = ['role' => 'user', 'text' => mb_strimwidth(trim($mensajeUsuario), 0, 320, '...')];
        if (trim($respuestaModelo) !== '') {
            $historial[] = ['role' => 'model', 'text' => mb_strimwidth(trim($respuestaModelo), 0, 420, '...')];
        }
        if (count($historial) > self::MAX_HISTORY_TURNS) {
            $historial = array_slice($historial, -self::MAX_HISTORY_TURNS);
        }
        session()->put(self::SESSION_HISTORY_KEY, $historial);
    }

    private function construirFunctionDeclarations(): array
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
    private function construirSystemPrompt(): string
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
4. productos (id_producto, id_categoria, codigo_interno, codigo_oem, nombre_pieza, marca_fabricante, procedencia, precio_venta_sugerido, margen_ganancia, unidad_medida, stock_actual, stock_minimo, stock_seguridad, pasillo, estante, nivel_gaveta, estado)
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

    // ═════════════════════════════════════════════════════════════════════════
    // BÚSQUEDA DIFUSA DE PRODUCTOS (TOKENS + SIMILAR_TEXT / LEVENSHTEIN)
    // ═════════════════════════════════════════════════════════════════════════

    private function buscarCandidatosProducto(string $texto): array
    {
        $limpio = trim($this->limpiarMuletillasVoz($texto));
        if ($limpio === '') {
            return ['estado' => 'ninguno', 'producto' => null, 'opciones' => [], 'dudoso' => false];
        }

        // 1. Coincidencia exacta por OEM / SKU / código alternativo
        $exacto = DB::table('productos as p')
            ->where('p.estado', true)
            ->where(function ($q) use ($limpio) {
                $q->where('p.codigo_oem', 'ilike', $limpio)
                  ->orWhere('p.codigo_interno', 'ilike', $limpio)
                  ->orWhereExists(function ($sq) use ($limpio) {
                      $sq->select(DB::raw(1))
                         ->from('codigos_alternativos as ca')
                         ->whereColumn('ca.id_producto', 'p.id_producto')
                         ->where('ca.codigo_referencia', 'ilike', $limpio);
                  });
            })
            ->first();

        if ($exacto) {
            $vehs = $this->obtenerVehiculosPorProducto([$exacto->id_producto])[$exacto->id_producto] ?? [];
            return ['estado' => 'unico', 'producto' => $this->formatearCandidatoProducto($exacto, $vehs), 'opciones' => [], 'dudoso' => false];
        }

        $norm = $this->normalizarFonetica($this->normalizarTexto($limpio));
        $terminos = $this->extraerTerminosProducto($norm);
        if (empty($terminos)) {
            return ['estado' => 'ninguno', 'producto' => null, 'opciones' => [], 'dudoso' => false];
        }

        $productos = DB::table('productos as p')
            ->leftJoin('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->where('p.estado', true)
            ->select('p.*', 'c.nombre as categoria_nombre')
            ->get();

        $vehiculosMapa = $this->obtenerVehiculosPorProducto($productos->pluck('id_producto')->all());
        $puntuados = [];

        foreach ($productos as $p) {
            $vehs = $vehiculosMapa[$p->id_producto] ?? [];
            $vehsTexto = implode(' ', $vehs);
            $haystack = $this->normalizarFonetica($this->normalizarTexto(
                "{$p->nombre_pieza} {$p->marca_fabricante} {$p->categoria_nombre} {$p->codigo_oem} {$p->codigo_interno} {$vehsTexto}"
            ));
            $hayTokens = array_values(array_filter(preg_split('/[^a-z0-9ñ]+/u', $haystack) ?: [], fn($w) => mb_strlen($w) >= 3));

            $puntaje = 0;
            $exactMatches = 0;
            $fuzzyMatches = 0;

            foreach ($terminos as $t) {
                $raiz = $this->singularizar($t);
                if (str_contains($haystack, $t)) {
                    $puntaje += 4;
                    $exactMatches++;
                } elseif (mb_strlen($raiz) >= 3 && str_contains($haystack, $raiz)) {
                    $puntaje += 3;
                    $exactMatches++;
                } elseif (mb_strlen($t) >= 4) {
                    // Búsqueda difusa tolerante a errores de voz (levenshtein + similar_text)
                    $mejorSim = 0.0;
                    foreach ($hayTokens as $hw) {
                        if (abs(strlen($t) - strlen($hw)) > 3) {
                            continue;
                        }
                        $dist = levenshtein($t, $hw);
                        similar_text($t, $hw, $pct);
                        if (($dist <= 2 && $pct >= 72.0) || $pct >= 80.0) {
                            if ($pct > $mejorSim) {
                                $mejorSim = $pct;
                            }
                        }
                    }
                    if ($mejorSim > 0) {
                        $puntaje += 2;
                        $fuzzyMatches++;
                    }
                }
            }

            if ($puntaje > 0) {
                $puntuados[] = [
                    'producto'     => $this->formatearCandidatoProducto($p, $vehs),
                    'puntaje'      => $puntaje,
                    'exactMatches' => $exactMatches,
                    'fuzzyMatches' => $fuzzyMatches,
                    'totalMatches' => $exactMatches + $fuzzyMatches,
                ];
            }
        }

        if (empty($puntuados)) {
            return ['estado' => 'ninguno', 'producto' => null, 'opciones' => [], 'dudoso' => false];
        }

        usort($puntuados, function ($a, $b) {
            if ($b['totalMatches'] !== $a['totalMatches']) {
                return $b['totalMatches'] <=> $a['totalMatches'];
            }
            if ($b['puntaje'] !== $a['puntaje']) {
                return $b['puntaje'] <=> $a['puntaje'];
            }
            return ($b['producto']['stock_actual'] ?? 0) <=> ($a['producto']['stock_actual'] ?? 0);
        });

        $top = $puntuados[0];
        $cercanos = array_values(array_filter($puntuados, function ($it) use ($top) {
            return $it['totalMatches'] >= $top['totalMatches'] && ($top['puntaje'] - $it['puntaje']) <= 2;
        }));

        // Si solo hubo coincidencia difusa débil (sin match exacto), preguntar confirmando en lugar de asumir a ciegas
        $soloDifuso = ($top['exactMatches'] === 0 && $top['fuzzyMatches'] > 0);
        if (count($cercanos) === 1 && !$soloDifuso) {
            return ['estado' => 'unico', 'producto' => $cercanos[0]['producto'], 'opciones' => [], 'dudoso' => false];
        }

        $opciones = [];
        foreach (array_slice($cercanos, 0, 5) as $idx => $it) {
            $opt = $it['producto'];
            $opt['indice'] = $idx + 1;
            $opciones[] = $opt;
        }

        return ['estado' => 'ambiguo', 'producto' => null, 'opciones' => $opciones, 'dudoso' => $soloDifuso || count($opciones) > 1];
    }

    private function construirMensajeDesambiguacion(array $opciones, bool $dudoso, string $origen): array
    {
        $cabecera = $dudoso
            ? 'No estoy seguro de cuál me dices, ¿te refieres a alguno de estos o me lo puedes repetir?'
            : 'Encontré ' . count($opciones) . ' opciones disponibles en el catálogo:';

        $lineas = [$cabecera];
        foreach ($opciones as $opt) {
            $vehTxt = !empty($opt['vehiculos']) ? ' — ' . implode(', ', array_slice($opt['vehiculos'], 0, 2)) : '';
            $marcaTxt = !empty($opt['marca_fabricante']) ? $opt['marca_fabricante'] : 'Estándar';
            $precioTxt = number_format((float) $opt['precio_venta_sugerido'], 2);
            $lineas[] = "{$opt['indice']}. {$opt['nombre_pieza']} ({$marcaTxt}{$vehTxt}) — Bs. {$precioTxt} (Stock: {$opt['stock_actual']})";
        }
        $lineas[] = 'Puedes decirme "el 1", "el primero" (con la cantidad si quieres) o "ninguno" para repetirlo.';

        return $this->respuestaAclaracion(implode("\n", $lineas), $origen);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // VÍA B: CONOCIMIENTO INTEGRAL DEL SISTEMA + CONSULTAS SOBRE TODAS LAS TABLAS
    // ═════════════════════════════════════════════════════════════════════════

    private function consultarViaMotorLocal(string $pregunta, string $norm, int $rolId, string $nombreRol): array
    {
        try {
            // 1. Preguntas sobre funcionamiento del sistema web ("¿Qué puedes hacer?", "¿Cómo cierro la caja?", etc.)
            $respSistema = $this->intencionConocimientoSistema($norm, $nombreRol);
            if ($respSistema !== null) {
                return $respSistema;
            }

            // 2. Consultas de administración / tablas maestras (roles, usuarios, categorías, proveedores, vehículos, ajustes, auditoría)
            if (preg_match('/\b(rol|roles|perfiles|permisos)\b/', $norm)) {
                return $this->intencionRoles();
            }
            if (preg_match('/\b(usuarios|empleados|personal|vendedores|cajeros|bodegueros|cuentas del sistema)\b/', $norm)) {
                return $this->intencionUsuarios($rolId, $nombreRol);
            }
            if (preg_match('/\b(categoria|categorias|rubros|familias de repuestos)\b/', $norm)) {
                return $this->intencionCategorias();
            }
            if (preg_match('/\b(auditoria|bitacora|registro de acciones|quien hizo)\b/', $norm)) {
                return $this->intencionAuditoria($rolId, $nombreRol);
            }
            if (preg_match('/\b(ajustes|mermas|salidas internas|historial de ajustes)\b/', $norm)) {
                return $this->intencionHistorialAjustes();
            }
            if (preg_match('/\b(resumen general|resumen del negocio|como esta el negocio|cuantos repuestos tenemos|estadisticas generales)\b/', $norm)) {
                return $this->intencionResumenNegocio($rolId);
            }

            // 3. Reportes Ciclo 4 y consultas operativas
            if (preg_match('/\b(rotacion|hueso|baja salida|sin movimiento|mas vendido|mas vendidos|menos vendido|desplazamiento)\b/', $norm)) {
                return $this->intencionRotacionRepuestos($norm);
            }
            if (preg_match('/\b(rentabilidad|margen|margenes|utilidad|ganancia|ganancias|rentable|rentables)\b/', $norm)) {
                return $this->intencionRentabilidad();
            }
            if (preg_match('/\b(compras.*proveedor|compras.*distribuidor|resumen de compras|comprado a)\b/', $norm)) {
                return $this->intencionComprasDistribuidor();
            }
            if (preg_match('/\b(balance.*pago|medios de pago|metodos de pago|desglose.*pago|recaudacion por canal|por metodo)\b/', $norm)) {
                return $this->intencionBalanceMediosPago();
            }
            if (preg_match('/\b(stock critico|stock bajo|bajo minimo|alerta.*stock|agotado|reabastecer|faltante.*stock|pocas unidades|por acabarse)\b/', $norm)) {
                return $this->intencionStockCritico();
            }
            if (preg_match('/\b(cuentas por pagar|deuda|pendiente.*pago|credito.*proveedor|compras a credito|por pagar|debemos)\b/', $norm)) {
                return $this->intencionCuentasPorPagar();
            }
            if (preg_match('/\b(proveedor|proveedores|distribuidor|distribuidores|importadora|importadoras)\b/', $norm)) {
                return $this->intencionProveedores();
            }
            if (preg_match('/\b(venta|ventas|vendio|vendimos|vendido|recaudado|ingresos de hoy|caja|efectivo|transferencia|qr|tarjeta|arqueo)\b/', $norm)) {
                return $this->intencionVentasYCaja($norm, $rolId);
            }
            if (preg_match('/\b(cotizacion|cotizaciones|proforma|proformas|vigente|vencida)\b/', $norm)) {
                return $this->intencionCotizaciones($norm);
            }
            if (preg_match('/\b(cliente|clientes|taller|talleres|mecanico|flota|descuento frecuente)\b/', $norm)) {
                return $this->intencionClientes($norm);
            }
            if (preg_match('/\b(que vehiculos|que marcas|lista de vehiculos|catalogo vehicular)\b/', $norm)) {
                return $this->intencionVehiculos();
            }
            if ($this->detectarIntencionCompatibilidad($norm)) {
                return $this->intencionCompatibilidadVehicular($norm);
            }

            // 4. Solo buscar repuestos si hay una coincidencia real (NUNCA asumir que cualquier texto desconocido es un repuesto)
            return $this->intencionBuscarRepuestoSinFalsos($pregunta, $norm);
        } catch (\Throwable $e) {
            Log::error('AiSqlAssistantService (Motor Local): ' . $e->getMessage());
            return $this->respuestaInformativa('Perdón, tuve un problemita consultando ese dato. ¿Me lo repites con otras palabras?', 'local');
        }
    }

    private function intencionConocimientoSistema(string $norm, string $nombreRol): ?array
    {
        if (preg_match('/\b(como cierro|cerrar la caja|como hago el arqueo|como se cierra la caja)\b/', $norm)) {
            $msg = 'Para cerrar la caja ve al menú "Tesorería y Caja" (/caja). Ahí verás tu turno abierto y un calculador de billetes y monedas: anotas el efectivo físico contado en gaveta, el sistema compara automáticamente con lo esperado y al confirmar cierra el turno y te deja imprimir o descargar el Acta de Arqueo en PDF y Excel.';
            return $this->respuestaInformativa($msg, 'local');
        }
        if (preg_match('/\b(como abro|abrir la caja|como se abre la caja|iniciar turno)\b/', $norm)) {
            $msg = 'Para abrir la caja entra al menú "Tesorería y Caja" (/caja), escribe el monto de base inicial con el que arrancas en gaveta (por ejemplo Bs. 500) y pulsa "Aperturar Sesión de Caja". Con la caja abierta ya puedes cobrar ventas en el POS o conmigo desde aquí.';
            return $this->respuestaInformativa($msg, 'local');
        }
        if (preg_match('/\b(como exporto|descargar pdf|exportar excel|imprimir reporte|sacar pdf)\b/', $norm)) {
            $msg = 'En todos los listados y reportes del sistema verás dos botones en la esquina superior derecha: "Exportar PDF" (rojo) y "Exportar Excel" (verde). Respetan los filtros que tengas aplicados. Y en ventas, cotizaciones, compras y arqueos también puedes imprimir el comprobante o bajar su PDF.';
            return $this->respuestaInformativa($msg, 'local');
        }
        if (preg_match('/\b(como convierto|convertir cotizacion|pasar proforma a venta)\b/', $norm)) {
            $msg = 'Entra a "Cotizaciones" (/cotizaciones), busca la proforma vigente y haz clic en "Convertir en Venta" (o ábrela y pulsa el botón verde). Eliges la forma de pago con caja abierta y al instante se genera la Nota de Venta y su Ticket 80mm.';
            return $this->respuestaInformativa($msg, 'local');
        }
        if (preg_match('/\b(como pago|liquidar deuda|pagar a proveedor|cuentas por pagar como)\b/', $norm)) {
            $msg = 'Ve a "Cuentas por Pagar" (/compras/cuentas/por-pagar). Allí aparecen todas las facturas a crédito pendientes; al pulsar "Liquidar Pago" la factura pasa a Pagada y queda registrada en auditoría.';
            return $this->respuestaInformativa($msg, 'local');
        }
        if (preg_match('/\b(que puede hacer|que puedes hacer|que hace este sistema|como funciona el sistema|que modulos tiene|para que sirve|ayudame|ayuda)\b/', $norm)) {
            $msg = "Estás en Multipartes El Tuerca (tu perfil actual es {$nombreRol}). El sistema maneja todo tu negocio: catálogo de repuestos con OEM y compatibilidad vehicular, alertas de stock bajo y mermas, compras a proveedores y cuentas por pagar, clientes y talleres, cotizaciones, punto de venta con ticket 80mm/PDF, arqueo de caja y reportes con gráficos (rotación, rentabilidad, compras y medios de pago) exportables a PDF y Excel.\nConmigo puedes consultar cualquier dato o armar paso a paso ventas, cotizaciones, compras (incluso creando productos nuevos), ajustes y clientes.";
            return $this->respuestaInformativa($msg, 'local');
        }

        return null;
    }

    private function intencionRoles(): array
    {
        $roles = DB::table('roles as r')
            ->leftJoin('usuarios as u', 'u.id_rol', '=', 'r.id_rol')
            ->select([
                'r.nombre_rol as Rol',
                'r.descripcion as Descripcion',
                DB::raw('COUNT(u.id_usuario) as "Usuarios"'),
            ])
            ->groupBy('r.id_rol', 'r.nombre_rol', 'r.descripcion')
            ->orderBy('r.id_rol')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $nombres = implode(', ', array_column($roles, 'Rol'));
        $texto = 'En el sistema tenemos ' . count($roles) . " roles configurados: {$nombres}. Cada uno tiene acceso solo a los módulos que le corresponden (el Administrador ve todo; Bodeguero maneja inventario y compras; Vendedor clientes, proformas y ventas; Cajero caja, cobros y pagos; y Cliente Web su portal de compras).";
        return $this->respuestaConDatos($texto, $roles, 'local');
    }

    private function intencionUsuarios(int $rolId, string $nombreRol): array
    {
        if ($rolId !== 1) {
            return $this->respuestaRestriccion("Disculpa, el listado completo de usuarios del sistema solo puede consultarlo el Administrador (tu perfil actual es {$nombreRol}).");
        }

        $usuarios = DB::table('usuarios as u')
            ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->select([
                'u.nombre_completo as Nombre',
                'u.username as Usuario',
                'r.nombre_rol as Rol',
                'u.telefono as Telefono',
                DB::raw("CASE WHEN u.estado THEN 'Activo' ELSE 'Inactivo' END as \"Estado\""),
            ])
            ->orderBy('u.id_usuario')
            ->limit(15)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $activos = count(array_filter($usuarios, fn($u) => $u['Estado'] === 'Activo'));
        $texto = 'Actualmente hay ' . count($usuarios) . " usuarios registrados en la plataforma ({$activos} activos). Aquí tienes el detalle por rol:";
        return $this->respuestaConDatos($texto, $usuarios, 'local');
    }

    private function intencionCategorias(): array
    {
        $cats = DB::table('categorias as c')
            ->leftJoin('productos as p', 'p.id_categoria', '=', 'c.id_categoria')
            ->select([
                'c.nombre as Categoria',
                'c.descripcion as Descripcion',
                DB::raw('COUNT(p.id_producto) as "Repuestos"'),
            ])
            ->groupBy('c.id_categoria', 'c.nombre', 'c.descripcion')
            ->orderByDesc(DB::raw('COUNT(p.id_producto)'))
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $texto = 'Tienes ' . count($cats) . ' categorías de repuestos registradas en el catálogo:';
        return $this->respuestaConDatos($texto, $cats, 'local');
    }

    private function intencionProveedores(): array
    {
        $provs = DB::table('proveedores')
            ->select([
                'razon_social as Proveedor',
                'nit as NIT',
                'contacto_nombre as Contacto',
                'telefono as Telefono',
                'condicion_comercial as Condicion',
                DB::raw("CASE WHEN estado THEN 'Activo' ELSE 'Inactivo' END as \"Estado\""),
            ])
            ->orderBy('razon_social')
            ->limit(15)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $texto = 'Tienes ' . count($provs) . ' proveedores/distribuidores registrados en el sistema:';
        return $this->respuestaConDatos($texto, $provs, 'local');
    }

    private function intencionVehiculos(): array
    {
        $vehs = DB::table('vehiculos as v')
            ->leftJoin('compatibilidades as c', 'c.id_vehiculo', '=', 'v.id_vehiculo')
            ->select([
                'v.marca as Marca',
                'v.modelo as Modelo',
                DB::raw("CONCAT(v.anio_inicio, '-', v.anio_fin) as \"Anios\""),
                'v.motor_cilindrada as Motor',
                DB::raw('COUNT(c.id_compatibilidad) as "Repuestos"'),
            ])
            ->groupBy('v.id_vehiculo', 'v.marca', 'v.modelo', 'v.anio_inicio', 'v.anio_fin', 'v.motor_cilindrada')
            ->orderBy('v.marca')
            ->orderBy('v.modelo')
            ->limit(15)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $texto = 'En el catálogo vehicular tenemos ' . count($vehs) . ' modelos registrados (entre marcas japonesas y coreanas):';
        return $this->respuestaConDatos($texto, $vehs, 'local');
    }

    private function intencionHistorialAjustes(): array
    {
        $ajustes = DB::table('ajustes_inventario as a')
            ->join('productos as p', 'p.id_producto', '=', 'a.id_producto')
            ->join('usuarios as u', 'u.id_usuario', '=', 'a.id_usuario')
            ->select([
                'a.fecha_ajuste as Fecha',
                'p.nombre_pieza as Repuesto',
                'a.tipo_ajuste as Tipo',
                'a.cantidad_ajustada as Cantidad',
                'a.motivo as Motivo',
                'u.nombre_completo as Responsable',
            ])
            ->orderByDesc('a.id_ajuste')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $texto = empty($ajustes)
            ? 'Todavía no se han registrado ajustes o mermas en almacén.'
            : 'Aquí tienes los últimos ' . count($ajustes) . ' ajustes y mermas registrados en almacén:';
        return $this->respuestaConDatos($texto, $ajustes, 'local');
    }

    private function intencionAuditoria(int $rolId, string $nombreRol): array
    {
        if ($rolId !== 1) {
            return $this->respuestaRestriccion("Disculpa, la bitácora de auditoría solo está habilitada para el Administrador (tu perfil es {$nombreRol}).");
        }
        $logs = DB::table('bitacoras_auditorias as b')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'b.id_usuario')
            ->select([
                'b.fecha_hora as Fecha',
                DB::raw("COALESCE(u.nombre_completo, 'Sistema') as \"Usuario\""),
                'b.modulo_afectado as Modulo',
                'b.accion as Accion',
            ])
            ->orderByDesc('b.id_bitacora')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        return $this->respuestaConDatos('Estos son los últimos ' . count($logs) . ' movimientos registrados en la bitácora:', $logs, 'local');
    }

    private function intencionResumenNegocio(int $rolId): array
    {
        $prods = (int) DB::table('productos')->where('estado', true)->count();
        $stockTotal = (int) DB::table('productos')->where('estado', true)->sum('stock_actual');
        $clientes = (int) DB::table('clientes')->count();
        $proveedores = (int) DB::table('proveedores')->where('estado', true)->count();
        $ventasHoy = (int) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', date('Y-m-d'))->count();
        $montoHoy = (float) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', date('Y-m-d'))->sum('total_venta');

        $texto = "Así está el negocio hoy: tenemos {$prods} repuestos activos ({$stockTotal} unidades en almacén), {$clientes} clientes registrados y {$proveedores} proveedores activos. Hoy llevamos {$ventasHoy} venta(s) por Bs. " . number_format($montoHoy, 2) . '.';
        return $this->respuestaInformativa($texto, 'local');
    }

    private function intencionBuscarRepuestoSinFalsos(string $preguntaOriginal, string $norm): array
    {
        $terminos = $this->extraerTerminosSignificativos($norm);
        if (empty($terminos)) {
            return $this->respuestaAclaracion(
                'Perdón, no te entendí bien esa parte. ¿Me lo puedes repetir con otras palabras? Puedo ayudarte con consultas del negocio (stock, ventas, roles, proveedores, caja) o armar contigo una venta, cotización, compra, ajuste o cliente nuevo.',
                'local'
            );
        }

        $busq = $this->buscarCandidatosProducto($preguntaOriginal);
        if ($busq['estado'] === 'ninguno') {
            return $this->respuestaAclaracion(
                'Perdón, no te entendí bien esa parte o no encontré datos con esas palabras. ¿Me lo puedes repetir indicando qué necesitas revisar (por ejemplo un repuesto, vehículo, ventas, caja, proveedores o registrar una operación)?',
                'local'
            );
        }

        $lista = $busq['estado'] === 'unico' ? [$busq['producto']] : $busq['opciones'];
        $resumen = array_map(fn($p) => [
            'Código'   => $p['codigo_interno'],
            'OEM'      => $p['codigo_oem'],
            'Repuesto' => $p['nombre_pieza'],
            'Marca'    => $p['marca_fabricante'],
            'Stock'    => $p['stock_actual'],
            'Precio'   => 'Bs. ' . number_format((float) $p['precio_venta_sugerido'], 2),
        ], $lista);

        $top = $lista[0];
        $texto = 'Encontré ' . count($lista) . " coincidencia(s) en tu inventario. Por ejemplo: {$top['nombre_pieza']} ({$top['marca_fabricante']}), hay {$top['stock_actual']} unidades a Bs. " . number_format((float) $top['precio_venta_sugerido'], 2) . '.';
        return $this->respuestaConDatos($texto, $resumen, 'local');
    }

    // ─── Reportes Analíticos y Consultas Existentes ───

    private function intencionRotacionRepuestos(string $norm): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();
        $filas = collect(DB::select('SELECT * FROM fn_reporte_rotacion_repuestos(?, ?, NULL)', [$inicio, $fin]));

        if (str_contains($norm, 'hueso') || str_contains($norm, 'baja') || str_contains($norm, 'sin movimiento')) {
            $filtradas = $filas->where('clasificacion', 'Baja Rotación / Hueso')->take(10)->values();
            $enfoque = 'que casi no se han movido (baja rotación)';
        } else {
            $filtradas = $filas->take(10)->values();
            $enfoque = 'con mayor salida';
        }

        $resumen = $filtradas->map(fn($r) => [
            'Código'   => $r->codigo_interno,
            'Repuesto' => $r->nombre_pieza,
            'Stock'    => (int) $r->stock_actual,
            'Vendidas' => (int) $r->unidades_vendidas,
            'Rotación' => number_format((float) $r->indice_rotacion * 100, 1) . '%',
            'Estado'   => $r->clasificacion,
        ])->toArray();

        $top = $resumen[0] ?? null;
        $texto = $top
            ? 'Revisé los últimos 90 días y aquí tienes ' . count($resumen) . " repuestos {$enfoque}. Destaca {$top['Repuesto']} con {$top['Vendidas']} vendidas y {$top['Stock']} en stock."
            : 'Todavía no hay suficientes movimientos en estos 90 días para medir rotación.';
        return $this->respuestaConDatos($texto, $resumen, 'local');
    }

    private function intencionRentabilidad(): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();
        $filas = collect(DB::select('SELECT * FROM fn_reporte_rentabilidad(?, ?, NULL)', [$inicio, $fin]));
        $ingresos = (float) $filas->sum('ingresos_netos');
        $utilidad = (float) $filas->sum('utilidad_bruta');
        $margen   = $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 2) : 0.0;

        $resumen = $filas->take(10)->map(fn($r) => [
            'Código'         => $r->codigo_interno,
            'Repuesto'       => $r->nombre_pieza,
            'Vendidas'       => (int) $r->cantidad_vendida,
            'Ventas (Bs.)'   => number_format((float) $r->ingresos_netos, 2),
            'Ganancia (Bs.)' => number_format((float) $r->utilidad_bruta, 2),
            'Margen'         => number_format((float) $r->margen_real_porcentaje, 1) . '%',
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'Todavía no hay ventas cerradas en los últimos 90 días para calcular ganancias.'
            : 'En los últimos 90 días vendimos Bs. ' . number_format($ingresos, 2) . ' con una ganancia bruta estimada de Bs. ' . number_format($utilidad, 2) . " (margen promedio {$margen}%). El más rentable fue {$resumen[0]['Repuesto']}.";
        return $this->respuestaConDatos($texto, $resumen, 'local');
    }

    private function intencionComprasDistribuidor(): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();
        $filas = collect(DB::select('SELECT * FROM fn_reporte_compras_distribuidor(?, ?, NULL)', [$inicio, $fin]));
        $totalComprado = (float) $filas->sum('monto_total_comprado');
        $saldoPend     = (float) $filas->sum('saldo_pendiente_credito');

        $resumen = $filas->take(10)->map(fn($r) => [
            'Proveedor'       => $r->razon_social,
            'Órdenes'         => (int) $r->total_ordenes,
            'Unidades'        => (int) $r->unidades_compradas,
            'Comprado (Bs.)'  => number_format((float) $r->monto_total_comprado, 2),
            'Pendiente (Bs.)' => number_format((float) $r->saldo_pendiente_credito, 2),
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'No registramos compras a proveedores en los últimos 90 días.'
            : 'En los últimos 90 días compramos Bs. ' . number_format($totalComprado, 2) . ' entre ' . count($resumen) . ' proveedores (pendiente a crédito: Bs. ' . number_format($saldoPend, 2) . ').';
        return $this->respuestaConDatos($texto, $resumen, 'local');
    }

    private function intencionBalanceMediosPago(): array
    {
        $inicio = Carbon::now()->subDays(90)->toDateString();
        $fin    = Carbon::now()->toDateString();
        $filas = collect(DB::select('SELECT * FROM fn_reporte_balance_medios_pago(?, ?, NULL, NULL)', [$inicio, $fin]));
        $totalNeto = (float) $filas->sum('total_neto_recaudado');
        $tickets   = (int) $filas->sum('cantidad_tickets');

        $resumen = $filas->take(10)->map(fn($r) => [
            'Caja'            => '#' . $r->id_sesion . ' (' . $r->estado_sesion . ')',
            'Cajero'          => $r->cajero_responsable,
            'Medio de Pago'   => $r->metodo_pago,
            'Tickets'         => (int) $r->cantidad_tickets,
            'Recaudado (Bs.)' => number_format((float) $r->total_neto_recaudado, 2),
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'Todavía no hay ventas cerradas para mostrar el balance por forma de pago.'
            : "Balance por medios de pago (últimos 90 días): {$tickets} ventas por Bs. " . number_format($totalNeto, 2) . '.';
        return $this->respuestaConDatos($texto, $resumen, 'local');
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
                'p.stock_actual as Quedan',
                'p.stock_minimo as Minimo',
                DB::raw("CONCAT(COALESCE(p.pasillo,'-'), '-', COALESCE(p.estante,'-')) as \"Ubicacion\""),
                'p.precio_venta_sugerido as Precio_Bs',
            ])
            ->orderBy('p.stock_actual')
            ->limit(12)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $texto = empty($items)
            ? '¡Buenas noticias! Todos los repuestos activos están por encima de su stock mínimo.'
            : 'Ojo, hay ' . count($items) . ' repuestos en stock bajo o agotado. Aquí tienes los más urgentes:';
        return $this->respuestaConDatos($texto, $items, 'local');
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
                'nc.fecha_compra as Fecha',
                'nc.monto_total as Saldo_Bs',
            ])
            ->orderBy('nc.fecha_compra')
            ->limit(12)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $suma = array_sum(array_column($deudas, 'Saldo_Bs'));
        $texto = empty($deudas)
            ? '¡Excelente! No tienes ninguna cuenta pendiente por pagar a proveedores.'
            : 'Tienes ' . count($deudas) . ' facturas pendientes a crédito por un total de Bs. ' . number_format($suma, 2) . '.';
        return $this->respuestaConDatos($texto, $deudas, 'local');
    }

    private function intencionVentasYCaja(string $norm, int $rolId): array
    {
        $hoy = date('Y-m-d');
        $cantHoy = (int) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', $hoy)->count();
        $totalHoy = (float) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', $hoy)->sum('total_venta');
        $cantGlobal = (int) DB::table('notas_ventas')->where('estado', 'Completada')->count();
        $totalGlobal = (float) DB::table('notas_ventas')->where('estado', 'Completada')->sum('total_venta');

        $desglose = DB::table('notas_ventas')
            ->where('estado', 'Completada')
            ->when($cantHoy > 0, fn($q) => $q->whereDate('fecha_hora', $hoy))
            ->select([
                'metodo_pago as Forma_Pago',
                DB::raw('COUNT(id_venta) as "Ventas"'),
                DB::raw('ROUND(SUM(total_venta), 2) as "Total_Bs"'),
            ])
            ->groupBy('metodo_pago')
            ->orderByRaw('SUM(total_venta) DESC')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $infoCaja = '';
        if (in_array($rolId, [1, 4], true)) {
            $caja = DB::table('sesiones_cajas as sc')
                ->join('usuarios as u', 'u.id_usuario', '=', 'sc.id_usuario_cajero')
                ->where('sc.estado', 'Abierta')
                ->select('sc.id_sesion', 'u.nombre_completo', 'sc.monto_inicial', 'sc.monto_esperado_sistema')
                ->orderByDesc('sc.id_sesion')
                ->first();
            $infoCaja = $caja
                ? " La caja #{$caja->id_sesion} ({$caja->nombre_completo}) está abierta con Bs. " . number_format((float) ($caja->monto_esperado_sistema ?? $caja->monto_inicial), 2) . '.'
                : ' Ojo, todavía no abrimos la caja de hoy.';
        }

        $texto = $cantHoy > 0
            ? "Hoy llevamos {$cantHoy} venta(s) por Bs. " . number_format($totalHoy, 2) . " (acumulado general: {$cantGlobal} ventas por Bs. " . number_format($totalGlobal, 2) . ").{$infoCaja}"
            : "Hoy todavía no registramos ventas. En el acumulado general van {$cantGlobal} ventas por Bs. " . number_format($totalGlobal, 2) . ".{$infoCaja}";

        return $this->respuestaConDatos($texto, $desglose, 'local');
    }

    private function intencionCotizaciones(string $norm): array
    {
        $query = DB::table('cotizaciones as cot')
            ->join('clientes as cli', 'cli.id_cliente', '=', 'cot.id_cliente')
            ->select(['cot.id_cotizacion as Nro', 'cli.nombre_razon_social as Cliente', 'cot.fecha_vencimiento as Vence', 'cot.monto_total as Total_Bs', 'cot.estado as Estado']);

        if (str_contains($norm, 'vencida')) {
            $query->where('cot.estado', 'Vencida');
        } elseif (str_contains($norm, 'vigente')) {
            $query->where('cot.estado', 'Vigente')->whereDate('cot.fecha_vencimiento', '>=', date('Y-m-d'));
        }

        $filas = $query->orderByDesc('cot.id_cotizacion')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();
        $texto = 'Encontré ' . count($filas) . ' cotizaciones registradas:';
        return $this->respuestaConDatos($texto, $filas, 'local');
    }

    private function intencionClientes(string $norm): array
    {
        $query = DB::table('clientes')->select(['nombre_razon_social as Cliente', 'ci_nit as CI_NIT', 'tipo_cliente as Tipo', 'telefono as Telefono', 'descuento_frecuente as Descuento_Pct']);
        if (str_contains($norm, 'taller') || str_contains($norm, 'mecanico')) {
            $query->where('tipo_cliente', 'Taller/Mecánico');
        } elseif (str_contains($norm, 'empresa') || str_contains($norm, 'flota')) {
            $query->where('tipo_cliente', 'Empresa/Flota');
        }
        $clientes = $query->orderByDesc('descuento_frecuente')->orderBy('nombre_razon_social')->limit(12)->get()->map(fn($r) => (array) $r)->toArray();
        return $this->respuestaConDatos('Aquí tienes ' . count($clientes) . ' clientes registrados en tu cartera:', $clientes, 'local');
    }

    private function detectarIntencionCompatibilidad(string $norm): bool
    {
        return (bool) preg_match('/\b(compatible|compatibilidad|le hace|sirve para|toyota|nissan|suzuki|mitsubishi|hyundai|kia|mazda|honda|isuzu|corolla|hilux|vitara|sentra|frontier|tucson|sportage|l200|rav4|yaris|swift|rio|accent|crv|bt50|dmax)\b/', $norm);
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
                'p.marca_fabricante as Marca',
                DB::raw("CONCAT(v.marca, ' ', v.modelo, ' (', v.anio_inicio, '-', v.anio_fin, ')') as \"Vehiculo\""),
                'p.stock_actual as Stock',
                'p.precio_venta_sugerido as Precio_Bs',
            ]);

        if (!empty($vehiculosDetectados)) {
            $query->where(function ($q) use ($vehiculosDetectados) {
                foreach ($vehiculosDetectados as $veh) {
                    $q->orWhere('v.marca', 'ilike', "%{$veh}%")->orWhere('v.modelo', 'ilike', "%{$veh}%");
                }
            });
            if (!empty($piezasDetectadas)) {
                $query->where(function ($q) use ($piezasDetectadas) {
                    foreach ($piezasDetectadas as $pz) {
                        $raiz = $this->singularizar($pz);
                        $q->orWhere('p.nombre_pieza', 'ilike', "%{$raiz}%")->orWhere('p.codigo_oem', 'ilike', "%{$raiz}%");
                    }
                });
            }
        }

        $res = $query->orderByDesc('p.stock_actual')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();
        $texto = empty($res)
            ? 'No encontré repuestos compatibles con esa combinación exacta. ¿Probamos solo con la marca o modelo del vehículo?'
            : 'Encontré ' . count($res) . " repuestos compatibles. Por ejemplo: {$res[0]['Repuesto']} para {$res[0]['Vehiculo']} (stock: {$res[0]['Stock']}, Bs. " . number_format((float) $res[0]['Precio_Bs'], 2) . ').';
        return $this->respuestaConDatos($texto, $res, 'local');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SEGURIDAD SQL READ-ONLY Y RESTRICCIONES POR ROL
    // ═════════════════════════════════════════════════════════════════════════

    public function esSqlSeguro(string $sql, int $rolId = 1): bool
    {
        $sqlTrim = trim($sql);
        if (!preg_match('/^(SELECT|WITH)\b/i', $sqlTrim)) {
            return false;
        }
        if (str_contains($sqlTrim, ';') || str_contains($sqlTrim, '--') || str_contains($sqlTrim, '/*')) {
            return false;
        }
        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|CALL|EXEC|EXECUTE|COPY|VACUUM|SET|REPLACE|PG_SLEEP)\b/i', $sqlTrim)) {
            return false;
        }
        if (preg_match('/\bpassword_hash\b/i', $sqlTrim)) {
            return false;
        }
        if ($rolId === 2 && preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas|fn_reporte_rentabilidad|fn_reporte_balance_medios_pago)\b/i', $sqlTrim)) {
            return false;
        }
        if ($rolId === 3 && preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas|notas_compras|detalles_compras|costo_unitario|margen_ganancia|fn_reporte_rentabilidad|fn_reporte_compras_distribuidor|fn_reporte_balance_medios_pago)\b/i', $sqlTrim)) {
            return false;
        }
        if ($rolId === 4 && preg_match('/\b(bitacoras_auditorias|detalles_compras|costo_unitario|margen_ganancia|fn_reporte_rentabilidad|fn_reporte_compras_distribuidor)\b/i', $sqlTrim)) {
            return false;
        }
        if ($rolId === 5 && preg_match('/\b(bitacoras_auditorias|sesiones_cajas|movimientos_cajas|notas_compras|detalles_compras|notas_ventas|detalles_ventas|usuarios|clientes|proveedores|fn_reporte_)\b/i', $sqlTrim)) {
            return false;
        }
        return true;
    }

    private function verificarRestriccionRol(string $pregunta, int $rolId, string $nombreRol): ?array
    {
        $norm = $this->normalizarTexto($pregunta);
        if ($rolId === 2 && preg_match('/\b(rentabilidad|utilidad|ganancia|margen real|arqueo|cierre de caja|sesion de caja|estado de caja|balance.*pago|recaudado|bitacora|auditoria)\b/', $norm)) {
            return $this->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no podemos ver reportes de ganancias, saldos de caja ni auditoría.");
        }
        if ($rolId === 3 && preg_match('/\b(rentabilidad|utilidad|margen|costo.*compra|costo unitario|compras.*proveedor|compras.*distribuidor|cuentas por pagar|deuda.*proveedor|arqueo|cierre de caja|sesion de caja|bitacora|auditoria)\b/', $norm)) {
            return $this->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no tenemos acceso a costos de compra, ganancias ni arqueos de caja.");
        }
        if ($rolId === 4 && preg_match('/\b(rentabilidad|utilidad|margen|costo.*compra|costo unitario|compras.*distribuidor|bitacora|auditoria)\b/', $norm)) {
            return $this->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no podemos ver costos de compra ni márgenes de ganancia.");
        }
        return null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DETECTORES DE INTENCIÓN, "NO SÉ", EDICIÓN Y UTILIDADES
    // ═════════════════════════════════════════════════════════════════════════

    private function detectarOperacionTransaccional(string $norm): ?string
    {
        if (preg_match('/\b(registra(?:r)?|agrega(?:r)?|crea(?:r)?|anota(?:r)?|dar de alta|nuevo)\s+(?:un\s+|una\s+)?(?:nuevo\s+)?(?:cliente|taller|empresa)\b/', $norm)
            && !preg_match('/\b(venta|cotizacion|proforma|compra)\b/', $norm)
        ) {
            return 'preparar_cliente';
        }
        if (preg_match('/\b(merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|da de baja|dar de baja|se rompio|se dano|ajuste de inventario|ajustar stock|registrar ajuste)\b/', $norm)) {
            return 'preparar_ajuste_inventario';
        }
        if (preg_match('/\b(registra(?:r)?|ingresa(?:r)?|recepciona(?:r)?|anota(?:r)?|quiero\s+hacer|hacer)\b.*\b(compra|mercaderia|factura de compra)\b/', $norm)
            || preg_match('/^\s*(?:nueva\s+)?compra\s+(?:a|de)\b/', $norm)
        ) {
            return 'preparar_compra';
        }
        if (preg_match('/\b(cotiza(?:r|me)?|cotizacion|proforma)\b/', $norm)
            && !preg_match('/\b(ver|listar|mostrar|cuantas|cuantos|vigentes|vencidas|historial|como convierto|convertir)\b/', $norm)
        ) {
            return 'preparar_cotizacion';
        }
        if (preg_match('/\b(vende(?:r|me|le)?|vendeme|vendele|facturar|cobrar|registra(?:r)?\s+(?:una\s+)?venta|prepara(?:r)?\s+(?:una\s+)?venta|hacer\s+(?:una\s+)?venta|nueva\s+venta|anota(?:r)?\s+(?:una\s+)?venta)\b/', $norm)) {
            return 'preparar_venta';
        }
        return null;
    }

    private function esDesconocimientoONegativa(string $norm, string $campo): bool
    {
        if (preg_match('/\b(no se|no lo se|ni idea|no me acuerdo|no recuerdo|no tengo(?: ese dato| la factura| factura| telefono| ci| nit)?|sin nombre|sin factura|no importa|ponelo normal|ponlo normal|cualquiera|ninguno|ninguna|no recuerdo la factura|lo de siempre|saltar|omitir)\b/u', $norm)) {
            return true;
        }
        if ($campo === 'cliente' && preg_match('/^\s*(normal|mostrador|cliente normal|de paso)\s*$/u', $norm)) {
            return true;
        }
        if (in_array($campo, ['dias_vigencia', 'metodo_pago', 'tipo_pago', 'tipo_cliente'], true) && preg_match('/^\s*(normal|por defecto|como siempre)\s*$/u', $norm)) {
            return true;
        }
        return false;
    }

    private function esRespuestaNinguno(string $norm): bool
    {
        return (bool) preg_match('/\b(ninguno|ninguna|no es ninguno|no es ninguna|ninguno de esos|ninguna de esas|otro repuesto)\b/u', $norm);
    }

    private function esModificacionDeBorradorCancelado(string $norm, array $cancelado): bool
    {
        return (bool) preg_match('/\b(ponle|ponelo|cambialo|cambia(?:r|le)?|mejor\s+(?:en|por|con|a|\d)|agrega(?:le)?\s+descuento|quita(?:le)?\s+(?:el\s+)?descuento|con\s+\d+(?:[\.,]\d+)?\s*%?\s*(?:de\s+)?descuento|sin\s+descuento|que\s+sea\s+(?:en|por|a)|a\s+nombre\s+de)\b/u', $norm);
    }

    private function esFraseVaciaORuido(string $norm): bool
    {
        $soloLetras = preg_replace('/[^a-z0-9ñ]/u', '', $norm);
        return mb_strlen((string) $soloLetras) < 1;
    }

    private function esComandoCancelacion(string $norm): bool
    {
        return (bool) preg_match('/^\s*(cancelar|cancela|olvidalo|olvida|dejalo nomas|deja nomas|ya no|no quiero|empezar de nuevo|nueva charla|reiniciar|borrar todo|desde cero|nada gracias)\s*$/u', $norm)
            || (bool) preg_match('/\b(cancela eso|olvidalo|empezar de nuevo|nueva charla|dejalo nomas)\b/u', $norm);
    }

    private function esSaludoOConversacionBasica(string $norm): bool
    {
        return (bool) preg_match('/^\s*(hola|buenos dias|buenas tardes|buenas noches|buenas|que tal|como estas|hey|gracias|muchas gracias|mil gracias|ok|listo|perfecto|genial)\s*$/u', $norm);
    }

    private function responderSaludoAmigable(string $norm, ?object $usuario): array
    {
        $nombre = $usuario ? Str::words((string) ($usuario->nombre_completo ?? ''), 1, '') : '';
        $saludo = $nombre !== '' ? ", {$nombre}" : '';
        if (preg_match('/\b(gracias|perfecto|listo|genial|ok)\b/', $norm)) {
            return $this->respuestaInformativa("¡A la orden{$saludo}! Aquí sigo para lo que necesites en el mostrador.", 'local');
        }
        return $this->respuestaInformativa("¡Hola{$saludo}! ¿Cómo estás? Dime qué necesitas hoy: ¿consultamos algo del negocio o registramos una venta, cotización, compra, ajuste o cliente?", 'local');
    }

    private function esConsultaGeneralOAnaliticaExplicita(string $norm): bool
    {
        return (bool) preg_match('/\b(que roles|que usuarios|que proveedores|que categorias|que puede hacer|que puedes hacer|como cierro|como abro|como exporto|stock critico|stock bajo|rotacion|hueso|rentabilidad|ganancias|margen|cuentas por pagar|balance.*pago|cuanto vendimos|cuantas ventas|como esta la caja|compatibles para)\b/', $norm);
    }

    private function esRespuestaAlBorradorActual(array $draft, string $norm): bool
    {
        $esp = $draft['esperando'] ?? null;
        if ($esp === null) {
            return false;
        }
        if ($this->esDesconocimientoONegativa($norm, $esp)) {
            return true;
        }
        if ($esp === 'metodo_pago' && $this->detectarMetodoPagoExplicito($norm, true) !== null) {
            return true;
        }
        if (in_array($esp, ['seleccion_producto', 'seleccion_proveedor', 'nuevo_producto_categoria'], true) && $this->extraerIndiceOrdinal($norm) !== null) {
            return true;
        }
        return false;
    }

    private function esRespuestaAfirmativa(string $norm): bool
    {
        return (bool) preg_match('/\b(si|claro|dale|aplica|aplicaselo|por supuesto|esta bien|bueno|ok|hagale|metele|positivo|de acuerdo)\b/u', $norm);
    }

    private function esRespuestaNegativa(string $norm): bool
    {
        return (bool) preg_match('/\b(no|nop|sin descuento|dejalo sin|precio normal|no hace falta)\b/u', $norm);
    }

    private function indicaClientePorDefecto(string $norm): bool
    {
        return (bool) preg_match('/\b(no me acuerdo|cualquiera|ponelo normal|ponlo normal|sin nombre|mostrador|cliente ocasional|cliente general|consumidor final|ninguno en especial|de paso)\b/u', $norm);
    }

    private function detectarMetodoPagoExplicito(string $norm, bool $esperandoPago): ?string
    {
        if (str_contains($norm, 'mixto')) {
            return 'Mixto';
        }
        if (preg_match('/\b(qr|transferencia|banco|deposito|pago movil)\b/u', $norm)) {
            return 'Transferencia/QR';
        }
        if (preg_match('/\b(tarjeta|debito|credito|pos|visa|mastercard)\b/u', $norm)) {
            return 'Tarjeta Débito/Crédito';
        }
        if (preg_match('/\b(efectivo|cash|billete|contado|en mano)\b/u', $norm)) {
            return 'Efectivo';
        }
        if ($esperandoPago && preg_match('/\b(normal|lo de siempre|como siempre)\b/u', $norm)) {
            return 'Efectivo';
        }
        return null;
    }

    private function detectarTipoAjusteExplicito(string $norm): ?string
    {
        if (str_contains($norm, 'uso interno') || str_contains($norm, 'autoconsumo')) {
            return 'Uso Interno';
        }
        if (str_contains($norm, 'conteo') || str_contains($norm, 'fisico')) {
            return 'Conteo Físico';
        }
        if (str_contains($norm, 'devolucion') || str_contains($norm, 'danad')) {
            return 'Devolución Dañada';
        }
        if (str_contains($norm, 'perdida') || str_contains($norm, 'extravio') || str_contains($norm, 'se perdio')) {
            return 'Pérdida/Extravío';
        }
        if (str_contains($norm, 'merma') || str_contains($norm, 'se rompio') || str_contains($norm, 'quebr') || str_contains($norm, 'da de baja')) {
            return 'Merma';
        }
        return null;
    }

    private function extraerIndiceOrdinal(string $norm): ?int
    {
        $ordinales = [
            1 => '/\b(primera|primero|la\s*1|el\s*1|opcion\s*1|numero\s*1|nro\s*1|^1$)\b/u',
            2 => '/\b(segunda|segundo|la\s*2|el\s*2|opcion\s*2|numero\s*2|nro\s*2|^2$)\b/u',
            3 => '/\b(tercera|tercero|la\s*3|el\s*3|opcion\s*3|numero\s*3|nro\s*3|^3$)\b/u',
            4 => '/\b(cuarta|cuarto|la\s*4|el\s*4|opcion\s*4|numero\s*4|nro\s*4|^4$)\b/u',
            5 => '/\b(quinta|quinto|la\s*5|el\s*5|opcion\s*5|numero\s*5|nro\s*5|^5$)\b/u',
            6 => '/\b(sexta|sexto|la\s*6|el\s*6|^6$)\b/u',
            7 => '/\b(septima|septimo|la\s*7|el\s*7|^7$)\b/u',
            8 => '/\b(octava|octavo|la\s*8|el\s*8|^8$)\b/u',
        ];
        foreach ($ordinales as $num => $regex) {
            if (preg_match($regex, $norm)) {
                return $num;
            }
        }
        return null;
    }

    private function extraerCantidadDeTexto(string $norm, bool $excluirOrdinalSeleccion): ?int
    {
        $texto = $norm;
        if ($excluirOrdinalSeleccion) {
            $texto = (string) preg_replace('/\b(?:la|el|opcion|numero|nro)\s*[1-8]\b/u', ' ', $texto);
            $texto = (string) preg_replace('/\b(primera|primero|segunda|segundo|tercera|tercero|cuarta|cuarto|quinta|quinto)\b/u', ' ', $texto);
        }

        // Quitar precios, costos, porcentajes y días para no confundirlos con la cantidad
        $texto = (string) preg_replace('/\b(?:a|costo(?:\s+de)?|precio(?:\s+de)?)\s+\d+(?:[\.,]\d+)?\s*(?:bs|bolivianos)?\b/u', ' ', $texto);
        $texto = (string) preg_replace('/\b\d+(?:[\.,]\d+)?\s*(?:bs|bolivianos|%|por\s*ciento|dias?)\b/u', ' ', $texto);
        $texto = (string) preg_replace('/descuento\s+(?:de(?:l)?\s+)?\d+(?:[\.,]\d+)?/u', ' ', $texto);

        if (preg_match('/\b(\d{1,3})\b/u', $texto, $m)) {
            $val = (int) $m[1];
            if ($val >= 1 && $val <= 500) {
                return $val;
            }
        }

        $mapa = [
            'media docena' => 6, 'docena' => 12, 'un par' => 2, 'par' => 2,
            'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6,
            'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'uno' => 1, 'una' => 1,
        ];
        foreach ($mapa as $palabra => $num) {
            if (preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $texto)) {
                return $num;
            }
        }
        return null;
    }

    private function extraerCostoDeTexto(string $norm, bool $esRespuestaDirecta = false): ?float
    {
        if (preg_match('/\b(?:a|costo(?:\s+de)?|salio(?:\s+a)?|precio(?:\s+de)?|bs\.?)\s*(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)?/u', $norm, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)\b/u', $norm, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        if ($esRespuestaDirecta && preg_match('/\b(\d+(?:[\.,]\d+)?)\b/', $norm, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return null;
    }

    private function extraerFragmentoProductoLimpio(string $norm, string $operacion): string
    {
        $sinPrecio = preg_replace('/\b(?:a|por|de|costo(?:\s+de)?)\s+\d+(?:[\.,]\d+)?\s*(?:bs|bolivianos|%)\b/u', ' ', $norm);
        $sinDesc = preg_replace('/(?:con\s+)?(?:un\s+)?\d+(?:[\.,]\d+)?\s*(?:%|por\s*ciento)\s*(?:de\s+)?descuento|descuento\s+(?:de(?:l)?\s+)?\d+(?:[\.,]\d+)?\s*(?:%|bs|bolivianos)?/u', ' ', (string) $sinPrecio);
        $sinFactura = preg_replace('/\bfactura\s+[a-z0-9\-]+/u', ' ', (string) $sinDesc);
        $sinPago = preg_replace('/\b(en efectivo|por qr|con qr|por transferencia|con tarjeta|pago mixto|al contado|a credito|por \d+ dias)\b/u', ' ', (string) $sinFactura);

        // Quitar nombres de proveedores conocidos en compras para que no ensucien el nombre del producto
        if ($operacion === 'preparar_compra') {
            $provs = DB::table('proveedores')->where('estado', true)->pluck('razon_social');
            foreach ($provs as $razon) {
                $palabras = array_filter(explode(' ', $this->normalizarTexto($razon)), fn($w) => mb_strlen($w) >= 4);
                foreach ($palabras as $w) {
                    $sinPago = preg_replace('/\b' . preg_quote($w, '/') . '\b/u', ' ', (string) $sinPago);
                }
            }
        }

        // Quitar menciones genéricas de taller/cliente ("a un taller amigo")
        $sinPago = preg_replace('/\b(?:a\s+un|al|para\s+un|para\s+el)?\s*(?:taller|cliente|mecanico)\s*(?:amigo|frecuente)?\b/u', ' ', (string) $sinPago);

        $sinRuido = preg_replace(
            '/\b(registra|registrar|prepara|preparar|vende|vender|vendeme|vendele|cotiza|cotizar|cotizame|compra|comprar|ingresa|ingresar|recepciona|recepcionar|aplica|aplicar|reporta|reportar|anota|anotar|hacer|quiero|necesito|dame|ponme|poneme|nueva|nuevo|una|un|unas|unos|venta|cotizacion|proforma|merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|ajuste|de|del|la|el|los|las|por|para|al|con|en|a|que|se|rompio|dano|perdio|da|baja|unidades|unidad|uds|piezas|pzs|juegos|juego|pares|par|efectivo|qr|tarjeta|mixto|credito|contado|cliente|proveedor|factura|normal|viejo|bolivianos|bs|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|\d+)\b/u',
            ' ',
            (string) $sinPago
        );

        return trim((string) preg_replace('/\s+/', ' ', (string) $sinRuido));
    }

    private function formatearMotivoAjuste(string $tipoAjuste, string $motivoRaw, string $nombrePieza): string
    {
        $limpio = trim($motivoRaw);
        if ($limpio === '') {
            return "{$tipoAjuste} reportada por asistente para {$nombrePieza}.";
        }
        if (mb_strlen($limpio) < 10) {
            return "{$tipoAjuste} reportada por asistente: {$limpio}";
        }
        return $limpio;
    }

    private function obtenerVehiculosPorProducto(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('compatibilidades as comp')
            ->join('vehiculos as v', 'v.id_vehiculo', '=', 'comp.id_vehiculo')
            ->whereIn('comp.id_producto', $ids)
            ->select('comp.id_producto', 'v.marca', 'v.modelo', 'v.anio_inicio', 'v.anio_fin')
            ->get();

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r->id_producto][] = trim("{$r->marca} {$r->modelo} ({$r->anio_inicio}-{$r->anio_fin})");
        }
        foreach ($mapa as $id => $lista) {
            $mapa[$id] = array_values(array_unique($lista));
        }
        return $mapa;
    }

    private function formatearCandidatoProducto(object $p, array $vehiculos): array
    {
        return [
            'id_producto'           => (int) $p->id_producto,
            'codigo_interno'        => $p->codigo_interno,
            'codigo_oem'            => $p->codigo_oem,
            'nombre_pieza'          => $p->nombre_pieza,
            'marca_fabricante'      => $p->marca_fabricante ?: 'Original',
            'precio_venta_sugerido' => round((float) $p->precio_venta_sugerido, 2),
            'margen_ganancia'       => (float) ($p->margen_ganancia ?? 25.0),
            'stock_actual'          => (int) $p->stock_actual,
            'vehiculos'             => $vehiculos,
        ];
    }

    private function resolverSeleccionDeOpciones(array $opciones, string $norm): ?array
    {
        $indice = $this->extraerIndiceOrdinal($norm);
        if ($indice !== null) {
            foreach ($opciones as $opt) {
                if ((int) ($opt['indice'] ?? 0) === $indice) {
                    return $opt;
                }
            }
        }
        $terminos = $this->extraerTerminosProducto($norm);
        if (empty($terminos)) {
            return null;
        }
        $mejor = 0;
        $ganadores = [];
        foreach ($opciones as $opt) {
            $hay = $this->normalizarFonetica($this->normalizarTexto(
                "{$opt['nombre_pieza']} {$opt['marca_fabricante']} {$opt['codigo_oem']} " . implode(' ', $opt['vehiculos'] ?? [])
            ));
            $pts = 0;
            foreach ($terminos as $t) {
                if (str_contains($hay, $t) || str_contains($hay, $this->singularizar($t))) {
                    $pts++;
                }
            }
            if ($pts > $mejor) {
                $mejor = $pts;
                $ganadores = [$opt];
            } elseif ($pts > 0 && $pts === $mejor) {
                $ganadores[] = $opt;
            }
        }
        return count($ganadores) === 1 ? $ganadores[0] : null;
    }

    private function armarItemDesdeProducto(array $prod, int $cantidad, bool $esCompra, ?float $costoUnitario = null): array
    {
        $cantidad = max(1, $cantidad);
        $costoConfirmado = false;
        if ($esCompra) {
            if ($costoUnitario !== null && $costoUnitario > 0) {
                $precioUnitario = round($costoUnitario, 2);
                $costoConfirmado = true;
            } else {
                $precioUnitario = 0.0;
            }
        } else {
            $precioUnitario = round((float) $prod['precio_venta_sugerido'], 2);
        }

        return [
            'id_producto'      => (int) $prod['id_producto'],
            'codigo_oem'       => $prod['codigo_oem'],
            'nombre_pieza'     => $prod['nombre_pieza'],
            'cantidad'         => $cantidad,
            'precio_unitario'  => $precioUnitario,
            'subtotal'         => round($cantidad * $precioUnitario, 2),
            'stock_actual'     => (int) $prod['stock_actual'],
            'costo_confirmado' => $costoConfirmado,
        ];
    }

    private function guardarAccionPendiente(array $payload): string
    {
        $token = (string) Str::uuid();
        session()->put("ai_pending_action.{$token}", $payload);
        return $token;
    }

    public function rolPuedeEjecutarOperacion(int $rolId, string $operacion): bool
    {
        return in_array($operacion, self::PERMISOS_TRANSACCIONALES[$rolId] ?? [], true);
    }

    private function obtenerClienteOcasionalMostrador(): object
    {
        return DB::table('clientes')
            ->where('id_cliente', 1)
            ->orWhere('nombre_razon_social', 'ilike', '%mostrador%')
            ->orWhere('nombre_razon_social', 'ilike', '%ocasional%')
            ->orWhere('nombre_razon_social', 'ilike', '%general%')
            ->orderBy('id_cliente')
            ->first() ?: (object) [
                'id_cliente'          => 1,
                'nombre_razon_social' => 'Cliente Ocasional Mostrador',
                'ci_nit'              => 'S/N',
                'tipo_cliente'        => 'Particular',
                'descuento_frecuente' => 0.0,
            ];
    }

    private function formatearClienteArray(object $c): array
    {
        return [
            'id_cliente'          => (int) $c->id_cliente,
            'nombre_razon_social' => (string) $c->nombre_razon_social,
            'ci_nit'              => (string) ($c->ci_nit ?: 'S/N'),
            'tipo_cliente'        => (string) ($c->tipo_cliente ?? 'Particular'),
            'descuento_frecuente' => (float) ($c->descuento_frecuente ?? 0.0),
        ];
    }

    private function buscarClientePorTexto(string $texto): ?object
    {
        $limpio = trim($texto);
        if ($limpio === '' || $this->indicaClientePorDefecto($this->normalizarTexto($limpio))) {
            return null;
        }
        return DB::table('clientes')
            ->where('nombre_razon_social', 'ilike', "%{$limpio}%")
            ->orWhere('ci_nit', 'ilike', "%{$limpio}%")
            ->first();
    }

    private function detectarClienteRegistradoEnTexto(string $original, string $norm): ?object
    {
        $clientes = DB::table('clientes')->where('id_cliente', '<>', 1)->get();
        foreach ($clientes as $cl) {
            $nomNorm = $this->normalizarTexto($cl->nombre_razon_social);
            $palabras = array_values(array_filter(explode(' ', $nomNorm), fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['taller', 'mecanico', 'cliente', 'empresa', 'flota', 'general', 'mostrador', 'amigo'], true)));
            $hits = 0;
            foreach ($palabras as $w) {
                if (str_contains($norm, $w)) {
                    $hits++;
                }
            }
            if ($hits >= 2 || (count($palabras) === 1 && $hits === 1)) {
                return $cl;
            }
        }
        return null;
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
                $q->where('razon_social', 'ilike', "%{$texto}%")->orWhere('nit', 'ilike', "%{$texto}%");
            })
            ->first();
    }

    private function detectarProveedorEnTexto(string $original, string $norm): ?object
    {
        $todosProv = DB::table('proveedores')->where('estado', true)->get();
        foreach ($todosProv as $pr) {
            $palabras = array_filter(explode(' ', $this->normalizarTexto($pr->razon_social)), fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['importadora', 'distribuidora', 'repuestos', 'ltda', 'srl', 'bolivia'], true));
            foreach ($palabras as $w) {
                if (str_contains($norm, $w)) {
                    return $pr;
                }
            }
        }
        return null;
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

    private function traducirErrorAmigable(string $raw): array
    {
        $norm = $this->normalizarTexto($raw);
        if ($raw === 'CAJA_CERRADA' || (str_contains($norm, 'caja') && (str_contains($norm, 'abierta') || str_contains($norm, 'cerrada') || str_contains($norm, 'abrir')))) {
            return [
                'mensaje'  => 'Ojo, todavía no abrimos la caja de hoy. ¿Quieres que vayamos a abrirla primero o te ayudo con otra cosa?',
                'enlace'   => route('caja.index'),
                'etiqueta' => 'Ir a Abrir Caja',
            ];
        }
        if (str_contains($norm, 'stock') || str_contains($norm, 'insuficiente')) {
            return ['mensaje' => 'Uy, no nos alcanza el stock disponible en almacén para esa cantidad. Revisemos cuántas unidades quedan.'];
        }
        if (str_contains($norm, 'costo') || str_contains($norm, 'margen') || str_contains($norm, 'perdida')) {
            return ['mensaje' => 'Ojo, ese precio o descuento quedaría por debajo del costo de compra. Tendríamos que reducir un poco el descuento.'];
        }
        if (str_contains($norm, 'descuento')) {
            return ['mensaje' => 'Ese descuento supera el tope permitido para este cliente. ¿Probamos con uno un poco menor?'];
        }
        if (str_contains($norm, 'ya existe un cliente')) {
            return ['mensaje' => $raw];
        }
        return ['mensaje' => 'Mmm, algo no cuadró al intentar guardar la operación. Revisemos si la caja está abierta y si hay stock suficiente, y probamos de nuevo.'];
    }

    private function limpiarTecnicismosDeRespuesta(string $texto): string
    {
        $limpio = preg_replace('/\b(SQLSTATE|id_producto|id_cliente|id_venta|id_sesion|constraint|jsonb|JSON|array|PostgreSQL|Smart-DB|sp_registrar_\w+|fn_reporte_\w+)\b/i', '', $texto);
        return trim((string) preg_replace('/\s{2,}/', ' ', (string) $limpio));
    }

    private function extraerJsonDeRespuesta(string $raw): ?array
    {
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/im', '', trim($raw));
        $decoded = json_decode((string) $clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{[\s\S]*\}/', (string) $clean, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function respuestaRestriccion(string $msg): array
    {
        return ['ok' => true, 'tipo' => 'restriccion', 'origen' => 'local', 'texto_pantalla' => $msg, 'texto_voz' => $this->prepararTextoParaVoz($msg), 'datos_resumen' => []];
    }

    private function respuestaInformativa(string $msg, string $origen = 'local'): array
    {
        return ['ok' => true, 'tipo' => 'informacion', 'origen' => $origen, 'texto_pantalla' => $msg, 'texto_voz' => $this->prepararTextoParaVoz($msg), 'datos_resumen' => []];
    }

    private function respuestaAclaracion(string $msg, string $origen = 'local'): array
    {
        return ['ok' => true, 'tipo' => 'aclaracion', 'origen' => $origen, 'texto_pantalla' => $msg, 'texto_voz' => $this->prepararTextoParaVoz($msg), 'datos_resumen' => []];
    }

    private function respuestaConDatos(string $texto, array $datos, string $origen = 'local'): array
    {
        return ['ok' => true, 'tipo' => 'informacion', 'origen' => $origen, 'texto_pantalla' => $texto, 'texto_voz' => $this->prepararTextoParaVoz($texto), 'datos_resumen' => $datos];
    }

    private function etiquetaOperacionAmigable(string $op): string
    {
        return match ($op) {
            'preparar_venta'             => 'registrar ventas',
            'preparar_cotizacion'        => 'emitir cotizaciones',
            'preparar_compra'            => 'registrar compras a proveedores',
            'preparar_ajuste_inventario' => 'hacer ajustes de inventario',
            'preparar_cliente'           => 'registrar clientes nuevos',
            default                      => 'hacer esta operación',
        };
    }

    private function formatearRespuestaAmigable(string $explicacion, array $datos): string
    {
        $explicacion = $this->limpiarTecnicismosDeRespuesta($explicacion);
        $total = count($datos);
        if ($total === 0) {
            return trim($explicacion) . ' No encontré registros con esos datos.';
        }
        $primeraFila = $datos[0];
        $pares = [];
        foreach ($primeraFila as $col => $val) {
            if ($val !== null && $val !== '') {
                $etiqueta = ucwords(str_replace('_', ' ', (string) $col));
                $pares[] = (is_numeric($val) && preg_match('/(precio|monto|total|costo|deuda|saldo|venta|utilidad|ingreso|bs)/i', (string) $col))
                    ? "{$etiqueta}: Bs. " . number_format((float) $val, 2)
                    : "{$etiqueta}: {$val}";
            }
        }
        return trim($explicacion) . " Encontré {$total} resultado(s). Por ejemplo: " . implode(', ', array_slice($pares, 0, 5)) . '.';
    }

    public function prepararTextoParaVoz(string $texto): string
    {
        $limpio = preg_replace('/[\*\#\_`~>]/', '', $texto);
        $limpio = preg_replace('/\bBs\.?\s*/i', 'bolivianos ', (string) $limpio);
        $limpio = preg_replace('/\b(?:c[óo]digo\s+)?OEM\b/iu', 'código O E M', (string) $limpio);
        $limpio = preg_replace('/\bSKU\b/i', 'código interno', (string) $limpio);
        $limpio = preg_replace('/\n+/', '. ', (string) $limpio);
        return trim((string) preg_replace('/\s+/', ' ', (string) $limpio));
    }

    private function normalizarTexto(string $texto): string
    {
        $min = mb_strtolower($texto, 'UTF-8');
        return strtr($min, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u']);
    }

    private function normalizarFonetica(string $norm): string
    {
        $reemplazos = [
            '/\bpastiyas?\b/u'           => 'pastilla',
            '/\bpastias?\b/u'            => 'pastilla',
            '/\b(?:de\s+)?adelante\b/u'  => 'delantero',
            '/\bdelante\b/u'             => 'delantero',
            '/\b(?:de\s+)?atras\b/u'     => 'trasero',
            '/\baseite\b/u'              => 'aceite',
            '/\bcoroya\b/u'              => 'corolla',
            '/\bjailux\b/u'              => 'hilux',
            '/\bnisan\b/u'               => 'nissan',
            '/\bsusuki\b/u'              => 'suzuki',
            '/\bmitsubichi\b/u'          => 'mitsubishi',
            '/\b(?:hyunday|jiundai)\b/u' => 'hyundai',
            '/\bbosh\b/u'                => 'bosch',
            '/\bbitara\b/u'              => 'vitara',
            '/\bamortiguadores?\b/u'     => 'amortiguador',
            '/\bbujias?\b/u'             => 'bujia',
            '/\bfiltros?\b/u'            => 'filtro',
            '/\bfrenos?\b/u'             => 'freno',
        ];
        $out = $norm;
        foreach ($reemplazos as $patron => $reemplazo) {
            $out = (string) preg_replace($patron, $reemplazo, $out);
        }
        return $out;
    }

    private function extraerTerminosProducto(string $norm): array
    {
        $limpio = preg_replace('/[^a-z0-9\-ñ\s]/u', ' ', $norm);
        $partes = preg_split('/\s+/', trim((string) $limpio)) ?: [];
        $stopwords = [
            'que', 'cual', 'cuales', 'como', 'donde', 'cuanto', 'cuantos', 'cuantas',
            'hay', 'tiene', 'tienen', 'tenemos', 'mostrar', 'buscar', 'busca', 'dime',
            'del', 'de', 'la', 'el', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'para', 'por', 'con', 'en', 'al', 'repuesto', 'repuestos', 'pieza', 'piezas',
            'dame', 'ponme', 'poneme', 'quiero', 'necesito', 'vender', 'vende', 'vendeme',
            'vendele', 'cotizar', 'cotiza', 'comprar', 'compra', 'registrar', 'registra', 'venta',
            'juegos', 'juego', 'unidades', 'unidad', 'uds', 'pzs', 'par', 'pares',
            'primera', 'primero', 'segunda', 'segundo', 'tercera', 'tercero',
            'viejo', 'nuevo', 'favor', 'esta', 'estas', 'ese', 'esa', 'eso',
            'efectivo', 'tarjeta', 'transferencia', 'mixto', 'contado', 'credito',
            'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'amigo', 'taller', 'cliente', 'descuento', 'bolivianos',
        ];

        $terminos = [];
        foreach ($partes as $p) {
            if (mb_strlen($p) >= 2 && !ctype_digit($p) && !in_array($p, $stopwords, true)) {
                $terminos[] = $p;
            }
        }
        return array_values(array_unique($terminos));
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
            'favor', 'quiero', 'saber', 'necesito', 'esta', 'estan', 'son', 'esto',
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
