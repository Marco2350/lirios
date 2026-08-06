<?php
/**
 * Reporte de códigos de ramo: cada item de un pedido (producto o
 * personalizado) lleva un código corto (ej. "RM-4K2P9") generado en el
 * navegador al agregarlo al carrito — ver codigoRamo() en js/cart.js — y
 * que viaja en el mensaje de WhatsApp del cliente. Esta pantalla es el
 * "diccionario" para que Claudia escriba el código que le llegó por
 * WhatsApp y vea exactamente a qué ramo corresponde, sin tener que
 * adivinar a partir del texto del mensaje.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$hayCodigos = (int) db()->query("SELECT COUNT(*) FROM pedido_items WHERE codigo IS NOT NULL")->fetchColumn() > 0;

$busqueda = trim($_GET['q'] ?? '');

$sql = 'SELECT codigo, COUNT(DISTINCT pedido_id) AS veces, SUM(cantidad) AS unidades, SUM(subtotal) AS ingresos, MAX(id) AS ultimo_id
        FROM pedido_items WHERE codigo IS NOT NULL';
$params = [];
if ($busqueda !== '') {
    $sql .= ' AND codigo LIKE :q';
    $params['q'] = '%' . mb_strtoupper($busqueda, 'UTF-8') . '%';
}
$sql .= ' GROUP BY codigo ORDER BY MAX(id) DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$codigos = $stmt->fetchAll();

/* Nombre/detalle/tipo actuales de cada código, tomados del item más reciente */
$detalles = [];
if ($codigos) {
    $ids = array_column($codigos, 'ultimo_id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt2 = db()->prepare("SELECT id, nombre, detalle, tipo FROM pedido_items WHERE id IN ($marcadores)");
    $stmt2->execute($ids);
    foreach ($stmt2->fetchAll() as $d) {
        $detalles[$d['id']] = $d;
    }
}

/* Pedidos donde apareció cada código, para el detalle expandible */
$pedidosPorCodigo = [];
if ($codigos) {
    $codes = array_column($codigos, 'codigo');
    $marcadoresC = implode(',', array_fill(0, count($codes), '?'));
    $stmt3 = db()->prepare(
        "SELECT pi.codigo, p.id AS pedido_id, p.creado_en, p.cliente_nombre, p.estado, pi.cantidad
         FROM pedido_items pi
         JOIN pedidos p ON p.id = pi.pedido_id
         WHERE pi.codigo IN ($marcadoresC)
         ORDER BY p.creado_en DESC"
    );
    $stmt3->execute($codes);
    foreach ($stmt3->fetchAll() as $row) {
        $pedidosPorCodigo[$row['codigo']][] = $row;
    }
}

admin_header('Códigos de ramo', 'codigos.php');
?>

<p class="intro">Cada producto o ramo personalizado que un cliente agrega al carrito lleva un código corto (ej. <code>RM-4K2P9</code>) que se incluye en el mensaje de WhatsApp del pedido. Búscalo aquí para saber exactamente a qué ramo corresponde y en qué pedidos apareció.</p>

<?php if (!$hayCodigos): ?>
  <div class="panel">
    <h2>Todavía no hay códigos registrados</h2>
    <p class="intro" style="margin:0">En cuanto un cliente envíe un pedido por WhatsApp desde el carrito, los códigos de sus ramos aparecerán aquí automáticamente.</p>
  </div>
<?php else: ?>

<div class="panel">
  <form method="get" class="acciones-fila" style="margin-bottom:1rem; gap:0.6rem;">
    <input type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar código, ej: RM-4K2P9" style="flex:1; min-width:220px;">
    <button type="submit" class="btn mini">Buscar</button>
    <?php if ($busqueda !== ''): ?><a class="btn mini secundario" href="codigos.php">Limpiar</a><?php endif; ?>
  </form>

  <?php if (!$codigos): ?>
    <p>Ningún código coincide con «<?= e($busqueda) ?>».</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla">
        <thead>
          <tr><th>Código</th><th>Ramo</th><th>Veces pedido</th><th>Unidades</th><th>Ingresos</th></tr>
        </thead>
        <tbody>
          <?php foreach ($codigos as $c): $d = $detalles[$c['ultimo_id']] ?? null; ?>
            <tr>
              <td><code><?= e($c['codigo']) ?></code></td>
              <td>
                <?= e($d['nombre'] ?? '—') ?>
                <?php if ($d && $d['detalle']): ?><br><small class="muted"><?= e($d['detalle']) ?></small><?php endif; ?>
                <?php if ($d && $d['tipo'] === 'personalizado'): ?><br><small class="muted">Ramo personalizado</small><?php endif; ?>
              </td>
              <td>
                <details>
                  <summary><?= (int) $c['veces'] ?> pedido(s)</summary>
                  <ul style="margin:.4rem 0 0; padding-left:1.1rem;">
                    <?php foreach ($pedidosPorCodigo[$c['codigo']] ?? [] as $p): ?>
                      <li>
                        <?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?> —
                        <?= e($p['cliente_nombre'] ?: 'sin nombre') ?>
                        (<?= (int) $p['cantidad'] ?>×, pedido #<?= (int) $p['pedido_id'] ?>)
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              </td>
              <td><?= (int) $c['unidades'] ?></td>
              <td>L. <?= number_format((float) $c['ingresos'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php admin_footer(); ?>
