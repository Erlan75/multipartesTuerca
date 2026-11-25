# 04. Guía de Defensa Técnica y Decisiones de Arquitectura UX

Documento de sustento arquitectónico, seguridad e ingeniería de interfaz para evaluación académica y técnica de **Multipartes El Tuerca**.

---

## 1. Arquitectura Smart-DB vs. ORM Puro

En sistemas comerciales tradicionales que delegan toda la lógica en el ORM de aplicación (Eloquent), dos cajeros vendiendo la última unidad al mismo tiempo pueden provocar stock negativo, o un controlador sin transacción bien delimitada puede descontar inventario sin actualizar la sesión de caja.

La arquitectura **Smart-DB** adoptada por el proyecto traslada las invariantes críticas al motor **PostgreSQL 16+**:
1. **Atomicidad y Concurrencia Real:** Procedimientos como `sp_registrar_venta` y `sp_registrar_ajuste_inventario` trabajan junto con triggers `BEFORE INSERT` que ejecutan `SELECT ... FOR UPDATE` sobre `productos`, serializando accesos concurrentes sobre el mismo repuesto. En compras, `sp_registrar_compra` aplica `pg_advisory_xact_lock(p_id_proveedor, hashtext(...))` para evitar recepciones duplicadas simultáneas de la misma factura.
2. **Inmutabilidad Financiera:** El trigger `trg_proteger_detalles_venta` prohíbe `UPDATE` o `DELETE` sobre `detalles_ventas` de ventas completadas. Cualquier corrección exige una anulación formal (`sp_anular_venta`), que repone stock, ajusta caja y deja traza en `bitacoras_auditorias`.
3. **Protección de Margen Económico en Motor:** La pareja `trg_validar_descuento_cabecera` (tope 50%) y `trg_validar_margen_costo_venta` (precio neto prorrateado nunca inferior al último costo de compra) garantizan que ninguna ruta —ni el POS web, ni una conversión de cotización, ni el Asistente IA— pueda generar ventas a pérdida.
4. **Desacoplamiento Limpio en Laravel:** Laravel se enfoca en validación HTTP de entrada, autorización por rol (`CheckRole`), generación documental (`ExportadorUniversalService`, DomPDF/OpenSpout), vistas Blade y orquestación del Asistente IA.

---

## 2. Seguridad Anti-Inyección SQL y Blindaje del Asistente IA (CU39)

El módulo de lenguaje natural nunca confía ciegamente en la salida de un LLM:
1. **Separación Estricta Lectura vs. Escritura:**
   - Gemini solo puede generar SQL en modo analítico (`{"sql": "SELECT ..."}`). Para operaciones de escritura (`venta`, `cotización`, `compra`, `ajuste`, `cliente`), está restringido a invocar `functionDeclarations` tipadas.
2. **Auditor Determinista de SQL (`AiSqlAssistantService::esSqlSeguro`):**
   - Rechaza toda consulta que no inicie con `SELECT` o `WITH`.
   - Bloquea múltiples sentencias (`;`), comentarios (`--`, `/*`), y cualquier palabra reservada mutante o peligrosa (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `TRUNCATE`, `CREATE`, `GRANT`, `REVOKE`, `CALL`, `EXEC`, `EXECUTE`, `COPY`, `VACUUM`, `SET`, `REPLACE`, `PG_SLEEP`).
   - Prohíbe explícitamente exponer `password_hash`.
   - Aplica **aislamiento por rol sobre el SQL generado**: por ejemplo, bloquea al `Vendedor` (`id_rol = 3`) de consultar `bitacoras_auditorias`, `sesiones_cajas`, `detalles_compras`, `costo_unitario`, `margen_ganancia` o `fn_reporte_rentabilidad`.
3. **Confirmación Humana Obligatoria (2 Pasos):**
   - Ningún `functionCall` de Gemini modifica la base de datos directamente; primero rellena el borrador en `AiDraftManager`, valida mínimos obligatorios y emite una tarjeta con `action_token` UUID vinculada al `user_id` de sesión. Solo el clic explícito del usuario en **Confirmar y Ejecutar** (`POST /asistente-ia/confirmar`) invoca el procedimiento almacenado.

---

## 3. Decisiones UX/UI de la Vitrina Pública (Principios Capa Zero)

El rediseño de `welcome.blade.php`, `partials/storefront/*` y `auth/login` + `auth/register` respondió a criterios medibles de conversión y accesibilidad:

