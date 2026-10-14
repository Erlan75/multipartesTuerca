-- =============================================================================
-- TRIGGERS Y FUNCIONES DE INTEGRIDAD TRANSACCIONAL
-- PROYECTO: MULTIPARTES EL TUERCA
-- ARQUITECTURA: Smart-DB (PostgreSQL)
-- =============================================================================

-- =============================================================================
-- 1. TRIGGER: VALIDACIÓN Y DESCUENTO AUTOMÁTICO DE STOCK EN VENTAS
-- Tabla: detalles_ventas
-- Evento: BEFORE INSERT
-- Función: Bloquea la fila del producto (evita race conditions), valida que 
--          haya existencias y descuenta el stock en la misma transacción.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_descontar_stock_venta()
RETURNS TRIGGER AS $$
DECLARE
    v_stock_actual INT;
    v_nombre_pieza VARCHAR(150);
    v_estado_prod BOOLEAN;
BEGIN
    -- Bloqueo pesimista de fila en la tabla maestra
    SELECT stock_actual, nombre_pieza, estado
    INTO v_stock_actual, v_nombre_pieza, v_estado_prod
    FROM productos
    WHERE id_producto = NEW.id_producto
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: El producto ID % no existe en catálogo.', NEW.id_producto;
    END IF;

    IF v_estado_prod IS FALSE THEN
        RAISE EXCEPTION 'Operación rechazada: El repuesto "%" se encuentra descontinuado o inactivo.', v_nombre_pieza;
    END IF;

    IF v_stock_actual < NEW.cantidad_vendida THEN
        RAISE EXCEPTION 'Stock insuficiente para "%": solicitado %, en almacén %.', 
            v_nombre_pieza, NEW.cantidad_vendida, v_stock_actual;
    END IF;

    -- Descuento directo del inventario
    UPDATE productos
    SET stock_actual = stock_actual - NEW.cantidad_vendida
    WHERE id_producto = NEW.id_producto;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_descontar_stock_venta ON detalles_ventas;
CREATE TRIGGER trg_descontar_stock_venta
BEFORE INSERT ON detalles_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_descontar_stock_venta();


-- =============================================================================
-- 2. TRIGGER: INCREMENTO AUTOMÁTICO DE STOCK POR COMPRA A PROVEEDOR
-- Tabla: detalles_compras
-- Evento: AFTER INSERT
-- Función: Suma de inmediato las unidades al almacén al registrar la orden.
-- Ciclo 2: Lo dispara CALL sp_registrar_compra(...) (CU20). Es el ÚNICO punto
--          que incrementa stock por compras; el procedimiento no toca el stock.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_incrementar_stock_compra()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE productos
    SET stock_actual = stock_actual + NEW.cantidad_recibida
    WHERE id_producto = NEW.id_producto;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_incrementar_stock_compra ON detalles_compras;
CREATE TRIGGER trg_incrementar_stock_compra
AFTER INSERT ON detalles_compras
FOR EACH ROW
EXECUTE FUNCTION fn_trg_incrementar_stock_compra();


-- =============================================================================
-- 3. TRIGGER: VERIFICACIÓN ESTRICTA DE CAJA ABIERTA PARA VENTAS
-- Tabla: notas_ventas
-- Evento: BEFORE INSERT
-- Función: Impide registrar cobros si la sesión de caja está cerrada o no existe.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_validar_caja_abierta()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_caja VARCHAR(20);
BEGIN
    SELECT estado INTO v_estado_caja
    FROM sesiones_cajas
    WHERE id_sesion = NEW.id_sesion_caja;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: La sesión de caja % no existe.', NEW.id_sesion_caja;
    END IF;

    IF v_estado_caja <> 'Abierta' THEN
        RAISE EXCEPTION 'Operación rechazada: La caja % se encuentra en estado "%". Debe abrir una sesión.', 
            NEW.id_sesion_caja, v_estado_caja;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validar_caja_abierta ON notas_ventas;
CREATE TRIGGER trg_validar_caja_abierta
BEFORE INSERT ON notas_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_validar_caja_abierta();


