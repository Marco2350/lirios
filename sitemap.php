<?php
/**
 * Sitemap XML dinámico: páginas fijas del sitio + una entrada por cada
 * producto disponible, generado desde MySQL en vivo. La URL base se
 * calcula del propio request, así que funciona igual en localhost que
 * en el dominio real una vez publicado (no hay que tocar nada al mudarse
 * de hosting/dominio, ver CLAUDE.md sección 4 "portabilidad").
 */

declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/config/db.php';

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$paginas = ['/', '/catalogo.html', '/personalizar.html', '/nosotros.html', '/contacto.html', '/politica-privacidad.html', '/terminos-condiciones.html'];

try {
    $productos = db()->query('SELECT slug FROM productos WHERE disponible = 1')->fetchAll();
} catch (Throwable $e) {
    $productos = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($paginas as $ruta) {
    echo '  <url><loc>' . htmlspecialchars($base . $ruta, ENT_XML1) . '</loc></url>' . "\n";
}
foreach ($productos as $p) {
    $url = $base . '/producto.html?id=' . rawurlencode($p['slug']);
    echo '  <url><loc>' . htmlspecialchars($url, ENT_XML1) . '</loc></url>' . "\n";
}

echo '</urlset>';
