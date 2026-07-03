<?php
/**
 * CRUD del catálogo de productos (data/productos.json).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

$data = leer_json(PRODUCTOS_JSON);
$data['productos'] = $data['productos'] ?? [];
$data['ocasiones'] = $data['ocasiones'] ?? [];
$data['categorias'] = $data['categorias'] ?? [];

/* Fotos disponibles para el select de imagen */
$fotos = is_dir(IMAGENES_DIR)
    ? array_map('basename', glob(IMAGENES_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE))
    : [];

/* ---------- Acciones POST ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $idOriginal = limpiar_texto($_POST['id_original'] ?? '', 100);
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);
        $descripcion = limpiar_texto($_POST['descripcion'] ?? '', 600);
        $precio = limpiar_precio($_POST['precio'] ?? 0);
        $categoria = limpiar_texto($_POST['categoria'] ?? '', 50);
        $ocasion = limpiar_texto($_POST['ocasion'] ?? '', 50);
        $imagen = basename(limpiar_texto($_POST['imagen'] ?? '', 150));

        if ($nombre === '' || $precio <= 0) {
            flash('error', 'El nombre y un precio mayor que cero son obligatorios.');
        } elseif ($imagen !== '' && !in_array($imagen, $fotos, true)) {
            flash('error', 'La imagen seleccionada no existe en /images/productos.');
        } else {
            $producto = [
                'id' => '',
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'categoria' => $categoria,
                'ocasion' => $ocasion,
                'imagen' => $imagen !== '' ? 'images/productos/' . $imagen : '',
                'disponible' => isset($_POST['disponible']),
                'destacado' => isset($_POST['destacado']),
            ];

            $indice = null;
            foreach ($data['productos'] as $i => $p) {
                if ($p['id'] === $idOriginal) {
                    $indice = $i;
                    break;
                }
            }

            if ($indice !== null) {
                $producto['id'] = $idOriginal; // mantener el id: hay enlaces ?id= que lo usan
                $data['productos'][$indice] = $producto;
                $msj = 'Producto actualizado.';
            } else {
                $producto['id'] = id_unico(slugify($nombre), $data['productos']);
                $data['productos'][] = $producto;
                $msj = 'Producto agregado al catálogo.';
            }

            if (guardar_json(PRODUCTOS_JSON, $data)) {
                flash('ok', $msj);
            } else {
                flash('error', 'No se pudo escribir productos.json. Revisa permisos del archivo.');
            }
        }
        header('Location: productos.php');
        exit;
    }

    if ($accion === 'eliminar') {
        $id = limpiar_texto($_POST['id'] ?? '', 100);
        $antes = count($data['productos']);
        $data['productos'] = array_values(array_filter($data['productos'], fn($p) => $p['id'] !== $id));
        if (count($data['productos']) < $antes && guardar_json(PRODUCTOS_JSON, $data)) {
            flash('ok', 'Producto eliminado.');
        } else {
            flash('error', 'No se pudo eliminar el producto.');
        }
        header('Location: productos.php');
        exit;
    }

    if ($accion === 'toggle') {
        $id = limpiar_texto($_POST['id'] ?? '', 100);
        foreach ($data['productos'] as &$p) {
            if ($p['id'] === $id) {
                $p['disponible'] = empty($p['disponible']);
            }
        }
        unset($p);
        guardar_json(PRODUCTOS_JSON, $data);
        header('Location: productos.php');
        exit;
    }
}

/* ---------- Producto en edición (?editar=id) ---------- */

$editando = null;
if (!empty($_GET['editar'])) {
    foreach ($data['productos'] as $p) {
        if ($p['id'] === $_GET['editar']) {
            $editando = $p;
            break;
        }
    }
}

admin_header('Productos del catálogo', 'productos.php');
?>

