-- =============================================================================
-- PARTE 1: RESET Y CATÁLOGOS BASE
-- =============================================================================

-- 0. Limpieza total y reseteo de secuencias a 1
TRUNCATE TABLE 
    bitacoras_auditorias,
    detalles_ventas,
    notas_ventas,
    detalles_cotizaciones,
    cotizaciones,
    movimientos_cajas,
    sesiones_cajas,
    detalles_compras,
    notas_compras,
    ajustes_inventario,
    compatibilidades,
    codigos_alternativos,
    productos,
    categorias,
    vehiculos,
    clientes,
    proveedores,
    usuarios,
    roles
RESTART IDENTITY CASCADE;

-- -----------------------------------------------------------------------------
-- 1. ROLES (IDs: 1 al 4)
-- -----------------------------------------------------------------------------
INSERT INTO roles (nombre_rol, descripcion) VALUES
('Administrador', 'Control total administrativo, auditoría, reportes y configuración'),
('Bodeguero', 'Recepción de mercadería, gestión de compras, estanterías y ajustes'),
('Vendedor', 'Atención en mostrador, emisión de cotizaciones y notas de venta en POS'),
('Cajero', 'Apertura y cierre de turnos, arqueo y cobro de transacciones');

-- -----------------------------------------------------------------------------
-- 2. USUARIOS (IDs: 1 al 5 - INTEGRANTES DEL PROYECTO CON CLAVE 'Cher_4mi')
-- -----------------------------------------------------------------------------
INSERT INTO usuarios (id_usuario, id_rol, nombre_completo, username, correo, telefono, password_hash, estado) VALUES
(1, 1, 'Garcia Mamani Erlan Gaston', 'Erlan', 'erlan.garcia75@gmail.com', '75909498', '$2y$10$cQeCDqBLIzf8nCqcSLvflezp6Po7WVIxNQzUYazMWxYN1KD5yElyW', TRUE),
(2, 2, 'Rojas Daza Cristhian', 'Cristhian', 'cristhianrojasdaza338@gmail.com', '70012345', '$2y$10$cQeCDqBLIzf8nCqcSLvflezp6Po7WVIxNQzUYazMWxYN1KD5yElyW', TRUE),
(3, 3, 'Olivera Baltazar Juan Carlos', 'Carlos', 'oliveraj221@gmail.com', '71098765', '$2y$10$cQeCDqBLIzf8nCqcSLvflezp6Po7WVIxNQzUYazMWxYN1KD5yElyW', TRUE),
(4, 4, 'Baldivieso Orosco Sebastian Henry', 'Sebastian', 'sebastian.baldi7@gmail.com', '72054321', '$2y$10$cQeCDqBLIzf8nCqcSLvflezp6Po7WVIxNQzUYazMWxYN1KD5yElyW', TRUE),
(5, 1, 'Mamani Maturano Edilson', 'Edilson', 'erickmaturano04@gmail.com', '73067890', '$2y$10$cQeCDqBLIzf8nCqcSLvflezp6Po7WVIxNQzUYazMWxYN1KD5yElyW', TRUE);

SELECT setval('usuarios_id_usuario_seq', 5, true);

-- -----------------------------------------------------------------------------
-- 3. CATEGORÍAS (IDs: 1 al 6)
-- -----------------------------------------------------------------------------
INSERT INTO categorias (nombre, descripcion) VALUES
('Frenos', 'Discos, tambores, pastillas, zapatas, cilindros maestros y flexibles'),
('Suspensión y Dirección', 'Amortiguadores, muñones, terminales, bujes, cremalleras y brazos axiales'),
('Motor e Inyección', 'Bujías, inyectores, sensores, filtros, bombas de gasolina y empaquetaduras'),
('Transmisión y Embrague', 'Kits de embrague, platos, discos, crucetas, palieres y juntas homocinéticas'),
('Sistema Eléctrico e Iluminación', 'Alternadores, arrancadores, bobinas de encendido, focos y fusibles'),
('Refrigeración', 'Radiadores, bombas de agua, termostatos y mangueras');

