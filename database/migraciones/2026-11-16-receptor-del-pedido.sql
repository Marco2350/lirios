-- =========================================================
-- Migración 2026-11-16 — Quien recibe el arreglo vs. quien paga
-- =========================================================
-- Hasta hoy el pedido tenía un solo "nombre" y un solo "teléfono", y algunos
-- clientes ponían los de quien RECIBE el arreglo en vez de los suyos, así que
-- la floristería no tenía cómo contactar a quien paga.
--   cliente_nombre / telefono  = quien hace y paga el pedido (sin cambios).
--   receptor_nombre / receptor_telefono = quien recibe (nuevo, opcional).
-- Segura de correr más de una vez.
-- =========================================================

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS receptor_nombre   VARCHAR(150) DEFAULT NULL AFTER telefono,
  ADD COLUMN IF NOT EXISTS receptor_telefono VARCHAR(30)  DEFAULT NULL AFTER receptor_nombre;
