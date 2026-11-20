-- =============================================================================
-- MIGRACIÓN IDEMPOTENTE CICLO 4 (CU35 - CU39) + SANEAMIENTO CICLO 3
-- PROYECTO: MULTIPARTES EL TUERCA — ARQUITECTURA SMART-DB (PostgreSQL 16+)
-- =============================================================================

-- 1. Saneamiento Ciclo 3: Eliminar sobrecarga heredada de 2 parámetros en sp_cerrar_caja
DROP PROCEDURE IF EXISTS sp_cerrar_caja(INT, NUMERIC);

-- 2. Garantizar catálogo completo de acciones en bitacoras_auditorias (Ciclos 1 a 4)
ALTER TABLE bitacoras_auditorias DROP CONSTRAINT IF EXISTS chk_bitacora_accion;
ALTER TABLE bitacoras_auditorias ADD CONSTRAINT chk_bitacora_accion CHECK (accion IN (
    'INSERT', 'UPDATE', 'DELETE', 'ANULACION', 'LOGIN', 'LOGOUT',
    'LOGIN_FALLIDO', 'CUENTA_BLOQUEADA', 'LOGIN_BLOQUEADO',
    'PASSWORD_RESET_SOLICITUD', 'PASSWORD_RESET_EXITOSO', 'PASSWORD_CAMBIO_PERFIL',
    'PASSWORD_MODIFICADO', 'REGISTRO_CLIENTE',
    'APERTURA_CAJA', 'CIERRE_CAJA', 'CONVERSION_COTIZACION', 'DESCUENTO_AUTORIZADO'
));

-- =============================================================================
-- CU35: REPORTE ANALÍTICO DE ROTACIÓN DE REPUESTOS Y PRODUCTOS HUESO
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_reporte_rotacion_repuestos(
    p_fecha_inicio DATE,
    p_fecha_fin DATE,
    p_id_categoria INT DEFAULT NULL
)
RETURNS TABLE (
    id_producto INT,
    codigo_interno VARCHAR(40),
    codigo_oem VARCHAR(60),
    nombre_pieza VARCHAR(150),
    categoria VARCHAR(60),
    stock_actual INT,
    stock_minimo INT,
    unidades_vendidas BIGINT,
    frecuencia_ventas BIGINT,
    indice_rotacion NUMERIC(10, 4),
    dias_sin_movimiento INT,
    clasificacion VARCHAR(30)
) AS $$
BEGIN
    RETURN QUERY
    WITH ventas_periodo AS (
        SELECT
            dv.id_producto,
            COALESCE(SUM(dv.cantidad_vendida), 0)::BIGINT AS uds_vendidas,
            COUNT(DISTINCT nv.id_venta)::BIGINT AS freq_ventas
        FROM detalles_ventas dv
        JOIN notas_ventas nv ON nv.id_venta = dv.id_venta
        WHERE nv.estado = 'Completada'
          AND nv.fecha_hora::DATE BETWEEN p_fecha_inicio AND p_fecha_fin
        GROUP BY dv.id_producto
    ),
    ultima_salida AS (
        SELECT
            dv.id_producto,
            MAX(nv.fecha_hora::DATE) AS ultima_fecha_venta
        FROM detalles_ventas dv
        JOIN notas_ventas nv ON nv.id_venta = dv.id_venta
        WHERE nv.estado = 'Completada'
        GROUP BY dv.id_producto
    ),
    base_calc AS (
        SELECT
            p.id_producto,
            p.codigo_interno,
            p.codigo_oem,
            p.nombre_pieza,
            c.nombre AS categoria,
            p.stock_actual,
            p.stock_minimo,
            COALESCE(vp.uds_vendidas, 0)::BIGINT AS unidades_vendidas,
            COALESCE(vp.freq_ventas, 0)::BIGINT AS frecuencia_ventas,
            COALESCE(
                ROUND(
                    COALESCE(vp.uds_vendidas, 0)::NUMERIC
                    / NULLIF((p.stock_actual + COALESCE(vp.uds_vendidas, 0))::NUMERIC, 0),
                    4
                ),
                0.0000
            ) AS indice_rotacion,
            GREATEST(
                0,
                (CURRENT_DATE - COALESCE(us.ultima_fecha_venta, p_fecha_inicio))::INT
            ) AS dias_sin_movimiento
        FROM productos p
        JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN ventas_periodo vp ON vp.id_producto = p.id_producto
        LEFT JOIN ultima_salida us ON us.id_producto = p.id_producto
        WHERE p.estado = TRUE
          AND (p_id_categoria IS NULL OR p.id_categoria = p_id_categoria)
    )
    SELECT
        b.id_producto,
        b.codigo_interno,
        b.codigo_oem,
        b.nombre_pieza,
        b.categoria,
        b.stock_actual,
        b.stock_minimo,
        b.unidades_vendidas,
        b.frecuencia_ventas,
        b.indice_rotacion,
        b.dias_sin_movimiento,
        (CASE
            WHEN b.unidades_vendidas >= 10 OR b.indice_rotacion >= 0.4000 THEN 'Alta Rotación'
            WHEN b.unidades_vendidas >= 3  OR b.indice_rotacion >= 0.1500 THEN 'Rotación Media'
            ELSE 'Baja Rotación / Hueso'
        END)::VARCHAR(30) AS clasificacion
    FROM base_calc b
    ORDER BY b.unidades_vendidas DESC, b.indice_rotacion DESC, b.nombre_pieza ASC;
