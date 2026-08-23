<?php
/**
 * POST /api/pedidos.php
 * Único endpoint de escritura dentro de /api: registra el pedido que el
 * cliente envía por WhatsApp desde carrito.html, para alimentar la
 * reportería de ventas del panel admin. No requiere sesión (lo llama
 * cualquier visitante, como un formulario de contacto), pero valida y
 * sanea todo antes de tocar la base de datos, y jamás confía en el total
 * que manda el navegador: siempre se recalcula aquí a partir de los items.
 *
 * Body esperado (JSON):
 * {
 *   "cliente": "María López",           // opcional
 *   "telefono": "9988-7766",             // opcional
 *   "fechaEntrega": "2026-08-25",         // opcional, YYYY-MM-DD (input type=date)
 *   "horaEntrega": "14:30",                // opcional, HH:MM (input type=time)
 *   "tipoEntrega": "Delivery a domicilio",  // opcional, texto libre
 *   "direccion": "Barrio El Centro...",      // opcional
 *   "dedicatoria": "Feliz cumpleaños...",     // opcional
 *   "pago": "Efectivo",                        // opcional
 *   "nota": "Entregar el viernes",               // opcional
 *   "items": [
 *     { "id": "ramo-dulce-amor"|null, "codigo": "RM-4K2P9"|null,
 *       "tipo": "producto"|"personalizado",
 *       "nombre": "...", "precio": 950, "cantidad": 1, "detalle": "..."|null }
 *   ]
 * }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

function limpiar(?string $texto, int $max): ?string
{
    if ($texto === null) return null;
    $texto = trim(strip_tags($texto));
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = mb_substr($texto, 0, $max, 'UTF-8');
    return $texto === '' ? null : $texto;
}

/**
 * Avisa por correo al negocio de un pedido nuevo, además de WhatsApp.
 * Nunca lanza error ni bloquea la respuesta: si el hosting no tiene
 * correo saliente configurado, el pedido igual quedó guardado en BD.
 */
function notificar_pedido_por_correo(int $pedidoId, ?string $cliente, ?string $nota, array $items, float $total, array $entrega): void
{
    try {
        $lineas = array_map(
            fn($i) => "{$i['cantidad']}x {$i['nombre']}" . ($i['detalle'] ? " ({$i['detalle']})" : '') . " - L. " . number_format($i['precio'], 2),
            $items
        );

        $cuerpo = "Nuevo pedido #{$pedidoId} desde el sitio web\n\n"
            . implode("\n", $lineas)
            . "\n\nTotal: L. " . number_format($total, 2)
            . "\nCliente: " . ($cliente ?: '(sin nombre)')
            . "\nTeléfono: " . ($entrega['telefono'] ?: '(sin especificar)')
            . "\nEntrega: " . ($entrega['fecha_entrega'] ?: '(sin especificar)') . " / Hora: " . ($entrega['hora_entrega'] ?: '(sin especificar)')
            . "\nDelivery o retiro: " . ($entrega['tipo_entrega'] ?: '(sin especificar)')
            . "\nDirección: " . ($entrega['direccion'] ?: '(sin especificar)')
            . "\nDedicatoria: " . ($entrega['dedicatoria'] ?: '(sin especificar)')
            . "\nPago: " . ($entrega['forma_pago'] ?: '(sin especificar)')
            . "\nNota: " . ($nota ?: '(sin nota)')
            . "\n\nEste pedido también se envió por WhatsApp; revisa el panel /admin/pedidos.php para más detalle.";

        $asunto = "Nuevo pedido #{$pedidoId} - LIRIOS Floristería";
        $cabeceras = "Content-Type: text/plain; charset=UTF-8\r\nFrom: LIRIOS Floristería <no-responder@liriosfloristeria.com>";

        @mail(NOTIFICACION_EMAIL, $asunto, $cuerpo, $cabeceras);
    } catch (Throwable $e) {
        // Silencioso a propósito: perder la notificación por correo no debe
        // afectar el registro del pedido, que ya se guardó en la BD.
    }
}

$crudo = file_get_contents('php://input');
$datos = json_decode($crudo, true);

