<?php
/**
 * Detalle de una caja: resumen, movimientos de efectivo (entradas y salidas),
 * y — mientras está abierta — el cierre con arqueo físico.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/caja.php';

verificar_csrf();

$id = (int) ($_GET['id'] ?? 0);
$caja = $id > 0 ? caja_con_resumen($id) : null;
if (!$caja) {
    flash('error', 'No se encontró esa caja.');
    header('Location: caja.php');
    exit;
}
$abierta = $caja['estado'] === 'ABIERTA';
$volver = 'Location: caja-detalle.php?id=' . $id;

/* ---------- Acciones ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!$abierta) {
        flash('error', 'Esta caja ya está cerrada: no se puede modificar.');
        header($volver);
        exit;
    }

    if ($accion === 'agregar_movimiento') {
        $tipo = $_POST['tipo'] ?? '';
        $monto = limpiar_precio($_POST['monto'] ?? 0);
        $concepto = limpiar_texto($_POST['concepto'] ?? '', 255);
        if (!isset(CAJA_TIPOS_MOV[$tipo]) || $monto <= 0 || $concepto === '') {
            flash('error', 'Indica si es entrada o salida, un monto mayor que cero y el concepto.');
        } else {
            db()->prepare('INSERT INTO caja_movimientos (caja_id, tipo, monto, concepto, creado_en) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $tipo, $monto, $concepto, date('Y-m-d H:i:s')]);
            flash('ok', CAJA_TIPOS_MOV[$tipo] . ' de ' . lempiras($monto) . ' registrada.');
        }
        header($volver);
        exit;
    }

    if ($accion === 'eliminar_movimiento') {
        db()->prepare('DELETE FROM caja_movimientos WHERE id = ? AND caja_id = ?')->execute([(int) ($_POST['mov_id'] ?? 0), $id]);
        flash('ok', 'Movimiento eliminado.');
        header($volver);
        exit;
    }

    if ($accion === 'cerrar') {
        $cantidades = caja_leer_cantidades($_POST);
        $contado = caja_total_cantidades($cantidades);
        $observacion = limpiar_texto($_POST['observacion'] ?? '', 500);
        $diferencia = round($contado - $caja['esperado'], 2);

        if (abs($diferencia) >= 0.005 && $observacion === '') {
            flash('error', 'El conteo no cuadra con el saldo esperado (diferencia de ' . lempiras(abs($diferencia))
                . '). Revisa el conteo o escribe una observación que explique la diferencia para poder cerrar.');
            header($volver);
            exit;
        }

        try {
            db()->beginTransaction();
            caja_guardar_desglose($id, 'CIERRE', $cantidades);
            db()->prepare(
                "UPDATE caja_diaria SET saldo_cierre = ?, diferencia = ?, observacion = ?, estado = 'CERRADA', cerrada_en = ?
                  WHERE id = ? AND estado = 'ABIERTA'"
            )->execute([$contado, $diferencia, $observacion ?: null, date('Y-m-d H:i:s'), $id]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('[lirios] No se pudo cerrar la caja: ' . $e->getMessage());
            flash('error', 'No se pudo cerrar la caja. Intenta de nuevo.');
            header($volver);
            exit;
        }
        flash('ok', abs($diferencia) < 0.005 ? 'Caja cerrada: el conteo cuadra.' : 'Caja cerrada con una diferencia de ' . lempiras($diferencia) . '.');
        header($volver);
        exit;
    }

    header($volver);
    exit;
}

/* ---------- Datos ---------- */

$movs = db()->prepare('SELECT * FROM caja_movimientos WHERE caja_id = ? ORDER BY creado_en DESC, id DESC');
$movs->execute([$id]);
$movimientos = $movs->fetchAll();

$desgloseApertura = caja_desglose($id, 'APERTURA');
$desgloseCierre = $abierta ? [] : caja_desglose($id, 'CIERRE');
$efectivoPedidos = caja_pedidos_efectivo($caja['fecha']);
$denominaciones = $abierta ? caja_denominaciones() : [];
$dif = $abierta ? null : (float) $caja['diferencia'];

admin_header('Control de caja', 'caja.php');
?>

