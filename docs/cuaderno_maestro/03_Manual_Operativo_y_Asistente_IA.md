# 03. Manual Operativo por Rol y Guía Técnica del Asistente IA (CU39)

---

## 1. Flujo Operativo Paso a Paso por Rol

El sistema segmenta la operación en **5 roles oficiales** definidos en la tabla `roles` y verificados tanto en el middleware Laravel (`role:...`) como dentro de PostgreSQL (`fn_validar_usuario_autorizado`).

### 1.1. Administrador (`id_rol = 1`)
- **Alcance:** Acceso total al sistema sin restricciones.
- **Flujo operativo clave:**
  1. **Seguridad y Auditoría:** Crea/edita usuarios (`/usuarios`), configura roles (`/roles`) y supervisa trazas inmutables en `/auditoria` (logins fallidos, anulaciones, cambios de precio, descuentos autorizados, cierres de caja).
  2. **Supervisión Financiera:** Revisa `/reportes/rentabilidad` (exclusivo de este rol) para controlar utilidad bruta real y detectar productos con bajo margen.
  3. **Respaldo Gerencial:** Puede operar POS, abrir/cerrar cajas, aprobar compras, liquidar créditos a proveedores y utilizar las 5 operaciones transaccionales del Asistente IA.

### 1.2. Bodeguero (`id_rol = 2`)
- **Alcance:** Gestión física de inventario, catálogo técnico, proveedores, compras y reportes de almacén.
- **Flujo operativo clave:**
  1. **Catálogo Técnico:** Registra repuestos (`/productos/crear`), códigos alternativos (`/codigos-alternativos`), vehículos (`/vehiculos`) y compatibilidades (`/compatibilidades`), manteniendo ubicación exacta (`pasillo`, `estante`, `nivel_gaveta`).
  2. **Recepción de Mercadería (`/compras/recepcion`):** Selecciona proveedor activo, factura/nota, tipo de pago (`Contado` o `Crédito`), ítems y costos unitarios; al guardar se ejecuta `sp_registrar_compra` y el trigger incrementa automáticamente `stock_actual`.
  3. **Control de Stock:** Revisa alertas bajo mínimo (`/inventario/alertas`), registra mermas, extravíos, devoluciones dañadas, uso interno o conteos físicos (`/inventario/ajustes` -> `sp_registrar_ajuste_inventario`) y consulta `/reportes/rotacion` y `/reportes/compras-distribuidor`.

### 1.3. Vendedor (`id_rol = 3`)
- **Alcance:** Atención al cliente en mostrador, cotizaciones/proformas y ventas en POS (sin acceso a costos de compra, rentabilidad ni arqueo de caja).
- **Flujo operativo clave:**
  1. **Cartera de Clientes (`/clientes`):** Registra clientes particulares, talleres mecánicos o flotas asignando porcentaje de `descuento_frecuente` (0%–50%).
  2. **Cotizaciones (`/cotizaciones/crear`):** Arma presupuestos formales con vigencia controlada (`sp_registrar_cotizacion`) exportables a PDF/Excel; si el cliente confirma dentro del plazo, las convierte en venta directa con un clic (`sp_convertir_cotizacion_a_venta`).
  3. **Terminal POS (`/ventas/pos`):** Verifica disponibilidad y compatibilidad OEM, arma el carrito, aplica descuento dentro del margen seguro, registra la venta con caja abierta y emite Ticket Térmico 80mm o comprobante PDF.

### 1.4. Cajero (`id_rol = 4`)
- **Alcance:** Tesorería, control de turnos de caja, cobro multicanal, liquidación de cuentas por pagar y balance de medios de pago.
- **Flujo operativo clave:**
  1. **Apertura de Turno (`/caja`):** Declara el fondo inicial en gaveta (`sp_abrir_caja`). El trigger `trg_validar_sesion_unica_cajero` impide abrir más de una sesión simultánea por usuario.
  2. **Operación Diaria:** Registra ingresos extra o retiros menores justificados (`sp_registrar_movimiento_caja`), cobra ventas POS multicanal (`Efectivo`, `Transferencia/QR`, `Tarjeta Débito/Crédito`, `Mixto`) y liquida facturas a crédito de proveedores en `/compras/cuentas/por-pagar`.
  3. **Cierre y Arqueo (`sp_cerrar_caja`):** Cuenta billetes y monedas en el calculador de `/caja`, compara con `monto_esperado_sistema`, sella el estado (`Cuadrada` o `Con Diferencia`) y exporta el Acta de Arqueo en PDF o Excel.

