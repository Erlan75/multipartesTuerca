# 02. Diccionario Smart-DB y Reglas de Negocio (PostgreSQL 16+)

La carpeta `database/smartdb/` se encuentra consolidada en **4 archivos maestros canónicos** para cualquier despliegue limpio en PostgreSQL:
1. `database/smartdb/Script.sql` — Esquema DDL completo (19 tablas, índices y `CHECK` constraints).
2. `database/smartdb/PA.sql` — 17 procedimientos almacenados (`sp_*`) y funciones (`fn_*`) organizados en 6 módulos.
3. `database/smartdb/Triggers.sql` — 15 funciones `fn_trg_*` y sus triggers activos.
4. `database/smartdb/Poblacion.sql` — Semilla inicial idempotente con reset de secuencias y cierre de caja de ejemplo.

---

## 1. Diccionario de las 19 Tablas (`database/smartdb/Script.sql`)

### Módulo 1: Seguridad y Usuarios
1. **`roles`**
   - **PK:** `id_rol` (`SERIAL`)
   - **Columnas:** `nombre_rol VARCHAR(50)`, `descripcion VARCHAR(255)`
   - **Reglas / Índices:** `chk_rol_nombre_valido` (`length(trim(nombre_rol)) >= 3`), índice único `uq_rol_nombre_lower`.
2. **`usuarios`**
   - **PK:** `id_usuario` (`SERIAL`)
   - **FK:** `id_rol -> roles(id_rol)` (`ON DELETE RESTRICT`)
   - **Columnas:** `nombre_completo`, `username`, `correo`, `telefono`, `password_hash`, `estado BOOLEAN DEFAULT TRUE`, `created_at`
   - **Reglas / Índices:** `chk_usuario_nombre >= 3`, `chk_usuario_username >= 4`, `chk_usuario_correo` (regex email), `chk_usuario_password_hash >= 60`, índices únicos `uq_usuario_username_lower` y `uq_usuario_correo_lower`.

### Módulo 2: Catálogo, Inventario y Compatibilidad Vehicular
3. **`categorias`**
   - **PK:** `id_categoria` (`SERIAL`)
   - **Columnas:** `nombre VARCHAR(60)`, `descripcion TEXT`
   - **Reglas / Índices:** `chk_categoria_nombre >= 3`, índice único `uq_categoria_nombre_lower`.
4. **`productos`**
   - **PK:** `id_producto` (`SERIAL`)
   - **FK:** `id_categoria -> categorias(id_categoria)` (`ON DELETE RESTRICT`)
   - **Columnas:** `codigo_interno`, `codigo_oem`, `nombre_pieza`, `marca_fabricante`, `procedencia`, `precio_venta_sugerido`, `margen_ganancia`, `unidad_medida`, `stock_actual`, `stock_minimo`, `stock_seguridad`, `pasillo`, `estante`, `nivel_gaveta`, `imagen_url VARCHAR(255)`, `estado`
   - **Reglas / Índices:** `chk_prod_cod_interno >= 2`, `chk_prod_cod_oem >= 2`, `chk_prod_nombre >= 3`, `chk_prod_precio_sug > 0`, `chk_prod_margen >= 0`, `chk_prod_stocks` (`stock_actual >= 0`, `stock_minimo >= 0`, `stock_seguridad >= stock_minimo`), `uq_producto_codinterno_lower`, `idx_producto_oem_lower`.
5. **`codigos_alternativos`**
   - **PK:** `id_codigo_alt` (`SERIAL`)
   - **FK:** `id_producto -> productos(id_producto)` (`ON DELETE CASCADE`)
   - **Columnas:** `codigo_referencia`, `marca_alternativa`
   - **Reglas / Índices:** `chk_codalt_referencia >= 2`, `idx_codalt_ref_lower`.
6. **`vehiculos`**
   - **PK:** `id_vehiculo` (`SERIAL`)
   - **Columnas:** `marca`, `modelo`, `anio_inicio`, `anio_fin`, `motor_cilindrada`, `combustible`
   - **Reglas / Índices:** `chk_vehiculo_marca >= 2`, `chk_vehiculo_modelo >= 1`, `chk_vehiculo_anios` (`1950 <= anio_inicio <= anio_fin <= 2040`), `chk_vehiculo_combustible` (`Gasolina`, `Diésel`, `GNV`, `Híbrido`, `Eléctrico`), índice único `uq_vehiculo_completo`.
