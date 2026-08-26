-- =========================================================
-- Lirios Floristería — esquema normalizado (MySQL/MariaDB)
-- Fuente de verdad del catálogo y del personalizador.
-- Se consulta en vivo desde /api/*.php (sitio público) y
-- desde /admin (panel de administración).
--
-- INSTALACIÓN LIMPIA (hosting final / cPanel):
-- 1. Crear la base de datos y el usuario desde "Bases de datos
--    MySQL" en cPanel (el nombre queda con el prefijo de la cuenta,
--    ej. "midominio_lirios") y asignar el usuario a esa base con
--    todos los privilegios. Un usuario de cPanel normal NO tiene
--    permiso para CREATE DATABASE por SQL, así que este script ya
--    NO lo intenta — impórtalo con esa base ya seleccionada:
--      · phpMyAdmin: elegir la base en el panel izquierdo antes de
--        usar "Importar".
--      · Línea de comandos: mysql -u USUARIO -p NOMBRE_BASE < schema.sql
-- 2. Actualizar /.env (copiado de /.env.example) con host, nombre
--    de base, usuario y contraseña reales de ese hosting.
--
-- DESARROLLO LOCAL (XAMPP): si necesitas crear la base tú mismo,
-- descomenta las dos líneas siguientes antes de importar.
-- =========================================================

-- CREATE DATABASE IF NOT EXISTS lirios
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE lirios;

-- ---------------------------------------------------------
-- Taxonomía: categorías > subcategorías
-- ---------------------------------------------------------

CREATE TABLE categorias (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(60)  NOT NULL UNIQUE,
  nombre          VARCHAR(150) NOT NULL,
  icono           VARCHAR(10)  DEFAULT NULL,
  imagen_portada  VARCHAR(255) DEFAULT NULL,
  orden           INT NOT NULL DEFAULT 0,
  visible         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subcategorias (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id  INT NOT NULL,
  slug          VARCHAR(60)  NOT NULL,
  nombre        VARCHAR(100) NOT NULL,
  orden         INT NOT NULL DEFAULT 0,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_categoria_slug (categoria_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Catálogo de productos
-- ---------------------------------------------------------

-- Nota (2026-08-26): el catálogo tuvo tallas S/M/L/XL con precio propio por
-- talla (tabla producto_variantes). La clienta pidió quitar ese sistema y
-- dejar un precio único por producto ("quitar segmentos de bases"). Se migró
-- el precio de cada producto multi-talla al de la talla M (o la disponible
-- más cercana a M si no tenía M) y se eliminó producto_variantes — backup
-- previo en database/backups/producto_variantes_20260826_073258.sql.
CREATE TABLE productos (
  id                       INT AUTO_INCREMENT PRIMARY KEY,
  subcategoria_id          INT NOT NULL,
  slug                     VARCHAR(140) NOT NULL UNIQUE,
  nombre                   VARCHAR(150) NOT NULL,
  descripcion_corta        VARCHAR(255) NOT NULL,
  descripcion              TEXT,
  imagen                   VARCHAR(255) DEFAULT NULL,
  incluye                  VARCHAR(255) NOT NULL DEFAULT 'Tarjeta personalizada y empaque premium',
  precio                   DECIMAL(10,2) NOT NULL,
  entrega_disponible       TINYINT(1) NOT NULL DEFAULT 1,
  retiro_tienda_disponible TINYINT(1) NOT NULL DEFAULT 1,
  disponible               TINYINT(1) NOT NULL DEFAULT 1,
  destacado                TINYINT(1) NOT NULL DEFAULT 0,
  creado_en                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Pedidos enviados por WhatsApp (registrados desde /api/pedidos.php
-- al hacer clic en "Enviar pedido por WhatsApp" en carrito.html).
-- Alimentan la reportería de ventas del panel admin.
-- ---------------------------------------------------------

CREATE TABLE pedidos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  cliente_nombre VARCHAR(150) DEFAULT NULL,
  telefono       VARCHAR(30) DEFAULT NULL,
  fecha_entrega  DATE DEFAULT NULL,
  hora_entrega   VARCHAR(20) DEFAULT NULL,
  tipo_entrega   VARCHAR(40) DEFAULT NULL,
  direccion      VARCHAR(300) DEFAULT NULL,
  dedicatoria    VARCHAR(300) DEFAULT NULL,
  forma_pago     VARCHAR(100) DEFAULT NULL,
  nota           VARCHAR(500) DEFAULT NULL,
  ip             VARCHAR(45) DEFAULT NULL,
  estado         ENUM('pendiente','coordinado','entregado') NOT NULL DEFAULT 'pendiente',
  total          DECIMAL(10,2) NOT NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id        INT NOT NULL,
  tipo             ENUM('producto','personalizado') NOT NULL,
  producto_id      INT DEFAULT NULL,
  codigo           VARCHAR(20) DEFAULT NULL,
  nombre           VARCHAR(200) NOT NULL,
  detalle          VARCHAR(500) DEFAULT NULL,
  precio_unitario  DECIMAL(10,2) NOT NULL,
  cantidad         INT NOT NULL,
  subtotal         DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_pedidos_creado_en ON pedidos(creado_en);
CREATE INDEX idx_pedidos_ip_creado ON pedidos(ip, creado_en);
CREATE INDEX idx_pedido_items_codigo ON pedido_items(codigo);

-- =========================================================
-- Datos semilla — taxonomía (12 categorías del negocio)
-- =========================================================

-- Taxonomía vigente desde 2026-08-21: 14 categorías pedidas por la clienta,
-- cada una pensada como página propia con portada distinta (imagen_portada).
-- 'amor-y-romance' y 'peluches' quedaron huérfanas de esta lista (no se
-- borraron por si tienen productos asociados) — se marcan visible=0 para no
-- aparecer en el menú/grilla del sitio hasta que se decida fusionarlas o
-- retirarlas (ver columna `visible`, sección 6.1 de CLAUDE.md, 2026-08-26).
-- 'arreglos-florales' se creó el 2026-08-21 y se retiró el mismo día a
-- pedido de la clienta ("no, porque todos son arreglos florales" — es
-- redundante como categoría, no aporta nada que no diga ya cada producto).
INSERT INTO categorias (slug, nombre, icono, imagen_portada, orden, visible) VALUES
  ('ramos-florales',                    'Ramos Florales',                NULL, 'images/categorias/ramos-florales.webp',                    1,  1),
  ('arreglos-en-base',                  'Arreglos en Base',              NULL, 'images/categorias/arreglos-en-base.webp',                  2,  1),
  ('cumpleanos',                        'Arreglos Cumpleaños',           NULL, 'images/categorias/cumpleanos.webp',                        3,  1),
  ('caballero',                         'Arreglos para Caballero',       NULL, NULL,                                                        4,  1),
  ('infantil',                          'Arreglos Infantiles',           NULL, 'images/categorias/infantil.webp',                          5,  1),
  ('desayuno-sorpresa',                 'Desayuno Sorpresa',             NULL, 'images/categorias/desayuno-sorpresa.webp',                 6,  1),
  ('aniversario',                       'Aniversario',                   NULL, 'images/categorias/aniversario.webp',                       7,  1),
  ('chocolates-perfumes-complementos',  'Complementos',                  NULL, 'images/categorias/chocolates-perfumes-complementos.webp',  8,  1),
  ('graduaciones',                      'Arreglos de Graduación',        NULL, 'images/categorias/graduaciones.webp',                      9,  1),
  ('bodas',                             'Arreglos para Bodas',           NULL, NULL,                                                        10, 1),
  ('funebres',                          'Arreglos Fúnebres',             NULL, 'images/categorias/funebres.webp',                          11, 1),
  ('flores-preservadas',                'Arreglos con Flores Preservadas', NULL, NULL,                                                      12, 1),
  ('globos',                            'Globos',                        NULL, 'images/categorias/globos.webp',                            13, 1),
  ('amor-y-romance',                    'Amor y Romance',                NULL, NULL,                                                        90, 0),
  ('peluches',                          'Peluches',                      NULL, NULL,                                                        91, 0);

INSERT INTO subcategorias (categoria_id, slug, nombre, orden) VALUES
  -- 1. Ramos Florales
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'rosas',       'Rosas',       1),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'girasoles',   'Girasoles',   2),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'hortensias',  'Hortensias',  3),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'tulipanes',   'Tulipanes',   4),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'lirios',      'Lirios',      5),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'gerberas',    'Gerberas',    6),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'mini-rosas',  'Mini Rosas',  7),
  ((SELECT id FROM categorias WHERE slug='ramos-florales'), 'mixtos',      'Mixtos',      8),

  -- 2. Arreglos en Base
  ((SELECT id FROM categorias WHERE slug='arreglos-en-base'), 'vidrio',          'Vidrio',          1),
  ((SELECT id FROM categorias WHERE slug='arreglos-en-base'), 'ceramica',        'Cerámica',        2),
  ((SELECT id FROM categorias WHERE slug='arreglos-en-base'), 'madera',          'Madera',          3),
  ((SELECT id FROM categorias WHERE slug='arreglos-en-base'), 'acrilico',        'Acrílico',        4),
  ((SELECT id FROM categorias WHERE slug='arreglos-en-base'), 'carton-premium',  'Cartón Premium',  5),
  ((SELECT id FROM categorias WHERE slug='arreglos-en-base'), 'canastas',        'Canastas',        6),

  -- 3. Regalos y Complementos
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'chocolates',     'Chocolates',     1),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'perfumes',       'Perfumes',       2),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'vinos-y-whisky', 'Vinos y Whisky', 3),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'tazas',          'Tazas',          4),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'stanley',        'Stanley',        5),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'agendas',        'Agendas',        6),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'globos',         'Globos',         7),
  ((SELECT id FROM categorias WHERE slug='chocolates-perfumes-complementos'), 'tarjetas',       'Tarjetas',       8),

  -- 4. Cumpleaños
  ((SELECT id FROM categorias WHERE slug='cumpleanos'), 'para-ella',      'Para ella',       1),
  ((SELECT id FROM categorias WHERE slug='cumpleanos'), 'para-el',        'Para él',         2),
  ((SELECT id FROM categorias WHERE slug='cumpleanos'), 'infantil',       'Infantil',        3),
  ((SELECT id FROM categorias WHERE slug='cumpleanos'), 'con-globos',     'Con globos',      4),
  ((SELECT id FROM categorias WHERE slug='cumpleanos'), 'con-chocolates', 'Con chocolates',  5),
  ((SELECT id FROM categorias WHERE slug='cumpleanos'), 'sorpresas',      'Sorpresas',       6),

  -- 5. Amor y Romance
  ((SELECT id FROM categorias WHERE slug='amor-y-romance'), 'aniversario',        'Aniversario',         1),
  ((SELECT id FROM categorias WHERE slug='amor-y-romance'), 'te-amo',             'Te amo',              2),
  ((SELECT id FROM categorias WHERE slug='amor-y-romance'), 'pedida-de-perdon',   'Pedida de perdón',    3),
  ((SELECT id FROM categorias WHERE slug='amor-y-romance'), 'primeras-citas',     'Primeras citas',      4),
  ((SELECT id FROM categorias WHERE slug='amor-y-romance'), 'pedida-de-mano',     'Pedida de mano',      5),
  ((SELECT id FROM categorias WHERE slug='amor-y-romance'), 'san-valentin',       'San Valentín',        6),

  -- 6. Graduaciones
  ((SELECT id FROM categorias WHERE slug='graduaciones'), 'ramos',          'Ramos',          1),
  ((SELECT id FROM categorias WHERE slug='graduaciones'), 'arreglos',       'Arreglos',       2),
  ((SELECT id FROM categorias WHERE slug='graduaciones'), 'globos',         'Globos',         3),
  ((SELECT id FROM categorias WHERE slug='graduaciones'), 'peluches',       'Peluches',       4),
  ((SELECT id FROM categorias WHERE slug='graduaciones'), 'chocolates',     'Chocolates',     5),
  ((SELECT id FROM categorias WHERE slug='graduaciones'), 'personalizados', 'Personalizados', 6),

  -- 7. Condolencias
  ((SELECT id FROM categorias WHERE slug='funebres'), 'coronas',            'Coronas',             1),
  ((SELECT id FROM categorias WHERE slug='funebres'), 'cruces',             'Cruces',              2),
  ((SELECT id FROM categorias WHERE slug='funebres'), 'corazones',          'Corazones',           3),
  ((SELECT id FROM categorias WHERE slug='funebres'), 'arreglos-verticales','Arreglos verticales', 4),
  ((SELECT id FROM categorias WHERE slug='funebres'), 'ramos-funebres',     'Ramos fúnebres',      5),

  -- 8. Bodas y Eventos
  ((SELECT id FROM categorias WHERE slug='bodas'), 'ramos-de-novia',    'Ramos de novia',     1),
  ((SELECT id FROM categorias WHERE slug='bodas'), 'boutonnieres',      'Boutonnières',       2),
  ((SELECT id FROM categorias WHERE slug='bodas'), 'centros-de-mesa',   'Centros de mesa',    3),
  ((SELECT id FROM categorias WHERE slug='bodas'), 'decoracion-floral', 'Decoración floral',  4),
  ((SELECT id FROM categorias WHERE slug='bodas'), 'iglesias',          'Iglesias',           5),
  ((SELECT id FROM categorias WHERE slug='bodas'), 'recepciones',      'Recepciones',        6),

  -- 9. Caballero
  ((SELECT id FROM categorias WHERE slug='caballero'), 'whisky',             'Whisky',             1),
  ((SELECT id FROM categorias WHERE slug='caballero'), 'vinos',              'Vinos',              2),
  ((SELECT id FROM categorias WHERE slug='caballero'), 'cervezas',           'Cervezas',           3),
  ((SELECT id FROM categorias WHERE slug='caballero'), 'chocolates',         'Chocolates',         4),
  ((SELECT id FROM categorias WHERE slug='caballero'), 'perfumes',           'Perfumes',           5),
  ((SELECT id FROM categorias WHERE slug='caballero'), 'ramos-elegantes',    'Ramos elegantes',    6),
  ((SELECT id FROM categorias WHERE slug='caballero'), 'regalos-ejecutivos', 'Regalos ejecutivos', 7),

  -- 10. Globos
  ((SELECT id FROM categorias WHERE slug='globos'), 'burbuja',          'Burbuja',           1),
  ((SELECT id FROM categorias WHERE slug='globos'), 'helio',            'Helio',             2),
  ((SELECT id FROM categorias WHERE slug='globos'), 'numeros',          'Números',           3),
  ((SELECT id FROM categorias WHERE slug='globos'), 'letras',           'Letras',            4),
  ((SELECT id FROM categorias WHERE slug='globos'), 'personalizados',   'Personalizados',    5),
  ((SELECT id FROM categorias WHERE slug='globos'), 'arcos-y-bouquets', 'Arcos y bouquets',  6),

  -- 11. Infantil
  ((SELECT id FROM categorias WHERE slug='infantil'), 'nacimiento',   'Nacimiento',   1),
  ((SELECT id FROM categorias WHERE slug='infantil'), 'baby-shower',  'Baby Shower',  2),
  ((SELECT id FROM categorias WHERE slug='infantil'), 'nina',         'Niña',         3),
  ((SELECT id FROM categorias WHERE slug='infantil'), 'nino',         'Niño',         4),
  ((SELECT id FROM categorias WHERE slug='infantil'), 'personajes',   'Personajes',   5),
  ((SELECT id FROM categorias WHERE slug='infantil'), 'dulces',       'Dulces',       6),

  -- 12. Peluches
  ((SELECT id FROM categorias WHERE slug='peluches'), 'osos',         'Osos',         1),
  ((SELECT id FROM categorias WHERE slug='peluches'), 'stitch',       'Stitch',       2),
  ((SELECT id FROM categorias WHERE slug='peluches'), 'capibara',     'Capibara',     3),
  ((SELECT id FROM categorias WHERE slug='peluches'), 'personajes',   'Personajes',   4),
  ((SELECT id FROM categorias WHERE slug='peluches'), 'gigantes',     'Gigantes',     5),
  ((SELECT id FROM categorias WHERE slug='peluches'), 'mini-peluches','Mini peluches',6),

  -- 14. Desayuno Sorpresa (categoría nueva 2026-08-21)
  ((SELECT id FROM categorias WHERE slug='desayuno-sorpresa'), 'clasico',      'Clásico',       1),
  ((SELECT id FROM categorias WHERE slug='desayuno-sorpresa'), 'con-pastel',   'Con pastel',    2),
  ((SELECT id FROM categorias WHERE slug='desayuno-sorpresa'), 'con-globos',   'Con globos',    3),

  -- 15. Aniversario (categoría nueva 2026-08-21; separada de la vieja "Amor y Romance")
  ((SELECT id FROM categorias WHERE slug='aniversario'), 'ramos',        'Ramos',         1),
  ((SELECT id FROM categorias WHERE slug='aniversario'), 'con-detalles', 'Con detalles',  2),
  ((SELECT id FROM categorias WHERE slug='aniversario'), 'bodas-de-oro', 'Bodas de oro',  3),

  -- 16. Arreglos con Flores Preservadas (categoría nueva 2026-08-21)
  ((SELECT id FROM categorias WHERE slug='flores-preservadas'), 'cajas',    'Cajas',    1),
  ((SELECT id FROM categorias WHERE slug='flores-preservadas'), 'cupulas',  'Cúpulas',  2),
  ((SELECT id FROM categorias WHERE slug='flores-preservadas'), 'marcos',   'Marcos',   3);

-- =========================================================
-- Catálogo de productos: SIN datos semilla a propósito.
--
-- Los 7 productos de ejemplo (Ramo Dulce Amor, Canasta Primaveral,
-- etc.) que existían aquí eran solo placeholders para desarrollo y
-- se quitaron de esta instalación limpia para no publicar un
-- catálogo ficticio por accidente. Claudia carga el catálogo real
-- (nombre, categoría, precio, fotos) desde
-- /admin/productos.php una vez que el sitio esté en el hosting
-- final — ver CLAUDE.md sección 9, pendiente "Catálogo real de
-- productos".
-- =========================================================

-- ---------------------------------------------------------
-- Intentos de acceso al panel admin (freno a fuerza bruta en el login)
-- ---------------------------------------------------------

CREATE TABLE admin_login_intentos (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  ip        VARCHAR(45) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_fecha (ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