### 1.5. Cliente Web (`id_rol = 5`)
- **Alcance:** Vitrina comercial pública (`/`) y portal personal (`/mis-cotizaciones`, `/mi-comprobante/{id}`).
- **Flujo operativo clave:**
  1. **Exploración y Validación:** Busca por código OEM, nombre o vehículo en el autocompletado del Hero o en el catálogo facetado, y verifica compatibilidades técnicas en el modal.
  2. **Carrito Lateral (`cart_drawer`):** Agrega repuestos en stock y elige entre:
     - **Compra Directa (`POST /mi-compra`):** Si hay caja abierta en tienda ejecuta `sp_registrar_venta` y lo redirige a `/mi-comprobante/{id}` para descargar su PDF; si la caja física está cerrada resguarda el pedido como proforma (`sp_registrar_cotizacion`) para que no pierda su reserva.
     - **Solicitar Proforma (`POST /mis-cotizaciones`):** Emite proforma formal por 7 o 15 días lista para descargar en PDF.

---

## 2. Arquitectura Técnica del Asistente Conversacional IA (CU39)

El widget flotante integrado en `resources/views/layouts/app.blade.php` soporta texto y dictado/lectura por voz (`SpeechRecognition` + `speechSynthesis`), conectado a `AsistenteIaController` (`POST /asistente/consultar`, `POST /asistente-ia/confirmar`, `POST /asistente/limpiar`) y orquestado por `AiSqlAssistantService`.

### 2.1. Pipeline de Procesamiento en 2 Vías
1. **Preprocesamiento de Voz (`AiTextNormalizer`):**
   - `limpiarMuletillasVoz()` remueve rellenos de dictado (*"ehhh"*, *"por favor"*, *"a ver"*, *"digamos"*, *"hermanito"*).
   - `normalizarFonetica()` corrige errores frecuentes de reconocimiento de voz (*pastiyas -> pastilla*, *coroya -> corolla*, *jailux -> hilux*, *nisan -> nissan*, *susuki -> suzuki*, *bosh -> bosch*, *bitara -> vitara*).
2. **Evaluación de Contexto y Borrador Activo (`AiDraftManager`):**
   - Verifica comandos de cancelación/reinicio y frases vacías/ruido.
   - Si no hay borrador activo pero el usuario acaba de pulsar **Cancelar** en una tarjeta (dentro de los últimos **5 minutos** en `ai_last_cancelled_draft`), detecta ediciones rápidas (*"mejor por QR"*, *"cambia la cantidad a 3"*, *"ponle 10% de descuento"*), recupera el borrador, recalcula subtotales/descuentos porcentuales y vuelve a mostrar la tarjeta actualizada.
   - Si existe un borrador activo en `ai_tx_draft` (TTL 15 min), absorbe la respuesta dentro de la máquina de estados.
3. **Vía A — Motor Google Gemini (`AiGeminiClient`):**
   - Envía el `systemInstruction` con el mapa de módulos, matriz de permisos y las 19 tablas, más el historial multi-turno (`ai_chat_history`, hasta 8 turnos) y las 5 `functionDeclarations`.
   - Intenta primero `gemini-2.5-flash` y conmuta a `gemini-2.0-flash` si hay error HTTP o timeout (`6.5s`).
   - Si Gemini retorna `functionCall`, pasa obligatoriamente por `AiDraftManager::procesarFunctionCallGemini()` (nunca ejecuta escrituras directas).
   - Si Gemini retorna `{"sql": "SELECT ..."}`, `AiSqlAssistantService::esSqlSeguro()` valida lectura pura (`SELECT`/`WITH` sin `;`, `--`, `/*`, mutaciones, `password_hash` ni tablas restringidas para el `id_rol` actual) antes de consultar PostgreSQL.
