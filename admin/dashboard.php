<?php
/** Menú principal del panel de administración. */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$data = leer_json(PRODUCTOS_JSON);
$productos = $data['productos'] ?? [];
$disponibles = array_filter($productos, fn($p) => !empty($p['disponible']));

$opciones = leer_json(OPCIONES_JSON);
$totalOpciones = count($opciones['flores'] ?? [])
    + count($opciones['colores'] ?? [])
    + count($opciones['tamanos'] ?? [])
    + count($opciones['extras'] ?? []);

$imagenes = is_dir(IMAGENES_DIR)
    ? glob(IMAGENES_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE)
    : [];

admin_header('Bienvenida al panel', 'dashboard.php');
?>

<p class="intro">Desde aquí puedes editar el catálogo de productos, las opciones del personalizador de ramos y las fotos, sin tocar código. Los cambios se publican en el sitio al instante.</p>

<div class="stats">
  <div class="stat"><span class="num"><?= count($productos) ?></span><span class="lbl">productos en catálogo</span></div>
  <div class="stat"><span class="num"><?= count($disponibles) ?></span><span class="lbl">disponibles</span></div>
  <div class="stat"><span class="num"><?= $totalOpciones ?></span><span class="lbl">opciones de personalización</span></div>
  <div class="stat"><span class="num"><?= count($imagenes) ?></span><span class="lbl">fotos subidas</span></div>
</div>

<div class="card-grid">
  <a class="tarjeta" href="productos.php">
    <h2>🌸 Productos</h2>
    <p>Agrega, edita o elimina arreglos del catálogo: nombre, precio, descripción, foto y disponibilidad.</p>
  </a>
  <a class="tarjeta" href="personalizacion.php">
    <h2>🎀 Personalización</h2>
    <p>Edita las flores, colores, tamaños y extras (con sus precios) del constructor de ramos.</p>
  </a>
  <a class="tarjeta" href="subir-imagen.php">
    <h2>📷 Imágenes</h2>
    <p>Sube fotos nuevas de productos para usarlas en el catálogo.</p>
  </a>
</div>

<?php admin_footer(); ?>
