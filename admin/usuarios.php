<?php
/**
 * Usuarios del panel: alta, cambio de rol, activar/desactivar, restablecer
 * contraseña y eliminar. Qué puede hacer cada rol se define en roles.php.
 * Protegido por el módulo «usuarios» (por defecto solo el dueño).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

exigir_permiso('usuarios', 'ver');
verificar_csrf();

$yo = usuario_actual();

/** Cuántos dueños activos hay (para no quedarse sin ninguno). */
function duenos_activos(?int $excluirId = null): int
{
    $sql = 'SELECT COUNT(*) FROM admin_usuarios u JOIN admin_roles r ON r.id = u.rol_id
            WHERE u.activo = 1 AND r.es_dueno = 1' . ($excluirId ? ' AND u.id <> ' . (int) $excluirId : '');
    return (int) db()->query($sql)->fetchColumn();
}

function rol_es_dueno(int $rolId): bool
{
    $stmt = db()->prepare('SELECT es_dueno FROM admin_roles WHERE id = ?');
    $stmt->execute([$rolId]);
    return (int) $stmt->fetchColumn() === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'crear') {
        exigir_permiso('usuarios', 'crear');
        $usuario = mb_strtolower(limpiar_texto($_POST['usuario'] ?? '', 50));
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);
        $rolId = (int) ($_POST['rol_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');

        $existe = db()->prepare('SELECT COUNT(*) FROM admin_usuarios WHERE usuario = ?');
        $existe->execute([$usuario]);
        $rolValido = db()->prepare('SELECT COUNT(*) FROM admin_roles WHERE id = ?');
        $rolValido->execute([$rolId]);

        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $usuario)) {
            flash('error', 'El usuario debe tener entre 3 y 50 caracteres: letras sin tilde, números, punto, guion o guion bajo.');
        } elseif ($nombre === '') {
            flash('error', 'Escribe el nombre de la persona.');
        } elseif (!(int) $rolValido->fetchColumn()) {
            flash('error', 'Elige un rol válido.');
        } elseif (mb_strlen($password) < 8) {
            flash('error', 'La contraseña inicial debe tener al menos 8 caracteres.');
        } elseif ((int) $existe->fetchColumn()) {
            flash('error', 'Ya existe un usuario con ese nombre de usuario.');
        } else {
            db()->prepare('INSERT INTO admin_usuarios (usuario, nombre, password_hash, rol_id) VALUES (?, ?, ?, ?)')
                ->execute([$usuario, $nombre, password_hash($password, PASSWORD_DEFAULT), $rolId]);
            flash('ok', "Usuario «{$usuario}» creado.");
        }
        header('Location: usuarios.php');
        exit;
    }

    if ($accion === 'guardar') {
        exigir_permiso('usuarios', 'editar');
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);
        $rolId = (int) ($_POST['rol_id'] ?? 0);
        $activo = !empty($_POST['activo']) ? 1 : 0;
        $nueva = (string) ($_POST['password'] ?? '');

        $stmt = db()->prepare('SELECT id, rol_id, activo FROM admin_usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $actual = $stmt->fetch();
        $rolValido = db()->prepare('SELECT COUNT(*) FROM admin_roles WHERE id = ?');
        $rolValido->execute([$rolId]);

        if (!$actual || $nombre === '' || !(int) $rolValido->fetchColumn()) {
            flash('error', 'Revisa el nombre y el rol del usuario.');
        } elseif ($nueva !== '' && mb_strlen($nueva) < 8) {
            flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
        } elseif ($id === (int) $yo['id'] && (!$activo || !rol_es_dueno($rolId) && es_dueno())) {
            // Nadie se quita a sí mismo el acceso (ni se baja de dueño) por accidente.
            flash('error', 'No puedes desactivarte ni quitarte tu propio rol de dueño.');
        } elseif (rol_es_dueno((int) $actual['rol_id']) && (int) $actual['activo'] === 1
                  && (!$activo || !rol_es_dueno($rolId)) && duenos_activos($id) === 0) {
            flash('error', 'Tiene que quedar al menos un dueño activo.');
        } else {
            db()->prepare('UPDATE admin_usuarios SET nombre = ?, rol_id = ?, activo = ? WHERE id = ?')
                ->execute([$nombre, $rolId, $activo, $id]);
            if ($nueva !== '') {
                db()->prepare('UPDATE admin_usuarios SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($nueva, PASSWORD_DEFAULT), $id]);
            }
            flash('ok', 'Usuario guardado.' . ($nueva !== '' ? ' La contraseña fue restablecida.' : ''));
        }
        header('Location: usuarios.php');
        exit;
    }

    if ($accion === 'eliminar') {
        exigir_permiso('usuarios', 'eliminar');
        $stmt = db()->prepare('SELECT rol_id, activo FROM admin_usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $actual = $stmt->fetch();
        if (!$actual) {
            flash('error', 'Ese usuario ya no existe.');
        } elseif ($id === (int) $yo['id']) {
            flash('error', 'No puedes eliminar tu propio usuario.');
        } elseif (rol_es_dueno((int) $actual['rol_id']) && (int) $actual['activo'] === 1 && duenos_activos($id) === 0) {
            flash('error', 'Tiene que quedar al menos un dueño activo.');
        } else {
            db()->prepare('DELETE FROM admin_usuarios WHERE id = ?')->execute([$id]);
            flash('ok', 'Usuario eliminado.');
        }
        header('Location: usuarios.php');
        exit;
    }

    header('Location: usuarios.php');
    exit;
}

$roles = db()->query(
    'SELECT r.id, r.nombre, r.es_dueno FROM admin_roles r ORDER BY r.es_dueno DESC, r.nombre'
)->fetchAll();
$usuarios = db()->query(
    'SELECT u.*, r.nombre AS rol_nombre, r.es_dueno FROM admin_usuarios u JOIN admin_roles r ON r.id = u.rol_id
     ORDER BY u.activo DESC, r.es_dueno DESC, u.nombre'
)->fetchAll();

$totalActivos = count(array_filter($usuarios, fn($u) => (int) $u['activo'] === 1));
$puedeEditar = puede('usuarios', 'editar');
$puedeEliminar = puede('usuarios', 'eliminar');

// Datos para rellenar el modal de edición (se leen con textContent/value, nunca como HTML).
$datosUsuarios = [];
foreach ($usuarios as $u) {
    $datosUsuarios[$u['id']] = [
        'usuario' => $u['usuario'], 'nombre' => $u['nombre'], 'rol_id' => (int) $u['rol_id'],
        'activo' => (int) $u['activo'] === 1, 'yo' => (int) $u['id'] === (int) $yo['id'],
    ];
}

admin_header('Usuarios y roles', 'usuarios.php');
?>

<nav class="ped-tabs" aria-label="Usuarios y roles">
  <a href="usuarios.php" class="on">Usuarios</a>
  <a href="roles.php">Roles y permisos</a>
</nav>

<div class="ur-resumen">
  <span class="ur-stat"><strong><?= count($usuarios) ?></strong> <?= count($usuarios) === 1 ? 'usuario' : 'usuarios' ?></span>
  <span class="ur-stat"><strong><?= $totalActivos ?></strong> <?= $totalActivos === 1 ? 'activo' : 'activos' ?></span>
  <span class="ur-stat"><strong><?= count($roles) ?></strong> <?= count($roles) === 1 ? 'rol' : 'roles' ?></span>
</div>

<div class="ped-toolbar" role="search">
  <label class="ped-buscar">
    <?= admin_icono('buscar') ?>
    <input type="search" id="ur-buscar" placeholder="Buscar por nombre o usuario" aria-label="Buscar usuario">
  </label>
  <select id="ur-f-rol" aria-label="Filtrar por rol">
    <option value="">Todos los roles</option>
    <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['nombre']) ?></option><?php endforeach; ?>
  </select>
  <select id="ur-f-estado" aria-label="Filtrar por estado">
    <option value="">Todos los estados</option>
    <option value="1">Activos</option>
    <option value="0">Inactivos</option>
  </select>
  <a class="ped-limpiar" href="#" id="ur-limpiar" hidden>Quitar filtros</a>
  <?php if (puede('usuarios', 'crear')): ?>
    <button type="button" class="btn ped-nuevo" data-abrir-modal="modal-usuario-nuevo"><?= admin_icono('mas') ?> Usuario</button>
  <?php endif; ?>
