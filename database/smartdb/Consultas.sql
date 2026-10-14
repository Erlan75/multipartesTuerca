--=======================================================================================

-- Consultas Simples (Una sola tabla, filtros, ordenamientos y agregaciones básicas)

-- Consulta 1: Listado de repuestos con alerta de stock crítico o de seguridad

sql
SELECT 
    codigo_interno,
    codigo_oem,
    nombre_pieza,
    marca_fabricante,
    stock_actual,
    stock_minimo,
    stock_seguridad
FROM productos
WHERE stock_actual <= stock_seguridad AND estado = TRUE
ORDER BY stock_actual ASC;



-- Consulta 2: Clientes con derecho a descuento preferencial

sql
SELECT 
    nombre_razon_social,
    ci_nit,
    telefono,
    tipo_cliente,
    descuento_frecuente
FROM clientes
WHERE descuento_frecuente > 0.00
ORDER BY descuento_frecuente DESC;



-- Consulta 3: Ubicación física en depósito de piezas de alta rotación

sql
SELECT 
    codigo_interno,
    nombre_pieza,
    pasillo,
    estante,
    nivel_gaveta
FROM productos
WHERE pasillo IS NOT NULL 
  AND estante IS NOT NULL
ORDER BY pasillo, estante, nivel_gaveta;



-- Consulta 4: Resumen de ventas agrupadas por método de cobro

sql
SELECT 
    metodo_pago,
    COUNT(id_venta) AS total_transacciones,
    SUM(total_venta) AS monto_total_recaudado,
    AVG(total_venta)::NUMERIC(12,2) AS ticket_promedio
FROM notas_ventas
WHERE estado = 'Completada'
GROUP BY metodo_pago
ORDER BY monto_total_recaudado DESC;



-- Consulta 5: Auditorías de tipo UPDATE y ANULACION registradas

sql
SELECT 
    id_bitacora,
    fecha_hora,
    modulo_afectado,
    accion,
    registro_id,
    ip_origen
FROM bitacoras_auditorias
WHERE accion IN ('UPDATE', 'ANULACION')
ORDER BY fecha_hora DESC;



-- Consulta 6: Cotizaciones que ya han expirado formalmente

sql
SELECT 
    id_cotizacion,
    fecha_emision::DATE AS emision,
    fecha_vencimiento,
    monto_total,
    estado
FROM cotizaciones
WHERE estado = 'Vencida' OR (fecha_vencimiento < CURRENT_DATE AND estado = 'Vigente')
ORDER BY fecha_vencimiento ASC;



-- Consulta 7: Conteo y volumen de compras por estado de crédito

sql
SELECT 
    tipo_pago,
    estado_pago,
    COUNT(id_compra) AS cantidad_notas,
    SUM(monto_total) AS total_acumulado
FROM notas_compras
GROUP BY tipo_pago, estado_pago
ORDER BY total_acumulado DESC;



-- Consulta 8: Catálogo vehicular filtrado por tipo de combustible

sql
SELECT 
    marca,
    modelo,
    anio_inicio,
    anio_fin,
    motor_cilindrada,
    combustible
FROM vehiculos
WHERE combustible = 'Gasolina' AND anio_fin >= 2015
ORDER BY marca, modelo, anio_inicio DESC;



--======================================================================

-- Consultas Dobles (JOIN entre 2 tablas)

-- Consulta 9: Catálogo de productos con el nombre de su categoría

sql
SELECT 
    p.codigo_interno,
    p.nombre_pieza,
    p.marca_fabricante,
    p.precio_venta_sugerido,
    c.nombre AS categoria
FROM productos p
JOIN categorias c ON p.id_categoria = c.id_categoria
ORDER BY c.nombre, p.nombre_pieza;



-- Consulta 10: Códigos equivalentes y alternativos por producto

sql
SELECT 
    p.codigo_interno,
    p.nombre_pieza,
    p.codigo_oem,
    ca.codigo_referencia AS codigo_alternativo,
    ca.marca_alternativa
FROM productos p
JOIN codigos_alternativos ca ON p.id_producto = ca.id_producto
ORDER BY p.codigo_interno;



-- Consulta 11: Empleados del sistema con su rol de seguridad correspondiente

sql
SELECT 
    u.id_usuario,
    u.nombre_completo,
    u.username,
    u.correo,
    r.nombre_rol AS rol
