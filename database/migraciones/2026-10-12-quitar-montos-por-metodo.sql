-- =========================================================
-- Migración 2026-10-12 — Se quitan los montos por método de pago
-- =========================================================
-- La migración 2026-10-11 agregó pago_tarjeta / pago_efectivo /
-- pago_transferencia para anotar montos cobrados. La clienta aclaró que
-- tarjeta / efectivo / transferencia son la FORMA DE PAGO que elige el cliente
-- (columna `forma_pago`, que ya existía) y que el monto es el total del
-- pedido, así que esas 3 columnas sobran. `origen` y `descripcion` se quedan.
-- Segura de correr más de una vez (MariaDB: IF EXISTS).
-- =========================================================

ALTER TABLE pedidos
  DROP COLUMN IF EXISTS pago_tarjeta,
  DROP COLUMN IF EXISTS pago_efectivo,
  DROP COLUMN IF EXISTS pago_transferencia;
