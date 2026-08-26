<?php
/**
 * GET /api/categorias.php
 * Árbol de categorías > subcategorías, consultado en vivo desde MySQL.
 * Lo usan el catálogo (filtros) y el panel admin.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $categorias = db()->query(
        'SELECT id, slug, nombre, icono, imagen_portada, orden, visible FROM categorias ORDER BY orden, nombre'
    )->fetchAll();

    $subStmt = db()->query(
        'SELECT id, categoria_id, slug, nombre FROM subcategorias ORDER BY orden, nombre'
    );
    $subsPorCategoria = [];
    foreach ($subStmt->fetchAll() as $s) {
        $subsPorCategoria[$s['categoria_id']][] = [
            'id' => (int) $s['id'],
            'slug' => $s['slug'],
            'nombre' => $s['nombre'],
        ];
    }

    $resultado = array_map(function ($c) use ($subsPorCategoria) {
        return [
            'id' => (int) $c['id'],
            'slug' => $c['slug'],
            'nombre' => $c['nombre'],
            'icono' => $c['icono'],
            'imagenPortada' => $c['imagen_portada'],
            'orden' => (int) $c['orden'],
            'visible' => (bool) $c['visible'],
            'subcategorias' => $subsPorCategoria[$c['id']] ?? [],
        ];
    }, $categorias);

    echo json_encode(['categorias' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo cargar la taxonomía de categorías.']);
}
