<?php
/**
 * Layout compartido del panel: shell con sidebar fijo a la izquierda
 * (colapsable en móvil vía checkbox, sin JS), mensajes flash y cierre
 * de página. La navegación usa iconos propios en SVG (sin emojis, sin
 * librerías externas — ver admin_icono()).
 */

/** Devuelve el <svg> inline de un ícono del panel (trazo, 24×24, sin relleno). */
function admin_icono(string $nombre): string
{
    $trazos = [
        'inicio' => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v8a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1v-8"/>',
        'pedidos' => '<path d="M6 3h12v17l-2.5-1.5L13 20l-2.5-1.5L8 20l-2-1.5V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'codigos' => '<path d="M9 3 7 21M17 3l-2 18M4 8h16M3 16h16"/>',
        'reportes' => '<path d="M3 20h18"/><path d="M5 20V10M12 20V4M19 20v-7"/>',
        'productos' => '<path d="M11 3h6a2 2 0 0 1 2 2v6l-9.5 9.5a1.5 1.5 0 0 1-2 0L3 16a1.5 1.5 0 0 1 0-2Z"/><circle cx="15.5" cy="7.5" r="1.25"/>',
        'categorias' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.3"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.3"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.3"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.3"/>',
        'imagenes' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m5 18 5-5 3.5 3.5L18 12l1.7 1.7"/>',
        'contrasena' => '<circle cx="8" cy="15" r="3.3"/><path d="M10.3 12.7 18 5"/><path d="M15 8l2 2"/><path d="M17.3 5.7 19 7.3"/>',
        'salir' => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"/><path d="M15 16l4-4-4-4"/><path d="M19 12H9"/>',
        'externo' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.3 2.3L16 9.8"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
    ];
    $d = $trazos[$nombre] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function admin_header(string $titulo, string $activo = ''): void
{
    $items = [
        'dashboard.php' => ['inicio', 'Inicio'],
        'pedidos.php' => ['pedidos', 'Pedidos'],
        'codigos.php' => ['codigos', 'Códigos de ramo'],
        'reportes.php' => ['reportes', 'Reportes'],
        'productos.php' => ['productos', 'Productos'],
        'categorias.php' => ['categorias', 'Categorías'],
        'subir-imagen.php' => ['imagenes', 'Imágenes'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($titulo) ?> — Panel LIRIOS</title>
  <link rel="icon" type="image/png" href="../images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,500;0,700;0,800&family=Arimo:ital,wght@0,400;0,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/admin.css?v=12">
</head>
<body class="admin-shell">

  <input type="checkbox" id="sidebar-toggle" class="sidebar-toggle-input">
  <label for="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Abrir menú">
    <span></span><span></span><span></span>
  </label>
  <label for="sidebar-toggle" class="sidebar-overlay" aria-hidden="true"></label>

  <aside class="admin-sidebar">
    <a class="sidebar-brand" href="dashboard.php">
      <img src="../images/Logo_Negativo.png" alt="LIRIOS Floristería">
    </a>
    <span class="sidebar-eyebrow">Panel de administración</span>

    <nav class="sidebar-nav">
      <?php foreach ($items as $url => [$icono, $nombre]): ?>
        <a href="<?= e($url) ?>" class="<?= $activo === $url ? 'activo' : '' ?>">
          <span class="sidebar-icon"><?= admin_icono($icono) ?></span>
          <?= e($nombre) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <a href="../index.html" target="_blank" rel="noopener">
        <span class="sidebar-icon"><?= admin_icono('externo') ?></span> Ver sitio
      </a>
      <a href="cambiar-password.php" class="<?= $activo === 'cambiar-password.php' ? 'activo' : '' ?>">
        <span class="sidebar-icon"><?= admin_icono('contrasena') ?></span> Contraseña
      </a>
      <a href="logout.php" class="salir">
        <span class="sidebar-icon"><?= admin_icono('salir') ?></span> Cerrar sesión
      </a>
    </div>
  </aside>

  <div class="admin-content">
    <span class="eyebrow">Panel LIRIOS</span>
    <h1><?= e($titulo) ?></h1>
<?php
    mostrar_flash();
}

function admin_footer(): void
{
    ?>
  </div>

  <!-- Modal de confirmación compartido (eliminar producto/categoría/subcategoría,
       ver data-confirmar-eliminar en cada tabla y admin.js). Uno solo por página,
       en vez de uno por fila, para no duplicar dialogs en tablas largas. -->
  <dialog class="modal-confirmar" id="modal-confirmar">
    <h3>Confirmar</h3>
    <p data-confirmar-mensaje-texto></p>
    <div class="modal-confirmar-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="button" class="btn mini peligro" data-confirmar-boton>Sí, eliminar</button>
    </div>
  </dialog>

  <script src="../js/admin.js?v=1"></script>
</body>
</html>
    <?php
}

/* ---------- Mensajes flash (un solo uso, guardados en sesión) ---------- */

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/**
 * Muestra el mensaje flash guardado en sesión (si hay). Los errores se
 * muestran en un modal bloqueante (hay que confirmar "Entendido" — no se
 * quiere que un error de guardado pase inadvertido); los mensajes "ok" se
 * muestran en un toast que se cierra solo, para no interrumpir con un
 * clic extra en cada guardado exitoso (son la mayoría de las acciones
 * del panel). Ambos se abren/cierran desde admin.js.
 */
function mostrar_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    if ($f['tipo'] === 'error') {
        echo '<dialog class="modal-alerta error" id="alerta-flash">'
            . '<div class="modal-alerta-icono">✕</div>'
            . '<h3>Ocurrió un problema</h3>'
            . '<p>' . e($f['mensaje']) . '</p>'
            . '<div class="modal-alerta-acciones"><button type="button" class="btn mini" data-cerrar-modal>Entendido</button></div>'
            . '</dialog>';
    } else {
        echo '<div class="toast-flash ok" id="toast-flash" role="status">'
            . '<span class="toast-flash-icono">✓</span> ' . e($f['mensaje'])
            . '</div>';
    }
}
