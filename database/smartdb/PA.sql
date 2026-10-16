-- =============================================================================
-- PROCEDIMIENTOS ALMACENADOS Y FUNCIONES TRANSACCIONALES
-- PROYECTO: MULTIPARTES EL TUERCA
-- ARQUITECTURA: Smart-DB (PostgreSQL)
-- =============================================================================

-- =============================================================================
-- 1. FUNCIÓN: REGISTRO INTEGRAL DE VENTA ATÓMICA
-- Nombre: sp_registrar_venta
-- Retorna: INT (ID de la venta generada)
-- ¿Qué hace?:
--   - Recibe el cliente, cajero/vendedor, sesión de caja y un array JSON con los ítems.
--   - Valida que el carrito contenga al menos un producto.
--   - Calcula la sumatoria de subtotales y deduce el descuento global.
--   - Inserta la cabecera en 'notas_ventas' (disparando el Trigger 3 de validación 
--     de caja abierta y el Trigger 9.1 de acumulación de efectivo en caja).
--   - Itera e inserta cada línea en 'detalles_ventas' (disparando el Trigger 1 de 
--     bloqueo FOR UPDATE, validación de stock y descuento físico en almacén).
-- =============================================================================
CREATE OR REPLACE FUNCTION sp_registrar_venta(
    p_id_cliente INT,
    p_id_usuario_vendedor INT,
    p_id_sesion_caja INT,
    p_metodo_pago VARCHAR(30),
    p_descuento_total NUMERIC(12, 2),
    p_items JSONB -- Formato esperado: '[{"id_producto": 1, "cantidad": 2, "precio": 150.00}]'
)
RETURNS INT AS $$
DECLARE
    v_id_venta INT;
    v_total_bruto NUMERIC(12, 2) := 0.00;
    v_total_neto NUMERIC(12, 2) := 0.00;
    v_item JSONB;
    v_subtotal NUMERIC(12, 2);
BEGIN
    -- Validación preventiva: no permitir ventas vacías
    IF p_items IS NULL OR jsonb_array_length(p_items) = 0 THEN
        RAISE EXCEPTION 'Regla Smart-DB: No se pueden registrar ventas sin productos en el carrito.';
    END IF;

    -- Cálculo acumulativo de subtotales
    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        v_subtotal := (v_item->>'cantidad')::INT * (v_item->>'precio')::NUMERIC(12, 2);
        v_total_bruto := v_total_bruto + v_subtotal;
    END LOOP;

    -- Aplicación y control de descuento comercial
    v_total_neto := v_total_bruto - COALESCE(p_descuento_total, 0.00);
    IF v_total_neto < 0 THEN
        RAISE EXCEPTION 'Regla Smart-DB: El descuento (Bs. %) no puede ser mayor al importe bruto (Bs. %).', 
            p_descuento_total, v_total_bruto;
    END IF;

    -- Inserción de cabecera:
    -- Dispara fn_trg_validar_caja_abierta() y fn_trg_acumular_venta_caja()
    INSERT INTO notas_ventas (
        id_cliente,
        id_usuario_vendedor,
        id_sesion_caja,
        total_venta,
        descuento_total,
        metodo_pago,
        estado
    ) VALUES (
        p_id_cliente,
        p_id_usuario_vendedor,
        p_id_sesion_caja,
        v_total_neto,
        COALESCE(p_descuento_total, 0.00),
        p_metodo_pago,
        'Completada'
    ) RETURNING id_venta INTO v_id_venta;

    -- Inserción de renglones:
    -- Cada INSERT dispara fn_trg_descontar_stock_venta() con bloqueo pesimista
    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        INSERT INTO detalles_ventas (
            id_venta,
            id_producto,
            cantidad_vendida,
            precio_unitario_cobrado,
            subtotal
        ) VALUES (
            v_id_venta,
            (v_item->>'id_producto')::INT,
            (v_item->>'cantidad')::INT,
            (v_item->>'precio')::NUMERIC(12, 2),
            ((v_item->>'cantidad')::INT * (v_item->>'precio')::NUMERIC(12, 2))
        );
    END LOOP;

    RETURN v_id_venta;
END;
$$ LANGUAGE plpgsql;


-- =============================================================================
-- AUXILIAR: VALIDACIÓN DE USUARIO ACTIVO Y AUTORIZADO POR ROL
-- Nombre: fn_validar_usuario_autorizado
-- Retorna: VARCHAR (nombre del rol del usuario validado)
-- ¿Qué hace?:
--   - Verifica que el usuario exista, esté activo y que su rol figure en la lista
--     de roles permitidos. Si no, lanza una excepción y aborta la transacción.
--   - La reutilizan los procedimientos transaccionales del Ciclo 2 como blindaje
--     de autorización dentro del propio motor.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_validar_usuario_autorizado(
    p_id_usuario INT,
    p_roles_permitidos TEXT[]
)
RETURNS VARCHAR AS $$
DECLARE
    v_estado BOOLEAN;
    v_rol VARCHAR(50);