FROM usuarios u
JOIN roles r ON u.id_rol = r.id_rol
ORDER BY r.nombre_rol, u.nombre_completo;



-- Consulta 12: Compras vinculadas a la razón social de la empresa proveedora

sql
SELECT 
    nc.numero_factura_nota,
    nc.fecha_compra,
    pr.razon_social AS proveedor,
    pr.condicion_comercial,
    nc.monto_total,
    nc.estado_pago
FROM notas_compras nc
JOIN proveedores pr ON nc.id_proveedor = pr.id_proveedor
ORDER BY nc.fecha_compra DESC;



-- Consulta 13: Arqueos de caja con el nombre del cajero asignado

sql
SELECT 
    sc.id_sesion,
    u.nombre_completo AS cajero,
    sc.fecha_hora_apertura,
    sc.monto_inicial,
    sc.monto_esperado_sistema,
    sc.monto_real_contado,
    sc.diferencia,
    sc.estado
FROM sesiones_cajas sc
JOIN usuarios u ON sc.id_usuario_cajero = u.id_usuario
ORDER BY sc.id_sesion DESC;



-- Consulta 14: Presupuestos formales con datos de contacto del cliente

sql
SELECT 
    c.id_cotizacion,
    cl.nombre_razon_social AS cliente,
    cl.telefono,
    c.fecha_emision::DATE AS fecha,
    c.fecha_vencimiento,
    c.monto_total,
    c.estado
FROM cotizaciones c
JOIN clientes cl ON c.id_cliente = cl.id_cliente
ORDER BY c.monto_total DESC;



-- Consulta 15: Movimientos menores de caja agrupados por sesión

sql
SELECT 
    mc.id_movimiento,
    mc.id_sesion,
    mc.tipo,
    mc.monto,
    mc.motivo,
    sc.estado AS estado_sesion
FROM movimientos_cajas mc
JOIN sesiones_cajas sc ON mc.id_sesion = sc.id_sesion
ORDER BY mc.hora DESC;



-- Consulta 16: Ajustes de inventario y usuario responsable del almacén

sql
SELECT 
    ai.id_ajuste,
    p.nombre_pieza,
    ai.cantidad_ajustada,
    ai.tipo_ajuste,
    ai.motivo,
    u.nombre_completo AS responsable
FROM ajustes_inventario ai
JOIN usuarios u ON ai.id_usuario = u.id_usuario
JOIN productos p ON ai.id_producto = p.id_producto
ORDER BY ai.fecha_ajuste DESC;



--=========================================================================================

-- Consultas Múltiples (JOIN entre 3 o más tablas con agrupaciones y lógica de negocio)

-- Consulta 17: Detalle completo de facturación POS (Ticket comercial)

sql
SELECT 
    nv.id_venta,
    nv.fecha_hora::TIMESTAMP(0) AS fecha_venta,
    cl.nombre_razon_social AS cliente,
    uv.nombre_completo AS vendedor,
    p.codigo_interno,
    p.nombre_pieza,
    dv.cantidad_vendida,
    dv.precio_unitario_cobrado,
    dv.subtotal,
    nv.total_venta
FROM notas_ventas nv
JOIN clientes cl ON nv.id_cliente = cl.id_cliente
JOIN usuarios uv ON nv.id_usuario_vendedor = uv.id_usuario
JOIN detalles_ventas dv ON nv.id_venta = dv.id_venta
JOIN productos p ON dv.id_producto = p.id_producto
ORDER BY nv.id_venta DESC, dv.id_detalle_venta ASC;



-- Consulta 18: Aplicación técnica vehicular de repuestos (Catálogo cruzado)

sql
SELECT 
    p.codigo_oem,
    p.nombre_pieza,
    cat.nombre AS categoria,
    v.marca AS marca_vehiculo,
    v.modelo AS modelo_vehiculo,
    CONCAT(v.anio_inicio, ' - ', v.anio_fin) AS rango_anios,
    v.motor_cilindrada,
    c.notas_posicion
FROM productos p
JOIN categorias cat ON p.id_categoria = cat.id_categoria
JOIN compatibilidades c ON p.id_producto = c.id_producto
JOIN vehiculos v ON c.id_vehiculo = v.id_vehiculo
ORDER BY v.marca, v.modelo, p.nombre_pieza;



-- Consulta 19: Renglones de compras con proveedor, producto y receptor

