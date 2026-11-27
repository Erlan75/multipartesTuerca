<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;

/**
 * Gestor de sesión multi-turno, máquina de estados por campo
 * (pendiente | completado | omitido_por_usuario), subflujo de alta de
 * producto nuevo en compras y recuperación de borradores cancelados (CU39).
 */
class AiDraftManager
{
    private const SESSION_HISTORY_KEY   = 'ai_chat_history';
    private const SESSION_DRAFT_KEY     = 'ai_tx_draft';
    private const SESSION_CANCELLED_KEY = 'ai_last_cancelled_draft';
    private const MAX_HISTORY_TURNS     = 8;

    public function __construct(
        private readonly AiTextNormalizer $text,
        private readonly AiTransactionExecutor $executor
    ) {}

    // ─── Persistencia de historial y borradores en sesión ───

    public function obtenerHistorial(): array
    {
        return array_slice(session()->get(self::SESSION_HISTORY_KEY, []), -self::MAX_HISTORY_TURNS);
    }

    public function registrarTurnoHistorial(string $mensajeUsuario, string $respuestaModelo): void
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

    public function limpiarBorradorOperacion(): void
    {
        session()->forget(self::SESSION_DRAFT_KEY);
    }

    public function limpiarBorradorCancelado(): void
    {
        session()->forget(self::SESSION_CANCELLED_KEY);
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

    public function obtenerBorradorCancelado(int $userId): ?array
    {
        $cancelado = session()->get(self::SESSION_CANCELLED_KEY);
        if (!is_array($cancelado) || (int) ($cancelado['user_id'] ?? 0) !== $userId) {
            return null;
        }
        if ((now()->timestamp - (int) ($cancelado['cancelled_at'] ?? 0)) > 300) {
            session()->forget(self::SESSION_CANCELLED_KEY);
            return null;
        }
        return $cancelado;
    }

    public function obtenerBorradorActivo(int $userId): ?array
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

    public function esRespuestaAlBorradorActual(array $draft, string $norm): bool
    {
        $esp = $draft['esperando'] ?? null;
        if ($esp === null) {
            return false;
        }
        if ($this->text->esDesconocimientoONegativa($norm, $esp)) {
            return true;
        }
        if ($esp === 'metodo_pago' && $this->text->detectarMetodoPagoExplicito($norm, true) !== null) {
            return true;
        }
        if (in_array($esp, ['seleccion_producto', 'seleccion_proveedor', 'nuevo_producto_categoria'], true) && $this->text->extraerIndiceOrdinal($norm) !== null) {
            return true;
        }
        return false;
    }

    // ─── Flujo principal de borradores conversacionales ───

    public function iniciarBorradorDesdeLocal(
        string $operacion,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): ?array {
        if (!$this->executor->rolPuedeEjecutarOperacion($rolId, $operacion)) {
            $msg = "Tu cuenta de {$nombreRol} no tiene permiso para {$this->text->etiquetaOperacionAmigable($operacion)}. Si la necesitas, pídele apoyo al administrador.";
            return $this->text->respuestaRestriccion($msg);
        }

        $draft = $this->crearBorradorVacio($operacion, $userId);
        return $this->absorberMensajeEnBorrador($draft, $preguntaOriginal, $preguntaLimpia, $norm, $userId, 'local', true);
    }

    public function continuarBorradorConversacional(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        if (!empty($draft['nuevo_producto_draft'])) {
            return $this->procesarSubflujoProductoNuevo($draft, $preguntaOriginal, $preguntaLimpia, $norm, $userId, 'local');
        }

        $nuevaOp = $this->text->detectarOperacionTransaccional($norm);
        if ($nuevaOp !== null && $nuevaOp !== $draft['operacion'] && empty($draft['opciones_pendientes']) && $draft['esperando'] !== 'motivo') {
            if (!$this->executor->rolPuedeEjecutarOperacion($rolId, $nuevaOp)) {
                $this->limpiarBorradorOperacion();
                return $this->text->respuestaRestriccion("Tu cuenta de {$nombreRol} no tiene permiso para {$this->text->etiquetaOperacionAmigable($nuevaOp)}.");
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
    public function aplicarEdicionSobreBorradorRecuperado(
        array $draft,
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $norm,
        int $rolId,
        string $nombreRol,
        int $userId
    ): array {
        $op = $draft['operacion'];

        if ($op === 'preparar_venta') {
            $metodo = $this->text->detectarMetodoPagoExplicito($norm, false);
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
            $costoNuevo = $this->text->extraerCostoDeTexto($norm, false);
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

        if (in_array($op, ['preparar_venta', 'preparar_cotizacion'], true)) {
            $cli = $this->text->detectarClienteRegistradoEnTexto($preguntaLimpia, $norm);
            if ($cli) {
                $this->setCampo($draft, 'cliente', 'completado', $this->text->formatearClienteArray($cli));
            }
        }

        return $this->evaluarEstadoBorrador($draft, $userId, 'local');
    }

    /**
     * Incorpora los argumentos de un Function Call de Gemini en la máquina de estados local
     * y los fusiona con el extractor determinístico multi-slot para que no se pierda ningún dato.
     */
    public function procesarFunctionCallGemini(
        string $fnName,
        array $args,
        ?array $draftActivo,
        int $rolId,
        string $nombreRol,
        int $userId,
        string $preguntaOriginal = '',
        string $preguntaLimpia = '',
        string $norm = ''
    ): array {
        if (!$this->executor->rolPuedeEjecutarOperacion($rolId, $fnName)) {
            return $this->text->respuestaRestriccion("Tu cuenta de {$nombreRol} no tiene permiso para {$this->text->etiquetaOperacionAmigable($fnName)}.");
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
            if ($preguntaLimpia !== '') {
                $normUso = $norm !== '' ? $norm : $this->text->normalizarFonetica($this->text->normalizarTexto($preguntaLimpia));
                $this->extraerCamposClienteNuevo($draft, $preguntaLimpia, $normUso, $draft['esperando'] ?? null, true);
            }
            return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
        }

        if (!empty($args['cliente']) && in_array($fnName, ['preparar_venta', 'preparar_cotizacion'], true)) {
            $cliTxt = trim((string) $args['cliente']);
            if ($this->text->indicaClientePorDefecto($this->text->normalizarTexto($cliTxt))) {
                $this->setCampo($draft, 'cliente', 'omitido_por_usuario', null);
            } else {
                $cli = $this->text->buscarClientePorTexto($cliTxt);
                if ($cli) {
                    $this->setCampo($draft, 'cliente', 'completado', $this->text->formatearClienteArray($cli));
                } elseif (mb_strlen($cliTxt) >= 3) {
                    $nombreLimpioCli = mb_convert_case($cliTxt, MB_CASE_TITLE, 'UTF-8');
                    $this->setCampo($draft, 'cliente', 'completado', [
                        'id_cliente'             => 1,
                        'nombre_razon_social'    => $nombreLimpioCli,
                        'ci_nit'                 => 'S/N',
                        'tipo_cliente'           => preg_match('/\btaller\b/i', $nombreLimpioCli) ? 'Taller/Mecánico' : 'Particular',
                        'descuento_frecuente'    => 0.0,
                        'cliente_nuevo_sugerido' => true,
                    ]);
                }
            }
        }

        if ($fnName === 'preparar_venta') {
            if (!empty($args['metodo_pago'])) {
                $this->setCampo($draft, 'metodo_pago', 'completado', $this->text->normalizarMetodoPago((string) $args['metodo_pago']));
            }
            if (!empty($args['sin_descuento'])) {
                $this->setCampo($draft, 'descuento', 'completado', 0.0);
                $draft['descuento_pct_pend'] = null;
            } elseif (isset($args['descuento_porcentaje']) && (float) $args['descuento_porcentaje'] > 0) {
                $draft['descuento_pct_pend'] = min(50.0, (float) $args['descuento_porcentaje']);
                $this->setCampo($draft, 'descuento', 'completado', 0.0);
            } elseif (isset($args['descuento_total']) && (float) $args['descuento_total'] > 0) {
                $this->setCampo($draft, 'descuento', 'completado', round((float) $args['descuento_total'], 2));
            }
            if (!empty($args['monto_recibido']) && (float) $args['monto_recibido'] > 0) {
                $this->setCampo($draft, 'pago_detalle', 'completado', ['monto_recibido' => round((float) $args['monto_recibido'], 2)]);
            } elseif (!empty($args['nro_referencia'])) {
                $this->setCampo($draft, 'pago_detalle', 'completado', ['nro_referencia' => strtoupper(trim((string) $args['nro_referencia']))]);
            }
        }

        if (!empty($args['sin_mas_productos'])) {
            $draft['sin_mas_productos'] = true;
        }

        if ($fnName === 'preparar_cotizacion' && !empty($args['dias_vigencia'])) {
            $this->setCampo($draft, 'dias_vigencia', 'completado', max(1, min(90, (int) $args['dias_vigencia'])));
        }

        if ($fnName === 'preparar_compra') {
            if (!empty($args['proveedor'])) {
                $prov = $this->text->resolverProveedor((string) $args['proveedor']);
                if ($prov) {
                    $this->setCampo($draft, 'proveedor', 'completado', ['id_proveedor' => (int) $prov->id_proveedor, 'razon_social' => $prov->razon_social]);
                }
            }
            if (!empty($args['numero_factura_nota'])) {
                $this->setCampo($draft, 'numero_factura_nota', 'completado', strtoupper(trim((string) $args['numero_factura_nota'])));
            }
            if (!empty($args['tipo_pago'])) {
                $this->setCampo($draft, 'tipo_pago', 'completado', str_contains($this->text->normalizarTexto((string) $args['tipo_pago']), 'cred') ? 'Crédito' : 'Contado');
            }
        }

        if ($fnName === 'preparar_ajuste_inventario') {
            if (!empty($args['tipo_ajuste'])) {
                $this->setCampo($draft, 'tipo_ajuste', 'completado', $this->text->normalizarTipoAjuste((string) $args['tipo_ajuste']));
            }
            if (!empty($args['cantidad_ajustada'])) {
                $this->setCampo($draft, 'cantidad', 'completado', abs((int) $args['cantidad_ajustada']));
            }
            if (!empty($args['motivo'])) {
                $this->setCampo($draft, 'motivo', 'completado', trim((string) $args['motivo']));
            }
            if (!empty($args['producto'])) {
                $busq = $this->text->buscarCandidatosProducto((string) $args['producto']);
                if ($busq['estado'] === 'unico') {
                    $this->setCampo($draft, 'producto', 'completado', $busq['producto']);
                } elseif ($busq['estado'] === 'ambiguo') {
                    $draft['opciones_pendientes'] = $busq['opciones'];
                    $draft['es_lista_dudosa'] = (bool) ($busq['dudoso'] ?? false);
                    $draft['esperando'] = 'seleccion_producto';
                    $this->guardarBorrador($draft);
                    return $this->text->construirMensajeDesambiguacion($busq['opciones'], $draft['es_lista_dudosa'], 'gemini');
                }
            }
            return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
        }

        if (!empty($args['items']) && is_array($args['items'])) {
            $resueltos = [];
            foreach ($args['items'] as $raw) {
                $pTxt = trim((string) ($raw['producto'] ?? ''));
                $cant = max(1, (int) ($raw['cantidad'] ?? 1));
                $costo = isset($raw['costo_unitario']) && (float) $raw['costo_unitario'] > 0 ? (float) $raw['costo_unitario'] : null;
                if ($pTxt === '') {
                    continue;
                }
                $busq = $this->text->buscarCandidatosProducto($pTxt);
                if ($busq['estado'] === 'ninguno' && $norm !== '') {
                    $fragFallback = $this->text->extraerFragmentoProductoLimpio($norm, $fnName, $args['cliente'] ?? null);
                    if ($fragFallback !== '') {
                        $busq = $this->text->buscarCandidatosProducto($fragFallback);
                    }
                }
                if ($busq['estado'] === 'unico') {
                    $resueltos[] = $this->text->armarItemDesdeProducto($busq['producto'], $cant, $fnName === 'preparar_compra', $costo);
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
                    return $this->text->construirMensajeDesambiguacion($busq['opciones'], $draft['es_lista_dudosa'], 'gemini');
                } elseif ($fnName === 'preparar_compra') {
                    $draft['nuevo_producto_draft'] = $this->iniciarNuevoProductoDraft($pTxt, $cant, $costo);
                    $draft['esperando'] = 'confirmar_producto_nuevo';
                    $this->guardarBorrador($draft);
                    return $this->text->respuestaAclaracion(
                        "No encontré ningún repuesto parecido a \"{$pTxt}\" en nuestro catálogo. ¿Es un producto nuevo que quieres registrar ahora mismo para agregarlo a esta compra?",
                        'gemini'
                    );
                } else {
                    $this->guardarBorrador($draft);
                    return $this->text->respuestaAclaracion("No encontré un repuesto parecido a \"{$pTxt}\" en tu inventario. ¿Me lo repites o me dices para qué vehículo es?", 'gemini');
                }
            }
            if (!empty($resueltos)) {
                $this->setCampo($draft, 'items', 'completado', $resueltos);
            }
        }

        // Fusionar siempre con el extractor determinístico local sobre la frase original para completar slots omitidos por Gemini
        if ($preguntaLimpia !== '') {
            $normUso = $norm !== '' ? $norm : $this->text->normalizarFonetica($this->text->normalizarTexto($preguntaLimpia));
            return $this->absorberMensajeEnBorrador($draft, $preguntaOriginal ?: $preguntaLimpia, $preguntaLimpia, $normUso, $userId, 'gemini', true);
        }

        return $this->evaluarEstadoBorrador($draft, $userId, 'gemini');
    }

    // ─── Internals de máquina de estados por campo ───

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
            'operacion'              => $operacion,
            'user_id'                => $userId,
            'updated_at'             => now()->timestamp,
            'campos'                 => $campos,
            'esperando'              => null,
            'opciones_pendientes'    => [],
            'proveedores_opciones'   => [],
            'pending_cantidad'       => null,
            'pending_costo'          => null,
            'descuento_pct_pend'     => null,
            'descuento_pct_aplicado' => null,
            'nuevo_producto_draft'   => null,
            'es_lista_dudosa'        => false,
            'sin_mas_productos'      => false,
        ];
    }

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

        if ($this->text->indicaCierreProductos($norm)) {
            $draft['sin_mas_productos'] = true;
        }

        // A. Detectar "no sé / no me acuerdo / cualquiera / normal"
        $esNingunoEnSeleccion = ($esperando === 'seleccion_producto' && !empty($draft['opciones_pendientes']) && $this->text->esRespuestaNinguno($norm));
        if (!$esPrimerTurno && !$esNingunoEnSeleccion && $esperando !== null && $this->text->esDesconocimientoONegativa($norm, $esperando)) {
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

        // B. Selección entre opciones de productos
        if ($esperando === 'seleccion_producto' && !empty($draft['opciones_pendientes'])) {
            if ($this->text->esRespuestaNinguno($norm)) {
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
                        return $this->text->respuestaAclaracion(
                            'Entendido, no es ninguno de esos. ¿Es un producto nuevo que quieres registrar ahora mismo para agregarlo a esta compra, o prefieres repetirme el nombre?',
                            $origen
                        );
                    }
                    $this->guardarBorrador($draft);
                    return $this->text->respuestaAclaracion('¡Sin problema! Dime de nuevo qué repuesto es o para qué vehículo lo buscas.', $origen);
                }
            } else {
                $seleccionado = $this->text->resolverSeleccionDeOpciones($draft['opciones_pendientes'], $norm);
                if ($seleccionado !== null) {
                    $cant = $this->text->extraerCantidadDeTexto($norm, true) ?? ($draft['pending_cantidad'] ?: 1);
                    $costo = $this->text->extraerCostoDeTexto($norm, false) ?? $draft['pending_costo'];

                    if ($op === 'preparar_ajuste_inventario') {
                        $this->setCampo($draft, 'producto', 'completado', $seleccionado);
                        if ($this->text->extraerCantidadDeTexto($norm, true) !== null || $draft['pending_cantidad'] !== null) {
                            $this->setCampo($draft, 'cantidad', 'completado', $cant);
                        }
                    } else {
                        $items = $this->valorCampo($draft, 'items', []);
                        $items[] = $this->text->armarItemDesdeProducto($seleccionado, $cant, $op === 'preparar_compra', $costo);
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

        // C. Cliente nuevo
        if ($op === 'preparar_cliente') {
            $this->extraerCamposClienteNuevo($draft, $preguntaLimpia, $norm, $esperando, $esPrimerTurno);
            return $this->evaluarEstadoBorrador($draft, $userId, $origen);
        }

        // D. Proveedor (Compras)
        if ($op === 'preparar_compra' && $this->estadoCampo($draft, 'proveedor') === 'pendiente') {
            $prov = null;
            if (!empty($draft['proveedores_opciones'])) {
                $idxProv = $this->text->extraerIndiceOrdinal($norm);
                if ($idxProv !== null && isset($draft['proveedores_opciones'][$idxProv - 1])) {
                    $prov = (object) $draft['proveedores_opciones'][$idxProv - 1];
                }
            }
            if (!$prov) {
                $prov = $this->text->detectarProveedorEnTexto($preguntaLimpia, $norm);
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

        // E. Venta (extracción multi-slot simultánea en cada turno)
        if ($op === 'preparar_venta') {
            if ($esperando === 'descuento') {
                $cliArr = $this->valorCampo($draft, 'cliente');
                $pctFrec = (float) ($cliArr['descuento_frecuente'] ?? 0);
                if ($this->text->esRespuestaNegativa($norm) || preg_match('/\b(precio normal|normal|cero|0)\b/u', $norm)) {
                    $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    $draft['esperando'] = null;
                } elseif ($pctFrec > 0 && $this->text->esRespuestaAfirmativa($norm)) {
                    $draft['descuento_pct_pend'] = $pctFrec;
                    $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    $draft['esperando'] = null;
                } elseif (($descDet = $this->text->extraerDescuentoDeTexto($norm)) !== null) {
                    if ($descDet['tipo'] === 'pct') {
                        $draft['descuento_pct_pend'] = $descDet['valor'];
                        $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    } else {
                        $this->setCampo($draft, 'descuento', 'completado', round((float) $descDet['valor'], 2));
                    }
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
                $descDet = $this->text->extraerDescuentoDeTexto($norm);
                if ($descDet !== null) {
                    if ($descDet['tipo'] === 'pct') {
                        $draft['descuento_pct_pend'] = $descDet['valor'];
                        $this->setCampo($draft, 'descuento', 'completado', 0.0);
                    } else {
                        $this->setCampo($draft, 'descuento', 'completado', round((float) $descDet['valor'], 2));
                    }
                }
            }

            $metodo = $this->text->detectarMetodoPagoExplicito($norm, $esperando === 'metodo_pago');
            $pagoDet = $this->text->extraerPagoDetalleDeTexto(
                $preguntaLimpia,
                $norm,
                $metodo ?? ($this->estadoCampo($draft, 'metodo_pago') === 'completado' ? (string) $this->valorCampo($draft, 'metodo_pago') : null)
            );
            if ($metodo === null && $pagoDet !== null && ($pagoDet['tipo'] ?? '') === 'efectivo') {
                $metodo = 'Efectivo';
            }

            if ($metodo !== null) {
                $this->setCampo($draft, 'metodo_pago', 'completado', $metodo);
                if ($esperando === 'metodo_pago') {
                    $draft['esperando'] = null;
                }
            }

            if ($pagoDet !== null && $this->estadoCampo($draft, 'pago_detalle') === 'pendiente') {
                if (!empty($pagoDet['omitido'])) {
                    $this->setCampo($draft, 'pago_detalle', 'omitido_por_usuario', null);
                } elseif (!empty($pagoDet['data'])) {
                    $this->setCampo($draft, 'pago_detalle', 'completado', $pagoDet['data']);
                }
                if ($esperando === 'pago_detalle') {
                    $draft['esperando'] = null;
                }
            } elseif ($esperando === 'pago_detalle') {
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

        // F. Cotización
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

        // G. Compra
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
                $costo = $this->text->extraerCostoDeTexto($norm, true);
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

        // H. Ajuste
        if ($op === 'preparar_ajuste_inventario') {
            $tipoAj = $this->text->detectarTipoAjusteExplicito($norm);
            if ($tipoAj !== null && $this->estadoCampo($draft, 'tipo_ajuste') === 'pendiente') {
                $this->setCampo($draft, 'tipo_ajuste', 'completado', $tipoAj);
                if ($esperando === 'tipo_ajuste') {
                    $draft['esperando'] = null;
                }
            }
            if ($esperando === 'cantidad' && $this->estadoCampo($draft, 'cantidad') === 'pendiente') {
                $c = $this->text->extraerCantidadDeTexto($norm, false);
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

        // I. Cliente en Venta / Cotización (registrado o nombre nuevo sugerido desde el turno 1)
        $clienteNombreParaLimpieza = null;
        if (in_array($op, ['preparar_venta', 'preparar_cotizacion'], true)) {
            if ($this->estadoCampo($draft, 'cliente') === 'completado') {
                $cliExistente = $this->valorCampo($draft, 'cliente');
                $clienteNombreParaLimpieza = is_array($cliExistente) ? ($cliExistente['nombre_razon_social'] ?? null) : null;
            } elseif ($this->estadoCampo($draft, 'cliente') === 'pendiente') {
                if ($this->text->indicaClientePorDefecto($norm)) {
                    $this->setCampo($draft, 'cliente', 'omitido_por_usuario', null);
                    if ($esperando === 'cliente') {
                        $draft['esperando'] = null;
                    }
                } else {
                    $cli = $this->text->detectarClienteRegistradoEnTexto($preguntaLimpia, $norm);
                    if ($cli) {
                        $this->setCampo($draft, 'cliente', 'completado', $this->text->formatearClienteArray($cli));
                        $clienteNombreParaLimpieza = $cli->nombre_razon_social;
                        if ($esperando === 'cliente') {
                            $draft['esperando'] = null;
                        }
                    } else {
                        $nombreExtraido = $this->text->extraerNombreClienteDeFrase($preguntaLimpia);
                        if ($nombreExtraido !== null) {
                            $cliPorNombre = $this->text->buscarClientePorTexto($nombreExtraido);
                            if ($cliPorNombre) {
                                $this->setCampo($draft, 'cliente', 'completado', $this->text->formatearClienteArray($cliPorNombre));
                                $clienteNombreParaLimpieza = $cliPorNombre->nombre_razon_social;
                            } else {
                                $this->setCampo($draft, 'cliente', 'completado', [
                                    'id_cliente'             => 1,
                                    'nombre_razon_social'    => $nombreExtraido,
                                    'ci_nit'                 => 'S/N',
                                    'tipo_cliente'           => preg_match('/\btaller\b/i', $nombreExtraido) ? 'Taller/Mecánico' : 'Particular',
                                    'descuento_frecuente'    => 0.0,
                                    'cliente_nuevo_sugerido' => true,
                                ]);
                                $clienteNombreParaLimpieza = $nombreExtraido;
                            }
                            if ($esperando === 'cliente') {
                                $draft['esperando'] = null;
                            }
                        } elseif ($esperando === 'cliente' && !$esPrimerTurno && mb_strlen(trim($preguntaLimpia)) >= 3) {
                            $nombreDirecto = mb_convert_case(trim($preguntaLimpia), MB_CASE_TITLE, 'UTF-8');
                            $this->setCampo($draft, 'cliente', 'completado', [
                                'id_cliente'             => 1,
                                'nombre_razon_social'    => $nombreDirecto,
                                'ci_nit'                 => 'S/N',
                                'tipo_cliente'           => preg_match('/\btaller\b/i', $nombreDirecto) ? 'Taller/Mecánico' : 'Particular',
                                'descuento_frecuente'    => 0.0,
                                'cliente_nuevo_sugerido' => true,
                            ]);
                            $clienteNombreParaLimpieza = $nombreDirecto;
                            $draft['esperando'] = null;
                        }
                    }
                }
            }
        }

        // J. Producto(s) si todavía está pendiente
        $campoProd = $op === 'preparar_ajuste_inventario' ? 'producto' : 'items';
        $necesitaProd = $this->estadoCampo($draft, $campoProd) === 'pendiente' && empty($draft['opciones_pendientes']);

        if ($necesitaProd) {
            $cantDet = $this->text->extraerCantidadDeTexto($norm, false);
            if ($cantDet !== null) {
                $draft['pending_cantidad'] = $cantDet;
                if ($op === 'preparar_ajuste_inventario' && $this->estadoCampo($draft, 'cantidad') === 'pendiente') {
                    $this->setCampo($draft, 'cantidad', 'completado', $cantDet);
                }
            }
            if ($op === 'preparar_compra') {
                $costoDet = $this->text->extraerCostoDeTexto($norm, false);
                if ($costoDet !== null && $costoDet > 0) {
                    $draft['pending_costo'] = $costoDet;
                    $this->setCampo($draft, 'costo_unitario', 'completado', round($costoDet, 2));
                }
            }

            $fragProd = $this->text->extraerFragmentoProductoLimpio($norm, $op, $clienteNombreParaLimpieza);
            if ($fragProd !== '') {
                $busqueda = $this->text->buscarCandidatosProducto($fragProd);
                if ($busqueda['estado'] === 'unico') {
                    $prod = $busqueda['producto'];
                    $cant = $draft['pending_cantidad'] ?: 1;
                    if ($op === 'preparar_ajuste_inventario') {
                        $this->setCampo($draft, 'producto', 'completado', $prod);
                    } else {
                        $costoUso = $draft['pending_costo'] ?? $this->valorCampo($draft, 'costo_unitario');
                        $items = [$this->text->armarItemDesdeProducto($prod, $cant, $op === 'preparar_compra', $costoUso ? (float) $costoUso : null)];
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
                    return $this->text->construirMensajeDesambiguacion($busqueda['opciones'], $draft['es_lista_dudosa'], $origen);
                } elseif ($busqueda['estado'] === 'ninguno') {
                    if ($op === 'preparar_compra') {
                        $draft['nuevo_producto_draft'] = $this->iniciarNuevoProductoDraft(
                            $fragProd,
                            $draft['pending_cantidad'],
                            $draft['pending_costo'] ?? $this->valorCampo($draft, 'costo_unitario')
                        );
                        $draft['esperando'] = 'confirmar_producto_nuevo';
                        $this->guardarBorrador($draft);
                        $msg = "No encontré ningún repuesto parecido a \"{$fragProd}\" en nuestro catálogo. ¿Es un producto nuevo que quieres registrar ahora mismo para agregarlo a esta compra?";
                        return $this->text->respuestaAclaracion($msg, $origen);
                    }

                    if (!$esPrimerTurno && in_array($esperando, ['items', 'producto'], true)) {
                        $this->guardarBorrador($draft);
                        return $this->text->respuestaAclaracion(
                            "No encontré ningún repuesto parecido a \"{$preguntaLimpia}\" en el inventario activo. ¿Me lo puedes repetir con otro nombre o decirme para qué vehículo es?",
                            $origen
                        );
                    }
                }
            }
        }

        return $this->evaluarEstadoBorrador($draft, $userId, $origen);
    }

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
                    'texto_voz'          => $this->text->prepararTextoParaVoz($msg),
                    'enlace_comprobante' => route('caja.index'),
                    'etiqueta_enlace'    => 'Ir a Abrir Caja',
                    'datos_resumen'      => [],
                ];
            }

            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->text->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            if ($this->estadoCampo($draft, 'items') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'items', 'pendiente', []);
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    'Para poder registrar la venta necesito sí o sí saber qué repuesto sale y cuántas unidades son. Sin ese dato no puedo guardarla. Cuando me digas el repuesto y la cantidad, seguimos enseguida.',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'items') === 'pendiente' || empty($this->valorCampo($draft, 'items', []))) {
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('¡Con gusto armamos la venta! ¿Qué repuesto vas a vender (o para qué vehículo) y cuántas unidades?', $origen);
            }

            $items = $this->valorCampo($draft, 'items', []);
            $listaTxt = implode(', ', array_map(fn($i) => "{$i['cantidad']}x {$i['nombre_pieza']}", $items));
            $bruto = round(array_sum(array_column($items, 'subtotal')), 2);

            if (!empty($draft['descuento_pct_pend'])) {
                $pctAplicado = (float) $draft['descuento_pct_pend'];
                $calcDesc = round($bruto * ($pctAplicado / 100.0), 2);
                $this->setCampo($draft, 'descuento', 'completado', $calcDesc);
                $draft['descuento_pct_aplicado'] = $pctAplicado;
                $draft['descuento_pct_pend'] = null;
            }

            if ($this->estadoCampo($draft, 'cliente') === 'pendiente') {
                $descTxtPrev = '';
                if ($this->estadoCampo($draft, 'descuento') === 'completado') {
                    $dVal = (float) $this->valorCampo($draft, 'descuento', 0.0);
                    if (!empty($draft['descuento_pct_aplicado'])) {
                        $descTxtPrev = " con {$draft['descuento_pct_aplicado']}% de descuento";
                    } elseif ($dVal > 0) {
                        $descTxtPrev = ' con Bs. ' . number_format($dVal, 2) . ' de descuento';
                    }
                }
                $draft['esperando'] = 'cliente';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    "¡Perfecto! Anoté {$listaTxt}{$descTxtPrev}. ¿A nombre de qué cliente o taller va esta venta? (Si es cliente de paso o no recuerdas el nombre, dime 'no sé' o 'normal').",
                    $origen
                );
            }

            $cliArr = $this->estadoCampo($draft, 'cliente') === 'completado'
                ? $this->valorCampo($draft, 'cliente')
                : $this->text->formatearClienteArray($this->text->obtenerClienteOcasionalMostrador());
            $esNuevoCli = !empty($cliArr['cliente_nuevo_sugerido']);
            $cliDescLabel = $esNuevoCli
                ? "{$cliArr['nombre_razon_social']} (como cliente nuevo/ocasional)"
                : $cliArr['nombre_razon_social'];

            if ($this->estadoCampo($draft, 'descuento') === 'pendiente') {
                $pctFrec = (float) ($cliArr['descuento_frecuente'] ?? 0);
                $draft['esperando'] = 'descuento';
                $this->guardarBorrador($draft);

                if ($pctFrec > 0) {
                    $montoDesc = round($bruto * ($pctFrec / 100.0), 2);
                    return $this->text->respuestaAclaracion(
                        "Anoté {$listaTxt} para {$cliDescLabel} (tiene {$pctFrec}% de descuento frecuente, serían Bs. " . number_format($montoDesc, 2) . " menos). ¿Se lo aplico o va a precio normal?",
                        $origen
                    );
                }
                return $this->text->respuestaAclaracion(
                    "Anoté {$listaTxt} para {$cliDescLabel}. ¿Aplicamos algún descuento (en % o Bs.) o va a precio normal?",
                    $origen
                );
            }

            $descuento = $this->estadoCampo($draft, 'descuento') === 'completado'
                ? round((float) $this->valorCampo($draft, 'descuento', 0.0), 2)
                : 0.0;

            if ($this->estadoCampo($draft, 'metodo_pago') === 'pendiente') {
                $neto = round(max(0.0, $bruto - $descuento), 2);
                $descDetalleTxt = '';
                if (!empty($draft['descuento_pct_aplicado']) && $descuento > 0) {
                    $descDetalleTxt = " con {$draft['descuento_pct_aplicado']}% de descuento (Bs. " . number_format($descuento, 2) . ' menos)';
                } elseif ($descuento > 0) {
                    $descDetalleTxt = ' con Bs. ' . number_format($descuento, 2) . ' de descuento';
                } elseif ($this->estadoCampo($draft, 'descuento') === 'completado') {
                    $descDetalleTxt = ' a precio normal';
                }
                $draft['esperando'] = 'metodo_pago';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    "¡Perfecto! Anoté {$listaTxt} para {$cliDescLabel}{$descDetalleTxt}. El total queda en Bs. " . number_format($neto, 2) . ". Solo me falta saber: ¿cómo te pagó? (Efectivo, Transferencia/QR, Tarjeta o Mixto).",
                    $origen
                );
            }

            $metodoPago = $this->estadoCampo($draft, 'metodo_pago') === 'completado'
                ? (string) $this->valorCampo($draft, 'metodo_pago', 'Efectivo')
                : 'Efectivo';

            $pagoDetalle = $this->estadoCampo($draft, 'pago_detalle') === 'completado'
                ? (array) $this->valorCampo($draft, 'pago_detalle', [])
                : [];

            $this->limpiarBorradorOperacion();
            return $this->executor->generarTarjetaVenta($draft, (object) $cliArr, $items, $descuento, $metodoPago, $pagoDetalle, $sesionAbierta, $userId, $origen);
        }

        // ═══ 2. COTIZACIÓN ═══
        if ($op === 'preparar_cotizacion') {
            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->text->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            if ($this->estadoCampo($draft, 'items') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'items', 'pendiente', []);
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    'Para armar la cotización necesito sí o sí el repuesto y la cantidad. Sin eso no puedo emitirla. ¿Qué repuesto cotizamos y cuántas unidades?',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'items') === 'pendiente' || empty($this->valorCampo($draft, 'items', []))) {
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('¡Claro que sí! Armemos la proforma. ¿Qué repuesto necesitas cotizar y cuántas unidades?', $origen);
            }

            if ($this->estadoCampo($draft, 'cliente') === 'pendiente') {
                $draft['esperando'] = 'cliente';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿A nombre de qué cliente o taller emitimos la cotización? (Si no tienes el dato, dime 'no sé' y la dejo a Cliente Ocasional Mostrador).", $origen);
            }

            if ($this->estadoCampo($draft, 'dias_vigencia') === 'pendiente') {
                $draft['esperando'] = 'dias_vigencia';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿Por cuántos días de vigencia quieres esta proforma? (Por defecto son 7 días; dime el número o 'normal').", $origen);
            }

            $cliArr = $this->estadoCampo($draft, 'cliente') === 'completado'
                ? $this->valorCampo($draft, 'cliente')
                : $this->text->formatearClienteArray($this->text->obtenerClienteOcasionalMostrador());
            $dias = $this->estadoCampo($draft, 'dias_vigencia') === 'completado'
                ? (int) $this->valorCampo($draft, 'dias_vigencia', 7)
                : 7;

            $this->limpiarBorradorOperacion();
            return $this->executor->generarTarjetaCotizacion($draft, (object) $cliArr, $this->valorCampo($draft, 'items', []), $dias, $userId, $origen);
        }

        // ═══ 3. COMPRA / RECEPCIÓN DE MERCADERÍA ═══
        if ($op === 'preparar_compra') {
            if ($this->estadoCampo($draft, 'proveedor') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'proveedor', 'pendiente', null);
                $draft['esperando'] = 'seleccion_proveedor';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    'Para registrar una compra necesito obligatoriamente el proveedor o distribuidor. Sin ese dato no puedo asentar el ingreso. Dime cuál proveedor es cuando lo tengas.',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'proveedor') === 'pendiente') {
                $provs = DB::table('proveedores')->where('estado', true)->orderBy('razon_social')->limit(8)->get();
                $opts = [];
                $lineas = ['¡Con gusto registramos la compra! ¿A qué proveedor le compraste?'];
                foreach ($provs as $idx => $pr) {
                    $num = $idx + 1;
                    $opts[] = ['id_proveedor' => (int) $pr->id_proveedor, 'razon_social' => $pr->razon_social];
                    $lineas[] = "{$num}. {$pr->razon_social}";
                }
                $lineas[] = 'Dime el nombre o el número del proveedor.';
                $draft['proveedores_opciones'] = $opts;
                $draft['esperando'] = 'seleccion_proveedor';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(implode("\n", $lineas), $origen);
            }

            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->text->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            if ($this->estadoCampo($draft, 'items') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'items', 'pendiente', []);
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    'Para asentar la compra necesito sí o sí saber qué repuestos llegaron y cuántas unidades. Si es un producto nuevo, dímelo y lo registramos al paso.',
                    $origen
                );
            }
            if ($this->estadoCampo($draft, 'items') === 'pendiente' || empty($this->valorCampo($draft, 'items', []))) {
                $prov = $this->valorCampo($draft, 'proveedor');
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    "Perfecto, proveedor {$prov['razon_social']}. ¿Qué repuesto llegó, cuántas unidades y a qué costo unitario en Bs.?",
                    $origen
                );
            }