-- -----------------------------------------------------------------------------
-- 4. PRODUCTOS (IDs: 1 al 30)
-- -----------------------------------------------------------------------------
INSERT INTO productos (id_categoria, codigo_interno, codigo_oem, nombre_pieza, marca_fabricante, procedencia, precio_venta_sugerido, margen_ganancia, unidad_medida, stock_actual, stock_minimo, stock_seguridad, pasillo, estante, nivel_gaveta, estado) VALUES
-- Frenos (Cat 1)
(1, 'FR-001', '04465-02220', 'Juego de Pastillas de Freno Delanteras', 'Bosch', 'Alemania', 220.00, 35.00, 'Juego', 45, 8, 15, 'P1', 'E-01', 'G-01', TRUE),
(1, 'FR-002', '43512-02110', 'Disco de Freno Ventilado Delantero', 'Fremax', 'Brasil', 310.00, 30.00, 'Unidad', 20, 4, 8, 'P1', 'E-01', 'G-02', TRUE),
(1, 'FR-003', '55810-77J00', 'Pastillas de Freno Delanteras', 'Akebono', 'Japón', 260.00, 28.00, 'Juego', 30, 6, 10, 'P1', 'E-02', 'G-01', TRUE),
(1, 'FR-004', '44060-8H385', 'Juego de Balatas/Zapatas Traseras', 'Kobras', 'Brasil', 180.00, 32.00, 'Juego', 18, 5, 8, 'P1', 'E-02', 'G-02', TRUE),
(1, 'FR-005', '04466-02180', 'Pastillas de Freno Traseras', 'Cobreq', 'Brasil', 160.00, 30.00, 'Juego', 35, 6, 12, 'P1', 'E-03', 'G-01', TRUE),
(1, 'FR-006', '43512-0D060', 'Disco de Freno Delantero Sólido', 'Fremax', 'Brasil', 240.00, 28.00, 'Unidad', 18, 4, 8, 'P1', 'E-01', 'G-03', TRUE),
-- Suspensión y Dirección (Cat 2)
(2, 'SU-001', '48510-09L30', 'Amortiguador Delantero Derecho Gas', 'KYB', 'Japón', 450.00, 25.00, 'Unidad', 14, 4, 6, 'P2', 'E-01', 'G-01', TRUE),
(2, 'SU-002', '48520-09L30', 'Amortiguador Delantero Izquierdo Gas', 'KYB', 'Japón', 450.00, 25.00, 'Unidad', 14, 4, 6, 'P2', 'E-01', 'G-02', TRUE),
(2, 'SU-003', '45046-09251', 'Terminal de Dirección Exterior', '555 Three Five', 'Japón', 130.00, 40.00, 'Unidad', 50, 10, 20, 'P2', 'E-02', 'G-03', TRUE),
(2, 'SU-004', '43330-09510', 'Muñón / Rótula de Suspensión Inferior', '555 Three Five', 'Japón', 175.00, 35.00, 'Unidad', 35, 8, 15, 'P2', 'E-02', 'G-04', TRUE),
(2, 'SU-005', '41800-65J00', 'Amortiguador Trasero Hidráulico', 'Monroe', 'EE.UU.', 320.00, 30.00, 'Unidad', 22, 6, 10, 'P2', 'E-03', 'G-01', TRUE),
(2, 'SU-006', '48820-02030', 'Bieleta Estabilizadora Delantera', '555 Three Five', 'Japón', 95.00, 40.00, 'Unidad', 60, 10, 20, 'P2', 'E-03', 'G-02', TRUE),
(2, 'SU-007', '48655-02050', 'Buje Grande de Trapecio Inferior', 'RBI', 'Tailandia', 65.00, 45.00, 'Unidad', 80, 15, 30, 'P2', 'E-04', 'G-01', TRUE),
(2, 'SU-008', '45503-09321', 'Terminal Axial de Dirección', 'CTR', 'Corea del Sur', 110.00, 35.00, 'Unidad', 40, 8, 15, 'P2', 'E-02', 'G-05', TRUE),
-- Motor e Inyección (Cat 3)
(3, 'MO-001', '90919-01253', 'Bujía de Encendido Iridium SC20HR11', 'Denso', 'Japón', 75.00, 45.00, 'Unidad', 120, 20, 40, 'P3', 'E-01', 'G-01', TRUE),
(3, 'MO-002', '09482-00547', 'Bujía Nickel BKR6E-11', 'NGK', 'Japón', 35.00, 50.00, 'Unidad', 200, 30, 60, 'P3', 'E-01', 'G-02', TRUE),
(3, 'MO-003', '90915-YZZE1', 'Filtro de Aceite de Motor Blindado', 'Toyota OEM', 'Tailandia', 55.00, 38.00, 'Unidad', 85, 15, 30, 'P3', 'E-02', 'G-01', TRUE),
(3, 'MO-004', '16510-61A31', 'Filtro de Aceite Metálico', 'Wix', 'EE.UU.', 48.00, 40.00, 'Unidad', 60, 12, 25, 'P3', 'E-02', 'G-02', TRUE),
(3, 'MO-005', '17801-21050', 'Filtro de Aire de Motor Seco', 'Mann Filter', 'Alemania', 85.00, 40.00, 'Unidad', 50, 10, 20, 'P3', 'E-04', 'G-01', TRUE),
(3, 'MO-006', '23300-21010', 'Filtro de Gasolina de Línea Blindado', 'Denso', 'Japón', 110.00, 35.00, 'Unidad', 30, 6, 12, 'P3', 'E-03', 'G-02', TRUE),
(3, 'MO-007', '11213-22050', 'Empaque de Tapa de Válvulas', 'Stone', 'Japón', 70.00, 50.00, 'Unidad', 45, 8, 15, 'P3', 'E-05', 'G-01', TRUE),
(3, 'MO-008', '23221-0D010', 'Bomba Eléctrica de Gasolina 3 Bar', 'Denso', 'Japón', 280.00, 30.00, 'Unidad', 25, 5, 10, 'P3', 'E-03', 'G-03', TRUE),
-- Transmisión y Embrague (Cat 4)
(4, 'TR-001', '31250-0D160', 'Disco de Embrague Mecánico', 'Aisin', 'Japón', 520.00, 25.00, 'Unidad', 12, 3, 6, 'P4', 'E-01', 'G-01', TRUE),
(4, 'TR-002', '31210-0D130', 'Prensa / Plato Opresor de Embrague', 'Aisin', 'Japón', 580.00, 25.00, 'Unidad', 10, 3, 5, 'P4', 'E-01', 'G-02', TRUE),
(4, 'TR-003', '31230-12180', 'Rodamiento / Collarín de Empuje', 'Koyo', 'Japón', 160.00, 35.00, 'Unidad', 25, 5, 10, 'P4', 'E-02', 'G-01', TRUE),
-- Sistema Eléctrico e Iluminación (Cat 5)
(5, 'EL-001', '90919-02258', 'Bobina de Encendido Individual', 'Denso', 'Japón', 290.00, 30.00, 'Unidad', 28, 6, 12, 'P5', 'E-01', 'G-01', TRUE),
(5, 'EL-002', '90987-02025', 'Relé Multiuso 4 Pines 12V', 'Omron', 'Japón', 45.00, 50.00, 'Unidad', 70, 15, 30, 'P5', 'E-03', 'G-01', TRUE),
(5, 'EL-003', '90981-13075', 'Foco Halógeno H4 12V 60/55W', 'Osram', 'Alemania', 35.00, 60.00, 'Unidad', 150, 20, 50, 'P5', 'E-03', 'G-02', TRUE),
-- Refrigeración (Cat 6)
(6, 'RE-001', '16100-29155', 'Bomba de Agua con Junta y Polea', 'Aisin', 'Japón', 380.00, 28.00, 'Unidad', 15, 3, 6, 'P6', 'E-01', 'G-01', TRUE),
(6, 'RE-002', '90916-03093', 'Termostato de Motor con Empaque 82C', 'Tama', 'Japón', 85.00, 40.00, 'Unidad', 30, 6, 12, 'P6', 'E-01', 'G-02', TRUE);

