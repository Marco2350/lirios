<?php
/**
 * Layout compartido del panel: barra superior, mensajes flash
 * y cierre de página.
 */

function admin_header(string $titulo, string $activo = ''): void
{
    $items = [
        'dashboard.php' => 'Inicio',
        'productos.php' => 'Productos',
        'personalizacion.php' => 'Personalización',
        'subir-imagen.php' => 'Imágenes',
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($titulo) ?> — Panel Lirios Floristería</title>
  <link rel="icon" type="image/png" href="../images/logo.png">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <header class="admin-topbar">
    <a class="brand" href="dashboard.php">
      <img src="../images/logo.png" alt="">
      Panel de administración
    </a>
    <nav class="admin-nav">
      <?php foreach ($items as $url => $nombre): ?>
        <a href="<?= e($url) ?>" class="<?= $activo === $url ? 'activo' : '' ?>"><?= e($nombre) ?></a>
      <?php endforeach; ?>
      <a href="../index.html" target="_blank" rel="noopener">Ver sitio ↗</a>
      <a href="logout.php" class="salir">Cerrar sesión</a>
    </nav>
  </header>
  <main class="admin-main">
  <h1><?= e($titulo) ?></h1>
<?php
    mostrar_flash();
}

function admin_footer(): void
{
    echo "  </main>\n</body>\n</html>";
}

/* ---------- Mensajes flash (un solo uso, guardados en sesión) ---------- */

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function mostrar_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $clase = $f['tipo'] === 'ok' ? 'ok' : 'error';
        echo '<div class="flash ' . $clase . '">' . e($f['mensaje']) . '</div>';
    }
}
