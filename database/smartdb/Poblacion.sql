-- =============================================================================
-- ARCHIVO MAESTRO 4/4: POBLACIÓN SEMILLA Y DATOS INICIALES AMPLIADOS
-- PROYECTO: MULTIPARTES EL TUERCA — ARQUITECTURA SMART-DB (PostgreSQL 16+)
-- ORDEN DE DESPLIEGUE: 1. Script.sql -> 2. PA.sql -> 3. Triggers.sql -> 4. Poblacion.sql
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
-- 1. ROLES (IDs: 1 al 5)
-- -----------------------------------------------------------------------------
INSERT INTO roles (id_rol, nombre_rol, descripcion) VALUES
(1, 'Administrador', 'Control total administrativo, auditoría, reportes y configuración'),
(2, 'Bodeguero', 'Recepción de mercadería, gestión de compras, estanterías y ajustes'),
(3, 'Vendedor', 'Atención en mostrador, emisión de cotizaciones y notas de venta en POS'),
(4, 'Cajero', 'Apertura y cierre de turnos, arqueo y cobro de transacciones'),
(5, 'Cliente', 'Cliente web registrado para cotizaciones y compras')
ON CONFLICT (id_rol) DO NOTHING;

SELECT setval('roles_id_rol_seq', (SELECT MAX(id_rol) FROM roles), true);

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
-- 3. CATEGORÍAS (IDs: 1 al 8)
-- -----------------------------------------------------------------------------
INSERT INTO categorias (nombre, descripcion) VALUES
('Frenos y Pastillas', 'Discos ventilados/sólidos, pastillas cerámicas, zapatas, cilindros maestros y bombas'),
('Encendido e Inyección', 'Bujías iridium/nickel, bobinas tipo lápiz, inyectores y bombas de gasolina'),
('Suspensión y Dirección', 'Amortiguadores a gas/hidráulicos, muñones, terminales, bieletas, bujes y cremalleras'),
('Filtros y Lubricantes', 'Filtros de aceite, aire, diésel, cabina, aceites sintéticos y refrigerantes OEM'),
('Motor y Distribución', 'Bombas de agua, termostatos, empaques, correas, cadenas de distribución y radiadores'),
('Embrague y Transmisión', 'Kits de embrague, discos, prensas, collarines, crucetas y juntas homocinéticas'),
('Sistema Eléctrico y Sensores', 'Alternadores, arrancadores, sensores lambda/CKP/ABS/MAF y relés automotrices'),
('Iluminación y Accesorios', 'Focos halógenos/LED, ópticas delanteras, bocinas, plumillas y líquido DOT 4');

-- -----------------------------------------------------------------------------
-- 4. PRODUCTOS (IDs: 1 al 68) CON FOTOS LOCALES DE ESTUDIO Y UBICACIÓN FÍSICA
-- Incluye: stock saludable, 7 ítems con stock bajo (<= stock_minimo) y 3 agotados (= 0)
-- -----------------------------------------------------------------------------
INSERT INTO productos (id_categoria, codigo_interno, codigo_oem, nombre_pieza, marca_fabricante, procedencia, precio_venta_sugerido, margen_ganancia, unidad_medida, stock_actual, stock_minimo, stock_seguridad, pasillo, estante, nivel_gaveta, imagen_url, estado) VALUES
-- 1. Frenos y Pastillas (Cat 1, IDs 1..10)
(1, 'FR-001', '04465-02220', 'Juego de Pastillas de Freno Delanteras', 'Bosch', 'Alemania', 220.00, 35.00, 'Juego', 38, 8, 15, 'Pasillo A', 'Estante 1', 'G-01', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-002', '43512-02110', 'Disco de Freno Ventilado Delantero', 'Fremax', 'Brasil', 310.00, 30.00, 'Unidad', 24, 4, 8, 'Pasillo A', 'Estante 1', 'G-02', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-003', '55810-77J00', 'Pastillas de Freno Cerámicas Delanteras', 'Akebono', 'Japón', 265.00, 32.00, 'Juego', 28, 6, 12, 'Pasillo A', 'Estante 2', 'G-01', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-004', '44060-8H385', 'Juego de Balatas y Zapatas Traseras', 'Cobreq', 'Brasil', 185.00, 34.00, 'Juego', 20, 5, 10, 'Pasillo A', 'Estante 2', 'G-02', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-005', '04466-02180', 'Pastillas de Freno Traseras Estándar', 'Cobreq', 'Brasil', 165.00, 32.00, 'Juego', 32, 6, 12, 'Pasillo A', 'Estante 3', 'G-01', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-006', '43512-0D060', 'Disco de Freno Delantero Sólido', 'Fremax', 'Brasil', 240.00, 30.00, 'Unidad', 18, 4, 8, 'Pasillo A', 'Estante 3', 'G-02', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-007', '04465-0K240', 'Pastillas de Freno Delanteras Hilux 4x4', 'Akebono', 'Japón', 340.00, 30.00, 'Juego', 4, 6, 12, 'Pasillo A', 'Estante 4', 'G-01', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-008', 'D1060-4KV0A', 'Pastillas de Freno Delanteras NP300', 'Bosch', 'Alemania', 295.00, 32.00, 'Juego', 22, 5, 10, 'Pasillo A', 'Estante 4', 'G-02', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-009', '58101-D7A70', 'Pastillas Delanteras Tucson / Sportage', 'Sangsin Hi-Q', 'Corea del Sur', 210.00, 35.00, 'Juego', 30, 6, 12, 'Pasillo A', 'Estante 5', 'G-01', 'images/repuestos/frenos.jpg', TRUE),
(1, 'FR-010', '47201-0K040', 'Bomba / Cilindro Maestro de Freno Hilux', 'AISIN', 'Japón', 480.00, 28.00, 'Unidad', 0, 3, 6, 'Pasillo A', 'Estante 6', 'G-01', 'images/repuestos/frenos.jpg', TRUE),

-- 2. Encendido e Inyección (Cat 2, IDs 11..18)
(2, 'EN-001', '90919-01253', 'Bujía de Encendido Iridium SC20HR11', 'Denso', 'Japón', 78.00, 45.00, 'Unidad', 96, 20, 40, 'Pasillo B', 'Estante 1', 'G-01', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-002', '09482-00547', 'Bujía Nickel BKR6E-11 Calibrada', 'NGK', 'Japón', 35.00, 50.00, 'Unidad', 140, 25, 50, 'Pasillo B', 'Estante 1', 'G-02', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-003', '90919-02258', 'Bobina de Encendido Individual Lápiz', 'Denso', 'Japón', 295.00, 32.00, 'Unidad', 28, 6, 12, 'Pasillo B', 'Estante 2', 'G-01', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-004', '27301-2B100', 'Bobina de Encendido Gamma 1.6L', 'Bosch', 'Corea del Sur', 245.00, 34.00, 'Unidad', 22, 5, 10, 'Pasillo B', 'Estante 2', 'G-02', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-005', '23221-0D010', 'Bomba Eléctrica de Gasolina 3 Bar', 'Denso', 'Japón', 285.00, 32.00, 'Unidad', 18, 5, 10, 'Pasillo B', 'Estante 3', 'G-01', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-006', '23250-0T020', 'Inyector de Combustible Multipunto 1.8L', 'Denso', 'Japón', 420.00, 28.00, 'Unidad', 3, 4, 8, 'Pasillo B', 'Estante 3', 'G-02', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-007', '90919-01275', 'Bujía Láser Iridium ILKAR7B11', 'NGK', 'Japón', 92.00, 42.00, 'Unidad', 64, 12, 24, 'Pasillo B', 'Estante 4', 'G-01', 'images/repuestos/encendido.jpg', TRUE),
(2, 'EN-008', '22448-JA00C', 'Bobina de Encendido Nissan 1.8 / 2.0', 'Hitachi', 'Japón', 310.00, 30.00, 'Unidad', 16, 4, 8, 'Pasillo B', 'Estante 4', 'G-02', 'images/repuestos/encendido.jpg', TRUE),