-- -----------------------------------------------------------------------------
-- 5. CÓDIGOS ALTERNATIVOS
-- -----------------------------------------------------------------------------
INSERT INTO codigos_alternativos (id_producto, codigo_referencia, marca_alternativa) VALUES
(1, '0986AB2088', 'Bosch Aftermarket'),
(1, 'D-1210', 'MK Kashiyama'),
(3, 'AN-663WK', 'Akebono Japón'),
(5, 'N-1340', 'Nisshinbo'),
(6, 'BD4850', 'Fremax'),
(9, 'SE-3601', '555 Japón'),
(10, 'SB-3602', '555 Japón'),
(12, 'SL-3600', '555 Japón'),
(13, 'T2408W', 'RBI Gomas'),
(15, 'IK20TT', 'Denso Iridium TT'),
(16, 'BKR6EY-11', 'V-Power NGK'),
(17, 'W-68/3', 'Mann Filter'),
(18, '51394', 'Wix Filters'),
(19, 'C-24005', 'Mann Filter'),
(28, '64193', 'Osram Original Line'),
(30, 'WV48B-82', 'Tama Enterprises');

-- -----------------------------------------------------------------------------
-- 6. VEHÍCULOS (IDs: 1 al 12)
-- -----------------------------------------------------------------------------
INSERT INTO vehiculos (marca, modelo, anio_inicio, anio_fin, motor_cilindrada, combustible) VALUES
('Toyota', 'Corolla', 2008, 2014, '1.8L 2ZR-FE', 'Gasolina'),
('Toyota', 'Corolla', 2015, 2019, '1.8L 2ZR-FAE', 'Gasolina'),
('Toyota', 'Yaris', 2006, 2013, '1.3L 2NZ-FE', 'Gasolina'),
('Toyota', 'Hilux', 2005, 2015, '2.7L 2TR-FE', 'Gasolina'),
('Toyota', 'RAV4', 2006, 2012, '2.4L 2AZ-FE', 'Gasolina'),
('Suzuki', 'Grand Vitara', 2006, 2015, '2.0L J20A', 'Gasolina'),
('Suzuki', 'Grand Vitara', 2008, 2018, '2.4L J24B', 'Gasolina'),
('Suzuki', 'New Vitara', 2016, 2022, '1.6L M16A', 'Gasolina'),
('Suzuki', 'Swift', 2007, 2015, '1.3L M13A', 'Gasolina'),
('Suzuki', 'Jimny', 2018, 2024, '1.5L K15B', 'Gasolina'),
('Nissan', 'Tiida', 2007, 2015, '1.8L MR18DE', 'Gasolina'),
('Nissan', 'Frontier D40', 2008, 2016, '2.5L YD25DDTi', 'Diésel');

