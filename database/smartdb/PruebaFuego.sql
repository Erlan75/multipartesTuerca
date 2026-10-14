SELECT 
    '1. Roles' AS entidad, COUNT(*) AS total_registros FROM roles
UNION ALL
SELECT '2. Usuarios', COUNT(*) FROM usuarios
UNION ALL
SELECT '3. Categorías', COUNT(*) FROM categorias
UNION ALL
SELECT '4. Productos (Repuestos)', COUNT(*) FROM productos
UNION ALL
SELECT '5. Códigos Alternativos', COUNT(*) FROM codigos_alternativos
UNION ALL
SELECT '6. Vehículos (Modelos/Años)', COUNT(*) FROM vehiculos
UNION ALL
SELECT '7. Compatibilidades Técnicas', COUNT(*) FROM compatibilidades
UNION ALL
SELECT '8. Proveedores Mayoristas', COUNT(*) FROM proveedores
UNION ALL
SELECT '9. Clientes y Talleres', COUNT(*) FROM clientes
UNION ALL
SELECT '10. Sesiones de Caja', COUNT(*) FROM sesiones_cajas
UNION ALL
SELECT '11. Movimientos de Caja', COUNT(*) FROM movimientos_cajas
UNION ALL
SELECT '12. Órdenes de Compra', COUNT(*) FROM notas_compras
UNION ALL
SELECT '13. Detalles de Compras', COUNT(*) FROM detalles_compras
UNION ALL
SELECT '14. Cotizaciones', COUNT(*) FROM cotizaciones
UNION ALL
SELECT '15. Detalles de Cotizaciones', COUNT(*) FROM detalles_cotizaciones
UNION ALL
SELECT '16. Notas de Venta (POS)', COUNT(*) FROM notas_ventas
UNION ALL
SELECT '17. Detalles de Ventas', COUNT(*) FROM detalles_ventas
UNION ALL
SELECT '18. Ajustes de Inventario', COUNT(*) FROM ajustes_inventario
UNION ALL
SELECT '19. Bitácora de Auditoría', COUNT(*) FROM bitacoras_auditorias
ORDER BY entidad;

-- Intento de sobreventa fraudulenta
INSERT INTO detalles_ventas (id_venta, id_producto, cantidad_vendida, precio_unitario_cobrado, subtotal)
VALUES (2, 2, 500, 310.00, 155000.00);

-- Intento de venta en sesión cerrada 
INSERT INTO notas_ventas (id_cliente, id_usuario_vendedor, id_sesion_caja, total_venta, descuento_total, metodo_pago, estado)
VALUES (1, 2, 1, 300.00, 0.00, 'Efectivo', 'Completada');

-- Intento de eliminación maliciosa de un ítem vendido
DELETE FROM detalles_ventas 
WHERE id_detalle_venta = 1;

-- Intento de doble apertura de caja
INSERT INTO sesiones_cajas (id_usuario_cajero, monto_inicial, estado)
VALUES (1, 500.00, 'Abierta');

--================================================================================================
SELECT sp_registrar_venta(
    2,                                             -- id_cliente (Taller Mecánico El Pistón)
    2,                                             -- id_usuario_vendedor (Jorge Justiniano)
    2,                                             -- id_sesion_caja (Sesión 2 activa)
    'Transferencia/QR'::VARCHAR,                   -- metodo_pago
    20.00::NUMERIC,                                -- p_descuento_total (Bs. 20 de rebaja)
    '[
        {"id_producto": 15, "cantidad": 2, "precio": 75.00},
        {"id_producto": 17, "cantidad": 1, "precio": 55.00}
    ]'::JSONB                                      -- Carrito en JSON: 2 bujías (150) + 1 filtro (55) = 205 - 20 = 185 Bs.
);

--Revisar la cabecera y el detalle generados:
SELECT 
    v.id_venta,
    v.total_venta,
    v.descuento_total,
    v.metodo_pago,
    dv.id_producto,
    p.nombre_pieza,
    dv.cantidad_vendida,
    dv.subtotal