-- 3. Suspensión y Dirección (Cat 3, IDs 19..28)
(3, 'SU-001', '48510-09L30', 'Amortiguador Delantero Derecho Gas', 'KYB', 'Japón', 460.00, 28.00, 'Unidad', 16, 4, 8, 'Pasillo C', 'Estante 1', 'G-01', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-002', '48520-09L30', 'Amortiguador Delantero Izquierdo Gas', 'KYB', 'Japón', 460.00, 28.00, 'Unidad', 16, 4, 8, 'Pasillo C', 'Estante 1', 'G-02', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-003', '45046-09251', 'Terminal de Dirección Exterior', '555 Three Five', 'Japón', 135.00, 40.00, 'Unidad', 45, 8, 16, 'Pasillo C', 'Estante 2', 'G-01', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-004', '43330-09510', 'Muñón / Rótula de Suspensión Inferior', '555 Three Five', 'Japón', 180.00, 36.00, 'Unidad', 32, 8, 15, 'Pasillo C', 'Estante 2', 'G-02', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-005', '41800-65J00', 'Amortiguador Trasero Hidráulico', 'Monroe', 'EE.UU.', 325.00, 30.00, 'Unidad', 20, 5, 10, 'Pasillo C', 'Estante 3', 'G-01', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-006', '48820-02030', 'Bieleta Estabilizadora Delantera', '555 Three Five', 'Japón', 98.00, 42.00, 'Unidad', 52, 10, 20, 'Pasillo C', 'Estante 3', 'G-02', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-007', '48655-02050', 'Buje Grande de Trapecio Inferior', 'RBI', 'Tailandia', 68.00, 45.00, 'Unidad', 75, 15, 30, 'Pasillo C', 'Estante 4', 'G-01', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-008', '45503-09321', 'Terminal Axial de Dirección', 'CTR', 'Corea del Sur', 115.00, 38.00, 'Unidad', 38, 8, 16, 'Pasillo C', 'Estante 4', 'G-02', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-009', '341362-KYB', 'Amortiguador Delantero Hilux Revo 4x4', 'KYB', 'Japón', 540.00, 28.00, 'Unidad', 2, 4, 8, 'Pasillo C', 'Estante 5', 'G-01', 'images/repuestos/suspension.jpg', TRUE),
(3, 'SU-010', '44200-02510', 'Cremallera de Dirección Electroasistida', 'CTR', 'Corea del Sur', 1850.00, 26.00, 'Unidad', 0, 2, 4, 'Pasillo C', 'Estante 6', 'G-01', 'images/repuestos/suspension.jpg', TRUE),

-- 4. Filtros y Lubricantes (Cat 4, IDs 29..38)
(4, 'FL-001', '90915-YZZE1', 'Filtro de Aceite de Motor Blindado', 'Toyota OEM', 'Tailandia', 58.00, 40.00, 'Unidad', 90, 15, 30, 'Pasillo D', 'Estante 1', 'G-01', 'images/repuestos/filtros.jpg', TRUE),
(4, 'FL-002', '16510-61A31', 'Filtro de Aceite Metálico Roscado', 'Mann-Filter', 'Alemania', 52.00, 42.00, 'Unidad', 70, 12, 25, 'Pasillo D', 'Estante 1', 'G-02', 'images/repuestos/filtros.jpg', TRUE),
(4, 'FL-003', '17801-21050', 'Filtro de Aire de Motor Seco', 'Mann-Filter', 'Alemania', 88.00, 40.00, 'Unidad', 48, 10, 20, 'Pasillo D', 'Estante 2', 'G-01', 'images/repuestos/filtros.jpg', TRUE),
(4, 'FL-004', '23300-21010', 'Filtro de Gasolina de Línea Blindado', 'Denso', 'Japón', 115.00, 35.00, 'Unidad', 30, 6, 12, 'Pasillo D', 'Estante 2', 'G-02', 'images/repuestos/filtros.jpg', TRUE),
(4, 'FL-005', '23390-0L070', 'Elemento Filtro de Diésel Hilux/Prado', 'Sakura', 'Japón', 125.00, 38.00, 'Unidad', 4, 6, 12, 'Pasillo D', 'Estante 3', 'G-01', 'images/repuestos/filtros.jpg', TRUE),
(4, 'FL-006', '87139-02090', 'Filtro de Cabina Carbón Activado', 'Sakura', 'Indonesia', 95.00, 40.00, 'Unidad', 40, 8, 15, 'Pasillo D', 'Estante 3', 'G-02', 'images/repuestos/filtros.jpg', TRUE),
(4, 'FL-007', 'CAS-EDGE-5W30', 'Aceite Sintético Edge 5W-30 Galón 4L', 'Castrol', 'EE.UU.', 295.00, 30.00, 'Galón', 42, 8, 16, 'Pasillo D', 'Estante 4', 'G-01', 'images/repuestos/lubricantes.jpg', TRUE),
(4, 'FL-008', 'MOB1-ESP-5W30', 'Aceite Full Sintético Mobil 1 5W-30 4L', 'Mobil 1', 'EE.UU.', 335.00, 28.00, 'Galón', 34, 8, 15, 'Pasillo D', 'Estante 4', 'G-02', 'images/repuestos/lubricantes.jpg', TRUE),
(4, 'FL-009', '08889-80072', 'Refrigerante Super Long Life Rosa 4L', 'Toyota OEM', 'Japón', 165.00, 35.00, 'Galón', 36, 8, 15, 'Pasillo D', 'Estante 5', 'G-01', 'images/repuestos/lubricantes.jpg', TRUE),
(4, 'FL-010', 'CAS-GTX-10W40', 'Aceite Semi-Sintético GTX 10W-40 4L', 'Castrol', 'Brasil', 210.00, 32.00, 'Galón', 50, 10, 20, 'Pasillo D', 'Estante 5', 'G-02', 'images/repuestos/lubricantes.jpg', TRUE),

