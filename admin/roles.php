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
            flash('ok', "Rol «{$nombre}» creado. Marca ahora sus permisos y guarda.");
        }
        header('Location: roles.php');
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
        header('Location: roles.php');
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

admin_header('Usuarios y roles', 'usuarios.php');
?>

<nav class="ped-tabs" aria-label="Usuarios y roles">
  <a href="usuarios.php">Usuarios</a>
  <a href="roles.php" class="on">Roles y permisos</a>
</nav>

<p class="intro">Cada rol define qué módulos ve y qué puede hacer en ellos. «Eliminar» se marca aparte, así puedes dejar que alguien edite sin poder borrar. Los cambios aplican de inmediato.</p>

<?php foreach ($roles as $r): $esD = (int) $r['es_dueno'] === 1; $fid = 'rol-' . (int) $r['id']; ?>
  <div class="panel">
    <form id="<?= e($fid) ?>" method="post" action="roles.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar_rol">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <div class="panel-header">
        <h2><?= e($r['nombre']) ?> <small class="muted">· <?= (int) $r['usuarios'] ?> <?= (int) $r['usuarios'] === 1 ? 'usuario' : 'usuarios' ?></small></h2>
      </div>

      <?php if ($esD): ?>
        <p class="intro">Acceso total a todos los módulos, incluido eliminar y administrar usuarios. Este rol no se puede restringir.</p>
      <?php else: ?>
        <div class="form-grid">
          <div>
            <label for="<?= e($fid) ?>-n">Nombre del rol</label>
            <input type="text" id="<?= e($fid) ?>-n" name="nombre" value="<?= e($r['nombre']) ?>" maxlength="80" required>
          </div>
          <div>
            <label for="<?= e($fid) ?>-d">Descripción</label>
            <input type="text" id="<?= e($fid) ?>-d" name="descripcion" value="<?= e($r['descripcion']) ?>" maxlength="200">
          </div>
        </div>
        <div class="tabla-scroll">
          <table class="tabla">
            <thead>
              <tr><th>Módulo</th><?php foreach (ACCIONES_PERMISO as $etq): ?><th style="text-align:center"><?= e($etq) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
              <?php foreach (MODULOS as $modulo => $etqMod): $perm = $permisosPorRol[$r['id']][$modulo] ?? []; ?>
                <tr>
                  <td><?= e($etqMod) ?></td>
                  <?php foreach (array_keys(ACCIONES_PERMISO) as $a): ?>
                    <td style="text-align:center">
                      <input type="checkbox" name="perm[<?= e($modulo) ?>][<?= e($a) ?>]" value="1" data-perm-accion="<?= e($a) ?>"
                             aria-label="<?= e(ACCIONES_PERMISO[$a] . ' ' . $etqMod) ?>" <?= !empty($perm['puede_' . $a]) ? 'checked' : '' ?>>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:1rem">
          <?php if (puede('usuarios', 'editar')): ?><button type="submit" class="btn">Guardar rol</button><?php endif; ?>
          <?php if (puede('usuarios', 'eliminar')): ?>
            <button type="button" class="btn peligro" data-confirmar-eliminar="<?= e($fid) ?>-del"
                    data-confirmar-mensaje="¿Eliminar el rol «<?= e($r['nombre']) ?>»? Solo se puede si ya no tiene usuarios.">Eliminar rol</button>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
    <?php if (!$esD): ?>
      <form id="<?= e($fid) ?>-del" method="post" action="roles.php">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="eliminar_rol">
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (puede('usuarios', 'crear')): ?>
<div class="panel">
  <h2>Nuevo rol</h2>
  <form method="post" action="roles.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear_rol">
    <div class="form-grid">
      <div>
        <label for="nr-nombre">Nombre del rol *</label>
        <input type="text" id="nr-nombre" name="nombre" required maxlength="80" placeholder="Ej. Caja">
      </div>
      <div>
        <label for="nr-desc">Descripción</label>
        <input type="text" id="nr-desc" name="descripcion" maxlength="200">
      </div>
    </div>
    <button type="submit" class="btn">Crear rol</button>
  </form>
</div>
<?php endif; ?>

<script>
// Marcar «Crear», «Editar» o «Eliminar» marca «Ver»; desmarcar «Ver» desmarca el resto de esa fila.
document.querySelectorAll('input[data-perm-accion]').forEach(function (cb) {
  cb.addEventListener('change', function () {
    var fila = cb.closest('tr');
    var ver = fila.querySelector('[data-perm-accion="ver"]');
    if (cb.dataset.permAccion === 'ver' && !cb.checked) {
      fila.querySelectorAll('input').forEach(function (o) { o.checked = false; });
    } else if (cb.checked) {
      ver.checked = true;
    }
  });
});
</script>

<?php admin_footer(); ?>