BEGIN
    IF p_id_usuario IS NULL THEN
        RAISE EXCEPTION 'Regla Smart-DB: Debe indicarse el usuario responsable de la operación.';
    END IF;

    SELECT u.estado, r.nombre_rol
    INTO v_estado, v_rol
    FROM usuarios u
    JOIN roles r ON r.id_rol = u.id_rol
    WHERE u.id_usuario = p_id_usuario;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: El usuario con ID % no existe.', p_id_usuario;
    END IF;

    IF v_estado IS NOT TRUE THEN
        RAISE EXCEPTION 'Operación rechazada: El usuario con ID % se encuentra inactivo.', p_id_usuario;
    END IF;

    IF NOT (v_rol = ANY (p_roles_permitidos)) THEN
        RAISE EXCEPTION 'Acceso denegado Smart-DB: El rol "%" no está autorizado para esta operación (permitidos: %).',
            v_rol, array_to_string(p_roles_permitidos, ', ');
    END IF;

    RETURN v_rol;
END;
$$ LANGUAGE plpgsql;


-- =============================================================================
-- 2. PROCEDIMIENTO: RECEPCIÓN DE COMPRA Y ENTRADA A ALMACÉN (CU20 / CU21)
-- Nombre: sp_registrar_compra
-- Invocación (Laravel):
--   DB::select('CALL sp_registrar_compra(?, ?, ?, ?, ?, ?::jsonb, NULL)', [...])
--   -> devuelve una fila con p_id_compra (ID de la nota de compra generada).
-- ¿Qué hace?:
--   - Valida que el receptor sea un usuario activo con rol Administrador o Bodeguero.
--   - Valida que el proveedor exista y esté ACTIVO (proveedores.estado).
--   - Acepta tipo_pago 'Contado' o 'Crédito' y DERIVA el estado de pago:
--       Contado -> 'Pagado'   |   Crédito -> 'Pendiente' (queda como cuenta por pagar).
--   - Impide registrar dos veces la misma factura para el mismo proveedor
--     (con bloqueo consultivo para evitar condiciones de carrera).
--   - Valida cada ítem (producto existente y activo, cantidad > 0, costo > 0).
--   - Inserta la cabecera en 'notas_compras' y las líneas en 'detalles_compras'.
--     Cada línea dispara el Trigger 2 (fn_trg_incrementar_stock_compra), que suma
--     las unidades a productos.stock_actual. En 'productos' SOLO cambia el stock.
--   - El costo de adquisición queda registrado en detalles_compras.costo_unitario,
--     que es la fuente oficial del historial de costos (CU21).
--   - Registra el evento en 'bitacoras_auditorias'.
-- =============================================================================
DROP FUNCTION IF EXISTS sp_registrar_compra(INT, INT, VARCHAR, DATE, VARCHAR, VARCHAR, JSONB);

CREATE OR REPLACE PROCEDURE sp_registrar_compra(
    p_id_proveedor INT,
    p_id_usuario_receptor INT,
    p_numero_factura_nota VARCHAR(60),
    p_fecha_compra DATE,
    p_tipo_pago VARCHAR(30),
    p_items JSONB, -- Formato esperado: '[{"id_producto": 1, "cantidad": 10, "costo": 95.50}]'
    INOUT p_id_compra INT DEFAULT NULL
)
AS $$
DECLARE
    v_prov_razon VARCHAR(120);
    v_prov_estado BOOLEAN;
    v_estado_pago VARCHAR(30);
    v_total_compra NUMERIC(12, 2) := 0.00;
    v_item JSONB;
    v_nro_item INT := 0;
    v_id_producto INT;
    v_cantidad INT;
    v_costo NUMERIC(12, 2);
    v_prod_nombre VARCHAR(150);
    v_prod_estado BOOLEAN;
