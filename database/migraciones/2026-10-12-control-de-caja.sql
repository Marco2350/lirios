-- =========================================================
-- Migración 2026-10-12 — Control de caja
-- =========================================================
-- Módulo /admin/caja.php (basado en el flujo del módulo controlCaja de FPTRAX):
-- apertura del día con desglose de billetes y monedas, movimientos de entrada
-- y salida de efectivo, y cierre con arqueo físico contra el saldo esperado.
-- A diferencia de FPTRAX no hay sucursales ni varias cajas físicas: LIRIOS
-- tiene una sola tienda, así que hay una caja por día (fecha única).
-- Solo crea tablas nuevas — no toca ninguna existente. Segura de correr
-- más de una vez (IF NOT EXISTS; la semilla solo corre si la tabla está vacía).
-- =========================================================

CREATE TABLE IF NOT EXISTS caja_denominaciones (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  valor   DECIMAL(10,2) NOT NULL,
  tipo    ENUM('BILLETE','MONEDA') NOT NULL,
  activa  TINYINT(1) NOT NULL DEFAULT 1,   -- desactivar en vez de borrar: el historial conserva su desglose
  UNIQUE KEY uq_caja_denominacion (valor, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caja_diaria (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  fecha           DATE NOT NULL,
  saldo_apertura  DECIMAL(10,2) NOT NULL DEFAULT 0,
  saldo_cierre    DECIMAL(10,2) DEFAULT NULL,       -- conteo físico al cerrar
  diferencia      DECIMAL(10,2) DEFAULT NULL,       -- saldo_cierre - saldo esperado (calculado por el servidor)
  observacion     VARCHAR(500) DEFAULT NULL,        -- obligatoria si el cierre no cuadra
  estado          ENUM('ABIERTA','CERRADA') NOT NULL DEFAULT 'ABIERTA',
  abierta_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cerrada_en      DATETIME DEFAULT NULL,
  UNIQUE KEY uq_caja_fecha (fecha)                  -- una caja por día
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caja_desglose (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  caja_id          INT NOT NULL,
  momento          ENUM('APERTURA','CIERRE') NOT NULL,
  denominacion_id  INT DEFAULT NULL,
  tipo             ENUM('BILLETE','MONEDA') NOT NULL,
  valor            DECIMAL(10,2) NOT NULL,          -- copia del valor al momento (editar denominaciones no altera el historial)
  cantidad         INT NOT NULL,
  FOREIGN KEY (caja_id) REFERENCES caja_diaria(id) ON DELETE CASCADE,
  FOREIGN KEY (denominacion_id) REFERENCES caja_denominaciones(id) ON DELETE SET NULL,
  KEY idx_caja_desglose (caja_id, momento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caja_movimientos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  caja_id    INT NOT NULL,
  tipo       ENUM('ENTRADA','SALIDA') NOT NULL,
  monto      DECIMAL(10,2) NOT NULL,
  concepto   VARCHAR(255) NOT NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (caja_id) REFERENCES caja_diaria(id) ON DELETE CASCADE,
  KEY idx_caja_movimientos (caja_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semilla: denominaciones de Honduras (editables desde /admin/caja-denominaciones.php)
INSERT INTO caja_denominaciones (valor, tipo)
SELECT v.valor, v.tipo FROM (
  SELECT 500 AS valor, 'BILLETE' AS tipo UNION ALL SELECT 100, 'BILLETE' UNION ALL SELECT 50, 'BILLETE'
  UNION ALL SELECT 20, 'BILLETE' UNION ALL SELECT 10, 'BILLETE' UNION ALL SELECT 5, 'BILLETE'
  UNION ALL SELECT 2, 'MONEDA' UNION ALL SELECT 1, 'MONEDA' UNION ALL SELECT 0.50, 'MONEDA'
  UNION ALL SELECT 0.20, 'MONEDA' UNION ALL SELECT 0.10, 'MONEDA' UNION ALL SELECT 0.05, 'MONEDA'
) v
WHERE NOT EXISTS (SELECT 1 FROM caja_denominaciones);
