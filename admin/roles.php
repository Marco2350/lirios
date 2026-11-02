<?php
/**
 * Roles y permisos: cada rol marca, por módulo del panel, si sus usuarios
 * pueden ver / crear / editar / eliminar. El rol «Dueño» (es_dueno = 1) lo
 * puede todo y no es editable. Protegido por el módulo «usuarios».
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

exigir_permiso('usuarios', 'ver');
verificar_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'crear_rol') {
        exigir_permiso('usuarios', 'crear');
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 80);
        $existe = db()->prepare('SELECT COUNT(*) FROM admin_roles WHERE nombre = ?');
        $existe->execute([$nombre]);
        if ($nombre === '') {
            flash('error', 'Escribe el nombre del rol.');
        } elseif ((int) $existe->fetchColumn()) {
            flash('error', 'Ya existe un rol con ese nombre.');
        } else {
            db()->prepare('INSERT INTO admin_roles (nombre, descripcion) VALUES (?, ?)')
                ->execute([$nombre, limpiar_texto($_POST['descripcion'] ?? '', 200) ?: null]);
            $id = (int) db()->lastInsertId();
            flash('ok', "Rol «{$nombre}» creado. Marca ahora sus permisos y guarda.");
        }
        header('Location: roles.php' . ($id ? '?rol=' . $id : ''));
        exit;
    }

    if ($accion === 'guardar_rol') {
        exigir_permiso('usuarios', 'editar');
        $stmt = db()->prepare('SELECT es_dueno FROM admin_roles WHERE id = ?');
        $stmt->execute([$id]);
        $esDueno = $stmt->fetchColumn();
        if ($esDueno === false || (int) $esDueno === 1) {
            flash('error', 'El rol Dueño siempre tiene acceso total y no se puede modificar.');
        } else {
            $nombre = limpiar_texto($_POST['nombre'] ?? '', 80);
            $dup = db()->prepare('SELECT COUNT(*) FROM admin_roles WHERE nombre = ? AND id <> ?');
            $dup->execute([$nombre, $id]);
            if ($nombre === '' || (int) $dup->fetchColumn()) {
                flash('error', 'Revisa el nombre del rol: no puede estar vacío ni repetirse.');
            } else {
                db()->beginTransaction();
                try {
                    db()->prepare('UPDATE admin_roles SET nombre = ?, descripcion = ? WHERE id = ?')
                        ->execute([$nombre, limpiar_texto($_POST['descripcion'] ?? '', 200) ?: null, $id]);
                    db()->prepare('DELETE FROM admin_rol_permisos WHERE rol_id = ?')->execute([$id]);
                    $ins = db()->prepare(
                        'INSERT INTO admin_rol_permisos (rol_id, modulo, puede_ver, puede_crear, puede_editar, puede_eliminar)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    foreach (array_keys(MODULOS) as $modulo) {
                        $m = $_POST['perm'][$modulo] ?? [];
                        $ver = !empty($m['ver']);
                        // Crear/editar/eliminar sin «ver» no tienen sentido: se ignoran.
                        $flags = [$ver, $ver && !empty($m['crear']), $ver && !empty($m['editar']), $ver && !empty($m['eliminar'])];
                        if ($ver) {
                            $ins->execute(array_merge([$id, $modulo], array_map('intval', $flags)));
                        }
                    }
                    db()->commit();
                    flash('ok', 'Rol guardado. Los cambios aplican de inmediato a sus usuarios.');
                } catch (Throwable $e) {
                    db()->rollBack();
                    error_log('[lirios] No se pudo guardar el rol: ' . $e->getMessage());
                    flash('error', 'No se pudo guardar el rol. Intenta de nuevo.');
                }
            }
        }
        header('Location: roles.php?rol=' . $id);
        exit;
    }

    if ($accion === 'eliminar_rol') {
        exigir_permiso('usuarios', 'eliminar');
        $stmt = db()->prepare('SELECT es_dueno FROM admin_roles WHERE id = ?');
        $stmt->execute([$id]);
        $esDueno = $stmt->fetchColumn();
        $enUso = db()->prepare('SELECT COUNT(*) FROM admin_usuarios WHERE rol_id = ?');
        $enUso->execute([$id]);
        if ($esDueno === false) {
            flash('error', 'Ese rol ya no existe.');
        } elseif ((int) $esDueno === 1) {
            flash('error', 'El rol Dueño no se puede eliminar.');
        } elseif ((int) $enUso->fetchColumn()) {
            flash('error', 'Ese rol todavía tiene usuarios. Cámbiales el rol primero.');
        } else {
            db()->prepare('DELETE FROM admin_roles WHERE id = ?')->execute([$id]);
            flash('ok', 'Rol eliminado.');
        }
        header('Location: roles.php');
        exit;
    }

    header('Location: roles.php');
    exit;
}

$roles = db()->query(
    'SELECT r.*, (SELECT COUNT(*) FROM admin_usuarios u WHERE u.rol_id = r.id) AS usuarios
     FROM admin_roles r ORDER BY r.es_dueno DESC, r.nombre'
)->fetchAll();
$permisosPorRol = [];
foreach (db()->query('SELECT * FROM admin_rol_permisos')->fetchAll() as $p) {
    $permisosPorRol[$p['rol_id']][$p['modulo']] = $p;
}

$ICONO_ACCION = ['ver' => 'ver', 'crear' => 'mas', 'editar' => 'editar', 'eliminar' => 'eliminar'];
$ICONO_MODULO = [
    'pedidos' => 'pedidos', 'codigos' => 'codigos', 'caja' => 'caja', 'reportes' => 'reportes', 'productos' => 'productos',
    'categorias' => 'categorias', 'zonas' => 'zonas', 'imagenes' => 'imagenes', 'usuarios' => 'usuarios',
];
$TOTAL_PERMISOS = count(MODULOS) * count(ACCIONES_PERMISO);
$rolSel = (int) ($_GET['rol'] ?? 0);
if (!in_array($rolSel, array_map('intval', array_column($roles, 'id')), true)) {
    $rolSel = (int) $roles[0]['id'];
}
$puedeEditar = puede('usuarios', 'editar');

admin_header('Usuarios y roles', 'usuarios.php');
?>

<nav class="ped-tabs" aria-label="Usuarios y roles">
  <a href="usuarios.php">Usuarios</a>
  <a href="roles.php" class="on">Roles y permisos</a>
</nav>

<div class="ur-roles">

  <aside class="ur-roles-lista" role="tablist" aria-label="Roles">
    <h2>Roles del panel</h2>
    <?php foreach ($roles as $r):
        $esD = (int) $r['es_dueno'] === 1;
        $cuenta = 0;
        foreach ($permisosPorRol[$r['id']] ?? [] as $pp) {
            $cuenta += (int) $pp['puede_ver'] + (int) $pp['puede_crear'] + (int) $pp['puede_editar'] + (int) $pp['puede_eliminar'];
        }
        ?>
      <button type="button" class="ur-rol-item" role="tab" data-rol="<?= (int) $r['id'] ?>" aria-selected="<?= (int) $r['id'] === $rolSel ? 'true' : 'false' ?>">
        <span class="ur-ico ur-tono-<?= $esD ? 'dueno' : (int) $r['id'] % 5 ?>"><?= admin_icono($esD ? 'corona' : 'escudo') ?></span>
        <span class="txt">
          <strong><?= e($r['nombre']) ?></strong>
          <small><?= (int) $r['usuarios'] ?> <?= (int) $r['usuarios'] === 1 ? 'usuario' : 'usuarios' ?></small>
        </span>
        <span class="ur-rol-cuenta" data-cuenta-rol="<?= (int) $r['id'] ?>" title="Permisos concedidos"><?= $esD ? 'Todo' : $cuenta . '/' . $TOTAL_PERMISOS ?></span>
      </button>
    <?php endforeach; ?>
    <?php if (puede('usuarios', 'crear')): ?>
      <button type="button" class="btn mini secundario ur-rol-nuevo" data-abrir-modal="modal-rol-nuevo"><?= admin_icono('mas') ?> Nuevo rol</button>
    <?php endif; ?>
  </aside>

  <div>
    <?php foreach ($roles as $r):
        $esD = (int) $r['es_dueno'] === 1;
        $fid = 'rol-' . (int) $r['id'];
        $iM = 0;
        ?>
      <section class="ur-panel" data-panel="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $rolSel ? '' : 'hidden' ?>>
        <form id="<?= e($fid) ?>" method="post" action="roles.php">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="guardar_rol">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">

          <div class="ur-panel-cab">
            <span class="ur-ico ur-tono-<?= $esD ? 'dueno' : (int) $r['id'] % 5 ?>"><?= admin_icono($esD ? 'corona' : 'escudo') ?></span>
            <div>
              <h2><?= e($r['nombre']) ?></h2>
              <p><?= e($r['descripcion'] ?: ($esD ? 'Acceso total al panel.' : 'Sin descripción')) ?> · <?= (int) $r['usuarios'] ?> <?= (int) $r['usuarios'] === 1 ? 'usuario' : 'usuarios' ?></p>
            </div>
            <?php if (!$esD): ?>
              <div class="ur-panel-acciones">
                <span class="ur-sucio">Cambios sin guardar</span>
                <?php if (puede('usuarios', 'eliminar')): ?>
                  <button type="button" class="btn mini peligro" data-confirmar-eliminar="<?= e($fid) ?>-del"
                          data-confirmar-mensaje="¿Eliminar el rol «<?= e($r['nombre']) ?>»? Solo se puede si ya no tiene usuarios.">Eliminar rol</button>
                <?php endif; ?>
                <?php if ($puedeEditar): ?><button type="submit" class="btn"><?= admin_icono('check') ?> Guardar cambios</button><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($esD): ?>
            <div class="ur-aviso-dueno">
              <?= admin_icono('corona') ?>
              <div><strong>Acceso total.</strong> Este rol puede ver, crear, editar y eliminar en todos los módulos, y administrar usuarios y roles. No se puede restringir.</div>
            </div>
          <?php else: ?>
            <div class="ur-datos-rol">
              <div>
                <label for="<?= e($fid) ?>-n">Nombre del rol</label>
                <input type="text" id="<?= e($fid) ?>-n" name="nombre" value="<?= e($r['nombre']) ?>" maxlength="80" required <?= $puedeEditar ? '' : 'readonly' ?>>
              </div>
              <div>
                <label for="<?= e($fid) ?>-d">Descripción</label>
                <input type="text" id="<?= e($fid) ?>-d" name="descripcion" value="<?= e($r['descripcion']) ?>" maxlength="200" <?= $puedeEditar ? '' : 'readonly' ?>>
              </div>
            </div>
          <?php endif; ?>

          <div class="ur-modulos" style="<?= $esD ? 'margin-top:1rem' : '' ?>">
            <?php foreach (MODULOS as $modulo => $etqMod):
                $perm = $permisosPorRol[$r['id']][$modulo] ?? [];
                $marcados = 0;
                foreach (array_keys(ACCIONES_PERMISO) as $a) {
                    $marcados += ($esD || !empty($perm['puede_' . $a])) ? 1 : 0;
                }
                $bloqueado = $esD || !$puedeEditar;
                ?>
              <div class="ur-modulo <?= $marcados ? 'on' : '' ?>" data-modulo>
                <div class="ur-modulo-cab">
                  <span class="ur-ico ur-tono-<?= $iM++ % 5 ?>"><?= admin_icono($ICONO_MODULO[$modulo] ?? 'escudo') ?></span>
                  <strong><?= e($etqMod) ?><small><span data-cuenta><?= $marcados ?></span>/<?= count(ACCIONES_PERMISO) ?> permisos</small></strong>
                  <label class="ur-switch" title="Marcar o quitar todos">
                    <input type="checkbox" data-master aria-label="Todos los permisos de <?= e($etqMod) ?>" <?= $marcados === count(ACCIONES_PERMISO) ? 'checked' : '' ?> <?= $bloqueado ? 'disabled' : '' ?>><span></span>
                  </label>
                </div>
                <div class="ur-modulo-cuerpo">
                  <?php foreach (ACCIONES_PERMISO as $a => $etqA): ?>
                    <label class="ur-permiso <?= $a === 'eliminar' ? 'eliminar' : '' ?>">
                      <span class="lbl"><?= admin_icono($ICONO_ACCION[$a]) ?><?= e($etqA) ?></span>
                      <span class="ur-switch">
                        <input type="checkbox" data-perm="<?= e($a) ?>" <?= $bloqueado ? '' : 'name="perm[' . e($modulo) . '][' . e($a) . ']" value="1"' ?>
                               <?= ($esD || !empty($perm['puede_' . $a])) ? 'checked' : '' ?> <?= $bloqueado ? 'disabled' : '' ?>><span></span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </form>
        <?php if (!$esD): ?>
          <form id="<?= e($fid) ?>-del" method="post" action="roles.php">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar_rol">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          </form>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<?php if (puede('usuarios', 'crear')): ?>
<dialog class="modal-portada" id="modal-rol-nuevo" style="width:min(460px,92vw)">
  <form method="post" action="roles.php" data-enter-siguiente>
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear_rol">
    <h3>Nuevo rol</h3>
    <p class="modal-ayuda">Se crea sin permisos. Después marca en la lista qué módulos puede ver y qué puede hacer.</p>
    <label for="nr-nombre">Nombre del rol *</label>
    <input type="text" id="nr-nombre" name="nombre" required maxlength="80" placeholder="Ej. Caja">
    <label for="nr-desc">Descripción</label>
    <input type="text" id="nr-desc" name="descripcion" maxlength="200" placeholder="Qué hace este rol">
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Crear rol</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script>
(function () {
  var TOTAL = <?= (int) $TOTAL_PERMISOS ?>;
  var items = document.querySelectorAll('.ur-rol-item');
  var paneles = document.querySelectorAll('.ur-panel');
  var sinGuardar = false;

  // ---- Elegir rol (sin recargar) ----
  function elegir(id) {
    items.forEach(function (b) { b.setAttribute('aria-selected', b.dataset.rol === id ? 'true' : 'false'); });
    paneles.forEach(function (p) { p.hidden = p.dataset.panel !== id; });
    try { history.replaceState(null, '', 'roles.php?rol=' + id); } catch (e) { /* sin historial: no pasa nada */ }
  }
  items.forEach(function (b) { b.addEventListener('click', function () { elegir(b.dataset.rol); }); });

  // ---- Interruptores: «Ver» es la base; el interruptor general marca/quita todo el módulo ----
  function refrescarModulo(mod) {
    var permisos = mod.querySelectorAll('[data-perm]');
    var n = 0;
    permisos.forEach(function (c) { if (c.checked) n++; });
    mod.querySelector('[data-cuenta]').textContent = n;
    mod.querySelector('[data-master]').checked = n === permisos.length;
    mod.classList.toggle('on', n > 0);
  }
  function refrescarRol(panel) {
    var total = panel.querySelectorAll('[data-perm]:checked').length;
    var badge = document.querySelector('[data-cuenta-rol="' + panel.dataset.panel + '"]');
    if (badge && panel.querySelector('[data-master]:not([disabled])')) badge.textContent = total + '/' + TOTAL;
  }
  paneles.forEach(function (panel) {
    panel.addEventListener('change', function (e) {
      var c = e.target, mod = c.closest('[data-modulo]');
      if (!mod || c.disabled) return;
      if (c.hasAttribute('data-master')) {
        mod.querySelectorAll('[data-perm]').forEach(function (p) { p.checked = c.checked; });
      } else if (c.dataset.perm === 'ver' && !c.checked) {
        mod.querySelectorAll('[data-perm]').forEach(function (p) { p.checked = false; });
      } else if (c.checked) {
        mod.querySelector('[data-perm="ver"]').checked = true;
      }
      refrescarModulo(mod);
      refrescarRol(panel);
      panel.classList.add('sucio');
      sinGuardar = true;
    });
    panel.addEventListener('input', function (e) {
      if (e.target.matches('.ur-datos-rol input')) { panel.classList.add('sucio'); sinGuardar = true; }
    });
    panel.querySelector('form').addEventListener('submit', function () { sinGuardar = false; });
  });
  window.addEventListener('beforeunload', function (e) { if (sinGuardar) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>

<?php admin_footer(); ?>