1. **Contraste de Capas y Jerarquía Visual (WCAG AA):**
   - Cabecera y Hero en **Dark Mode Premium** (`#0F172A` con acentos `#10B981` Esmeralda y `#F59E0B` Ámbar) para proyectar solidez técnica automotriz y orientar la atención hacia el buscador protagonista.
   - Catálogo en **Swiss Clean Light** (`#F8FAFC` / `#FFFFFF` con texto `#0F172A` y `#475569`) para maximizar la legibilidad rápida de códigos OEM, SKUs y precios en `Bs.` bajo cualquier iluminación (incluidos talleres mecánicos).
2. **Tipografía Dual de Propósito:**
   - `Plus Jakarta Sans` para titulares y controles de navegación.
   - `JetBrains Mono` exclusiva para códigos OEM, SKU y montos monetarios, evitando confusión visual entre `0`/`O` o `1`/`I` en números de parte automotrices.
3. **Búsqueda y Filtrado sin Fricción (Zero Dead Ends):**
   - Buscador con **Autocompletado Instantáneo** en el Hero sobre `$autocompleteCatalog` (OEM, nombre, marca, vehículo) + **Bento Grid** de acceso rápido por sistema + **Filter Chips** deslizables en móvil + filtros facetados combinables (`vehiculo_id`, `marca`, `en_stock`).
4. **Conversión Dual en `cart_drawer.blade.php`:**
   - Panel lateral deslizante (`420px`) que unifica en un mismo flujo la **Compra Directa** (`POST /mi-compra`) y la **Solicitud de Proforma Formal** (`POST /mis-cotizaciones`), reforzado con **Trust Badges** (stock verificado, retiro en 2do Anillo, Efectivo/QR) y barra inferior fija en dispositivos móviles.
5. **Coherencia en Autenticación (`Split-Screen 50/50`):**
   - `login.blade.php` y `register.blade.php` comparten la misma gramática visual que la vitrina pública, con campos táctiles de `48px`, botón para mostrar/ocultar contraseña y enlace directo de retorno a la tienda.

---

## 4. Respuestas Técnicas a Preguntas Frecuentes de Evaluación

- **¿Qué pasa si la tienda no tiene caja abierta y un cliente web realiza una compra directa?**
  `VentaController::storeCompraWeb` verifica primero `SesionCaja::where('estado', 'Abierta')`. Si hay turno abierto ejecuta `sp_registrar_venta` y entrega el comprobante PDF (`/mi-comprobante/{id}`); si no hay caja abierta, resguarda el pedido automáticamente mediante `sp_registrar_cotizacion` para que el cliente conserve su proforma PDF sin violar el trigger `trg_validar_caja_abierta`.
- **¿Cómo evita el sistema que una caída o falta de cuota de Google Gemini rompa el Asistente IA?**
  `AiGeminiClient` intenta primero `gemini-2.5-flash` con timeout acotado (`6.5s`), hace fallback a `gemini-2.0-flash` y registra el incidente con `Log::warning`. Si ambos fallan o no hay API Key, `AiSqlAssistantService` conmuta de forma transparente a la **Vía B (motor local)**, que conserva la misma máquina de estados transaccional (`AiDraftManager`), las consultas a las tablas maestras/reportes y el subflujo de alta de productos nuevos.
- **¿Cómo se evita el problema de consultas N+1 en la vitrina pública?**
  `HomeController@index` calcula los conteos por marca en 2 consultas SQL agregadas (`GROUP BY` sobre `vehiculos` y `compatibilidades`), carga categorías con `withCount`, y precarga el catálogo y el índice de autocompletado con eager loading (`with(['categoria', 'vehiculos', 'codigosAlternativos'])`).
- **¿Cómo se garantiza la trazabilidad de auditoría cuando Laravel ejecuta procedimientos almacenados?**
  Tanto los controladores como `AiTransactionExecutor` ejecutan `SELECT set_config('app.id_usuario', ?, false)` y `SELECT set_config('app.ip_origen', ?, false)` antes de invocar los `CALL sp_*`, permitiendo que procedimientos y triggers (`fn_trg_auditar_anulacion_venta`, `fn_trg_auditar_cambio_password`) capturen al usuario autenticado e IP real dentro de `bitacoras_auditorias`.
- **¿Cómo se despliega la base de datos Smart-DB desde cero sin conflictos de orden o parches?**
  Los antiguos scripts incrementales (`Migracion_Ciclo2.sql`, `Migracion_Ciclo4.sql`) fueron fusionados en 4 archivos maestros idempotentes que se ejecutan en orden estricto: `Script.sql` -> `PA.sql` -> `Triggers.sql` -> `Poblacion.sql`.
