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

$roles = db()->query('SELECT id, nombre FROM admin_roles ORDER BY es_dueno DESC, nombre')->fetchAll();
$usuarios = db()->query(
    'SELECT u.*, r.nombre AS rol_nombre, r.es_dueno FROM admin_usuarios u JOIN admin_roles r ON r.id = u.rol_id
     ORDER BY u.activo DESC, r.es_dueno DESC, u.nombre'
)->fetchAll();

admin_header('Usuarios y roles', 'usuarios.php');
?>

<nav class="ped-tabs" aria-label="Usuarios y roles">
  <a href="usuarios.php" class="on">Usuarios</a>
  <a href="roles.php">Roles y permisos</a>
</nav>

<div class="panel">
  <div class="panel-header"><h2>Usuarios del panel</h2></div>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Nueva contraseña</th><th>Activo</th><th>Último acceso</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): $fid = 'usr-' . (int) $u['id']; $esYo = (int) $u['id'] === (int) $yo['id']; ?>
          <tr>
            <td><strong><?= e($u['usuario']) ?></strong><?= $esYo ? ' <span class="badge si">Tú</span>' : '' ?></td>
            <td><input type="text" name="nombre" form="<?= e($fid) ?>" value="<?= e($u['nombre']) ?>" maxlength="100" required aria-label="Nombre"></td>
            <td>
              <select name="rol_id" form="<?= e($fid) ?>" aria-label="Rol">
                <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $u['rol_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td><input type="password" name="password" form="<?= e($fid) ?>" minlength="8" autocomplete="new-password" placeholder="Dejar vacío para no cambiar" aria-label="Nueva contraseña"></td>
            <td><input type="checkbox" name="activo" value="1" form="<?= e($fid) ?>" <?= (int) $u['activo'] ? 'checked' : '' ?> <?= $esYo ? 'disabled' : '' ?> aria-label="Activo"></td>
            <td><?= $u['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_acceso']))) : '<span class="muted">Nunca</span>' ?></td>
            <td style="white-space:nowrap">
              <?php if (puede('usuarios', 'editar')): ?><button type="submit" form="<?= e($fid) ?>" class="btn mini secundario">Guardar</button><?php endif; ?>
              <?php if (puede('usuarios', 'eliminar') && !$esYo): ?>
                <button type="button" class="btn mini peligro" data-confirmar-eliminar="<?= e($fid) ?>-del"
                        data-confirmar-mensaje="¿Eliminar al usuario «<?= e($u['usuario']) ?>»? Para solo quitarle el acceso, desmarca «Activo» en su lugar.">Eliminar</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($usuarios as $u): $fid = 'usr-' . (int) $u['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="usuarios.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <?php if ((int) $u['id'] === (int) $yo['id']): ?><input type="hidden" name="activo" value="1"><?php endif; ?>
    </form>
    <form id="<?= e($fid) ?>-del" method="post" action="usuarios.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<?php if (puede('usuarios', 'crear')): ?>
<div class="panel">
  <h2>Agregar usuario</h2>
  <form method="post" action="usuarios.php" data-enter-siguiente>
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear">
    <div class="form-grid">
      <div>
        <label for="nu-usuario">Usuario *</label>
        <input type="text" id="nu-usuario" name="usuario" required maxlength="50" autocapitalize="none" spellcheck="false" placeholder="Ej. sarahi">
      </div>
      <div>
        <label for="nu-nombre">Nombre completo *</label>
        <input type="text" id="nu-nombre" name="nombre" required maxlength="100" placeholder="Ej. Sarahí Reyes">
      </div>
      <div>
        <label for="nu-rol">Rol *</label>
        <select id="nu-rol" name="rol_id" required>
          <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="nu-pass">Contraseña inicial * (mínimo 8)</label>
        <input type="password" id="nu-pass" name="password" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn">Crear usuario</button>
  </form>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