sql
SELECT 
    nc.numero_factura_nota,
    pr.razon_social AS proveedor,
    u.nombre_completo AS receptor_almacen,
    p.codigo_interno,
    p.nombre_pieza,
    dc.cantidad_recibida,
    dc.costo_unitario,
    dc.subtotal
FROM notas_compras nc
JOIN proveedores pr ON nc.id_proveedor = pr.id_proveedor
JOIN usuarios u ON nc.id_usuario_receptor = u.id_usuario
JOIN detalles_compras dc ON nc.id_compra = dc.id_compra
JOIN productos p ON dc.id_producto = p.id_producto
ORDER BY nc.id_compra DESC;



-- Consulta 20: Desglose de cotizaciones con cliente, vendedor e ítems

sql
SELECT 
    cot.id_cotizacion,
    cl.nombre_razon_social AS cliente,
    uv.nombre_completo AS vendedor,
    p.nombre_pieza,
    dc.cantidad,
    dc.precio_unitario_cotizado,
    dc.subtotal,
    cot.estado AS estado_cotizacion
FROM cotizaciones cot
JOIN clientes cl ON cot.id_cliente = cl.id_cliente
JOIN usuarios uv ON cot.id_usuario = uv.id_usuario
JOIN detalles_cotizaciones dc ON cot.id_cotizacion = dc.id_cotizacion
JOIN productos p ON dc.id_producto = p.id_producto
ORDER BY cot.id_cotizacion DESC;



-- Consulta 21: Rendimiento y volumen de ventas generado por cada vendedor

sql
SELECT 
    u.id_usuario,
    u.nombre_completo AS vendedor,
    COUNT(DISTINCT nv.id_venta) AS transacciones_atendidas,
    COALESCE(SUM(dv.cantidad_vendida), 0) AS total_repuestos_despachados,
    COALESCE(SUM(nv.total_venta), 0.00) AS total_recaudado_bs
FROM usuarios u
JOIN notas_ventas nv ON u.id_usuario = nv.id_usuario_vendedor
JOIN detalles_ventas dv ON nv.id_venta = dv.id_venta
WHERE nv.estado = 'Completada'
GROUP BY u.id_usuario, u.nombre_completo
ORDER BY total_recaudado_bs DESC;



-- Consulta 22: Rentabilidad bruta acumulada por categoría de autoparte

sql
SELECT 
    cat.nombre AS categoria,
    COUNT(DISTINCT p.id_producto) AS tipos_repuestos,
    SUM(dv.cantidad_vendida) AS unidades_vendidas,
    SUM(dv.subtotal) AS ingreso_bruto_generado
FROM categorias cat
JOIN productos p ON cat.id_categoria = p.id_categoria
JOIN detalles_ventas dv ON p.id_producto = dv.id_producto
JOIN notas_ventas nv ON dv.id_venta = nv.id_venta
WHERE nv.estado = 'Completada'
GROUP BY cat.nombre
ORDER BY ingreso_bruto_generado DESC;



-- Consulta 23: Balance de flujo monetario en sesiones de caja

sql
SELECT 
    sc.id_sesion,
    u.nombre_completo AS cajero,
    sc.monto_inicial,
    COALESCE(SUM(nv.total_venta), 0.00) AS ventas_efectivo_qr,
    sc.monto_esperado_sistema,
    sc.monto_real_contado,
    sc.diferencia,
    sc.estado
FROM sesiones_cajas sc
JOIN usuarios u ON sc.id_usuario_cajero = u.id_usuario
LEFT JOIN notas_ventas nv ON sc.id_sesion = nv.id_sesion_caja AND nv.estado = 'Completada'
GROUP BY sc.id_sesion, u.nombre_completo, sc.monto_inicial, sc.monto_esperado_sistema, sc.monto_real_contado, sc.diferencia, sc.estado
ORDER BY sc.id_sesion DESC;



-- Consulta 24: Auditoría de eventos con usuario y datos del registro

sql
SELECT 
    ba.id_bitacora,
    ba.fecha_hora::TIMESTAMP(0) AS fecha_evento,
    u.nombre_completo AS operador,
    r.nombre_rol,
    ba.modulo_afectado,
    ba.accion,
    ba.registro_id,
    ba.detalle_cambio
FROM bitacoras_auditorias ba
JOIN usuarios u ON ba.id_usuario = u.id_usuario
JOIN roles r ON u.id_rol = r.id_rol
ORDER BY ba.fecha_hora DESC;