-- -----------------------------------------------------------------------------
-- 7. COMPATIBILIDADES TÉCNICAS
-- -----------------------------------------------------------------------------
INSERT INTO compatibilidades (id_producto, id_vehiculo, notas_posicion) VALUES
(1, 1, 'Eje Delantero - Versión Nacional y Americana'),
(1, 2, 'Eje Delantero - Caliper estándar'),
(2, 1, 'Eje Delantero - Diámetro 275mm'),
(2, 2, 'Eje Delantero - 5 barrenos'),
(3, 6, 'Eje Delantero - Versión 5 puertas'),
(3, 7, 'Eje Delantero - Motor J24B'),
(3, 8, 'Eje Delantero - Tracción AllGrip 4x4'),
(4, 1, 'Eje Trasero - Tambores de frenado 200mm'),
(5, 1, 'Eje Trasero - Modelos con freno a disco'),
(6, 3, 'Eje Delantero - Diámetro 255mm 4 barrenos'),
(7, 1, 'Delantero Derecho - Excluye deportiva'),
(8, 1, 'Delantero Izquierdo - Excluye deportiva'),
(9, 1, 'Lado Derecho e Izquierdo Cremallera Dirección'),
(10, 1, 'Trapecio Inferior Ambos Lados'),
(11, 6, 'Eje Trasero - Amortiguador ojo/ojo'),
(12, 1, 'Barra estabilizadora delantera'),
(13, 1, 'Buje silentblock reforzado'),
(14, 1, 'Articulación cremallera dirección'),
(15, 1, 'Luz de bujía 1.1mm precalibrada'),
(16, 6, 'Luz de bujía 1.1mm para motor 2.0L'),
(17, 1, 'Rosca 3/4-16 UNF con válvula bypass'),
(18, 6, 'Filtro roscado M20x1.5'),
(19, 1, 'Caja de filtro rectangular motor 1.8L'),
(20, 1, 'Filtro en línea de combustible'),
(21, 1, 'Empaque de goma NBR alta temperatura'),
(22, 1, 'Bomba eléctrica sumergible'),
(23, 1, 'Diámetro 212mm / 21 estrías'),
(24, 1, 'Plato diafragma reforzado'),
(25, 1, 'Rodamiento sellado libre mantenimiento'),
(26, 1, 'Bobina individual tipo lápiz'),
(27, 1, 'Relé multiuso caja de fusibles'),
(28, 1, 'Faro delantero alta y baja combinada'),
(29, 1, 'Bomba con empaque metálico'),
(30, 1, 'Apertura 82 grados centígrados');

