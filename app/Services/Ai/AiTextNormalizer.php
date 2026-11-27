<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Normalización de voz/texto, limpieza de muletillas, búsqueda difusa de entidades
 * (repuestos, clientes y proveedores) y formateo amigable de respuestas (CU39).
 */
class AiTextNormalizer
{
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

    public function normalizarTexto(string $texto): string
    {
        $min = mb_strtolower($texto, 'UTF-8');
        return strtr($min, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        ]);
    }

    public function normalizarFonetica(string $norm): string
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

    public function prepararTextoParaVoz(string $texto): string
    {
        $limpio = preg_replace('/[\*\#\_`~>]/', '', $texto);
        $limpio = preg_replace('/\bBs\.?\s*/i', 'bolivianos ', (string) $limpio);
        $limpio = preg_replace('/\b(?:c[óo]digo\s+)?OEM\b/iu', 'código O E M', (string) $limpio);
        $limpio = preg_replace('/\bSKU\b/i', 'código interno', (string) $limpio);
        $limpio = preg_replace('/\n+/', '. ', (string) $limpio);
        return trim((string) preg_replace('/\s+/', ' ', (string) $limpio));
    }

    public function limpiarTecnicismosDeRespuesta(string $texto): string
    {
        $limpio = preg_replace(
            '/\b(SQLSTATE|id_producto|id_cliente|id_venta|id_sesion|constraint|jsonb|JSON|array|PostgreSQL|Smart-DB|sp_registrar_\w+|fn_reporte_\w+)\b/i',
            '',
            $texto
        );
        return trim((string) preg_replace('/\s{2,}/', ' ', (string) $limpio));
    }

    public function extraerJsonDeRespuesta(string $raw): ?array
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

    public function formatearRespuestaAmigable(string $explicacion, array $datos): string
    {
        $explicacion = trim($this->limpiarTecnicismosDeRespuesta($explicacion));
        $total = count($datos);
        if ($total === 0) {
            return $explicacion . ' No encontré registros con esos datos.';
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

        if ($total === 1) {
            if (count($primeraFila) === 1) {
                $valUnico = reset($primeraFila);
                if ($valUnico !== null && $valUnico !== '') {
                    if ($explicacion !== '' && str_contains($explicacion, (string) $valUnico)) {
                        return $explicacion;
                    }
                    $colUnico = (string) array_key_first($primeraFila);
                    $valFmt = (is_numeric($valUnico) && preg_match('/(monto|precio|costo|venta|total_bs|utilidad|ingreso|saldo|deuda)/i', $colUnico))
                        ? 'Bs. ' . number_format((float) $valUnico, 2)
                        : (string) $valUnico;
                    return ($explicacion !== '' ? rtrim($explicacion, '. :') . ': ' : 'Resultado: ') . "{$valFmt}.";
                }
            }
            return ($explicacion !== '' ? rtrim($explicacion, '. ') . '. ' : '') . implode(', ', array_slice($pares, 0, 5)) . '.';
        }

        return $explicacion . " (Encontré {$total} registros; por ejemplo: " . implode(', ', array_slice($pares, 0, 5)) . '.)';
    }

    // ─── Detectores de intención, desconocimiento, ordinals y montos ───

    public function detectarOperacionTransaccional(string $norm): ?string
    {
        $esPreguntaConsulta = (bool) preg_match('/\b(cuanto|cuantos|cuantas|cantidad de|numero de|total de|lista de|listar|ver|mostrar|historial|reporte|resumen|cuales son|quienes son|como convierto|convertir)\b/', $norm);

        if (preg_match('/\b(registra(?:r)?|agrega(?:r)?|crea(?:r)?|anota(?:r)?|nuevo)\s+(?:un\s+|una\s+)?(?:nuevo\s+)?(?:cliente|taller|empresa)\b/', $norm)
            && !preg_match('/\b(venta|cotizacion|proforma|compra)\b/', $norm)
            && !$esPreguntaConsulta
        ) {
            return 'preparar_cliente';
        }
        if (preg_match('/\b(merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|da de baja|dar de baja|se rompio|se dano|ajuste de inventario|ajustar stock|registrar ajuste)\b/', $norm)
            && !$esPreguntaConsulta
        ) {
            return 'preparar_ajuste_inventario';
        }
        if (!$esPreguntaConsulta && (
            preg_match('/\b(registra(?:r)?|ingresa(?:r)?|recepciona(?:r)?|anota(?:r)?|quiero\s+hacer|hacer|acabo\s+de)\b.*\b(compra(?:r)?|mercaderia|factura de compra)\b/', $norm)
            || preg_match('/^\s*(?:nueva\s+)?compra\s+(?:a|de)\b/', $norm)
            || preg_match('/^\s*(?:compre|acabo de comprar)\b/', $norm)
        )) {
            return 'preparar_compra';
        }
        if (!$esPreguntaConsulta && preg_match('/\b(cotiza(?:r|me)?|cotice|acabo de cotizar|cotizacion|proforma)\b/', $norm)) {
            return 'preparar_cotizacion';
        }
        if (!$esPreguntaConsulta && preg_match('/\b(vende(?:r|me|le)?|vendeme|vendele|vendi|vendimos|acabo de vender|acabo de cobrar|despache|facturar|cobrar|registra(?:r)?\s+(?:una\s+)?venta|prepara(?:r)?\s+(?:una\s+)?venta|hacer\s+(?:una\s+)?venta|nueva\s+venta|anota(?:r)?\s+(?:una\s+)?venta)\b/', $norm)) {
            return 'preparar_venta';
        }
        return null;
    }

    public function indicaCierreProductos(string $norm): bool
    {
        return (bool) preg_match('/\b(y nada mas|nada mas|solo eso|solamente eso|eso es todo|es todo|sin mas productos|ya no mas)\b/u', $norm);
    }

    /**
     * Extrae descuento porcentual, en Bs. o explícitamente sin descuento desde texto libre.
     */
    public function extraerDescuentoDeTexto(string $norm): ?array
    {
        if (preg_match('/\b(sin descuento|sin rebaja|precio normal|cero descuento)\b/u', $norm)) {
            return ['tipo' => 'sin', 'valor' => 0.0];
        }

        if (preg_match('/(?:con\s+)?(?:un\s+)?(?:descuento|rebaja)\s+(?:de(?:l)?\s+)?(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento\b)/u', $norm, $m)
            || preg_match('/(?:con\s+)?(?:un\s+)?(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento\b)\s*(?:de\s+)?(?:descuento|rebaja)\b/u', $norm, $m)
            || preg_match('/\bcon\s+(?:el\s+|un\s+)?(\d+(?:[\.,]\d+)?)\s*(%|por\s*ciento\b)/u', $norm, $m)
        ) {
            return ['tipo' => 'pct', 'valor' => min(50.0, max(0.0, (float) str_replace(',', '.', $m[1])))];
        }

        if (preg_match('/(?:descuento|rebaja|descuentale)\s+(?:de(?:l)?\s+)?(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)?\b/u', $norm, $mBs)
            || preg_match('/\bmenos\s+(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)\b/u', $norm, $mBs)
        ) {
            return ['tipo' => 'bs', 'valor' => max(0.0, (float) str_replace(',', '.', $mBs[1]))];
        }

        return null;
    }

    /**
     * Extrae detalle de pago (monto entregado, referencia o pago exacto) si aparece en la frase.
     */
    public function extraerPagoDetalleDeTexto(string $preguntaLimpia, string $norm, ?string $metodo): ?array
    {
        if (preg_match('/\b(exacto|cabal|justo|sin vuelto)\b/u', $norm)) {
            return ['omitido' => true];
        }

        if (($metodo === null || $metodo === 'Efectivo')
            && preg_match('/\b(?:me\s+pago\s+con|pago\s+con|con\s+un\s+billete\s+de|billete\s+de|recibi)\s*(\d+(?:[\.,]\d+)?)\s*(?:bs|bolivianos)?\b/u', $norm, $mEf)
        ) {
            $monto = round((float) str_replace(',', '.', $mEf[1]), 2);
            if ($monto > 0) {
                return ['tipo' => 'efectivo', 'data' => ['monto_recibido' => $monto]];
            }
        }

        if (in_array($metodo, ['Transferencia/QR', 'Tarjeta Débito/Crédito'], true)
            && preg_match('/\b(?:ref(?:erencia)?|comprobante|nro|numero|operacion)\s*[:#]?\s*([a-z0-9\-]{2,30})\b/i', $preguntaLimpia, $mRef)
        ) {
            return ['tipo' => 'referencia', 'data' => ['nro_referencia' => strtoupper(trim($mRef[1]))]];
        }

        return null;
    }

    /**
     * Extrae el nombre del cliente o taller desde frases como "a Armando Loza", "para Carlos", "al taller El Piston".
     */
    public function extraerNombreClienteDeFrase(string $preguntaLimpia): ?string
    {
        $limpia = (string) preg_replace(
            '/\b(?:con\s+(?:un\s+)?descuento\b.*|sin\s+descuento\b.*|descuento\s+(?:de(?:l)?\s+)?\d+.*|en\s+efectivo\b.*|por\s+qr\b.*|por\s+transferencia\b.*|con\s+tarjeta\b.*|pago\s+mixto\b.*|y\s+nada\s+m[aá]s\b.*|nada\s+m[aá]s\b.*|solo\s+eso\b.*|eso\s+es\s+todo\b.*|me\s+pag[oó]\b.*)$/iu',
            '',
            $preguntaLimpia
        );
        $limpia = trim($limpia, " \t\n\r\0\x0B,.;:");
        if ($limpia === '') {
            return null;
        }

        $wordStop = '(?!(?:a|para|con|en|por|sin|y|de|del|la|el|los|las|descuento|rebaja|efectivo|qr|transferencia|tarjeta|mixto|contado|credito|bolivianos|bs|nada|solo|eso|todo|me|pago)\b)';
        $tokenRe = $wordStop . '[A-Za-zÁÉÍÓÚÑáéíóúñ][A-Za-zÁÉÍÓÚÑáéíóúñ0-9\.\'\-]{1,28}';

        $patrones = [
            '/\b(?:a\s+nombre\s+de|al\s+cliente|para\s+el\s+cliente|para\s+cliente|al\s+taller|para\s+el\s+taller|para\s+taller|al\s+mec[aá]nico|para\s+el\s+mec[aá]nico|a\s+la\s+empresa|para\s+la\s+empresa)\s+(' . $tokenRe . '(?:\s+' . $tokenRe . '){0,3})/iu',
            '/\b(?:para|a)\s+(' . $tokenRe . '(?:\s+' . $tokenRe . '){0,3})/iu',
        ];

        $bloqueados = [
            'toyota', 'hilux', 'corolla', 'yaris', 'rav4', 'prado', 'suzuki', 'vitara', 'swift', 'jimny', 'alto',
            'nissan', 'frontier', 'np300', 'sentra', 'versa', 'qashqai', 'hyundai', 'tucson', 'santa', 'accent', 'elantra',
            'kia', 'sportage', 'rio', 'cerato', 'sorento', 'mitsubishi', 'l200', 'triton', 'montero', 'mazda', 'cx5', 'bt50',
            'vender', 'vende', 'vendeme', 'vendele', 'vendi', 'cotizar', 'cotiza', 'comprar', 'compra', 'cobrar', 'pagar', 'registrar',
            'freno', 'frenos', 'pastilla', 'pastillas', 'disco', 'discos', 'balata', 'balatas', 'zapata', 'zapatas',
            'amortiguador', 'amortiguadores', 'bujia', 'bujias', 'filtro', 'filtros', 'aceite', 'embrague', 'bobina', 'sensor',
            'efectivo', 'credito', 'contado', 'tarjeta', 'transferencia', 'mixto', 'normal', 'mostrador', 'ocasional',
            'cualquiera', 'mitad', 'precio', 'costo', 'unidades', 'juegos', 'pares', 'piezas', 'delantero', 'delantera',
            'trasero', 'trasera', 'favor', 'manana', 'hoy', 'ayer', 'todos', 'todas', 'paso', 'tiempo',
        ];

        foreach ($patrones as $idx => $patron) {
            if (!preg_match_all($patron, $limpia, $matches, PREG_SET_ORDER)) {
                continue;
            }
            $candidatos = $idx === 1 ? array_reverse($matches) : $matches;
            foreach ($candidatos as $m) {
                $cand = trim((string) ($m[1] ?? ''));
                if (mb_strlen($cand) < 3) {
                    continue;
                }
                $candNorm = $this->normalizarTexto($cand);
                if ($this->indicaClientePorDefecto($candNorm)) {
                    continue;
                }
                $palabras = preg_split('/\s+/', $candNorm) ?: [];
                if (empty($palabras)) {
                    continue;
                }
                $tieneBloqueado = false;
                foreach ($palabras as $pw) {
                    if (in_array($pw, $bloqueados, true)) {
                        $tieneBloqueado = true;
                        break;
                    }
                }
                if ($tieneBloqueado) {
                    continue;
                }
                return mb_convert_case($cand, MB_CASE_TITLE, 'UTF-8');
            }
        }

        return null;
    }

    public function esDesconocimientoONegativa(string $norm, string $campo): bool
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

    public function esRespuestaNinguno(string $norm): bool
    {
        return (bool) preg_match('/\b(ninguno|ninguna|no es ninguno|no es ninguna|ninguno de esos|ninguna de esas|otro repuesto)\b/u', $norm);
    }

    public function esModificacionDeBorradorCancelado(string $norm, array $cancelado): bool
    {
        return (bool) preg_match('/\b(ponle|ponelo|cambialo|cambia(?:r|le)?|mejor\s+(?:en|por|con|a|\d)|agrega(?:le)?\s+descuento|quita(?:le)?\s+(?:el\s+)?descuento|con\s+\d+(?:[\.,]\d+)?\s*%?\s*(?:de\s+)?descuento|sin\s+descuento|que\s+sea\s+(?:en|por|a)|a\s+nombre\s+de)\b/u', $norm);
    }

    public function esFraseVaciaORuido(string $norm): bool
    {
        $soloLetras = preg_replace('/[^a-z0-9ñ]/u', '', $norm);
        return mb_strlen((string) $soloLetras) < 1;
    }

    public function esComandoCancelacion(string $norm): bool
    {
        return (bool) preg_match('/^\s*(cancelar|cancela|olvidalo|olvida|dejalo nomas|deja nomas|ya no|no quiero|empezar de nuevo|nueva charla|reiniciar|borrar todo|desde cero|nada gracias)\s*$/u', $norm)
            || (bool) preg_match('/\b(cancela eso|olvidalo|empezar de nuevo|nueva charla|dejalo nomas)\b/u', $norm);
    }

    public function esSaludoOConversacionBasica(string $norm): bool
    {
        return (bool) preg_match('/^\s*(hola|buenos dias|buenas tardes|buenas noches|buenas|que tal|como estas|hey|gracias|muchas gracias|mil gracias|ok|listo|perfecto|genial)\s*$/u', $norm);
    }

    public function responderSaludoAmigable(string $norm, ?object $usuario): array
    {
        $nombre = $usuario ? Str::words((string) ($usuario->nombre_completo ?? ''), 1, '') : '';
        $saludo = $nombre !== '' ? ", {$nombre}" : '';
        if (preg_match('/\b(gracias|perfecto|listo|genial|ok)\b/', $norm)) {
            return $this->respuestaInformativa("¡A la orden{$saludo}! Aquí sigo para lo que necesites en el mostrador.", 'local');
        }
        return $this->respuestaInformativa("¡Hola{$saludo}! ¿Cómo estás? Dime qué necesitas hoy: ¿consultamos algo del negocio o registramos una venta, cotización, compra, ajuste o cliente?", 'local');
    }

    public function esConsultaGeneralOAnaliticaExplicita(string $norm): bool
    {
        return (bool) preg_match('/\b(que roles|que usuarios|que proveedores|que categorias|que puede hacer|que puedes hacer|como cierro|como abro|como exporto|stock critico|stock bajo|rotacion|hueso|rentabilidad|ganancias|margen|cuentas por pagar|balance.*pago|cuanto vendimos|cuantas ventas|como esta la caja|compatibles para|cuantos productos|cuantos repuestos|cantidad de productos|cuantos administradores|cuantos usuarios|cuantos clientes|cuantos proveedores|cuantos vehiculos|cuantas categorias|cuantas compras|cuantas cotizaciones)\b/', $norm);
    }

    public function esRespuestaAfirmativa(string $norm): bool
    {
        return (bool) preg_match('/\b(si|claro|dale|aplica|aplicaselo|por supuesto|esta bien|bueno|ok|hagale|metele|positivo|de acuerdo)\b/u', $norm);
    }

    public function esRespuestaNegativa(string $norm): bool
    {
        return (bool) preg_match('/\b(no|nop|sin descuento|dejalo sin|precio normal|no hace falta)\b/u', $norm);
    }

    public function indicaClientePorDefecto(string $norm): bool
    {
        return (bool) preg_match('/\b(no me acuerdo|cualquiera|ponelo normal|ponlo normal|sin nombre|mostrador|cliente ocasional|cliente general|consumidor final|ninguno en especial|de paso)\b/u', $norm);
    }

    public function detectarMetodoPagoExplicito(string $norm, bool $esperandoPago): ?string
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

    public function detectarTipoAjusteExplicito(string $norm): ?string
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

    public function normalizarMetodoPago(string $raw): string
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

    public function normalizarTipoAjuste(string $raw): string
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

    public function extraerIndiceOrdinal(string $norm): ?int
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

    public function extraerCantidadDeTexto(string $norm, bool $excluirOrdinalSeleccion): ?int
    {
        $texto = $norm;
        if ($excluirOrdinalSeleccion) {
            $texto = (string) preg_replace('/\b(?:la|el|opcion|numero|nro)\s*[1-8]\b/u', ' ', $texto);
            $texto = (string) preg_replace('/\b(primera|primero|segunda|segundo|tercera|tercero|cuarta|cuarto|quinta|quinto)\b/u', ' ', $texto);
        }

        $texto = (string) preg_replace('/\b(?:a|costo(?:\s+de)?|precio(?:\s+de)?)\s+\d+(?:[\.,]\d+)?\s*(?:bs\b|bolivianos\b)?/u', ' ', $texto);
        $texto = (string) preg_replace('/\b\d+(?:[\.,]\d+)?\s*(?:%|bs\b|bolivianos\b|por\s*ciento\b|dias?\b)/u', ' ', $texto);
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

    public function extraerCostoDeTexto(string $norm, bool $esRespuestaDirecta = false): ?float
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

    public function extraerFragmentoProductoLimpio(string $norm, string $operacion, ?string $clienteNombre = null): string
    {
        $sinCierre = preg_replace('/\b(y nada mas|nada mas|solo eso|solamente eso|eso es todo|es todo|sin mas productos)\b/u', ' ', $norm);
        $sinPrecio = preg_replace('/\b(?:a|por|de|costo(?:\s+de)?|me\s+pago\s+con|pago\s+con|billete\s+de)\s+\d+(?:[\.,]\d+)?\s*(?:%|bs\b|bolivianos\b)?/u', ' ', (string) $sinCierre);
        $sinDesc = preg_replace('/(?:con\s+)?(?:un\s+)?\d+(?:[\.,]\d+)?\s*(?:%|por\s*ciento)\s*(?:de\s+)?(?:descuento|rebaja)|(?:descuento|rebaja)\s+(?:de(?:l)?\s+)?\d+(?:[\.,]\d+)?\s*(?:%|bs|bolivianos)?|menos\s+\d+(?:[\.,]\d+)?\s*(?:bs|bolivianos)/u', ' ', (string) $sinPrecio);
        $sinFactura = preg_replace('/\bfactura\s+[a-z0-9\-]+/u', ' ', (string) $sinDesc);
        $sinPago = preg_replace('/\b(en efectivo|por qr|con qr|por transferencia|con tarjeta|pago mixto|al contado|a credito|por \d+ dias|sin descuento|precio normal)\b/u', ' ', (string) $sinFactura);

        if ($clienteNombre !== null && trim($clienteNombre) !== '') {
            $cliNorm = $this->normalizarTexto(trim($clienteNombre));
            $sinPago = preg_replace('/\b(?:a\s+nombre\s+de|al\s+cliente|para\s+el\s+cliente|al\s+taller|para\s+el\s+taller|para|a)?\s*' . preg_quote($cliNorm, '/') . '\b/u', ' ', (string) $sinPago);
        }

        if ($operacion === 'preparar_compra') {
            $provs = DB::table('proveedores')->where('estado', true)->pluck('razon_social');
            foreach ($provs as $razon) {
                $palabras = array_filter(explode(' ', $this->normalizarTexto($razon)), fn($w) => mb_strlen($w) >= 4);
                foreach ($palabras as $w) {
                    $sinPago = preg_replace('/\b' . preg_quote($w, '/') . '\b/u', ' ', (string) $sinPago);
                }
            }
        }

        $sinPago = preg_replace('/\b(?:a\s+un|al|para\s+un|para\s+el)?\s*(?:taller|cliente|mecanico)\s*(?:amigo|frecuente)?\b/u', ' ', (string) $sinPago);

        $sinRuido = preg_replace(
            '/\b(acabo|acaba|registra|registrar|prepara|preparar|vende|vender|vendeme|vendele|vendi|vendimos|despache|cotiza|cotizar|cotizame|cotice|compra|comprar|compre|ingresa|ingresar|recepciona|recepcionar|aplica|aplicar|reporta|reportar|anota|anotar|hacer|quiero|necesito|dame|ponme|poneme|nueva|nuevo|una|un|unas|unos|venta|cotizacion|proforma|merma|perdida|extravio|uso interno|autoconsumo|conteo fisico|devolucion danada|ajuste|de|del|la|el|los|las|por|para|al|con|en|a|que|se|rompio|dano|perdio|da|baja|unidades|unidad|uds|piezas|pzs|juegos|juego|pares|par|efectivo|qr|tarjeta|mixto|credito|contado|cliente|proveedor|factura|normal|viejo|bolivianos|bs|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|\d+)\b/u',
            ' ',
            (string) $sinPago
        );

        return trim((string) preg_replace('/\s+/', ' ', (string) $sinRuido));
    }

    public function formatearMotivoAjuste(string $tipoAjuste, string $motivoRaw, string $nombrePieza): string
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

    public function extraerTerminosProducto(string $norm): array
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

    public function extraerTerminosSignificativos(string $norm): array
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

    public function singularizar(string $palabra): string
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

    // ─── Búsqueda difusa de repuestos, clientes y proveedores ───

    public function buscarCandidatosProducto(string $texto): array
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

    public function construirMensajeDesambiguacion(array $opciones, bool $dudoso, string $origen): array
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

    public function resolverSeleccionDeOpciones(array $opciones, string $norm): ?array
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

    public function armarItemDesdeProducto(array $prod, int $cantidad, bool $esCompra, ?float $costoUnitario = null): array
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

    public function obtenerClienteOcasionalMostrador(): object
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

    public function formatearClienteArray(object $c): array
    {
        return [
            'id_cliente'          => (int) $c->id_cliente,
            'nombre_razon_social' => (string) $c->nombre_razon_social,
            'ci_nit'              => (string) ($c->ci_nit ?: 'S/N'),
            'tipo_cliente'        => (string) ($c->tipo_cliente ?? 'Particular'),
            'descuento_frecuente' => (float) ($c->descuento_frecuente ?? 0.0),
        ];
    }

    public function buscarClientePorTexto(string $texto): ?object
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

    public function detectarClienteRegistradoEnTexto(string $original, string $norm): ?object
    {
        $clientes = DB::table('clientes')->where('id_cliente', '<>', 1)->get();
        foreach ($clientes as $cl) {
            $nomNorm = $this->normalizarTexto($cl->nombre_razon_social);
            $palabras = array_values(array_filter(
                explode(' ', $nomNorm),
                fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['taller', 'mecanico', 'cliente', 'empresa', 'flota', 'general', 'mostrador', 'amigo'], true)
            ));
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

    public function resolverProveedor(?string $busqueda): ?object
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

    public function detectarProveedorEnTexto(string $original, string $norm): ?object
    {
        $todosProv = DB::table('proveedores')->where('estado', true)->get();
        foreach ($todosProv as $pr) {
            $palabras = array_filter(
                explode(' ', $this->normalizarTexto($pr->razon_social)),
                fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['importadora', 'distribuidora', 'repuestos', 'ltda', 'srl', 'bolivia'], true)
            );
            foreach ($palabras as $w) {
                if (str_contains($norm, $w)) {
                    return $pr;
                }
            }
        }
        return null;
    }

    // ─── Constructores estándar de respuesta ───

    public function respuestaRestriccion(string $msg): array
    {
        return ['ok' => true, 'tipo' => 'restriccion', 'origen' => 'local', 'texto_pantalla' => $msg, 'texto_voz' => $this->prepararTextoParaVoz($msg), 'datos_resumen' => []];
    }

    public function respuestaInformativa(string $msg, string $origen = 'local'): array
    {
        return ['ok' => true, 'tipo' => 'informacion', 'origen' => $origen, 'texto_pantalla' => $msg, 'texto_voz' => $this->prepararTextoParaVoz($msg), 'datos_resumen' => []];
    }

    public function respuestaAclaracion(string $msg, string $origen = 'local'): array
    {
        return ['ok' => true, 'tipo' => 'aclaracion', 'origen' => $origen, 'texto_pantalla' => $msg, 'texto_voz' => $this->prepararTextoParaVoz($msg), 'datos_resumen' => []];
    }

    public function respuestaConDatos(string $texto, array $datos, string $origen = 'local'): array
    {
        return ['ok' => true, 'tipo' => 'informacion', 'origen' => $origen, 'texto_pantalla' => $texto, 'texto_voz' => $this->prepararTextoParaVoz($texto), 'datos_resumen' => $datos];
    }

    public function etiquetaOperacionAmigable(string $op): string
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

    public function obtenerNombreRol(int $rolId): string
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
}