--===========================================================================================

-- Subconsultas (WHERE IN, NOT IN, EXISTS, Escalares y Derivadas)

-- Consulta 25: Repuestos cuyo precio de venta supera el promedio general de su categoría

sql
SELECT 
    p.codigo_interno,
    p.nombre_pieza,
    p.id_categoria,
    p.precio_venta_sugerido
FROM productos p
WHERE p.precio_venta_sugerido > (
    SELECT AVG(p2.precio_venta_sugerido)
    FROM productos p2
    WHERE p2.id_categoria = p.id_categoria
)
ORDER BY p.id_categoria, p.precio_venta_sugerido DESC;



-- Consulta 26: Productos con existencias físicas que nunca han tenido salidas por venta

sql
SELECT 
    p.id_producto,
    p.codigo_interno,
    p.nombre_pieza,
    p.stock_actual
FROM productos p
WHERE p.id_producto NOT IN (
    SELECT DISTINCT dv.id_producto 
    FROM detalles_ventas dv
)
ORDER BY p.stock_actual DESC;



-- Consulta 27: Proveedores a los que actualmente se les adeuda dinero por compras a crédito

sql
SELECT 
    pr.id_proveedor,
    pr.razon_social,
    pr.telefono,
    pr.condicion_comercial
FROM proveedores pr
WHERE EXISTS (
    SELECT 1 
    FROM notas_compras nc 
    WHERE nc.id_proveedor = pr.id_proveedor 
      AND nc.tipo_pago = 'Crédito' 
      AND nc.estado_pago = 'Pendiente'
);



-- Consulta 28: Clientes cuyo gasto histórico total está por encima del ticket promedio de la tienda

sql
SELECT 
    c.id_cliente,
    c.nombre_razon_social,
    c.tipo_cliente,
    t.total_consumido
FROM clientes c
JOIN (
    SELECT 
        id_cliente, 
        SUM(total_venta) AS total_consumido
    FROM notas_ventas
    WHERE estado = 'Completada'
    GROUP BY id_cliente
) t ON c.id_cliente = t.id_cliente
WHERE t.total_consumido > (
    SELECT AVG(total_venta) 
    FROM notas_ventas 
    WHERE estado = 'Completada'
)
ORDER BY t.total_consumido DESC;



-- Consulta 29: Top repuestos más vendidos mediante subconsulta derivada

sql
SELECT 
    p.codigo_interno,
    p.nombre_pieza,
    top_ventas.total_unidades
FROM productos p
JOIN (
    SELECT 
        id_producto,
        SUM(cantidad_vendida) AS total_unidades
    FROM detalles_ventas
    GROUP BY id_producto
) top_ventas ON p.id_producto = top_ventas.id_producto
ORDER BY top_ventas.total_unidades DESC
LIMIT 5;



-- Consulta 30: Vehículos que no tienen ningún repuesto asignado en la matriz técnica

sql
SELECT 
    v.id_vehiculo,
    v.marca,
    v.modelo,
    v.anio_inicio,
    v.anio_fin
FROM vehiculos v
WHERE NOT EXISTS (
    SELECT 1 
    FROM compatibilidades comp 
    WHERE comp.id_vehiculo = v.id_vehiculo
);



-- Consulta 31: Lista de compras cuyo monto superó el costo promedio de adquisiciones

sql
SELECT 
    nc.numero_factura_nota,
    nc.fecha_compra,
    nc.monto_total,
    (SELECT pr.razon_social FROM proveedores pr WHERE pr.id_proveedor = nc.id_proveedor) AS proveedor
FROM notas_compras nc
WHERE nc.monto_total > (
    SELECT AVG(monto_total) 
    FROM notas_compras
)
ORDER BY nc.monto_total DESC;



-- Consulta 32: Cotizaciones que superan el monto total de la venta más cara registrada

sql
SELECT 
    c.id_cotizacion,
    c.fecha_emision::DATE AS fecha,
    c.monto_total,
    c.estado,
    (SELECT cl.nombre_razon_social FROM clientes cl WHERE cl.id_cliente = c.id_cliente) AS cliente
FROM cotizaciones c
WHERE c.monto_total > (
    SELECT MAX(total_venta) 
    FROM notas_ventas 
    WHERE estado = 'Completada'
);