</div>

<div class="ur-lista" id="ur-lista">
  <?php foreach ($usuarios as $u):
      $esYo = (int) $u['id'] === (int) $yo['id'];
      $dueno = (int) $u['es_dueno'] === 1;
      $tono = $dueno ? 'dueno' : (string) ((int) $u['rol_id'] % 5);
      ?>
    <article class="ur-fila <?= (int) $u['activo'] ? '' : 'inactivo' ?>" data-rol="<?= (int) $u['rol_id'] ?>" data-activo="<?= (int) $u['activo'] ?>"
             data-texto="<?= e(mb_strtolower($u['nombre'] . ' ' . $u['usuario'], 'UTF-8')) ?>">
      <div class="ur-quien">
        <span class="ur-avatar ur-tono-<?= e($tono) ?>" aria-hidden="true"><?= e(iniciales($u['nombre'])) ?></span>
        <div>
          <strong><?= e($u['nombre']) ?><?= $esYo ? '<span class="ur-yo">Tú</span>' : '' ?></strong>
          <small>@<?= e($u['usuario']) ?></small>
        </div>
      </div>
      <div class="ur-col-rol">
        <span class="ur-rol-badge ur-tono-<?= e($tono) ?>"><?= $dueno ? admin_icono('corona') : admin_icono('escudo') ?><?= e($u['rol_nombre']) ?></span>
      </div>
      <div class="ur-col-estado">
        <span class="ur-estado <?= (int) $u['activo'] ? '' : 'off' ?>"><i></i><?= (int) $u['activo'] ? 'Activo' : 'Inactivo' ?></span>
      </div>
      <div class="ur-acceso">
        <?= $u['ultimo_acceso'] ? 'Último acceso<br>' . e(date('d/m/Y H:i', strtotime($u['ultimo_acceso']))) : 'Aún no ha entrado' ?>
      </div>
      <div class="ur-acciones">
        <?php if ($puedeEditar): ?>
          <button type="button" class="btn mini secundario" data-editar-usuario="<?= (int) $u['id'] ?>"><?= admin_icono('editar') ?> Editar</button>
        <?php endif; ?>
        <?php if ($puedeEliminar && !$esYo): ?>
          <button type="button" class="btn mini peligro" data-confirmar-eliminar="usr-del-<?= (int) $u['id'] ?>"
                  data-confirmar-mensaje="¿Eliminar al usuario «<?= e($u['usuario']) ?>»? Para solo quitarle el acceso, edítalo y desmarca «Activo».">Eliminar</button>
          <form id="usr-del-<?= (int) $u['id'] ?>" method="post" action="usuarios.php">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  <p class="ur-vacio" id="ur-vacio" hidden>Ningún usuario coincide con los filtros.</p>
