<?php
/**
 * Redirect corto y estable a la foto de un producto — usado en el enlace
 * "Referencia del arreglo" del mensaje de WhatsApp (ver js/whatsapp.js).
 *
 * Nota (2026-09-01): antes ese enlace era la ruta completa a la imagen
 * (ej. ".../images/productos/05786acc-b908-49c3-979e-0ddd1fa723f9.webp"),
 * un link largo con muchos caracteres parecidos entre sí y terminado en
 * una extensión de archivo. Un cliente reportó no poder ver la foto
 * porque el link le llegó cortado justo antes del ".webp" final —
 * probablemente al reenviar/copiar el mensaje de WhatsApp. Este endpoint
 * acorta ese enlace a "foto.php?p=<slug-del-producto>" (sin extensión al
 * final que se pueda perder) y redirige (302) al archivo real; la ruta
 * completa la resuelve el servidor, no depende de que el link se haya
 * copiado completo hasta el último carácter.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$slug = trim((string) ($_GET['p'] ?? ''));
$ruta = null;

if ($slug !== '') {
    $stmt = db()->prepare('SELECT imagen FROM productos WHERE slug = ?');
    $stmt->execute([$slug]);
    $ruta = $stmt->fetchColumn() ?: null;
}

if (!$ruta) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Foto no disponible.';
    exit;
}

/* $ruta es relativa (ej. "images/productos/xxx.webp"). Al estar foto.php
   en la raíz del sitio, un Location relativo resuelve correcto tanto en
   el dominio real como en una subcarpeta (este entorno de desarrollo). */
header('Location: ' . $ruta, true, 302);
