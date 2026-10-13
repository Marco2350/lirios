-- =========================================================
-- Migración 2026-10-11 — Agenda de pedidos por día
-- =========================================================
-- /admin/pedidos.php gana una vista "Agenda" (una tabla por día, como la
-- hoja de cálculo que la clienta llevaba a mano). Columnas nuevas:
--   pago_tarjeta / pago_efectivo / pago_transferencia: monto realmente
--     cobrado por método (distinto de `total`, que es lo que sumó el carrito).
--   origen: 'web' (carrito) o 'manual' (registrado desde la agenda).
--   descripcion: texto libre de "Información" para pedidos manuales, que
--     no tienen filas en pedido_items.
-- Segura de correr más de una vez (MariaDB: IF NOT EXISTS).
-- =========================================================

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS pago_tarjeta       DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS pago_efectivo      DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS pago_transferencia DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS origen             ENUM('web','manual') NOT NULL DEFAULT 'web',
  ADD COLUMN IF NOT EXISTS descripcion        VARCHAR(500) DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_pedidos_fecha_entrega ON pedidos(fecha_entrega);
