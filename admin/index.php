<?php
/**
 * Login del panel de administración.
 * Una sola contraseña (hasheada en includes/config.php), con freno a
 * intentos automatizados: 6 intentos fallidos por IP bloquean el acceso
 * durante 15 minutos (tabla admin_login_intentos).
 */

require_once __DIR__ . '/includes/config.php';

const LOGIN_MAX_INTENTOS = 6;
const LOGIN_VENTANA_MIN = 15;

admin_session_start();

// Si ya hay sesión activa, ir directo al panel
if (!empty($_SESSION['lirios_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$ip = ip_visitante();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloqueado = false;
    if ($ip) {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM admin_login_intentos WHERE ip = ? AND creado_en >= (NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE)'
        );
        $stmt->execute([$ip]);
        $bloqueado = (int) $stmt->fetchColumn() >= LOGIN_MAX_INTENTOS;
    }

    if ($bloqueado) {
        $error = 'Demasiados intentos fallidos. Espera ' . LOGIN_VENTANA_MIN . ' minutos y vuelve a intentarlo.';
    } else {
        $password = $_POST['password'] ?? '';
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['lirios_admin'] = true;
            if ($ip) {
                db()->prepare('DELETE FROM admin_login_intentos WHERE ip = ?')->execute([$ip]);
            }
            header('Location: dashboard.php');
            exit;
        }
        if ($ip) {
            db()->prepare('INSERT INTO admin_login_intentos (ip) VALUES (?)')->execute([$ip]);
        }
        // Pausa breve para frenar intentos automatizados de adivinar la contraseña
        sleep(1);
        $error = 'La contraseña no es correcta. Verifícala e intenta de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Iniciar sesión — Panel LIRIOS</title>
  <link rel="icon" type="image/png" href="../images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,500;0,700;0,800&family=Arimo:ital,wght@0,400;0,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/admin.css?v=12">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <img src="../images/logo.png" alt="LIRIOS Floristería">
      <h1>Panel de administración</h1>
      <p>Inicia sesión para gestionar el catálogo y los pedidos.</p>
      <?php if ($error): ?>
        <div class="flash error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="post" action="index.php">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
        <button type="submit" class="btn">Iniciar sesión</button>
      </form>
    </div>
  </div>
</body>
</html>