</div>

<?php if (puede('usuarios', 'crear')): ?>
<dialog class="modal-portada modal-pedido" id="modal-usuario-nuevo" style="width:min(520px,92vw)">
  <form method="post" action="usuarios.php" data-enter-siguiente>
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear">
    <h3>Nuevo usuario</h3>
    <p class="modal-ayuda">Elige su rol: define qué módulos puede ver y qué puede hacer en ellos.</p>
    <div class="form-grid">
      <div>
        <label for="nu-usuario">Usuario *</label>
        <input type="text" id="nu-usuario" name="usuario" required maxlength="50" autocapitalize="none" spellcheck="false" autocomplete="off" placeholder="Ej. sarahi">
      </div>
      <div>
        <label for="nu-nombre">Nombre completo *</label>
        <input type="text" id="nu-nombre" name="nombre" required maxlength="100" autocomplete="off" placeholder="Ej. Sarahí Reyes">
      </div>
      <div>
        <label for="nu-rol">Rol *</label>
        <select id="nu-rol" name="rol_id" required>
          <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="nu-pass">Contraseña inicial * (mín. 8)</label>
        <input type="password" id="nu-pass" name="password" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Crear usuario</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<dialog class="modal-portada modal-pedido" id="modal-usuario-editar" style="width:min(520px,92vw)">
  <form method="post" action="usuarios.php" id="form-usuario-editar" data-enter-siguiente>
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="activo" value="1" id="ue-activo-fijo" disabled>
    <h3>Editar usuario <small id="ue-usuario" class="muted"></small></h3>
    <div class="form-grid">
      <div>
        <label for="ue-nombre">Nombre completo *</label>
        <input type="text" id="ue-nombre" name="nombre" required maxlength="100" autocomplete="off">
      </div>
      <div>
        <label for="ue-rol">Rol *</label>
        <select id="ue-rol" name="rol_id" required>
          <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="full">
        <label for="ue-pass">Restablecer contraseña</label>
        <input type="password" id="ue-pass" name="password" minlength="8" autocomplete="new-password" placeholder="Déjala vacía para no cambiarla (mín. 8)">
      </div>
      <div class="full">
        <label class="ur-permiso" for="ue-activo" style="border:0;padding:0.2rem 0">
          <span class="lbl"><strong>Usuario activo</strong> <small class="muted" id="ue-activo-nota">Si lo desactivas, ya no puede entrar al panel.</small></span>
          <span class="ur-switch"><input type="checkbox" id="ue-activo" name="activo" value="1"><span></span></span>
        </label>
      </div>
    </div>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Guardar cambios</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script>