<div class="caja-cabecera">
  <a class="caja-volver" href="caja.php">← Control de caja</a>
  <div class="caja-titulo">
    <div class="caja-titulo-texto">
      <h2><?= e($caja['sucursal_nombre']) ?> <span class="caja-hero-sep">·</span> <?= e($caja['caja_nombre']) ?></h2>
      <p class="muted"><?= e(caja_fecha_larga($caja['fecha'])) ?></p>
    </div>
    <span class="caja-estado <?= $abierta ? 'abierta' : 'cerrada' ?>"><i></i> <?= $abierta ? 'Abierta' : 'Cerrada' ?></span>
  </div>
  <div class="caja-acciones">
    <button type="button" class="btn secundario caja-btn" onclick="window.print()">Imprimir</button>
    <?php if ($abierta): ?><button type="button" class="btn caja-btn" data-abrir-modal="modal-cerrar-caja">Cerrar caja</button><?php endif; ?>
  </div>
</div>

<section class="caja-cifras caja-cifras-grande" aria-label="Resumen">
  <div><span>Apertura</span><strong><?= e(lempiras((float) $caja['saldo_apertura'])) ?></strong></div>
  <div class="mas"><span>Entradas</span><strong>+ <?= e(lempiras((float) $caja['ingresos'])) ?></strong></div>
  <div class="menos"><span>Salidas</span><strong>− <?= e(lempiras((float) $caja['egresos'])) ?></strong></div>
  <div class="total"><span>Saldo esperado</span><strong><?= e(lempiras($caja['esperado'])) ?></strong></div>
  <?php if (!$abierta): ?>
    <div><span>Conteo al cierre</span><strong><?= e(lempiras((float) $caja['saldo_cierre'])) ?></strong></div>
    <div class="<?= abs($dif) < 0.005 ? 'cuadra' : ($dif > 0 ? 'sobra' : 'falta') ?>">
      <span>Diferencia</span>
      <strong><?= abs($dif) < 0.005 ? 'Cuadra' : ($dif > 0 ? 'Sobra ' : 'Falta ') . e(lempiras(abs($dif))) ?></strong>
    </div>
  <?php endif; ?>
</section>

<?php if (!$abierta && $caja['observacion']): ?>
  <p class="caja-obs"><strong>Observación al cierre:</strong> <?= e($caja['observacion']) ?></p>
<?php endif; ?>

