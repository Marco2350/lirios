-- =========================================================
-- Migración 2026-10-12b — Sucursales y varias cajas
-- =========================================================
-- La primera versión del Control de caja asumía una sola caja por día. LIRIOS
-- puede tener varias sucursales y varias cajas por sucursal (como en FPTRAX):
--   caja_sucursales: sucursales del negocio.
--   caja_cajas: cajas físicas, cada una de una sucursal.
--   caja_diaria.caja_id: a qué caja pertenece cada apertura. Ahora la regla es
--     UNA APERTURA POR CAJA Y POR DÍA (antes: una por día en total), y una sola
--     abierta a la vez por caja.
-- Si ya hubiera cajas diarias de la versión anterior, quedan asignadas a la
-- caja principal sembrada aquí. Segura de correr más de una vez.
-- =========================================================

CREATE TABLE IF NOT EXISTS caja_sucursales (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(100) NOT NULL,
  activa  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_caja_sucursal_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caja_cajas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  sucursal_id  INT NOT NULL,
  nombre       VARCHAR(100) NOT NULL,
  activa       TINYINT(1) NOT NULL DEFAULT 1,   -- desactivar en vez de borrar: el historial conserva su caja
  UNIQUE KEY uq_caja_nombre (sucursal_id, nombre),
  FOREIGN KEY (sucursal_id) REFERENCES caja_sucursales(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO caja_sucursales (nombre)
SELECT 'Tienda principal' WHERE NOT EXISTS (SELECT 1 FROM caja_sucursales);

INSERT INTO caja_cajas (sucursal_id, nombre)
SELECT (SELECT MIN(id) FROM caja_sucursales), 'Caja principal' WHERE NOT EXISTS (SELECT 1 FROM caja_cajas);

ALTER TABLE caja_diaria ADD COLUMN IF NOT EXISTS caja_id INT NULL AFTER id;

UPDATE caja_diaria SET caja_id = (SELECT MIN(id) FROM caja_cajas) WHERE caja_id IS NULL;

ALTER TABLE caja_diaria MODIFY caja_id INT NOT NULL;

ALTER TABLE caja_diaria ADD FOREIGN KEY IF NOT EXISTS fk_caja_diaria_caja (caja_id) REFERENCES caja_cajas(id) ON DELETE RESTRICT;

ALTER TABLE caja_diaria ADD UNIQUE INDEX IF NOT EXISTS uq_caja_dia (caja_id, fecha);

ALTER TABLE caja_diaria DROP INDEX IF EXISTS uq_caja_fecha;