-- 5. Motor y Distribución (Cat 5, IDs 39..46)
(5, 'MO-001', '16100-29155', 'Bomba de Agua con Junta y Polea', 'AISIN', 'Japón', 390.00, 28.00, 'Unidad', 18, 4, 8, 'Pasillo E', 'Estante 1', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-002', '90916-03093', 'Termostato de Motor con Empaque 82C', 'Tama', 'Japón', 88.00, 42.00, 'Unidad', 32, 6, 12, 'Pasillo E', 'Estante 1', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-003', '11213-22050', 'Empaque de Tapa de Válvulas NBR', 'Stone', 'Japón', 72.00, 48.00, 'Unidad', 42, 8, 16, 'Pasillo E', 'Estante 2', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-004', '13568-09131', 'Kit Correa de Distribución y Templador', 'Gates', 'EE.UU.', 560.00, 30.00, 'Kit', 14, 3, 6, 'Pasillo E', 'Estante 2', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-005', '6PK1230', 'Correa Poly-V Accesorios / Alternador', 'Gates', 'EE.UU.', 135.00, 40.00, 'Unidad', 36, 8, 16, 'Pasillo E', 'Estante 3', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-006', '13506-37010', 'Kit Cadena de Distribución Completo 2ZR', 'OSK', 'Japón', 890.00, 28.00, 'Kit', 2, 3, 6, 'Pasillo E', 'Estante 3', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-007', '11115-0C020', 'Empaque de Culata Multilámina Metálica', 'Stone', 'Japón', 340.00, 35.00, 'Unidad', 12, 3, 6, 'Pasillo E', 'Estante 4', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(5, 'MO-008', '16400-0L210', 'Radiador de Aluminio Brazado Hilux', 'Koyorad', 'Japón', 980.00, 26.00, 'Unidad', 8, 2, 4, 'Pasillo E', 'Estante 5', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),

-- 6. Embrague y Transmisión (Cat 6, IDs 47..54)
(6, 'TR-001', '31250-0D160', 'Disco de Embrague Mecánico 212mm', 'AISIN', 'Japón', 525.00, 28.00, 'Unidad', 14, 3, 6, 'Pasillo E', 'Estante 6', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-002', '31210-0D130', 'Prensa / Plato Opresor de Embrague', 'AISIN', 'Japón', 585.00, 28.00, 'Unidad', 12, 3, 6, 'Pasillo E', 'Estante 6', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-003', '31230-12180', 'Rodamiento / Collarín de Empuje', 'Koyo', 'Japón', 165.00, 36.00, 'Unidad', 26, 5, 10, 'Pasillo F', 'Estante 1', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-004', 'TYK-1508', 'Kit de Embrague Completo Hilux 2.7L', 'Exedy', 'Japón', 1450.00, 26.00, 'Kit', 10, 3, 6, 'Pasillo F', 'Estante 1', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-005', '826842-VAL', 'Kit de Embrague Completo Tucson/Sportage', 'Valeo', 'Corea del Sur', 1290.00, 28.00, 'Kit', 11, 3, 6, 'Pasillo F', 'Estante 2', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-006', 'GUT-23', 'Cruceta de Cardán Reforzada con Grasero', 'GMB', 'Japón', 145.00, 38.00, 'Unidad', 30, 6, 12, 'Pasillo F', 'Estante 2', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-007', 'TO-043-HDK', 'Junta Homocinética Lado Rueda con Guardapolvo', 'HDK', 'Japón', 360.00, 32.00, 'Unidad', 3, 4, 8, 'Pasillo F', 'Estante 3', 'G-01', 'images/repuestos/motor_transmision.jpg', TRUE),
(6, 'TR-008', '30502-69F10', 'Collarín Hidráulico Esclavo Sentra/Qashqai', 'Valeo', 'Francia', 490.00, 30.00, 'Unidad', 0, 2, 5, 'Pasillo F', 'Estante 3', 'G-02', 'images/repuestos/motor_transmision.jpg', TRUE),

-- 7. Sistema Eléctrico y Sensores (Cat 7, IDs 55..61)
(7, 'EL-001', '90987-02025', 'Relé Multiuso 4 Pines 12V Caja Fusibles', 'Omron', 'Japón', 48.00, 50.00, 'Unidad', 68, 15, 30, 'Pasillo F', 'Estante 4', 'G-01', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(7, 'EL-002', '89465-02330', 'Sensor de Oxígeno Lambda Banco 1', 'Denso', 'Japón', 440.00, 30.00, 'Unidad', 14, 4, 8, 'Pasillo F', 'Estante 4', 'G-02', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(7, 'EL-003', '90919-05060', 'Sensor de Posición de Cigüeñal CKP', 'Denso', 'Japón', 280.00, 34.00, 'Unidad', 18, 4, 8, 'Pasillo F', 'Estante 4', 'G-03', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(7, 'EL-004', '27060-0T041', 'Alternador Completo 12V 100A Regulado', 'Denso', 'Japón', 1680.00, 25.00, 'Unidad', 6, 2, 4, 'Pasillo F', 'Estante 5', 'G-01', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(7, 'EL-005', '28100-0L080', 'Motor de Arranque / Burro 12V Hilux', 'Bosch', 'Brasil', 1520.00, 26.00, 'Unidad', 7, 2, 4, 'Pasillo F', 'Estante 5', 'G-02', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(7, 'EL-006', '89542-0K020', 'Sensor ABS de Rueda Delantera Derecha', 'Bosch', 'Alemania', 240.00, 35.00, 'Unidad', 2, 4, 8, 'Pasillo F', 'Estante 5', 'G-03', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(7, 'EL-007', '22680-7S000', 'Sensor Flujo de Aire MAF Nissan/Mazda', 'Hitachi', 'Japón', 520.00, 30.00, 'Unidad', 10, 3, 6, 'Pasillo F', 'Estante 6', 'G-01', 'images/repuestos/electrico_iluminacion.jpg', TRUE),

-- 8. Iluminación y Accesorios (Cat 8, IDs 62..68)
(8, 'IL-001', '90981-13075', 'Foco Halógeno H4 12V 60/55W Original', 'Osram', 'Alemania', 38.00, 55.00, 'Unidad', 120, 20, 40, 'Pasillo F', 'Estante 6', 'G-02', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(8, 'IL-002', 'OSR-LED-H4', 'Kit Focos LED H4 Cool White 6000K Par', 'Osram', 'Alemania', 290.00, 35.00, 'Juego', 24, 5, 10, 'Pasillo F', 'Estante 6', 'G-03', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(8, 'IL-003', 'PHI-H11-12V', 'Foco Halógeno H11 12V 55W Neblinero', 'Philips', 'Alemania', 55.00, 48.00, 'Unidad', 60, 12, 24, 'Pasillo B', 'Estante 5', 'G-01', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(8, 'IL-004', '81110-0K690', 'Farol / Óptica Delantera Derecha Hilux', 'TYC', 'Taiwán', 780.00, 28.00, 'Unidad', 8, 2, 4, 'Pasillo B', 'Estante 5', 'G-02', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(8, 'IL-005', 'BOS-EC6-HORN', 'Juego de Bocinas Claxon Caracol 12V', 'Bosch', 'Alemania', 140.00, 38.00, 'Juego', 25, 5, 10, 'Pasillo B', 'Estante 6', 'G-01', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(8, 'IL-006', 'BOS-AERO-2416', 'Par Plumillas Limpiaparabrisas Aerotwin', 'Bosch', 'Bélgica', 135.00, 40.00, 'Juego', 34, 8, 16, 'Pasillo B', 'Estante 6', 'G-02', 'images/repuestos/electrico_iluminacion.jpg', TRUE),
(8, 'IL-007', 'DOT4-BOS-500', 'Líquido de Frenos DOT 4 Alta Temp 500ml', 'Bosch', 'Alemania', 58.00, 45.00, 'Unidad', 55, 12, 24, 'Pasillo A', 'Estante 5', 'G-02', 'images/repuestos/lubricantes.jpg', TRUE);

-- -----------------------------------------------------------------------------
-- 5. CÓDIGOS ALTERNATIVOS (36 referencias cruzadas)
-- -----------------------------------------------------------------------------
INSERT INTO codigos_alternativos (id_producto, codigo_referencia, marca_alternativa) VALUES
(1, '0986AB2088', 'Bosch Aftermarket'),
(1, 'D-1210', 'MK Kashiyama'),
(2, 'BD4855', 'Fremax Ventilado'),
(3, 'AN-663WK', 'Akebono Japón'),
(5, 'N-1340', 'Nisshinbo'),
(6, 'BD4850', 'Fremax'),
(7, 'AN-735WK', 'Akebono Heavy Duty'),
(8, 'D-1844', 'Sangsin Brake'),
(9, 'SP-1849', 'Hi-Q Korea'),
(11, 'IK20TT', 'Denso Iridium TT'),
(11, '90919-01275', 'Toyota Alternativo'),
(12, 'BKR6EY-11', 'NGK V-Power'),
(13, 'UF-596', 'Standard Ignition'),
(14, '27301-2B010', 'Hyundai Mobis'),
(15, 'DFP-0106', 'Denso Fuel Pump'),
(17, 'LZKAR7A', 'NGK Iridium'),
(19, '339114', 'KYB Excel-G'),
(20, '339115', 'KYB Excel-G'),
(21, 'SE-3601', '555 Japón'),
(22, 'SB-3602', '555 Japón'),
(24, 'SL-3600', '555 Japón'),
(25, 'T2408W', 'RBI Gomas'),
(26, 'CRKH-42', 'CTR Korea'),
(29, 'W-68/3', 'Mann-Filter'),
(29, 'PH-4967', 'Fram Extra Guard'),
(30, '51394', 'Wix Filters'),
(31, 'C-24005', 'Mann-Filter'),
(33, 'F-11240', 'Sakura Diesel'),
(34, 'CAC-1114', 'Sakura Bio-Guard'),
(39, 'WPT-140', 'AISIN Water Pump'),
(40, 'WV48B-82', 'Tama Enterprises'),
(42, 'TCK-328', 'Gates PowerGrip'),
(47, 'DT-124V', 'AISIN Clutch'),
(50, 'TYK-1505', 'Exedy Heavy Duty'),
(56, 'DOX-0109', 'Denso Universal'),
(62, '64193', 'Osram Original Line');

-- -----------------------------------------------------------------------------
-- 6. VEHÍCULOS (IDs: 1 al 27 - PARQUE AUTOMOTOR SANTA CRUZ, BOLIVIA)
-- -----------------------------------------------------------------------------
INSERT INTO vehiculos (marca, modelo, anio_inicio, anio_fin, motor_cilindrada, combustible) VALUES
-- Toyota (IDs 1..6)
('Toyota', 'Hilux', 2016, 2024, '2.7L 2TR-FE', 'Gasolina'),
('Toyota', 'Hilux', 2016, 2024, '2.8L 1GD-FTV', 'Diésel'),
('Toyota', 'Corolla', 2014, 2023, '1.8L 2ZR-FE', 'Gasolina'),
('Toyota', 'Yaris', 2015, 2023, '1.5L 2NR-FE', 'Gasolina'),
('Toyota', 'RAV4', 2015, 2023, '2.0L 3ZR-FAE', 'Gasolina'),
('Toyota', 'Land Cruiser Prado', 2012, 2022, '4.0L 1GR-FE', 'Gasolina'),
-- Suzuki (IDs 7..10)
('Suzuki', 'Grand Vitara', 2010, 2020, '2.4L J24B', 'Gasolina'),
('Suzuki', 'Swift', 2014, 2024, '1.2L K12M', 'Gasolina'),
('Suzuki', 'Jimny', 2019, 2025, '1.5L K15B', 'Gasolina'),
('Suzuki', 'Alto 800', 2015, 2023, '0.8L F8D', 'Gasolina'),
-- Nissan (IDs 11..14)
('Nissan', 'Frontier NP300', 2016, 2024, '2.5L YD25DDTi', 'Diésel'),
('Nissan', 'Sentra', 2014, 2022, '1.8L MRA8DE', 'Gasolina'),
('Nissan', 'Versa', 2015, 2023, '1.6L HR16DE', 'Gasolina'),
('Nissan', 'Qashqai', 2015, 2022, '2.0L MR20DD', 'Gasolina'),
-- Hyundai (IDs 15..18)
('Hyundai', 'Tucson', 2016, 2024, '2.0L Nu MPi', 'Gasolina'),
('Hyundai', 'Santa Fe', 2014, 2022, '2.4L Theta II', 'Gasolina'),
('Hyundai', 'Accent', 2013, 2022, '1.4L Kappa', 'Gasolina'),
('Hyundai', 'Elantra', 2015, 2022, '1.6L Gamma', 'Gasolina'),
-- Kia (IDs 19..22)
('Kia', 'Sportage', 2016, 2024, '2.0L Nu MPi', 'Gasolina'),
('Kia', 'Rio', 2014, 2023, '1.4L Kappa', 'Gasolina'),
('Kia', 'Cerato', 2015, 2022, '1.6L Gamma', 'Gasolina'),
('Kia', 'Sorento', 2015, 2022, '2.4L Theta II', 'Gasolina'),
-- Mitsubishi (IDs 23..24)
('Mitsubishi', 'L200 Triton', 2016, 2024, '2.4L 4N15', 'Diésel'),
('Mitsubishi', 'Montero Sport', 2015, 2022, '3.0L 6B31', 'Gasolina'),
-- Mazda (IDs 25..27)
('Mazda', 'CX-5', 2016, 2023, '2.0L SkyActiv-G', 'Gasolina'),
('Mazda', 'BT-50', 2015, 2022, '3.2L P5AT', 'Diésel'),
('Mazda', 'Mazda 3', 2015, 2022, '2.0L SkyActiv-G', 'Gasolina');

-- -----------------------------------------------------------------------------
-- 7. COMPATIBILIDADES TÉCNICAS (122 asignaciones exactas para los 68 productos)
-- -----------------------------------------------------------------------------
INSERT INTO compatibilidades (id_producto, id_vehiculo, notas_posicion) VALUES
-- Frenos (Prods 1..10)
(1, 3, 'Eje Delantero - Caliper estándar 275mm'),
(1, 5, 'Eje Delantero - Versión 4x2 y 4x4'),
(2, 3, 'Eje Delantero - Disco ventilado 5 barrenos'),
(2, 5, 'Eje Delantero - Diámetro 296mm'),
(3, 7, 'Eje Delantero - Versión 5 puertas J24B'),
(3, 8, 'Eje Delantero - Swift GLX'),
(3, 9, 'Eje Delantero - Jimny AllGrip 4x4'),
(4, 3, 'Eje Trasero - Zapatas freno tambor'),
(4, 4, 'Eje Trasero - Tambor 200mm'),
(5, 3, 'Eje Trasero - Modelos con disco sólido'),
(5, 5, 'Eje Trasero - Freno a disco'),
(6, 4, 'Eje Delantero - 4 barrenos 255mm'),
(6, 10, 'Eje Delantero - Alto 800 estándar'),
(7, 1, 'Eje Delantero - Hilux Revo 2.7L 4x4'),
(7, 2, 'Eje Delantero - Hilux Revo 2.8L Turbo Diésel'),
(7, 6, 'Eje Delantero - Prado TXL 4.0L'),
(8, 11, 'Eje Delantero - Frontier NP300 4x2/4x4'),
(8, 23, 'Eje Delantero - L200 Triton'),
(9, 15, 'Eje Delantero - Tucson TL'),
(9, 19, 'Eje Delantero - Sportage QL'),
(9, 16, 'Eje Delantero - Santa Fe DM'),
(10, 1, 'Compartimento Motor - Bomba doble circuito'),
(10, 2, 'Compartimento Motor - Con sensor de nivel'),

-- Encendido (Prods 11..18)
(11, 3, 'Culata - Luz precalibrada 1.1mm'),
(11, 4, 'Culata - Motor Dual VVT-i'),
(11, 5, 'Culata - Rosca larga 14mm'),
(12, 7, 'Culata - Motor J24B'),
(12, 10, 'Culata - 3 cilindros F8D'),
(12, 17, 'Culata - Accent 1.4L'),
(13, 3, 'Tapa de válvulas - Bobina tipo lápiz'),
(13, 4, 'Tapa de válvulas - Motor 2NR-FE'),
(13, 5, 'Tapa de válvulas - Motor 3ZR-FAE'),
(14, 17, 'Tapa de válvulas - Encendido directo'),
(14, 18, 'Tapa de válvulas - Motor Gamma 1.6L'),
(14, 20, 'Tapa de válvulas - Kia Rio 1.4L'),
(14, 21, 'Tapa de válvulas - Kia Cerato 1.6L'),
(15, 3, 'Tanque combustible - Presión 3.0 Bar'),
(15, 4, 'Tanque combustible - Módulo sumergible'),
(15, 7, 'Tanque combustible - Bomba auxiliar'),
(16, 3, 'Riel de inyección - 12 orificios'),
(16, 5, 'Riel de inyección - Con O-rings incluidos'),
(17, 12, 'Culata - Motor MRA8DE Iridium'),
(17, 13, 'Culata - Motor HR16DE'),
(17, 14, 'Culata - Motor MR20DD'),
(18, 12, 'Tapa de válvulas - Nissan Sentra'),
(18, 13, 'Tapa de válvulas - Nissan Versa'),
(18, 14, 'Tapa de válvulas - Nissan Qashqai'),

-- Suspensión (Prods 19..28)
(19, 3, 'Delantero Derecho - Tipo McPherson Gas'),
(19, 4, 'Delantero Derecho - Presurizado nitrógeno'),
(20, 3, 'Delantero Izquierdo - Tipo McPherson Gas'),
(20, 4, 'Delantero Izquierdo - Presurizado nitrógeno'),
(21, 3, 'Cremallera lado derecho e izquierdo'),
(21, 4, 'Terminal exterior rosca M14'),
(21, 17, 'Dirección delantera ambos lados'),
(22, 3, 'Trapecio inferior ambos lados'),
(22, 5, 'Brazo inferior suspensión delantera'),
(22, 15, 'Suspensión delantera Tucson'),
(23, 7, 'Eje Trasero - Ojo / Ojo reforzado'),
(23, 9, 'Eje Trasero - Suzuki Jimny'),
(24, 3, 'Barra estabilizadora delantera'),
(24, 5, 'Enlace estabilizador ambos lados'),
(24, 19, 'Barra estabilizadora Kia Sportage'),
(25, 3, 'Buje silentblock grande de bandeja'),
(25, 4, 'Buje posterior de trapecio'),
(26, 3, 'Articulación interna cremallera dirección'),
(26, 15, 'Axial dirección Hyundai Tucson'),
(26, 19, 'Axial dirección Kia Sportage'),
(27, 1, 'Delantero ambos lados - Hilux 2.7L'),
(27, 2, 'Delantero ambos lados - Hilux 2.8L'),
(28, 3, 'Caja dirección electroasistida completa'),
(28, 4, 'Cremallera eléctrica EPS'),

-- Filtros y Lubricantes (Prods 29..38)
(29, 3, 'Motor - Rosca 3/4-16 UNF con válvula'),
(29, 4, 'Motor - Blindado alta retención'),
(29, 5, 'Motor - Compatible 2.0L'),
(30, 7, 'Motor - Filtro roscado M20x1.5'),
(30, 8, 'Motor - Suzuki Swift 1.2L'),
(30, 9, 'Motor - Suzuki Jimny K15B'),
(30, 25, 'Motor - Mazda CX-5 SkyActiv'),
(31, 3, 'Caja de filtro rectangular 1.8L'),
(31, 4, 'Carcasa filtro de aire'),
(32, 3, 'Línea de combustible alta presión'),
(32, 1, 'Línea de combustible gasolina 2.7L'),
(33, 2, 'Trampa de agua y diésel Hilux 2.8L'),
(33, 11, 'Sistema diésel Common Rail NP300'),
(33, 23, 'Filtro primario diésel L200 Triton'),
(34, 3, 'Guantera - Filtro antipolen y olores'),
(34, 1, 'Guantera - Cabina Hilux Revo'),
(34, 15, 'Guantera - Aire acondicionado Tucson'),
(35, 3, 'Motor gasolina moderno 5W-30 API SP'),
(35, 15, 'Motor Hyundai Tucson / Elantra'),
(35, 25, 'Motor Mazda SkyActiv-G'),
(36, 1, 'Motor Hilux gasolina / flotas'),
(36, 5, 'Motor RAV4 2.0L'),
(36, 19, 'Motor Kia Sportage'),
(37, 1, 'Radiador y depósito expansión Toyota'),
(37, 2, 'Sistema enfriamiento Hilux 2.8L'),
(37, 3, 'Circuito refrigeración Toyota Corolla'),
(38, 7, 'Motor gasolina alto kilometraje'),
(38, 17, 'Motor Hyundai Accent 1.4L'),
(38, 20, 'Motor Kia Rio 1.4L'),

-- Motor y Distribución (Prods 39..46)
(39, 3, 'Frontal motor con junta metálica'),
(39, 5, 'Sistema enfriamiento motor ZR'),
(40, 3, 'Carcasa termostato 82 grados C'),
(40, 7, 'Carcasa agua motor J24B'),
(41, 3, 'Tapa de válvulas caucho NBR'),
(41, 4, 'Tapa culata motor 2NR-FE'),
(42, 11, 'Distribución frontal con tensor'),
(42, 23, 'Kit distribución L200 Triton'),
(43, 3, 'Correa alternador / aire acondicionado'),
(43, 15, 'Correa accesorios Tucson 2.0L'),
(44, 3, 'Distribución completa cadena + guías'),
(44, 5, 'Distribución motor 2.0L 3ZR'),
(45, 1, 'Culata motor 2TR-FE 2.7L'),
(45, 6, 'Motor 1GR-FE banco cilindros'),
(46, 1, 'Frontal - Radiador Hilux gasolina'),
(46, 2, 'Frontal - Radiador Hilux turbo diésel'),

-- Embrague y Transmisión (Prods 47..54)
(47, 3, 'Caja mecánica - 212mm / 21 estrías'),
(47, 4, 'Caja mecánica 5 velocidades'),
(48, 3, 'Plato opresor diafragma reforzado'),
(48, 4, 'Prensa de embrague Toyota'),
(49, 3, 'Horquilla caja mecánica'),
(49, 8, 'Collarín sellado libre mantenimiento'),
(50, 1, 'Caja Hilux 2.7L - Disco + Prensa + Collarín'),
(50, 2, 'Caja Hilux 4x4 - Alta resistencia'),
(51, 15, 'Caja mecánica 6v Hyundai Tucson'),
(51, 19, 'Caja mecánica 6v Kia Sportage'),
(52, 1, 'Cardán trasero transmisión 4x4'),
(52, 11, 'Cardán trasero Frontier NP300'),
(52, 26, 'Cardán Mazda BT-50'),
(53, 3, 'Palier delantero lado rueda 26 estrías'),
(53, 7, 'Punta de eje delantero'),
(54, 12, 'Collarín hidráulico concéntrico Sentra'),
(54, 14, 'Collarín hidráulico Qashqai'),

-- Sistema Eléctrico y Sensores (Prods 55..61)
(55, 3, 'Caja de fusibles compartimento motor'),
(55, 1, 'Relé electroventilador / bocina'),
(56, 3, 'Múltiple de escape antes de catalizador'),
(56, 5, 'Sensor O2 4 cables conector OEM'),
(57, 3, 'Bloque motor lectura volante'),
(57, 1, 'Sensor CKP Hilux 2TR-FE'),
(58, 3, 'Motor polea 6 canales 100 Amperios'),
(58, 5, 'Alternador regulado RAV4'),
(59, 1, 'Arrancador 12V piñón 9 dientes'),
(59, 2, 'Burro arranque alto torque Hilux'),
(60, 1, 'Maza rueda delantera derecha ABS'),
(60, 2, 'Sensor velocidad rueda Hilux Revo'),
(61, 12, 'Ducto admisión aire MAF Nissan'),
(61, 25, 'Caudalímetro Mazda CX-5'),

-- Iluminación y Accesorios (Prods 62..68)
(62, 1, 'Faro principal luz alta y baja'),
(62, 7, 'Óptica delantera estándar H4'),
(62, 17, 'Faro principal Hyundai Accent'),
(63, 1, 'Faro principal actualización LED 6000K'),
(63, 11, 'Óptica delantera Frontier NP300'),
(64, 3, 'Neblinero delantero parachoques'),
(64, 15, 'Farol antiniebla Hyundai Tucson'),
(65, 1, 'Faro delantero derecho Hilux Revo'),
(65, 2, 'Faro delantero derecho con luz día'),
(66, 3, 'Soporte frontal doble tono grave/agudo'),
(66, 19, 'Claxon universal 12V Kia Sportage'),
(67, 3, 'Parabrisas delantero medida 24" + 16"'),
(67, 15, 'Parabrisas delantero Tucson'),
(68, 1, 'Depósito hidráulico frenos y embrague'),
(68, 11, 'Sistema frenos ABS alta temperatura');

-- =============================================================================
-- PARTE 2: PROVEEDORES, CLIENTES, CAJA Y COMPRAS
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 8. PROVEEDORES / DISTRIBUIDORAS MAYORISTAS (IDs: 1 al 6)
-- -----------------------------------------------------------------------------
INSERT INTO proveedores (razon_social, nit, contacto_nombre, telefono, correo, direccion, condicion_comercial) VALUES
('Importadora Toyosa S.A.', '1020304050', 'Carlos Montero', '33445566', 'repuestos@toyosa.bo', 'Av. Cristo Redentor Km 4', 'Crédito 30 días'),
('Imcruz Repuestos Mayorista', '1029384756', 'Laura Gutierrez', '33556677', 'distribucion@imcruz.bo', 'Av. Banzer 3er Anillo Interno', 'Crédito 15 días'),
('Autopartes Japonesas Nippon SRL', '1928374650', 'Hiroshi Tanaka', '33221144', 'ventas@nipponparts.bo', 'Zona Parque Industrial Mza 12', 'Contado'),
('Distribuidora Automotriz Brasilera SRL', '2039485710', 'Marcos Da Silva', '33667788', 'pedidos@dabparts.bo', 'Av. Mutualista c/ C-8', 'Crédito 30 días'),
('Frenos & Embragues del Oriente', '3049586711', 'Ramiro Castro', '33889900', 'ramiro@frenosoriente.bo', 'Zona La Ramada Calle Muchirí #45', 'Contado'),
('Lubricantes y Filtros Bolivia SRL', '4120394815', 'Patricia Méndez', '33771122', 'ventas@lubribol.bo', 'Av. Grigotá 4to Anillo #120', 'Crédito 60 días');

-- -----------------------------------------------------------------------------
-- 9. CLIENTES PARTICULARES, TALLERES Y EMPRESAS (IDs: 1 al 14)
-- -----------------------------------------------------------------------------
INSERT INTO clientes (nombre_razon_social, ci_nit, telefono, tipo_cliente, descuento_frecuente) VALUES
('Cliente Ocasional Mostrador', '0000000', '00000000', 'Particular', 0.00),
('Taller Mecánico Especializado El Pistón', '4587123018', '77012345', 'Taller/Mecánico', 10.00),
('Serviteca y Frenos Santa Cruz', '6789123014', '78123456', 'Taller/Mecánico', 8.00),
('Flota de Taxis 24 de Septiembre', '3456789012', '76234567', 'Empresa/Flota', 12.00),
('Roberto Saucedo Vaca', '5348912', '75345678', 'Particular', 0.00),
('Ing. Marcelo Justiniano', '4891234', '71456789', 'Particular', 0.00),
('AutoElectrónica Vaca Diez', '8912345019', '79567890', 'Taller/Mecánico', 10.00),
('Constructora y Transportes Chiquitania', '1238945017', '73678901', 'Empresa/Flota', 12.00),
('Sindicato de Choferes Micros Línea 18', '7845123011', '72112233', 'Empresa/Flota', 12.00),
('Taller Mecánico Hermanos Choque', '5412987019', '76334455', 'Taller/Mecánico', 8.00),
('Centro Automotriz Pirai', '6512098014', '77889911', 'Taller/Mecánico', 5.00),
('Agropecuaria Norte Integrado SRL', '3344556019', '75556677', 'Empresa/Flota', 10.00),
('Taller Diesel Montero Express', '7123456015', '76998877', 'Taller/Mecánico', 8.00),
('Claudia Patricia Rojas Arce', '6234891', '70991122', 'Particular', 0.00);

-- -----------------------------------------------------------------------------
-- 10. SESIONES DE CAJA (IDs: 1 y 2)
-- -----------------------------------------------------------------------------
INSERT INTO sesiones_cajas (id_usuario_cajero, fecha_hora_apertura, monto_inicial, estado) VALUES
(4, CURRENT_TIMESTAMP - INTERVAL '1 day 8 hours', 300.00, 'Abierta');

INSERT INTO movimientos_cajas (id_sesion, tipo, monto, motivo, hora) VALUES
(1, 'Retiro Menor', 50.00, 'Compra de botellón de agua y café para oficina', CURRENT_TIMESTAMP - INTERVAL '1 day 5 hours'),
(1, 'Ingreso Extra', 100.00, 'Ingreso de sencillo adicional para cambio', CURRENT_TIMESTAMP - INTERVAL '1 day 4 hours');

INSERT INTO sesiones_cajas (id_usuario_cajero, fecha_hora_apertura, monto_inicial, estado) VALUES
(1, CURRENT_TIMESTAMP - INTERVAL '4 hours', 350.00, 'Abierta');

INSERT INTO movimientos_cajas (id_sesion, tipo, monto, motivo, hora) VALUES
(2, 'Retiro Menor', 30.00, 'Envío de encomienda por moto a taller cliente', CURRENT_TIMESTAMP - INTERVAL '2 hours');

-- -----------------------------------------------------------------------------
-- 11. NOTAS DE COMPRAS (IDs: 1 al 6)
-- -----------------------------------------------------------------------------
INSERT INTO notas_compras (id_proveedor, id_usuario_receptor, numero_factura_nota, fecha_compra, monto_total, tipo_pago, estado_pago) VALUES
(1, 2, 'FAC-008921', CURRENT_DATE - INTERVAL '12 days', 6160.00, 'Crédito', 'Pendiente'),
(2, 2, 'FAC-003412', CURRENT_DATE - INTERVAL '9 days', 4870.00, 'Contado', 'Pagado'),
(3, 2, 'FAC-011290', CURRENT_DATE - INTERVAL '6 days', 4560.00, 'Contado', 'Pagado'),
(4, 2, 'FAC-007811', CURRENT_DATE - INTERVAL '4 days', 3450.00, 'Crédito', 'Pendiente'),
(5, 2, 'FAC-001244', CURRENT_DATE - INTERVAL '2 days', 3890.00, 'Contado', 'Pagado'),
(6, 2, 'FAC-004902', CURRENT_DATE - INTERVAL '1 day', 4390.00, 'Crédito', 'Pendiente');

-- -----------------------------------------------------------------------------
-- 12. DETALLES DE COMPRAS (No incluye los ítems dejados con stock bajo/agotado)
-- -----------------------------------------------------------------------------
INSERT INTO detalles_compras (id_compra, id_producto, cantidad_recibida, costo_unitario, subtotal) VALUES
-- Compra 1 (Total: 6160.00)
(1, 1, 15, 150.00, 2250.00),
(1, 2, 10, 215.00, 2150.00),
(1, 11, 40, 44.00, 1760.00),

-- Compra 2 (Total: 4870.00)
(2, 19, 5, 330.00, 1650.00),
(2, 20, 5, 330.00, 1650.00),
(2, 29, 30, 35.00, 1050.00),
(2, 21, 8, 65.00, 520.00),

-- Compra 3 (Total: 4560.00)
(3, 47, 4, 380.00, 1520.00),
(3, 48, 4, 430.00, 1720.00),
(3, 39, 4, 280.00, 1120.00),
(3, 49, 2, 100.00, 200.00),

-- Compra 4 (Total: 3450.00)
(4, 5, 10, 110.00, 1100.00),
(4, 6, 6, 165.00, 990.00),
(4, 35, 6, 205.00, 1230.00),
(4, 62, 5, 26.00, 130.00),

-- Compra 5 (Total: 3890.00)
(5, 31, 20, 55.00, 1100.00),
(5, 15, 5, 195.00, 975.00),
(5, 13, 5, 205.00, 1025.00),
(5, 37, 8, 98.75, 790.00),

-- Compra 6 (Total: 4390.00)
(6, 50, 2, 1050.00, 2100.00),
(6, 58, 1, 1240.00, 1240.00),
(6, 36, 5, 210.00, 1050.00);

-- =============================================================================
-- PARTE 3: COTIZACIONES, VENTAS, AJUSTES, AUDITORÍA Y SECUENCIAS
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 13. COTIZACIONES (IDs: 1 al 6)
-- -----------------------------------------------------------------------------
INSERT INTO cotizaciones (id_cliente, id_usuario, fecha_emision, fecha_vencimiento, monto_total, estado) VALUES
(2, 2, CURRENT_TIMESTAMP - INTERVAL '3 days', CURRENT_DATE + 4, 885.00, 'Vigente'),
(3, 3, CURRENT_TIMESTAMP - INTERVAL '2 days', CURRENT_DATE + 5, 1280.00, 'Vigente'),
(5, 2, CURRENT_TIMESTAMP - INTERVAL '15 days', CURRENT_DATE - 8, 532.00, 'Vencida'),
(4, 3, CURRENT_TIMESTAMP - INTERVAL '5 days', CURRENT_DATE + 2, 1674.00, 'Concretada/Facturada'),
(9, 2, CURRENT_TIMESTAMP - INTERVAL '4 days', CURRENT_DATE + 6, 822.00, 'Vigente'),
(10, 3, CURRENT_TIMESTAMP - INTERVAL '1 day', CURRENT_DATE + 5, 1425.00, 'Vigente');

-- -----------------------------------------------------------------------------
-- 14. DETALLES DE COTIZACIONES
-- -----------------------------------------------------------------------------
INSERT INTO detalles_cotizaciones (id_cotizacion, id_producto, cantidad, precio_unitario_cotizado, subtotal) VALUES
-- Cotización 1 (Total: 885.00)
(1, 1, 2, 220.00, 440.00),
(1, 2, 1, 310.00, 310.00),
(1, 21, 1, 135.00, 135.00),

-- Cotización 2 (Total: 1280.00)
(2, 19, 1, 460.00, 460.00),
(2, 20, 1, 460.00, 460.00),
(2, 22, 2, 180.00, 360.00),

-- Cotización 3 (Total: 532.00)
(3, 1, 1, 220.00, 220.00),
(3, 11, 4, 78.00, 312.00),

-- Cotización 4 (Total: 1674.00)
(4, 47, 1, 525.00, 525.00),
(4, 48, 1, 585.00, 585.00),
(4, 49, 2, 165.00, 330.00),
(4, 11, 3, 78.00, 234.00),

-- Cotización 5 (Total: 822.00)
(5, 5, 2, 165.00, 330.00),
(5, 24, 2, 98.00, 196.00),
(5, 31, 2, 88.00, 176.00),
(5, 25, 2, 60.00, 120.00),

-- Cotización 6 (Total: 1425.00)
(6, 39, 1, 390.00, 390.00),
(6, 40, 1, 88.00, 88.00),
(6, 15, 1, 285.00, 285.00),
(6, 6, 2, 240.00, 480.00),
(6, 62, 4, 45.50, 182.00);

-- -----------------------------------------------------------------------------
-- 15. NOTAS DE VENTAS (POS) (IDs: 1 al 10)
-- -----------------------------------------------------------------------------
INSERT INTO notas_ventas (id_cliente, id_usuario_vendedor, id_sesion_caja, fecha_hora, total_venta, descuento_total, metodo_pago, estado, motivo_anulacion) VALUES
(1, 2, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 6 hours', 312.00, 0.00, 'Efectivo', 'Completada', NULL),
(2, 2, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 4 hours', 510.00, 20.00, 'Transferencia/QR', 'Completada', NULL),
(5, 3, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 2 hours', 440.00, 0.00, 'Efectivo', 'Completada', NULL),
(1, 3, 1, CURRENT_TIMESTAMP - INTERVAL '1 day 1 hour', 220.00, 0.00, 'Efectivo', 'Anulada', 'Cliente canceló la compra en mostrador por falta de saldo'),
(6, 2, 2, CURRENT_TIMESTAMP - INTERVAL '3 hours', 370.00, 0.00, 'Transferencia/QR', 'Completada', NULL),
(3, 2, 2, CURRENT_TIMESTAMP - INTERVAL '2 hours', 1095.00, 50.00, 'Efectivo', 'Completada', NULL),
(9, 2, 2, CURRENT_TIMESTAMP - INTERVAL '50 minutes', 465.00, 20.00, 'Transferencia/QR', 'Completada', NULL),
(10, 3, 2, CURRENT_TIMESTAMP - INTERVAL '40 minutes', 406.00, 0.00, 'Efectivo', 'Completada', NULL),
(7, 2, 2, CURRENT_TIMESTAMP - INTERVAL '25 minutes', 270.00, 0.00, 'Efectivo', 'Completada', NULL),
(8, 3, 2, CURRENT_TIMESTAMP - INTERVAL '10 minutes', 335.00, 0.00, 'Tarjeta Débito/Crédito', 'Completada', NULL);

-- -----------------------------------------------------------------------------
-- 16. DETALLES DE VENTAS
-- -----------------------------------------------------------------------------
INSERT INTO detalles_ventas (id_venta, id_producto, cantidad_vendida, precio_unitario_cobrado, subtotal) VALUES
-- Venta 1 (Total: 312.00)
(1, 11, 4, 78.00, 312.00),

-- Venta 2 (Bruto: 530.00 - Descuento 20.00 = 510.00)
(2, 1, 1, 220.00, 220.00),
(2, 2, 1, 310.00, 310.00),

-- Venta 3 (Total: 440.00)
(3, 1, 2, 220.00, 440.00),

-- Venta 4 (Anulada - Total: 220.00)
(4, 1, 1, 220.00, 220.00),

-- Venta 5 (Total: 370.00)
(5, 11, 4, 78.00, 312.00),
(5, 29, 1, 58.00, 58.00),

-- Venta 6 (Bruto: 1145.00 - Descuento 50.00 = 1095.00)
(6, 19, 1, 460.00, 460.00),
(6, 39, 1, 390.00, 390.00),
(6, 35, 1, 295.00, 295.00),

-- Venta 7 (Bruto: 485.00 - Descuento 20.00 = 465.00)
(7, 5, 1, 165.00, 165.00),
(7, 24, 2, 98.00, 196.00),
(7, 62, 2, 38.00, 76.00),
(7, 55, 1, 48.00, 48.00),

-- Venta 8 (Total: 406.00)
(8, 26, 2, 115.00, 230.00),
(8, 31, 2, 88.00, 176.00),

-- Venta 9 (Total: 270.00)
(9, 21, 2, 135.00, 270.00),

-- Venta 10 (Total: 335.00)
(10, 36, 1, 335.00, 335.00);

-- -----------------------------------------------------------------------------
-- 17. AJUSTES FÍSICOS DE INVENTARIO
-- -----------------------------------------------------------------------------
INSERT INTO ajustes_inventario (id_producto, id_usuario, cantidad_ajustada, tipo_ajuste, motivo, fecha_ajuste) VALUES
(11, 2, -2, 'Merma', 'Empaque aplastado durante descarga con electrodo fisurado', CURRENT_TIMESTAMP - INTERVAL '5 days'),
(29, 2, 3, 'Conteo Físico', 'Aparición de sobrante físico no contabilizado en estantería E-01', CURRENT_TIMESTAMP - INTERVAL '2 days'),
(1, 2, -1, 'Devolución Dañada', 'Pastilla de freno con seguro quebrado devuelta por cliente', CURRENT_TIMESTAMP - INTERVAL '1 day'),
(62, 2, -2, 'Uso Interno', 'Reposición de focos halógenos para camioneta de reparto', CURRENT_TIMESTAMP - INTERVAL '12 hours');

-- -----------------------------------------------------------------------------
-- 18. BITÁCORA Y AUDITORÍA HISTÓRICA
-- -----------------------------------------------------------------------------
INSERT INTO bitacoras_auditorias (id_usuario, fecha_hora, modulo_afectado, accion, registro_id, detalle_cambio, ip_origen) VALUES
(1, CURRENT_TIMESTAMP - INTERVAL '3 days', 'Seguridad / Roles', 'INSERT', 1, '{"evento": "Creación inicial de roles y permisos del sistema"}', '127.0.0.1'),
(1, CURRENT_TIMESTAMP - INTERVAL '2 days', 'Catálogo / Productos', 'UPDATE', 1, '{"producto": "Juego de Pastillas de Freno Delanteras", "precio_anterior": 200.00, "precio_nuevo": 220.00}', '192.168.1.15'),
(3, CURRENT_TIMESTAMP - INTERVAL '1 day 1 hour', 'Ventas / POS', 'ANULACION', 4, '{"venta_id": 4, "motivo": "Cliente canceló compra en mostrador por falta de saldo", "monto": 220.00}', '192.168.1.20');

-- -----------------------------------------------------------------------------
-- 19. CIERRE FORMAL DE LA SESIÓN 1 (ARQUEO Y CUADRE AUTOMÁTICO)
-- -----------------------------------------------------------------------------
UPDATE sesiones_cajas 
SET monto_real_contado = monto_esperado_sistema,
    estado = 'Cerrada'
WHERE id_sesion = 1;

-- -----------------------------------------------------------------------------
-- 20. SINCRONIZACIÓN FINAL DE SECUENCIAS SERIAL/BIGSERIAL
-- -----------------------------------------------------------------------------
SELECT setval('roles_id_rol_seq', COALESCE((SELECT MAX(id_rol) FROM roles), 1), true);
SELECT setval('usuarios_id_usuario_seq', COALESCE((SELECT MAX(id_usuario) FROM usuarios), 1), true);
SELECT setval('categorias_id_categoria_seq', COALESCE((SELECT MAX(id_categoria) FROM categorias), 1), true);
SELECT setval('productos_id_producto_seq', COALESCE((SELECT MAX(id_producto) FROM productos), 1), true);
SELECT setval('codigos_alternativos_id_codigo_alt_seq', COALESCE((SELECT MAX(id_codigo_alt) FROM codigos_alternativos), 1), true);
SELECT setval('vehiculos_id_vehiculo_seq', COALESCE((SELECT MAX(id_vehiculo) FROM vehiculos), 1), true);
SELECT setval('compatibilidades_id_compatibilidad_seq', COALESCE((SELECT MAX(id_compatibilidad) FROM compatibilidades), 1), true);
SELECT setval('ajustes_inventario_id_ajuste_seq', COALESCE((SELECT MAX(id_ajuste) FROM ajustes_inventario), 1), true);
SELECT setval('proveedores_id_proveedor_seq', COALESCE((SELECT MAX(id_proveedor) FROM proveedores), 1), true);
SELECT setval('notas_compras_id_compra_seq', COALESCE((SELECT MAX(id_compra) FROM notas_compras), 1), true);
SELECT setval('detalles_compras_id_detalle_compra_seq', COALESCE((SELECT MAX(id_detalle_compra) FROM detalles_compras), 1), true);
SELECT setval('sesiones_cajas_id_sesion_seq', COALESCE((SELECT MAX(id_sesion) FROM sesiones_cajas), 1), true);
SELECT setval('movimientos_cajas_id_movimiento_seq', COALESCE((SELECT MAX(id_movimiento) FROM movimientos_cajas), 1), true);
SELECT setval('clientes_id_cliente_seq', COALESCE((SELECT MAX(id_cliente) FROM clientes), 1), true);
SELECT setval('cotizaciones_id_cotizacion_seq', COALESCE((SELECT MAX(id_cotizacion) FROM cotizaciones), 1), true);
SELECT setval('detalles_cotizaciones_id_detalle_cot_seq', COALESCE((SELECT MAX(id_detalle_cot) FROM detalles_cotizaciones), 1), true);
SELECT setval('notas_ventas_id_venta_seq', COALESCE((SELECT MAX(id_venta) FROM notas_ventas), 1), true);
SELECT setval('detalles_ventas_id_detalle_venta_seq', COALESCE((SELECT MAX(id_detalle_venta) FROM detalles_ventas), 1), true);
SELECT setval('bitacoras_auditorias_id_bitacora_seq', COALESCE((SELECT MAX(id_bitacora) FROM bitacoras_auditorias), 1), true);