-- =============================================================================
-- PARTE 2: PROVEEDORES, CLIENTES, COMPRAS Y CAJA
-- PROYECTO: MULTIPARTES EL TUERCA
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 8. PROVEEDORES / DISTRIBUIDORAS MAYORISTAS (IDs: 1 al 5)
-- -----------------------------------------------------------------------------
INSERT INTO proveedores (razon_social, nit, contacto_nombre, telefono, correo, direccion, condicion_comercial) VALUES
('Importadora Toyosa S.A.', '1020304050', 'Carlos Montero', '33445566', 'repuestos@toyosa.bo', 'Av. Cristo Redentor Km 4', 'Crédito 30 días'),
('Imcruz Repuestos Mayorista', '1029384756', 'Laura Gutierrez', '33556677', 'distribucion@imcruz.bo', 'Av. Banzer 3er Anillo Interno', 'Crédito 15 días'),
('Autopartes Japonesas Nippon SRL', '1928374650', 'Hiroshi Tanaka', '33221144', 'ventas@nipponparts.bo', 'Zona Parque Industrial Mza 12', 'Contado'),
('Distribuidora Automotriz Brasilera SRL', '2039485710', 'Marcos Da Silva', '33667788', 'pedidos@dabparts.bo', 'Av. Mutualista c/ C-8', 'Crédito 30 días'),
('Frenos & Embragues del Oriente', '3049586711', 'Ramiro Castro', '33889900', 'ramiro@frenosoriente.bo', 'Zona La Ramada Calle Muchirí #45', 'Contado');

-- -----------------------------------------------------------------------------
-- 9. CLIENTES PARTICULARES, TALLERES Y EMPRESAS (IDs: 1 al 10)
-- -----------------------------------------------------------------------------
INSERT INTO clientes (nombre_razon_social, ci_nit, telefono, tipo_cliente, descuento_frecuente) VALUES
('Cliente Ocasional Mostrador', '0000000', '00000000', 'Particular', 0.00),
('Taller Mecánico Especializado El Pistón', '4587123018', '77012345', 'Taller/Mecánico', 10.00),
('Serviteca y Frenos Santa Cruz', '6789123014', '78123456', 'Taller/Mecánico', 8.00),
('Flota de Taxis 24 de Septiembre', '3456789012', '76234567', 'Empresa/Flota', 12.00),
('Roberto Saucedo Vaca', '5348912', '75345678', 'Particular', 0.00),
('Ing. Marcelo Justiniano', '4891234', '71456789', 'Particular', 0.00),
('AutoElectrónica Vaca Diez', '8912345019', '79567890', 'Taller/Mecánico', 10.00),
('Constructora y Transportes Chiquitania', '1238945017', '73678901', 'Empresa/Flota', 15.00),
('Sindicato de Choferes Micros Línea 18', '7845123011', '72112233', 'Empresa/Flota', 15.00),
('Taller Mecánico Hermanos Choque', '5412987019', '76334455', 'Taller/Mecánico', 8.00);

-- -----------------------------------------------------------------------------
-- 10. SESIONES DE CAJA (IDs: 1 y 2)
-- Ambas nacen 'Abierta' para recibir movimientos y ventas sin error de trigger
-- -----------------------------------------------------------------------------
-- Sesión 1: Turno de ayer (asignada a Sebastian Baldivieso, Cajero ID 4)
INSERT INTO sesiones_cajas (id_usuario_cajero, fecha_hora_apertura, monto_inicial, estado) VALUES
(4, CURRENT_TIMESTAMP - INTERVAL '1 day 8 hours', 300.00, 'Abierta');

-- Movimientos de Sesión 1
INSERT INTO movimientos_cajas (id_sesion, tipo, monto, motivo, hora) VALUES
(1, 'Retiro Menor', 50.00, 'Compra de botellón de agua y café para oficina', CURRENT_TIMESTAMP - INTERVAL '1 day 5 hours'),
(1, 'Ingreso Extra', 100.00, 'Ingreso de sencillo adicional para cambio', CURRENT_TIMESTAMP - INTERVAL '1 day 4 hours');

