-- =============================================================================
-- MIGRACIÓN CICLO 2 (CU17, CU18, CU19, CU20, CU22) SOBRE UNA BD EXISTENTE
-- Uso: psql -h 127.0.0.1 -U postgres -d MultiPartes -f database/smartdb/Migracion_Ciclo2.sql
-- Es idempotente: puede re-ejecutarse sin efectos secundarios.
-- Para una instalación desde cero usar: Script.sql -> Triggers.sql -> PA.sql -> Poblacion.sql
-- =============================================================================
\set ON_ERROR_STOP on
BEGIN;

-- CU19: baja lógica de proveedores
ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS estado BOOLEAN DEFAULT TRUE;

-- CU17 / CU18: salidas por uso interno centralizadas en ajustes_inventario
ALTER TABLE ajustes_inventario DROP CONSTRAINT IF EXISTS chk_ajuste_tipo;
ALTER TABLE ajustes_inventario ADD CONSTRAINT chk_ajuste_tipo
    CHECK (tipo_ajuste IN ('Merma', 'Pérdida/Extravío', 'Conteo Físico', 'Devolución Dañada', 'Uso Interno'));

-- Procedimientos y funciones (fuente oficial: PA.sql)
\ir PA.sql

COMMIT;
