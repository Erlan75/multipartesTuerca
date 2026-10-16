-- =============================================================================
-- BASE DE DATOS: SISTEMA MULTIPARTES EL TUERCA
-- MOTOR: PostgreSQL
-- =============================================================================
-- HISTORIAL DE CAMBIOS ESTRUCTURALES Y CONSOLIDACIÓN TOTAL DDL (CICLO 1 Y CICLO 2)
-- -----------------------------------------------------------------------------
-- [Ciclo 1 - Seguridad]
--   * bitacoras_auditorias.id_usuario admite NULL (login fallido de usuarios inexistentes).
--   * chk_bitacora_accion: LOGIN_FALLIDO, CUENTA_BLOQUEADA, LOGIN_BLOQUEADO,
--     PASSWORD_RESET_SOLICITUD, PASSWORD_RESET_EXITOSO, PASSWORD_CAMBIO_PERFIL,
--     PASSWORD_MODIFICADO.
-- [Ciclo 2 - Consolidación Nativa]
--   * proveedores.estado BOOLEAN NOT NULL DEFAULT TRUE (CU19: baja lógica nativa).
--   * ajustes_inventario.chk_ajuste_tipo nativo con 'Uso Interno' (CU17/CU18).
--   * usuarios.chk_usuario_password_hash CHECK (length(password_hash) >= 60).
-- =============================================================================

-- Limpieza preventiva si se ejecuta en pruebas
DROP TABLE IF EXISTS bitacoras_auditorias CASCADE;
DROP TABLE IF EXISTS detalles_ventas CASCADE;
DROP TABLE IF EXISTS notas_ventas CASCADE;
DROP TABLE IF EXISTS detalles_cotizaciones CASCADE;
DROP TABLE IF EXISTS cotizaciones CASCADE;
DROP TABLE IF EXISTS clientes CASCADE;
DROP TABLE IF EXISTS movimientos_cajas CASCADE;
DROP TABLE IF EXISTS sesiones_cajas CASCADE;
DROP TABLE IF EXISTS detalles_compras CASCADE;
DROP TABLE IF EXISTS notas_compras CASCADE;
DROP TABLE IF EXISTS proveedores CASCADE;
DROP TABLE IF EXISTS ajustes_inventario CASCADE;
DROP TABLE IF EXISTS compatibilidades CASCADE;
DROP TABLE IF EXISTS vehiculos CASCADE;
DROP TABLE IF EXISTS codigos_alternativos CASCADE;
DROP TABLE IF EXISTS productos CASCADE;
DROP TABLE IF EXISTS categorias CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

-- =============================================================================
-- 1. MÓDULO DE SEGURIDAD Y GESTIÓN DE USUARIOS
-- =============================================================================

CREATE TABLE roles (
    id_rol SERIAL PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255),
    CONSTRAINT chk_rol_nombre_valido CHECK (length(trim(nombre_rol)) >= 3)
);
-- Evita roles duplicados sin importar mayúsculas/minúsculas
CREATE UNIQUE INDEX uq_rol_nombre_lower ON roles (lower(trim(nombre_rol)));
INSERT INTO roles (id_rol, nombre_rol, descripcion) 
VALUES (5, 'Cliente', 'Cliente web registrado para cotizaciones y compras') 
ON CONFLICT (id_rol) DO NOTHING;
SELECT setval('roles_id_rol_seq', (SELECT COALESCE(MAX(id_rol), 1) FROM roles), true);

CREATE TABLE usuarios (
    id_usuario SERIAL PRIMARY KEY,
    id_rol INT NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL,
    correo VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) 
        REFERENCES roles(id_rol) ON DELETE RESTRICT,
    CONSTRAINT chk_usuario_nombre CHECK (length(trim(nombre_completo)) >= 3),
    CONSTRAINT chk_usuario_username CHECK (length(trim(username)) >= 4),
    CONSTRAINT chk_usuario_correo CHECK (correo ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'),
    CONSTRAINT chk_usuario_password_hash CHECK (length(password_hash) >= 60)
);
CREATE UNIQUE INDEX uq_usuario_username_lower ON usuarios (lower(trim(username)));
CREATE UNIQUE INDEX uq_usuario_correo_lower ON usuarios (lower(trim(correo)));