<div class="caja-grid">
  <section class="panel caja-movs">
    <h2>Movimientos <small class="muted">(<?= count($movimientos) ?>)</small></h2>
    <?php if (!$movimientos): ?>
      <p class="muted">Todavía no hay entradas ni salidas de efectivo.</p>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tabla caja-tabla">
          <thead><tr><th>Hora</th><th>Concepto</th><th class="num">Monto</th><?php if ($abierta): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($movimientos as $m): $entrada = $m['tipo'] === 'ENTRADA'; ?>
              <tr>
                <td data-label="Hora"><?= e(date('g:i a', strtotime($m['creado_en']))) ?></td>
                <td data-label="Concepto"><span class="caja-tipo <?= $entrada ? 'entrada' : 'salida' ?>"><?= $entrada ? 'Entrada' : 'Salida' ?></span> <?= e($m['concepto']) ?></td>
                <td class="num <?= $entrada ? 'mas' : 'menos' ?>" data-label="Monto"><?= $entrada ? '+ ' : '− ' ?><?= e(lempiras((float) $m['monto'])) ?></td>
                <?php if ($abierta): ?>
                  <td><button type="button" class="btn mini peligro" data-confirmar-eliminar="del-mov-<?= (int) $m['id'] ?>"
                              data-confirmar-mensaje="¿Eliminar este movimiento de <?= e(lempiras((float) $m['monto'])) ?>?">Eliminar</button></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <aside class="caja-lateral">
    <?php if ($abierta): ?>
      <section class="panel">
        <h2>Registrar movimiento</h2>
        <form method="post" action="caja-detalle.php?id=<?= $id ?>" class="caja-form-mov">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="agregar_movimiento">
          <div class="caja-tipo-toggle" role="radiogroup" aria-label="Tipo de movimiento">
            <label><input type="radio" name="tipo" value="ENTRADA" checked><span>Entrada</span></label>
            <label><input type="radio" name="tipo" value="SALIDA"><span>Salida</span></label>
          </div>
          <label for="mov-monto">Monto (L.)</label>
          <input type="number" id="mov-monto" name="monto" min="0.01" step="0.01" inputmode="decimal" required placeholder="0.00">
          <label for="mov-concepto">Concepto</label>
          <input type="text" id="mov-concepto" name="concepto" maxlength="255" required placeholder="Ej. Pago de proveedor de flores">
          <button type="submit" class="btn">Guardar movimiento</button>
        </form>
        <?php if ((int) $efectivoPedidos['n'] > 0): ?>
          <p class="caja-ref">Referencia: hay <strong><?= (int) $efectivoPedidos['n'] ?></strong> pedido(s) para entregar el <?= e(date('d/m', strtotime($caja['fecha']))) ?> con pago en efectivo, por <strong><?= e(lempiras((float) $efectivoPedidos['total'])) ?></strong>. Regístralos como entrada cuando cobres.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="panel">
      <h2>Efectivo al abrir</h2>
      <?php if (!$desgloseApertura): ?>
        <p class="muted">La caja se abrió sin desglose.</p>
      <?php else: ?>
        <ul class="caja-lista-desglose">
          <?php foreach ($desgloseApertura as $d): ?>
            <li><span><?= (int) $d['cantidad'] ?> × <?= e(($d['tipo'] === 'BILLETE' ? 'Billete' : 'Moneda') . ' L. ' . rtrim(rtrim(number_format((float) $d['valor'], 2), '0'), '.')) ?></span><b><?= e(lempiras((float) $d['subtotal'])) ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (!$abierta): ?>
      <section class="panel">
        <h2>Conteo al cerrar</h2>
        <?php if (!$desgloseCierre): ?>
          <p class="muted">Se cerró sin desglose.</p>
        <?php else: ?>
          <ul class="caja-lista-desglose">
            <?php foreach ($desgloseCierre as $d): ?>
              <li><span><?= (int) $d['cantidad'] ?> × <?= e(($d['tipo'] === 'BILLETE' ? 'Billete' : 'Moneda') . ' L. ' . rtrim(rtrim(number_format((float) $d['valor'], 2), '0'), '.')) ?></span><b><?= e(lempiras((float) $d['subtotal'])) ?></b></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </aside>
</div>

<?php if ($abierta): ?>
  <?php foreach ($movimientos as $m): ?>
    <form id="del-mov-<?= (int) $m['id'] ?>" method="post" action="caja-detalle.php?id=<?= $id ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar_movimiento">
      <input type="hidden" name="mov_id" value="<?= (int) $m['id'] ?>">
    </form>
  <?php endforeach; ?>

  <dialog class="modal-portada modal-caja modal-cierre" id="modal-cerrar-caja" aria-labelledby="cerrar-titulo" data-esperado="<?= e((string) round($caja['esperado'], 2)) ?>">
    <form method="post" action="caja-detalle.php?id=<?= $id ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="cerrar">
      <h3 id="cerrar-titulo">Arqueo y cierre de caja</h3>
      <p class="modal-ayuda">Cuenta el efectivo que hay ahora en la caja. El sistema lo compara con el saldo esperado.</p>

      <div class="caja-cierre-resumen">
        <div><span>Saldo esperado</span><strong><?= e(lempiras($caja['esperado'])) ?></strong></div>
        <div><span>Conteo</span><strong data-desglose-total-copia>L. 0.00</strong></div>
        <div data-diferencia-caja class="cuadra"><span>Diferencia</span><strong data-diferencia-texto>Falta <?= e(lempiras($caja['esperado'])) ?></strong></div>
      </div>

      <?php caja_campos_desglose($denominaciones, 'ci'); ?>

      <label for="cierre-obs">Observación <small data-obs-ayuda class="muted">(obligatoria si hay diferencia)</small></label>
      <textarea id="cierre-obs" name="observacion" rows="2" maxlength="500" placeholder="Ej. Se entregó cambio de L. 20 sin registrar"></textarea>

      <div class="modal-portada-acciones">
        <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
        <button type="submit" class="btn mini">Cerrar caja</button>
      </div>
    </form>
  </dialog>
<?php endif; ?>

<script src="../js/caja.js?v=1"></script>
<?php admin_footer(); ?>
