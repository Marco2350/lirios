<?php
/**
 * GET /api/zonas-delivery.php
 * Zonas de delivery con su polígono y precio, consultadas en vivo desde
 * MySQL. Lo usa js/delivery-map.js (carrito.html) para detectar en qué
 * zona cae el pin que el cliente marca en el mapa y mostrarle el costo.
 * Solo lectura — el precio real que se cobra se vuelve a calcular en el
 * servidor (api/pedidos.php) a partir de esta misma tabla, nunca se
 * confía en lo que mande el navegador.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $filas = db()->query(
        'SELECT id, nombre, precio, poligono FROM zonas_delivery WHERE visible = 1 ORDER BY orden, nombre'
    )->fetchAll();

    $zonas = array_map(function ($z) {
        $poligono = json_decode($z['poligono'], true);
        return [
            'id' => (int) $z['id'],
            'nombre' => $z['nombre'],
            'precio' => (float) $z['precio'],
            'poligono' => is_array($poligono) ? $poligono : [],
        ];
    }, $filas);

    echo json_encode(['zonas' => $zonas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar las zonas de delivery.']);
}