7. **`compatibilidades`**
   - **PK:** `id_compatibilidad` (`SERIAL`)
   - **FK:** `id_producto -> productos(id_producto)` (`ON DELETE CASCADE`), `id_vehiculo -> vehiculos(id_vehiculo)` (`ON DELETE CASCADE`)
   - **Columnas:** `notas_posicion VARCHAR(255)`
   - **Reglas:** `uq_compatibilidad_prod_veh` (`UNIQUE (id_producto, id_vehiculo)`).
8. **`ajustes_inventario`**
   - **PK:** `id_ajuste` (`SERIAL`)
   - **FK:** `id_producto -> productos(id_producto)`, `id_usuario -> usuarios(id_usuario)` (`ON DELETE RESTRICT`)
   - **Columnas:** `cantidad_ajustada INT`, `tipo_ajuste VARCHAR(40)`, `motivo TEXT`, `fecha_ajuste`
   - **Reglas:** `chk_ajuste_cantidad <> 0`, `chk_ajuste_tipo` (`Merma`, `Pérdida/Extravío`, `Conteo Físico`, `Devolución Dañada`, `Uso Interno`), `chk_ajuste_motivo >= 5` (el SP exige `>= 10`).

### Módulo 3: Compras y Proveedores
9. **`proveedores`**
   - **PK:** `id_proveedor` (`SERIAL`)
   - **Columnas:** `razon_social`, `nit`, `contacto_nombre`, `telefono`, `correo`, `direccion`, `condicion_comercial`, `estado BOOLEAN DEFAULT TRUE`
   - **Reglas / Índices:** `chk_prov_razon_social >= 3`, `chk_prov_condicion` (`Contado`, `Crédito 15 días`, `Crédito 30 días`, `Crédito 60 días`), `uq_proveedor_razon_lower`.
10. **`notas_compras`**
    - **PK:** `id_compra` (`SERIAL`)
    - **FK:** `id_proveedor -> proveedores(id_proveedor)`, `id_usuario_receptor -> usuarios(id_usuario)` (`ON DELETE RESTRICT`)
    - **Columnas:** `numero_factura_nota`, `fecha_compra`, `monto_total`, `tipo_pago`, `estado_pago`
    - **Reglas:** `chk_compra_factura >= 2`, `chk_compra_monto_total >= 0`, `chk_compra_tipo_pago` (`Contado`, `Crédito`), `chk_compra_estado_pago` (`Pagado`, `Pendiente`, `Anulado`).
11. **`detalles_compras`**
    - **PK:** `id_detalle_compra` (`SERIAL`)
    - **FK:** `id_compra -> notas_compras(id_compra)` (`ON DELETE CASCADE`), `id_producto -> productos(id_producto)` (`ON DELETE RESTRICT`)
    - **Columnas:** `cantidad_recibida`, `costo_unitario`, `subtotal`
    - **Reglas:** `chk_detcompra_cant > 0`, `chk_detcompra_costo > 0`, `chk_detcompra_subtotal >= costo_unitario * cantidad_recibida`.

### Módulo 4: Tesorería y Caja
12. **`sesiones_cajas`**
    - **PK:** `id_sesion` (`SERIAL`)
    - **FK:** `id_usuario_cajero -> usuarios(id_usuario)` (`ON DELETE RESTRICT`)
    - **Columnas:** `fecha_hora_apertura`, `monto_inicial`, `fecha_hora_cierre`, `monto_esperado_sistema`, `monto_real_contado`, `diferencia`, `estado`
    - **Reglas:** `chk_sesion_inicial >= 0`, `chk_sesion_estado` (`Abierta`, `Cerrada`, `Cuadrada`, `Con Diferencia`), `chk_sesion_fechas`.
13. **`movimientos_cajas`**
    - **PK:** `id_movimiento` (`SERIAL`)
    - **FK:** `id_sesion -> sesiones_cajas(id_sesion)` (`ON DELETE CASCADE`)
    - **Columnas:** `tipo`, `monto`, `motivo`, `hora`
    - **Reglas:** `chk_mov_tipo` (`Ingreso Extra`, `Retiro Menor`), `chk_mov_monto > 0`, `chk_mov_motivo >= 4`.

