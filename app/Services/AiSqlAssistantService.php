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

    private const SESSION_HISTORY_KEY = 'ai_chat_history';
    private const SESSION_DRAFT_KEY   = 'ai_tx_draft';
    private const MAX_HISTORY_TURNS   = 8;

    /**
     * Punto de entrada principal conversacional (multi-turno + limpieza de voz + slot-filling + Gemini 2.5/2.0 + motor local).
     */
    public function procesarConsulta(string $pregunta, ?object $usuario = null): array
    {
        $preguntaOriginal = trim($pregunta);
        $preguntaLimpia = $this->limpiarMuletillasVoz($preguntaOriginal);
        $norm = $this->normalizarFonetica($this->normalizarTexto($preguntaLimpia));

        $rolId = (int) ($usuario->id_rol ?? 1);
        $nombreRol = $usuario->rol->nombre_rol ?? $this->obtenerNombreRol($rolId);
        $userId = (int) ($usuario->id_usuario ?? 1);

        // 0. Comandos rápidos de cancelación o reinicio de charla
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

        // Recuperar borrador transaccional activo en sesión (expira tras 15 min)
        $draft = $this->obtenerBorradorActivo($userId);

        // Saludos o agradecimientos sencillos cuando NO hay un borrador esperando respuesta
        if (!$draft && $this->esSaludoOConversacionBasica($norm)) {
            $resp = $this->responderSaludoAmigable($norm, $usuario);
            $this->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        // Si hay borrador activo, verificar si el usuario cambió de tema hacia una consulta analítica
        if ($draft && $this->esConsultaAnaliticaExplicita($norm) && !$this->esRespuestaAlBorradorActual($draft, $norm)) {
            $this->limpiarBorradorOperacion();
            $draft = null;
        }

        // 1. Si hay un borrador en curso (slot-filling multi-turno)
        if ($draft !== null) {
            $respuestaDraft = $this->continuarBorradorConversacional(
                $draft,
                $preguntaOriginal,
                $preguntaLimpia,
                $norm,
                $rolId,
                $nombreRol,
                $userId
            );
            $this->registrarTurnoHistorial($preguntaOriginal, $respuestaDraft['texto_pantalla'] ?? '');
            return $respuestaDraft;
        }

        // 2. Detectar si el usuario inicia una operación transaccional (aunque esté incompleta)
        $operacionDetectada = $this->detectarOperacionTransaccional($norm);
        $esIntencionEscritura = $operacionDetectada !== null;

        // 3. Verificar restricciones de rol para consultas analíticas (si no es escritura)
        if (!$esIntencionEscritura) {
            $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
            if ($bloqueoRol !== null) {
                $this->registrarTurnoHistorial($preguntaOriginal, $bloqueoRol['texto_pantalla']);
                return $bloqueoRol;
            }
        }

        // 4. Vía A: Motor Google Gemini (gemini-2.5-flash -> fallback gemini-2.0-flash) con historial multi-turno
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

        // 5. Vía B (Transaccional local flexible con slot-filling multi-turno)
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

        // 6. Verificar bloqueo de rol antes de motor analítico local
        $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
        if ($bloqueoRol !== null) {
            $this->registrarTurnoHistorial($preguntaOriginal, $bloqueoRol['texto_pantalla']);
            return $bloqueoRol;
        }

        // 7. Vía B (Analítica): Motor Semántico Local Resiliente en PHP
        $respLocal = $this->consultarViaMotorLocal($preguntaLimpia, $norm, $rolId, $nombreRol);
        $this->registrarTurnoHistorial($preguntaOriginal, $respLocal['texto_pantalla'] ?? '');
        return $respLocal;
    }

    /**
     * Limpia únicamente el borrador de operación pendiente.
     */
    public function limpiarBorradorOperacion(): void
    {
        session()->forget(self::SESSION_DRAFT_KEY);
    }

    /**
     * Limpia el historial de chat y el borrador de operación en curso.
     */
    public function limpiarConversacionCompleta(): void
    {
        session()->forget([self::SESSION_HISTORY_KEY, self::SESSION_DRAFT_KEY]);
    }

    /**
     * Limpia muletillas de voz frecuentes y puntuación errática de la Web Speech API.
     */
    public function limpiarMuletillasVoz(string $texto): string
    {
        $limpio = trim($texto);
        $limpio = preg_replace('/[“”"\'´`]+/u', ' ', $limpio);
        $limpio = preg_replace('/\.{2,}|,{2,}|!{2,}|\?{2,}/u', ' ', $limpio);

        $muletillas = [
            'hazme un favor',
            'haceme un favor',
            'por favor',
            'porfa',
            'quiero que',
            'o sea',
            'osea',
            'a ver',
            'aver',
            'como se dice',
            'asi nomas',
            'así nomás',
            'hermanito',
            'hermano',
            'amigo',
            'disculpa',
            'perdón',
            'perdon',
            'escucha',
            'digamos',
            'bueno',
            'pucha',
            'pues',
            'este',
            'mira',
            'oye',
            'che',
            'ehhh',
            'ehh',
            'eh',
            'mmmm',
            'mmm',
            'mm',
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

    /**
     * Paso 2 del Modo Transaccional Asistido: Ejecuta el procedimiento real de base de datos
     * tras confirmación explícita del usuario mediante action_token.
     */
    public function ejecutarAccionConfirmada(array $payload, int $userId, int $rolId, string $nombreRol, string $ipOrigen): array
    {
        $operacion = $payload['operacion'] ?? '';
        $this->limpiarBorradorOperacion();

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
        $texto = "¡Listo! Ya quedó registrada la venta #{$folio} para {$payload['cliente_nombre']} por Bs. " . number_format($totalNeto, 2) . " ({$metodoPago}). Ya se descontó el stock del almacén y se sumó a la caja #{$idSesion}. ¿Quieres imprimir el ticket ahora?";

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
        $texto = "¡Perfecto! Ya emití la cotización #{$folio} para {$payload['cliente_nombre']} por Bs. " . number_format($total, 2) . ", válida por {$dias} días. Puedes verla, imprimirla o bajar el PDF aquí abajo.";

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
        $stockMsg = $stockRes !== null ? " Ahora te quedan {$stockRes} unidades en almacén." : '';
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

    // ═════════════════════════════════════════════════════════════════════════
    // MOTOR CONVERSACIONAL MULTI-TURNO (SLOT-FILLING + DISOLUCIÓN DE AMBIGÜEDAD)
    // ═════════════════════════════════════════════════════════════════════════

    private function obtenerBorradorActivo(int $userId): ?array
    {
        $draft = session()->get(self::SESSION_DRAFT_KEY);
        if (!is_array($draft)) {
            return null;
        }
        if ((int) ($draft['user_id'] ?? 0) !== $userId) {
            return null;
        }
        // Expirar borrador inactivo tras 15 minutos
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

    private function crearBorradorVacio(string $operacion, int $userId): array
    {
        return [
            'operacion'             => $operacion,
            'user_id'               => $userId,
            'updated_at'            => now()->timestamp,
            'cliente_id'            => null,
            'cliente_nombre'        => null,
            'cliente_ci_nit'        => null,
            'cliente_tipo'          => null,
            'cliente_descuento_pct' => 0.0,
            'cliente_confirmado'    => false,
            'descuento_preguntado'  => false,
            'descuento_resuelto'    => false,
            'descuento_total'       => 0.0,
            'metodo_pago'           => null,
            'dias_vigencia'         => 7,
            'proveedor_id'          => null,
            'proveedor_nombre'      => null,
            'proveedores_opciones'  => [],
            'numero_factura_nota'   => null,
            'tipo_pago'             => 'Contado',
            'tipo_ajuste'           => null,
            'motivo'                => null,
            'ajuste_cantidad'       => null,
            'ajuste_producto'       => null,
            'items'                 => [],
            'pending_cantidad'      => null,
            'pending_costo'         => null,
            'opciones_pendientes'   => [],
            'esperando'             => null,
        ];
    }

    /**
     * Inicia un borrador a partir de un mensaje local (cuando Gemini no está disponible o devolvió null).
     */
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
            $msg = "Tu cuenta de {$nombreRol} no tiene permiso para {$this->etiquetaOperacionAmigable($operacion)}. Si la necesitas, pídele una mano al administrador.";
            return $this->respuestaRestriccion($msg);
        }

        $draft = $this->crearBorradorVacio($operacion, $userId);
        return $this->absorberMensajeEnBorrador($draft, $preguntaOriginal, $preguntaLimpia, $norm, $rolId, $nombreRol, $userId, 'local', true);
    }

    /**
     * Continúa un borrador en curso (Turno 2, 3, ...), combinando la respuesta del usuario con lo ya guardado.
     */
    private function continuarBorradorConversacional(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        // Primero verificar si el usuario quiere iniciar OTRA operación diferente explícitamente
        $nuevaOp = $this->detectarOperacionTransaccional($norm);
        if ($nuevaOp !== null && $nuevaOp !== $draft['operacion'] && empty($draft['opciones_pendientes'])) {
            $this->limpiarBorradorOperacion();
            $nuevoDraft = $this->crearBorradorVacio($nuevaOp, $userId);
            return $this->absorberMensajeEnBorrador($nuevoDraft, $preguntaOriginal, $preguntaLimpia, $norm, $rolId, $nombreRol, $userId, 'local', true);
        }

        // Si Gemini está configurado y no es una selección numérica simple o respuesta de sí/no/pago directa,
        // permitir que Gemini extraiga campos ricos y los fusione con el borrador actual.
        $esRespuestaSimple = $this->esRespuestaDeterministicaSimple($draft, $norm);
        $apiKey = config('services.gemini.key');
        if (!empty($apiKey) && !$esRespuestaSimple) {
            try {
                $resGemini = $this->consultarViaGemini($preguntaOriginal, $preguntaLimpia, $apiKey, $rolId, $nombreRol, $userId, $draft);
                if ($resGemini !== null) {
                    return $resGemini;
                }
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService (Turno borrador Gemini): ' . $e->getMessage());
            }
        }

        return $this->absorberMensajeEnBorrador($draft, $preguntaOriginal, $preguntaLimpia, $norm, $rolId, $nombreRol, $userId, 'local', false);
    }

    /**
     * Fusiona datos extraídos del mensaje natural del usuario dentro del borrador y avanza al siguiente paso.
     */
    private function absorberMensajeEnBorrador(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId,
        string $origen,
        bool $esPrimerTurno
    ): array {
        $operacion = $draft['operacion'];
        $esperando = $draft['esperando'] ?? null;

        // 1. Si estábamos esperando respuesta sobre descuento frecuente de taller
        if ($esperando === 'descuento_taller') {
            $bruto = round(array_sum(array_column($draft['items'], 'subtotal')), 2);
            if ($this->esRespuestaAfirmativa($norm)) {
                $pct = (float) ($draft['cliente_descuento_pct'] ?? 0);
                $draft['descuento_total'] = round($bruto * ($pct / 100.0), 2);
                $draft['descuento_resuelto'] = true;
                $draft['esperando'] = null;
            } elseif ($this->esRespuestaNegativa($norm)) {
                $draft['descuento_total'] = 0.0;
                $draft['descuento_resuelto'] = true;
                $draft['esperando'] = null;
            } elseif (preg_match('/(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento)?/u', $norm, $mD)) {
                $val = (float) str_replace(',', '.', $mD[1]);
                $draft['descuento_total'] = !empty($mD[2]) ? round($bruto * ($val / 100.0), 2) : round($val, 2);
                $draft['descuento_resuelto'] = true;
                $draft['esperando'] = null;
            }
        }

        // 2. Si estábamos esperando selección entre varios repuestos parecidos
        if ($esperando === 'seleccion_producto' && !empty($draft['opciones_pendientes'])) {
            $seleccionado = $this->resolverSeleccionDeOpciones($draft['opciones_pendientes'], $norm);
            if ($seleccionado !== null) {
                $cantExtraida = $this->extraerCantidadDeTexto($norm, true);
                $cantidad = $cantExtraida ?? ($draft['pending_cantidad'] ?: 1);

                if ($operacion === 'preparar_ajuste_inventario') {
                    $draft['ajuste_producto'] = $seleccionado;
                    if ($cantExtraida !== null) {
                        $draft['ajuste_cantidad'] = $cantExtraida;
                    }
                } else {
                    $costoExtraido = $this->extraerCostoDeTexto($norm);
                    $costo = $costoExtraido ?? $draft['pending_costo'];
                    $draft['items'][] = $this->armarItemDesdeProducto(
                        $seleccionado,
                        $cantidad,
                        $operacion === 'preparar_compra',
                        $costo
                    );
                }

                $draft['opciones_pendientes'] = [];
                $draft['pending_cantidad'] = null;
                $draft['pending_costo'] = null;
                $draft['esperando'] = null;
            } else {
                // Tal vez el usuario dio un filtro más preciso (ej. "las del Corolla, 2 juegos")
                $cantidadNueva = $this->extraerCantidadDeTexto($norm, false);
                if ($cantidadNueva !== null) {
                    $draft['pending_cantidad'] = $cantidadNueva;
                }
                $subCandidatos = $this->filtrarOpcionesPorTexto($draft['opciones_pendientes'], $norm);
                if (count($subCandidatos) === 1) {
                    $elegido = $subCandidatos[0];
                    $cantidad = $draft['pending_cantidad'] ?: 1;
                    if ($operacion === 'preparar_ajuste_inventario') {
                        $draft['ajuste_producto'] = $elegido;
                        if ($cantidadNueva !== null) {
                            $draft['ajuste_cantidad'] = $cantidadNueva;
                        }
                    } else {
                        $costo = $this->extraerCostoDeTexto($norm) ?? $draft['pending_costo'];
                        $draft['items'][] = $this->armarItemDesdeProducto($elegido, $cantidad, $operacion === 'preparar_compra', $costo);
                    }
                    $draft['opciones_pendientes'] = [];
                    $draft['pending_cantidad'] = null;
                    $draft['pending_costo'] = null;
                    $draft['esperando'] = null;
                }
            }
        }

        // 3. Si estábamos esperando proveedor (Compras)
        if ($operacion === 'preparar_compra' && empty($draft['proveedor_id'])) {
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
                $draft['proveedor_id'] = (int) $prov->id_proveedor;
                $draft['proveedor_nombre'] = $prov->razon_social;
                $draft['proveedores_opciones'] = [];
                if ($esperando === 'proveedor') {
                    $draft['esperando'] = null;
                }
            }
        }

        // 4. Extraer datos específicos de cada operación presentes en el mensaje
        if ($operacion === 'preparar_venta') {
            $metodo = $this->detectarMetodoPagoExplicito($norm, $esperando === 'metodo_pago');
            if ($metodo !== null) {
                $draft['metodo_pago'] = $metodo;
                if ($esperando === 'metodo_pago') {
                    $draft['esperando'] = null;
                }
            }

            if (preg_match('/descuento\s+(?:de\s+)?(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento|bs|bolivianos)?/u', $norm, $mDesc)) {
                $valor = (float) str_replace(',', '.', $mDesc[1]);
                if (!empty($mDesc[2]) && str_contains($mDesc[2], '%')) {
                    $draft['descuento_pct_solicitado'] = $valor;
                } else {
                    $draft['descuento_total'] = round($valor, 2);
                }
                $draft['descuento_resuelto'] = true;
            }
        }

        if ($operacion === 'preparar_cotizacion') {
            if (preg_match('/\b(\d{1,2})\s*dias?\b/u', $norm, $mDias)) {
                $draft['dias_vigencia'] = max(1, min(90, (int) $mDias[1]));
            }
        }

        if ($operacion === 'preparar_compra') {
            if (preg_match('/\bfactura\s+(?:n[°º#]?\s*)?([a-z0-9\-]{2,30})/i', $preguntaLimpia, $mFac)) {
                $draft['numero_factura_nota'] = strtoupper($mFac[1]);
            }
            if (str_contains($norm, 'credito') || str_contains($norm, 'fiado')) {
                $draft['tipo_pago'] = 'Crédito';
            } elseif (str_contains($norm, 'contado')) {
                $draft['tipo_pago'] = 'Contado';
            }

            // Si estábamos esperando el costo unitario del último ítem
            if ($esperando === 'costo_unitario' && !empty($draft['items'])) {
                $costo = $this->extraerCostoDeTexto($norm, true);
                if ($costo !== null && $costo > 0) {
                    $lastIdx = count($draft['items']) - 1;
                    $draft['items'][$lastIdx]['precio_unitario'] = round($costo, 2);
                    $draft['items'][$lastIdx]['subtotal'] = round($draft['items'][$lastIdx]['cantidad'] * $costo, 2);
                    $draft['items'][$lastIdx]['costo_confirmado'] = true;
                    $draft['esperando'] = null;
                }
            }
        }

        if ($operacion === 'preparar_ajuste_inventario') {
            $tipoAj = $this->detectarTipoAjusteExplicito($norm);
            if ($tipoAj !== null) {
                $draft['tipo_ajuste'] = $tipoAj;
                if ($esperando === 'tipo_ajuste') {
                    $draft['esperando'] = null;
                }
            }
            if ($esperando === 'cantidad' && empty($draft['ajuste_cantidad'])) {
                $c = $this->extraerCantidadDeTexto($norm, false);
                if ($c !== null) {
                    $draft['ajuste_cantidad'] = $c;
                    $draft['esperando'] = null;
                }
            }
            if (empty($draft['motivo'])) {
                if (preg_match('/\b(?:porque|por motivo|motivo|se\s+rompi|se\s+dañ|se\s+dan|se\s+perdi|extravi|para\s+el\s+taller|uso\s+de\s+tienda|golpead|quebrad|fallad)(.*)$/iu', $preguntaLimpia, $mMot)) {
                    $draft['motivo'] = trim($mMot[0]);
                } elseif (!$esPrimerTurno && $esperando === 'motivo') {
                    $draft['motivo'] = trim($preguntaLimpia);
                }
            }
        }

        // 5. Cliente (en venta o cotización): detectar si mencionó cliente o dijo "normal / cualquiera / no me acuerdo"
        if (in_array($operacion, ['preparar_venta', 'preparar_cotizacion'], true)) {
            if ($this->indicaClientePorDefecto($norm)) {
                $cliDefault = $this->obtenerClienteOcasionalMostrador();
                $draft['cliente_id'] = (int) $cliDefault->id_cliente;
                $draft['cliente_nombre'] = $cliDefault->nombre_razon_social;
                $draft['cliente_ci_nit'] = $cliDefault->ci_nit ?: 'S/N';
                $draft['cliente_tipo'] = $cliDefault->tipo_cliente ?? 'Particular';
                $draft['cliente_descuento_pct'] = 0.0;
                $draft['cliente_confirmado'] = true;
            } elseif (empty($draft['cliente_id'])) {
                $clienteEncontrado = $this->detectarClienteRegistradoEnTexto($preguntaLimpia, $norm);
                if ($clienteEncontrado) {
                    $draft['cliente_id'] = (int) $clienteEncontrado->id_cliente;
                    $draft['cliente_nombre'] = $clienteEncontrado->nombre_razon_social;
                    $draft['cliente_ci_nit'] = $clienteEncontrado->ci_nit ?: 'S/N';
                    $draft['cliente_tipo'] = $clienteEncontrado->tipo_cliente ?? 'Particular';
                    $draft['cliente_descuento_pct'] = (float) ($clienteEncontrado->descuento_frecuente ?? 0);
                    $draft['cliente_confirmado'] = true;
                }
            }
        }

        // 6. Buscar repuesto(s) si todavía no tenemos producto en el borrador
        $necesitaProducto = $operacion === 'preparar_ajuste_inventario'
            ? empty($draft['ajuste_producto'])
            : empty($draft['items']);

        if ($necesitaProducto && empty($draft['opciones_pendientes'])) {
            $cantidadDetectada = $this->extraerCantidadDeTexto($norm, false);
            if ($cantidadDetectada !== null) {
                $draft['pending_cantidad'] = $cantidadDetectada;
                if ($operacion === 'preparar_ajuste_inventario') {
                    $draft['ajuste_cantidad'] = $cantidadDetectada;
                }
            }
            $costoDetectado = $operacion === 'preparar_compra' ? $this->extraerCostoDeTexto($norm, false) : null;
            if ($costoDetectado !== null) {
                $draft['pending_costo'] = $costoDetectado;
            }

            $fragProd = $this->extraerFragmentoProductoLimpio($norm, $operacion);
            if ($fragProd !== '') {
                $busqueda = $this->buscarCandidatosProducto($fragProd);
                if ($busqueda['estado'] === 'unico') {
                    $prodUnico = $busqueda['producto'];
                    $cant = $draft['pending_cantidad'] ?: 1;
                    if ($operacion === 'preparar_ajuste_inventario') {
                        $draft['ajuste_producto'] = $prodUnico;
                        if ($draft['ajuste_cantidad'] === null && $cantidadDetectada !== null) {
                            $draft['ajuste_cantidad'] = $cantidadDetectada;
                        }
                    } else {
                        $draft['items'][] = $this->armarItemDesdeProducto(
                            $prodUnico,
                            $cant,
                            $operacion === 'preparar_compra',
                            $draft['pending_costo']
                        );
                    }
                    $draft['pending_cantidad'] = null;
                    $draft['pending_costo'] = null;
                    $draft['esperando'] = null;
                } elseif ($busqueda['estado'] === 'ambiguo') {
                    $draft['opciones_pendientes'] = $busqueda['opciones'];
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->construirMensajeDesambiguacion($busqueda['opciones'], $fragProd, $origen);
                } elseif (!$esPrimerTurno && $esperando === 'producto') {
                    $this->guardarBorrador($draft);
                    $msg = "Mmm, no encontré ningún repuesto parecido a \"{$preguntaLimpia}\" en el inventario activo. ¿Me dices el nombre de otra forma o para qué vehículo es (por ejemplo Corolla, Hilux o Vitara)?";
                    return $this->respuestaAclaracion($msg, $origen);
                }
            }
        }

        // 7. Evaluar qué falta o si ya estamos listos para mostrar la Tarjeta de Confirmación
        return $this->evaluarEstadoBorrador($draft, $userId, $origen);
    }

    /**
     * Evalúa el borrador acumulado: si falta algún dato indispensable pregunta amablemente;
     * si ya está completo, genera la Tarjeta de Confirmación Humana y limpia el borrador.
     */
    private function evaluarEstadoBorrador(array $draft, int $userId, string $origen): array
    {
        $op = $draft['operacion'];

        // ─── VENTA ───
        if ($op === 'preparar_venta') {
            // Revisar si hay caja abierta antes de hacer trabajar de más al dueño
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
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], 'repuestos', $origen);
            }

            if (empty($draft['items'])) {
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                $msg = '¡Con gusto te ayudo con la venta! ¿Qué repuesto vas a vender (o para qué vehículo) y cuántas unidades?';
                return $this->respuestaAclaracion($msg, $origen);
            }

            // Aplicar descuento porcentual si el usuario lo pidió antes de resolver el producto
            if (!empty($draft['descuento_pct_solicitado'])) {
                $brutoTmp = round(array_sum(array_column($draft['items'], 'subtotal')), 2);
                $draft['descuento_total'] = round($brutoTmp * ((float) $draft['descuento_pct_solicitado'] / 100.0), 2);
                unset($draft['descuento_pct_solicitado']);
            }

            // Si el cliente es taller/empresa con descuento frecuente y aún no se preguntó
            if (
                !empty($draft['cliente_id'])
                && (float) ($draft['cliente_descuento_pct'] ?? 0) > 0
                && !$draft['descuento_resuelto']
                && !$draft['descuento_preguntado']
            ) {
                $bruto = round(array_sum(array_column($draft['items'], 'subtotal')), 2);
                $pct = (float) $draft['cliente_descuento_pct'];
                $montoDesc = round($bruto * ($pct / 100.0), 2);
                $draft['descuento_preguntado'] = true;
                $draft['esperando'] = 'descuento_taller';
                $this->guardarBorrador($draft);
                $msg = "Vi que {$draft['cliente_nombre']} tiene un {$pct}% de descuento frecuente (serían Bs. " . number_format($montoDesc, 2) . " menos). ¿Se lo aplico a esta venta?";
                return $this->respuestaAclaracion($msg, $origen);
            }

            // Si falta método de pago, preguntar de forma cercana y natural
            if (empty($draft['metodo_pago'])) {
                $bruto = round(array_sum(array_column($draft['items'], 'subtotal')), 2);
                $neto = round(max(0.0, $bruto - (float) $draft['descuento_total']), 2);
                $listaTxt = implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $draft['items']));
                $draft['esperando'] = 'metodo_pago';
                $this->guardarBorrador($draft);
                $msg = "¡Genial! Anoté {$listaTxt} por Bs. " . number_format($neto, 2) . ". ¿Cómo te va a pagar: en efectivo, QR o tarjeta?";
                return $this->respuestaAclaracion($msg, $origen);
            }

            // Cliente por defecto silencioso si no se nombró ninguno
            $cliente = !empty($draft['cliente_id'])
                ? (object) [
                    'id_cliente'          => $draft['cliente_id'],
                    'nombre_razon_social' => $draft['cliente_nombre'],
                    'ci_nit'              => $draft['cliente_ci_nit'] ?: 'S/N',
                ]
                : $this->obtenerClienteOcasionalMostrador();

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaVenta($cliente, $draft['items'], (float) $draft['descuento_total'], (string) $draft['metodo_pago'], $sesionAbierta, $userId, $origen);
        }

        // ─── COTIZACIÓN ───
        if ($op === 'preparar_cotizacion') {
            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], 'repuestos', $origen);
            }

            if (empty($draft['items'])) {
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                $msg = '¡Claro que sí! Armemos la cotización. ¿Qué repuesto necesitas cotizar y cuántas unidades?';
                return $this->respuestaAclaracion($msg, $origen);
            }

            $cliente = !empty($draft['cliente_id'])
                ? (object) [
                    'id_cliente'          => $draft['cliente_id'],
                    'nombre_razon_social' => $draft['cliente_nombre'],
                    'ci_nit'              => $draft['cliente_ci_nit'] ?: 'S/N',
                ]
                : $this->obtenerClienteOcasionalMostrador();

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaCotizacion($cliente, $draft['items'], (int) ($draft['dias_vigencia'] ?: 7), $userId, $origen);
        }

        // ─── COMPRA ───
        if ($op === 'preparar_compra') {
            if (empty($draft['proveedor_id'])) {
                $provs = DB::table('proveedores')->where('estado', true)->orderBy('razon_social')->limit(8)->get();
                $opcionesProv = [];
                $lineas = [];
                foreach ($provs as $idx => $p) {
                    $num = $idx + 1;
                    $opcionesProv[] = ['id_proveedor' => (int) $p->id_proveedor, 'razon_social' => $p->razon_social];
                    $lineas[] = "{$num}. {$p->razon_social}";
                }
                $draft['proveedores_opciones'] = $opcionesProv;
                $draft['esperando'] = 'proveedor';
                $this->guardarBorrador($draft);

                $msg = !empty($lineas)
                    ? "¡Con gusto registramos la compra! ¿A qué proveedor le compraste?\n" . implode("\n", $lineas) . "\nPuedes decirme el número o el nombre."
                    : '¡Con gusto registramos la compra! ¿Cuál es el nombre del proveedor?';
                return $this->respuestaAclaracion($msg, $origen);
            }

            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], 'repuestos', $origen);
            }

            if (empty($draft['items'])) {
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                $msg = "Perfecto, anoté al proveedor {$draft['proveedor_nombre']}. ¿Qué repuesto ingresó, cuántas unidades y a qué costo unitario en Bs.?";
                return $this->respuestaAclaracion($msg, $origen);
            }

            // Verificar que el ítem tenga costo unitario confirmado
            $ultimo = $draft['items'][count($draft['items']) - 1];
            if (empty($ultimo['costo_confirmado']) || (float) $ultimo['precio_unitario'] <= 0) {
                $draft['esperando'] = 'costo_unitario';
                $this->guardarBorrador($draft);
                $msg = "Anoté {$ultimo['cantidad']} unidad(es) de {$ultimo['nombre_pieza']}. ¿A cuánto te salió cada unidad (en Bs.)?";
                return $this->respuestaAclaracion($msg, $origen);
            }

            $factura = trim((string) ($draft['numero_factura_nota'] ?? ''));
            if (mb_strlen($factura) < 2) {
                $factura = 'S/F-' . date('Ymd-Hi');
            }

            $proveedor = (object) [
                'id_proveedor' => $draft['proveedor_id'],
                'razon_social' => $draft['proveedor_nombre'],
            ];

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaCompra($proveedor, $draft['items'], $factura, (string) ($draft['tipo_pago'] ?: 'Contado'), $userId, $origen);
        }

        // ─── AJUSTE / MERMA ───
        if ($op === 'preparar_ajuste_inventario') {
            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->construirMensajeDesambiguacion($draft['opciones_pendientes'], 'repuestos', $origen);
            }

            if (empty($draft['ajuste_producto'])) {
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                $msg = '¡Claro! Vamos a registrar el ajuste en almacén. ¿De qué repuesto se trata y cuántas unidades?';
                return $this->respuestaAclaracion($msg, $origen);
            }

            if (empty($draft['tipo_ajuste'])) {
                $draft['esperando'] = 'tipo_ajuste';
                $this->guardarBorrador($draft);
                $msg = "¿Qué tipo de ajuste es para {$draft['ajuste_producto']['nombre_pieza']}: merma (dañado), uso interno, pérdida o conteo físico?";
                return $this->respuestaAclaracion($msg, $origen);
            }

            if (empty($draft['ajuste_cantidad'])) {
                $draft['esperando'] = 'cantidad';
                $this->guardarBorrador($draft);
                $msg = "¿Cuántas unidades de {$draft['ajuste_producto']['nombre_pieza']} anotamos para este ajuste de {$draft['tipo_ajuste']}?";
                return $this->respuestaAclaracion($msg, $origen);
            }

            $productoObj = (object) $draft['ajuste_producto'];
            $tipoAjuste = (string) $draft['tipo_ajuste'];
            $cantidad = max(1, (int) $draft['ajuste_cantidad']);
            $motivo = $this->formatearMotivoAjuste($tipoAjuste, (string) ($draft['motivo'] ?? ''), $productoObj->nombre_pieza);

            $this->limpiarBorradorOperacion();
            return $this->generarTarjetaAjuste($productoObj, $tipoAjuste, $cantidad, $motivo, $userId, $origen);
        }

        $this->limpiarBorradorOperacion();
        return $this->respuestaInformativa('¿Me repites qué te gustaría hacer? Puedo ayudarte con ventas, cotizaciones, compras, ajustes o consultas de inventario.', $origen);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // GENERACIÓN DE TARJETAS DE CONFIRMACIÓN (LENGUAJE COTIDIANO, CERO TECNICISMOS)
    // ═════════════════════════════════════════════════════════════════════════

    private function generarTarjetaVenta(object $cliente, array $items, float $descuento, string $metodoPago, object $sesionAbierta, int $userId, string $origen): array
    {
        $bruto = round(array_sum(array_column($items, 'subtotal')), 2);
        $descuento = round(max(0.0, min($bruto, $descuento)), 2);
        $neto = round(max(0.0, $bruto - $descuento), 2);
        $metodoPago = $this->normalizarMetodoPago($metodoPago);

        $advertencias = [];
        foreach ($items as $it) {
            if ((int) $it['cantidad'] > (int) $it['stock_actual']) {
                $advertencias[] = "Ojo: de {$it['nombre_pieza']} solo nos quedan {$it['stock_actual']} en almacén y estás pidiendo {$it['cantidad']}.";
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
            'items'           => $items,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $resumenItems = implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $items));
        $texto = "¡Todo listo! Te armé esta nota de venta para {$cliente->nombre_razon_social}: {$resumenItems}, por un total de Bs. " . number_format($neto, 2) . " ({$metodoPago}). Si está todo bien, dale a Confirmar y Ejecutar.";

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
                'resumen'      => [
                    'Cliente'    => $cliente->nombre_razon_social . ' (' . ($cliente->ci_nit ?: 'S/N') . ')',
                    'Caja'       => "Caja #{$sesionAbierta->id_sesion} (Abierta)",
                    'Forma Pago' => $metodoPago,
                    'Descuento'  => 'Bs. ' . number_format($descuento, 2),
                    'Total'      => 'Bs. ' . number_format($neto, 2),
                ],
                'items'        => $items,
                'total_neto'   => $neto,
                'advertencia'  => !empty($advertencias) ? implode(' ', $advertencias) : null,
            ],
        ];
    }

    private function generarTarjetaCotizacion(object $cliente, array $items, int $diasVigencia, int $userId, string $origen): array
    {
        $diasVigencia = max(1, min(90, $diasVigencia));
        $total = round(array_sum(array_column($items, 'subtotal')), 2);

        $advertencias = [];
        foreach ($items as $it) {
            if ((int) $it['cantidad'] > (int) $it['stock_actual']) {
                $advertencias[] = "Dato: de {$it['nombre_pieza']} hoy tenemos {$it['stock_actual']} unidades en stock.";
            }
        }

        $payload = [
            'operacion'      => 'preparar_cotizacion',
            'user_id'        => $userId,
            'created_at'     => now()->timestamp,
            'id_cliente'     => (int) $cliente->id_cliente,
            'cliente_nombre' => $cliente->nombre_razon_social,
            'dias_vigencia'  => $diasVigencia,
            'total_neto'     => $total,
            'items'          => $items,
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
                    'Cliente'   => $cliente->nombre_razon_social,
                    'Vigencia'  => "{$diasVigencia} días (hasta " . Carbon::today()->addDays($diasVigencia)->format('d/m/Y') . ')',
                    'Total'     => 'Bs. ' . number_format($total, 2),
                ],
                'items'        => $items,
                'total_neto'   => $total,
                'advertencia'  => !empty($advertencias) ? implode(' ', $advertencias) : null,
            ],
        ];
    }

    private function generarTarjetaCompra(object $proveedor, array $items, string $factura, string $tipoPago, int $userId, string $origen): array
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
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "¡Perfecto! Preparé el ingreso de compra a {$proveedor->razon_social} (Nota {$factura}, {$tipoPago}) por Bs. " . number_format($total, 2) . ". Confirma la tarjeta para sumar las unidades al inventario.";

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

    private function generarTarjetaAjuste(object $producto, string $tipoAjuste, int $cantidad, string $motivo, int $userId, string $origen): array
    {
        $advertencia = null;
        if ($tipoAjuste !== 'Conteo Físico' && $cantidad > (int) $producto->stock_actual) {
            $advertencia = "Ojo: hoy tenemos {$producto->stock_actual} unidades en almacén y estás descontando {$cantidad}. Revisa la cantidad antes de confirmar.";
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
            ? "dejar el stock en {$cantidad} unidades (antes: {$producto->stock_actual})"
            : "descontar {$cantidad} unidad(es) por {$tipoAjuste} (antes: {$producto->stock_actual})";
        $texto = "¡Listo! Preparé el ajuste para {$producto->nombre_pieza}: {$accionDesc}. Si todo está correcto, dale a Confirmar y Ejecutar.";

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

    // ═════════════════════════════════════════════════════════════════════════
    // VÍA A: GOOGLE GEMINI 2.5 FLASH -> FALLBACK 2.0 FLASH (CON HISTORIAL MULTI-TURNO)
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
                'parts' => [
                    ['text' => $this->construirSystemPrompt()],
                ],
            ],
            'tools' => [
                [
                    'functionDeclarations' => $this->construirFunctionDeclarations(),
                ],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => 0.25,
                'maxOutputTokens' => 750,
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
                Log::warning("AiSqlAssistantService: Modelo {$modelo} respondió HTTP {$response->status()}, intentando fallback.");
            } catch (\Throwable $e) {
                Log::warning("AiSqlAssistantService: Error llamando a {$modelo}: " . $e->getMessage());
            }
        }

        if (!is_array($responseJson)) {
            return null;
        }

        $parts = $responseJson['candidates'][0]['content']['parts'] ?? [];
        if (empty($parts)) {
            return null;
        }

        // 1. Function Call (Modo Transaccional Asistido fusionado con el slot-filler multi-turno)
        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                $fnName = (string) $part['functionCall']['name'];
                $fnArgs = is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [];
                return $this->procesarFunctionCallGemini($fnName, $fnArgs, $draftActivo, $rolId, $nombreRol, $userId);
            }
        }

        // 2. Respuesta textual / JSON analítico
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

        // Si Gemini devolvió respuesta conversacional natural (ej. aclaración o charla), mostrarla limpia
        if (is_array($parsed) && !empty($parsed['respuesta'])) {
            $textoLibre = $this->limpiarTecnicismosDeRespuesta((string) $parsed['respuesta']);
            return $this->respuestaAclaracion($textoLibre, 'gemini');
        }

        // Si es texto plano natural (sin bloques SQL ni código)
        if (!str_contains(strtoupper($rawText), 'SELECT ') && !str_contains($rawText, '{')) {
            $textoLibre = $this->limpiarTecnicismosDeRespuesta($rawText);
            return $this->respuestaAclaracion($textoLibre, 'gemini');
        }

        return null;
    }

    /**
     * Fusiona los argumentos devueltos por Gemini (incluso parciales) con el borrador de sesión
     * y aplica disolución de ambigüedades, valores por defecto y tarjeta de confirmación.
     */
    private function procesarFunctionCallGemini(
        string $fnName,
        array $args,
        ?array $draftActivo,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        if (!$this->rolPuedeEjecutarOperacion($rolId, $fnName)) {
            $msg = "Tu cuenta de {$nombreRol} no tiene permiso para {$this->etiquetaOperacionAmigable($fnName)}. Si la necesitas, pídele ayuda al administrador.";
            return $this->respuestaRestriccion($msg);
        }

        $draft = ($draftActivo && ($draftActivo['operacion'] ?? '') === $fnName)
            ? $draftActivo
            : $this->crearBorradorVacio($fnName, $userId);

        // Cliente
        if (!empty($args['cliente'])) {
            $cliTxt = trim((string) $args['cliente']);
            if ($this->indicaClientePorDefecto($this->normalizarTexto($cliTxt))) {
                $cliDefault = $this->obtenerClienteOcasionalMostrador();
                $draft['cliente_id'] = (int) $cliDefault->id_cliente;
                $draft['cliente_nombre'] = $cliDefault->nombre_razon_social;
                $draft['cliente_ci_nit'] = $cliDefault->ci_nit ?: 'S/N';
                $draft['cliente_descuento_pct'] = 0.0;
                $draft['cliente_confirmado'] = true;
            } else {
                $encontrado = $this->buscarClientePorTexto($cliTxt);
                if ($encontrado) {
                    $draft['cliente_id'] = (int) $encontrado->id_cliente;
                    $draft['cliente_nombre'] = $encontrado->nombre_razon_social;
                    $draft['cliente_ci_nit'] = $encontrado->ci_nit ?: 'S/N';
                    $draft['cliente_tipo'] = $encontrado->tipo_cliente ?? 'Particular';
                    $draft['cliente_descuento_pct'] = (float) ($encontrado->descuento_frecuente ?? 0);
                    $draft['cliente_confirmado'] = true;
                }
            }
        }

        // Método de pago (venta)
        if ($fnName === 'preparar_venta' && !empty($args['metodo_pago'])) {
            $draft['metodo_pago'] = $this->normalizarMetodoPago((string) $args['metodo_pago']);
        }
        if ($fnName === 'preparar_venta' && isset($args['descuento_total']) && (float) $args['descuento_total'] > 0) {
            $draft['descuento_total'] = round((float) $args['descuento_total'], 2);
            $draft['descuento_resuelto'] = true;
        }

        // Vigencia (cotización)
        if ($fnName === 'preparar_cotizacion' && !empty($args['dias_vigencia'])) {
            $draft['dias_vigencia'] = max(1, min(90, (int) $args['dias_vigencia']));
        }

        // Proveedor y factura (compra)
        if ($fnName === 'preparar_compra') {
            if (!empty($args['proveedor'])) {
                $prov = $this->resolverProveedor((string) $args['proveedor']);
                if ($prov) {
                    $draft['proveedor_id'] = (int) $prov->id_proveedor;
                    $draft['proveedor_nombre'] = $prov->razon_social;
                }
            }
            if (!empty($args['numero_factura_nota'])) {
                $draft['numero_factura_nota'] = trim((string) $args['numero_factura_nota']);
            }
            if (!empty($args['tipo_pago'])) {
                $draft['tipo_pago'] = str_contains($this->normalizarTexto((string) $args['tipo_pago']), 'cred') ? 'Crédito' : 'Contado';
            }
        }

        // Ajuste de inventario
        if ($fnName === 'preparar_ajuste_inventario') {
            if (!empty($args['tipo_ajuste'])) {
                $draft['tipo_ajuste'] = $this->normalizarTipoAjuste((string) $args['tipo_ajuste']);
            }
            if (!empty($args['cantidad_ajustada'])) {
                $draft['ajuste_cantidad'] = abs((int) $args['cantidad_ajustada']);
            }
            if (!empty($args['motivo'])) {
                $draft['motivo'] = trim((string) $args['motivo']);
            }
            if (!empty($args['producto'])) {
                $busq = $this->buscarCandidatosProducto((string) $args['producto']);
                if ($busq['estado'] === 'unico') {
                    $draft['ajuste_producto'] = $busq['producto'];
                    $draft['opciones_pendientes'] = [];
                } elseif ($busq['estado'] === 'ambiguo') {
                    $draft['opciones_pendientes'] = $busq['opciones'];
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->construirMensajeDesambiguacion($busq['opciones'], (string) $args['producto'], 'gemini');
                }
            }

            return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
        }

        // Ítems (venta, cotización, compra)
        if (!empty($args['items']) && is_array($args['items'])) {
            $nuevosItems = [];
            foreach ($args['items'] as $rawIt) {
                $prodTxt = trim((string) ($rawIt['producto'] ?? ''));
                $cant = max(1, (int) ($rawIt['cantidad'] ?? 1));
                $costo = isset($rawIt['costo_unitario']) && (float) $rawIt['costo_unitario'] > 0 ? (float) $rawIt['costo_unitario'] : null;

                if ($prodTxt === '') {
                    continue;
                }

                $busq = $this->buscarCandidatosProducto($prodTxt);
                if ($busq['estado'] === 'unico') {
                    $nuevosItems[] = $this->armarItemDesdeProducto($busq['producto'], $cant, $fnName === 'preparar_compra', $costo);
                } elseif ($busq['estado'] === 'ambiguo') {
                    if (!empty($nuevosItems)) {
                        $draft['items'] = array_merge($draft['items'], $nuevosItems);
                    }
                    $draft['pending_cantidad'] = $cant;
                    $draft['pending_costo'] = $costo;
                    $draft['opciones_pendientes'] = $busq['opciones'];
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->construirMensajeDesambiguacion($busq['opciones'], $prodTxt, 'gemini');
                } else {
                    $this->guardarBorrador($draft);
                    return $this->respuestaAclaracion(
                        "No encontré un repuesto que coincida con \"{$prodTxt}\" en tu inventario. ¿Me dices el nombre o para qué vehículo es?",
                        'gemini'
                    );
                }
            }

            if (!empty($nuevosItems)) {
                $draft['items'] = $nuevosItems;
                $draft['opciones_pendientes'] = [];
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
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }

        $contexto = "Rol actual: {$nombreRol}. Fecha de hoy: " . Carbon::today()->toDateString() . '.';
        if ($draftActivo) {
            $resumenBorrador = "Operación en curso: {$draftActivo['operacion']}";
            if (!empty($draftActivo['items'])) {
                $resumenBorrador .= ' | Ítems ya elegidos: ' . implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $draftActivo['items']));
            }
            if (!empty($draftActivo['opciones_pendientes'])) {
                $resumenBorrador .= ' | Opciones mostradas: ' . implode('; ', array_map(fn($o) => "{$o['indice']}. {$o['nombre_pieza']} ({$o['marca_fabricante']})", $draftActivo['opciones_pendientes']));
            }
            if (!empty($draftActivo['esperando'])) {
                $resumenBorrador .= " | Esperando dato: {$draftActivo['esperando']}";
            }
            $contexto .= ' ' . $resumenBorrador;
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [
                ['text' => "[Contexto: {$contexto}]\nMensaje del usuario: {$preguntaLimpia}" . ($preguntaLimpia !== $preguntaOriginal ? " (dictado original: {$preguntaOriginal})" : '')],
            ],
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

    /**
     * Declaraciones de herramientas (Function Calling) flexibles para operaciones paso a paso.
     */
    private function construirFunctionDeclarations(): array
    {
        return [
            [
                'name'        => 'preparar_venta',
                'description' => 'Inicia o actualiza el borrador de una Nota de Venta en mostrador. Puedes invocarla incluso si faltan datos (como cantidad, cliente o método de pago), porque el sistema guiará al dueño paso a paso.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente' => [
                            'type'        => 'STRING',
                            'description' => 'Nombre, taller o CI/NIT del cliente si se mencionó. Si dijo "cualquiera", "normal", "no me acuerdo" o no lo mencionó, omitir o usar "Cliente Ocasional Mostrador".',
                        ],
                        'items' => [
                            'type'  => 'ARRAY',
                            'items' => [
                                'type'       => 'OBJECT',
                                'properties' => [
                                    'producto' => ['type' => 'STRING', 'description' => 'Nombre del repuesto, vehículo o código OEM'],
                                    'cantidad' => ['type' => 'INTEGER', 'description' => 'Cantidad entera positiva (1 por defecto)'],
                                ],
                                'required' => ['producto'],
                            ],
                        ],
                        'descuento_total' => [
                            'type'        => 'NUMBER',
                            'description' => 'Descuento en Bolivianos (Bs.), 0 si no pide descuento.',
                        ],
                        'metodo_pago' => [
                            'type'        => 'STRING',
                            'description' => 'Solo incluir si el usuario lo indicó: Efectivo, Transferencia/QR, Tarjeta Débito/Crédito o Mixto.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_cotizacion',
                'description' => 'Inicia o actualiza una cotización o proforma comercial.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'cliente'       => ['type' => 'STRING', 'description' => 'Nombre del cliente o taller (opcional)'],
                        'dias_vigencia' => ['type' => 'INTEGER', 'description' => 'Días de vigencia (7 por defecto)'],
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
                'description' => 'Inicia o actualiza el ingreso de una compra a proveedor.',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'proveedor'           => ['type' => 'STRING', 'description' => 'Nombre del proveedor o distribuidor'],
                        'numero_factura_nota' => ['type' => 'STRING', 'description' => 'Número de factura o nota si se conoce'],
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
                                'required' => ['producto'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'preparar_ajuste_inventario',
                'description' => 'Inicia o actualiza un ajuste de inventario (merma, pérdida, uso interno, devolución dañada o conteo físico).',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'producto'          => ['type' => 'STRING', 'description' => 'Repuesto afectado'],
                        'cantidad_ajustada' => ['type' => 'INTEGER', 'description' => 'Cantidad entera positiva'],
                        'tipo_ajuste'       => ['type' => 'STRING', 'description' => 'Merma, Pérdida/Extravío, Conteo Físico, Devolución Dañada o Uso Interno'],
                        'motivo'            => ['type' => 'STRING', 'description' => 'Razón del ajuste en palabras sencillas'],
                    ],
                ],
            ],
        ];
    }

    /**
     * System Prompt cálido, paciente y libre de tecnicismos para el dueño de Multipartes El Tuerca.
     */
    private function construirSystemPrompt(): string
    {
        return <<<PROMPT
Eres el colaborador de confianza en el mostrador de "Multipartes El Tuerca" en Santa Cruz de la Sierra, Bolivia.
Hablas con el dueño o el equipo de la tienda con un tono cercano, paciente, respetuoso y natural (como hablar con un buen amigo que te ayuda en el negocio).
La moneda oficial es Bolivianos (Bs.).

REGLAS DE PERSONALIDAD Y COMPRENSIÓN:
1. CERO TECNICISMOS: Jamás menciones SQL, PostgreSQL, tablas, columnas, JSON, arrays, IDs internos, triggers, constraints ni errores técnicos. Habla siempre en español cotidiano de tienda de repuestos.
2. TOLERANCIA TOTAL A VOZ Y ERRORES: El usuario suele dictar por micrófono con muletillas ("eh", "este", "mmm", "o sea", "a ver", "pues", "hermano", "porfa"), dudas, repeticiones o errores ortográficos/fonéticos ("pastiyas", "bujia", "amortiguador de adelante", "del corolla viejo", "bosh"). Ignora todo el ruido y entiende la intención real.
3. DIÁLOGO MULTI-TURNO: Recuerda lo que se habló en los mensajes anteriores. Si faltan datos para una operación, invoca la herramienta correspondiente con los datos que ya tengas (aunque sean parciales) para que el sistema guíe paso a paso al dueño, o responde con una pregunta corta y amable.

TIENES TRES MODOS DE RESPUESTA:
A) OPERACIONES (venta, cotización, compra o merma/ajuste):
   INVOCA la función declarada correspondiente (preparar_venta, preparar_cotizacion, preparar_compra o preparar_ajuste_inventario) con los datos detectados en la conversación. No inventes el método de pago si el usuario todavía no lo dijo.
B) CONSULTAS DE DATOS / REPORTES:
   Responde EXCLUSIVAMENTE con un JSON válido:
   {"sql": "SELECT ...", "explicacion": "Resumen amable y claro en español sencillo (en Bs. si aplica)"}