4. **Vía B — Respaldo Local Inteligente:**
   - Si Gemini no está disponible o no aplica, el motor local atiende guías operativas (*"¿cómo cierro la caja?"*, *"¿qué puede hacer este sistema?"*), consultas reales a las tablas maestras (`roles`, `usuarios`, `categorias`, `proveedores`, `vehiculos`, `ajustes_inventario`, `bitacoras_auditorias`, reportes `fn_reporte_*`) sin falsos positivos ante texto sin sentido, e inicia borradores transaccionales con la misma máquina de estados.

### 2.2. Máquina de Estados por Campo (`pendiente | completado | omitido_por_usuario`)
Cada operación mantiene un diccionario `campos[nombre] = ['estado' => ..., 'valor' => ...]`:
- **Extracción simultánea:** Una frase como *"véndele 2 pastiyas de freno con 10% de descuento"* rellena a la vez `items` (tras desambiguación si hay coincidencias) y `descuento_pct_pend`.
- **Respeto a *"no sé / no me acuerdo / normal"*:** Si el campo es **opcional** (`cliente`, `descuento`, `metodo_pago`, `pago_detalle`, `dias_vigencia`, `numero_factura_nota`, `tipo_pago`, `motivo`, `tipo_cliente`, `ci_nit`, `telefono`, `descuento_frecuente`), cambia a `omitido_por_usuario`, no vuelve a insistir y aplica su valor por defecto controlado (ej. `Cliente Ocasional Mostrador`, `Efectivo`, `Contado`, `7 días`, `S/F-YYYYMMDD-HHMM`).
- **Blindaje de mínimos obligatorios:** Si el usuario dice *"no sé"* ante un campo obligatorio (`items`/`producto`, `proveedor` en compra, `costo_unitario > 0` en compra, `tipo_ajuste`/`cantidad` en ajuste, `nombre_razon_social >= 3` en cliente), el sistema rechaza omitirlo y explica con tono cercano qué dato mínimo necesita antes de armar la tarjeta.

### 2.3. Desambiguación Difusa y Alta de Producto Nuevo en Compras
- **Desambiguación:** Cuando la búsqueda difusa (`levenshtein` + `similar_text`) arroja múltiples candidatos o solo coincidencias aproximadas, lista hasta 5 opciones numeradas con marca, vehículos compatibles, precio en `Bs.` y stock actual, aceptando *"el 1"*, *"la primera"* o *"ninguno"*.
- **Subflujo de Producto Nuevo en `preparar_compra`:** Si el repuesto comprado no existe en `productos`, el asistente pregunta si desea darlo de alta ahora mismo. Si confirma, solicita paso a paso: nombre exacto, categoría numerada, código OEM/interno (genera `REP-XXXX` único si dice *"no sé"*), costo de compra (si falta), precio de venta sugerido (calcula automático `costo * 1.30` si dice *"no sé"*) y pasillo/estante opcional. Inserta el producto con `stock_actual = 0`, registra en `bitacoras_auditorias`, lo añade al carrito de compra y continúa automáticamente solicitando factura y forma de pago hasta emitir la tarjeta de confirmación.

### 2.4. Confirmación Humana en 2 Pasos (`action_token`)
Ninguna mutación de base de datos ocurre en `/asistente/consultar`. Al completarse un borrador:
1. `AiTransactionExecutor` almacena el payload en sesión bajo `ai_pending_action.{uuid}` (expiración 10 minutos) junto con `draft_snapshot`, y retorna una **Tarjeta de Confirmación** en el chat.
2. El usuario pulsa **Confirmar y Ejecutar** (`POST /asistente-ia/confirmar`) para disparar el procedimiento almacenado (`sp_registrar_*`) o **Cancelar** (`cancelar=true`) para guardar el borrador en `ai_last_cancelled_draft` por 5 minutos.
3. Toda respuesta visible y hablada pasa por `limpiarTecnicismosDeRespuesta()` y `traducirErrorAmigable()` para garantizar cero jerga técnica al dueño del negocio.
