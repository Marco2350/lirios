<?php
/**
 * Galería de fotos de producto en /images/productos.
 * Vía secundaria de subida — lo normal es subir la foto directo desde
 * el formulario de productos.php; esta pantalla sirve para ver todas
 * las fotos disponibles o subir una suelta para usar más tarde.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $resultado = guardar_imagen_subida($_FILES['foto']);
    if ($resultado['ok']) {
        flash('ok', "Foto subida como «{$resultado['nombre']}». Ya puedes elegirla al crear o editar un producto.");
    } else {
        flash('error', $resultado['error']);
    }
    header('Location: subir-imagen.php');
    exit;
}

$imagenes = is_dir(IMAGENES_DIR)
    ? glob(IMAGENES_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE)
    : [];
rsort($imagenes); // las más recientes primero (por nombre)

admin_header('Fotos de productos', 'subir-imagen.php');
?>

<div class="panel">
  <h2>Subir foto suelta</h2>
  <p class="intro">Normalmente no hace falta esta pantalla: al crear o editar un producto en <a href="productos.php">Productos</a> puedes subir la foto ahí mismo. Usa esto solo si quieres subir una foto para usarla más adelante.</p>
  <form method="post" action="subir-imagen.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="full">
        <label for="foto">Archivo de imagen</label>
        <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
        <small>JPG, PNG o WebP · máximo 3 MB · se convierte a WebP automáticamente.</small>
      </div>
    </div>
    <button type="submit" class="btn">Subir foto</button>
  </form>
</div>

<div class="panel">
  <h2>Fotos disponibles (<?= count($imagenes) ?>)</h2>
  <?php if (!$imagenes): ?>
    <p>Aún no has subido ninguna foto.</p>
  <?php else: ?>
    <div class="img-grid">
      <?php foreach ($imagenes as $ruta): ?>
        <figure>
          <img src="../images/productos/<?= e(basename($ruta)) ?>" alt="" loading="lazy">
          <figcaption><?= e(basename($ruta)) ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php admin_footer(); ?>
