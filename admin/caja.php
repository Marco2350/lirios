<?php
/**
 * Control de caja: apertura del día (eligiendo qué caja de qué sucursal) con
 * desglose de billetes y monedas, e historial con filtros. El detalle
 * (movimientos y cierre con arqueo) está en caja-detalle.php; las sucursales
 * y cajas se administran en caja-cajas.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/caja.php';

verificar_csrf();

/* ---------- Abrir una caja (hoy) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'abrir') {
    $hoy = caja_hoy();
    $cajaId = (int) ($_POST['caja_id'] ?? 0);

    $stmt = db()->prepare(
        'SELECT c.*, s.nombre AS sucursal_nombre FROM caja_cajas c
           JOIN caja_sucursales s ON s.id = c.sucursal_id
          WHERE c.id = ? AND c.activa = 1 AND s.activa = 1'
    );
    $stmt->execute([$cajaId]);
    $caja = $stmt->fetch();
    if (!$caja) {
        flash('error', 'Elige la caja que vas a abrir.');
        header('Location: caja.php');
        exit;
    }
    $nombre = $caja['sucursal_nombre'] . ' · ' . $caja['nombre'];

    $stmt = db()->prepare("SELECT COUNT(*) FROM caja_diaria WHERE caja_id = ? AND estado = 'ABIERTA'");
    $stmt->execute([$cajaId]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'La caja «' . $nombre . '» ya está abierta. Ciérrala antes de abrirla de nuevo.');
        header('Location: caja.php');
        exit;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM caja_diaria WHERE caja_id = ? AND fecha = ?');
    $stmt->execute([$cajaId, $hoy]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'La caja «' . $nombre . '» ya se abrió hoy. Solo se permite una apertura por caja al día.');
        header('Location: caja.php');
        exit;
    }

    $cantidades = caja_leer_cantidades($_POST);
    $apertura = caja_total_cantidades($cantidades);

    try {
        db()->beginTransaction();
        db()->prepare("INSERT INTO caja_diaria (caja_id, fecha, saldo_apertura, estado, abierta_en) VALUES (?, ?, ?, 'ABIERTA', ?)")
            ->execute([$cajaId, $hoy, $apertura, date('Y-m-d H:i:s')]);
        $id = (int) db()->lastInsertId();
        caja_guardar_desglose($id, 'APERTURA', $cantidades);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[lirios] No se pudo abrir la caja: ' . $e->getMessage());
        flash('error', 'No se pudo abrir la caja. Intenta de nuevo.');
        header('Location: caja.php');
        exit;
    }

    flash('ok', 'Caja «' . $nombre . '» abierta con ' . lempiras($apertura) . '.');
    header('Location: caja-detalle.php?id=' . $id);
    exit;
}

/* ---------- Datos ---------- */

$desde = caja_fecha_valida($_GET['desde'] ?? null);
$hasta = caja_fecha_valida($_GET['hasta'] ?? null);
$estado = in_array($_GET['estado'] ?? '', ['ABIERTA', 'CERRADA'], true) ? $_GET['estado'] : '';
$sucursalFiltro = (int) ($_GET['sucursal'] ?? 0);
$cajaFiltro = (int) ($_GET['caja'] ?? 0);

$sucursales = caja_sucursales(false);
$catalogo = caja_catalogo(false);
$cajasVisibles = $sucursalFiltro ? array_values(array_filter($catalogo, fn($c) => (int) $c['sucursal_id'] === $sucursalFiltro)) : $catalogo;
if ($cajaFiltro && !in_array($cajaFiltro, array_map('intval', array_column($cajasVisibles, 'id')), true)) {
    $cajaFiltro = 0; // la caja elegida no pertenece a la sucursal elegida
}

$registros = caja_listar($desde, $hasta, $estado, $sucursalFiltro, $cajaFiltro);
$abiertas = array_map(fn($a) => caja_con_resumen((int) $a['id']), cajas_abiertas());
$disponibles = caja_disponibles_para_abrir(caja_hoy());
$denominaciones = caja_denominaciones();
$hayCajas = count(caja_catalogo(true)) > 0;

$filtrosQs = http_build_query(array_filter([
    'desde' => $desde, 'hasta' => $hasta, 'estado' => $estado, 'sucursal' => $sucursalFiltro ?: null, 'caja' => $cajaFiltro ?: null,
]));
$hayFiltros = $desde || $hasta || $estado || $sucursalFiltro || $cajaFiltro;

