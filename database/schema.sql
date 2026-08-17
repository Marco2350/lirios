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
  id        INT AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(60)  NOT NULL UNIQUE,
  nombre    VARCHAR(100) NOT NULL,
  icono     VARCHAR(10)  DEFAULT NULL,
  orden     INT NOT NULL DEFAULT 0
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

CREATE TABLE productos (
  id                       INT AUTO_INCREMENT PRIMARY KEY,
  subcategoria_id          INT NOT NULL,
  slug                     VARCHAR(140) NOT NULL UNIQUE,
  nombre                   VARCHAR(150) NOT NULL,
  descripcion_corta        VARCHAR(255) NOT NULL,
  descripcion              TEXT,
  imagen                   VARCHAR(255) DEFAULT NULL,
  incluye                  VARCHAR(255) NOT NULL DEFAULT 'Tarjeta personalizada y empaque premium',
  entrega_disponible       TINYINT(1) NOT NULL DEFAULT 1,
  retiro_tienda_disponible TINYINT(1) NOT NULL DEFAULT 1,
  disponible               TINYINT(1) NOT NULL DEFAULT 1,
  destacado                TINYINT(1) NOT NULL DEFAULT 0,
  creado_en                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada producto puede venderse en varias tallas, cada una con su propio precio.
CREATE TABLE producto_variantes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  producto_id  INT NOT NULL,
  talla        ENUM('S','M','L','XL') NOT NULL,
  precio       DECIMAL(10,2) NOT NULL,
  disponible   TINYINT(1) NOT NULL DEFAULT 1,
  orden        INT NOT NULL DEFAULT 0,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_producto_talla (producto_id, talla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Opciones del personalizador de ramos
-- ---------------------------------------------------------

CREATE TABLE pers_flores (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  slug    VARCHAR(60) NOT NULL UNIQUE,
  nombre  VARCHAR(100) NOT NULL,
  precio  DECIMAL(10,2) NOT NULL DEFAULT 0,
  kind    VARCHAR(30) NOT NULL DEFAULT 'rose',
  orden   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pers_colores (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  slug    VARCHAR(60) NOT NULL UNIQUE,
  nombre  VARCHAR(100) NOT NULL,
  css     VARCHAR(150) NOT NULL,
  orden   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pers_wraps (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(60) NOT NULL UNIQUE,
  nombre       VARCHAR(100) NOT NULL,
  precio       DECIMAL(10,2) NOT NULL DEFAULT 0,
  color        VARCHAR(20) NOT NULL DEFAULT '#CCCCCC',
  descripcion  VARCHAR(150) DEFAULT '',
  orden        INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pers_ribbons (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  slug    VARCHAR(60) NOT NULL UNIQUE,
  nombre  VARCHAR(100) NOT NULL,
  precio  DECIMAL(10,2) NOT NULL DEFAULT 0,
  color   VARCHAR(20) NOT NULL DEFAULT '#CCCCCC',
  orden   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pers_extras (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  slug    VARCHAR(60) NOT NULL UNIQUE,
  nombre  VARCHAR(100) NOT NULL,
  precio  DECIMAL(10,2) NOT NULL DEFAULT 0,
  orden   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Pedidos enviados por WhatsApp (registrados desde /api/pedidos.php
-- al hacer clic en "Enviar pedido por WhatsApp" en carrito.html).
-- Alimentan la reportería de ventas del panel admin.
-- ---------------------------------------------------------

CREATE TABLE pedidos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  cliente_nombre VARCHAR(150) DEFAULT NULL,
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

INSERT INTO categorias (slug, nombre, icono, orden) VALUES
  ('ramos-florales',          'Ramos Florales',          NULL, 1),
  ('arreglos-en-base',        'Arreglos en Base',        NULL, 2),
  ('regalos-y-complementos',  'Regalos y Complementos',  NULL, 3),
  ('cumpleanos',              'Cumpleaños',               NULL, 4),
  ('amor-y-romance',          'Amor y Romance',          NULL, 5),
  ('graduaciones',            'Graduaciones',             NULL, 6),
  ('condolencias',            'Condolencias',             NULL, 7),
  ('bodas-y-eventos',         'Bodas y Eventos',          NULL, 8),
  ('caballero',               'Caballero',                NULL, 9),
  ('globos',                  'Globos',                   NULL, 10),
  ('infantil',                'Infantil',                 NULL, 11),
  ('peluches',                'Peluches',                 NULL, 12);

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
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'chocolates',     'Chocolates',     1),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'perfumes',       'Perfumes',       2),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'vinos-y-whisky', 'Vinos y Whisky', 3),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'tazas',          'Tazas',          4),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'stanley',        'Stanley',        5),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'agendas',        'Agendas',        6),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'globos',         'Globos',         7),
  ((SELECT id FROM categorias WHERE slug='regalos-y-complementos'), 'tarjetas',       'Tarjetas',       8),

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
  ((SELECT id FROM categorias WHERE slug='condolencias'), 'coronas',            'Coronas',             1),
  ((SELECT id FROM categorias WHERE slug='condolencias'), 'cruces',             'Cruces',              2),
  ((SELECT id FROM categorias WHERE slug='condolencias'), 'corazones',          'Corazones',           3),
  ((SELECT id FROM categorias WHERE slug='condolencias'), 'arreglos-verticales','Arreglos verticales', 4),
  ((SELECT id FROM categorias WHERE slug='condolencias'), 'ramos-funebres',     'Ramos fúnebres',      5),

  -- 8. Bodas y Eventos
  ((SELECT id FROM categorias WHERE slug='bodas-y-eventos'), 'ramos-de-novia',    'Ramos de novia',     1),
  ((SELECT id FROM categorias WHERE slug='bodas-y-eventos'), 'boutonnieres',      'Boutonnières',       2),
  ((SELECT id FROM categorias WHERE slug='bodas-y-eventos'), 'centros-de-mesa',   'Centros de mesa',    3),
  ((SELECT id FROM categorias WHERE slug='bodas-y-eventos'), 'decoracion-floral', 'Decoración floral',  4),
  ((SELECT id FROM categorias WHERE slug='bodas-y-eventos'), 'iglesias',          'Iglesias',           5),
  ((SELECT id FROM categorias WHERE slug='bodas-y-eventos'), 'recepciones',      'Recepciones',        6),

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
  ((SELECT id FROM categorias WHERE slug='peluches'), 'mini-peluches','Mini peluches',6);

