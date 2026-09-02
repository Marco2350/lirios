-- Backup antes de: (1) eliminar la categoría 'Arreglos Cumpleaños' completa (0 productos,
-- sin pérdida de catálogo) y (2) quitar la subclasificación de 'Ramos Florales' (se
-- consolidan sus 8 subcategorías en una sola 'General' para no perder los 59 productos ya
-- cargados — a pedido de la clienta (2026-08-31).
-- Generado: 2026-09-01 05:58:01

-- Categoría 'cumpleanos' (id=4)
INSERT INTO categorias (id, slug, nombre, icono, imagen_portada, orden, visible) VALUES (4, 'cumpleanos', 'Arreglos Cumpleaños', NULL, 'images/categorias/cumpleanos.webp', 3, 1);

-- Subcategorías de 'cumpleanos' (6 filas, todas con 0 productos)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (27, 4, 'con-chocolates', 'Con chocolates', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (26, 4, 'con-globos', 'Con globos', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (25, 4, 'infantil', 'Infantil', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (24, 4, 'para-el', 'Para él', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (23, 4, 'para-ella', 'Para ella', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (28, 4, 'sorpresas', 'Sorpresas', 6);

-- Subcategorías de 'ramos-florales' (8 filas, antes de consolidarlas en 'General')
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (1, 1, 'rosas', 'Rosas', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (2, 1, 'girasoles', 'Girasoles', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (3, 1, 'hortensias', 'Hortensias', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (4, 1, 'tulipanes', 'Tulipanes', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (5, 1, 'lirios', 'Lirios', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (6, 1, 'gerberas', 'Gerberas', 6);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (7, 1, 'mini-rosas', 'Mini Rosas', 7);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (8, 1, 'mixtos', 'Mixtos', 8);

-- Mapeo original producto -> subcategoría (59 productos), para revertir si hace falta:
UPDATE productos SET subcategoria_id=1 WHERE id=2;
UPDATE productos SET subcategoria_id=1 WHERE id=5;
UPDATE productos SET subcategoria_id=1 WHERE id=38;
UPDATE productos SET subcategoria_id=1 WHERE id=40;
UPDATE productos SET subcategoria_id=1 WHERE id=61;
UPDATE productos SET subcategoria_id=1 WHERE id=62;
UPDATE productos SET subcategoria_id=1 WHERE id=63;
UPDATE productos SET subcategoria_id=1 WHERE id=81;
UPDATE productos SET subcategoria_id=1 WHERE id=82;
UPDATE productos SET subcategoria_id=1 WHERE id=84;
UPDATE productos SET subcategoria_id=1 WHERE id=87;
UPDATE productos SET subcategoria_id=1 WHERE id=88;
UPDATE productos SET subcategoria_id=1 WHERE id=89;
UPDATE productos SET subcategoria_id=1 WHERE id=90;
UPDATE productos SET subcategoria_id=1 WHERE id=91;
UPDATE productos SET subcategoria_id=1 WHERE id=93;
UPDATE productos SET subcategoria_id=1 WHERE id=94;
UPDATE productos SET subcategoria_id=1 WHERE id=95;
UPDATE productos SET subcategoria_id=1 WHERE id=96;
UPDATE productos SET subcategoria_id=1 WHERE id=97;
UPDATE productos SET subcategoria_id=1 WHERE id=98;
UPDATE productos SET subcategoria_id=1 WHERE id=100;
UPDATE productos SET subcategoria_id=1 WHERE id=101;
UPDATE productos SET subcategoria_id=1 WHERE id=102;
UPDATE productos SET subcategoria_id=1 WHERE id=103;
UPDATE productos SET subcategoria_id=1 WHERE id=108;
UPDATE productos SET subcategoria_id=1 WHERE id=109;
UPDATE productos SET subcategoria_id=1 WHERE id=117;
UPDATE productos SET subcategoria_id=1 WHERE id=120;
UPDATE productos SET subcategoria_id=1 WHERE id=122;
UPDATE productos SET subcategoria_id=1 WHERE id=123;
UPDATE productos SET subcategoria_id=1 WHERE id=127;
UPDATE productos SET subcategoria_id=1 WHERE id=129;
UPDATE productos SET subcategoria_id=1 WHERE id=147;
UPDATE productos SET subcategoria_id=1 WHERE id=152;
UPDATE productos SET subcategoria_id=1 WHERE id=153;
UPDATE productos SET subcategoria_id=1 WHERE id=154;
UPDATE productos SET subcategoria_id=2 WHERE id=7;
UPDATE productos SET subcategoria_id=2 WHERE id=8;
UPDATE productos SET subcategoria_id=2 WHERE id=9;
UPDATE productos SET subcategoria_id=2 WHERE id=10;
UPDATE productos SET subcategoria_id=2 WHERE id=11;
UPDATE productos SET subcategoria_id=2 WHERE id=12;
UPDATE productos SET subcategoria_id=2 WHERE id=14;
UPDATE productos SET subcategoria_id=2 WHERE id=16;
UPDATE productos SET subcategoria_id=2 WHERE id=17;
UPDATE productos SET subcategoria_id=2 WHERE id=18;
UPDATE productos SET subcategoria_id=2 WHERE id=19;
UPDATE productos SET subcategoria_id=2 WHERE id=20;
UPDATE productos SET subcategoria_id=2 WHERE id=21;
UPDATE productos SET subcategoria_id=2 WHERE id=22;
UPDATE productos SET subcategoria_id=2 WHERE id=26;
UPDATE productos SET subcategoria_id=5 WHERE id=6;
UPDATE productos SET subcategoria_id=6 WHERE id=85;
UPDATE productos SET subcategoria_id=6 WHERE id=86;
UPDATE productos SET subcategoria_id=6 WHERE id=99;
UPDATE productos SET subcategoria_id=8 WHERE id=119;
UPDATE productos SET subcategoria_id=8 WHERE id=121;
UPDATE productos SET subcategoria_id=8 WHERE id=155;

