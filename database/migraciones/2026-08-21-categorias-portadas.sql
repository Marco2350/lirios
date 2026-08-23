-- =========================================================
-- Migración 2026-08-21 — Nueva taxonomía de categorías con portada
-- =========================================================
-- Para ejecutar en la base de datos YA desplegada (hosting remoto),
-- sin volver a correr schema.sql completo (eso borraría datos reales).
-- Es segura de correr más de una vez (usa IF NOT EXISTS / ON DUPLICATE
-- KEY UPDATE / INSERT IGNORE en todo lo que modifica).
--
-- Qué hace:
--   1. Agrega la columna categorias.imagen_portada.
--   2. Renombra 3 categorías existentes para que su slug coincida con
--      el nombre del archivo de portada (no se pierde ningún producto:
--      los productos apuntan a subcategoria_id, no a categoria slug).
--   3. Pone nombre e imagen_portada nuevos a las categorías que ya
--      existían y siguen en la lista.
--   4. Crea las 4 categorías que no existían (arreglos-florales,
--      desayuno-sorpresa, aniversario, flores-preservadas) con sus
--      subcategorías mínimas.
--   5. NO borra 'amor-y-romance' ni 'peluches' — quedan huérfanas por
--      si tienen productos asociados. Revisar y decidir a mano si se
--      fusionan o se retiran.
-- =========================================================

ALTER TABLE categorias
  ADD COLUMN IF NOT EXISTS imagen_portada VARCHAR(255) DEFAULT NULL AFTER icono;

-- 2. Renombrar slugs existentes que cambiaron de nombre
UPDATE categorias SET slug = 'funebres',                          nombre = 'Arreglos Fúnebres' WHERE slug = 'condolencias';
UPDATE categorias SET slug = 'bodas',                              nombre = 'Arreglos para Bodas' WHERE slug = 'bodas-y-eventos';
UPDATE categorias SET slug = 'chocolates-perfumes-complementos',   nombre = 'Arreglos con Chocolates, Perfumes, Bebidas, Tazas y Otros Complementos' WHERE slug = 'regalos-y-complementos';

-- 3. Actualizar nombre + portada + orden de categorías que ya existían
UPDATE categorias SET nombre = 'Ramos Florales',           imagen_portada = 'images/categorias/ramos-florales.webp',                  orden = 1  WHERE slug = 'ramos-florales';
UPDATE categorias SET nombre = 'Arreglos en Base',         imagen_portada = 'images/categorias/arreglos-en-base.webp',                orden = 3  WHERE slug = 'arreglos-en-base';
UPDATE categorias SET nombre = 'Arreglos Cumpleaños',      imagen_portada = 'images/categorias/cumpleanos.webp',                      orden = 4  WHERE slug = 'cumpleanos';
UPDATE categorias SET nombre = 'Arreglos para Caballero',  orden = 5                                                                             WHERE slug = 'caballero';
UPDATE categorias SET nombre = 'Arreglos Infantiles',      imagen_portada = 'images/categorias/infantil.webp',                        orden = 6  WHERE slug = 'infantil';
UPDATE categorias SET nombre = 'Arreglos de Graduación',   imagen_portada = 'images/categorias/graduaciones.webp',                    orden = 10 WHERE slug = 'graduaciones';
UPDATE categorias SET nombre = 'Arreglos Fúnebres',        imagen_portada = 'images/categorias/funebres.webp',                        orden = 12 WHERE slug = 'funebres';
UPDATE categorias SET nombre = 'Globos',                   orden = 14                                                                            WHERE slug = 'globos';
UPDATE categorias SET orden = 11                                                                                                                  WHERE slug = 'bodas';
UPDATE categorias SET imagen_portada = 'images/categorias/chocolates-perfumes-complementos.webp', orden = 9                          WHERE slug = 'chocolates-perfumes-complementos';
UPDATE categorias SET orden = 90 WHERE slug = 'amor-y-romance';
UPDATE categorias SET orden = 91 WHERE slug = 'peluches';
-- Categorías extra encontradas en la base remota (no vienen del schema.sql
-- original, no tienen subcategorías ni productos) — se dejan al final igual
-- que las huérfanas de arriba, para que no desplacen a las 14 vigentes de
-- la grilla del home (esa grilla corta a los primeros 14 por `orden`).
UPDATE categorias SET orden = 92 WHERE slug = 'flores-amarillas';
UPDATE categorias SET orden = 93 WHERE slug = 'girasoles';

-- 4. Crear las categorías nuevas (idempotente por slug UNIQUE)
INSERT INTO categorias (slug, nombre, icono, imagen_portada, orden) VALUES
  ('arreglos-florales',  'Arreglos Florales',                 NULL, 'images/categorias/arreglos-florales.webp',  2),
  ('desayuno-sorpresa',  'Desayuno Sorpresa',                 NULL, 'images/categorias/desayuno-sorpresa.webp',  7),
  ('aniversario',        'Aniversario',                       NULL, 'images/categorias/aniversario.webp',        8),
  ('flores-preservadas', 'Arreglos con Flores Preservadas',   NULL, NULL,                                        13)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  imagen_portada = VALUES(imagen_portada),
  orden = VALUES(orden);

-- Subcategorías mínimas para las 4 categorías nuevas (no duplica si ya corriste esto antes)
INSERT IGNORE INTO subcategorias (categoria_id, slug, nombre, orden) VALUES
  ((SELECT id FROM categorias WHERE slug='arreglos-florales'), 'mixtos',      'Mixtos',      1),
  ((SELECT id FROM categorias WHERE slug='arreglos-florales'), 'monocromos',  'Monocromos',  2),
  ((SELECT id FROM categorias WHERE slug='arreglos-florales'), 'tropicales',  'Tropicales',  3),

  ((SELECT id FROM categorias WHERE slug='desayuno-sorpresa'), 'clasico',      'Clásico',       1),
  ((SELECT id FROM categorias WHERE slug='desayuno-sorpresa'), 'con-pastel',   'Con pastel',    2),
  ((SELECT id FROM categorias WHERE slug='desayuno-sorpresa'), 'con-globos',   'Con globos',    3),

  ((SELECT id FROM categorias WHERE slug='aniversario'), 'ramos',        'Ramos',         1),
  ((SELECT id FROM categorias WHERE slug='aniversario'), 'con-detalles', 'Con detalles',  2),
  ((SELECT id FROM categorias WHERE slug='aniversario'), 'bodas-de-oro', 'Bodas de oro',  3),

  ((SELECT id FROM categorias WHERE slug='flores-preservadas'), 'cajas',    'Cajas',    1),
  ((SELECT id FROM categorias WHERE slug='flores-preservadas'), 'cupulas',  'Cúpulas',  2),
  ((SELECT id FROM categorias WHERE slug='flores-preservadas'), 'marcos',   'Marcos',   3);

-- Verificación rápida después de correr esto:
-- SELECT slug, nombre, imagen_portada, orden FROM categorias ORDER BY orden;
