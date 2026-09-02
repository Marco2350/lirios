-- Backup antes de consolidar en una sola subcategoría 'General' todas las
-- categorías EXCEPTO 'ramos-florales' (que se restaura a sus 8 subcategorías
-- originales en esta misma corrida) — corrección a pedido de la clienta
-- (2026-08-31 / 2026-09-01): 'las subcategorías de ramos están bien, el resto
-- se quita'.
-- Generado: 2026-09-01 06:15:39

-- Categoría 'arreglos-en-base' (id=2) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (9, 2, 'vidrio', 'Vidrio', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (10, 2, 'ceramica', 'Cerámica', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (11, 2, 'madera', 'Madera', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (12, 2, 'acrilico', 'Acrílico', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (13, 2, 'carton-premium', 'Cartón Premium', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (14, 2, 'canastas', 'Canastas', 6);
-- Mapeo original producto -> subcategoría (arreglos-en-base, 39 productos):
UPDATE productos SET subcategoria_id=9 WHERE id=27;
UPDATE productos SET subcategoria_id=9 WHERE id=28;
UPDATE productos SET subcategoria_id=9 WHERE id=37;
UPDATE productos SET subcategoria_id=9 WHERE id=53;
UPDATE productos SET subcategoria_id=9 WHERE id=54;
UPDATE productos SET subcategoria_id=9 WHERE id=142;
UPDATE productos SET subcategoria_id=9 WHERE id=143;
UPDATE productos SET subcategoria_id=9 WHERE id=144;
UPDATE productos SET subcategoria_id=9 WHERE id=145;
UPDATE productos SET subcategoria_id=10 WHERE id=56;
UPDATE productos SET subcategoria_id=10 WHERE id=57;
UPDATE productos SET subcategoria_id=10 WHERE id=60;
UPDATE productos SET subcategoria_id=10 WHERE id=132;
UPDATE productos SET subcategoria_id=11 WHERE id=23;
UPDATE productos SET subcategoria_id=11 WHERE id=25;
UPDATE productos SET subcategoria_id=11 WHERE id=42;
UPDATE productos SET subcategoria_id=11 WHERE id=43;
UPDATE productos SET subcategoria_id=11 WHERE id=44;
UPDATE productos SET subcategoria_id=11 WHERE id=45;
UPDATE productos SET subcategoria_id=11 WHERE id=47;
UPDATE productos SET subcategoria_id=11 WHERE id=50;
UPDATE productos SET subcategoria_id=11 WHERE id=51;
UPDATE productos SET subcategoria_id=11 WHERE id=59;
UPDATE productos SET subcategoria_id=11 WHERE id=131;
UPDATE productos SET subcategoria_id=13 WHERE id=24;
UPDATE productos SET subcategoria_id=13 WHERE id=48;
UPDATE productos SET subcategoria_id=13 WHERE id=49;
UPDATE productos SET subcategoria_id=13 WHERE id=58;
UPDATE productos SET subcategoria_id=13 WHERE id=83;
UPDATE productos SET subcategoria_id=13 WHERE id=130;
UPDATE productos SET subcategoria_id=13 WHERE id=141;
UPDATE productos SET subcategoria_id=13 WHERE id=146;
UPDATE productos SET subcategoria_id=13 WHERE id=149;
UPDATE productos SET subcategoria_id=13 WHERE id=150;
UPDATE productos SET subcategoria_id=14 WHERE id=13;
UPDATE productos SET subcategoria_id=14 WHERE id=15;
UPDATE productos SET subcategoria_id=14 WHERE id=46;
UPDATE productos SET subcategoria_id=14 WHERE id=52;
UPDATE productos SET subcategoria_id=14 WHERE id=55;

-- Categoría 'caballero' (id=9) — subcategorías (7 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (52, 9, 'whisky', 'Whisky', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (53, 9, 'vinos', 'Vinos', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (54, 9, 'cervezas', 'Cervezas', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (55, 9, 'chocolates', 'Chocolates', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (56, 9, 'perfumes', 'Perfumes', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (57, 9, 'ramos-elegantes', 'Ramos elegantes', 6);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (58, 9, 'regalos-ejecutivos', 'Regalos ejecutivos', 7);
-- Mapeo original producto -> subcategoría (caballero, 21 productos):
UPDATE productos SET subcategoria_id=57 WHERE id=156;
UPDATE productos SET subcategoria_id=57 WHERE id=157;
UPDATE productos SET subcategoria_id=57 WHERE id=158;
UPDATE productos SET subcategoria_id=57 WHERE id=159;
UPDATE productos SET subcategoria_id=57 WHERE id=160;
UPDATE productos SET subcategoria_id=57 WHERE id=161;
UPDATE productos SET subcategoria_id=57 WHERE id=166;
UPDATE productos SET subcategoria_id=57 WHERE id=167;
UPDATE productos SET subcategoria_id=57 WHERE id=168;
UPDATE productos SET subcategoria_id=57 WHERE id=169;
UPDATE productos SET subcategoria_id=57 WHERE id=170;
UPDATE productos SET subcategoria_id=57 WHERE id=171;
UPDATE productos SET subcategoria_id=57 WHERE id=172;
UPDATE productos SET subcategoria_id=57 WHERE id=173;
UPDATE productos SET subcategoria_id=58 WHERE id=65;
UPDATE productos SET subcategoria_id=58 WHERE id=162;
UPDATE productos SET subcategoria_id=58 WHERE id=163;
UPDATE productos SET subcategoria_id=58 WHERE id=164;
UPDATE productos SET subcategoria_id=58 WHERE id=165;
UPDATE productos SET subcategoria_id=58 WHERE id=174;
UPDATE productos SET subcategoria_id=58 WHERE id=175;

-- Categoría 'infantil' (id=11) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (65, 11, 'nacimiento', 'Nacimiento', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (66, 11, 'baby-shower', 'Baby Shower', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (67, 11, 'nina', 'Niña', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (68, 11, 'nino', 'Niño', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (69, 11, 'personajes', 'Personajes', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (70, 11, 'dulces', 'Dulces', 6);
-- Mapeo original producto -> subcategoría (infantil, 0 productos):

-- Categoría 'desayuno-sorpresa' (id=16) — subcategorías (3 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (80, 16, 'clasico', 'Clásico', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (81, 16, 'con-pastel', 'Con pastel', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (82, 16, 'con-globos', 'Con globos', 3);
-- Mapeo original producto -> subcategoría (desayuno-sorpresa, 3 productos):
UPDATE productos SET subcategoria_id=80 WHERE id=67;
UPDATE productos SET subcategoria_id=80 WHERE id=68;
UPDATE productos SET subcategoria_id=81 WHERE id=69;

-- Categoría 'aniversario' (id=17) — subcategorías (3 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (83, 17, 'ramos', 'Ramos', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (84, 17, 'con-detalles', 'Con detalles', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (85, 17, 'bodas-de-oro', 'Bodas de oro', 3);
-- Mapeo original producto -> subcategoría (aniversario, 4 productos):
UPDATE productos SET subcategoria_id=83 WHERE id=39;
UPDATE productos SET subcategoria_id=83 WHERE id=139;
UPDATE productos SET subcategoria_id=83 WHERE id=140;
UPDATE productos SET subcategoria_id=84 WHERE id=151;

-- Categoría 'chocolates-perfumes-complementos' (id=3) — subcategorías (8 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (15, 3, 'chocolates', 'Chocolates', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (16, 3, 'perfumes', 'Perfumes', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (17, 3, 'vinos-y-whisky', 'Vinos y Whisky', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (18, 3, 'tazas', 'Tazas', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (19, 3, 'stanley', 'Stanley', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (20, 3, 'agendas', 'Agendas', 6);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (21, 3, 'globos', 'Globos', 7);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (22, 3, 'tarjetas', 'Tarjetas', 8);
-- Mapeo original producto -> subcategoría (chocolates-perfumes-complementos, 3 productos):
UPDATE productos SET subcategoria_id=15 WHERE id=66;
UPDATE productos SET subcategoria_id=15 WHERE id=70;
UPDATE productos SET subcategoria_id=19 WHERE id=1;

-- Categoría 'graduaciones' (id=6) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (35, 6, 'ramos', 'Ramos', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (36, 6, 'arreglos', 'Arreglos', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (37, 6, 'globos', 'Globos', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (38, 6, 'peluches', 'Peluches', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (39, 6, 'chocolates', 'Chocolates', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (40, 6, 'personalizados', 'Personalizados', 6);
-- Mapeo original producto -> subcategoría (graduaciones, 2 productos):
UPDATE productos SET subcategoria_id=35 WHERE id=3;
UPDATE productos SET subcategoria_id=35 WHERE id=4;

-- Categoría 'bodas' (id=8) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (46, 8, 'ramos-de-novia', 'Ramos de novia', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (47, 8, 'boutonnieres', 'Boutonnières', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (48, 8, 'centros-de-mesa', 'Centros de mesa', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (49, 8, 'decoracion-floral', 'Decoración floral', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (50, 8, 'iglesias', 'Iglesias', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (51, 8, 'recepciones', 'Recepciones', 6);
-- Mapeo original producto -> subcategoría (bodas, 1 productos):
UPDATE productos SET subcategoria_id=46 WHERE id=64;

-- Categoría 'funebres' (id=7) — subcategorías (5 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (41, 7, 'coronas', 'Coronas', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (42, 7, 'cruces', 'Cruces', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (43, 7, 'corazones', 'Corazones', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (44, 7, 'arreglos-verticales', 'Arreglos verticales', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (45, 7, 'ramos-funebres', 'Ramos fúnebres', 5);
-- Mapeo original producto -> subcategoría (funebres, 21 productos):
UPDATE productos SET subcategoria_id=41 WHERE id=71;
UPDATE productos SET subcategoria_id=41 WHERE id=73;
UPDATE productos SET subcategoria_id=41 WHERE id=74;
UPDATE productos SET subcategoria_id=41 WHERE id=76;
UPDATE productos SET subcategoria_id=41 WHERE id=105;
UPDATE productos SET subcategoria_id=41 WHERE id=106;
UPDATE productos SET subcategoria_id=42 WHERE id=72;
UPDATE productos SET subcategoria_id=42 WHERE id=75;
UPDATE productos SET subcategoria_id=43 WHERE id=78;
UPDATE productos SET subcategoria_id=44 WHERE id=77;
UPDATE productos SET subcategoria_id=44 WHERE id=107;
UPDATE productos SET subcategoria_id=44 WHERE id=118;
UPDATE productos SET subcategoria_id=44 WHERE id=124;
UPDATE productos SET subcategoria_id=44 WHERE id=125;
UPDATE productos SET subcategoria_id=44 WHERE id=126;
UPDATE productos SET subcategoria_id=44 WHERE id=133;
UPDATE productos SET subcategoria_id=44 WHERE id=134;
UPDATE productos SET subcategoria_id=44 WHERE id=135;
UPDATE productos SET subcategoria_id=45 WHERE id=104;
UPDATE productos SET subcategoria_id=45 WHERE id=136;
UPDATE productos SET subcategoria_id=45 WHERE id=138;

-- Categoría 'flores-preservadas' (id=18) — subcategorías (3 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (86, 18, 'cajas', 'Cajas', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (87, 18, 'cupulas', 'Cúpulas', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (88, 18, 'marcos', 'Marcos', 3);
-- Mapeo original producto -> subcategoría (flores-preservadas, 0 productos):

-- Categoría 'globos' (id=10) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (59, 10, 'burbuja', 'Burbuja', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (60, 10, 'helio', 'Helio', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (61, 10, 'numeros', 'Números', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (62, 10, 'letras', 'Letras', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (63, 10, 'personalizados', 'Personalizados', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (64, 10, 'arcos-y-bouquets', 'Arcos y bouquets', 6);
-- Mapeo original producto -> subcategoría (globos, 1 productos):
UPDATE productos SET subcategoria_id=59 WHERE id=128;

-- Categoría 'amor-y-romance' (id=5) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (29, 5, 'aniversario', 'Aniversario', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (30, 5, 'te-amo', 'Te amo', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (31, 5, 'pedida-de-perdon', 'Pedida de perdón', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (32, 5, 'primeras-citas', 'Primeras citas', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (33, 5, 'pedida-de-mano', 'Pedida de mano', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (34, 5, 'san-valentin', 'San Valentín', 6);
-- Mapeo original producto -> subcategoría (amor-y-romance, 2 productos):
UPDATE productos SET subcategoria_id=29 WHERE id=148;
UPDATE productos SET subcategoria_id=30 WHERE id=79;

-- Categoría 'peluches' (id=12) — subcategorías (6 filas)
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (71, 12, 'osos', 'Osos', 1);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (72, 12, 'stitch', 'Stitch', 2);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (73, 12, 'capibara', 'Capibara', 3);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (74, 12, 'personajes', 'Personajes', 4);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (75, 12, 'gigantes', 'Gigantes', 5);
INSERT INTO subcategorias (id, categoria_id, slug, nombre, orden) VALUES (76, 12, 'mini-peluches', 'Mini peluches', 6);
-- Mapeo original producto -> subcategoría (peluches, 0 productos):

