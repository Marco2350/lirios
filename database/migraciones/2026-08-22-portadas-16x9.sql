-- =========================================================
-- Migración 2026-08-22 — Portadas nuevas (fotos horizontales)
-- =========================================================
-- La clienta subió un segundo lote de fotos de portada (16:9, no las
-- 9:16 verticales que había pedido originalmente). Se reemplazaron las
-- imágenes de 6 categorías que ya tenían portada, y se agregó la de
-- Globos, que no tenía. Los archivos .webp ya se generaron en
-- images/categorias/ (mismo nombre de siempre, solo cambió el contenido
-- del archivo para las 6 reemplazadas) — este script solo asegura que
-- Globos quede con imagen_portada en bases de datos que aún no la
-- tengan. Segura de correr más de una vez.
-- =========================================================

UPDATE categorias SET imagen_portada = 'images/categorias/globos.webp' WHERE slug = 'globos';

-- Nota: ramos-florales, infantil, funebres, aniversario, desayuno-sorpresa
-- y chocolates-perfumes-complementos ya apuntaban a su archivo .webp desde
-- la migración anterior — no hace falta tocar la fila, solo el archivo
-- físico en images/categorias/ (ya reemplazado).

-- Categorías que TODAVÍA no tienen portada: caballero, bodas, flores-preservadas.
