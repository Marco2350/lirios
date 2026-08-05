<?php
/**
 * CRUD del catálogo de productos, sobre la base de datos MySQL
 * (tablas productos / producto_variantes / subcategorias / categorias).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

const TALLAS = ['S', 'M', 'L', 'XL'];
const INCLUYE_POR_DEFECTO = 'Tarjeta personalizada y empaque premium';

/* Subcategorías agrupadas por categoría, para el <select> del formulario */
$subcategorias = db()->query(
    'SELECT s.id, s.nombre, c.nombre AS categoria_nombre, c.orden AS cat_orden, s.orden
     FROM subcategorias s JOIN categorias c ON c.id = s.categoria_id
     ORDER BY c.orden, s.orden'
)->fetchAll();
$subcategoriasPorCategoria = [];
foreach ($subcategorias as $s) {
    $subcategoriasPorCategoria[$s['categoria_nombre']][] = $s;
}

/* Fotos disponibles para el select de imagen */
$fotos = is_dir(IMAGENES_DIR)
    ? array_map('basename', glob(IMAGENES_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE))
    : [];

/* ---------- Acciones POST ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $idOriginal = (int) ($_POST['id_original'] ?? 0);
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 150);
        $descripcionCorta = limpiar_texto($_POST['descripcion_corta'] ?? '', 255);
        $descripcion = limpiar_texto($_POST['descripcion'] ?? '', 2000);
        $subcategoriaId = (int) ($_POST['subcategoria_id'] ?? 0);
        $incluye = limpiar_texto($_POST['incluye'] ?? '', 255) ?: INCLUYE_POR_DEFECTO;
        $imagenSeleccionada = basename(limpiar_texto($_POST['imagen'] ?? '', 150));
        $entregaDisponible = isset($_POST['entrega_disponible']) ? 1 : 0;
        $retiroDisponible = isset($_POST['retiro_tienda_disponible']) ? 1 : 0;
        $disponible = isset($_POST['disponible']) ? 1 : 0;
        $destacado = isset($_POST['destacado']) ? 1 : 0;

        $variantes = [];
        foreach (TALLAS as $talla) {
            $precio = limpiar_precio($_POST['precio_' . $talla] ?? '');
            if ($precio > 0) {
                $variantes[$talla] = [
                    'precio' => $precio,
                    'disponible' => isset($_POST['disp_' . $talla]) ? 1 : 0,
                ];
            }
        }

        if ($nombre === '' || $descripcionCorta === '') {
            flash('error', 'El nombre y la descripción corta son obligatorios.');
        } elseif ($subcategoriaId <= 0) {
            flash('error', 'Elige una subcategoría para el producto.');
        } elseif (!$variantes) {
            flash('error', 'Agrega al menos una talla con precio mayor que cero.');
        } elseif ($imagenSeleccionada !== '' && !in_array($imagenSeleccionada, $fotos, true)) {
            flash('error', 'La foto seleccionada ya no está disponible. Elige otra o sube una nueva.');
        } else {
            /* Resolver la foto: 1) archivo subido ahora mismo, 2) foto ya
               existente elegida en el select, 3) si se edita y no se tocó
               nada, mantener la foto que ya tenía el producto. */
            $rutaImagen = null;
            $errorImagen = null;

            if (!empty($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $resultado = guardar_imagen_subida($_FILES['imagen_archivo']);
                if ($resultado['ok']) {
                    $rutaImagen = 'images/productos/' . $resultado['nombre'];
                } else {
                    $errorImagen = $resultado['error'];
                }
            } elseif ($imagenSeleccionada !== '') {
                $rutaImagen = 'images/productos/' . $imagenSeleccionada;
            } elseif ($idOriginal > 0) {
                $actual = db()->prepare('SELECT imagen FROM productos WHERE id = ?');
                $actual->execute([$idOriginal]);
                $rutaImagen = $actual->fetchColumn() ?: null;
            }

            if ($errorImagen) {
                flash('error', $errorImagen);
                header('Location: productos.php');
                exit;
            }

            db()->beginTransaction();
            try {
                if ($idOriginal > 0) {
                    $stmt = db()->prepare(
                        'UPDATE productos SET subcategoria_id=:sub, nombre=:nombre,
                            descripcion_corta=:corta, descripcion=:desc, imagen=:imagen,
                            incluye=:incluye, entrega_disponible=:entrega,
                            retiro_tienda_disponible=:retiro, disponible=:disp, destacado=:dest
                         WHERE id=:id'
                    );
                    $stmt->execute([
                        'sub' => $subcategoriaId, 'nombre' => $nombre, 'corta' => $descripcionCorta,
                        'desc' => $descripcion, 'imagen' => $rutaImagen, 'incluye' => $incluye,
                        'entrega' => $entregaDisponible, 'retiro' => $retiroDisponible,
                        'disp' => $disponible, 'dest' => $destacado, 'id' => $idOriginal,
                    ]);
                    $productoId = $idOriginal;
                    $msj = 'Producto actualizado.';
                } else {
                    $slug = slug_unico('productos', slugify($nombre));
                    $stmt = db()->prepare(
                        'INSERT INTO productos
                            (subcategoria_id, slug, nombre, descripcion_corta, descripcion, imagen,
                             incluye, entrega_disponible, retiro_tienda_disponible, disponible, destacado)
                         VALUES (:sub, :slug, :nombre, :corta, :desc, :imagen, :incluye, :entrega, :retiro, :disp, :dest)'
                    );
                    $stmt->execute([
                        'sub' => $subcategoriaId, 'slug' => $slug, 'nombre' => $nombre,
                        'corta' => $descripcionCorta, 'desc' => $descripcion, 'imagen' => $rutaImagen,
                        'incluye' => $incluye, 'entrega' => $entregaDisponible, 'retiro' => $retiroDisponible,
                        'disp' => $disponible, 'dest' => $destacado,
                    ]);
                    $productoId = (int) db()->lastInsertId();
                    $msj = 'Producto agregado al catálogo.';
                }

                /* Variantes: upsert las que tienen precio, borrar el resto */
                $upsert = db()->prepare(
                    'INSERT INTO producto_variantes (producto_id, talla, precio, disponible, orden)
                     VALUES (:pid, :talla, :precio, :disp, :orden)
                     ON DUPLICATE KEY UPDATE precio = VALUES(precio), disponible = VALUES(disponible)'
                );
                $borrar = db()->prepare('DELETE FROM producto_variantes WHERE producto_id = :pid AND talla = :talla');
                foreach (TALLAS as $orden => $talla) {
                    if (isset($variantes[$talla])) {
                        $upsert->execute([
                            'pid' => $productoId, 'talla' => $talla,
                            'precio' => $variantes[$talla]['precio'],
                            'disp' => $variantes[$talla]['disponible'],
                            'orden' => $orden,
                        ]);
                    } else {
                        $borrar->execute(['pid' => $productoId, 'talla' => $talla]);
                    }
                }

                db()->commit();
                flash('ok', $msj);
            } catch (Throwable $e) {
                db()->rollBack();
                error_log('[lirios] Error al guardar producto: ' . $e->getMessage());
                flash('error', 'No se pudo guardar el producto. Intenta de nuevo; si el problema continúa, contacta a soporte técnico.');
            }
        }
        header('Location: productos.php');
        exit;
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
        flash('ok', 'Producto eliminado.');
        header('Location: productos.php');
        exit;
    }

    if ($accion === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE productos SET disponible = NOT disponible WHERE id = ?')->execute([$id]);
        header('Location: productos.php');
        exit;
    }
}