END;
$$ LANGUAGE plpgsql;

-- =============================================================================
-- CU36: REPORTE ANALÍTICO DE RENTABILIDAD Y MARGEN BRUTO REAL
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_reporte_rentabilidad(
    p_fecha_inicio DATE,
    p_fecha_fin DATE,
    p_id_categoria INT DEFAULT NULL
)
RETURNS TABLE (
    id_producto INT,
    codigo_interno VARCHAR(40),
    nombre_pieza VARCHAR(150),
    categoria VARCHAR(60),
    cantidad_vendida BIGINT,
    ingresos_netos NUMERIC(12, 2),
    costo_total_estimado NUMERIC(12, 2),
    utilidad_bruta NUMERIC(12, 2),
    margen_real_porcentaje NUMERIC(8, 2)
) AS $$
BEGIN
    RETURN QUERY
    WITH ultimo_costo_compra AS (
        SELECT DISTINCT ON (dc.id_producto)
            dc.id_producto,
            dc.costo_unitario
        FROM detalles_compras dc
        JOIN notas_compras nc ON nc.id_compra = dc.id_compra
        WHERE nc.estado_pago <> 'Anulado'
        ORDER BY dc.id_producto, nc.fecha_compra DESC, dc.id_detalle_compra DESC
    ),
    ventas_prorrateadas AS (
        SELECT
            dv.id_producto,
            dv.cantidad_vendida,
            CASE
                WHEN (nv.total_venta + nv.descuento_total) > 0
                    THEN dv.subtotal * (nv.total_venta / (nv.total_venta + nv.descuento_total))
                ELSE dv.subtotal
            END AS ingreso_neto_linea
        FROM detalles_ventas dv
        JOIN notas_ventas nv ON nv.id_venta = dv.id_venta
        WHERE nv.estado = 'Completada'
          AND nv.fecha_hora::DATE BETWEEN p_fecha_inicio AND p_fecha_fin
    ),
    agregado AS (
        SELECT
            p.id_producto,
            p.codigo_interno,
            p.nombre_pieza,
            c.nombre AS categoria,
            SUM(vp.cantidad_vendida)::BIGINT AS cantidad_vendida,
            ROUND(SUM(vp.ingreso_neto_linea), 2) AS ingresos_netos,
            ROUND(
                SUM(vp.cantidad_vendida) * COALESCE(
                    ucc.costo_unitario,
                    ROUND(p.precio_venta_sugerido / (1.00 + (COALESCE(p.margen_ganancia, 25.00) / 100.00)), 2)
                ),
                2
            ) AS costo_total_estimado
        FROM ventas_prorrateadas vp
        JOIN productos p ON p.id_producto = vp.id_producto
        JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN ultimo_costo_compra ucc ON ucc.id_producto = p.id_producto
        WHERE (p_id_categoria IS NULL OR p.id_categoria = p_id_categoria)
        GROUP BY
            p.id_producto, p.codigo_interno, p.nombre_pieza, c.nombre,
            ucc.costo_unitario, p.precio_venta_sugerido, p.margen_ganancia
    )
    SELECT
        a.id_producto,
        a.codigo_interno,
        a.nombre_pieza,
        a.categoria,
        a.cantidad_vendida,
        a.ingresos_netos,
        a.costo_total_estimado,
        ROUND(a.ingresos_netos - a.costo_total_estimado, 2) AS utilidad_bruta,
        COALESCE(
            ROUND(((a.ingresos_netos - a.costo_total_estimado) / NULLIF(a.ingresos_netos, 0)) * 100.0, 2),
            0.00
        ) AS margen_real_porcentaje
    FROM agregado a
    ORDER BY utilidad_bruta DESC, a.ingresos_netos DESC;
END;
$$ LANGUAGE plpgsql;