(function () {
  // ---- Filtros (en el navegador; son pocos usuarios) ----
  var filas = Array.prototype.slice.call(document.querySelectorAll('.ur-fila'));
  var q = document.getElementById('ur-buscar'), fr = document.getElementById('ur-f-rol'), fe = document.getElementById('ur-f-estado');
  var limpiar = document.getElementById('ur-limpiar'), vacio = document.getElementById('ur-vacio');
  function filtrar() {
    var t = q.value.trim().toLowerCase(), visibles = 0;
    filas.forEach(function (f) {
      var ok = (!t || f.dataset.texto.indexOf(t) !== -1) && (!fr.value || f.dataset.rol === fr.value) && (!fe.value || f.dataset.activo === fe.value);
      f.hidden = !ok;
      if (ok) visibles++;
    });
    vacio.hidden = visibles !== 0;
    limpiar.hidden = !(t || fr.value || fe.value);
  }
  [q, fr, fe].forEach(function (c) { c.addEventListener('input', filtrar); });
  limpiar.addEventListener('click', function (e) { e.preventDefault(); q.value = ''; fr.value = ''; fe.value = ''; filtrar(); });

  // ---- Modal de edición ----
  var dlg = document.getElementById('modal-usuario-editar');
  if (!dlg) return;
  var datos = <?= json_encode($datosUsuarios, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  var form = document.getElementById('form-usuario-editar');
  document.querySelectorAll('[data-editar-usuario]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = datos[b.dataset.editarUsuario];
      if (!d) return;
      form.elements['id'].value = b.dataset.editarUsuario;
      document.getElementById('ue-usuario').textContent = '@' + d.usuario;
      form.elements['nombre'].value = d.nombre;
      form.elements['rol_id'].value = d.rol_id;
      form.elements['password'].value = '';
      var chk = document.getElementById('ue-activo');
      chk.checked = d.activo;
      chk.disabled = d.yo;                                   // nadie se desactiva a sí mismo
      document.getElementById('ue-activo-fijo').disabled = !d.yo;
      document.getElementById('ue-activo-nota').textContent = d.yo ? 'No puedes desactivar tu propio usuario.' : 'Si lo desactivas, ya no puede entrar al panel.';
      dlg.showModal();
    });
  });
})();
</script>

<?php admin_footer(); ?>