-- Sesión 2: Turno activo de hoy (asignada a Andrea Torrico tras el cierre o a otro personal)
-- Para cumplir con el trigger de 1 caja abierta por cajero si está activo, usamos usuario 1 (Admin) o 5 según tu setup
INSERT INTO sesiones_cajas (id_usuario_cajero, fecha_hora_apertura, monto_inicial, estado) VALUES
(1, CURRENT_TIMESTAMP - INTERVAL '4 hours', 350.00, 'Abierta');

-- Movimientos de Sesión 2
INSERT INTO movimientos_cajas (id_sesion, tipo, monto, motivo, hora) VALUES
(2, 'Retiro Menor', 30.00, 'Envío de encomienda por moto a taller cliente', CURRENT_TIMESTAMP - INTERVAL '2 hours');

-- -----------------------------------------------------------------------------
-- 11. NOTAS DE COMPRAS (IDs: 1 al 5)
-- Registradas por Bodeguero (ID usuario 2: Cristhian Rojas)
-- -----------------------------------------------------------------------------
INSERT INTO notas_compras (id_proveedor, id_usuario_receptor, numero_factura_nota, fecha_compra, monto_total, tipo_pago, estado_pago) VALUES
(1, 2, 'FAC-008921', CURRENT_DATE - INTERVAL '10 days', 5400.00, 'Crédito', 'Pendiente'),
(2, 2, 'FAC-003412', CURRENT_DATE - INTERVAL '7 days', 3850.00, 'Contado', 'Pagado'),
(3, 2, 'FAC-011290', CURRENT_DATE - INTERVAL '4 days', 3900.00, 'Contado', 'Pagado'),
(4, 2, 'FAC-007811', CURRENT_DATE - INTERVAL '3 days', 2850.00, 'Crédito', 'Pendiente'),
(5, 2, 'FAC-001244', CURRENT_DATE - INTERVAL '1 day', 4120.00, 'Contado', 'Pagado');

-- -----------------------------------------------------------------------------
-- 12. DETALLES DE COMPRAS
-- Cada inserción disparará automáticamente el incremento de stock físico
-- -----------------------------------------------------------------------------
INSERT INTO detalles_compras (id_compra, id_producto, cantidad_recibida, costo_unitario, subtotal) VALUES
-- Compra 1 (Total: 5400.00)
(1, 1, 15, 160.00, 2400.00),
(1, 2, 10, 220.00, 2200.00),
(1, 15, 20, 40.00, 800.00),

-- Compra 2 (Total: 3850.00)
(2, 7, 5, 340.00, 1700.00),
(2, 8, 5, 340.00, 1700.00),
(2, 9, 10, 45.00, 450.00),

-- Compra 3 (Total: 3900.00)
(3, 23, 4, 390.00, 1560.00),
(3, 24, 4, 440.00, 1760.00),
(3, 25, 5, 116.00, 580.00),

-- Compra 4 (Total: 2850.00)
(4, 5, 10, 110.00, 1100.00),
(4, 6, 5, 160.00, 800.00),
(4, 12, 15, 63.33, 950.00),

-- Compra 5 (Total: 4120.00)
(5, 19, 20, 55.00, 1100.00),
(5, 22, 6, 190.00, 1140.00),
(5, 26, 4, 210.00, 840.00),
(5, 29, 3, 346.66, 1040.00);

-- =============================================================================
-- PARTE 3: COTIZACIONES, VENTAS, AJUSTES Y AUDITORÍA
-- PROYECTO: MULTIPARTES EL TUERCA
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 13. COTIZACIONES (IDs: 1 al 6)
-- -----------------------------------------------------------------------------
INSERT INTO cotizaciones (id_cliente, id_usuario, fecha_emision, fecha_vencimiento, monto_total, estado) VALUES
(2, 2, CURRENT_TIMESTAMP - INTERVAL '3 days', CURRENT_DATE + 4, 880.00, 'Vigente'),
(3, 3, CURRENT_TIMESTAMP - INTERVAL '2 days', CURRENT_DATE + 5, 1260.00, 'Vigente'),
(5, 2, CURRENT_TIMESTAMP - INTERVAL '15 days', CURRENT_DATE - 8, 520.00, 'Vencida'),
(4, 3, CURRENT_TIMESTAMP - INTERVAL '5 days', CURRENT_DATE + 2, 1645.00, 'Concretada/Facturada'),
(9, 2, CURRENT_TIMESTAMP - INTERVAL '4 days', CURRENT_DATE + 6, 810.00, 'Vigente'),
(10, 3, CURRENT_TIMESTAMP - INTERVAL '1 day', CURRENT_DATE + 5, 1425.00, 'Vigente');

