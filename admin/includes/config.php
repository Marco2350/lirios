<?php
/**
 * Configuración del panel de administración.
 * ⚠️ Contraseña temporal de desarrollo: "Lirios2026!"
 * Cambiarla antes de publicar: generar un hash nuevo con
 *   php -r "echo password_hash('NUEVA_CONTRASENA', PASSWORD_DEFAULT);"
 * y reemplazar el valor de ADMIN_PASSWORD_HASH.
 */

define('ADMIN_PASSWORD_HASH', '$2y$10$F/e0ZtgBcAYQJo7KFF06teqwgN3J5QakbIPfJULsIG/Dn63w7RJ3e');

// Rutas absolutas a los archivos de datos del sitio público
define('DATA_DIR', dirname(__DIR__, 2) . '/data');
define('PRODUCTOS_JSON', DATA_DIR . '/productos.json');
define('OPCIONES_JSON', DATA_DIR . '/opciones-personalizacion.json');
define('IMAGENES_DIR', dirname(__DIR__, 2) . '/images/productos');