<div class="panel">
  <h2><?= $editando ? 'Editar: ' . e($editando['nombre']) : 'Agregar producto nuevo' ?></h2>
  <form method="post" action="productos.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id_original" value="<?= e($editando['id'] ?? '') ?>">

    <div class="form-grid">
      <div>
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" required maxlength="100" value="<?= e($editando['nombre'] ?? '') ?>">
      </div>
      <div>
        <label for="precio">Precio (L.) *</label>
        <input type="number" id="precio" name="precio" required min="1" step="0.01" value="<?= e((string)($editando['precio'] ?? '')) ?>">
      </div>
      <div>
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
          <?php foreach ($data['categorias'] as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= (($editando['categoria'] ?? '') === $c['id']) ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="ocasion">Ocasión</label>
        <select id="ocasion" name="ocasion">
          <?php foreach ($data['ocasiones'] as $o): ?>
            <option value="<?= e($o['id']) ?>" <?= (($editando['ocasion'] ?? '') === $o['id']) ? 'selected' : '' ?>><?= e($o['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="imagen">Foto</label>
        <select id="imagen" name="imagen">
          <option value="">— Sin foto —</option>
          <?php $imgActual = basename($editando['imagen'] ?? ''); ?>
          <?php foreach ($fotos as $f): ?>
            <option value="<?= e($f) ?>" <?= $imgActual === $f ? 'selected' : '' ?>><?= e($f) ?></option>
          <?php endforeach; ?>
        </select>
        <small><a href="subir-imagen.php">Subir una foto nueva ↗</a></small>
      </div>
      <div>
        <label>&nbsp;</label>
        <label class="check-row"><input type="checkbox" name="disponible" <?= !empty($editando['disponible']) || !$editando ? 'checked' : '' ?>> Disponible</label>
        <label class="check-row"><input type="checkbox" name="destacado" <?= !empty($editando['destacado']) ? 'checked' : '' ?>> Destacado en la portada</label>
      </div>
      <div class="full">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" maxlength="600"><?= e($editando['descripcion'] ?? '') ?></textarea>
      </div>
    </div>

    <button type="submit" class="btn"><?= $editando ? 'Guardar cambios' : 'Agregar producto' ?></button>
    <?php if ($editando): ?>
      <a class="btn secundario" href="productos.php">Cancelar edición</a>
    <?php endif; ?>
  </form>
</div>

<div class="panel">
  <h2>Catálogo actual (<?= count($data['productos']) ?>)</h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr>
          <th>Foto</th>
          <th>Nombre</th>
          <th>Precio</th>
          <th>Categoría</th>
          <th>Ocasión</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$data['productos']): ?>
          <tr><td colspan="7">Aún no hay productos. Agrega el primero con el formulario de arriba.</td></tr>
        <?php endif; ?>
        <?php foreach ($data['productos'] as $p): ?>
          <tr>
            <td><?php if (!empty($p['imagen'])): ?><img class="mini" src="../<?= e($p['imagen']) ?>" alt=""><?php endif; ?></td>
            <td>
              <strong><?= e($p['nombre']) ?></strong>
              <?php if (!empty($p['destacado'])): ?> ⭐<?php endif; ?>
            </td>
            <td>L. <?= number_format((float)$p['precio'], 2) ?></td>
            <td><?= e($p['categoria']) ?></td>
            <td><?= e($p['ocasion']) ?></td>
            <td><span class="badge <?= !empty($p['disponible']) ? 'si' : 'no' ?>"><?= !empty($p['disponible']) ? 'Disponible' : 'Agotado' ?></span></td>
            <td>
              <div class="acciones-fila">
                <a class="btn mini secundario" href="productos.php?editar=<?= urlencode($p['id']) ?>">Editar</a>
                <form class="inline" method="post" action="productos.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="toggle">
                  <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                  <button type="submit" class="btn mini secundario"><?= !empty($p['disponible']) ? 'Marcar agotado' : 'Marcar disponible' ?></button>
                </form>
                <form class="inline" method="post" action="productos.php" onsubmit="return confirm('¿Eliminar «<?= e($p['nombre']) ?>» del catálogo?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                  <button type="submit" class="btn mini peligro">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_footer(); ?>