-- =============================================================================
-- CU37: REPORTE ANALÍTICO DE COMPRAS Y OBLIGACIONES POR DISTRIBUIDOR
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_reporte_compras_distribuidor(
    p_fecha_inicio DATE,
    p_fecha_fin DATE,
    p_id_proveedor INT DEFAULT NULL
)
RETURNS TABLE (
    id_proveedor INT,
    razon_social VARCHAR(120),
    nit VARCHAR(30),
    condicion_comercial VARCHAR(60),
    total_ordenes BIGINT,
    unidades_compradas BIGINT,
    monto_total_comprado NUMERIC(12, 2),
    monto_pagado NUMERIC(12, 2),
    saldo_pendiente_credito NUMERIC(12, 2),
    ultima_compra DATE
) AS $$
BEGIN
    RETURN QUERY
    WITH unidades_por_compra AS (
        SELECT
            dc.id_compra,
            COALESCE(SUM(dc.cantidad_recibida), 0)::BIGINT AS total_unidades
        FROM detalles_compras dc
        GROUP BY dc.id_compra
    )
    SELECT
        pr.id_proveedor,
        pr.razon_social,
        pr.nit,
        pr.condicion_comercial,
        COUNT(nc.id_compra)::BIGINT AS total_ordenes,
        COALESCE(SUM(upc.total_unidades), 0)::BIGINT AS unidades_compradas,
        ROUND(COALESCE(SUM(nc.monto_total), 0.00), 2) AS monto_total_comprado,
        ROUND(COALESCE(SUM(CASE WHEN nc.estado_pago = 'Pagado' THEN nc.monto_total ELSE 0.00 END), 0.00), 2) AS monto_pagado,
        ROUND(COALESCE(SUM(CASE WHEN nc.estado_pago = 'Pendiente' THEN nc.monto_total ELSE 0.00 END), 0.00), 2) AS saldo_pendiente_credito,
        MAX(nc.fecha_compra) AS ultima_compra
    FROM proveedores pr
    LEFT JOIN notas_compras nc
        ON nc.id_proveedor = pr.id_proveedor
       AND nc.estado_pago <> 'Anulado'
       AND nc.fecha_compra BETWEEN p_fecha_inicio AND p_fecha_fin
    LEFT JOIN unidades_por_compra upc
        ON upc.id_compra = nc.id_compra
    WHERE (p_id_proveedor IS NULL OR pr.id_proveedor = p_id_proveedor)
    GROUP BY pr.id_proveedor, pr.razon_social, pr.nit, pr.condicion_comercial
    HAVING COUNT(nc.id_compra) > 0 OR p_id_proveedor IS NOT NULL
    ORDER BY monto_total_comprado DESC, pr.razon_social ASC;
END;
$$ LANGUAGE plpgsql;

-- =============================================================================
-- CU38: REPORTE ANALÍTICO DE BALANCE DE MEDIOS DE PAGO Y TURNOS DE CAJA
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_reporte_balance_medios_pago(
    p_fecha_inicio DATE,
    p_fecha_fin DATE,
    p_id_sesion INT DEFAULT NULL,
    p_id_cajero INT DEFAULT NULL
)
RETURNS TABLE (
    id_sesion INT,
    estado_sesion VARCHAR(20),
    fecha_apertura TIMESTAMP WITHOUT TIME ZONE,
    cajero_responsable VARCHAR(120),
    metodo_pago VARCHAR(30),
    cantidad_tickets BIGINT,
    total_descuento NUMERIC(12, 2),
    total_neto_recaudado NUMERIC(12, 2),
    ticket_promedio NUMERIC(12, 2)
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        sc.id_sesion,
        sc.estado AS estado_sesion,
        sc.fecha_hora_apertura AS fecha_apertura,
        u.nombre_completo AS cajero_responsable,
        nv.metodo_pago,
        COUNT(nv.id_venta)::BIGINT AS cantidad_tickets,
        ROUND(COALESCE(SUM(nv.descuento_total), 0.00), 2) AS total_descuento,
        ROUND(COALESCE(SUM(nv.total_venta), 0.00), 2) AS total_neto_recaudado,
        COALESCE(
            ROUND(SUM(nv.total_venta) / NULLIF(COUNT(nv.id_venta), 0), 2),
            0.00
        ) AS ticket_promedio
    FROM notas_ventas nv
    JOIN sesiones_cajas sc ON sc.id_sesion = nv.id_sesion_caja
    JOIN usuarios u ON u.id_usuario = sc.id_usuario_cajero
    WHERE nv.estado = 'Completada'
      AND nv.fecha_hora::DATE BETWEEN p_fecha_inicio AND p_fecha_fin
      AND (p_id_sesion IS NULL OR sc.id_sesion = p_id_sesion)
      AND (p_id_cajero IS NULL OR sc.id_usuario_cajero = p_id_cajero)
    GROUP BY sc.id_sesion, sc.estado, sc.fecha_hora_apertura, u.nombre_completo, nv.metodo_pago
    ORDER BY sc.id_sesion DESC, total_neto_recaudado DESC;
END;
$$ LANGUAGE plpgsql;
