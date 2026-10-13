<?php
/**
 * Sucursales y cajas del Control de caja: crear, renombrar y activar/desactivar.
 * Nunca se borran: el historial de cajas diarias conserva su caja y su sucursal.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/caja.php';

verificar_csrf();

/** ¿La caja tiene una apertura sin cerrar? (no se puede desactivar así). */
function caja_tiene_abierta(int $cajaId): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM caja_diaria WHERE caja_id = ? AND estado = 'ABIERTA'");
    $stmt->execute([$cajaId]);
    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);

    try {
        switch ($accion) {
            case 'agregar_sucursal':
                if ($nombre === '') {
                    flash('error', 'Escribe el nombre de la sucursal.');
                    break;
                }
                db()->prepare('INSERT INTO caja_sucursales (nombre) VALUES (?)')->execute([$nombre]);
                flash('ok', 'Sucursal «' . $nombre . '» creada. Ahora agrégale sus cajas.');
                break;

            case 'renombrar_sucursal':
                if ($nombre === '') {
                    flash('error', 'El nombre no puede quedar vacío.');
                    break;
                }
                db()->prepare('UPDATE caja_sucursales SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
                flash('ok', 'Sucursal actualizada.');
                break;

            case 'alternar_sucursal':
                $abierta = db()->prepare("SELECT COUNT(*) FROM caja_diaria d JOIN caja_cajas c ON c.id = d.caja_id WHERE c.sucursal_id = ? AND d.estado = 'ABIERTA'");
                $abierta->execute([$id]);
                $activa = db()->prepare('SELECT activa FROM caja_sucursales WHERE id = ?');
                $activa->execute([$id]);
                if ((int) $activa->fetchColumn() === 1 && (int) $abierta->fetchColumn() > 0) {
                    flash('error', 'Esa sucursal tiene una caja abierta. Ciérrala antes de desactivar la sucursal.');
                    break;
                }
                db()->prepare('UPDATE caja_sucursales SET activa = 1 - activa WHERE id = ?')->execute([$id]);
                flash('ok', 'Sucursal actualizada.');
                break;

            case 'agregar_caja':
                $sucursalId = (int) ($_POST['sucursal_id'] ?? 0);
                $existe = db()->prepare('SELECT COUNT(*) FROM caja_sucursales WHERE id = ?');
                $existe->execute([$sucursalId]);
                if ($nombre === '' || (int) $existe->fetchColumn() === 0) {
                    flash('error', 'Escribe el nombre de la caja.');
                    break;
                }
                db()->prepare('INSERT INTO caja_cajas (sucursal_id, nombre) VALUES (?, ?)')->execute([$sucursalId, $nombre]);
                flash('ok', 'Caja «' . $nombre . '» creada. Ya puedes abrirla desde Control de caja.');
                break;

            case 'renombrar_caja':
                if ($nombre === '') {
                    flash('error', 'El nombre no puede quedar vacío.');
                    break;
                }
                db()->prepare('UPDATE caja_cajas SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
                flash('ok', 'Caja actualizada.');
                break;

            case 'alternar_caja':
                $activa = db()->prepare('SELECT activa FROM caja_cajas WHERE id = ?');
                $activa->execute([$id]);
                if ((int) $activa->fetchColumn() === 1 && caja_tiene_abierta($id)) {
                    flash('error', 'Esa caja está abierta. Ciérrala antes de desactivarla.');
                    break;
                }
                db()->prepare('UPDATE caja_cajas SET activa = 1 - activa WHERE id = ?')->execute([$id]);
                flash('ok', 'Caja actualizada.');
                break;
        }
    } catch (PDOException $e) {
        // Nombre repetido (índices únicos): sucursal repetida o caja repetida dentro de la misma sucursal
        flash('error', 'Ya existe una con ese nombre' . ($accion === 'agregar_caja' || $accion === 'renombrar_caja' ? ' en esa sucursal.' : '.'));
    }
    header('Location: caja-cajas.php');
    exit;
}

$sucursales = caja_sucursales(false);
$cajasPorSucursal = [];
$totalCajasActivas = 0;
foreach (caja_catalogo(false) as $c) {
    $cajasPorSucursal[$c['sucursal_id']][] = $c;
    if ($c['activa'] && $c['sucursal_activa']) {
        $totalCajasActivas++;
    }
}

// Estado de cada caja hoy: abierta ahora (de cualquier día), cerrada hoy, o sin abrir.
$estadoHoy = [];
$stmt = db()->prepare("SELECT id, caja_id, estado, fecha FROM caja_diaria WHERE estado = 'ABIERTA' OR fecha = ? ORDER BY estado = 'ABIERTA' ASC");
$stmt->execute([caja_hoy()]);
foreach ($stmt->fetchAll() as $r) {
    $estadoHoy[$r['caja_id']] = $r; // la ABIERTA queda al final y pisa a la cerrada de hoy
}
$abiertasAhora = count(array_filter($estadoHoy, fn($r) => $r['estado'] === 'ABIERTA'));

/** Ícono de línea (SVG) para esta pantalla. */
function cs_ico(string $n): string
{
    $d = [
        'tienda' => '<path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M5.5 12.5V20h13v-7.5"/><path d="M10 20v-4.5h4V20"/>',
        'caja' => '<rect x="3" y="6.5" width="18" height="11" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6.5 10v.01M17.5 14v.01"/>',
        'mas' => '<path d="M12 5v14M5 12h14"/>',
        'lapiz' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    ][$n] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

admin_header('Cajas y sucursales', 'caja.php');
?>

<div class="cs-cabecera">
  <div>
    <a class="caja-volver" href="caja.php">← Control de caja</a>
    <p class="cs-ayuda">Organiza tus sucursales y las cajas de cada una. Al abrir caja eliges cuál abres ese día.</p>
  </div>
  <button type="button" class="btn caja-btn" data-abrir-modal="modal-sucursal-nueva"><?= cs_ico('mas') ?> Nueva sucursal</button>
</div>

<?php if ($sucursales): ?>
  <ul class="cs-resumen" aria-label="Resumen">
    <li><strong><?= count($sucursales) ?></strong> <?= count($sucursales) === 1 ? 'sucursal' : 'sucursales' ?></li>
    <li><strong><?= $totalCajasActivas ?></strong> <?= $totalCajasActivas === 1 ? 'caja activa' : 'cajas activas' ?></li>
    <li class="<?= $abiertasAhora ? 'abierta' : '' ?>"><strong><?= $abiertasAhora ?></strong> <?= $abiertasAhora === 1 ? 'abierta ahora' : 'abiertas ahora' ?></li>
  </ul>
<?php endif; ?>

<?php if (!$sucursales): ?>
  <div class="caja-vacia">
    <p>Todavía no tienes sucursales. Crea la primera y luego agrégale sus cajas.</p>
    <button type="button" class="btn" data-abrir-modal="modal-sucursal-nueva">Crear mi primera sucursal</button>
  </div>
<?php endif; ?>

<?php foreach ($sucursales as $s):
    $cajas = $cajasPorSucursal[$s['id']] ?? [];
    $nActivas = count(array_filter($cajas, fn($c) => $c['activa']));
    ?>
  <section class="cs-sucursal <?= $s['activa'] ? '' : 'inactiva' ?>" aria-label="Sucursal <?= e($s['nombre']) ?>">
    <header class="cs-sucursal-cab">
      <span class="cs-icono"><?= cs_ico('tienda') ?></span>
      <div class="cs-sucursal-titulo">
        <h2><?= e($s['nombre']) ?></h2>
        <p><?= $nActivas ?> <?= $nActivas === 1 ? 'caja activa' : 'cajas activas' ?><?= count($cajas) > $nActivas ? ' · ' . (count($cajas) - $nActivas) . ' desactivada(s)' : '' ?></p>
      </div>
      <?php if (!$s['activa']): ?><span class="caja-estado cerrada"><i></i> Desactivada</span><?php endif; ?>
      <div class="cs-acciones">
        <button type="button" class="cs-link" data-renombrar data-accion="renombrar_sucursal" data-id="<?= (int) $s['id'] ?>" data-nombre="<?= e($s['nombre']) ?>" data-titulo="Renombrar sucursal"><?= cs_ico('lapiz') ?> Renombrar</button>
        <form method="post" action="caja-cajas.php" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="alternar_sucursal">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button type="submit" class="cs-link <?= $s['activa'] ? 'peligro' : '' ?>"><?= $s['activa'] ? 'Desactivar' : 'Activar' ?></button>
        </form>
        <?php if ($s['activa']): ?>
          <button type="button" class="btn mini" data-nueva-caja data-sucursal-id="<?= (int) $s['id'] ?>" data-sucursal-nombre="<?= e($s['nombre']) ?>"><?= cs_ico('mas') ?> Caja</button>
        <?php endif; ?>
      </div>
    </header>

    <?php if (!$cajas): ?>
      <div class="cs-sin-cajas">
        <p>Esta sucursal todavía no tiene cajas.</p>
        <?php if ($s['activa']): ?>
          <button type="button" class="btn mini" data-nueva-caja data-sucursal-id="<?= (int) $s['id'] ?>" data-sucursal-nombre="<?= e($s['nombre']) ?>">Agregar la primera caja</button>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <ul class="cs-cajas">
        <?php foreach ($cajas as $c):
            $hoy = $estadoHoy[$c['id']] ?? null;
            if (!$c['activa']) {
                $clase = 'off'; $texto = 'Desactivada';
            } elseif ($hoy && $hoy['estado'] === 'ABIERTA') {
                $clase = 'abierta'; $texto = $hoy['fecha'] === caja_hoy() ? 'Abierta ahora' : 'Abierta desde el ' . date('d/m', strtotime($hoy['fecha']));
            } elseif ($hoy) {
                $clase = 'cerrada'; $texto = 'Cerrada hoy';
            } else {
                $clase = 'libre'; $texto = 'Sin abrir hoy';
            }
            ?>
          <li class="cs-caja <?= e($clase) ?>">
            <div class="cs-caja-cab">
              <span class="cs-caja-icono"><?= cs_ico('caja') ?></span>
              <strong><?= e($c['nombre']) ?></strong>
            </div>
            <span class="cs-caja-estado"><i></i> <?= e($texto) ?></span>
            <div class="cs-caja-acciones">
              <?php if ($hoy && $c['activa']): ?>
                <a class="cs-link" href="caja-detalle.php?id=<?= (int) $hoy['id'] ?>">Ver</a>
              <?php endif; ?>
              <button type="button" class="cs-link" data-renombrar data-accion="renombrar_caja" data-id="<?= (int) $c['id'] ?>" data-nombre="<?= e($c['nombre']) ?>" data-titulo="Renombrar caja">Renombrar</button>
              <form method="post" action="caja-cajas.php" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="alternar_caja">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="cs-link <?= $c['activa'] ? 'peligro' : '' ?>"><?= $c['activa'] ? 'Desactivar' : 'Activar' ?></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<!-- Diálogos compartidos (uno de cada, se rellenan según el botón que los abre) -->
<dialog class="modal-portada modal-cs" id="modal-sucursal-nueva" aria-labelledby="sn-titulo">
  <form method="post" action="caja-cajas.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="agregar_sucursal">
    <h3 id="sn-titulo">Nueva sucursal</h3>
    <label for="sn-nombre">Nombre de la sucursal</label>
    <input type="text" id="sn-nombre" name="nombre" maxlength="100" required placeholder="Ej. Sucursal Santa Rita">
    <p class="modal-ayuda">Después podrás agregarle las cajas que necesite.</p>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Crear sucursal</button>
    </div>
  </form>
</dialog>

<dialog class="modal-portada modal-cs" id="modal-caja-nueva" aria-labelledby="cn-titulo">
  <form method="post" action="caja-cajas.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="agregar_caja">
    <input type="hidden" name="sucursal_id" id="cn-sucursal-id" value="">
    <h3 id="cn-titulo">Nueva caja</h3>
    <p class="cs-dialogo-sub">en <strong id="cn-sucursal-nombre"></strong></p>
    <label for="cn-nombre">Nombre de la caja</label>
    <input type="text" id="cn-nombre" name="nombre" maxlength="100" required placeholder="Ej. Caja 2">
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Crear caja</button>
    </div>
  </form>
</dialog>

<dialog class="modal-portada modal-cs" id="modal-renombrar" aria-labelledby="rn-titulo">
  <form method="post" action="caja-cajas.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" id="rn-accion" value="">
    <input type="hidden" name="id" id="rn-id" value="">
    <h3 id="rn-titulo">Renombrar</h3>
    <label for="rn-nombre">Nuevo nombre</label>
    <input type="text" id="rn-nombre" name="nombre" maxlength="100" required>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Guardar</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  function abrir(idDialogo, campoFoco) {
    var d = document.getElementById(idDialogo);
    d.showModal();
    var f = document.getElementById(campoFoco);
    if (f) { f.focus(); f.select(); }
  }

  document.querySelectorAll('[data-abrir-modal="modal-sucursal-nueva"]').forEach(function (b) {
    b.addEventListener('click', function () { document.getElementById('sn-nombre').focus(); });
  });

  document.querySelectorAll('[data-nueva-caja]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('cn-sucursal-id').value = b.dataset.sucursalId;
      document.getElementById('cn-sucursal-nombre').textContent = b.dataset.sucursalNombre;
      document.getElementById('cn-nombre').value = '';
      abrir('modal-caja-nueva', 'cn-nombre');
    });
  });

  document.querySelectorAll('[data-renombrar]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('rn-accion').value = b.dataset.accion;
      document.getElementById('rn-id').value = b.dataset.id;
      document.getElementById('rn-titulo').textContent = b.dataset.titulo;
      document.getElementById('rn-nombre').value = b.dataset.nombre;
      abrir('modal-renombrar', 'rn-nombre');
    });
  });
})();
</script>

<?php admin_footer(); ?>