/* ---------- Producto en edición (?editar=id) ---------- */

$editando = null;
$variantesEditando = [];
if (!empty($_GET['editar'])) {
    $stmt = db()->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch() ?: null;
    if ($editando) {
        $vStmt = db()->prepare('SELECT talla, precio, disponible FROM producto_variantes WHERE producto_id = ?');
        $vStmt->execute([$editando['id']]);
        foreach ($vStmt->fetchAll() as $v) {
            $variantesEditando[$v['talla']] = $v;
        }
    }
}

/* ---------- Catálogo actual (para la tabla) ---------- */

$productos = db()->query(
    'SELECT p.*, c.nombre AS categoria_nombre, s.nombre AS subcategoria_nombre
     FROM productos p
     JOIN subcategorias s ON s.id = p.subcategoria_id
     JOIN categorias c ON c.id = s.categoria_id
     ORDER BY p.nombre'
)->fetchAll();

$variantesPorProducto = [];
foreach (db()->query('SELECT producto_id, talla, precio FROM producto_variantes ORDER BY producto_id') as $v) {
    $variantesPorProducto[$v['producto_id']][] = $v;
}

admin_header('Productos del catálogo', 'productos.php');
?>

<p class="intro">Cada producto necesita al menos una talla con precio para poder mostrarse en el sitio. Los cambios que guardes aquí se ven en el catálogo público de inmediato.</p>