C) CHARLA / SALUDOS / ACLARACIONES:
   Responde en español natural, cálido y breve (máximo 2 o 3 líneas).

ESQUEMA INTERNO SOLO PARA CONSULTAS READ-ONLY:
1. roles (id_rol, nombre_rol, descripcion)
2. usuarios (id_usuario, id_rol, nombre_completo, username, correo, telefono, estado, created_at) -- PROHIBIDO password_hash
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

FUNCIONES ANALÍTICAS DISPONIBLES:
- fn_reporte_rotacion_repuestos(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_categoria INT DEFAULT NULL)
- fn_reporte_rentabilidad(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_categoria INT DEFAULT NULL)
- fn_reporte_compras_distribuidor(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_proveedor INT DEFAULT NULL)
- fn_reporte_balance_medios_pago(p_fecha_inicio DATE, p_fecha_fin DATE, p_id_sesion INT DEFAULT NULL, p_id_cajero INT DEFAULT NULL)
- fn_obtener_metricas_pos(p_fecha DATE DEFAULT CURRENT_DATE)

Solo sentencias SELECT o WITH de solo lectura, sin punto y coma final, máximo LIMIT 12, usa ILIKE para textos, jamás consultes password_hash.
PROMPT;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // BÚSQUEDA INTELIGENTE DE PRODUCTOS Y RESOLUCIÓN DE AMBIGÜEDADES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Busca productos activos con sus vehículos compatibles y detecta si hay ambigüedad (varios parecidos).
     */
    private function buscarCandidatosProducto(string $texto): array
    {
        $limpio = trim($this->limpiarMuletillasVoz($texto));
        if ($limpio === '') {
            return ['estado' => 'ninguno', 'producto' => null, 'opciones' => []];
        }

        // 1. Coincidencia exacta por código OEM, código interno o código alternativo -> inequívoco
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
            return ['estado' => 'unico', 'producto' => $this->formatearCandidatoProducto($exacto, $this->obtenerVehiculosPorProducto([$exacto->id_producto])[$exacto->id_producto] ?? []), 'opciones' => []];
        }

        // 2. Búsqueda tolerante por términos (incluyendo compatibilidad vehicular y fonética)
        $norm = $this->normalizarFonetica($this->normalizarTexto($limpio));
        $terminos = $this->extraerTerminosProducto($norm);
        if (empty($terminos)) {
            return ['estado' => 'ninguno', 'producto' => null, 'opciones' => []];
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

            $puntaje = 0;
            $terminosCoincidentes = 0;
            foreach ($terminos as $t) {
                $raiz = $this->singularizar($t);
                if (str_contains($haystack, $t)) {
                    $puntaje += 3;
                    $terminosCoincidentes++;
                } elseif (mb_strlen($raiz) >= 3 && str_contains($haystack, $raiz)) {
                    $puntaje += 2;
                    $terminosCoincidentes++;
                }
            }

            if ($puntaje > 0) {
                $puntuados[] = [
                    'producto'   => $this->formatearCandidatoProducto($p, $vehs),
                    'puntaje'    => $puntaje,
                    'coincides'  => $terminosCoincidentes,
                ];
            }
        }

        if (empty($puntuados)) {
            return ['estado' => 'ninguno', 'producto' => null, 'opciones' => []];
        }

        usort($puntuados, function ($a, $b) {
            if ($b['coincides'] !== $a['coincides']) {
                return $b['coincides'] <=> $a['coincides'];
            }
            if ($b['puntaje'] !== $a['puntaje']) {
                return $b['puntaje'] <=> $a['puntaje'];
            }
            return ($b['producto']['stock_actual'] ?? 0) <=> ($a['producto']['stock_actual'] ?? 0);
        });

        // Filtrar solo los mejores candidatos cercanos al ganador
        $topCoincides = $puntuados[0]['coincides'];
        $topPuntaje = $puntuados[0]['puntaje'];

        $cercanos = array_values(array_filter($puntuados, function ($item) use ($topCoincides, $topPuntaje) {
            return $item['coincides'] >= $topCoincides && ($topPuntaje - $item['puntaje']) <= 2;
        }));

        if (count($cercanos) === 1) {
            return ['estado' => 'unico', 'producto' => $cercanos[0]['producto'], 'opciones' => []];
        }

        // Múltiples coincidencias: preparar lista numerada amigable (máx 5)
        $opciones = [];
        foreach (array_slice($cercanos, 0, 5) as $idx => $item) {
            $opt = $item['producto'];
            $opt['indice'] = $idx + 1;
            $opciones[] = $opt;
        }

        return ['estado' => 'ambiguo', 'producto' => null, 'opciones' => $opciones];
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
            $etiqueta = trim("{$r->marca} {$r->modelo} ({$r->anio_inicio}-{$r->anio_fin})");
            $mapa[$r->id_producto][] = $etiqueta;
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

    private function construirMensajeDesambiguacion(array $opciones, string $busqueda, string $origen): array
    {
        $total = count($opciones);
        $lineas = ["Encontré {$total} opciones disponibles que coinciden:"];
        foreach ($opciones as $opt) {
            $vehTxt = !empty($opt['vehiculos']) ? ' · Para ' . implode(', ', array_slice($opt['vehiculos'], 0, 2)) : '';
            $marcaTxt = !empty($opt['marca_fabricante']) ? " ({$opt['marca_fabricante']})" : '';
            $precioTxt = number_format((float) $opt['precio_venta_sugerido'], 2);
            $lineas[] = "{$opt['indice']}. {$opt['nombre_pieza']}{$marcaTxt}{$vehTxt} — Bs. {$precioTxt} (Stock: {$opt['stock_actual']})";
        }
        $lineas[] = '¿Cuál de estas es y cuántas unidades necesitas? (Puedes decirme "la 1", "la primera" o la marca).';

        return $this->respuestaAclaracion(implode("\n", $lineas), $origen);
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

        // Si menciona una marca, código o vehículo que identifica exactamente a una opción
        $filtradas = $this->filtrarOpcionesPorTexto($opciones, $norm);
        if (count($filtradas) === 1) {
            return $filtradas[0];
        }

        return null;
    }

    private function filtrarOpcionesPorTexto(array $opciones, string $norm): array
    {
        $terminos = $this->extraerTerminosProducto($norm);
        if (empty($terminos)) {
            return [];
        }

        $mejorPuntaje = 0;
        $ganadores = [];

        foreach ($opciones as $opt) {
            $vehs = implode(' ', $opt['vehiculos'] ?? []);
            $haystack = $this->normalizarFonetica($this->normalizarTexto(
                "{$opt['nombre_pieza']} {$opt['marca_fabricante']} {$opt['codigo_oem']} {$opt['codigo_interno']} {$vehs}"
            ));
            $puntaje = 0;
            foreach ($terminos as $t) {
                $raiz = $this->singularizar($t);
                if (str_contains($haystack, $t)) {
                    $puntaje += 2;
                } elseif (mb_strlen($raiz) >= 3 && str_contains($haystack, $raiz)) {
                    $puntaje += 1;
                }
            }
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $ganadores = [$opt];
            } elseif ($puntaje > 0 && $puntaje === $mejorPuntaje) {
                $ganadores[] = $opt;
            }
        }

        return $mejorPuntaje > 0 ? $ganadores : [];
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

    // ═════════════════════════════════════════════════════════════════════════
    // AUDITOR DE SEGURIDAD SQL READ-ONLY Y RESTRICCIONES POR ROL
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
        $prohibidas = '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|CALL|EXEC|EXECUTE|COPY|VACUUM|SET|REPLACE|PG_SLEEP)\b/i';
        if (preg_match($prohibidas, $sqlTrim)) {
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
            return $this->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no podemos ver reportes de ganancias, saldos de caja ni auditoría. Eso solo lo ve el administrador.");
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
    // VÍA B (ANALÍTICA): MOTOR SEMÁNTICO LOCAL CON TONO AMIGABLE
    // ═════════════════════════════════════════════════════════════════════════

    private function consultarViaMotorLocal(string $pregunta, string $norm, int $rolId, string $nombreRol): array
    {
        try {
            if (preg_match('/\b(rotacion|hueso|baja salida|sin movimiento|mas vendido|mas vendidos|menos vendido|desplazamiento)\b/', $norm)) {
                return $this->intencionRotacionRepuestos($norm);
            }
            if (preg_match('/\b(rentabilidad|margen|margenes|utilidad|ganancia|ganancias|rentable|rentables)\b/', $norm)) {
                return $this->intencionRentabilidad();
            }
            if (preg_match('/\b(compras.*proveedor|compras.*distribuidor|distribuidor|distribuidores|resumen de compras|comprado a)\b/', $norm)) {
                return $this->intencionComprasDistribuidor();
            }
            if (preg_match('/\b(balance.*pago|medios de pago|metodos de pago|desglose.*pago|recaudacion por canal|por metodo)\b/', $norm)) {
                return $this->intencionBalanceMediosPago();
            }
            if (preg_match('/\b(stock critico|stock bajo|bajo minimo|alerta.*stock|agotado|reabastecer|faltante.*stock|pocas unidades|por acabarse)\b/', $norm)) {
                return $this->intencionStockCritico();
            }
            if (preg_match('/\b(cuentas por pagar|deuda|proveedores|pendiente.*pago|credito.*proveedor|compras a credito|por pagar|debemos)\b/', $norm)) {
                return $this->intencionCuentasPorPagar();
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
            if ($this->detectarIntencionCompatibilidad($norm)) {
                return $this->intencionCompatibilidadVehicular($norm);
            }

            return $this->intencionBuscarRepuesto($pregunta, $norm);
        } catch (\Throwable $e) {
            Log::error('AiSqlAssistantService (Motor Local): ' . $e->getMessage());
            $msg = 'Uy, no pude consultar eso en este momento. ¿Probamos de nuevo mencionando el repuesto, vehículo o qué necesitas ver?';
            return $this->respuestaInformativa($msg, 'local');
        }
    }

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
            $enfoque = 'que más han salido';
        }

        $resumen = $filtradas->map(fn($r) => [
            'Código'        => $r->codigo_interno,
            'Repuesto'      => $r->nombre_pieza,
            'Stock'         => (int) $r->stock_actual,
            'Vendidas'      => (int) $r->unidades_vendidas,
            'Rotación'      => number_format((float) $r->indice_rotacion * 100, 1) . '%',
            'Estado'        => $r->clasificacion,
        ])->toArray();

        $cant = count($resumen);
        $top = $resumen[0] ?? null;
        $texto = $top
            ? "Revisé los últimos 90 días y aquí tienes {$cant} repuestos {$enfoque}. Encabeza la lista {$top['Repuesto']} con {$top['Vendidas']} unidades vendidas y {$top['Stock']} todavía en almacén."
            : 'Todavía no hay suficientes movimientos en estos 90 días para medir la rotación.';

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
            'Código'        => $r->codigo_interno,
            'Repuesto'      => $r->nombre_pieza,
            'Vendidas'      => (int) $r->cantidad_vendida,
            'Ventas (Bs.)'  => number_format((float) $r->ingresos_netos, 2),
            'Ganancia (Bs.)'=> number_format((float) $r->utilidad_bruta, 2),
            'Margen'        => number_format((float) $r->margen_real_porcentaje, 1) . '%',
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'Todavía no hay ventas cerradas en los últimos 90 días para calcular ganancias.'
            : 'En estos últimos 90 días vendimos Bs. ' . number_format($ingresos, 2) . ' y la ganancia bruta estimada es de Bs. ' . number_format($utilidad, 2) . " (un margen promedio del {$margen}%). El que mejor ganancia dejó fue {$resumen[0]['Repuesto']} con Bs. {$resumen[0]['Ganancia (Bs.)']}.";

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
            'Proveedor'      => $r->razon_social,
            'Órdenes'        => (int) $r->total_ordenes,
            'Unidades'       => (int) $r->unidades_compradas,
            'Comprado (Bs.)' => number_format((float) $r->monto_total_comprado, 2),
            'Pendiente (Bs.)'=> number_format((float) $r->saldo_pendiente_credito, 2),
        ])->toArray();

        $texto = $filas->isEmpty()
            ? 'No registramos compras a proveedores en los últimos 90 días.'
            : 'En los últimos 90 días compramos un total de Bs. ' . number_format($totalComprado, 2) . ' entre ' . count($resumen) . ' proveedores. De eso, queda un saldo por pagar de Bs. ' . number_format($saldoPend, 2) . '.';

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
            ? 'Todavía no hay ventas cerradas en este período para mostrar el balance de pagos.'
            : "Aquí tienes el balance por forma de pago (últimos 90 días): {$tickets} ventas por un total de Bs. " . number_format($totalNeto, 2) . '.';

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
                'p.marca_fabricante as Marca',
                'p.stock_actual as Quedan',
                'p.stock_minimo as Minimo',
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
            $texto = '¡Buenas noticias! Todos los repuestos activos tienen buen stock por encima del mínimo.';
        } else {
            $primeros = array_slice($items, 0, 3);
            $detalles = array_map(
                fn($it) => "{$it['Repuesto']} (quedan {$it['Quedan']} en {$it['Ubicacion']})",
                $primeros
            );
            $texto = "Ojo, hay {$totalCriticos} repuestos con stock bajo o agotado. Los más urgentes: " . implode('; ', $detalles) . '.';
        }

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
                'pr.condicion_comercial as Condicion',
                'nc.fecha_compra as Fecha',
                'nc.monto_total as Saldo_Bs',
            ])
            ->orderBy('nc.fecha_compra')
            ->limit(12)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $sumaDeuda = array_sum(array_column($deudas, 'Saldo_Bs'));
        $cantidad = count($deudas);

        if ($cantidad === 0) {
            $texto = '¡Excelente! No tienes ninguna factura pendiente por pagar a proveedores.';
        } else {
            $detalleProv = array_map(
                fn($d) => "{$d['Proveedor']} (Bs. " . number_format((float) $d['Saldo_Bs'], 2) . ')',
                array_slice($deudas, 0, 3)
            );
            $texto = "Tienes {$cantidad} facturas pendientes a proveedores por un total de Bs. " . number_format($sumaDeuda, 2) . '. Entre ellas: ' . implode('; ', $detalleProv) . '.';
        }

        return $this->respuestaConDatos($texto, $deudas, 'local');
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
                'metodo_pago as Forma_Pago',
                DB::raw('COUNT(id_venta) as "Ventas"'),
                DB::raw('ROUND(SUM(total_venta), 2) as "Total_Bs"'),
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
                $infoCaja = " La caja #{$cajaAbierta->id_sesion} (a cargo de {$cajaAbierta->nombre_completo}) está abierta con Bs. {$esperado} acumulados.";
            } else {
                $infoCaja = ' Ojo, todavía no abrimos la caja de hoy.';
            }
        }

        if ($cantHoy > 0) {
            $texto = "Hoy llevamos {$cantHoy} venta(s) por Bs. " . number_format($totalHoy, 2) . ". En total acumulado van {$cantGlobal} ventas por Bs. " . number_format($totalGlobal, 2) . '.' . $infoCaja;
        } else {
            $texto = "Hoy todavía no registramos ventas. En el acumulado general tenemos {$cantGlobal} ventas por Bs. " . number_format($totalGlobal, 2) . '.' . $infoCaja;
        }

        return $this->respuestaConDatos($texto, $desglose, 'local');
    }

    private function intencionCotizaciones(string $norm): array
    {
        $query = DB::table('cotizaciones as cot')
            ->join('clientes as cli', 'cli.id_cliente', '=', 'cot.id_cliente')
            ->select([
                'cot.id_cotizacion as Nro',
                'cli.nombre_razon_social as Cliente',
                'cot.fecha_vencimiento as Vence',
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

        $filas = $query->orderByDesc('cot.id_cotizacion')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();
        $totalVigentes = DB::table('cotizaciones')->where('estado', 'Vigente')->whereDate('fecha_vencimiento', '>=', date('Y-m-d'))->count();
        $montoVigentes = (float) DB::table('cotizaciones')->where('estado', 'Vigente')->whereDate('fecha_vencimiento', '>=', date('Y-m-d'))->sum('monto_total');

        $texto = 'Encontré ' . count($filas) . " proformas. Hoy tienes {$totalVigentes} vigentes por Bs. " . number_format($montoVigentes, 2) . ' listas para concretar.';
        return $this->respuestaConDatos($texto, $filas, 'local');
    }

    private function intencionClientes(string $norm): array
    {
        $query = DB::table('clientes')
            ->select([
                'nombre_razon_social as Cliente',
                'ci_nit as CI_NIT',
                'tipo_cliente as Tipo',
                'telefono as Telefono',
                'descuento_frecuente as Descuento_Pct',
            ]);

        if (str_contains($norm, 'taller') || str_contains($norm, 'mecanico')) {
            $query->where('tipo_cliente', 'Taller/Mecánico');
        } elseif (str_contains($norm, 'empresa') || str_contains($norm, 'flota')) {
            $query->where('tipo_cliente', 'Empresa/Flota');
        }

        $clientes = $query->orderByDesc('descuento_frecuente')->orderBy('nombre_razon_social')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();
        $texto = 'Aquí tienes ' . count($clientes) . ' clientes registrados en tu cartera con su descuento frecuente.';

        return $this->respuestaConDatos($texto, $clientes, 'local');
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
                'p.marca_fabricante as Marca',
                DB::raw("CONCAT(v.marca, ' ', v.modelo, ' (', v.anio_inicio, '-', v.anio_fin, ')') as \"Vehiculo\""),
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

        $resultados = $query->orderByDesc('p.stock_actual')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();

        if (empty($resultados)) {
            $texto = 'No encontré repuestos compatibles con esa búsqueda exacta. ¿Quieres probar solo con la marca o el modelo del vehículo?';
        } else {
            $cant = count($resultados);
            $primero = $resultados[0];
            $texto = "Encontré {$cant} repuesto(s) compatibles. Por ejemplo: {$primero['Repuesto']} ({$primero['Marca']}) para {$primero['Vehiculo']}, hay {$primero['Stock']} en {$primero['Ubicacion']} a Bs. " . number_format((float) $primero['Precio_Bs'], 2) . '.';
        }

        return $this->respuestaConDatos($texto, $resultados, 'local');
    }

    private function intencionBuscarRepuesto(string $preguntaOriginal, string $norm): array
    {
        $terminos = $this->extraerTerminosSignificativos($norm);

        $query = DB::table('productos as p')
            ->join('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->where('p.estado', true)
            ->select([
                'p.codigo_interno as Codigo',
                'p.codigo_oem as OEM',
                'p.nombre_pieza as Repuesto',
                'p.marca_fabricante as Marca',
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

        $productos = $query->orderByDesc('p.stock_actual')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();

        if (empty($productos)) {
            $totalPzs = DB::table('productos')->where('estado', true)->count();
            $texto = "No encontré un repuesto con ese nombre entre los {$totalPzs} que tenemos activos. ¿Quieres que busquemos por vehículo, o prefieres registrar una venta o cotización?";
        } else {
            $cant = count($productos);
            $top = $productos[0];
            $texto = "Encontré {$cant} coincidencia(s). Por ejemplo: {$top['Repuesto']} ({$top['Marca']}), quedan {$top['Stock']} en {$top['Ubicacion']} a Bs. " . number_format((float) $top['Precio_Bs'], 2) . '.';
        }

        return $this->respuestaConDatos($texto, $productos, 'local');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DETECTORES DE INTENCIÓN, EXTRACCIÓN DE SLOTS Y NORMALIZACIÓN DE VOZ
    // ═════════════════════════════════════════════════════════════════════════

    private function detectarOperacionTransaccional(string $norm): ?string
    {
        if (preg_match('/\b(merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|da de baja|dar de baja|se rompio|se dano|ajuste de inventario|ajustar stock|registrar ajuste)\b/', $norm)) {
            return 'preparar_ajuste_inventario';
        }
        if (preg_match('/\b(registra(?:r)?|ingresa(?:r)?|recepciona(?:r)?|anota(?:r)?|quiero\s+hacer|hacer)\b.*\b(compra|proveedor|mercaderia|factura de compra)\b/', $norm)
            || preg_match('/^\s*(?:nueva\s+)?compra\s+(?:a|de)\b/', $norm)
        ) {
            return 'preparar_compra';
        }
        if (preg_match('/\b(cotiza(?:r|me)?|cotizacion|proforma)\b/', $norm)
            && !preg_match('/\b(ver|listar|mostrar|cuantas|cuantos|vigentes|vencidas|historial)\b/', $norm)
        ) {
            return 'preparar_cotizacion';
        }
        if (preg_match('/\b(vende(?:r|me)?|vendeme|facturar|cobrar|registra(?:r)?\s+(?:una\s+)?venta|prepara(?:r)?\s+(?:una\s+)?venta|hacer\s+(?:una\s+)?venta|nueva\s+venta|anota(?:r)?\s+(?:una\s+)?venta)\b/', $norm)) {
            return 'preparar_venta';
        }

        return null;
    }

    private function esComandoCancelacion(string $norm): bool
    {
        return (bool) preg_match('/^\s*(cancelar|cancela|olvidalo|olvida|dejalo nomas|deja nomas|ya no|no quiero|empezar de nuevo|nueva charla|reiniciar|borrar todo|desde cero|nada gracias)\s*$/u', $norm)
            || (bool) preg_match('/\b(cancela eso|olvidalo|empezar de nuevo|nueva charla|dejalo nomas)\b/u', $norm);
    }

    private function esSaludoOConversacionBasica(string $norm): bool
    {
        return (bool) preg_match('/^\s*(hola|buenos dias|buenas tardes|buenas noches|buenas|que tal|como estas|hey|gracias|muchas gracias|mil gracias|ok|listo|perfecto|genial|ayuda|que puedes hacer)\s*$/u', $norm);
    }

    private function responderSaludoAmigable(string $norm, ?object $usuario): array
    {
        $nombre = $usuario ? Str::words((string) ($usuario->nombre_completo ?? ''), 1, '') : '';
        $saludoNombre = $nombre !== '' ? ", {$nombre}" : '';

        if (preg_match('/\b(gracias|perfecto|listo|genial|ok)\b/', $norm)) {
            return $this->respuestaInformativa("¡A la orden{$saludoNombre}! Aquí sigo para lo que necesites en el mostrador.", 'local');
        }
        if (preg_match('/\b(ayuda|que puedes hacer)\b/', $norm)) {
            return $this->respuestaInformativa("Con gusto{$saludoNombre}. Puedes hablarme normal para consultar precios, stock o repuestos por vehículo, ver cómo van las ventas y ganancias, o pedirme que armemos juntos una venta, cotización, compra o merma paso a paso.", 'local');
        }

        return $this->respuestaInformativa("¡Hola{$saludoNombre}! ¿Cómo estás? Dime qué necesitas hoy: ¿buscamos un repuesto, revisamos ventas o registramos una operación?", 'local');
    }

    private function esConsultaAnaliticaExplicita(string $norm): bool
    {
        return (bool) preg_match('/\b(stock critico|stock bajo|rotacion|hueso|rentabilidad|ganancias|margen|cuentas por pagar|balance.*pago|cuanto vendimos|cuantas ventas|como esta la caja|compatibles para)\b/', $norm);
    }

    private function esRespuestaAlBorradorActual(array $draft, string $norm): bool
    {
        $esperando = $draft['esperando'] ?? null;
        if ($esperando === 'metodo_pago' && $this->detectarMetodoPagoExplicito($norm, true) !== null) {
            return true;
        }
        if ($esperando === 'seleccion_producto' && ($this->extraerIndiceOrdinal($norm) !== null || !empty($this->filtrarOpcionesPorTexto($draft['opciones_pendientes'] ?? [], $norm)))) {
            return true;
        }
        if ($esperando === 'descuento_taller' && ($this->esRespuestaAfirmativa($norm) || $this->esRespuestaNegativa($norm))) {
            return true;
        }
        return false;
    }

    private function esRespuestaDeterministicaSimple(array $draft, string $norm): bool
    {
        $esperando = $draft['esperando'] ?? null;
        if ($esperando === 'seleccion_producto') {
            return true;
        }
        if ($esperando === 'metodo_pago' && $this->detectarMetodoPagoExplicito($norm, true) !== null) {
            return true;
        }
        if ($esperando === 'descuento_taller' && ($this->esRespuestaAfirmativa($norm) || $this->esRespuestaNegativa($norm))) {
            return true;
        }
        if ($esperando === 'costo_unitario' && $this->extraerCostoDeTexto($norm, true) !== null) {
            return true;
        }
        if ($esperando === 'proveedor' && ($this->extraerIndiceOrdinal($norm) !== null || $this->detectarProveedorEnTexto($norm, $norm) !== null)) {
            return true;
        }
        if ($esperando === 'tipo_ajuste' && $this->detectarTipoAjusteExplicito($norm) !== null) {
            return true;
        }
        if ($esperando === 'cantidad' && $this->extraerCantidadDeTexto($norm, false) !== null) {
            return true;
        }
        if ($this->indicaClientePorDefecto($norm)) {
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
        return (bool) preg_match('/\b(no|nop|sin descuento|dejalo sin|precio normal|no hace falta|ninguno)\b/u', $norm);
    }

    private function indicaClientePorDefecto(string $norm): bool
    {
        return (bool) preg_match('/\b(no me acuerdo|cualquiera|ponelo normal|ponlo normal|sin nombre|mostrador|cliente ocasional|cliente general|consumidor final|ninguno en especial|lo normal)\b/u', $norm);
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
        if ($esperandoPago && preg_match('/\b(normal|lo de siempre|como siempre|el de siempre)\b/u', $norm)) {
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
            // Quitar "la 1", "el 2", "opcion 1", "primera", etc. para no confundir el índice con la cantidad
            $texto = (string) preg_replace('/\b(?:la|el|opcion|numero|nro)\s*[1-5]\b/u', ' ', $texto);
            $texto = (string) preg_replace('/\b(primera|primero|segunda|segundo|tercera|tercero|cuarta|cuarto|quinta|quinto)\b/u', ' ', $texto);
        }

        // Ignorar montos en Bs. o porcentajes o días
        $texto = (string) preg_replace('/\b(?:a|por|de)\s+\d+(?:[\.,]\d+)?\s*(?:bs|bolivianos|%)\b/u', ' ', $texto);
        $texto = (string) preg_replace('/\b\d+\s*(?:bs|bolivianos|%|dias?)\b/u', ' ', $texto);

        if (preg_match('/\b(?:dame|ponme|poneme|quiero|necesito|lleva|son|vender|cotizar|comprar|descontar)?\s*(\d{1,3})\s*(?:unidades|unidad|uds|pzs|piezas|juegos|juego|pares|par|cajas|caja|filtros|pastillas|bujias|litros|botellas)?\b/u', $texto, $m)) {
            $val = (int) $m[1];
            if ($val >= 1 && $val <= 500) {
                return $val;
            }
        }

        // Números en palabras
        $mapa = [
            'media docena' => 6, 'docena' => 12, 'un par' => 2, 'par' => 2,
            'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6,
            'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10,
            'uno' => 1, 'una' => 1,
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
        $sinPrecio = preg_replace('/\b(?:a|por|de)\s+\d+(?:[\.,]\d+)?\s*(?:bs|bolivianos|%)\b/u', ' ', $norm);
        $sinFactura = preg_replace('/\bfactura\s+[a-z0-9\-]+/u', ' ', (string) $sinPrecio);
        $sinPago = preg_replace('/\b(en efectivo|por qr|con qr|por transferencia|con tarjeta|pago mixto|al contado|a credito|con descuento de \d+|por \d+ dias)\b/u', ' ', (string) $sinFactura);

        $sinRuido = preg_replace(
            '/\b(registra|registrar|prepara|preparar|vende|vender|vendeme|cotiza|cotizar|cotizame|compra|comprar|ingresa|ingresar|recepciona|recepcionar|aplica|aplicar|reporta|reportar|anota|anotar|hacer|quiero|necesito|dame|ponme|poneme|nueva|nuevo|una|un|unas|unos|venta|cotizacion|proforma|merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|ajuste|de|del|la|el|los|las|por|para|al|con|en|que|se|rompio|dano|perdio|da|baja|unidades|unidad|uds|piezas|pzs|juegos|juego|pares|par|efectivo|qr|tarjeta|mixto|credito|contado|cliente|proveedor|factura|normal|viejo|nuevo|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|\d+)\b/u',
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

    // ═════════════════════════════════════════════════════════════════════════
    // HELPERS DE ENTIDADES, TRADUCCIÓN AMIGABLE Y NORMALIZACIÓN
    // ═════════════════════════════════════════════════════════════════════════

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

    private function obtenerClienteOcasionalMostrador(): object
    {
        $mostrador = DB::table('clientes')
            ->where('id_cliente', 1)
            ->orWhere('nombre_razon_social', 'ilike', '%mostrador%')
            ->orWhere('nombre_razon_social', 'ilike', '%ocasional%')
            ->orWhere('nombre_razon_social', 'ilike', '%general%')
            ->orderBy('id_cliente')
            ->first();

        return $mostrador ?: (object) [
            'id_cliente'          => 1,
            'nombre_razon_social' => 'Cliente Ocasional Mostrador',
            'ci_nit'              => 'S/N',
            'tipo_cliente'        => 'Particular',
            'descuento_frecuente' => 0.0,
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
        // Buscar por patrón explícito ("para cliente X", "al taller X")
        if (preg_match('/\b(?:cliente|para\s+el\s+taller|al\s+taller|taller|para)\s+([a-záéíóúñ\s]{3,40}?)(?:\s+(?:en\s+efectivo|por\s+qr|por\s+transferencia|con\s+tarjeta|pago\s+mixto|con\s+descuento|por\s+\d+\s+d[ií]as)|\s*$)/iu', $original, $m)) {
            $cand = trim($m[1]);
            $encontrado = $this->buscarClientePorTexto($cand);
            if ($encontrado) {
                return $encontrado;
            }
        }

        // Cruzar talleres o clientes con nombre distintivo mencionado en el mensaje
        $clientes = DB::table('clientes')->where('id_cliente', '<>', 1)->get();
        foreach ($clientes as $cl) {
            $nomNorm = $this->normalizarTexto($cl->nombre_razon_social);
            $palabras = array_values(array_filter(explode(' ', $nomNorm), fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['taller', 'mecanico', 'cliente', 'empresa', 'flota', 'general', 'mostrador'], true)));
            $coincidencias = 0;
            foreach ($palabras as $w) {
                if (str_contains($norm, $w)) {
                    $coincidencias++;
                }
            }
            if ($coincidencias >= 2 || (count($palabras) === 1 && $coincidencias === 1)) {
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
                $q->where('razon_social', 'ilike', "%{$texto}%")
                  ->orWhere('nit', 'ilike', "%{$texto}%");
            })
            ->first();
    }

    private function detectarProveedorEnTexto(string $original, string $norm): ?object
    {
        if (preg_match('/\b(?:proveedor|a|de)\s+([a-záéíóúñ0-9\.\s]{3,40}?)(?:\s+(?:al|con|factura|por|de\s+\d)|\s*$)/iu', $original, $m)) {
            $prov = $this->resolverProveedor(trim($m[1]));
            if ($prov) {
                return $prov;
            }
        }

        $todosProv = DB::table('proveedores')->where('estado', true)->get();
        foreach ($todosProv as $pr) {
            $palabras = array_filter(explode(' ', $this->normalizarTexto($pr->razon_social)), fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['importadora', 'distribuidora', 'repuestos', 'ltda', 'srl'], true));
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

    /**
     * Traduce cualquier excepción técnica o regla de PostgreSQL a español cotidiano sin tecnicismos.
     */
    private function traducirErrorAmigable(string $raw): array
    {
        $norm = $this->normalizarTexto($raw);

        if ($raw === 'CAJA_CERRADA' || str_contains($norm, 'caja') && (str_contains($norm, 'abierta') || str_contains($norm, 'cerrada') || str_contains($norm, 'abrir'))) {
            return [
                'mensaje'  => 'Ojo, todavía no abrimos la caja de hoy. ¿Quieres que vayamos a abrirla primero o te ayudo con otra cosa?',
                'enlace'   => route('caja.index'),
                'etiqueta' => 'Ir a Abrir Caja',
            ];
        }
        if (str_contains($norm, 'stock') || str_contains($norm, 'insuficiente') || str_contains($norm, 'existencia')) {
            return [
                'mensaje' => 'Uy, no nos alcanza el stock disponible en almacén para esa cantidad. Revisemos cuántas unidades quedan o probemos con menos.',
            ];
        }
        if (str_contains($norm, 'costo') || str_contains($norm, 'margen') || str_contains($norm, 'perdida')) {
            return [
                'mensaje' => 'Ojo, ese precio o descuento quedaría por debajo del costo de compra del repuesto. Tendríamos que bajar un poco el descuento.',
            ];
        }
        if (str_contains($norm, 'descuento')) {
            return [
                'mensaje' => 'Ese descuento supera el tope permitido para este cliente. ¿Probamos con un descuento un poquito menor?',
            ];
        }
        if (str_contains($norm, 'motivo')) {
            return [
                'mensaje' => 'Necesito un poquito más de detalle en el motivo del ajuste para poder guardarlo bien.',
            ];
        }

        return [
            'mensaje' => 'Mmm, algo no cuadró al intentar guardar la operación. Revisemos si la caja está abierta y si hay stock suficiente, y probamos de nuevo.',
        ];
    }

    private function limpiarTecnicismosDeRespuesta(string $texto): string
    {
        $limpio = preg_replace('/\b(SQLSTATE|id_producto|id_cliente|id_venta|id_sesion|constraint|jsonb|JSON|array|PostgreSQL|Smart-DB|sp_registrar_\w+|fn_reporte_\w+)\b/i', '', $texto);
        $limpio = preg_replace('/\s{2,}/', ' ', (string) $limpio);
        return trim((string) $limpio);
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

    private function respuestaAclaracion(string $msg, string $origen = 'local'): array
    {
        return [
            'ok'             => true,
            'tipo'           => 'aclaracion',
            'origen'         => $origen,
            'texto_pantalla' => $msg,
            'texto_voz'      => $this->prepararTextoParaVoz($msg),
            'datos_resumen'  => [],
        ];
    }

    private function respuestaConDatos(string $texto, array $datos, string $origen = 'local'): array
    {
        return [
            'ok'             => true,
            'tipo'           => 'informacion',
            'origen'         => $origen,
            'texto_pantalla' => $texto,
            'texto_voz'      => $this->prepararTextoParaVoz($texto),
            'datos_resumen'  => $datos,
        ];
    }

    private function etiquetaOperacionAmigable(string $op): string
    {
        return match ($op) {
            'preparar_venta'             => 'registrar ventas',
            'preparar_cotizacion'        => 'emitir cotizaciones',
            'preparar_compra'            => 'registrar compras a proveedores',
            'preparar_ajuste_inventario' => 'hacer ajustes de inventario',
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
                if (is_numeric($val) && preg_match('/(precio|monto|total|costo|deuda|saldo|venta|utilidad|ingreso|bs)/i', (string) $col)) {
                    $pares[] = "{$etiqueta}: Bs. " . number_format((float) $val, 2);
                } else {
                    $pares[] = "{$etiqueta}: {$val}";
                }
            }
        }

        $resumenUno = implode(', ', array_slice($pares, 0, 5));
        return trim($explicacion) . " Encontré {$total} resultado(s). Por ejemplo: {$resumenUno}.";
    }

    public function prepararTextoParaVoz(string $texto): string
    {
        $limpio = preg_replace('/[\*\#\_`~>]/', '', $texto);
        $limpio = preg_replace('/\bBs\.?\s*/i', 'bolivianos ', $limpio);
        $limpio = preg_replace('/\b(?:c[óo]digo\s+)?OEM\b/iu', 'código O E M', $limpio);
        $limpio = preg_replace('/\bSKU\b/i', 'código interno', $limpio);
        $limpio = preg_replace('/\n+/', '. ', $limpio);
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

    /**
     * Tolerancia a errores ortográficos y fonéticos frecuentes del dictado por voz.
     */
    private function normalizarFonetica(string $norm): string
    {
        $reemplazos = [
            '/\bpastiyas?\b/u'              => 'pastilla',
            '/\bpastias?\b/u'               => 'pastilla',
            '/\b(?:de\s+)?adelante\b/u'     => 'delantero',
            '/\bdelante\b/u'                => 'delantero',
            '/\b(?:de\s+)?atras\b/u'        => 'trasero',
            '/\baseite\b/u'                 => 'aceite',
            '/\bcoroya\b/u'                 => 'corolla',
            '/\bjailux\b/u'                 => 'hilux',
            '/\bnisan\b/u'                  => 'nissan',
            '/\bsusuki\b/u'                 => 'suzuki',
            '/\bmitsubichi\b/u'             => 'mitsubishi',
            '/\b(?:hyunday|jiundai)\b/u'    => 'hyundai',
            '/\bbosh\b/u'                   => 'bosch',
            '/\bbitara\b/u'                 => 'vitara',
            '/\bamortiguadores?\b/u'        => 'amortiguador',
            '/\bbujias?\b/u'                => 'bujia',
            '/\bfiltros?\b/u'               => 'filtro',
            '/\bfrenos?\b/u'                => 'freno',
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
            'cotizar', 'cotiza', 'comprar', 'compra', 'registrar', 'registra', 'venta',
            'juegos', 'juego', 'unidades', 'unidad', 'uds', 'pzs', 'par', 'pares',
            'primera', 'primero', 'segunda', 'segundo', 'tercera', 'tercero',
            'viejo', 'nuevo', 'favor', 'esta', 'estas', 'ese', 'esa', 'eso',
            'efectivo', 'tarjeta', 'transferencia', 'mixto', 'contado', 'credito',
            'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
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
