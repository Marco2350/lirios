<?php
/**
 * Cambiar la contraseña del panel de administración.
 * Reescribe la línea ADMIN_PASSWORD_HASH de includes/config.php
 * (la contraseña no vive en la base de datos, es una sola clave
 * compartida para todo el negocio — ver sección 7.1 de CLAUDE.md).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

const CONFIG_PATH = __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if (!password_verify($actual, ADMIN_PASSWORD_HASH)) {
        flash('error', 'La contraseña actual no es correcta.');
    } elseif (mb_strlen($nueva) < 8) {
        flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
    } elseif ($nueva !== $confirmar) {
        flash('error', 'La nueva contraseña y su confirmación no coinciden.');
    } else {
        $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
        $contenido = file_get_contents(CONFIG_PATH);
        // preg_replace_callback (no preg_replace): el hash trae '$' seguido de
        // dígitos ($2y$10$...), que preg_replace interpretaría como referencias
        // de grupo ($1, $2...) en el reemplazo y lo corrompería.
        $actualizado = preg_replace_callback(
            "/define\('ADMIN_PASSWORD_HASH', '.*?'\);/",
            fn() => "define('ADMIN_PASSWORD_HASH', '" . $nuevoHash . "');",
            $contenido,
            1
        );

        if ($actualizado === null || $actualizado === $contenido || !file_put_contents(CONFIG_PATH, $actualizado)) {
            error_log('[lirios] No se pudo reescribir ' . CONFIG_PATH . ' al cambiar la contraseña — revisar permisos de escritura.');
            flash('error', 'No se pudo guardar la nueva contraseña. Contacta a soporte técnico si el problema continúa.');
        } else {
            // La contraseña cambió: se cierra la sesión para que se vuelva a entrar con la nueva.
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['flash'] = ['tipo' => 'ok', 'mensaje' => 'Contraseña actualizada. Entra de nuevo con tu nueva contraseña.'];
            header('Location: index.php');
            exit;
        }
    }
    header('Location: cambiar-password.php');
    exit;
}

admin_header('Cambiar contraseña', 'cambiar-password.php');
?>

<div class="panel" style="max-width:480px">
  <h2>Cambiar contraseña del panel</h2>
  <p class="intro">Esta es la única contraseña del panel (no hay usuarios separados). Guárdala en un lugar seguro: si la pierdes, vas a necesitar ayuda técnica para restablecerla.</p>
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
