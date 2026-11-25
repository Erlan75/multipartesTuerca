<?php

namespace App\Services;

use App\Services\Ai\AiDraftManager;
use App\Services\Ai\AiGeminiClient;
use App\Services\Ai\AiTextNormalizer;
use App\Services\Ai\AiTransactionExecutor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fachada principal del Asistente Conversacional y Transaccional IA (CU39).
 * Orquesta AiGeminiClient, AiTextNormalizer, AiDraftManager y AiTransactionExecutor,
 * manteniendo compatibilidad total con AsistenteIaController.
 */
class AiSqlAssistantService
{
    public const PERMISOS_TRANSACCIONALES = AiTransactionExecutor::PERMISOS_TRANSACCIONALES;

    public function __construct(
        private readonly AiGeminiClient $gemini,
        private readonly AiTextNormalizer $text,
        private readonly AiDraftManager $drafts,
        private readonly AiTransactionExecutor $executor
    ) {}

    /**
     * Punto de entrada principal conversacional del Asistente IA (CU39).
     */
    public function procesarConsulta(string $pregunta, ?object $usuario = null): array
    {
        $preguntaOriginal = trim($pregunta);
        $preguntaLimpia = $this->text->limpiarMuletillasVoz($preguntaOriginal);
        $norm = $this->text->normalizarFonetica($this->text->normalizarTexto($preguntaLimpia));

        $rolId = (int) ($usuario->id_rol ?? 1);
        $nombreRol = $usuario->rol->nombre_rol ?? $this->text->obtenerNombreRol($rolId);
        $userId = (int) ($usuario->id_usuario ?? 1);

        // 0. Comandos explícitos de cancelación o reinicio
        if ($this->text->esComandoCancelacion($norm)) {
            $reinicioTotal = (bool) preg_match('/\b(empezar de nuevo|nueva charla|reiniciar|borrar todo|desde cero)\b/', $norm);
            if ($reinicioTotal) {
                $this->drafts->limpiarConversacionCompleta();
            } else {
                $this->drafts->limpiarBorradorOperacion();
            }
            $resp = $this->text->respuestaInformativa('¡Listo, sin problema! Dejamos eso a un lado. ¿En qué te ayudo ahora?', 'local');
            $this->drafts->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        // Frases vacías o puro ruido de micrófono
        if ($this->text->esFraseVaciaORuido($norm)) {
            $resp = $this->text->respuestaAclaracion('Perdón, no te entendí bien esa parte. ¿Me lo puedes repetir con otras palabras?', 'local');
            $this->drafts->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        $draft = $this->drafts->obtenerBorradorActivo($userId);

        // 1. Recuperación inteligente tras cancelar una tarjeta de confirmación (5 min)
        if ($draft === null) {
            $cancelado = $this->drafts->obtenerBorradorCancelado($userId);
            if ($cancelado !== null && $this->text->esModificacionDeBorradorCancelado($norm, $cancelado)) {
                $this->drafts->limpiarBorradorCancelado();
                $respEdit = $this->drafts->aplicarEdicionSobreBorradorRecuperado(
                    $cancelado,
                    $preguntaOriginal,
                    $preguntaLimpia,
                    $norm,
                    $rolId,
                    $nombreRol,
                    $userId
                );
                $this->drafts->registrarTurnoHistorial($preguntaOriginal, $respEdit['texto_pantalla'] ?? '');
                return $respEdit;
            }
        }

        // Saludos o agradecimientos cuando no hay borrador activo
        if (!$draft && $this->text->esSaludoOConversacionBasica($norm)) {
            $resp = $this->text->responderSaludoAmigable($norm, $usuario);
            $this->drafts->registrarTurnoHistorial($preguntaOriginal, $resp['texto_pantalla']);
            return $resp;
        }

        // Si hay borrador activo y el usuario cambia claramente a una consulta general/analítica
        if ($draft && $this->text->esConsultaGeneralOAnaliticaExplicita($norm) && !$this->drafts->esRespuestaAlBorradorActual($draft, $norm)) {
            $this->drafts->limpiarBorradorOperacion();
            $draft = null;
        }

        // 2. Continuar borrador conversacional en curso
        if ($draft !== null) {
            $respDraft = $this->drafts->continuarBorradorConversacional(
                $draft,
                $preguntaOriginal,
                $preguntaLimpia,
                $norm,
                $rolId,
                $nombreRol,
                $userId
            );
            $this->drafts->registrarTurnoHistorial($preguntaOriginal, $respDraft['texto_pantalla'] ?? '');
            return $respDraft;
        }

        // 3. Detectar intención de operación transaccional
        $operacionDetectada = $this->text->detectarOperacionTransaccional($norm);
        $esIntencionEscritura = $operacionDetectada !== null;

        // 4. Restricciones preventivas por rol para consultas analíticas
        if (!$esIntencionEscritura) {
            $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
            if ($bloqueoRol !== null) {
                $this->drafts->registrarTurnoHistorial($preguntaOriginal, $bloqueoRol['texto_pantalla']);
                return $bloqueoRol;
            }
        }

        // 5. Vía A: Motor Google Gemini con historial multi-turno
        $apiKey = config('services.gemini.key');
        if (!empty($apiKey)) {
            try {
                $resultadoGemini = $this->procesarViaGemini(
                    $preguntaOriginal,
                    $preguntaLimpia,
                    (string) $apiKey,
                    $rolId,
                    $nombreRol,
                    $userId
                );
                if ($resultadoGemini !== null) {
                    $this->drafts->registrarTurnoHistorial($preguntaOriginal, $resultadoGemini['texto_pantalla'] ?? '');
                    return $resultadoGemini;
                }
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService: Conmutando a motor local tras excepción en Gemini: ' . $e->getMessage());
            }
        }

        // 6. Vía B (Transaccional local con máquina de estados completa)
        if ($esIntencionEscritura) {
            $resultadoLocalTx = $this->drafts->iniciarBorradorDesdeLocal(
                $operacionDetectada,
                $preguntaOriginal,
                $preguntaLimpia,
                $norm,
                $rolId,
                $nombreRol,
                $userId
            );
            if ($resultadoLocalTx !== null) {
                $this->drafts->registrarTurnoHistorial($preguntaOriginal, $resultadoLocalTx['texto_pantalla'] ?? '');
                return $resultadoLocalTx;
            }
        }

        // 7. Restricción por rol antes del motor local analítico
        $bloqueoRol = $this->verificarRestriccionRol($preguntaLimpia, $rolId, $nombreRol);
        if ($bloqueoRol !== null) {
            $this->drafts->registrarTurnoHistorial($preguntaOriginal, $bloqueoRol['texto_pantalla']);
            return $bloqueoRol;
        }

        // 8. Vía B (Conocimiento del sistema + consultas sobre todas las tablas)
        $respLocal = $this->consultarViaMotorLocal($preguntaLimpia, $norm, $rolId, $nombreRol);
        $this->drafts->registrarTurnoHistorial($preguntaOriginal, $respLocal['texto_pantalla'] ?? '');
        return $respLocal;
    }

    // ─── API pública delegada (compatibilidad con AsistenteIaController) ───

    public function limpiarBorradorOperacion(): void
    {
        $this->drafts->limpiarBorradorOperacion();
    }

    public function limpiarConversacionCompleta(): void
    {
        $this->drafts->limpiarConversacionCompleta();
    }

    public function guardarBorradorCancelado(array $payload): void
    {
        $this->drafts->guardarBorradorCancelado($payload);
    }

    public function limpiarMuletillasVoz(string $texto): string
    {
        return $this->text->limpiarMuletillasVoz($texto);
    }

    public function prepararTextoParaVoz(string $texto): string
    {
        return $this->text->prepararTextoParaVoz($texto);
    }

    public function rolPuedeEjecutarOperacion(int $rolId, string $operacion): bool
    {
        return $this->executor->rolPuedeEjecutarOperacion($rolId, $operacion);
    }

    public function ejecutarAccionConfirmada(array $payload, int $userId, int $rolId, string $nombreRol, string $ipOrigen): array
    {
        $this->drafts->limpiarBorradorOperacion();
        $this->drafts->limpiarBorradorCancelado();

        $res = $this->executor->ejecutarAccionConfirmada($payload, $userId, $rolId, $nombreRol, $ipOrigen);
        if (!empty($res['ok'])) {
            $this->drafts->registrarTurnoHistorial('[Confirmación de operación]', $res['texto_pantalla'] ?? '');
        }
        return $res;
    }

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

    private function construirSystemPrompt(): string
    {
        return $this->gemini->construirSystemPrompt();
    }

    // ─── Orquestación Vía A (Gemini) ───

    private function procesarViaGemini(
        string $preguntaOriginal,
        string $preguntaLimpia,
        string $apiKey,
        int $rolId,
        string $nombreRol,
        int $userId
    ): ?array {
        $respGemini = $this->gemini->solicitarRespuesta(
            $preguntaOriginal,
            $preguntaLimpia,
            $apiKey,
            $nombreRol,
            $this->drafts->obtenerHistorial()
        );

        if ($respGemini === null) {
            return null;
        }

        if ($respGemini['tipo'] === 'function_call') {
            return $this->drafts->procesarFunctionCallGemini(
                $respGemini['name'],
                $respGemini['args'],
                null,
                $rolId,
                $nombreRol,
                $userId
            );
        }

        if ($respGemini['tipo'] === 'sql') {
            $sql = (string) $respGemini['sql'];
            if (!$this->esSqlSeguro($sql, $rolId)) {
                Log::warning('AiSqlAssistantService: SQL bloqueado por auditor de seguridad: ' . $sql);
                return null;
            }
            try {
                $filas = DB::select($sql);
                $datosArray = array_map(fn($row) => (array) $row, array_slice($filas, 0, 15));
                $textoPantalla = $this->text->formatearRespuestaAmigable($respGemini['explicacion'], $datosArray);
                return $this->text->respuestaConDatos($textoPantalla, $datosArray, 'gemini');
            } catch (\Throwable $e) {
                Log::warning('AiSqlAssistantService: Error ejecutando SQL de Gemini: ' . $e->getMessage());
                return null;
            }
        }

        return $this->text->respuestaInformativa($respGemini['texto'], 'gemini');
    }

    // ─── Vía B: Conocimiento del sistema + consultas analíticas locales ───

    private function verificarRestriccionRol(string $pregunta, int $rolId, string $nombreRol): ?array
    {
        $norm = $this->text->normalizarTexto($pregunta);
        if ($rolId === 2 && preg_match('/\b(rentabilidad|utilidad|ganancia|margen real|arqueo|cierre de caja|sesion de caja|estado de caja|balance.*pago|recaudado|bitacora|auditoria)\b/', $norm)) {
            return $this->text->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no podemos ver reportes de ganancias, saldos de caja ni auditoría.");
        }
        if ($rolId === 3 && preg_match('/\b(rentabilidad|utilidad|margen|costo.*compra|costo unitario|compras.*proveedor|compras.*distribuidor|cuentas por pagar|deuda.*proveedor|arqueo|cierre de caja|sesion de caja|bitacora|auditoria)\b/', $norm)) {
            return $this->text->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no tenemos acceso a costos de compra, ganancias ni arqueos de caja.");
        }
        if ($rolId === 4 && preg_match('/\b(rentabilidad|utilidad|margen|costo.*compra|costo unitario|compras.*distribuidor|bitacora|auditoria)\b/', $norm)) {
            return $this->text->respuestaRestriccion("Disculpa, con tu cuenta de {$nombreRol} no podemos ver costos de compra ni márgenes de ganancia.");
        }
        return null;
    }

    private function consultarViaMotorLocal(string $pregunta, string $norm, int $rolId, string $nombreRol): array
    {
        try {
            $respSistema = $this->intencionConocimientoSistema($norm, $nombreRol);
            if ($respSistema !== null) {
                return $respSistema;
            }

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
                return $this->intencionResumenNegocio();
            }
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
                return $this->intencionVentasYCaja($rolId);
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
            if (preg_match('/\b(compatible|compatibilidad|le hace|sirve para|toyota|nissan|suzuki|mitsubishi|hyundai|kia|mazda|honda|isuzu|corolla|hilux|vitara|sentra|frontier|tucson|sportage|l200|rav4|yaris|swift|rio|accent|crv|bt50|dmax)\b/', $norm)) {
                return $this->intencionCompatibilidadVehicular($norm);
            }

            return $this->intencionBuscarRepuestoSinFalsos($pregunta, $norm);
        } catch (\Throwable $e) {
            Log::error('AiSqlAssistantService (Motor Local): ' . $e->getMessage());
            return $this->text->respuestaInformativa('Perdón, tuve un problemita consultando ese dato. ¿Me lo repites con otras palabras?', 'local');
        }
    }

    private function intencionConocimientoSistema(string $norm, string $nombreRol): ?array
    {
        if (preg_match('/\b(como cierro|cerrar la caja|como hago el arqueo|como se cierra la caja)\b/', $norm)) {
            return $this->text->respuestaInformativa('Para cerrar la caja ve al menú "Tesorería y Caja" (/caja). Ahí verás tu turno abierto y un calculador de billetes y monedas: anotas el efectivo físico contado en gaveta, el sistema compara automáticamente con lo esperado y al confirmar cierra el turno y te deja imprimir o descargar el Acta de Arqueo en PDF y Excel.');
        }
        if (preg_match('/\b(como abro|abrir la caja|como se abre la caja|iniciar turno)\b/', $norm)) {
            return $this->text->respuestaInformativa('Para abrir la caja entra al menú "Tesorería y Caja" (/caja), escribe el monto de base inicial con el que arrancas en gaveta (por ejemplo Bs. 500) y pulsa "Aperturar Sesión de Caja". Con la caja abierta ya puedes cobrar ventas en el POS o conmigo desde aquí.');
        }
        if (preg_match('/\b(como exporto|descargar pdf|exportar excel|imprimir reporte|sacar pdf)\b/', $norm)) {
            return $this->text->respuestaInformativa('En todos los listados y reportes del sistema verás dos botones en la esquina superior derecha: "Exportar PDF" (rojo) y "Exportar Excel" (verde). Respetan los filtros que tengas aplicados. Y en ventas, cotizaciones, compras y arqueos también puedes imprimir el comprobante o bajar su PDF.');
        }
        if (preg_match('/\b(como convierto|convertir cotizacion|pasar proforma a venta)\b/', $norm)) {
            return $this->text->respuestaInformativa('Entra a "Cotizaciones" (/cotizaciones), busca la proforma vigente y haz clic en "Convertir en Venta" (o ábrela y pulsa el botón verde). Eliges la forma de pago con caja abierta y al instante se genera la Nota de Venta y su Ticket 80mm.');
        }
        if (preg_match('/\b(como pago|liquidar deuda|pagar a proveedor|cuentas por pagar como)\b/', $norm)) {
            return $this->text->respuestaInformativa('Ve a "Cuentas por Pagar" (/compras/cuentas/por-pagar). Allí aparecen todas las facturas a crédito pendientes; al pulsar "Liquidar Pago" la factura pasa a Pagada y queda registrada en auditoría.');
        }
        if (preg_match('/\b(que puede hacer|que puedes hacer|que hace este sistema|como funciona el sistema|que modulos tiene|para que sirve|ayudame|ayuda)\b/', $norm)) {
            return $this->text->respuestaInformativa("Estás en Multipartes El Tuerca (tu perfil actual es {$nombreRol}). El sistema maneja todo tu negocio: catálogo de repuestos con OEM y compatibilidad vehicular, alertas de stock bajo y mermas, compras a proveedores y cuentas por pagar, clientes y talleres, cotizaciones, punto de venta con ticket 80mm/PDF, arqueo de caja y reportes con gráficos (rotación, rentabilidad, compras y medios de pago) exportables a PDF y Excel.\nConmigo puedes consultar cualquier dato o armar paso a paso ventas, cotizaciones, compras (incluso creando productos nuevos), ajustes y clientes.");
        }
        return null;
    }

    private function intencionRoles(): array
    {
        $roles = DB::table('roles as r')
            ->leftJoin('usuarios as u', 'u.id_rol', '=', 'r.id_rol')
            ->select(['r.nombre_rol as Rol', 'r.descripcion as Descripcion', DB::raw('COUNT(u.id_usuario) as "Usuarios"')])
            ->groupBy('r.id_rol', 'r.nombre_rol', 'r.descripcion')
            ->orderBy('r.id_rol')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $nombres = implode(', ', array_column($roles, 'Rol'));
        $texto = 'En el sistema tenemos ' . count($roles) . " roles configurados: {$nombres}. Cada uno tiene acceso solo a los módulos que le corresponden (el Administrador ve todo; Bodeguero maneja inventario y compras; Vendedor clientes, proformas y ventas; Cajero caja, cobros y pagos; y Cliente Web su portal de compras).";
        return $this->text->respuestaConDatos($texto, $roles);
    }

    private function intencionUsuarios(int $rolId, string $nombreRol): array
    {
        if ($rolId !== 1) {
            return $this->text->respuestaRestriccion("Disculpa, el listado completo de usuarios del sistema solo puede consultarlo el Administrador (tu perfil actual es {$nombreRol}).");
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
        return $this->text->respuestaConDatos('Actualmente hay ' . count($usuarios) . " usuarios registrados en la plataforma ({$activos} activos). Aquí tienes el detalle por rol:", $usuarios);
    }

    private function intencionCategorias(): array
    {
        $cats = DB::table('categorias as c')
            ->leftJoin('productos as p', 'p.id_categoria', '=', 'c.id_categoria')
            ->select(['c.nombre as Categoria', 'c.descripcion as Descripcion', DB::raw('COUNT(p.id_producto) as "Repuestos"')])
            ->groupBy('c.id_categoria', 'c.nombre', 'c.descripcion')
            ->orderByDesc(DB::raw('COUNT(p.id_producto)'))
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        return $this->text->respuestaConDatos('Tienes ' . count($cats) . ' categorías de repuestos registradas en el catálogo:', $cats);
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

        return $this->text->respuestaConDatos('Tienes ' . count($provs) . ' proveedores/distribuidores registrados en el sistema:', $provs);
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

        return $this->text->respuestaConDatos('En el catálogo vehicular tenemos ' . count($vehs) . ' modelos registrados (entre marcas japonesas y coreanas):', $vehs);
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
        return $this->text->respuestaConDatos($texto, $ajustes);
    }

    private function intencionAuditoria(int $rolId, string $nombreRol): array
    {
        if ($rolId !== 1) {
            return $this->text->respuestaRestriccion("Disculpa, la bitácora de auditoría solo está habilitada para el Administrador (tu perfil es {$nombreRol}).");
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

        return $this->text->respuestaConDatos('Estos son los últimos ' . count($logs) . ' movimientos registrados en la bitácora:', $logs);
    }

    private function intencionResumenNegocio(): array
    {
        $prods = (int) DB::table('productos')->where('estado', true)->count();
        $stockTotal = (int) DB::table('productos')->where('estado', true)->sum('stock_actual');
        $clientes = (int) DB::table('clientes')->count();
        $proveedores = (int) DB::table('proveedores')->where('estado', true)->count();
        $ventasHoy = (int) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', date('Y-m-d'))->count();
        $montoHoy = (float) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', date('Y-m-d'))->sum('total_venta');

        return $this->text->respuestaInformativa("Así está el negocio hoy: tenemos {$prods} repuestos activos ({$stockTotal} unidades en almacén), {$clientes} clientes registrados y {$proveedores} proveedores activos. Hoy llevamos {$ventasHoy} venta(s) por Bs. " . number_format($montoHoy, 2) . '.');
    }

    private function intencionBuscarRepuestoSinFalsos(string $preguntaOriginal, string $norm): array
    {
        $terminos = $this->text->extraerTerminosSignificativos($norm);
        if (empty($terminos)) {
            return $this->text->respuestaAclaracion(
                'Perdón, no te entendí bien esa parte. ¿Me lo puedes repetir con otras palabras? Puedo ayudarte con consultas del negocio (stock, ventas, roles, proveedores, caja) o armar contigo una venta, cotización, compra, ajuste o cliente nuevo.'
            );
        }

        $busq = $this->text->buscarCandidatosProducto($preguntaOriginal);
        if ($busq['estado'] === 'ninguno') {
            return $this->text->respuestaAclaracion(
                'Perdón, no te entendí bien esa parte o no encontré datos con esas palabras. ¿Me lo puedes repetir indicando qué necesitas revisar (por ejemplo un repuesto, vehículo, ventas, caja, proveedores o registrar una operación)?'
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
        return $this->text->respuestaConDatos($texto, $resumen);
    }

    private function intencionRotacionRepuestos(string $norm): array
    {
        $filas = collect(DB::select('SELECT * FROM fn_reporte_rotacion_repuestos(?, ?, NULL)', [Carbon::now()->subDays(90)->toDateString(), Carbon::now()->toDateString()]));
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
        return $this->text->respuestaConDatos($texto, $resumen);
    }

    private function intencionRentabilidad(): array
    {
        $filas = collect(DB::select('SELECT * FROM fn_reporte_rentabilidad(?, ?, NULL)', [Carbon::now()->subDays(90)->toDateString(), Carbon::now()->toDateString()]));
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
        return $this->text->respuestaConDatos($texto, $resumen);
    }

    private function intencionComprasDistribuidor(): array
    {
        $filas = collect(DB::select('SELECT * FROM fn_reporte_compras_distribuidor(?, ?, NULL)', [Carbon::now()->subDays(90)->toDateString(), Carbon::now()->toDateString()]));
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
        return $this->text->respuestaConDatos($texto, $resumen);
    }

    private function intencionBalanceMediosPago(): array
    {
        $filas = collect(DB::select('SELECT * FROM fn_reporte_balance_medios_pago(?, ?, NULL, NULL)', [Carbon::now()->subDays(90)->toDateString(), Carbon::now()->toDateString()]));
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
        return $this->text->respuestaConDatos($texto, $resumen);
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
        return $this->text->respuestaConDatos($texto, $items);
    }

    private function intencionCuentasPorPagar(): array
    {
        $deudas = DB::table('notas_compras as nc')
            ->join('proveedores as pr', 'pr.id_proveedor', '=', 'nc.id_proveedor')
            ->where('nc.tipo_pago', 'Crédito')
            ->where('nc.estado_pago', 'Pendiente')
            ->select(['nc.numero_factura_nota as Factura', 'pr.razon_social as Proveedor', 'nc.fecha_compra as Fecha', 'nc.monto_total as Saldo_Bs'])
            ->orderBy('nc.fecha_compra')
            ->limit(12)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $suma = array_sum(array_column($deudas, 'Saldo_Bs'));
        $texto = empty($deudas)
            ? '¡Excelente! No tienes ninguna cuenta pendiente por pagar a proveedores.'
            : 'Tienes ' . count($deudas) . ' facturas pendientes a crédito por un total de Bs. ' . number_format($suma, 2) . '.';
        return $this->text->respuestaConDatos($texto, $deudas);
    }

    private function intencionVentasYCaja(int $rolId): array
    {
        $hoy = date('Y-m-d');
        $cantHoy = (int) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', $hoy)->count();
        $totalHoy = (float) DB::table('notas_ventas')->where('estado', 'Completada')->whereDate('fecha_hora', $hoy)->sum('total_venta');
        $cantGlobal = (int) DB::table('notas_ventas')->where('estado', 'Completada')->count();
        $totalGlobal = (float) DB::table('notas_ventas')->where('estado', 'Completada')->sum('total_venta');

        $desglose = DB::table('notas_ventas')
            ->where('estado', 'Completada')
            ->when($cantHoy > 0, fn($q) => $q->whereDate('fecha_hora', $hoy))
            ->select(['metodo_pago as Forma_Pago', DB::raw('COUNT(id_venta) as "Ventas"'), DB::raw('ROUND(SUM(total_venta), 2) as "Total_Bs"')])
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

        return $this->text->respuestaConDatos($texto, $desglose);
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
        return $this->text->respuestaConDatos('Encontré ' . count($filas) . ' cotizaciones registradas:', $filas);
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
        return $this->text->respuestaConDatos('Aquí tienes ' . count($clientes) . ' clientes registrados en tu cartera:', $clientes);
    }

    private function intencionCompatibilidadVehicular(string $norm): array
    {
        $palabrasClave = $this->text->extraerTerminosSignificativos($norm);
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
                        $raiz = $this->text->singularizar($pz);
                        $q->orWhere('p.nombre_pieza', 'ilike', "%{$raiz}%")->orWhere('p.codigo_oem', 'ilike', "%{$raiz}%");
                    }
                });
            }
        }

        $res = $query->orderByDesc('p.stock_actual')->limit(10)->get()->map(fn($r) => (array) $r)->toArray();
        $texto = empty($res)
            ? 'No encontré repuestos compatibles con esa combinación exacta. ¿Probamos solo con la marca o modelo del vehículo?'
            : 'Encontré ' . count($res) . " repuestos compatibles. Por ejemplo: {$res[0]['Repuesto']} para {$res[0]['Vehiculo']} (stock: {$res[0]['Stock']}, Bs. " . number_format((float) $res[0]['Precio_Bs'], 2) . ').';
        return $this->text->respuestaConDatos($texto, $res);
    }
}