-- =============================================================================
-- 2. MÓDULO DE CATÁLOGO, INVENTARIO Y COMPATIBILIDAD VEHICULAR
-- =============================================================================

CREATE TABLE categorias (
    id_categoria SERIAL PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    descripcion TEXT,
    CONSTRAINT chk_categoria_nombre CHECK (length(trim(nombre)) >= 3)
);
CREATE UNIQUE INDEX uq_categoria_nombre_lower ON categorias (lower(trim(nombre)));

CREATE TABLE productos (
    id_producto SERIAL PRIMARY KEY,
    id_categoria INT NOT NULL,
    codigo_interno VARCHAR(40) NOT NULL,
    codigo_oem VARCHAR(60) NOT NULL,
    nombre_pieza VARCHAR(150) NOT NULL,
    marca_fabricante VARCHAR(80),
    procedencia VARCHAR(50),
    precio_venta_sugerido NUMERIC(12, 2) NOT NULL,
    margen_ganancia NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    unidad_medida VARCHAR(20) NOT NULL DEFAULT 'Unidad',
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 3,
    stock_seguridad INT NOT NULL DEFAULT 5,
    pasillo VARCHAR(20),
    estante VARCHAR(20),
    nivel_gaveta VARCHAR(20),
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria) 
        REFERENCES categorias(id_categoria) ON DELETE RESTRICT,
    CONSTRAINT chk_prod_cod_interno CHECK (length(trim(codigo_interno)) >= 2),
    CONSTRAINT chk_prod_cod_oem CHECK (length(trim(codigo_oem)) >= 2),
    CONSTRAINT chk_prod_nombre CHECK (length(trim(nombre_pieza)) >= 3),
    CONSTRAINT chk_prod_precio_sug CHECK (precio_venta_sugerido > 0),
    CONSTRAINT chk_prod_margen CHECK (margen_ganancia >= 0),
    CONSTRAINT chk_prod_stocks CHECK (stock_actual >= 0 AND stock_minimo >= 0 AND stock_seguridad >= stock_minimo)
);
CREATE UNIQUE INDEX uq_producto_codinterno_lower ON productos (lower(trim(codigo_interno)));
CREATE INDEX idx_producto_oem_lower ON productos (lower(trim(codigo_oem)));

-- Códigos alternativos (Composición fuerte con Producto: ON DELETE CASCADE)
CREATE TABLE codigos_alternativos (
    id_codigo_alt SERIAL PRIMARY KEY,
    id_producto INT NOT NULL,
    codigo_referencia VARCHAR(60) NOT NULL,
    marca_alternativa VARCHAR(80),
    CONSTRAINT fk_codalt_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE CASCADE,
    CONSTRAINT chk_codalt_referencia CHECK (length(trim(codigo_referencia)) >= 2)
);
CREATE INDEX idx_codalt_ref_lower ON codigos_alternativos (lower(trim(codigo_referencia)));

CREATE TABLE vehiculos (
    id_vehiculo SERIAL PRIMARY KEY,
    marca VARCHAR(60) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    anio_inicio INT NOT NULL,
    anio_fin INT NOT NULL,
    motor_cilindrada VARCHAR(50),
    combustible VARCHAR(30) DEFAULT 'Gasolina',
    CONSTRAINT chk_vehiculo_marca CHECK (length(trim(marca)) >= 2),
    CONSTRAINT chk_vehiculo_modelo CHECK (length(trim(modelo)) >= 1),
    CONSTRAINT chk_vehiculo_anios CHECK (anio_inicio >= 1950 AND anio_fin >= anio_inicio AND anio_fin <= 2040),
    CONSTRAINT chk_vehiculo_combustible CHECK (combustible IN ('Gasolina', 'Diésel', 'GNV', 'Híbrido', 'Eléctrico'))
);
CREATE UNIQUE INDEX uq_vehiculo_completo ON vehiculos (lower(trim(marca)), lower(trim(modelo)), anio_inicio, anio_fin, lower(trim(motor_cilindrada)));