BEGIN
    -- 1. Usuario receptor autorizado
    PERFORM fn_validar_usuario_autorizado(p_id_usuario_receptor, ARRAY['Administrador', 'Bodeguero']);

    -- 2. Proveedor existente y activo
    SELECT razon_social, estado
    INTO v_prov_razon, v_prov_estado
    FROM proveedores
    WHERE id_proveedor = p_id_proveedor;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: El proveedor con ID % no existe.', p_id_proveedor;
    END IF;

    IF v_prov_estado IS FALSE THEN
        RAISE EXCEPTION 'Operación rechazada: El proveedor "%" se encuentra inactivo y no admite nuevas compras.', v_prov_razon;
    END IF;

    -- 3. Política de pago: Contado -> Pagado | Crédito -> Pendiente
    IF p_tipo_pago = 'Contado' THEN
        v_estado_pago := 'Pagado';
    ELSIF p_tipo_pago = 'Crédito' THEN
        v_estado_pago := 'Pendiente';
    ELSE
        RAISE EXCEPTION 'Regla Smart-DB: Tipo de pago "%" no válido. Valores permitidos: Contado, Crédito.', p_tipo_pago;
    END IF;

    -- 4. Documento físico
    IF p_numero_factura_nota IS NULL OR length(trim(p_numero_factura_nota)) < 2 THEN
        RAISE EXCEPTION 'Regla Smart-DB: El número de factura o nota de entrega es obligatorio (mínimo 2 caracteres).';
    END IF;

    IF p_fecha_compra IS NULL OR p_fecha_compra > CURRENT_DATE THEN
        RAISE EXCEPTION 'Regla Smart-DB: La fecha de compra es obligatoria y no puede ser futura (recibida: %).', p_fecha_compra;
    END IF;

    -- Serializa recepciones concurrentes del mismo documento del mismo proveedor
    PERFORM pg_advisory_xact_lock(p_id_proveedor, hashtext(lower(trim(p_numero_factura_nota))));

    IF EXISTS (
        SELECT 1
        FROM notas_compras
        WHERE id_proveedor = p_id_proveedor
          AND lower(trim(numero_factura_nota)) = lower(trim(p_numero_factura_nota))
    ) THEN
        RAISE EXCEPTION 'Operación rechazada: La factura "%" del proveedor "%" ya fue registrada.',
            trim(p_numero_factura_nota), v_prov_razon;
    END IF;

    -- 5. Validación de ítems y cálculo del total
    IF p_items IS NULL OR jsonb_typeof(p_items) <> 'array' OR jsonb_array_length(p_items) = 0 THEN
        RAISE EXCEPTION 'Regla Smart-DB: No se puede asentar una compra sin repuestos asociados.';
    END IF;

    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        v_nro_item := v_nro_item + 1;
        v_id_producto := (v_item->>'id_producto')::INT;
        v_cantidad := (v_item->>'cantidad')::INT;
        v_costo := (v_item->>'costo')::NUMERIC(12, 2);

        IF v_id_producto IS NULL OR v_cantidad IS NULL OR v_costo IS NULL THEN
            RAISE EXCEPTION 'Regla Smart-DB: El ítem #% está incompleto (requiere id_producto, cantidad y costo).', v_nro_item;
        END IF;

        SELECT nombre_pieza, estado
        INTO v_prod_nombre, v_prod_estado
        FROM productos
        WHERE id_producto = v_id_producto;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Error Smart-DB: El producto ID % (ítem #%) no existe en catálogo.', v_id_producto, v_nro_item;
        END IF;

        IF v_prod_estado IS FALSE THEN
            RAISE EXCEPTION 'Operación rechazada: El repuesto "%" (ítem #%) está descontinuado o inactivo.', v_prod_nombre, v_nro_item;
        END IF;

        IF v_cantidad <= 0 THEN
            RAISE EXCEPTION 'Regla Smart-DB: La cantidad recibida de "%" debe ser mayor a 0 (recibido: %).', v_prod_nombre, v_cantidad;
        END IF;

        IF v_costo <= 0 THEN
            RAISE EXCEPTION 'Regla Smart-DB: El costo unitario de "%" debe ser mayor a 0 (recibido: %).', v_prod_nombre, v_costo;
        END IF;

        v_total_compra := v_total_compra + (v_cantidad * v_costo);
    END LOOP;

    -- 6. Cabecera de la nota de compra
    INSERT INTO notas_compras (
        id_proveedor,
        id_usuario_receptor,
        numero_factura_nota,
        fecha_compra,
        monto_total,
        tipo_pago,
        estado_pago
    ) VALUES (
        p_id_proveedor,
        p_id_usuario_receptor,
        trim(p_numero_factura_nota),
        p_fecha_compra,
        v_total_compra,
        p_tipo_pago,
        v_estado_pago
    ) RETURNING id_compra INTO p_id_compra;

    -- 7. Detalles: cada fila dispara fn_trg_incrementar_stock_compra() (+stock_actual)
    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        INSERT INTO detalles_compras (
            id_compra,
            id_producto,
            cantidad_recibida,
            costo_unitario,
            subtotal
        ) VALUES (
            p_id_compra,
            (v_item->>'id_producto')::INT,
            (v_item->>'cantidad')::INT,
            (v_item->>'costo')::NUMERIC(12, 2),
            ((v_item->>'cantidad')::INT * (v_item->>'costo')::NUMERIC(12, 2))
        );
    END LOOP;

    -- 8. Auditoría transaccional
    INSERT INTO bitacoras_auditorias (
        id_usuario,
        modulo_afectado,
        accion,
        registro_id,
        detalle_cambio,
        ip_origen
    ) VALUES (
        p_id_usuario_receptor,
        'Compras / Recepción',
        'INSERT',
        p_id_compra,
        json_build_object(
            'proveedor', v_prov_razon,
            'numero_factura', trim(p_numero_factura_nota),
            'fecha_compra', p_fecha_compra,
            'tipo_pago', p_tipo_pago,
            'estado_pago', v_estado_pago,
            'monto_total', v_total_compra,
            'cantidad_items', v_nro_item
        )::TEXT,
        COALESCE(NULLIF(current_setting('app.ip_origen', true), ''), '127.0.0.1')
    );
END;
$$ LANGUAGE plpgsql;


-- =============================================================================
-- 3. PROCEDIMIENTO: ANULACIÓN CONTROLADA DE VENTA
-- Nombre: sp_anular_venta
-- ¿Qué hace?:
--   - Valida la existencia de la venta y que no haya sido anulada previamente.
--   - Exige una justificación textual descriptiva (mínimo 5 letras).
--   - Actualiza el estado a 'Anulada' (desencadena en cascada el Trigger 4 para devolver
--     las piezas al stock, el Trigger de descuento de caja y el Trigger 7 de auditoría).
-- =============================================================================
CREATE OR REPLACE PROCEDURE sp_anular_venta(
    p_id_venta INT,
    p_id_usuario INT,
    p_motivo_anulacion TEXT
)
AS $$
DECLARE
    v_estado_actual VARCHAR(30);
BEGIN
    IF length(trim(p_motivo_anulacion)) < 5 THEN
        RAISE EXCEPTION 'Regla Smart-DB: La justificación de anulación es obligatoria (mínimo 5 caracteres).';
    END IF;

    SELECT estado INTO v_estado_actual
    FROM notas_ventas
    WHERE id_venta = p_id_venta;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: La venta con ID % no existe.', p_id_venta;
    END IF;

    IF v_estado_actual = 'Anulada' THEN
        RAISE EXCEPTION 'Operación rechazada: La venta con ID % ya se encuentra anulada.', p_id_venta;
    END IF;

    -- Esta modificación activa en cadena:
    -- 1. fn_trg_restituir_stock_anulacion() -> Repone unidades a 'productos'
    -- 2. fn_trg_descontar_caja_anulacion() -> Deduce el total de 'sesiones_cajas'
    -- 3. fn_trg_auditar_anulacion_venta() -> Inserta traza en 'bitacoras_auditorias'
    UPDATE notas_ventas
    SET estado = 'Anulada',
        motivo_anulacion = trim(p_motivo_anulacion)
    WHERE id_venta = p_id_venta;
END;
$$ LANGUAGE plpgsql;


-- =============================================================================
-- 4. FUNCIÓN: CONVERSIÓN DE COTIZACIÓN A VENTA DIRECTA
-- Nombre: sp_convertir_cotizacion_a_venta
-- Retorna: INT (ID de la nueva venta creada)
-- ¿Qué hace?:
--   - Localiza el presupuesto formal y verifica que no haya caducado.
--   - Pasa la cotización a 'Concretada/Facturada' (Trigger 12 bloquea si expiró por fecha).
--   - Genera una 'NotaVenta' y copia automáticamente cada línea de 'detalles_cotizaciones'
--     hacia 'detalles_ventas', descontando stock y sumando a caja de forma transparente.
-- =============================================================================
CREATE OR REPLACE FUNCTION sp_convertir_cotizacion_a_venta(
    p_id_cotizacion INT,
    p_id_usuario_vendedor INT,
    p_id_sesion_caja INT,
    p_metodo_pago VARCHAR(30)
)
RETURNS INT AS $$
DECLARE
    v_cotizacion RECORD;
    v_id_venta INT;
    r_item RECORD;
BEGIN
    SELECT * INTO v_cotizacion
    FROM cotizaciones
    WHERE id_cotizacion = p_id_cotizacion;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: La cotización ID % no existe.', p_id_cotizacion;
    END IF;

    IF v_cotizacion.estado <> 'Vigente' THEN
        RAISE EXCEPTION 'Operación denegada: La cotización se encuentra %, no admite facturación.', v_cotizacion.estado;
    END IF;

    -- Cambio de estado (dispara fn_trg_validar_vigencia_cotizacion)
    UPDATE cotizaciones
    SET estado = 'Concretada/Facturada'
    WHERE id_cotizacion = p_id_cotizacion;

    -- Emisión de nota de venta
    INSERT INTO notas_ventas (
        id_cliente,
        id_usuario_vendedor,
        id_sesion_caja,
        total_venta,
        descuento_total,
        metodo_pago,
        estado
    ) VALUES (
        v_cotizacion.id_cliente,
        p_id_usuario_vendedor,
        p_id_sesion_caja,
        v_cotizacion.monto_total,
        0.00,
        p_metodo_pago,
        'Completada'
    ) RETURNING id_venta INTO v_id_venta;

    -- Copia de renglones cotizados a venta efectiva (dispara validación de stock)
    FOR r_item IN 
        SELECT id_producto, cantidad, precio_unitario_cotizado, subtotal
        FROM detalles_cotizaciones
        WHERE id_cotizacion = p_id_cotizacion
    LOOP
        INSERT INTO detalles_ventas (
            id_venta,
            id_producto,
            cantidad_vendida,
            precio_unitario_cobrado,
            subtotal
        ) VALUES (
            v_id_venta,
            r_item.id_producto,
            r_item.cantidad,
            r_item.precio_unitario_cotizado,
            r_item.subtotal
        );
    END LOOP;

    RETURN v_id_venta;
END;
$$ LANGUAGE plpgsql;


-- =============================================================================
-- 5. PROCEDIMIENTO: ARQUEO Y CIERRE DE SESIÓN DE CAJA
-- Nombre: sp_cerrar_caja
-- ¿Qué hace?:
--   - Valida que la sesión exista y permanezca en estado 'Abierta'.
--   - Recibe el arqueo físico (`p_monto_real_contado`).
--   - Actualiza el estado a 'Cerrada' (el Trigger 10 calcula la diferencia contra el
--     dinero esperado y marca automáticamente el resultado: 'Cuadrada' o 'Con Diferencia').
-- =============================================================================
CREATE OR REPLACE PROCEDURE sp_cerrar_caja(
    p_id_sesion INT,
    p_monto_real_contado NUMERIC(12, 2)
)
AS $$
DECLARE
    v_estado VARCHAR(20);
BEGIN
    SELECT estado INTO v_estado
    FROM sesiones_cajas
    WHERE id_sesion = p_id_sesion;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: La sesión de caja con ID % no existe.', p_id_sesion;
    END IF;

    IF v_estado <> 'Abierta' THEN
        RAISE EXCEPTION 'Operación rechazada: La sesión % ya está cerrada (Estado: %).', p_id_sesion, v_estado;
    END IF;

    -- Dispara fn_trg_conciliar_cierre_caja()
    UPDATE sesiones_cajas
    SET monto_real_contado = p_monto_real_contado,
        estado = 'Cerrada'
    WHERE id_sesion = p_id_sesion;
END;
$$ LANGUAGE plpgsql;
-- =============================================================================
-- 6. PROCEDIMIENTO: APERTURA FORMAL DE SESIÓN DE CAJA
-- Nombre: sp_abrir_caja
-- Retorna: INT (ID de la nueva sesión)
-- ¿Qué hace?:
--   - Valida que el monto inicial no sea negativo.
--   - Inserta la sesión con estado 'Abierta'.
--   - El Trigger 8 impide la apertura si el cajero ya tiene una caja abierta.
-- =============================================================================
CREATE OR REPLACE FUNCTION sp_abrir_caja(
    p_id_usuario_cajero INT,
    p_monto_inicial NUMERIC(12, 2)
)
RETURNS INT AS $$
DECLARE
    v_id_sesion INT;
BEGIN
    IF p_monto_inicial < 0.00 THEN
        RAISE EXCEPTION 'Regla Smart-DB: El fondo inicial de caja no puede ser negativo.';
    END IF;

    -- Dispara fn_trg_validar_sesion_unica_cajero()
    INSERT INTO sesiones_cajas (
        id_usuario_cajero,
        monto_inicial,
        estado
    ) VALUES (
        p_id_usuario_cajero,
        p_monto_inicial,
        'Abierta'
    ) RETURNING id_sesion INTO v_id_sesion;

    RETURN v_id_sesion;
END;
$$ LANGUAGE plpgsql;
-- =============================================================================
-- 7. PROCEDIMIENTO: REGISTRO DE MOVIMIENTO MENOR DE CAJA (GASTOS / INGRESOS EXTRA)
-- Nombre: sp_registrar_movimiento_caja
-- Retorna: INT (ID del movimiento)
-- ¿Qué hace?:
--   - Valida que la caja esté abierta y que no se retire más dinero del disponible.
--   - Inserta el movimiento y dispara el Trigger 9.2 para actualizar el saldo esperado.
-- =============================================================================
CREATE OR REPLACE FUNCTION sp_registrar_movimiento_caja(
    p_id_sesion INT,
    p_tipo VARCHAR(30),
    p_monto NUMERIC(12, 2),
    p_motivo VARCHAR(255)
)
RETURNS INT AS $$
DECLARE
    v_id_movimiento INT;
    v_estado VARCHAR(20);
    v_saldo_actual NUMERIC(12, 2);
BEGIN
    SELECT estado, monto_esperado_sistema 
    INTO v_estado, v_saldo_actual
    FROM sesiones_cajas
    WHERE id_sesion = p_id_sesion;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Regla Smart-DB: La sesión de caja % no existe.', p_id_sesion;
    END IF;

    IF v_estado <> 'Abierta' THEN
        RAISE EXCEPTION 'Operación rechazada: La caja % está %, no admite movimientos.', p_id_sesion, v_estado;
    END IF;

    IF p_tipo = 'Retiro Menor' AND p_monto > v_saldo_actual THEN
        RAISE EXCEPTION 'Regla Smart-DB: Saldo insuficiente en caja. Disponible: Bs. %, Retiro solicitado: Bs. %', 
            v_saldo_actual, p_monto;
    END IF;

    -- Dispara fn_trg_acumular_movimiento_caja()
    INSERT INTO movimientos_cajas (
        id_sesion,
        tipo,
        monto,
        motivo
    ) VALUES (
        p_id_sesion,
        p_tipo,
        p_monto,
        trim(p_motivo)
    ) RETURNING id_movimiento INTO v_id_movimiento;

    RETURN v_id_movimiento;
END;
$$ LANGUAGE plpgsql;
-- =============================================================================
-- 8. FUNCIÓN: REGISTRO DE COTIZACIÓN FORMAL CON ÍTEMS EN JSON
-- Nombre: sp_registrar_cotizacion
-- Retorna: INT (ID de la cotización generada)
-- ¿Qué hace?:
--   - Recibe cliente, vendedor, días de validez y los repuestos en JSON.
--   - Calcula la fecha de vencimiento y el monto total presupuestado.
--   - Inserta cabecera y detalles en una sola transacción atómica.
-- =============================================================================
CREATE OR REPLACE FUNCTION sp_registrar_cotizacion(
    p_id_cliente INT,
    p_id_usuario INT,
    p_dias_vigencia INT,
    p_items JSONB -- Formato: '[{"id_producto": 1, "cantidad": 2, "precio": 150.00}]'
)
RETURNS INT AS $$
DECLARE
    v_id_cotizacion INT;
    v_monto_total NUMERIC(12, 2) := 0.00;
    v_item JSONB;
BEGIN
    IF p_items IS NULL OR jsonb_array_length(p_items) = 0 THEN
        RAISE EXCEPTION 'Regla Smart-DB: No se puede generar una cotización vacía.';
    END IF;

    IF p_dias_vigencia <= 0 THEN
        RAISE EXCEPTION 'Regla Smart-DB: Los días de vigencia deben ser mayores a 0.';
    END IF;

    -- Cálculo del importe total cotizado
    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        v_monto_total := v_monto_total + ((v_item->>'cantidad')::INT * (v_item->>'precio')::NUMERIC(12, 2));
    END LOOP;

    -- Inserción de cabecera de cotización
    INSERT INTO cotizaciones (
        id_cliente,
        id_usuario,
        fecha_vencimiento,
        monto_total,
        estado
    ) VALUES (
        p_id_cliente,
        p_id_usuario,
        CURRENT_DATE + p_dias_vigencia,
        v_monto_total,
        'Vigente'
    ) RETURNING id_cotizacion INTO v_id_cotizacion;

    -- Inserción de detalles
    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        INSERT INTO detalles_cotizaciones (
            id_cotizacion,
            id_producto,
            cantidad,
            precio_unitario_cotizado,
            subtotal
        ) VALUES (
            v_id_cotizacion,
            (v_item->>'id_producto')::INT,
            (v_item->>'cantidad')::INT,
            (v_item->>'precio')::NUMERIC(12, 2),
            ((v_item->>'cantidad')::INT * (v_item->>'precio')::NUMERIC(12, 2))
        );
    END LOOP;

    RETURN v_id_cotizacion;
END;
$$ LANGUAGE plpgsql;
-- =============================================================================
-- 9. FUNCIÓN: MOTOR DE BÚSQUEDA TÉCNICA MULTICRITERIO PARA MOSTRADOR (POS)
-- Nombre: fn_buscar_repuestos
-- Retorna: TABLE con repuestos coincidentes, stock y ubicación en almacén
-- ¿Qué hace?:
--   - Busca por texto libre (código interno, OEM o descripción).
--   - Cruza con marcas alternativas en 'codigos_alternativos'.
--   - Filtra opcionalmente por marca, modelo y año del vehículo.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_buscar_repuestos(
    p_criterio VARCHAR(100) DEFAULT NULL,    -- Código OEM, alternativo o nombre
    p_marca_vehiculo VARCHAR(60) DEFAULT NULL,
    p_modelo_vehiculo VARCHAR(80) DEFAULT NULL,
    p_anio_vehiculo INT DEFAULT NULL
)
RETURNS TABLE (
    id_producto INT,
    codigo_interno VARCHAR(40),
    codigo_oem VARCHAR(60),
    nombre_pieza VARCHAR(150),
    marca_fabricante VARCHAR(80),
    precio_venta NUMERIC(12, 2),
    stock_actual INT,
    ubicacion TEXT
) AS $$
BEGIN
    RETURN QUERY
    SELECT DISTINCT
        p.id_producto,
        p.codigo_interno,
        p.codigo_oem,
        p.nombre_pieza,
        p.marca_fabricante,
        p.precio_venta_sugerido,
        p.stock_actual,
        CONCAT('Pasillo: ', COALESCE(p.pasillo, '-'), ' | Estante: ', COALESCE(p.estante, '-'), ' | Gaveta: ', COALESCE(p.nivel_gaveta, '-'))::TEXT AS ubicacion
    FROM productos p
    LEFT JOIN codigos_alternativos ca ON p.id_producto = ca.id_producto
    LEFT JOIN compatibilidades c ON p.id_producto = c.id_producto
    LEFT JOIN vehiculos v ON c.id_vehiculo = v.id_vehiculo
    WHERE p.estado = TRUE
      AND (
          p_criterio IS NULL 
          OR lower(p.codigo_interno) LIKE '%' || lower(trim(p_criterio)) || '%'
          OR lower(p.codigo_oem) LIKE '%' || lower(trim(p_criterio)) || '%'
          OR lower(p.nombre_pieza) LIKE '%' || lower(trim(p_criterio)) || '%'
          OR lower(ca.codigo_referencia) LIKE '%' || lower(trim(p_criterio)) || '%'
      )
      AND (p_marca_vehiculo IS NULL OR lower(v.marca) = lower(trim(p_marca_vehiculo)))
      AND (p_modelo_vehiculo IS NULL OR lower(v.modelo) = lower(trim(p_modelo_vehiculo)))
      AND (p_anio_vehiculo IS NULL OR (p_anio_vehiculo >= v.anio_inicio AND p_anio_vehiculo <= v.anio_fin));
END;
$$ LANGUAGE plpgsql;
-- =============================================================================
-- 10. PROCEDIMIENTO BLINDADO: LIQUIDACIÓN DE COMPRAS A CRÉDITO (CU22)
-- Nombre: sp_liquidar_pago_compra
-- Invocación (Laravel):
--   DB::statement('CALL sp_liquidar_pago_compra(?, ?)', [$idCompra, $idUsuario])
-- ¿Qué hace?:
--   - Solo un Administrador activo puede liquidar cuentas por pagar.
--   - Bloquea la fila de la compra (FOR UPDATE) para impedir doble liquidación concurrente.
--   - Valida explícitamente, en este orden:
--       1. Que la compra exista.
--       2. Que no esté 'Anulado'.
--       3. Que sea tipo_pago = 'Crédito' (las compras al Contado se pagan al recibirse).
--       4. Que esté en estado_pago = 'Pendiente' (si ya está 'Pagado' se rechaza).
--   - Cambia estado_pago a 'Pagado' y registra la liquidación en la bitácora.
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_liquidar_pago_compra(INT);

CREATE OR REPLACE PROCEDURE sp_liquidar_pago_compra(
    p_id_compra INT,
    p_id_usuario INT
)
AS $$
DECLARE
    v_tipo_pago VARCHAR(30);
    v_estado_pago VARCHAR(30);
    v_nro_factura VARCHAR(60);
    v_monto_total NUMERIC(12, 2);
    v_fecha_compra DATE;
    v_proveedor VARCHAR(120);
BEGIN
    -- 1. Administrador o Cajero activo
    PERFORM fn_validar_usuario_autorizado(p_id_usuario, ARRAY['Administrador', 'Cajero']);

    -- 2. Bloqueo pesimista de la orden de compra
    SELECT nc.tipo_pago, nc.estado_pago, nc.numero_factura_nota, nc.monto_total, nc.fecha_compra, pr.razon_social
    INTO v_tipo_pago, v_estado_pago, v_nro_factura, v_monto_total, v_fecha_compra, v_proveedor
    FROM notas_compras nc
    JOIN proveedores pr ON pr.id_proveedor = nc.id_proveedor
    WHERE nc.id_compra = p_id_compra
    FOR UPDATE OF nc;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Regla Smart-DB: La orden de compra con ID % no existe.', p_id_compra;
    END IF;

    IF v_estado_pago = 'Anulado' THEN
        RAISE EXCEPTION 'Operación rechazada: La compra factura % se encuentra anulada y no puede liquidarse.', v_nro_factura;
    END IF;

    IF v_tipo_pago <> 'Crédito' THEN
        RAISE EXCEPTION 'Operación rechazada: La compra factura % fue pagada al %. Solo las compras a Crédito admiten liquidación.',
            v_nro_factura, v_tipo_pago;
    END IF;

    IF v_estado_pago <> 'Pendiente' THEN
        RAISE EXCEPTION 'Operación rechazada: La compra a crédito factura % ya figura como "%" (totalmente cancelada).',
            v_nro_factura, v_estado_pago;
    END IF;

    -- 3. Liquidación formal (doble condición como blindaje adicional)
    UPDATE notas_compras
    SET estado_pago = 'Pagado'
    WHERE id_compra = p_id_compra
      AND tipo_pago = 'Crédito'
      AND estado_pago = 'Pendiente';

    -- 4. Auditoría transaccional
    INSERT INTO bitacoras_auditorias (
        id_usuario,
        modulo_afectado,
        accion,
        registro_id,
        detalle_cambio,
        ip_origen
    ) VALUES (
        p_id_usuario,
        'Compras / Cuentas por Pagar',
        'UPDATE',
        p_id_compra,
        json_build_object(
            'operacion', 'LIQUIDACION_CREDITO',
            'proveedor', v_proveedor,
            'numero_factura', v_nro_factura,
            'monto_liquidado', v_monto_total,
            'fecha_compra', v_fecha_compra,
            'dias_transcurridos', CURRENT_DATE - v_fecha_compra,
            'estado_anterior', 'Pendiente',
            'estado_nuevo', 'Pagado'
        )::TEXT,
        COALESCE(NULLIF(current_setting('app.ip_origen', true), ''), '127.0.0.1')
    );
END;
$$ LANGUAGE plpgsql;


-- =============================================================================
-- 11. FUNCIÓN: RESUMEN ANALÍTICO GERENCIAL DEL DÍA / TURNO (DASHBOARD)
-- Nombre: fn_obtener_metricas_pos
-- Retorna: TABLE con totales vendidos, margen de ganancia real y estado de stock
-- ¿Qué hace?:
--   - Consolida en una sola lectura: total vendido en el rango, margen bruto generado
--     (precio cobrado - costo de adquisición en compras), tickets emitidos y piezas
--     en punto de quiebre (stock_actual <= stock_minimo).
--   - Evita que Laravel procese arrays gigantescos en PHP.
-- =============================================================================
CREATE OR REPLACE FUNCTION fn_obtener_metricas_pos(
    p_fecha DATE DEFAULT CURRENT_DATE
)
RETURNS TABLE (
    total_ventas_bruto NUMERIC(12, 2),
    total_descuentos NUMERIC(12, 2),
    total_recaudado_neto NUMERIC(12, 2),
    cantidad_transacciones BIGINT,
    productos_bajo_minimo BIGINT
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        COALESCE(SUM(nv.total_venta + nv.descuento_total), 0.00) AS total_ventas_bruto,
        COALESCE(SUM(nv.descuento_total), 0.00) AS total_descuentos,
        COALESCE(SUM(nv.total_venta), 0.00) AS total_recaudado_neto,
        COUNT(nv.id_venta) AS cantidad_transacciones,
        (SELECT COUNT(*) FROM productos WHERE stock_actual <= stock_minimo AND estado = TRUE) AS productos_bajo_minimo
    FROM notas_ventas nv
    WHERE nv.fecha_hora::DATE = p_fecha
      AND nv.estado = 'Completada';
END;
$$ LANGUAGE plpgsql;





-- =============================================================================
-- 13. PROCEDIMIENTO: AJUSTES MANUALES Y SALIDAS POR USO INTERNO (CU17 / CU18)
-- Nombre: sp_registrar_ajuste_inventario
-- Invocación (Laravel):
--   DB::select('CALL sp_registrar_ajuste_inventario(?, ?, ?, ?, ?, NULL, NULL)', [...])
--   -> devuelve una fila con p_id_ajuste y p_stock_resultante.
-- Semántica de p_cantidad según p_tipo_ajuste:
--   - 'Merma', 'Pérdida/Extravío', 'Devolución Dañada', 'Uso Interno':
--       p_cantidad = unidades a DESCONTAR (entero positivo). Se guarda en negativo.
--   - 'Conteo Físico':
--       p_cantidad = stock REAL contado en almacén (>= 0). Se guarda la diferencia
--       (contado - sistema), que puede ser positiva (sobrante) o negativa (faltante).
-- ¿Qué hace?:
--   - Valida usuario activo con rol Administrador o Bodeguero.
--   - Valida tipo, cantidad y motivo (mínimo 10 caracteres, según CU17).
--   - Bloquea la fila del producto (FOR UPDATE) y verifica que el stock resultante
--     no sea negativo.
--   - Inserta en 'ajustes_inventario' con la cantidad firmada. El Trigger 5
--     (fn_trg_aplicar_ajuste_inventario) aplica la diferencia a productos.stock_actual
--     en la misma transacción; por eso el procedimiento NO actualiza el stock
--     directamente (evita el doble descuento).
--   - Registra el evento en 'bitacoras_auditorias'.
-- =============================================================================
CREATE OR REPLACE PROCEDURE sp_registrar_ajuste_inventario(
    p_id_producto INT,
    p_id_usuario INT,
    p_tipo_ajuste VARCHAR(40),
    p_cantidad INT,
    p_motivo TEXT,
    INOUT p_id_ajuste INT DEFAULT NULL,
    INOUT p_stock_resultante INT DEFAULT NULL
)
AS $$
DECLARE
    v_stock_actual INT;
    v_nombre_pieza VARCHAR(150);
    v_delta INT;
BEGIN
    -- 1. Usuario autorizado (almacén o administración)
    PERFORM fn_validar_usuario_autorizado(p_id_usuario, ARRAY['Administrador', 'Bodeguero']);

    -- 2. Validaciones de entrada
    IF p_tipo_ajuste IS NULL OR p_tipo_ajuste NOT IN ('Merma', 'Pérdida/Extravío', 'Conteo Físico', 'Devolución Dañada', 'Uso Interno') THEN
        RAISE EXCEPTION 'Regla Smart-DB: Tipo de ajuste "%" no válido. Permitidos: Merma, Pérdida/Extravío, Conteo Físico, Devolución Dañada, Uso Interno.', p_tipo_ajuste;
    END IF;

    IF p_cantidad IS NULL THEN
        RAISE EXCEPTION 'Regla Smart-DB: Debe indicar la cantidad del ajuste.';
    END IF;

    IF p_motivo IS NULL OR length(trim(p_motivo)) < 10 THEN
        RAISE EXCEPTION 'Regla Smart-DB: El motivo del ajuste es obligatorio (mínimo 10 caracteres).';
    END IF;

    -- 3. Bloqueo pesimista del producto
    SELECT stock_actual, nombre_pieza
    INTO v_stock_actual, v_nombre_pieza
    FROM productos
    WHERE id_producto = p_id_producto
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Error Smart-DB: El producto ID % no existe en catálogo.', p_id_producto;
    END IF;

    -- 4. Cálculo de la diferencia firmada
    IF p_tipo_ajuste = 'Conteo Físico' THEN
        IF p_cantidad < 0 THEN
            RAISE EXCEPTION 'Regla Smart-DB: El stock contado no puede ser negativo (recibido: %).', p_cantidad;
        END IF;

        v_delta := p_cantidad - v_stock_actual;

        IF v_delta = 0 THEN
            RAISE EXCEPTION 'Operación sin efecto: El conteo físico (%) de "%" coincide con el stock del sistema. No se requiere ajuste.',
                p_cantidad, v_nombre_pieza;
        END IF;
    ELSE
        IF p_cantidad <= 0 THEN
            RAISE EXCEPTION 'Regla Smart-DB: Para "%" indique las unidades a descontar como entero positivo (recibido: %).',
                p_tipo_ajuste, p_cantidad;
        END IF;

        v_delta := -p_cantidad;
    END IF;

    -- 5. Blindaje de stock no negativo
    IF v_stock_actual + v_delta < 0 THEN
        RAISE EXCEPTION 'Ajuste denegado: "%" tiene % unidad(es) en almacén; no se pueden descontar %.',
            v_nombre_pieza, v_stock_actual, abs(v_delta);
    END IF;

    -- 6. Registro del ajuste: dispara fn_trg_aplicar_ajuste_inventario() (stock_actual += v_delta)
    INSERT INTO ajustes_inventario (
        id_producto,
        id_usuario,
        cantidad_ajustada,
        tipo_ajuste,
        motivo
    ) VALUES (
        p_id_producto,
        p_id_usuario,
        v_delta,
        p_tipo_ajuste,
        trim(p_motivo)
    ) RETURNING id_ajuste INTO p_id_ajuste;

    p_stock_resultante := v_stock_actual + v_delta;

    -- 7. Auditoría transaccional
    INSERT INTO bitacoras_auditorias (
        id_usuario,
        modulo_afectado,
        accion,
        registro_id,
        detalle_cambio,
        ip_origen
    ) VALUES (
        p_id_usuario,
        'Inventario / Ajustes',
        'INSERT',
        p_id_ajuste,
        json_build_object(
            'id_producto', p_id_producto,
            'producto', v_nombre_pieza,
            'tipo_ajuste', p_tipo_ajuste,
            'cantidad_ajustada', v_delta,
            'stock_anterior', v_stock_actual,
            'stock_resultante', p_stock_resultante,
            'motivo', trim(p_motivo)
        )::TEXT,
        COALESCE(NULLIF(current_setting('app.ip_origen', true), ''), '127.0.0.1')
    );
END;
$$ LANGUAGE plpgsql;