-- =========================================================
-- Catálogo de productos: SIN datos semilla a propósito.
--
-- Los 7 productos de ejemplo (Ramo Dulce Amor, Canasta Primaveral,
-- etc.) que existían aquí eran solo placeholders para desarrollo y
-- se quitaron de esta instalación limpia para no publicar un
-- catálogo ficticio por accidente. Claudia carga el catálogo real
-- (nombre, categoría, tallas y precios, fotos) desde
-- /admin/productos.php una vez que el sitio esté en el hosting
-- final — ver CLAUDE.md sección 9, pendiente "Catálogo real de
-- productos".
-- =========================================================

-- =========================================================
-- Datos semilla — opciones del personalizador
-- (migrado de data/opciones-personalizacion.json)
-- =========================================================

INSERT INTO pers_flores (slug, nombre, precio, kind, orden) VALUES
  ('rosas',       'Rosas',       450, 'rose',      1),
  ('girasoles',   'Girasoles',   400, 'sunflower', 2),
  ('lirios',      'Lirios',      500, 'lily',      3),
  ('gerberas',    'Gerberas',    380, 'gerbera',   4),
  ('tulipanes',   'Tulipanes',   650, 'tulip',     5),
  ('claveles',    'Claveles',    300, 'carnation', 6),
  ('margaritas',  'Margaritas',  320, 'daisy',     7),
  ('astromelias', 'Astromelias', 350, 'aster',     8),
  ('mixto',       'Ramo mixto',  480, 'mixed',     9);

INSERT INTO pers_colores (slug, nombre, css, orden) VALUES
  ('rojo',     'Rojo',     '#B3261E', 1),
  ('rosado',   'Rosado',   '#E88BAD', 2),
  ('blanco',   'Blanco',   '#F5EFE6', 3),
  ('amarillo', 'Amarillo', '#E7C544', 4),
  ('lila',     'Lila',     '#B497D6', 5),
  ('naranja',  'Naranja',  '#E8894A', 6),
  ('mixto',    'Mixto',    'linear-gradient(135deg, #B3261E 0%, #E7C544 50%, #B497D6 100%)', 7);

INSERT INTO pers_wraps (slug, nombre, precio, color, descripcion, orden) VALUES
  ('kraft',   'Papel Kraft Natural',     60, '#d2b48c', 'Textura natural y rústica', 1),
  ('blanco',  'Papel Blanco Texturizado', 80, '#f5f0e6', 'Elegante y limpio',        2),
  ('rosado',  'Papel Rosado Suave',       75, '#f8d7d7', 'Romántico y delicado',     3),
  ('verde',   'Papel Verde Salvia',       70, '#a8b5a0', 'Fresco y moderno',         4);

INSERT INTO pers_ribbons (slug, nombre, precio, color, orden) VALUES
  ('dorado',  'Listón Dorado',           45, '#C8860B', 1),
  ('rosado',  'Listón Rosado',           40, '#E88BAD', 2),
  ('blanco',  'Listón Blanco',           35, '#f5f0e6', 3),
  ('verde',   'Listón Verde',            40, '#5a7a5a', 4),
  ('negro',   'Listón Negro Elegante',   50, '#2B2118', 5);

INSERT INTO pers_extras (slug, nombre, precio, orden) VALUES
  ('chocolates', 'Chocolates',             120, 1),
  ('peluche',    'Peluche pequeño',        200, 2),
  ('globo',      'Globo metálico',          80, 3),
  ('tarjeta',    'Tarjeta con dedicatoria',  30, 4),
  ('florero',    'Florero de vidrio',      150, 5);

-- ---------------------------------------------------------
-- Intentos de acceso al panel admin (freno a fuerza bruta en el login)
-- ---------------------------------------------------------

CREATE TABLE admin_login_intentos (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  ip        VARCHAR(45) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_fecha (ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
