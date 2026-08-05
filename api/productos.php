<?php
/**
 * GET /api/productos.php[?categoria=slug&subcategoria=slug&orden=asc|desc&q=texto]
 * Catálogo completo (o filtrado), consultado en vivo desde MySQL.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $categoriaSlug = trim((string) ($_GET['categoria'] ?? ''));
    $subcategoriaSlug = trim((string) ($_GET['subcategoria'] ?? ''));
    $orden = $_GET['orden'] ?? '';
    $busqueda = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80, 'UTF-8'));

    $sql = 'SELECT p.id, p.slug, p.nombre, p.descripcion_corta, p.descripcion, p.imagen,
                   p.incluye, p.entrega_disponible, p.retiro_tienda_disponible,
                   p.disponible, p.destacado,
                   c.slug AS categoria_slug, c.nombre AS categoria_nombre, c.icono AS categoria_icono,
                   s.slug AS subcategoria_slug, s.nombre AS subcategoria_nombre
            FROM productos p
            JOIN subcategorias s ON s.id = p.subcategoria_id
            JOIN categorias c ON c.id = s.categoria_id
            WHERE p.disponible = 1';
    $params = [];

    if ($categoriaSlug !== '') {
        $sql .= ' AND c.slug = :categoria';
        $params['categoria'] = $categoriaSlug;
    }
    if ($subcategoriaSlug !== '') {
        $sql .= ' AND s.slug = :subcategoria';
        $params['subcategoria'] = $subcategoriaSlug;
    }
    if ($busqueda !== '') {
        $sql .= ' AND (p.nombre LIKE :q1 OR p.descripcion_corta LIKE :q2 OR p.descripcion LIKE :q3)';
        $comodin = '%' . str_replace(['%', '_'], ['\%', '\_'], $busqueda) . '%';
        $params['q1'] = $comodin;
        $params['q2'] = $comodin;
        $params['q3'] = $comodin;
    }
    $sql .= ' ORDER BY p.destacado DESC, p.nombre';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll();

    /* Variantes de todos los productos encontrados, agrupadas por producto_id */
    $variantesPorProducto = [];
    if ($filas) {
        $ids = array_column($filas, 'id');
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $vStmt = db()->prepare(
            "SELECT producto_id, talla, precio, disponible
             FROM producto_variantes
             WHERE producto_id IN ($marcadores)
             ORDER BY orden, FIELD(talla,'S','M','L','XL')"
        );
        $vStmt->execute($ids);
        foreach ($vStmt->fetchAll() as $v) {
            $variantesPorProducto[$v['producto_id']][] = [
                'talla' => $v['talla'],
                'precio' => (float) $v['precio'],
                'disponible' => (bool) $v['disponible'],
            ];
        }
    }

    $productos = array_map(function ($p) use ($variantesPorProducto) {
        $variantes = $variantesPorProducto[$p['id']] ?? [];
        $precios = array_column(array_filter($variantes, fn($v) => $v['disponible']), 'precio');
        return [
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
            'precioDesde' => $precios ? min($precios) : null,
            'variantes' => $variantes,
        ];
    }, $filas);

    if ($orden === 'asc') {
        usort($productos, fn($a, $b) => ($a['precioDesde'] ?? 0) <=> ($b['precioDesde'] ?? 0));
    } elseif ($orden === 'desc') {
        usort($productos, fn($a, $b) => ($b['precioDesde'] ?? 0) <=> ($a['precioDesde'] ?? 0));
    }

    echo json_encode(['productos' => $productos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo cargar el catálogo.']);
}