-- =============================================================================
-- 4. TRIGGER: RESTITUCIÓN DE STOCK AL ANULAR UNA NOTA DE VENTA
-- Tabla: notas_ventas
-- Evento: AFTER UPDATE OF estado
-- Función: Si la venta pasa a "Anulada", devuelve automáticamente cada ítem al almacén.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_restituir_stock_anulacion()
RETURNS TRIGGER AS $$
DECLARE
    r_item RECORD;
BEGIN
    IF OLD.estado = 'Completada' AND NEW.estado = 'Anulada' THEN
        FOR r_item IN 
            SELECT id_producto, cantidad_vendida 
            FROM detalles_ventas 
            WHERE id_venta = OLD.id_venta
        LOOP
            UPDATE productos
            SET stock_actual = stock_actual + r_item.cantidad_vendida
            WHERE id_producto = r_item.id_producto;
        END LOOP;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_restituir_stock_anulacion ON notas_ventas;
CREATE TRIGGER trg_restituir_stock_anulacion
AFTER UPDATE OF estado ON notas_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_restituir_stock_anulacion();


-- =============================================================================
-- 5. TRIGGER: APLICACIÓN DIRECTA DE AJUSTES MANUALES DE INVENTARIO
-- Tabla: ajustes_inventario
-- Evento: AFTER INSERT
-- Función: Suma o resta stock según el motivo (merma, conteo físico, etc.).
-- Ciclo 2: Lo dispara CALL sp_registrar_ajuste_inventario(...) (CU17 / CU18),
--          que inserta la cantidad ya firmada (negativa para Merma, Pérdida/Extravío,
--          Devolución Dañada y Uso Interno; diferencia contado-sistema en Conteo Físico).
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_aplicar_ajuste_inventario()
RETURNS TRIGGER AS $$
DECLARE
    v_stock_actual INT;
BEGIN
    SELECT stock_actual INTO v_stock_actual
    FROM productos
    WHERE id_producto = NEW.id_producto
    FOR UPDATE;

    IF (v_stock_actual + NEW.cantidad_ajustada) < 0 THEN
        RAISE EXCEPTION 'Ajuste denegado: El ajuste de % dejaría el stock en negativo (Actual: %).', 
            NEW.cantidad_ajustada, v_stock_actual;
    END IF;

    UPDATE productos
    SET stock_actual = stock_actual + NEW.cantidad_ajustada
    WHERE id_producto = NEW.id_producto;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_aplicar_ajuste_inventario ON ajustes_inventario;
CREATE TRIGGER trg_aplicar_ajuste_inventario
AFTER INSERT ON ajustes_inventario
FOR EACH ROW
EXECUTE FUNCTION fn_trg_aplicar_ajuste_inventario();