            $items = $this->valorCampo($draft, 'items', []);
            $ultimoItem = $items[count($items) - 1];
            if ($this->estadoCampo($draft, 'costo_unitario') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'costo_unitario', 'pendiente', null);
                $draft['esperando'] = 'costo_unitario';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    "Para guardar una compra es obligatorio el costo unitario de {$ultimoItem['nombre_pieza']} (en Bs., mayor a 0). Sin ese costo no puedo registrar la entrada. ¿A cuánto te costó cada unidad?",
                    $origen
                );
            }
            if (empty($ultimoItem['costo_confirmado']) || (float) ($ultimoItem['precio_unitario'] ?? 0) <= 0) {
                $draft['esperando'] = 'costo_unitario';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    "Anoté {$ultimoItem['cantidad']}x {$ultimoItem['nombre_pieza']}. ¿A qué costo unitario (en Bs.) te salió cada unidad?",
                    $origen
                );
            }

            if ($this->estadoCampo($draft, 'numero_factura_nota') === 'pendiente') {
                $draft['esperando'] = 'numero_factura_nota';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
                    "¿Tienes el número de factura o nota de entrega? (Si no la recuerdas o no tiene factura, dime 'no sé' y genero un código interno automático).",
                    $origen
                );
            }

            if ($this->estadoCampo($draft, 'tipo_pago') === 'pendiente') {
                $draft['esperando'] = 'tipo_pago';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion(
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
            return $this->executor->generarTarjetaCompra($draft, $provObj, $items, $factura, $tipoPago, $userId, $origen);
        }

        // ═══ 4. AJUSTE DE INVENTARIO / MERMA / USO INTERNO ═══
        if ($op === 'preparar_ajuste_inventario') {
            if (!empty($draft['opciones_pendientes'])) {
                $draft['esperando'] = 'seleccion_producto';
                $this->guardarBorrador($draft);
                return $this->text->construirMensajeDesambiguacion($draft['opciones_pendientes'], (bool) $draft['es_lista_dudosa'], $origen);
            }

            if ($this->estadoCampo($draft, 'producto') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'producto', 'pendiente', null);
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('Para hacer el ajuste necesito sí o sí saber qué repuesto es. ¿Cuál repuesto vamos a ajustar?', $origen);
            }
            if ($this->estadoCampo($draft, 'producto') === 'pendiente') {
                $draft['esperando'] = 'producto';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('¡Claro! Vamos a registrar el ajuste en almacén. ¿De qué repuesto se trata y cuántas unidades?', $origen);
            }

            $prodArr = $this->valorCampo($draft, 'producto');

            if ($this->estadoCampo($draft, 'tipo_ajuste') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'tipo_ajuste', 'pendiente', null);
                $draft['esperando'] = 'tipo_ajuste';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("Necesito saber el tipo de ajuste para {$prodArr['nombre_pieza']}: ¿es Merma, Uso Interno, Pérdida/Extravío, Devolución Dañada o Conteo Físico?", $origen);
            }
            if ($this->estadoCampo($draft, 'tipo_ajuste') === 'pendiente') {
                $draft['esperando'] = 'tipo_ajuste';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿Qué tipo de ajuste es para {$prodArr['nombre_pieza']}: Merma (dañado), Uso Interno, Pérdida, Devolución Dañada o Conteo Físico?", $origen);
            }

            if ($this->estadoCampo($draft, 'cantidad') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'cantidad', 'pendiente', null);
                $draft['esperando'] = 'cantidad';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("Necesito saber cuántas unidades de {$prodArr['nombre_pieza']} ajustamos para poder registrarlo. ¿Cuántas son?", $origen);
            }
            if ($this->estadoCampo($draft, 'cantidad') === 'pendiente') {
                $draft['esperando'] = 'cantidad';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿Cuántas unidades de {$prodArr['nombre_pieza']} anotamos para este ajuste de {$this->valorCampo($draft, 'tipo_ajuste')}?", $origen);
            }

            if ($this->estadoCampo($draft, 'motivo') === 'pendiente') {
                $draft['esperando'] = 'motivo';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿Cuál es el motivo de este ajuste? (Por ejemplo: 'se rompió en almacén', o dime 'no sé' para anotar uno general).", $origen);
            }

            $tipoAj = (string) $this->valorCampo($draft, 'tipo_ajuste');
            $cant = max(1, (int) $this->valorCampo($draft, 'cantidad', 1));
            $motivoRaw = $this->estadoCampo($draft, 'motivo') === 'completado' ? (string) $this->valorCampo($draft, 'motivo', '') : '';
            $motivo = $this->text->formatearMotivoAjuste($tipoAj, $motivoRaw, $prodArr['nombre_pieza']);

            $this->limpiarBorradorOperacion();
            return $this->executor->generarTarjetaAjuste($draft, (object) $prodArr, $tipoAj, $cant, $motivo, $userId, $origen);
        }

        // ═══ 5. REGISTRAR CLIENTE / TALLER RÁPIDO ═══
        if ($op === 'preparar_cliente') {
            if ($this->estadoCampo($draft, 'nombre_razon_social') === 'omitido_por_usuario') {
                $this->setCampo($draft, 'nombre_razon_social', 'pendiente', null);
                $draft['esperando'] = 'nombre_razon_social';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('Para registrar un cliente o taller necesito obligatoriamente su nombre o razón social (mínimo 3 letras). ¿Cómo se llama?', $origen);
            }
            if ($this->estadoCampo($draft, 'nombre_razon_social') === 'pendiente') {
                $draft['esperando'] = 'nombre_razon_social';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('¡Con gusto lo registramos! ¿Cuál es el nombre completo del cliente o nombre del taller?', $origen);
            }

            $nomCli = (string) $this->valorCampo($draft, 'nombre_razon_social');

            if ($this->estadoCampo($draft, 'tipo_cliente') === 'pendiente') {
                $draft['esperando'] = 'tipo_cliente';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("Perfecto, anoté a {$nomCli}. ¿Es un cliente Particular, Taller/Mecánico o Empresa/Flota? (Si no sabes, dime 'normal').", $origen);
            }
            if ($this->estadoCampo($draft, 'ci_nit') === 'pendiente') {
                $draft['esperando'] = 'ci_nit';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿Tienes el CI o NIT de {$nomCli}? (Si no lo tienes, dime 'no sé').", $origen);
            }
            if ($this->estadoCampo($draft, 'telefono') === 'pendiente') {
                $draft['esperando'] = 'telefono';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion("¿Tienes su número de teléfono o WhatsApp? (Si no, dime 'no tengo').", $origen);
            }
            if ($this->estadoCampo($draft, 'descuento_frecuente') === 'pendiente') {
                $draft['esperando'] = 'descuento_frecuente';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('¿Le autorizamos algún porcentaje de descuento frecuente (por ejemplo 5% o 10%) o lo dejamos en 0%?', $origen);
            }

            $tipo = $this->estadoCampo($draft, 'tipo_cliente') === 'completado' ? (string) $this->valorCampo($draft, 'tipo_cliente') : 'Particular';
            $ciNit = $this->estadoCampo($draft, 'ci_nit') === 'completado' ? (string) $this->valorCampo($draft, 'ci_nit') : '0';
            $tel = $this->estadoCampo($draft, 'telefono') === 'completado' ? (string) $this->valorCampo($draft, 'telefono') : '';
            $desc = $this->estadoCampo($draft, 'descuento_frecuente') === 'completado' ? (float) $this->valorCampo($draft, 'descuento_frecuente') : 0.0;

            $this->limpiarBorradorOperacion();
            return $this->executor->generarTarjetaCliente($draft, $nomCli, $tipo, $ciNit, $tel, $desc, $userId, $origen);
        }

        $this->limpiarBorradorOperacion();
        return $this->text->respuestaInformativa('¿Me repites qué te gustaría hacer? Puedo ayudarte con ventas, cotizaciones, compras, ajustes, clientes o consultas del negocio.', $origen);
    }

    // ─── Subflujo de alta de producto nuevo durante una compra ───

    private function iniciarNuevoProductoDraft(string $textoPropuesto, ?int $cantidad, ?float $costo): array
    {
        $nombreLimpio = mb_convert_case(trim($textoPropuesto), MB_CASE_TITLE, 'UTF-8');
        return [
            'nombre_propuesto'   => $nombreLimpio,
            'cantidad'           => max(1, (int) ($cantidad ?: 1)),
            'costo_compra'       => ($costo !== null && $costo > 0) ? round($costo, 2) : null,
            'nombre_pieza'       => null,
            'id_categoria'       => null,
            'categoria_nombre'   => null,
            'categorias_opts'    => [],
            'codigo'             => null,
            'precio_venta'       => null,
            'margen'             => 30.0,
            'pasillo'            => null,
            'estante'            => null,
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

        if ($esperando === 'confirmar_producto_nuevo') {
            if ($this->text->esRespuestaNegativa($norm)) {
                $draft['nuevo_producto_draft'] = null;
                $draft['esperando'] = 'items';
                $this->guardarBorrador($draft);
                return $this->text->respuestaAclaracion('¡Sin problema! Dime de nuevo qué repuesto del catálogo llegó en esta compra.', $origen);
            }
            if ($this->text->esRespuestaAfirmativa($norm) || str_contains($norm, 'nuevo') || str_contains($norm, 'registrar')) {
                $draft['esperando'] = 'nuevo_producto_nombre';
                $this->guardarBorrador($draft);
                $prop = $np['nombre_propuesto'] ?? '';
                if (mb_strlen($prop) >= 3) {
                    return $this->text->respuestaAclaracion(
                        "¡Perfecto, lo damos de alta ahora! ¿Con qué nombre exacto lo guardamos en el catálogo? (Puedes decirme el nombre o responder 'así está bien' para usar \"{$prop}\").",
                        $origen
                    );
                }
                return $this->text->respuestaAclaracion('¡Perfecto, lo damos de alta ahora! ¿Cuál es el nombre exacto del repuesto nuevo?', $origen);
            }
            $this->guardarBorrador($draft);
            return $this->text->respuestaAclaracion('¿Quieres que registremos ese repuesto como producto nuevo ahora mismo? Respóndeme "sí" o "no".', $origen);
        }

        if ($esperando === 'nuevo_producto_nombre') {
            if ($this->text->esDesconocimientoONegativa($norm, 'nombre') && mb_strlen((string) $np['nombre_propuesto']) < 3) {
                return $this->text->respuestaAclaracion('Necesito al menos el nombre del repuesto nuevo (mínimo 3 letras) para poder crearlo. ¿Cómo se llama?', $origen);
            }
            if (preg_match('/\b(si|asi esta bien|ese|igual|el mismo|dale|perfecto)\b/u', $norm) && mb_strlen((string) $np['nombre_propuesto']) >= 3) {
                $np['nombre_pieza'] = $np['nombre_propuesto'];
            } else {
                $nombreDado = trim($preguntaLimpia);
                if (mb_strlen($nombreDado) < 3) {
                    return $this->text->respuestaAclaracion('El nombre es muy cortito; dame al menos 3 letras para identificar bien el repuesto.', $origen);
                }
                $np['nombre_pieza'] = $nombreDado;
            }
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        if ($esperando === 'nuevo_producto_categoria') {
            $opts = $np['categorias_opts'] ?? [];
            $elegida = null;
            $idx = $this->text->extraerIndiceOrdinal($norm);
            if ($idx !== null && isset($opts[$idx - 1])) {
                $elegida = $opts[$idx - 1];
            } elseif (preg_match('/^\s*(\d{1,2})\s*$/', $norm, $mN) && isset($opts[(int) $mN[1] - 1])) {
                $elegida = $opts[(int) $mN[1] - 1];
            } else {
                foreach ($opts as $c) {
                    if (str_contains($this->text->normalizarTexto($c['nombre']), $norm) || str_contains($norm, $this->text->normalizarTexto($c['nombre']))) {
                        $elegida = $c;
                        break;
                    }
                }
            }
            if (!$elegida && $this->text->esDesconocimientoONegativa($norm, 'categoria') && !empty($opts)) {
                $elegida = $opts[0];
            }
            if (!$elegida) {
                return $this->text->respuestaAclaracion('No ubiqué esa categoría. Dime el número de la lista (por ejemplo "1" o "la 2").', $origen);
            }
            $np['id_categoria'] = (int) $elegida['id_categoria'];
            $np['categoria_nombre'] = $elegida['nombre'];
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        if ($esperando === 'nuevo_producto_codigo') {
            if ($this->text->esDesconocimientoONegativa($norm, 'codigo')) {
                $np['codigo'] = $this->executor->generarCodigoInternoUnico();
            } else {
                $cod = strtoupper(preg_replace('/[^A-Z0-9\-_]/i', '', trim($preguntaLimpia)));
                if (mb_strlen($cod) < 2) {
                    $cod = $this->executor->generarCodigoInternoUnico();
                }
                $np['codigo'] = $cod;
            }
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        if ($esperando === 'nuevo_producto_costo') {
            $costo = $this->text->extraerCostoDeTexto($norm, true);
            if ($costo === null || $costo <= 0) {
                return $this->text->respuestaAclaracion('Para agregarlo a la compra necesito el costo de compra en Bs. (un número mayor a 0). ¿A cuánto te salió cada unidad?', $origen);
            }
            $np['costo_compra'] = round($costo, 2);
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        if ($esperando === 'nuevo_producto_precio') {
            $costoRef = (float) ($np['costo_compra'] ?: 100.0);
            if ($this->text->esDesconocimientoONegativa($norm, 'precio')) {
                $np['precio_venta'] = round($costoRef * 1.30, 2);
                $np['margen'] = 30.00;
            } else {
                $precio = $this->text->extraerCostoDeTexto($norm, true);
                if ($precio === null || $precio <= 0) {
                    return $this->text->respuestaAclaracion("Dime el precio de venta en Bs. (por ejemplo '250') o dime 'no sé' y lo calculo con 30% de ganancia.", $origen);
                }
                $np['precio_venta'] = round($precio, 2);
                $np['margen'] = $costoRef > 0 ? round(max(0.0, (($precio - $costoRef) / $costoRef) * 100.0), 2) : 30.00;
            }
            $draft['nuevo_producto_draft'] = $np;
            return $this->avanzarSubflujoProductoNuevo($draft, $userId, $origen);
        }

        if ($esperando === 'nuevo_producto_ubicacion') {
            if (!$this->text->esDesconocimientoONegativa($norm, 'ubicacion')) {
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
            return $this->text->respuestaAclaracion(
                "Anotado \"{$np['nombre_pieza']}\". ¿En qué categoría lo guardamos?\n" . implode("\n", $lineas) . "\nDime el número o el nombre.",
                $origen
            );
        }

        if (empty($np['codigo'])) {
            $draft['esperando'] = 'nuevo_producto_codigo';
            $this->guardarBorrador($draft);
            return $this->text->respuestaAclaracion(
                "Categoría {$np['categoria_nombre']}. ¿Tienes el código OEM o código interno del repuesto? (Si no lo tienes, dime 'no sé' y genero un código automático).",
                $origen
            );
        }

        if (empty($np['costo_compra'])) {
            $draft['esperando'] = 'nuevo_producto_costo';
            $this->guardarBorrador($draft);
            return $this->text->respuestaAclaracion(
                "Código {$np['codigo']}. ¿A cuánto te costó cada unidad en esta compra (en Bs.)?",
                $origen
            );
        }

        if (empty($np['precio_venta'])) {
            $sugerido30 = number_format(round((float) $np['costo_compra'] * 1.30, 2), 2);
            $draft['esperando'] = 'nuevo_producto_precio';
            $this->guardarBorrador($draft);
            return $this->text->respuestaAclaracion(
                "¿A qué precio de venta sugerido (en Bs.) lo venderemos al público? (Si me dices 'no sé', lo calculo automático con 30% de margen: Bs. {$sugerido30}).",
                $origen
            );
        }

        if (empty($np['ubicacion_resuelta'])) {
            $draft['esperando'] = 'nuevo_producto_ubicacion';
            $this->guardarBorrador($draft);
            return $this->text->respuestaAclaracion(
                'Precio de venta: Bs. ' . number_format((float) $np['precio_venta'], 2) . ". ¿En qué pasillo y estante lo vas a guardar? (Opcional: si aún no decides, dime 'no sé').",
                $origen
            );
        }

        $creado = $this->executor->crearProductoNuevo($np);
        $nuevoItem = $creado['item'];
        $codInterno = $creado['codigo_interno'];
        $cant = $nuevoItem['cantidad'];
        $costo = $nuevoItem['precio_unitario'];

        $this->setCampo($draft, 'items', 'completado', [$nuevoItem]);
        $this->setCampo($draft, 'costo_unitario', 'completado', $costo);
        $draft['nuevo_producto_draft'] = null;
        $draft['esperando'] = null;

        $siguiente = $this->evaluarEstadoBorrador($draft, $userId, $origen);
        $prefijo = "¡Listo! Registré \"{$np['nombre_pieza']}\" (código {$codInterno}, venta Bs. " . number_format((float) $np['precio_venta'], 2) . ") en tu catálogo y lo sumé a esta compra ({$cant}x a Bs. " . number_format($costo, 2) . ").\n";
        $siguiente['texto_pantalla'] = $prefijo . ($siguiente['texto_pantalla'] ?? '');
        $siguiente['texto_voz'] = $this->text->prepararTextoParaVoz($siguiente['texto_pantalla']);
        return $siguiente;
    }

    private function extraerCamposClienteNuevo(array &$draft, string $preguntaLimpia, string $norm, ?string $esperando, bool $esPrimerTurno): void
    {
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

        if (preg_match('/\b(?:tel[eé]fono|cel(?:ular)?|whatsapp|wsp)\s*[:#]?\s*([\d\-\+\s]{7,15})/iu', $preguntaLimpia, $mTel)) {
            $this->setCampo($draft, 'telefono', 'completado', trim($mTel[1]));
            if ($esperando === 'telefono') {
                $draft['esperando'] = null;
            }
        } elseif ($esperando === 'telefono' && preg_match('/\b(\d{7,12})\b/', $norm, $mTelSolo)) {
            $this->setCampo($draft, 'telefono', 'completado', $mTelSolo[1]);
            $draft['esperando'] = null;
        }

        if (preg_match('/\b(?:ci|nit|ci\/nit|carnet)\s*[:#]?\s*([a-z0-9\-]{4,20})/iu', $preguntaLimpia, $mCi)) {
            $this->setCampo($draft, 'ci_nit', 'completado', strtoupper(trim($mCi[1])));
            if ($esperando === 'ci_nit') {
                $draft['esperando'] = null;
            }
        } elseif ($esperando === 'ci_nit' && preg_match('/\b([0-9]{4,15}(?:\-[a-z0-9]{1,3})?)\b/i', $norm, $mCiSolo)) {
            $this->setCampo($draft, 'ci_nit', 'completado', strtoupper($mCiSolo[1]));
            $draft['esperando'] = null;
        }

        if (preg_match('/(?:descuento(?:\s+frecuente)?(?:\s+de(?:l)?)?)\s*(\d+(?:[\.,]\d+)?)\s*%?/u', $norm, $mDesc)) {
            $val = min(50.0, max(0.0, (float) str_replace(',', '.', $mDesc[1])));
            $this->setCampo($draft, 'descuento_frecuente', 'completado', $val);
            if ($esperando === 'descuento_frecuente') {
                $draft['esperando'] = null;
            }
        } elseif ($esperando === 'descuento_frecuente') {
            if ($this->text->esRespuestaNegativa($norm) || preg_match('/\b(cero|0|sin|normal)\b/u', $norm)) {
                $this->setCampo($draft, 'descuento_frecuente', 'completado', 0.0);
                $draft['esperando'] = null;
            } elseif (preg_match('/(\d+(?:[\.,]\d+)?)/', $norm, $mDSolo)) {
                $val = min(50.0, max(0.0, (float) str_replace(',', '.', $mDSolo[1])));
                $this->setCampo($draft, 'descuento_frecuente', 'completado', $val);
                $draft['esperando'] = null;
            }
        }

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
}
