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
  <link rel="stylesheet" href="../css/admin.css?v=31">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <aside class="login-brand">
        <img src="../images/Logo_Negativo.png" alt="LIRIOS Floristería">
        <div class="login-brand-text">
          <span class="login-brand-line"></span>
          <p class="login-brand-title">Flores que cuentan historias</p>
          <p class="login-brand-sub">Catálogo, pedidos y reportes de tu floristería, en un solo lugar.</p>
        </div>
        <span class="login-brand-foot">El Progreso, Yoro · Honduras</span>
      </aside>
      <div class="login-main">
      <h1>Bienvenida de nuevo</h1>
      <p class="login-lead">Inicia sesión para gestionar el catálogo y los pedidos.</p>
      <?php if ($error): ?>
        <div class="flash error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="post" action="index.php">
        <label for="password">Contraseña</label>
        <div class="password-field">
          <svg class="password-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          <input type="password" id="password" name="password" required autofocus autocomplete="current-password" placeholder="Ingresa tu contraseña">
          <button type="button" class="password-toggle" id="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false">
            <svg class="eye-open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-closed" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.6 10.6 0 0 1 12 19c-6.4 0-10-7-10-7a18 18 0 0 1 4.1-5.1M9.9 5.2A9.6 9.6 0 0 1 12 5c6.4 0 10 7 10 7a18 18 0 0 1-2.2 3.2M1 1l22 22"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
          </button>
        </div>
        <button type="submit" class="btn">Iniciar sesión</button>
      </form>
      <a class="login-back" href="../index.html">← Volver al sitio</a>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var input = document.getElementById('password');
      var toggle = document.getElementById('password-toggle');
      toggle.addEventListener('click', function () {
        var mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        toggle.classList.toggle('activo', mostrar);
        toggle.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
        toggle.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        input.focus();
      });
    })();
  </script>
</body>
</html>
