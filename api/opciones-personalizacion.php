<?php
/**
 * GET /api/opciones-personalizacion.php
 * Opciones del constructor de ramos, consultadas en vivo desde MySQL.
 * Misma forma que el antiguo data/opciones-personalizacion.json para
 * que js/personalizar.js no necesite cambios más allá de la URL.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $flores = db()->query(
        'SELECT slug AS id, nombre, precio, kind FROM pers_flores ORDER BY orden, nombre'
    )->fetchAll();
    foreach ($flores as &$f) { $f['precio'] = (float) $f['precio']; }
    unset($f);

    $colores = db()->query(
        'SELECT slug AS id, nombre, css FROM pers_colores ORDER BY orden, nombre'
    )->fetchAll();

    $wraps = db()->query(
        'SELECT slug AS id, nombre, precio, color, descripcion AS description FROM pers_wraps ORDER BY orden, nombre'
    )->fetchAll();
    foreach ($wraps as &$w) { $w['precio'] = (float) $w['precio']; }
    unset($w);

    $ribbons = db()->query(
        'SELECT slug AS id, nombre, precio, color FROM pers_ribbons ORDER BY orden, nombre'
    )->fetchAll();
    foreach ($ribbons as &$r) { $r['precio'] = (float) $r['precio']; }
    unset($r);

    $extras = db()->query(
        'SELECT slug AS id, nombre, precio FROM pers_extras ORDER BY orden, nombre'
    )->fetchAll();
    foreach ($extras as &$x) { $x['precio'] = (float) $x['precio']; }
    unset($x);

    echo json_encode([
        'flores' => $flores,
        'colores' => $colores,
        'wraps' => $wraps,
        'ribbons' => $ribbons,
        'extras' => $extras,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar las opciones de personalización.']);
}