if (!is_array($datos) || empty($datos['items']) || !is_array($datos['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'El pedido debe incluir al menos un producto.']);
    exit;
}

$items = array_slice($datos['items'], 0, 40); // límite razonable por pedido
$cliente = limpiar((string) ($datos['cliente'] ?? ''), 150);
$telefono = limpiar((string) ($datos['telefono'] ?? ''), 30);
$tipoEntrega = limpiar((string) ($datos['tipoEntrega'] ?? ''), 40);
$direccion = limpiar((string) ($datos['direccion'] ?? ''), 300);
$dedicatoria = limpiar((string) ($datos['dedicatoria'] ?? ''), 300);
$pago = limpiar((string) ($datos['pago'] ?? ''), 100);
$nota = limpiar((string) ($datos['nota'] ?? ''), 500);

$fechaEntregaCruda = (string) ($datos['fechaEntrega'] ?? '');
$fechaEntrega = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntregaCruda) ? $fechaEntregaCruda : null;

$horaEntregaCruda = (string) ($datos['horaEntrega'] ?? '');
$horaEntrega = (bool) preg_match('/^\d{2}:\d{2}$/', $horaEntregaCruda) ? $horaEntregaCruda : null;

$itemsValidados = [];
foreach ($items as $item) {
    if (!is_array($item)) continue;

    $tipo = ($item['tipo'] ?? '') === 'personalizado' ? 'personalizado' : 'producto';
    $nombre = limpiar((string) ($item['nombre'] ?? ''), 200);
    $detalle = limpiar(isset($item['detalle']) ? (string) $item['detalle'] : null, 500);
    $precio = round(max(0, min(100000, (float) ($item['precio'] ?? 0))), 2);
    $cantidad = max(1, min(99, (int) ($item['cantidad'] ?? 1)));
    $slug = is_string($item['id'] ?? null) ? trim($item['id']) : null;
    $codigoCrudo = is_string($item['codigo'] ?? null) ? trim($item['codigo']) : null;
    $codigo = ($codigoCrudo && preg_match('/^[A-Z0-9-]{1,20}$/', $codigoCrudo)) ? $codigoCrudo : null;

    if ($nombre === null || $precio <= 0) continue;

    $itemsValidados[] = [
        'tipo' => $tipo,
        'slug' => $slug,
        'codigo' => $codigo,
        'nombre' => $nombre,
        'detalle' => $detalle,
        'precio' => $precio,
        'cantidad' => $cantidad,
        'subtotal' => round($precio * $cantidad, 2),
    ];
}

if (!$itemsValidados) {
    http_response_code(400);
    echo json_encode(['error' => 'Ningún producto del pedido es válido.']);
    exit;
}

$total = round(array_sum(array_column($itemsValidados, 'subtotal')), 2);

/* Límite de frecuencia: máx. 5 pedidos por IP cada 10 minutos, para que
   el endpoint público no se pueda inundar de pedidos falsos. */
$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
if ($ip) {
    $limite = db()->prepare(
        'SELECT COUNT(*) FROM pedidos WHERE ip = :ip AND creado_en >= (NOW() - INTERVAL 10 MINUTE)'
    );
    $limite->execute(['ip' => $ip]);
    if ((int) $limite->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Demasiados pedidos en poco tiempo. Espera unos minutos e intenta de nuevo.']);
        exit;
    }
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare(
        'INSERT INTO pedidos (cliente_nombre, telefono, fecha_entrega, hora_entrega, tipo_entrega, direccion, dedicatoria, forma_pago, nota, ip, total)
         VALUES (:cliente, :telefono, :fecha_entrega, :hora_entrega, :tipo_entrega, :direccion, :dedicatoria, :forma_pago, :nota, :ip, :total)'
    );
    $stmt->execute([
        'cliente' => $cliente,
        'telefono' => $telefono,
        'fecha_entrega' => $fechaEntrega,
        'hora_entrega' => $horaEntrega,
        'tipo_entrega' => $tipoEntrega,
        'direccion' => $direccion,
        'dedicatoria' => $dedicatoria,
        'forma_pago' => $pago,
        'nota' => $nota,
        'ip' => $ip,
        'total' => $total,
    ]);
    $pedidoId = (int) db()->lastInsertId();

    $buscarProducto = db()->prepare('SELECT id FROM productos WHERE slug = ? LIMIT 1');
    $insertarItem = db()->prepare(
        'INSERT INTO pedido_items (pedido_id, tipo, producto_id, codigo, nombre, detalle, precio_unitario, cantidad, subtotal)
         VALUES (:pedido_id, :tipo, :producto_id, :codigo, :nombre, :detalle, :precio, :cantidad, :subtotal)'
    );

    foreach ($itemsValidados as $item) {
        $productoId = null;
        if ($item['tipo'] === 'producto' && $item['slug']) {
            $buscarProducto->execute([$item['slug']]);
            $fila = $buscarProducto->fetch();
            $productoId = $fila ? (int) $fila['id'] : null;
        }

        $insertarItem->execute([
            'pedido_id' => $pedidoId,
            'tipo' => $item['tipo'],
            'producto_id' => $productoId,
            'codigo' => $item['codigo'],
            'nombre' => $item['nombre'],
            'detalle' => $item['detalle'],
            'precio' => $item['precio'],
            'cantidad' => $item['cantidad'],
            'subtotal' => $item['subtotal'],
        ]);
    }

    db()->commit();

    notificar_pedido_por_correo($pedidoId, $cliente, $nota, $itemsValidados, $total, [
        'telefono' => $telefono,
        'fecha_entrega' => $fechaEntrega,
        'hora_entrega' => $horaEntrega,
        'tipo_entrega' => $tipoEntrega,
        'direccion' => $direccion,
        'dedicatoria' => $dedicatoria,
        'forma_pago' => $pago,
    ]);

    http_response_code(201);
    echo json_encode(['ok' => true, 'id' => $pedidoId]);
} catch (Throwable $e) {
    db()->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo registrar el pedido.']);
}