-- Tabla intermedia de compatibilidad técnica
CREATE TABLE compatibilidades (
    id_compatibilidad SERIAL PRIMARY KEY,
    id_producto INT NOT NULL,
    id_vehiculo INT NOT NULL,
    notas_posicion VARCHAR(255),
    CONSTRAINT fk_comp_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE CASCADE,
    CONSTRAINT fk_comp_vehiculo FOREIGN KEY (id_vehiculo) 
        REFERENCES vehiculos(id_vehiculo) ON DELETE CASCADE,
    CONSTRAINT uq_compatibilidad_prod_veh UNIQUE (id_producto, id_vehiculo)
);

CREATE TABLE ajustes_inventario (
    id_ajuste SERIAL PRIMARY KEY,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    cantidad_ajustada INT NOT NULL,
    tipo_ajuste VARCHAR(40) NOT NULL,
    motivo TEXT NOT NULL,
    fecha_ajuste TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ajuste_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE RESTRICT,
    CONSTRAINT fk_ajuste_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT chk_ajuste_cantidad CHECK (cantidad_ajustada <> 0),
    -- 'Uso Interno' (CU18): salidas por autoconsumo, centralizadas en esta misma tabla
    CONSTRAINT chk_ajuste_tipo CHECK (tipo_ajuste IN ('Merma', 'Pérdida/Extravío', 'Conteo Físico', 'Devolución Dañada', 'Uso Interno')),
    CONSTRAINT chk_ajuste_motivo CHECK (length(trim(motivo)) >= 5)
);

-- =============================================================================
-- 3. MÓDULO DE COMPRAS Y PROVEEDORES
-- =============================================================================

CREATE TABLE proveedores (
    id_proveedor SERIAL PRIMARY KEY,
    razon_social VARCHAR(120) NOT NULL,
    nit VARCHAR(30),
    contacto_nombre VARCHAR(100),
    telefono VARCHAR(20),
    correo VARCHAR(100),
    direccion VARCHAR(200),
    condicion_comercial VARCHAR(60) DEFAULT 'Contado',
    estado BOOLEAN NOT NULL DEFAULT TRUE, -- CU19: baja lógica (TRUE = activo, FALSE = inactivo)
    CONSTRAINT chk_prov_razon_social CHECK (length(trim(razon_social)) >= 3),
    CONSTRAINT chk_prov_condicion CHECK (condicion_comercial IN ('Contado', 'Crédito 15 días', 'Crédito 30 días', 'Crédito 60 días'))
);
CREATE UNIQUE INDEX uq_proveedor_razon_lower ON proveedores (lower(trim(razon_social)));

CREATE TABLE notas_compras (
    id_compra SERIAL PRIMARY KEY,
    id_proveedor INT NOT NULL,
    id_usuario_receptor INT NOT NULL,
    numero_factura_nota VARCHAR(60) NOT NULL,
    fecha_compra DATE NOT NULL,
    monto_total NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    tipo_pago VARCHAR(30) NOT NULL,
    estado_pago VARCHAR(30) NOT NULL,
    CONSTRAINT fk_compra_proveedor FOREIGN KEY (id_proveedor) 
        REFERENCES proveedores(id_proveedor) ON DELETE RESTRICT,
    CONSTRAINT fk_compra_usuario FOREIGN KEY (id_usuario_receptor) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT chk_compra_factura CHECK (length(trim(numero_factura_nota)) >= 2),
    CONSTRAINT chk_compra_monto_total CHECK (monto_total >= 0.00),
    CONSTRAINT chk_compra_tipo_pago CHECK (tipo_pago IN ('Contado', 'Crédito')),
    CONSTRAINT chk_compra_estado_pago CHECK (estado_pago IN ('Pagado', 'Pendiente', 'Anulado'))
);

-- Composición fuerte con NotaCompra (ON DELETE CASCADE)
CREATE TABLE detalles_compras (
    id_detalle_compra SERIAL PRIMARY KEY,
    id_compra INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_recibida INT NOT NULL,
    costo_unitario NUMERIC(12, 2) NOT NULL,
    subtotal NUMERIC(12, 2) NOT NULL,
    CONSTRAINT fk_detcompra_compra FOREIGN KEY (id_compra) 
        REFERENCES notas_compras(id_compra) ON DELETE CASCADE,
    CONSTRAINT fk_detcompra_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE RESTRICT,
    CONSTRAINT chk_detcompra_cant CHECK (cantidad_recibida > 0),
    CONSTRAINT chk_detcompra_costo CHECK (costo_unitario > 0),
    CONSTRAINT chk_detcompra_subtotal CHECK (subtotal >= costo_unitario * cantidad_recibida)
);

