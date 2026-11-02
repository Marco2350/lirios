-- =========================================================
-- Migración 2026-11-01 — Usuarios, roles con permisos por módulo y canales sociales
-- =========================================================
-- 1) pedidos.canal: se suman 'instagram', 'facebook' y 'tiktok' (ventas por
--    redes sociales atendidas por un empleado).
-- 2) Usuarios del panel (admin_usuarios), cada uno con un rol. Los roles
--    (admin_roles) son parametrizables desde /admin/roles.php: para cada
--    módulo se marca qué puede hacer (ver / crear / editar / eliminar) en
--    admin_rol_permisos. Un rol con es_dueno = 1 tiene acceso total y no
--    se puede restringir.
-- 3) Siembra: rol «Dueño» (total) y rol «Servicio al cliente» (solo
--    pedidos: ver, crear y editar; sin eliminar), y el usuario «marco»
--    con el rol Dueño y la contraseña que ya se usaba en el panel.
-- Segura de correr más de una vez.
-- =========================================================

ALTER TABLE pedidos
  MODIFY COLUMN canal ENUM('web','tienda','whatsapp','instagram','facebook','tiktok') NOT NULL DEFAULT 'web';

CREATE TABLE IF NOT EXISTS admin_roles (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(200) DEFAULT NULL,
  es_dueno    TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = acceso total, sin restricciones
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_rol_permisos (
  rol_id          INT NOT NULL,
  modulo          VARCHAR(40) NOT NULL,
  puede_ver       TINYINT(1) NOT NULL DEFAULT 0,
  puede_crear     TINYINT(1) NOT NULL DEFAULT 0,
  puede_editar    TINYINT(1) NOT NULL DEFAULT 0,
  puede_eliminar  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (rol_id, modulo),
  FOREIGN KEY (rol_id) REFERENCES admin_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_usuarios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  usuario       VARCHAR(50) NOT NULL UNIQUE,
  nombre        VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol_id        INT NOT NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME DEFAULT NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_roles (id, nombre, descripcion, es_dueno) VALUES
  (1, 'Dueño', 'Acceso total al panel, incluido eliminar y administrar usuarios.', 1),
  (2, 'Servicio al cliente', 'Atiende pedidos: los ve, los crea y los edita, pero no puede eliminar.', 0);

INSERT IGNORE INTO admin_rol_permisos (rol_id, modulo, puede_ver, puede_crear, puede_editar, puede_eliminar) VALUES
  (2, 'pedidos', 1, 1, 1, 0);

INSERT IGNORE INTO admin_usuarios (id, usuario, nombre, password_hash, rol_id) VALUES
  (1, 'marco', 'Marco Palma', '$2y$10$TvxGpGaHB37/aBhPEyOH1uFcYu/5isbqYer2ewJUOA7cOn7bMwnaa', 1);