FROM notas_ventas v
JOIN detalles_ventas dv ON v.id_venta = dv.id_venta
JOIN productos p ON dv.id_producto = p.id_producto
WHERE v.id_venta = (SELECT MAX(id_venta) FROM notas_ventas);

--Revisar que la caja acumuló el cobro automáticamente:
SELECT id_sesion, monto_inicial, monto_esperado_sistema, estado 
FROM sesiones_cajas 
WHERE id_sesion = 2;

--================================================================================
-- 1. Consultar estado y monto de la venta 12
SELECT id_venta, total_venta, estado, motivo_anulacion 
FROM notas_ventas 
WHERE id_venta = 12;

-- 2. Consultar el stock actual de las piezas involucradas (Bujía 15 y Filtro 17)
SELECT id_producto, nombre_pieza, stock_actual 
FROM productos 
WHERE id_producto IN (15, 17);

-- 3. Consultar el dinero esperado en la caja 2
SELECT id_sesion, monto_esperado_sistema, estado 
FROM sesiones_cajas 
WHERE id_sesion = 2;

CALL sp_anular_venta(
    12,                                                                     -- p_id_venta
    2,                                                                      -- p_id_usuario (Jorge Justiniano)
    'Devolución total: cliente se equivocó de código de bujía en mostrador'  -- p_motivo_anulacion
);

-- 1. La venta ahora debe figurar como 'Anulada' con su justificación guardada
SELECT id_venta, total_venta, estado, motivo_anulacion 
FROM notas_ventas 
WHERE id_venta = 12;

-- 2. El inventario se restituyó automáticamente (Trigger 4)
-- El producto 15 aumentó en +2 y el producto 17 aumentó en +1
SELECT id_producto, nombre_pieza, stock_actual 
FROM productos 
WHERE id_producto IN (15, 17);

-- 3. El saldo en caja se redujo exactamente en Bs. 185.00 (Trigger de anulación)
SELECT id_sesion, monto_esperado_sistema, estado 
FROM sesiones_cajas 
WHERE id_sesion = 2;

-- 4. Se generó la traza inmutable en la bitácora de auditoría en formato JSON (Trigger 7)
SELECT id_bitacora, id_usuario, fecha_hora, modulo_afectado, accion, detalle_cambio 
FROM bitacoras_auditorias 
ORDER BY id_bitacora DESC 
LIMIT 1;

select * from notas_ventas

select * from detalles_ventas
--==================================================
--ID del ajuste, fecha y hora exacta del registro.
SELECT 
    ai.id_ajuste,
    ai.fecha_ajuste::TIMESTAMP(0) AS fecha_hora,
    u.nombre_completo AS responsable,
    p.codigo_interno,
    p.codigo_oem,
    p.nombre_pieza,
    c.nombre AS categoria,
    ai.tipo_ajuste,
    CASE 
        WHEN ai.cantidad_ajustada > 0 THEN CONCAT('+', ai.cantidad_ajustada)
        ELSE ai.cantidad_ajustada::TEXT
    END AS unidades_ajustadas,
    p.stock_actual AS stock_disponible_actual,
    ai.motivo
FROM ajustes_inventario ai
JOIN productos p ON ai.id_producto = p.id_producto
JOIN categorias c ON p.id_categoria = c.id_categoria
JOIN usuarios u ON ai.id_usuario = u.id_usuario
ORDER BY ai.fecha_ajuste DESC;

-- Ejemplo: Se retira 1 juego de pastillas de freno (ID 1) para el auto del reparto/empresa
INSERT INTO ajustes_inventario (
    id_producto, 
    id_usuario, 
    cantidad_ajustada, 
    tipo_ajuste, 
    motivo
) VALUES (
    3, 
    1, -- ID del Administrador / Don Carlos
    -1, 
    'Merma', 
    'Autoconsumo: Mantenimiento correctivo de frenos de la vagoneta de reparto de la tienda'
);

select * from  productos
select * from ajuste_inventario