### Módulo 5: Clientes, Cotizaciones y Ventas (POS)
14. **`clientes`**
    - **PK:** `id_cliente` (`SERIAL`)
    - **Columnas:** `nombre_razon_social`, `ci_nit`, `telefono`, `tipo_cliente`, `descuento_frecuente`
    - **Reglas / Índices:** `chk_cliente_nombre >= 3`, `chk_cliente_tipo` (`Particular`, `Taller/Mecánico`, `Empresa/Flota`), `chk_cliente_descuento` (`0.00` a `50.00`), `idx_cliente_cinit`.
15. **`cotizaciones`**
    - **PK:** `id_cotizacion` (`SERIAL`)
    - **FK:** `id_cliente -> clientes(id_cliente)`, `id_usuario -> usuarios(id_usuario)` (`ON DELETE RESTRICT`)
    - **Columnas:** `fecha_emision`, `fecha_vencimiento`, `monto_total`, `estado`
    - **Reglas:** `chk_cot_fechas` (`fecha_vencimiento >= fecha_emision::DATE`), `chk_cot_monto >= 0`, `chk_cot_estado` (`Vigente`, `Concretada/Facturada`, `Vencida`, `Anulada`).
16. **`detalles_cotizaciones`**
    - **PK:** `id_detalle_cot` (`SERIAL`)
    - **FK:** `id_cotizacion -> cotizaciones(id_cotizacion)` (`ON DELETE CASCADE`), `id_producto -> productos(id_producto)` (`ON DELETE RESTRICT`)
    - **Columnas:** `cantidad`, `precio_unitario_cotizado`, `subtotal`
    - **Reglas:** `chk_detcot_cant > 0`, `chk_detcot_precio > 0`, `chk_detcot_subtotal` (`subtotal = precio_unitario_cotizado * cantidad`).
17. **`notas_ventas`**
    - **PK:** `id_venta` (`SERIAL`)
    - **FK:** `id_cliente -> clientes(id_cliente)`, `id_usuario_vendedor -> usuarios(id_usuario)`, `id_sesion_caja -> sesiones_cajas(id_sesion)` (`ON DELETE RESTRICT`)
    - **Columnas:** `fecha_hora`, `total_venta`, `descuento_total`, `metodo_pago`, `estado`, `motivo_anulacion`
    - **Reglas:** `chk_venta_total >= 0`, `chk_venta_descuento >= 0`, `chk_venta_metodo_pago` (`Efectivo`, `Transferencia/QR`, `Tarjeta Débito/Crédito`, `Mixto`), `chk_venta_estado` (`Completada`, `Anulada`), `chk_venta_anulacion` (si está `Anulada`, exige `motivo_anulacion >= 5`).
18. **`detalles_ventas`**
    - **PK:** `id_detalle_venta` (`SERIAL`)
    - **FK:** `id_venta -> notas_ventas(id_venta)` (`ON DELETE CASCADE`), `id_producto -> productos(id_producto)` (`ON DELETE RESTRICT`)
    - **Columnas:** `cantidad_vendida`, `precio_unitario_cobrado`, `subtotal`
    - **Reglas:** `chk_detventa_cant > 0`, `chk_detventa_precio > 0`, `chk_detventa_subtotal`.

### Módulo 6: Auditoría e Inmutabilidad
19. **`bitacoras_auditorias`**
    - **PK:** `id_bitacora` (`BIGSERIAL`)
    - **FK:** `id_usuario -> usuarios(id_usuario)` (`ON DELETE RESTRICT`, admite `NULL` para intentos fallidos o procesos internos)
    - **Columnas:** `fecha_hora`, `modulo_afectado`, `accion`, `registro_id`, `detalle_cambio TEXT`, `ip_origen VARCHAR(45)`
    - **Reglas / Índices:** `chk_bitacora_accion` (18 acciones oficiales: `INSERT`, `UPDATE`, `DELETE`, `ANULACION`, `LOGIN`, `LOGOUT`, `LOGIN_FALLIDO`, `CUENTA_BLOQUEADA`, `LOGIN_BLOQUEADO`, `PASSWORD_RESET_SOLICITUD`, `PASSWORD_RESET_EXITOSO`, `PASSWORD_CAMBIO_PERFIL`, `PASSWORD_MODIFICADO`, `REGISTRO_CLIENTE`, `APERTURA_CAJA`, `CIERRE_CAJA`, `CONVERSION_COTIZACION`, `DESCUENTO_AUTORIZADO`), `idx_bitacora_fecha`.

