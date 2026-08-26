<?php
/**
 * GET /api/producto.php?slug=ramo-dulce-amor
 * Detalle de un producto (precio único) + relacionados
 * de la misma subcategoría, consultado en vivo desde MySQL.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $slug = trim((string) ($_GET['slug'] ?? ''));
    if ($slug === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Falta el parámetro slug.']);
        exit;
    }

    $stmt = db()->prepare(
        'SELECT p.id, p.slug, p.nombre, p.descripcion_corta, p.descripcion, p.imagen,
                p.incluye, p.precio, p.entrega_disponible, p.retiro_tienda_disponible,
                p.disponible, p.destacado,
                s.id AS subcategoria_id, c.slug AS categoria_slug, c.nombre AS categoria_nombre,
                c.icono AS categoria_icono, s.slug AS subcategoria_slug, s.nombre AS subcategoria_nombre
         FROM productos p
         JOIN subcategorias s ON s.id = p.subcategoria_id
         JOIN categorias c ON c.id = s.categoria_id
         WHERE p.slug = :slug'
    );
    $stmt->execute(['slug' => $slug]);
    $p = $stmt->fetch();

    if (!$p) {
        http_response_code(404);
        echo json_encode(['error' => 'Producto no encontrado.']);
        exit;
    }

    $relStmt = db()->prepare(
        'SELECT p2.slug, p2.nombre, p2.imagen, p2.precio
         FROM productos p2
         WHERE p2.subcategoria_id = :sub AND p2.id != :id AND p2.disponible = 1
         ORDER BY p2.destacado DESC, p2.nombre
         LIMIT 3'
    );
    $relStmt->execute(['sub' => $p['subcategoria_id'], 'id' => $p['id']]);
    $relacionados = array_map(fn($r) => [
        'slug' => $r['slug'],
        'nombre' => $r['nombre'],
        'imagen' => $r['imagen'],
        'precio' => (float) $r['precio'],
    ], $relStmt->fetchAll());

    echo json_encode([
        'producto' => [
            'id' => (int) $p['id'],
            'slug' => $p['slug'],
            'nombre' => $p['nombre'],
            'descripcionCorta' => $p['descripcion_corta'],
            'descripcion' => $p['descripcion'],
            'imagen' => $p['imagen'],
            'incluye' => $p['incluye'],
            'entregaDisponible' => (bool) $p['entrega_disponible'],
            'retiroDisponible' => (bool) $p['retiro_tienda_disponible'],
            'disponible' => (bool) $p['disponible'],
            'destacado' => (bool) $p['destacado'],
            'categoria' => [
                'slug' => $p['categoria_slug'],
                'nombre' => $p['categoria_nombre'],
                'icono' => $p['categoria_icono'],
            ],
            'subcategoria' => [
                'slug' => $p['subcategoria_slug'],
                'nombre' => $p['subcategoria_nombre'],
            ],
            'precio' => (float) $p['precio'],
        ],
        'relacionados' => $relacionados,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo cargar el producto.']);
}