<div class="panel">
  <h2><?= $editando ? 'Editar producto: ' . e($editando['nombre']) : 'Agregar producto nuevo' ?></h2>
  <form method="post" action="productos.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id_original" value="<?= e((string)($editando['id'] ?? '')) ?>">

    <div class="form-grid">
      <div>
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" required maxlength="150" value="<?= e($editando['nombre'] ?? '') ?>">
      </div>
      <div>
        <label for="subcategoria_id">Categoría / Subcategoría *</label>
        <select id="subcategoria_id" name="subcategoria_id" required>
          <option value="">— Elegir —</option>
          <?php foreach ($subcategoriasPorCategoria as $catNombre => $subs): ?>
            <optgroup label="<?= e($catNombre) ?>">
              <?php foreach ($subs as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int)($editando['subcategoria_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full">
        <label for="imagen_archivo">Foto del producto</label>
        <?php if (!empty($editando['imagen'])): ?>
          <div class="imagen-actual">
            <img src="../<?= e($editando['imagen']) ?>" alt="">
            <span>Foto actual — sube una nueva para reemplazarla</span>
          </div>
        <?php endif; ?>
        <input type="file" id="imagen_archivo" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <small>JPG, PNG o WebP · máx. 3 MB · se convierte a WebP automáticamente.</small>
      </div>
      <div class="full">
        <label for="imagen">O elige una foto ya subida (opcional)</label>
        <select id="imagen" name="imagen">
          <option value="">— No cambiar / sin foto —</option>
          <?php $imgActual = basename($editando['imagen'] ?? ''); ?>
          <?php foreach ($fotos as $f): ?>
            <option value="<?= e($f) ?>" <?= $imgActual === $f ? 'selected' : '' ?>><?= e($f) ?></option>
          <?php endforeach; ?>
        </select>
        <small><a href="subir-imagen.php">Ver galería de fotos ↗</a></small>
      </div>
      <div class="full">
        <label for="descripcion_corta">Descripción corta * <small>(flores principales, se muestra en la tarjeta)</small></label>
        <input type="text" id="descripcion_corta" name="descripcion_corta" required maxlength="255" value="<?= e($editando['descripcion_corta'] ?? '') ?>">
      </div>
      <div class="full">
        <label for="descripcion">Descripción completa</label>
        <textarea id="descripcion" name="descripcion" maxlength="2000"><?= e($editando['descripcion'] ?? '') ?></textarea>
      </div>
      <div class="full">
        <label for="incluye">Incluye</label>
        <input type="text" id="incluye" name="incluye" maxlength="255" value="<?= e($editando['incluye'] ?? INCLUYE_POR_DEFECTO) ?>">
      </div>

      <div class="full">
        <label>Tallas y precios * <small>(deja el precio en blanco en las tallas que no apliquen)</small></label>
        <div class="talla-grid">
          <?php foreach (TALLAS as $talla): $v = $variantesEditando[$talla] ?? null; ?>
            <div class="talla-box">
              <strong><?= e($talla) ?></strong>
              <input type="number" name="precio_<?= e($talla) ?>" min="0" step="0.01" placeholder="L." value="<?= $v ? e((string)$v['precio']) : '' ?>">
              <label class="check-row"><input type="checkbox" name="disp_<?= e($talla) ?>" <?= (!$v || !empty($v['disponible'])) ? 'checked' : '' ?>> Disponible</label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label>&nbsp;</label>
        <label class="check-row"><input type="checkbox" name="entrega_disponible" <?= (!$editando || !empty($editando['entrega_disponible'])) ? 'checked' : '' ?>> Entrega a domicilio</label>
        <label class="check-row"><input type="checkbox" name="retiro_tienda_disponible" <?= (!$editando || !empty($editando['retiro_tienda_disponible'])) ? 'checked' : '' ?>> Retiro en tienda</label>
      </div>
      <div>
        <label>&nbsp;</label>
        <label class="check-row"><input type="checkbox" name="disponible" <?= (!$editando || !empty($editando['disponible'])) ? 'checked' : '' ?>> Disponible en el catálogo</label>
        <label class="check-row"><input type="checkbox" name="destacado" <?= !empty($editando['destacado']) ? 'checked' : '' ?>> Destacado en la portada</label>
      </div>
    </div>

    <button type="submit" class="btn"><?= $editando ? 'Guardar cambios' : 'Agregar producto' ?></button>
    <?php if ($editando): ?>
      <a class="btn secundario" href="productos.php">Cancelar edición</a>
    <?php endif; ?>
  </form>
</div>

<div class="panel">
  <h2>Catálogo actual (<?= count($productos) ?>)</h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr>
          <th>Foto</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th>Subcategoría</th>
          <th>Tallas</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$productos): ?>
          <tr><td colspan="7">Aún no hay productos. Agrega el primero con el formulario de arriba.</td></tr>
        <?php endif; ?>
        <?php foreach ($productos as $p): ?>
          <tr>
            <td><?php if (!empty($p['imagen'])): ?><img class="mini" src="../<?= e($p['imagen']) ?>" alt=""><?php endif; ?></td>
            <td>
              <strong><?= e($p['nombre']) ?></strong>
              <?php if (!empty($p['destacado'])): ?> <span class="badge destacado">Destacado</span><?php endif; ?>
            </td>
            <td><?= e($p['categoria_nombre']) ?></td>
            <td><?= e($p['subcategoria_nombre']) ?></td>
            <td>
              <?php foreach ($variantesPorProducto[$p['id']] ?? [] as $v): ?>
                <span class="badge si"><?= e($v['talla']) ?> L.<?= number_format((float)$v['precio'], 0) ?></span>
              <?php endforeach; ?>
            </td>
            <td><span class="badge <?= !empty($p['disponible']) ? 'si' : 'no' ?>"><?= !empty($p['disponible']) ? 'Disponible' : 'Agotado' ?></span></td>
            <td>
              <div class="acciones-fila">
                <a class="btn mini secundario" href="productos.php?editar=<?= (int) $p['id'] ?>">Editar</a>
                <form class="inline" method="post" action="productos.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn mini secundario"><?= !empty($p['disponible']) ? 'Marcar agotado' : 'Marcar disponible' ?></button>
                </form>
                <form class="inline" method="post" action="productos.php" onsubmit="return confirm('¿Eliminar «<?= e($p['nombre']) ?>» del catálogo?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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