-- -----------------------------------------------------------------------------
-- 14. DETALLES DE COTIZACIONES
-- Multiplicaciones exactas: subtotal = cantidad * precio_unitario_cotizado
-- -----------------------------------------------------------------------------
INSERT INTO detalles_cotizaciones (id_cotizacion, id_producto, cantidad, precio_unitario_cotizado, subtotal) VALUES
-- Cotización 1 (Total: 880.00)
(1, 1, 2, 220.00, 440.00),
(1, 2, 1, 310.00, 310.00),
(1, 9, 1, 130.00, 130.00),

-- Cotización 2 (Total: 1260.00)
(2, 7, 1, 450.00, 450.00),
(2, 8, 1, 450.00, 450.00),
(2, 10, 2, 180.00, 360.00),

-- Cotización 3 (Total: 520.00)
(3, 1, 1, 220.00, 220.00),
(3, 15, 4, 75.00, 300.00),

-- Cotización 4 (Total: 1645.00)
(4, 23, 1, 520.00, 520.00),
(4, 24, 1, 580.00, 580.00),
(4, 25, 2, 160.00, 320.00),
(4, 15, 3, 75.00, 225.00),

-- Cotización 5 (Total: 810.00)
(5, 5, 2, 160.00, 320.00),
(5, 12, 2, 95.00, 190.00),
(5, 19, 2, 85.00, 170.00),
(5, 13, 2, 65.00, 130.00),

-- Cotización 6 (Total: 1425.00)
(6, 29, 1, 380.00, 380.00),
(6, 30, 1, 85.00, 85.00),
(6, 22, 1, 280.00, 280.00),
(6, 6, 2, 240.00, 480.00),
(6, 28, 4, 50.00, 200.00);