-- =============================================================================
-- 6. TRIGGER: AUDITORÍA AUTOMÁTICA EN MODIFICACIÓN DE PRECIOS Y ESTADO
-- Tabla: productos
-- Evento: AFTER UPDATE
-- Función: Registra en bitacoras_auditorias cualquier alteración económica o de catálogo.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_auditar_cambios_producto()
RETURNS TRIGGER AS $$
BEGIN
    IF (OLD.precio_venta_sugerido <> NEW.precio_venta_sugerido OR OLD.estado <> NEW.estado) THEN
        INSERT INTO bitacoras_auditorias (
            id_usuario,
            modulo_afectado,
            accion,
            registro_id,
            detalle_cambio,
            ip_origen
        ) VALUES (
            1, -- Asignado al usuario administrador raíz por defecto si es directo en BD
            'Catálogo / Productos',
            'UPDATE',
            NEW.id_producto,
            json_build_object(
                'producto', NEW.nombre_pieza,
                'codigo_oem', NEW.codigo_oem,
                'precio_anterior', OLD.precio_venta_sugerido,
                'precio_nuevo', NEW.precio_venta_sugerido,
                'estado_anterior', OLD.estado,
                'estado_nuevo', NEW.estado
            )::TEXT,
            '127.0.0.1'
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_auditar_cambios_producto ON productos;
CREATE TRIGGER trg_auditar_cambios_producto
AFTER UPDATE ON productos
FOR EACH ROW
EXECUTE FUNCTION fn_trg_auditar_cambios_producto();


-- =============================================================================
-- 7. TRIGGER: AUDITORÍA AUTOMÁTICA EN ANULACIÓN DE VENTAS
-- Tabla: notas_ventas
-- Evento: AFTER UPDATE OF estado
-- Función: Deja registro inmutable cuando una venta pasa a estado Anulada.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_auditar_anulacion_venta()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado = 'Completada' AND NEW.estado = 'Anulada' THEN
        INSERT INTO bitacoras_auditorias (
            id_usuario,
            modulo_afectado,
            accion,
            registro_id,
            detalle_cambio,
            ip_origen
        ) VALUES (
            NEW.id_usuario_vendedor,
            'Ventas / POS',
            'ANULACION',
            NEW.id_venta,
            json_build_object(
                'total_venta', NEW.total_venta,
                'metodo_pago', NEW.metodo_pago,
                'motivo_anulacion', NEW.motivo_anulacion,
                'fecha_anulacion', CURRENT_TIMESTAMP
            )::TEXT,
            '127.0.0.1'
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_auditar_anulacion_venta ON notas_ventas;
CREATE TRIGGER trg_auditar_anulacion_venta
AFTER UPDATE OF estado ON notas_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_auditar_anulacion_venta();
-- =============================================================================
-- 8. TRIGGER: CONTROL DE CAJA ÚNICA POR USUARIO
-- Tabla: sesiones_cajas
-- Evento: BEFORE INSERT
-- Función: Impide que un mismo cajero tenga más de una sesión 'Abierta' simultánea.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_validar_sesion_unica_cajero()
RETURNS TRIGGER AS $$
DECLARE
    v_cajas_abiertas INT;
BEGIN
    SELECT COUNT(*) INTO v_cajas_abiertas
    FROM sesiones_cajas
    WHERE id_usuario_cajero = NEW.id_usuario_cajero
      AND estado = 'Abierta';

    IF v_cajas_abiertas > 0 THEN
        RAISE EXCEPTION 'Regla Smart-DB: El cajero % ya cuenta con una sesión de caja abierta. Debe cerrarla antes de aperturar una nueva.', 
            NEW.id_usuario_cajero;
    END IF;

    -- Si se abre correctamente, el monto esperado inicial es el fondo de cambio
    NEW.monto_esperado_sistema := NEW.monto_inicial;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validar_sesion_unica_cajero ON sesiones_cajas;
CREATE TRIGGER trg_validar_sesion_unica_cajero
BEFORE INSERT ON sesiones_cajas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_validar_sesion_unica_cajero();


-- =============================================================================
-- 9. TRIGGER: ACUMULADOR FINANCIERO EN TIEMPO REAL PARA CAJA (VENTAS Y MOVIMIENTOS)
-- Tablas: notas_ventas, movimientos_cajas
-- Evento: AFTER INSERT / AFTER UPDATE
-- Función: Actualiza monto_esperado_sistema con cada cobro o egreso menor.
-- =============================================================================

-- 9.1. Impacto de Ventas en la Caja
CREATE OR REPLACE FUNCTION fn_trg_acumular_venta_caja()
RETURNS TRIGGER AS $$
BEGIN
    -- Solo impacta en caja si la venta está completada
    IF NEW.estado = 'Completada' THEN
        UPDATE sesiones_cajas
        SET monto_esperado_sistema = monto_esperado_sistema + NEW.total_venta
        WHERE id_sesion = NEW.id_sesion_caja;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_acumular_venta_caja ON notas_ventas;
CREATE TRIGGER trg_acumular_venta_caja
AFTER INSERT ON notas_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_acumular_venta_caja();

-- 9.2. Impacto de Movimientos Menores (Retiros e Ingresos Extra)
CREATE OR REPLACE FUNCTION fn_trg_acumular_movimiento_caja()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.tipo = 'Ingreso Extra' THEN
        UPDATE sesiones_cajas
        SET monto_esperado_sistema = monto_esperado_sistema + NEW.monto
        WHERE id_sesion = NEW.id_sesion;
    ELSIF NEW.tipo = 'Retiro Menor' THEN
        UPDATE sesiones_cajas
        SET monto_esperado_sistema = monto_esperado_sistema - NEW.monto
        WHERE id_sesion = NEW.id_sesion;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_acumular_movimiento_caja ON movimientos_cajas;
CREATE TRIGGER trg_acumular_movimiento_caja
AFTER INSERT ON movimientos_cajas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_acumular_movimiento_caja();


-- =============================================================================
-- 10. TRIGGER: CÁLCULO DE ARQUEO Y CIERRE AUTOMÁTICO DE CAJA
-- Tabla: sesiones_cajas
-- Evento: BEFORE UPDATE OF monto_real_contado, estado
-- Función: Al cerrar la sesión, calcula diferencia = monto_real_contado - monto_esperado_sistema
--          y clasifica el estado en 'Cuadrada' o 'Con Diferencia'.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_conciliar_cierre_caja()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado = 'Cerrada' AND OLD.estado = 'Abierta' THEN
        IF NEW.monto_real_contado IS NULL THEN
            RAISE EXCEPTION 'Regla Smart-DB: Debe especificar el arqueo físico (monto_real_contado) para cerrar la sesión.';
        END IF;

        NEW.fecha_hora_cierre := CURRENT_TIMESTAMP;
        NEW.diferencia := NEW.monto_real_contado - NEW.monto_esperado_sistema;

        IF NEW.diferencia = 0.00 THEN
            NEW.estado := 'Cuadrada';
        ELSE
            NEW.estado := 'Con Diferencia';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_conciliar_cierre_caja ON sesiones_cajas;
CREATE TRIGGER trg_conciliar_cierre_caja
BEFORE UPDATE ON sesiones_cajas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_conciliar_cierre_caja();


-- =============================================================================
-- 11. TRIGGER: INMUTABILIDAD TRANSACCIONAL (ANTI-MANIPULACIÓN)
-- Tabla: detalles_ventas
-- Evento: BEFORE UPDATE OR DELETE
-- Función: Prohíbe alterar o borrar renglones de una venta ya concretada.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_proteger_detalles_venta()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_venta VARCHAR(30);
BEGIN
    SELECT estado INTO v_estado_venta
    FROM notas_ventas
    WHERE id_venta = COALESCE(OLD.id_venta, NEW.id_venta);

    IF v_estado_venta = 'Completada' THEN
        RAISE EXCEPTION 'Violación de Seguridad Smart-DB: Las líneas de una venta completada son inmutables. Debe anular la venta formalmente.';
    END IF;

    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_proteger_detalles_venta ON detalles_ventas;
CREATE TRIGGER trg_proteger_detalles_venta
BEFORE UPDATE OR DELETE ON detalles_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_proteger_detalles_venta();


-- =============================================================================
-- 12. TRIGGER: VIGENCIA ESTRICTA EN COTIZACIONES
-- Tabla: cotizaciones
-- Evento: BEFORE UPDATE OF estado
-- Función: Impide pasar a 'Concretada/Facturada' una cotización vencida por fecha.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_validar_vigencia_cotizacion()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado = 'Concretada/Facturada' AND OLD.estado = 'Vigente' THEN
        IF CURRENT_DATE > OLD.fecha_vencimiento THEN
            NEW.estado := 'Vencida';
            RAISE EXCEPTION 'Regla Smart-DB: La cotización expiró el %. No se puede convertir en venta.', OLD.fecha_vencimiento;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validar_vigencia_cotizacion ON cotizaciones;
CREATE TRIGGER trg_validar_vigencia_cotizacion
BEFORE UPDATE OF estado ON cotizaciones
FOR EACH ROW
EXECUTE FUNCTION fn_trg_validar_vigencia_cotizacion();
-- =============================================================================
-- 13. TRIGGER: DESCUENTO DE CAJA POR VENTA ANULADA
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_trg_descontar_caja_anulacion()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado = 'Completada' AND NEW.estado = 'Anulada' THEN
        UPDATE sesiones_cajas
        SET monto_esperado_sistema = monto_esperado_sistema - OLD.total_venta
        WHERE id_sesion = OLD.id_sesion_caja;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_descontar_caja_anulacion ON notas_ventas;
CREATE TRIGGER trg_descontar_caja_anulacion
AFTER UPDATE OF estado ON notas_ventas
FOR EACH ROW
EXECUTE FUNCTION fn_trg_descontar_caja_anulacion();
