<?php
/**
 * Subida de fotos de producto a /images/productos.
 * Valida tipo real del archivo, tamaño máximo y sanea el nombre.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

const MAX_BYTES = 3 * 1024 * 1024; // 3 MB
$extensionesValidas = ['jpg', 'jpeg', 'png', 'webp'];
$mimesValidos = ['image/jpeg', 'image/png', 'image/webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $foto = $_FILES['foto'];

    if ($foto['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'La subida falló. Verifica que el archivo no pase de 3 MB e intenta de nuevo.');
    } elseif ($foto['size'] > MAX_BYTES) {
        flash('error', 'La foto pesa más de 3 MB. Comprímela antes de subirla (ej. tinypng.com o squoosh.app).');
    } else {
        $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
        // Verificar el contenido real del archivo, no solo la extensión
        $mime = mime_content_type($foto['tmp_name']);

        if (!in_array($ext, $extensionesValidas, true) || !in_array($mime, $mimesValidos, true)) {
            flash('error', 'Formato no permitido. Sube imágenes JPG, PNG o WebP.');
        } else {
            if (!is_dir(IMAGENES_DIR)) {
                mkdir(IMAGENES_DIR, 0755, true);
            }
            $base = slugify(pathinfo($foto['name'], PATHINFO_FILENAME));
            $nombre = $base . '.' . $ext;
            $n = 2;
            while (file_exists(IMAGENES_DIR . '/' . $nombre)) {
                $nombre = $base . '-' . $n . '.' . $ext;
                $n++;
            }
            if (move_uploaded_file($foto['tmp_name'], IMAGENES_DIR . '/' . $nombre)) {
                flash('ok', "Foto subida como «{$nombre}». Ya puedes elegirla al crear o editar un producto.");
            } else {
                flash('error', 'No se pudo guardar la foto en /images/productos. Revisa permisos de la carpeta.');
            }
        }
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
  <h2>Subir foto nueva</h2>
  <p class="intro">Formatos: JPG, PNG o WebP · Máximo 3 MB. Sube las fotos ya comprimidas para que el sitio cargue rápido (puedes usar <a href="https://squoosh.app" target="_blank" rel="noopener">squoosh.app</a>).</p>
  <form method="post" action="subir-imagen.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="full">
        <label for="foto">Archivo de imagen</label>
        <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
      </div>
    </div>
    <button type="submit" class="btn">Subir foto</button>
  </form>
</div>

<div class="panel">
  <h2>Fotos disponibles (<?= count($imagenes) ?>)</h2>
  <?php if (!$imagenes): ?>
    <p>Aún no hay fotos en <code>/images/productos</code>.</p>
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