-- -----------------------------------------------------------------------------
-- 15. NOTAS DE VENTAS (POS) (IDs: 1 al 10)
-- Ventas 1 a 4 -> Sesión 1 (Turno de ayer, abierta para recibir)
-- Ventas 5 a 10 -> Sesión 2 (Turno de hoy, abierta)
-- -----------------------------------------------------------------------------
INSERT INTO notas_ventas (id_cliente, id_usuario_vendedor, id_sesion_caja, fecha_hora, total_venta, descuento_total, metodo_pago, estado, motivo_anulacion) VALUES
(1, 2, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 6 hours', 300.00, 0.00, 'Efectivo', 'Completada', NULL),
(2, 2, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 4 hours', 510.00, 20.00, 'Transferencia/QR', 'Completada', NULL),
(5, 3, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 2 hours', 440.00, 0.00, 'Efectivo', 'Completada', NULL),
(1, 3, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 1 hour', 220.00, 0.00, 'Efectivo', 'Anulada', 'Cliente canceló la compra en mostrador por falta de saldo'),
(6, 2, 2, CURRENT_TIMESTAMP - INTERVAL '3 hours', 355.00, 0.00, 'Transferencia/QR', 'Completada', NULL),
(3, 2, 2, CURRENT_TIMESTAMP - INTERVAL '2 hours', 830.00, 50.00, 'Efectivo', 'Completada', NULL),
(9, 2, 2, CURRENT_TIMESTAMP - INTERVAL '50 minutes', 445.00, 20.00, 'Transferencia/QR', 'Completada', NULL),
(10, 3, 2, CURRENT_TIMESTAMP - INTERVAL '40 minutes', 510.00, 0.00, 'Efectivo', 'Completada', NULL),
(7, 2, 2, CURRENT_TIMESTAMP - INTERVAL '25 minutes', 240.00, 0.00, 'Efectivo', 'Completada', NULL),
(8, 3, 2, CURRENT_TIMESTAMP - INTERVAL '10 minutes', 140.00, 0.00, 'Tarjeta Débito/Crédito', 'Completada', NULL);

-- -----------------------------------------------------------------------------
-- 16. DETALLES DE VENTAS
-- Cada INSERT dispara fn_trg_descontar_stock_venta() con bloqueo pesimista
-- -----------------------------------------------------------------------------
INSERT INTO detalles_ventas (id_venta, id_producto, cantidad_vendida, precio_unitario_cobrado, subtotal) VALUES
-- Venta 1 (Total: 300.00)
(1, 15, 4, 75.00, 300.00),

-- Venta 2 (Total: 530.00 - Descuento 20.00 = 510.00)
(2, 1, 1, 220.00, 220.00),
(2, 2, 1, 310.00, 310.00),

-- Venta 3 (Total: 440.00)
(3, 1, 2, 220.00, 440.00),

-- Venta 4 (Anulada - Total: 220.00)
(4, 1, 1, 220.00, 220.00),

-- Venta 5 (Total: 355.00)
(5, 15, 4, 75.00, 300.00),
(5, 17, 1, 55.00, 55.00),

-- Venta 6 (Total: 880.00 - Descuento 50.00 = 830.00)
(6, 7, 1, 450.00, 450.00),
(6, 29, 1, 380.00, 380.00),
(6, 17, 1, 50.00, 50.00),

-- Venta 7 (Total: 465.00 - Descuento 20.00 = 445.00)
(7, 5, 1, 160.00, 160.00),
(7, 12, 2, 95.00, 190.00),
(7, 28, 2, 35.00, 70.00),
(7, 27, 1, 45.00, 45.00),

-- Venta 8 (Total: 510.00)
(8, 14, 2, 110.00, 220.00),
(8, 19, 2, 85.00, 170.00),
(8, 21, 2, 60.00, 120.00),

-- Venta 9 (Total: 240.00)
(9, 9, 2, 120.00, 240.00),

-- Venta 10 (Total: 140.00)
(10, 28, 4, 35.00, 140.00);

-- -----------------------------------------------------------------------------
-- 17. AJUSTES FÍSICOS DE INVENTARIO
-- Disparan fn_trg_aplicar_ajuste_inventario() (responsable: Bodeguero Cristhian Rojas ID 2)
-- -----------------------------------------------------------------------------
INSERT INTO ajustes_inventario (id_producto, id_usuario, cantidad_ajustada, tipo_ajuste, motivo, fecha_ajuste) VALUES
(15, 2, -2, 'Merma', 'Empaque aplastado durante descarga con electrodo fisurado', CURRENT_TIMESTAMP - INTERVAL '5 days'),
(17, 2, 3, 'Conteo Físico', 'Aparición de sobrante físico no contabilizado en estantería E-02', CURRENT_TIMESTAMP - INTERVAL '2 days'),
(1, 2, -1, 'Devolución Dañada', 'Pastilla de freno con seguro quebrado devuelta por cliente', CURRENT_TIMESTAMP - INTERVAL '1 day');

-- -----------------------------------------------------------------------------
-- 18. BITÁCORA Y AUDITORÍA HISTÓRICA
-- -----------------------------------------------------------------------------
INSERT INTO bitacoras_auditorias (id_usuario, fecha_hora, modulo_afectado, accion, registro_id, detalle_cambio, ip_origen) VALUES
(1, CURRENT_TIMESTAMP - INTERVAL '3 days', 'Seguridad / Roles', 'INSERT', 1, '{"evento": "Creación inicial de roles y permisos del sistema"}', '127.0.0.1'),
(1, CURRENT_TIMESTAMP - INTERVAL '2 days', 'Catálogo / Productos', 'UPDATE', 1, '{"producto": "Juego de Pastillas de Freno", "precio_anterior": 200.00, "precio_nuevo": 220.00}', '192.168.1.15'),
(3, CURRENT_TIMESTAMP - INTERVAL '1 day 1 hour', 'Ventas / POS', 'ANULACION', 4, '{"venta_id": 4, "motivo": "Cliente canceló compra en mostrador por falta de saldo", "monto": 220.00}', '192.168.1.20');

-- -----------------------------------------------------------------------------
-- 19. CIERRE FORMAL DE LA SESIÓN 1 (ARQUEO Y CUADRE AUTOMÁTICO)
-- El Trigger 10 calculará la diferencia y asignará 'Cuadrada'
-- -----------------------------------------------------------------------------
UPDATE sesiones_cajas 
SET monto_real_contado = monto_esperado_sistema,
    estado = 'Cerrada'
WHERE id_sesion = 1;