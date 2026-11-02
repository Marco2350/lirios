<?php
/**
 * Cambiar la contraseña del usuario que tiene la sesión abierta
 * (admin_usuarios.password_hash). Cada persona cambia la suya; el dueño
 * puede restablecer la de otro usuario desde /admin/usuarios.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');
    $yo = usuario_actual();

    $stmt = db()->prepare('SELECT password_hash FROM admin_usuarios WHERE id = ?');
    $stmt->execute([$yo['id']]);
    $hashActual = (string) $stmt->fetchColumn();

    if (!password_verify($actual, $hashActual)) {
        flash('error', 'La contraseña actual no es correcta.');
    } elseif (mb_strlen($nueva) < 8) {
        flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
    } elseif ($nueva !== $confirmar) {
        flash('error', 'La nueva contraseña y su confirmación no coinciden.');
    } else {
        db()->prepare('UPDATE admin_usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $yo['id']]);
        // La contraseña cambió: se cierra la sesión para que se vuelva a entrar con la nueva.
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['flash'] = ['tipo' => 'ok', 'mensaje' => 'Contraseña actualizada. Entra de nuevo con tu nueva contraseña.'];
        header('Location: index.php');
        exit;
    }
    header('Location: cambiar-password.php');
    exit;
}

admin_header('Cambiar contraseña', 'cambiar-password.php');
?>

<div class="panel" style="max-width:480px">
  <h2>Cambiar contraseña del panel</h2>
  <p class="intro">Esta es la contraseña de tu usuario. Guárdala en un lugar seguro: si la pierdes, el dueño puede restablecértela desde «Usuarios y roles».</p>
  <form method="post" action="cambiar-password.php">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="full">
        <label for="actual">Contraseña actual</label>
        <input type="password" id="actual" name="actual" required autocomplete="current-password">
      </div>
      <div class="full">
        <label for="nueva">Nueva contraseña</label>
        <input type="password" id="nueva" name="nueva" required minlength="8" autocomplete="new-password">
      </div>
      <div class="full">
        <label for="confirmar">Confirmar nueva contraseña</label>
        <input type="password" id="confirmar" name="confirmar" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn">Guardar nueva contraseña</button>
  </form>
</div>

<?php admin_footer(); ?>
