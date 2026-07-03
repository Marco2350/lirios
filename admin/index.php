<?php
/**
 * Login del panel de administración.
 * Una sola contraseña (hasheada en includes/config.php).
 */

require_once __DIR__ . '/includes/config.php';

session_start();

// Si ya hay sesión activa, ir directo al panel
if (!empty($_SESSION['lirios_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['lirios_admin'] = true;
        header('Location: dashboard.php');
        exit;
    }
    // Pausa breve para frenar intentos automatizados de adivinar la contraseña
    sleep(1);
    $error = 'Contraseña incorrecta. Intenta de nuevo.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Iniciar sesión — Panel Lirios Floristería</title>
  <link rel="icon" type="image/png" href="../images/logo.png">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <img src="../images/logo.png" alt="Lirios Floristería">
      <h1>Panel de administración</h1>
      <p>Ingresa la contraseña para editar el catálogo y las opciones de personalización.</p>
      <?php if ($error): ?>
        <div class="flash error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="post" action="index.php">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
        <button type="submit" class="btn">Entrar</button>
      </form>
    </div>
  </div>
</body>
</html>