admin_header('Control de caja', 'caja.php');
?>

<form class="ped-toolbar" method="get" action="caja.php">
  <label class="caja-filtro">Desde <input type="date" name="desde" value="<?= e((string) $desde) ?>" onchange="this.form.submit()"></label>
  <label class="caja-filtro">Hasta <input type="date" name="hasta" value="<?= e((string) $hasta) ?>" onchange="this.form.submit()"></label>
  <select name="sucursal" onchange="this.form.submit()" aria-label="Filtrar por sucursal" class="<?= $sucursalFiltro ? 'activo' : '' ?>">
    <option value="">Sucursal</option>
    <?php foreach ($sucursales as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $sucursalFiltro === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
  </select>
  <select name="caja" onchange="this.form.submit()" aria-label="Filtrar por caja" class="<?= $cajaFiltro ? 'activo' : '' ?>">
    <option value="">Caja</option>
    <?php foreach ($cajasVisibles as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cajaFiltro === (int) $c['id'] ? 'selected' : '' ?>><?= e($sucursalFiltro ? $c['nombre'] : caja_nombre_completo($c)) ?></option><?php endforeach; ?>
  </select>
  <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado" class="<?= $estado ? 'activo' : '' ?>">
    <option value="">Estado</option>
    <option value="ABIERTA" <?= $estado === 'ABIERTA' ? 'selected' : '' ?>>Abierta</option>
    <option value="CERRADA" <?= $estado === 'CERRADA' ? 'selected' : '' ?>>Cerrada</option>
  </select>
  <?php if ($hayFiltros): ?><a class="ped-limpiar" href="caja.php">Quitar filtros</a><?php endif; ?>

  <a class="btn secundario caja-btn caja-btn-der" href="caja-reporte.php<?= $filtrosQs ? '?' . e($filtrosQs) : '' ?>" target="_blank" rel="noopener">Reporte</a>
  <a class="btn secundario caja-btn" href="caja-cajas.php">Cajas y sucursales</a>
  <a class="btn secundario caja-btn" href="caja-denominaciones.php">Denominaciones</a>
  <?php if ($disponibles): ?>
    <button type="button" class="btn caja-btn" data-abrir-modal="modal-abrir-caja">Abrir caja</button>
  <?php endif; ?>
</form>

<?php if (!$hayCajas): ?>
  <div class="caja-vacia">
    <p>Todavía no hay cajas activas. Crea la primera para empezar a registrar el efectivo.</p>
    <a class="btn" href="caja-cajas.php">Crear una caja</a>
  </div>
<?php endif; ?>

<?php foreach ($abiertas as $ab): ?>
  <section class="caja-hero" aria-label="Caja abierta: <?= e(caja_nombre_completo($ab)) ?>">
    <div class="caja-hero-cab">
      <div>
        <span class="caja-estado abierta"><i></i> Caja abierta</span>
        <h2><?= e($ab['sucursal_nombre']) ?> <span class="caja-hero-sep">·</span> <?= e($ab['caja_nombre']) ?></h2>
        <p class="muted"><?= e(caja_fecha_larga($ab['fecha'])) ?> · abierta a las <?= e(date('g:i a', strtotime($ab['abierta_en']))) ?></p>
        <?php if ($ab['fecha'] !== caja_hoy()): ?>
          <p class="caja-alerta">Quedó abierta de un día anterior. Ciérrala para poder abrirla hoy.</p>
        <?php endif; ?>
      </div>
      <a class="btn" href="caja-detalle.php?id=<?= (int) $ab['id'] ?>">Movimientos y cierre</a>
    </div>
    <div class="caja-cifras">
      <div><span>Apertura</span><strong><?= e(lempiras((float) $ab['saldo_apertura'])) ?></strong></div>
      <div class="mas"><span>Entradas</span><strong>+ <?= e(lempiras((float) $ab['ingresos'])) ?></strong></div>
      <div class="menos"><span>Salidas</span><strong>− <?= e(lempiras((float) $ab['egresos'])) ?></strong></div>
      <div class="total"><span>Saldo esperado</span><strong><?= e(lempiras($ab['esperado'])) ?></strong></div>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($hayCajas && !$abiertas): ?>
  <div class="caja-vacia">
    <?php if ($disponibles): ?>
      <p>No hay ninguna caja abierta ahora.</p>
      <button type="button" class="btn" data-abrir-modal="modal-abrir-caja">Abrir una caja</button>
    <?php else: ?>
      <p>Todas las cajas ya se abrieron y cerraron hoy. Mañana podrás abrirlas de nuevo.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="panel caja-historial">
  <h2>Historial</h2>
  <?php if (!$registros): ?>
    <p class="muted">No hay cajas con esos filtros todavía.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla caja-tabla">
        <thead>
          <tr>
            <th>Fecha</th><th>Sucursal / Caja</th><th class="num">Apertura</th><th class="num">Entradas</th><th class="num">Salidas</th>
            <th class="num">Esperado</th><th class="num">Conteo al cierre</th><th class="num">Diferencia</th><th>Estado</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registros as $c):
              $cerrada = $c['estado'] === 'CERRADA';
              $dif = $cerrada ? (float) $c['diferencia'] : null;
              ?>
            <tr>
              <td data-label="Fecha"><strong><?= e(date('d/m/Y', strtotime($c['fecha']))) ?></strong></td>
              <td data-label="Caja"><?= e($c['sucursal_nombre']) ?> <span class="muted">·</span> <?= e($c['caja_nombre']) ?></td>
              <td class="num" data-label="Apertura"><?= e(lempiras((float) $c['saldo_apertura'])) ?></td>
              <td class="num mas" data-label="Entradas"><?= e(lempiras((float) $c['ingresos'])) ?></td>
              <td class="num menos" data-label="Salidas"><?= e(lempiras((float) $c['egresos'])) ?></td>
              <td class="num" data-label="Esperado"><?= e(lempiras($c['esperado'])) ?></td>
              <td class="num" data-label="Conteo al cierre"><?= $cerrada ? e(lempiras((float) $c['saldo_cierre'])) : '—' ?></td>
              <td class="num" data-label="Diferencia">
                <?php if ($dif === null): ?>—
                <?php elseif (abs($dif) < 0.005): ?><span class="caja-dif ok">Cuadra</span>
                <?php else: ?><span class="caja-dif <?= $dif > 0 ? 'sobra' : 'falta' ?>"><?= $dif > 0 ? 'Sobra ' : 'Falta ' ?><?= e(lempiras(abs($dif))) ?></span>
                <?php endif; ?>
              </td>
              <td data-label="Estado"><span class="caja-estado <?= $cerrada ? 'cerrada' : 'abierta' ?>"><i></i> <?= $cerrada ? 'Cerrada' : 'Abierta' ?></span></td>
              <td><a class="btn mini secundario" href="caja-detalle.php?id=<?= (int) $c['id'] ?>">Ver</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($disponibles): ?>
<dialog class="modal-portada modal-caja" id="modal-abrir-caja" aria-labelledby="abrir-titulo">
  <form method="post" action="caja.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="abrir">
    <h3 id="abrir-titulo">Abrir caja — <?= e(caja_fecha_larga(caja_hoy())) ?></h3>
    <label for="abrir-caja-id">¿Qué caja vas a abrir?</label>
    <select id="abrir-caja-id" name="caja_id" required>
      <?php if (count($disponibles) > 1): ?><option value="" disabled selected>Elige una caja…</option><?php endif; ?>
      <?php
        $grupoActual = null;
        foreach ($disponibles as $c):
            if ($grupoActual !== $c['sucursal_nombre']):
                if ($grupoActual !== null) echo '</optgroup>';
                $grupoActual = $c['sucursal_nombre'];
                echo '<optgroup label="' . e($grupoActual) . '">';
            endif; ?>
        <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option>
      <?php endforeach; ?>
      </optgroup>
    </select>
    <p class="caja-nueva-enlace">¿Falta alguna? <a href="caja-cajas.php">Crear una caja o sucursal</a></p>
    <p class="modal-ayuda">Cuenta el efectivo con el que empieza esta caja. Puedes dejarlo en blanco si arranca vacía.</p>
    <?php caja_campos_desglose($denominaciones, 'ap'); ?>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Abrir caja</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script src="../js/caja.js?v=1"></script>
<?php admin_footer(); ?>