---

## 2. Catálogo de los 17 Procedimientos Almacenados y Funciones (`database/smartdb/PA.sql`)

| Módulo | Objeto | Parámetros de Entrada | Retorno / `INOUT` | Función Principal |
| :--- | :--- | :--- | :--- | :--- |
| **1. Seguridad** | `fn_validar_usuario_autorizado` | `p_id_usuario INT`, `p_roles_permitidos TEXT[]` | `RETURNS VARCHAR` (nombre del rol) | Valida existencia, estado activo y rol autorizado; aborta si falla. |
| **2. Catálogo** | `fn_buscar_repuestos` | `p_criterio VARCHAR(100)`, `p_marca_vehiculo VARCHAR(60)`, `p_modelo_vehiculo VARCHAR(80)`, `p_anio_vehiculo INT` (todos `DEFAULT NULL`) | `RETURNS TABLE` (`id_producto`, `codigo_interno`, `codigo_oem`, `nombre_pieza`, `marca_fabricante`, `precio_venta`, `stock_actual`, `ubicacion`) | Búsqueda multicriterio por OEM, código alternativo, nombre y compatibilidad. |
| **2. Inventario** | `sp_registrar_ajuste_inventario` | `p_id_producto INT`, `p_id_usuario INT`, `p_tipo_ajuste VARCHAR(40)`, `p_cantidad INT`, `p_motivo TEXT` | `INOUT p_id_ajuste INT`, `INOUT p_stock_resultante INT` | Valida usuario (`Administrador`, `Bodeguero`), motivo ≥ 10 chars, bloquea producto `FOR UPDATE`, calcula delta y registra auditoría. |
| **3. Compras** | `sp_registrar_compra` | `p_id_proveedor INT`, `p_id_usuario_receptor INT`, `p_numero_factura_nota VARCHAR(60)`, `p_fecha_compra DATE`, `p_tipo_pago VARCHAR(30)`, `p_items JSONB` | `INOUT p_id_compra INT` | Valida proveedor activo, aplica `pg_advisory_xact_lock` anti-factura duplicada, calcula total y audita; los detalles disparan aumento de stock. |
| **3. Compras** | `sp_liquidar_pago_compra` | `p_id_compra INT`, `p_id_usuario INT` | — | Bloquea compra `FOR UPDATE`, exige `tipo_pago='Crédito'` y `estado_pago='Pendiente'`, cambia a `'Pagado'` y audita. |
| **4. Ventas/Cot.** | `sp_registrar_cotizacion` | `p_id_cliente INT`, `p_id_usuario INT`, `p_dias_vigencia INT`, `p_items JSONB` | `INOUT p_id_cotizacion INT` | Registra proforma vigente con fecha de vencimiento y detalles atómicos. |
| **4. Ventas/Cot.** | `sp_convertir_cotizacion_a_venta` | `p_id_cotizacion INT`, `p_id_usuario_vendedor INT`, `p_id_sesion_caja INT`, `p_metodo_pago VARCHAR(30)`, `p_descuento_total NUMERIC(12,2)`, `p_detalles_pago JSONB` | `INOUT p_id_venta INT` | Valida vigencia de cotización, la marca `'Concretada/Facturada'`, crea la venta, copia detalles y audita `CONVERSION_COTIZACION`. |
| **4. Ventas/Cot.** | `sp_registrar_venta` | `p_id_cliente INT`, `p_id_usuario_vendedor INT`, `p_id_sesion_caja INT`, `p_metodo_pago VARCHAR(30)`, `p_descuento_total NUMERIC(12,2)`, `p_items JSONB`, `p_detalles_pago JSONB` | `INOUT p_id_venta INT` | Registra venta atómica POS/Web/IA, calcula bruto/neto, audita venta y registra evento `DESCUENTO_AUTORIZADO` si `v_descuento > 0`. |
| **4. Ventas/Cot.** | `sp_anular_venta` | `p_id_venta INT`, `p_id_usuario INT`, `p_motivo_anulacion TEXT` | — | Valida motivo ≥ 5 chars, propaga `app.id_usuario` y marca `'Anulada'` disparando restitución de stock, caja y bitácora. |
| **5. Caja** | `sp_abrir_caja` | `p_id_usuario_cajero INT`, `p_monto_inicial NUMERIC(12,2)` | `INOUT p_id_sesion INT` | Abre turno de caja (`Abierta`) y registra `APERTURA_CAJA`. |
| **5. Caja** | `sp_registrar_movimiento_caja` | `p_id_sesion INT`, `p_tipo VARCHAR(30)`, `p_monto NUMERIC(12,2)`, `p_motivo VARCHAR(255)`, `p_id_usuario INT` | `INOUT p_id_movimiento INT` | Impide retiros mayores al saldo esperado actual e inserta movimiento. |
| **5. Caja** | `sp_cerrar_caja` | `p_id_sesion INT`, `p_monto_real_contado NUMERIC(12,2)`, `p_observaciones TEXT`, `p_id_usuario INT` | — | Registra arqueo físico, dispara conciliación automática y audita `CIERRE_CAJA`. |
| **6. Reportes** | `fn_obtener_metricas_pos` | `p_fecha DATE DEFAULT CURRENT_DATE` | `RETURNS TABLE` (bruto, descuentos, neto, transacciones, productos bajo mínimo) | KPIs diarios del Dashboard y POS. |
| **6. Reportes** | `fn_reporte_rotacion_repuestos` | `p_fecha_inicio DATE`, `p_fecha_fin DATE`, `p_id_categoria INT` | `RETURNS TABLE` (12 cols: SKU, OEM, stock, vendidas, índice rotación, días s/ mov., clasificación) | Clasifica `Alta Rotación`, `Rotación Media` o `Baja Rotación / Hueso` (CU35). |
| **6. Reportes** | `fn_reporte_rentabilidad` | `p_fecha_inicio DATE`, `p_fecha_fin DATE`, `p_id_categoria INT` | `RETURNS TABLE` (ingresos netos prorrateados, costo último/estimado, utilidad bruta, margen %) | Calcula margen real descontando descuentos prorrateados y último costo de compra (CU36). |
| **6. Reportes** | `fn_reporte_compras_distribuidor` | `p_fecha_inicio DATE`, `p_fecha_fin DATE`, `p_id_proveedor INT` | `RETURNS TABLE` (órdenes, unidades, total comprado, pagado, saldo pendiente, última compra) | Consolida compras y obligaciones pendientes por distribuidor (CU37). |
| **6. Reportes** | `fn_reporte_balance_medios_pago` | `p_fecha_inicio DATE`, `p_fecha_fin DATE`, `p_id_sesion INT`, `p_id_cajero INT` | `RETURNS TABLE` (sesión, estado, apertura, cajero, medio de pago, tickets, descuento, neto, ticket promedio) | Audita recaudación por canal y turno de caja (CU38). |