-- =============================================================================
-- 4. MÓDULO DE CAJA Y TURNOS
-- =============================================================================

CREATE TABLE sesiones_cajas (
    id_sesion SERIAL PRIMARY KEY,
    id_usuario_cajero INT NOT NULL,
    fecha_hora_apertura TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_inicial NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    fecha_hora_cierre TIMESTAMP WITHOUT TIME ZONE,
    monto_esperado_sistema NUMERIC(12, 2),
    monto_real_contado NUMERIC(12, 2),
    diferencia NUMERIC(12, 2),
    estado VARCHAR(20) NOT NULL DEFAULT 'Abierta',
    CONSTRAINT fk_sesion_cajero FOREIGN KEY (id_usuario_cajero) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT chk_sesion_inicial CHECK (monto_inicial >= 0.00),
    CONSTRAINT chk_sesion_estado CHECK (estado IN ('Abierta', 'Cerrada', 'Cuadrada', 'Con Diferencia')),
    CONSTRAINT chk_sesion_fechas CHECK (fecha_hora_cierre IS NULL OR fecha_hora_cierre >= fecha_hora_apertura)
);

-- Composición fuerte con SesionCaja (ON DELETE CASCADE)
CREATE TABLE movimientos_cajas (
    id_movimiento SERIAL PRIMARY KEY,
    id_sesion INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    monto NUMERIC(12, 2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    hora TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_sesion FOREIGN KEY (id_sesion) 
        REFERENCES sesiones_cajas(id_sesion) ON DELETE CASCADE,
    CONSTRAINT chk_mov_tipo CHECK (tipo IN ('Ingreso Extra', 'Retiro Menor')),
    CONSTRAINT chk_mov_monto CHECK (monto > 0.00),
    CONSTRAINT chk_mov_motivo CHECK (length(trim(motivo)) >= 4)
);

-- =============================================================================
-- 5. MÓDULO DE CLIENTES, COTIZACIONES Y VENTAS (POS)
-- =============================================================================

CREATE TABLE clientes (
    id_cliente SERIAL PRIMARY KEY,
    nombre_razon_social VARCHAR(120) NOT NULL,
    ci_nit VARCHAR(30),
    telefono VARCHAR(20),
    tipo_cliente VARCHAR(30) NOT NULL DEFAULT 'Particular',
    descuento_frecuente NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT chk_cliente_nombre CHECK (length(trim(nombre_razon_social)) >= 3),
    CONSTRAINT chk_cliente_tipo CHECK (tipo_cliente IN ('Particular', 'Taller/Mecánico', 'Empresa/Flota')),
    CONSTRAINT chk_cliente_descuento CHECK (descuento_frecuente >= 0.00 AND descuento_frecuente <= 50.00)
);
CREATE INDEX idx_cliente_cinit ON clientes (trim(ci_nit));

CREATE TABLE cotizaciones (
    id_cotizacion SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_usuario INT NOT NULL,
    fecha_emision TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE NOT NULL,
    monto_total NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    estado VARCHAR(30) NOT NULL DEFAULT 'Vigente',
    CONSTRAINT fk_cot_cliente FOREIGN KEY (id_cliente) 
        REFERENCES clientes(id_cliente) ON DELETE RESTRICT,
    CONSTRAINT fk_cot_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT chk_cot_fechas CHECK (fecha_vencimiento >= fecha_emision::DATE),
    CONSTRAINT chk_cot_monto CHECK (monto_total >= 0.00),
    CONSTRAINT chk_cot_estado CHECK (estado IN ('Vigente', 'Concretada/Facturada', 'Vencida', 'Anulada'))
);

-- Composición fuerte con Cotización (ON DELETE CASCADE)
CREATE TABLE detalles_cotizaciones (
    id_detalle_cot SERIAL PRIMARY KEY,
    id_cotizacion INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario_cotizado NUMERIC(12, 2) NOT NULL,
    subtotal NUMERIC(12, 2) NOT NULL,
    CONSTRAINT fk_detcot_cotizacion FOREIGN KEY (id_cotizacion) 
        REFERENCES cotizaciones(id_cotizacion) ON DELETE CASCADE,
    CONSTRAINT fk_detcot_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE RESTRICT,
    CONSTRAINT chk_detcot_cant CHECK (cantidad > 0),
    CONSTRAINT chk_detcot_precio CHECK (precio_unitario_cotizado > 0),
    CONSTRAINT chk_detcot_subtotal CHECK (subtotal = precio_unitario_cotizado * cantidad)
);

CREATE TABLE notas_ventas (
    id_venta SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_usuario_vendedor INT NOT NULL,
    id_sesion_caja INT NOT NULL,
    fecha_hora TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_venta NUMERIC(12, 2) NOT NULL,
    descuento_total NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    metodo_pago VARCHAR(30) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'Completada',
    motivo_anulacion TEXT,
    CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente) 
        REFERENCES clientes(id_cliente) ON DELETE RESTRICT,
    CONSTRAINT fk_venta_vendedor FOREIGN KEY (id_usuario_vendedor) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_venta_caja FOREIGN KEY (id_sesion_caja) 
        REFERENCES sesiones_cajas(id_sesion) ON DELETE RESTRICT,
    CONSTRAINT chk_venta_total CHECK (total_venta >= 0.00),
    CONSTRAINT chk_venta_descuento CHECK (descuento_total >= 0.00),
    CONSTRAINT chk_venta_metodo_pago CHECK (metodo_pago IN ('Efectivo', 'Transferencia/QR', 'Tarjeta Débito/Crédito', 'Mixto')),
    CONSTRAINT chk_venta_estado CHECK (estado IN ('Completada', 'Anulada')),
    CONSTRAINT chk_venta_anulacion CHECK (
        (estado = 'Anulada' AND motivo_anulacion IS NOT NULL AND length(trim(motivo_anulacion)) >= 5) 
        OR (estado = 'Completada')
    )
);

