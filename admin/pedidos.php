<?php
/**
 * Lista de pedidos individuales enviados por WhatsApp (tablas pedidos /
 * pedido_items), con estado editable (pendiente/coordinado/entregado).
 * Complementa a reportes.php: ahí se ven los totales, aquí el detalle
 * de cada pedido puntual.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

const ESTADOS = ['pendiente' => 'Pendiente', 'coordinado' => 'Coordinado', 'entregado' => 'Entregado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_estado') {
    $id = (int) ($_POST['id'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    if (isset(ESTADOS[$estado])) {
        db()->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')->execute([$estado, $id]);
        flash('ok', 'Estado actualizado.');
    }
    header('Location: pedidos.php' . (isset($_GET['estado']) ? '?estado=' . urlencode($_GET['estado']) : ''));
    exit;
}

$estadoFiltro = $_GET['estado'] ?? '';
$sql = 'SELECT * FROM pedidos WHERE 1=1';
$params = [];
if (isset(ESTADOS[$estadoFiltro])) {
    $sql .= ' AND estado = :estado';
    $params['estado'] = $estadoFiltro;
}
$sql .= ' ORDER BY creado_en DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$itemsPorPedido = [];
if ($pedidos) {
    $ids = array_column($pedidos, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $itemsStmt = db()->prepare("SELECT * FROM pedido_items WHERE pedido_id IN ($marcadores) ORDER BY id");
    $itemsStmt->execute($ids);
    foreach ($itemsStmt->fetchAll() as $it) {
        $itemsPorPedido[$it['pedido_id']][] = $it;
    }
}

admin_header('Pedidos', 'pedidos.php');
?>

<p class="intro">Cada pedido enviado por WhatsApp desde el carrito queda aquí. Marca el estado a medida que lo coordinas con la clienta — esto es solo para tu organización, no le llega ninguna notificación al cliente.</p>

<div class="panel">
  <div class="acciones-fila" style="margin-bottom:1rem;">
    <a class="btn mini <?= $estadoFiltro === '' ? '' : 'secundario' ?>" href="pedidos.php">Todos</a>
    <?php foreach (ESTADOS as $clave => $etiqueta): ?>
      <a class="btn mini <?= $estadoFiltro === $clave ? '' : 'secundario' ?>" href="pedidos.php?estado=<?= e($clave) ?>"><?= e($etiqueta) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$pedidos): ?>
    <p>No hay pedidos<?= $estadoFiltro ? ' con estado «' . e(ESTADOS[$estadoFiltro]) . '»' : '' ?> todavía.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla">
        <thead>
          <tr><th>Fecha</th><th>Cliente</th><th>Detalle</th><th>Total</th><th>Estado</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pedidos as $p): $fid = 'ped-' . $p['id']; ?>
            <tr>
              <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></td>
              <td>
                <?= e($p['cliente_nombre'] ?: '— sin nombre —') ?>
                <?php if ($p['telefono']): ?><br><small class="muted">📱 <?= e($p['telefono']) ?></small><?php endif; ?>
                <?php if ($p['fecha_entrega'] || $p['hora_entrega']): ?>
                  <br><small class="muted">📅 <?= $p['fecha_entrega'] ? e(date('d/m/Y', strtotime($p['fecha_entrega']))) : '__' ?> · <?= e($p['hora_entrega'] ?: '__') ?></small>
                <?php endif; ?>
                <?php if ($p['tipo_entrega']): ?><br><small class="muted">🚚 <?= e($p['tipo_entrega']) ?></small><?php endif; ?>
                <?php if ($p['direccion']): ?><br><small class="muted">📍 <?= e($p['direccion']) ?></small><?php endif; ?>
                <?php if ($p['dedicatoria']): ?><br><small class="muted">💌 <?= e($p['dedicatoria']) ?></small><?php endif; ?>
                <?php if ($p['forma_pago']): ?><br><small class="muted">💳 <?= e($p['forma_pago']) ?></small><?php endif; ?>
                <?php if ($p['nota']): ?><br><small class="muted">📝 <?= e($p['nota']) ?></small><?php endif; ?>
              </td>
              <td>
                <details>
                  <summary><?= count($itemsPorPedido[$p['id']] ?? []) ?> producto(s)</summary>
                  <ul style="margin:.4rem 0 0; padding-left:1.1rem;">
                    <?php foreach ($itemsPorPedido[$p['id']] ?? [] as $it): ?>
                      <li>
                        <?= (int) $it['cantidad'] ?>× <?= e($it['nombre']) ?> — L. <?= number_format((float) $it['subtotal'], 2) ?>
                        <?php if ($it['codigo']): ?> <code><?= e($it['codigo']) ?></code><?php endif; ?>
                        <?php if ($it['detalle']): ?><br><small class="muted"><?= e($it['detalle']) ?></small><?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              </td>
              <td style="white-space:nowrap">L. <?= number_format((float) $p['total'], 2) ?></td>
              <td>
                <select name="estado" form="<?= e($fid) ?>" onchange="this.form.requestSubmit()">
                  <?php foreach (ESTADOS as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>" <?= $p['estado'] === $clave ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Formularios de cambio de estado (fuera de la tabla, asociados por form="id") -->
  <?php foreach ($pedidos as $p): $fid = 'ped-' . $p['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="pedidos.php<?= $estadoFiltro ? '?estado=' . e($estadoFiltro) : '' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="actualizar_estado">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<?php admin_footer(); ?>