---

## 3. Catálogo de Triggers e Invariantes de Negocio (`database/smartdb/Triggers.sql`)

| # | Trigger / Función | Tabla y Evento | Invariante de Negocio Protegida |
| :--- | :--- | :--- | :--- |
| 1 | `trg_descontar_stock_venta` (`fn_trg_descontar_stock_venta`) | `detalles_ventas` · `BEFORE INSERT` | Bloquea fila de producto con `FOR UPDATE` (anti race conditions), verifica producto activo y stock suficiente, y descuenta `stock_actual`. |
| 2 | `trg_incrementar_stock_compra` (`fn_trg_incrementar_stock_compra`) | `detalles_compras` · `AFTER INSERT` | Único punto que suma unidades compradas al `stock_actual` tras `sp_registrar_compra`. |
| 3 | `trg_validar_caja_abierta` (`fn_trg_validar_caja_abierta`) | `notas_ventas` · `BEFORE INSERT` | Prohíbe registrar ventas si la sesión de caja no existe o no está `'Abierta'`. |
| 4 | `trg_restituir_stock_anulacion` (`fn_trg_restituir_stock_anulacion`) | `notas_ventas` · `AFTER UPDATE OF estado` | Cuando una venta pasa de `'Completada'` a `'Anulada'`, devuelve automáticamente todas sus piezas al inventario. |
| 5 | `trg_aplicar_ajuste_inventario` (`fn_trg_aplicar_ajuste_inventario`) | `ajustes_inventario` · `AFTER INSERT` | Bloquea producto `FOR UPDATE`, impide dejar `stock_actual < 0` y aplica el delta firmado. |
| 6 | `trg_auditar_cambios_producto` (`fn_trg_auditar_cambios_producto`) | `productos` · `AFTER UPDATE` | Registra inmutablemente en `bitacoras_auditorias` cambios en `precio_venta_sugerido` o `estado`. |
| 7 | `trg_auditar_anulacion_venta` (`fn_trg_auditar_anulacion_venta`) | `notas_ventas` · `AFTER UPDATE OF estado` | Guarda traza `ANULACION` con motivo, montos e IP/usuario de sesión (`app.id_usuario`, `app.ip_origen`). |
| 8 | `trg_validar_sesion_unica_cajero` (`fn_trg_validar_sesion_unica_cajero`) | `sesiones_cajas` · `BEFORE INSERT` | Impide que un mismo cajero abra dos cajas simultáneas e inicializa `monto_esperado_sistema := monto_inicial`. |
| 9.1 | `trg_acumular_venta_caja` (`fn_trg_acumular_venta_caja`) | `notas_ventas` · `AFTER INSERT` | Suma `total_venta` al `monto_esperado_sistema` de la sesión abierta. |
| 9.2 | `trg_acumular_movimiento_caja` (`fn_trg_acumular_movimiento_caja`) | `movimientos_cajas` · `AFTER INSERT` | Suma `'Ingreso Extra'` o resta `'Retiro Menor'` en tiempo real al saldo esperado de caja. |
| 10 | `trg_conciliar_cierre_caja` (`fn_trg_conciliar_cierre_caja`) | `sesiones_cajas` · `BEFORE UPDATE` | Al pasar de `'Abierta'` a `'Cerrada'`, exige arqueo físico, sella hora de cierre, calcula `diferencia` y asigna `'Cuadrada'` o `'Con Diferencia'`. |
| 11 | `trg_proteger_detalles_venta` (`fn_trg_proteger_detalles_venta`) | `detalles_ventas` · `BEFORE UPDATE OR DELETE` | Hace inmutables las líneas de una venta `'Completada'`; obliga a anular formalmente en lugar de editar/borrar renglones. |
| 12 | `trg_validar_vigencia_cotizacion` (`fn_trg_validar_vigencia_cotizacion`) | `cotizaciones` · `BEFORE UPDATE OF estado` | Impide convertir a venta una cotización vencida por fecha (`CURRENT_DATE > fecha_vencimiento`). |
| 13 | `trg_descontar_caja_anulacion` (`fn_trg_descontar_caja_anulacion`) | `notas_ventas` · `AFTER UPDATE OF estado` | Descuenta del saldo esperado de caja el monto de una venta anulada. |
| 14 | `trg_auditar_cambio_password` (`fn_trg_auditar_cambio_password`) | `usuarios` · `AFTER UPDATE OF password_hash` | Audita con acción `PASSWORD_MODIFICADO` cualquier cambio o reseteo de credencial. |
| 15.1 | `trg_validar_descuento_cabecera` (`fn_trg_validar_descuento_cabecera`) | `notas_ventas` · `BEFORE INSERT OR UPDATE` | Impide descuentos negativos o superiores al **50%** del importe bruto total. |
| 15.2 | `trg_validar_margen_costo_venta` (`fn_trg_validar_margen_costo_venta`) | `detalles_ventas` · `BEFORE INSERT` | Prorratea el descuento global y prohíbe vender un repuesto a un precio neto efectivo menor a su último costo de compra (o costo base estimado según margen). |

---

## 4. Conteos de Referencia de Población Semilla (`database/smartdb/Poblacion.sql`)

La carga semilla maestra reinicia las secuencias e inserta el catálogo comercial ampliado con los siguientes volúmenes verificados:
- **Seguridad:** `5` roles y `5` usuarios base.
- **Catálogo y Compatibilidad:** `8` categorías, `68` productos con `imagen_url` local (`7` con stock bajo `<= stock_minimo` y `3` agotados con stock `0`), `36` códigos alternativos, `27` vehículos del parque automotor local y `162` registros de compatibilidad técnica.
- **Actores Comerciales:** `6` proveedores mayoristas y `14` clientes (`id_cliente = 1` reservado para `Cliente Ocasional Mostrador`).
- **Operación Transaccional:** `2` sesiones de caja (`1` cerrada/cuadrada y `1` abierta), `6` notas de compras (`22` detalles), `6` cotizaciones, `10` notas de venta (`18` detalles), `4` ajustes de inventario y `3` registros iniciales en bitácora.