-- Composición fuerte con NotaVenta (ON DELETE CASCADE)
CREATE TABLE detalles_ventas (
    id_detalle_venta SERIAL PRIMARY KEY,
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_vendida INT NOT NULL,
    precio_unitario_cobrado NUMERIC(12, 2) NOT NULL,
    subtotal NUMERIC(12, 2) NOT NULL,
    CONSTRAINT fk_detventa_venta FOREIGN KEY (id_venta) 
        REFERENCES notas_ventas(id_venta) ON DELETE CASCADE,
    CONSTRAINT fk_detventa_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE RESTRICT,
    CONSTRAINT chk_detventa_cant CHECK (cantidad_vendida > 0),
    CONSTRAINT chk_detventa_precio CHECK (precio_unitario_cobrado > 0),
    CONSTRAINT chk_detventa_subtotal CHECK (subtotal = precio_unitario_cobrado * cantidad_vendida)
);

-- =============================================================================
-- 6. MÓDULO DE AUDITORÍA Y BITÁCORA
-- =============================================================================

CREATE TABLE bitacoras_auditorias (
    id_bitacora BIGSERIAL PRIMARY KEY,
    id_usuario INT, -- Admite NULL: intentos de acceso de usuarios inexistentes o procesos del motor
    fecha_hora TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modulo_afectado VARCHAR(60) NOT NULL,
    accion VARCHAR(30) NOT NULL,
    registro_id INT,
    detalle_cambio TEXT NOT NULL,
    ip_origen VARCHAR(45),
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT chk_bitacora_accion CHECK (accion IN (
        'INSERT', 'UPDATE', 'DELETE', 'ANULACION', 'LOGIN', 'LOGOUT',
        'LOGIN_FALLIDO', 'CUENTA_BLOQUEADA', 'LOGIN_BLOQUEADO',
        'PASSWORD_RESET_SOLICITUD', 'PASSWORD_RESET_EXITOSO', 'PASSWORD_CAMBIO_PERFIL',
        'PASSWORD_MODIFICADO', 'REGISTRO_CLIENTE'
    ))
);
CREATE INDEX idx_bitacora_fecha ON bitacoras_auditorias (fecha_hora);