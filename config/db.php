<?php
/**
 * Conexión PDO compartida a la base de datos "lirios" (MySQL/MariaDB).
 * La usan tanto /api/*.php (sitio público) como /admin/includes/functions.php.
 *
 * Las credenciales viven en /.env (fuera del control de versiones, ver
 * .gitignore y .env.example) y NUNCA en este archivo. Si no hay .env
 * (ej. una copia local recién clonada), se usan valores por defecto
 * pensados para XAMPP en 127.0.0.1.
 */

require_once __DIR__ . '/env.php';

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'lirios'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

/**
 * ⚠️ Correo donde llega el aviso de cada pedido nuevo (además de WhatsApp).
 * Se define en .env como NOTIFICACION_EMAIL; si no está presente, se usa
 * este placeholder. En hosting compartido normal (cPanel) la función
 * mail() de PHP funciona sin configuración extra; en XAMPP local no envía
 * nada salvo que se configure un servidor SMTP — es normal que en
 * desarrollo esta notificación "falle" en silencio.
 */
define('NOTIFICACION_EMAIL', env('NOTIFICACION_EMAIL', 'pedidos@liriosfloristeria.com'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            // El detalle real (que puede incluir host/usuario) solo va al
            // log del servidor — nunca a la respuesta HTTP.
            error_log('[lirios] No se pudo conectar a la base de datos: ' . $e->getMessage());
            fallar_conexion_bd();
        }
    }
    return $pdo;
}

/** Corta la ejecución con un mensaje genérico (JSON en /api, HTML en el resto). */
function fallar_conexion_bd(): never
{
    http_response_code(503);
    $esApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

    if ($esApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'El sitio no pudo conectarse a la base de datos. Intenta de nuevo en unos minutos.']);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex, nofollow">
      <title>Servicio no disponible — LIRIOS Floristería</title>
      <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
             font-family:system-ui,-apple-system,Arial,sans-serif;background:#FFF9F0;color:#2B2118;padding:1.5rem}
        .caja{max-width:420px;text-align:center}
        h1{font-size:1.25rem;margin:0 0 .6rem}
        p{color:#7A6B58;margin:0;line-height:1.5}
      </style>
    </head>
    <body>
      <div class="caja">
        <h1>No pudimos conectar con la base de datos</h1>
        <p>Intenta recargar la página en unos minutos. Si el problema continúa, contacta a soporte técnico.</p>
      </div>
    </body>
    </html>
    HTML;
    exit;
}
