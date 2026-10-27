-- =========================================================
-- Migración 2026-10-26 — Hoja del día: canal de venta y vendedor
-- =========================================================
-- /admin/pedidos.php gana la vista "Día" (hoja como la de la clienta, con
-- total del día y de la semana). Los pedidos manuales ahora dicen por dónde
-- entró la venta:
--   canal: 'web' (carrito), 'tienda' (venta en físico) o 'whatsapp'
--          (venta por WhatsApp, atendida por un empleado).
--   vendedor: quién la atendió (texto libre, opcional).
-- Los pedidos del carrito quedan como 'web'; los manuales que ya existían
-- se dejan en 'tienda' (no se sabe por dónde entraron). Segura de correr
-- más de una vez.
-- =========================================================

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS canal    ENUM('web','tienda','whatsapp') NOT NULL DEFAULT 'web',
  ADD COLUMN IF NOT EXISTS vendedor VARCHAR(100) DEFAULT NULL;

UPDATE pedidos SET canal = 'tienda' WHERE origen = 'manual' AND canal = 'web';
