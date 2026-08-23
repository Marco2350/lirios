-- =========================================================
-- Migración 2026-08-21 (b) — Complementos + quitar Arreglos Florales
-- =========================================================
-- Pedido de la clienta el mismo día:
--   1. Renombrar la categoría larga a "Complementos" (antes "Arreglos
--      con Chocolates, Perfumes, Bebidas, Tazas y Otros Complementos").
--   2. Quitar "Arreglos Florales" — la creamos horas antes en la
--      primera migración, pero es redundante: todo el catálogo son
--      arreglos florales, no aporta como categoría propia. Se verificó
--      que tenía 0 productos asignados antes de borrarla (DELETE en
--      categorias hace CASCADE sobre sus subcategorías).
--   3. Renumerar `orden` para que quede 1..13 sin huecos.
-- Segura de correr más de una vez.
-- =========================================================

UPDATE categorias SET nombre = 'Complementos' WHERE slug = 'chocolates-perfumes-complementos';

DELETE FROM categorias WHERE slug = 'arreglos-florales';

UPDATE categorias SET orden = 1  WHERE slug = 'ramos-florales';
UPDATE categorias SET orden = 2  WHERE slug = 'arreglos-en-base';
UPDATE categorias SET orden = 3  WHERE slug = 'cumpleanos';
UPDATE categorias SET orden = 4  WHERE slug = 'caballero';
UPDATE categorias SET orden = 5  WHERE slug = 'infantil';
UPDATE categorias SET orden = 6  WHERE slug = 'desayuno-sorpresa';
UPDATE categorias SET orden = 7  WHERE slug = 'aniversario';
UPDATE categorias SET orden = 8  WHERE slug = 'chocolates-perfumes-complementos';
UPDATE categorias SET orden = 9  WHERE slug = 'graduaciones';
UPDATE categorias SET orden = 10 WHERE slug = 'bodas';
UPDATE categorias SET orden = 11 WHERE slug = 'funebres';
UPDATE categorias SET orden = 12 WHERE slug = 'flores-preservadas';
UPDATE categorias SET orden = 13 WHERE slug = 'globos';
