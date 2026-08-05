<?php
/**
 * Configuración del panel de administración.
 * La contraseña se cambia desde el propio panel en
 * /admin/cambiar-password.php (reescribe el hash de abajo).
 * Si hace falta cambiarla a mano: generar un hash nuevo con
 *   php -r "echo password_hash('NUEVA_CONTRASENA', PASSWORD_DEFAULT);"
 * y reemplazar el valor de ADMIN_PASSWORD_HASH.
 */

define('ADMIN_PASSWORD_HASH', '$2y$10$TvxGpGaHB37/aBhPEyOH1uFcYu/5isbqYer2ewJUOA7cOn7bMwnaa');

// Conexión PDO a MySQL (fuente de verdad del catálogo y la personalización)
require_once dirname(__DIR__, 2) . '/config/db.php';

// Carpeta de imágenes de producto
define('IMAGENES_DIR', dirname(__DIR__, 2) . '/images/productos');

/** Minutos de inactividad antes de cerrar la sesión del panel sola. */
const ADMIN_SESION_INACTIVIDAD_MIN = 30;

/**
 * Arranca (o retoma) la sesión del panel con cookies endurecidas
 * (HttpOnly, SameSite=Lax, Secure si hay HTTPS) y la cierra sola si
 * pasó demasiado tiempo sin actividad. Debe llamarse ANTES de leer o
 * escribir $_SESSION en cualquier página de /admin.
 */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $limite = ADMIN_SESION_INACTIVIDAD_MIN * 60;
    if (!empty($_SESSION['lirios_admin']) && !empty($_SESSION['ultima_actividad'])
        && (time() - $_SESSION['ultima_actividad']) > $limite) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['ultima_actividad'] = time();
}

/** IP del visitante, validada (null si no se pudo determinar). */
function ip_visitante(): ?string
{
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
}
