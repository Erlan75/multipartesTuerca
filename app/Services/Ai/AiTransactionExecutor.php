<?php

namespace App\Services\Ai;

use App\Helpers\Bitacora;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Construcción de Tarjetas de Confirmación, alta de productos nuevos en compras
 * y ejecución atómica de procedimientos almacenados Smart-DB (CU39).
 */
class AiTransactionExecutor
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

    public function __construct(
        private readonly AiTextNormalizer $text
    ) {}

    public function rolPuedeEjecutarOperacion(int $rolId, string $operacion): bool
    {
        return in_array($operacion, self::PERMISOS_TRANSACCIONALES[$rolId] ?? [], true);
    }

    /**
     * Ejecuta en PostgreSQL la operación previamente confirmada por el usuario mediante token.
     */
    public function ejecutarAccionConfirmada(array $payload, int $userId, int $rolId, string $nombreRol, string $ipOrigen): array
    {
        $operacion = $payload['operacion'] ?? '';

        if (!$this->rolPuedeEjecutarOperacion($rolId, $operacion)) {
            $msg = "Tu cuenta de {$nombreRol} no tiene permiso para completar esta operación. Si la necesitas, pídele apoyo al administrador.";
            return [
                'ok'             => false,
                'tipo'           => 'restriccion',
                'origen'         => 'local',
                'texto_pantalla' => $msg,
                'texto_voz'      => $this->text->prepararTextoParaVoz($msg),
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
        } catch (\Throwable $e) {
            Log::warning('AiTransactionExecutor (Confirmación): ' . $e->getMessage());
            $traducido = $this->traducirErrorAmigable($e->getMessage());
            return [
                'ok'                 => false,
                'tipo'               => 'error',
                'origen'             => 'local',
                'texto_pantalla'     => $traducido['mensaje'],
                'texto_voz'          => $this->text->prepararTextoParaVoz($traducido['mensaje']),
                'enlace_comprobante' => $traducido['enlace'] ?? null,
                'etiqueta_enlace'    => $traducido['etiqueta'] ?? null,
                'datos_resumen'      => [],
            ];
        }
    }

    /**
     * Inserta un producto nuevo con stock inicial 0 durante el subflujo de compra y audita en bitácora.
     */
    public function crearProductoNuevo(array $np): array
    {
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

        $cant = max(1, (int) $np['cantidad']);
        $costo = round((float) $np['costo_compra'], 2);

        return [
            'id_producto'    => $idProducto,
            'codigo_interno' => $codInterno,
            'codigo_oem'     => $codOem,
            'item'           => [
                'id_producto'      => $idProducto,
                'codigo_oem'       => $codOem,
                'nombre_pieza'     => $np['nombre_pieza'],
                'cantidad'         => $cant,
                'precio_unitario'  => $costo,
                'subtotal'         => round($cant * $costo, 2),
                'stock_actual'     => 0,
                'costo_confirmado' => true,
            ],
        ];
    }

    public function generarCodigoInternoUnico(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $cand = 'REP-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            if (!DB::table('productos')->whereRaw('LOWER(TRIM(codigo_interno)) = LOWER(TRIM(?))', [$cand])->exists()) {
                return $cand;
            }
        }
        return 'REP-' . date('His');
    }

    // ─── Generadores de Tarjetas de Confirmación (con snapshot para recuperación tras cancelar) ───

    public function generarTarjetaVenta(
        array $draft,
        object $cliente,
        array $items,
        float $descuento,
        string $metodoPago,
        array $pagoDetalle,
        object $sesionAbierta,
        int $userId,
        string $origen
    ): array {
        $bruto = round(array_sum(array_column($items, 'subtotal')), 2);
        $descuento = round(max(0.0, min($bruto, $descuento)), 2);
        $neto = round(max(0.0, $bruto - $descuento), 2);
        $metodoPago = $this->text->normalizarMetodoPago($metodoPago);

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

        $esClienteNuevoSugerido = !empty($cliente->cliente_nuevo_sugerido);

        $payload = [
            'operacion'              => 'preparar_venta',
            'user_id'                => $userId,
            'created_at'             => now()->timestamp,
            'id_cliente'             => (int) $cliente->id_cliente,
            'cliente_nombre'         => $cliente->nombre_razon_social,
            'cliente_nuevo_sugerido' => $esClienteNuevoSugerido,
            'id_sesion_caja'         => (int) $sesionAbierta->id_sesion,
            'metodo_pago'            => $metodoPago,
            'descuento_total'        => $descuento,
            'subtotal_bruto'         => $bruto,
            'total_neto'             => $neto,
            'monto_recibido'         => $montoRecibido,
            'nro_referencia'         => $referencia,
            'monto_efectivo'         => $montoEfec,
            'monto_digital'          => $montoDig,
            'items'                  => $items,
            'draft_snapshot'         => $draft,
        ];

        $etiquetaCliente = $esClienteNuevoSugerido
            ? "{$cliente->nombre_razon_social} (Cliente nuevo / ocasional)"
            : $cliente->nombre_razon_social . ' (' . ($cliente->ci_nit ?: 'S/N') . ')';

        $resumen = [
            'Cliente'    => $etiquetaCliente,
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
            'texto_voz'        => $this->text->prepararTextoParaVoz($texto),
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

    public function generarTarjetaCotizacion(array $draft, object $cliente, array $items, int $diasVigencia, int $userId, string $origen): array
    {
        $diasVigencia = max(1, min(90, $diasVigencia));
        $total = round(array_sum(array_column($items, 'subtotal')), 2);
        $esClienteNuevoSugerido = !empty($cliente->cliente_nuevo_sugerido);

        $payload = [
            'operacion'              => 'preparar_cotizacion',
            'user_id'                => $userId,
            'created_at'             => now()->timestamp,
            'id_cliente'             => (int) $cliente->id_cliente,
            'cliente_nombre'         => $cliente->nombre_razon_social,
            'cliente_nuevo_sugerido' => $esClienteNuevoSugerido,
            'dias_vigencia'          => $diasVigencia,
            'total_neto'             => $total,
            'items'                  => $items,
            'draft_snapshot'         => $draft,
        ];

        $token = $this->guardarAccionPendiente($payload);
        $texto = "¡Listo! Preparé la cotización para {$cliente->nombre_razon_social} por Bs. " . number_format($total, 2) . ", válida por {$diasVigencia} días. Dale a Confirmar y Ejecutar para emitirla.";

        return [
            'ok'               => true,
            'tipo'             => 'confirmacion',
            'origen'           => $origen,
            'texto_pantalla'   => $texto,
            'texto_voz'        => $this->text->prepararTextoParaVoz($texto),
            'datos_resumen'    => [],
            'accion_pendiente' => [
                'action_token' => $token,
                'operacion'    => 'preparar_cotizacion',
                'titulo'       => 'Resumen de Cotización / Proforma',
                'badge'        => 'Proforma',
                'resumen'      => [
                    'Cliente'  => $esClienteNuevoSugerido ? "{$cliente->nombre_razon_social} (Cliente nuevo / ocasional)" : $cliente->nombre_razon_social,
                    'Vigencia' => "{$diasVigencia} días (hasta " . Carbon::today()->addDays($diasVigencia)->format('d/m/Y') . ')',
                    'Total'    => 'Bs. ' . number_format($total, 2),
                ],
                'items'        => $items,
                'total_neto'   => $total,
                'advertencia'  => null,
            ],
        ];
    }

    public function generarTarjetaCompra(array $draft, object $proveedor, array $items, string $factura, string $tipoPago, int $userId, string $origen): array
    {
        $tipoPago = str_contains($this->text->normalizarTexto($tipoPago), 'cred') ? 'Crédito' : 'Contado';
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
            'texto_voz'        => $this->text->prepararTextoParaVoz($texto),
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

    public function generarTarjetaAjuste(array $draft, object $producto, string $tipoAjuste, int $cantidad, string $motivo, int $userId, string $origen): array
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
            'texto_voz'        => $this->text->prepararTextoParaVoz($texto),
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

    public function generarTarjetaCliente(array $draft, string $nombre, string $tipo, string $ciNit, string $telefono, float $descuento, int $userId, string $origen): array
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
            'texto_voz'        => $this->text->prepararTextoParaVoz($texto),
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

    public function traducirErrorAmigable(string $raw): array
    {
        $norm = $this->text->normalizarTexto($raw);
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

    // ─── Ejecutores atómicos en PostgreSQL ───

    /**
     * Si el cliente fue capturado por nombre nuevo y aún apunta al ID 1 (Ocasional),
     * lo busca o lo crea automáticamente antes de ejecutar la venta o cotización.
     */
    private function resolverOCrearClienteSugerido(array $payload): int
    {
        $idCliente = (int) ($payload['id_cliente'] ?? 1);
        $nombre = trim((string) ($payload['cliente_nombre'] ?? ''));
        if (empty($payload['cliente_nuevo_sugerido']) || $idCliente !== 1 || mb_strlen($nombre) < 3) {
            return $idCliente > 0 ? $idCliente : 1;
        }

        try {
            $existente = DB::table('clientes')
                ->whereRaw('LOWER(TRIM(nombre_razon_social)) = LOWER(TRIM(?))', [$nombre])
                ->first();
            if ($existente) {
                return (int) $existente->id_cliente;
            }

            $tipo = preg_match('/\btaller\b/i', $nombre)
                ? 'Taller/Mecánico'
                : (preg_match('/\b(empresa|flota|srl|sa)\b/i', $nombre) ? 'Empresa/Flota' : 'Particular');

            $nuevoId = (int) DB::table('clientes')->insertGetId([
                'nombre_razon_social' => $nombre,
                'ci_nit'              => '0',
                'telefono'            => null,
                'tipo_cliente'        => $tipo,
                'descuento_frecuente' => 0.00,
            ], 'id_cliente');

            Bitacora::registrar(
                'Ventas / Clientes',
                'INSERT',
                $nuevoId,
                json_encode(['origen' => 'ASISTENTE_IA_AUTO_CLIENTE', 'nombre_razon_social' => $nombre, 'tipo_cliente' => $tipo])
            );

            return $nuevoId > 0 ? $nuevoId : 1;
        } catch (\Throwable $e) {
            Log::warning('AiTransactionExecutor::resolverOCrearClienteSugerido fallback a ID 1: ' . $e->getMessage());
            return 1;
        }
    }

    private function guardarAccionPendiente(array $payload): string
    {
        $token = (string) Str::uuid();
        session()->put("ai_pending_action.{$token}", $payload);
        return $token;
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

        $idCliente = $this->resolverOCrearClienteSugerido($payload);

        $detallesPago = [
            'origen'          => 'ASISTENTE_IA_CONFIRMADO',
            'cliente_ref'     => $payload['cliente_nombre'] ?? null,
            'metodo_pago'     => $metodoPago,
            'monto_recibido'  => $montoRecibido,
            'cambio_devuelto' => $vuelto,
            'nro_referencia'  => $referencia,
            'monto_efectivo'  => $montoEfec,
            'monto_digital'   => $montoDig,
        ];

        $res = DB::select('CALL sp_registrar_venta(?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, NULL)', [
            $idCliente,
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
            'texto_voz'          => $this->text->prepararTextoParaVoz($texto),
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

        $idCliente = $this->resolverOCrearClienteSugerido($payload);
        $dias = (int) ($payload['dias_vigencia'] ?? 7);
        $res = DB::select('CALL sp_registrar_cotizacion(?, ?, ?, ?::jsonb, NULL)', [
            $idCliente,
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
            'texto_voz'          => $this->text->prepararTextoParaVoz($texto),
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
            'texto_voz'          => $this->text->prepararTextoParaVoz($texto),
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
            'texto_voz'          => $this->text->prepararTextoParaVoz($texto),
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
            'texto_voz'          => $this->text->prepararTextoParaVoz($texto),
            'enlace_comprobante' => route('clientes.show', $idCliente),
            'etiqueta_enlace'    => 'Ver Ficha del Cliente',
            'datos_resumen'      => [],
        ];
    }
}
