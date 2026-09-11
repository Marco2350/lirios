<?php
/**
 * CRUD de la taxonomía del catálogo: categorías y subcategorías
 * (tablas categorias / subcategorias en MySQL).
 *
 * Nota de estructura: los <form> viven FUERA de la tabla y los
 * inputs de cada fila se asocian con el atributo form="id",
 * porque un <form> dentro de <tr>/<tbody> es HTML inválido.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

/* ---------- Acciones POST ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_categoria') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);
        $icono = limpiar_texto($_POST['icono'] ?? '', 10);
        $orden = (int) ($_POST['orden'] ?? 0);
        $visible = isset($_POST['visible']) ? 1 : 0;
        $portadaSeleccionada = basename(limpiar_texto($_POST['imagen_portada_select'] ?? '', 150));

        $fotosPortadas = is_dir(IMAGENES_CATEGORIAS_DIR)
            ? array_map('basename', glob(IMAGENES_CATEGORIAS_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE))
            : [];

        if ($nombre === '') {
            flash('error', 'El nombre de la categoría es obligatorio.');
        } elseif ($portadaSeleccionada !== '' && !in_array($portadaSeleccionada, $fotosPortadas, true)) {
            flash('error', 'La portada seleccionada ya no está disponible. Elige otra o sube una nueva.');
        } else {
            /* Resolver la portada: 1) archivo subido ahora mismo, 2) foto ya
               existente elegida en el select, 3) si se edita y no se tocó
               nada, mantener la portada que ya tenía la categoría. */
            $rutaPortada = null;
            $errorImagen = null;

            if (!empty($_FILES['imagen_portada']) && $_FILES['imagen_portada']['error'] !== UPLOAD_ERR_NO_FILE) {
                $resultado = guardar_imagen_subida($_FILES['imagen_portada'], IMAGENES_CATEGORIAS_DIR);
                if ($resultado['ok']) {
                    $rutaPortada = 'images/categorias/' . $resultado['nombre'];
                } else {
                    $errorImagen = $resultado['error'];
                }
            } elseif ($portadaSeleccionada !== '') {
                $rutaPortada = 'images/categorias/' . $portadaSeleccionada;
            } elseif ($id > 0) {
                $actual = db()->prepare('SELECT imagen_portada FROM categorias WHERE id = ?');
                $actual->execute([$id]);
                $rutaPortada = $actual->fetchColumn() ?: null;
            }

            if ($errorImagen) {
                flash('error', $errorImagen);
            } elseif ($id > 0) {
                db()->prepare('UPDATE categorias SET nombre=:nombre, icono=:icono, orden=:orden, visible=:visible, imagen_portada=:portada WHERE id=:id')
                    ->execute(['nombre' => $nombre, 'icono' => $icono, 'orden' => $orden, 'visible' => $visible, 'portada' => $rutaPortada, 'id' => $id]);
                flash('ok', 'Categoría actualizada.');
            } else {
                db()->beginTransaction();
                try {
                    $slug = slug_unico('categorias', slugify($nombre));
                    db()->prepare('INSERT INTO categorias (slug, nombre, icono, orden, visible, imagen_portada) VALUES (:slug, :nombre, :icono, :orden, :visible, :portada)')
                        ->execute(['slug' => $slug, 'nombre' => $nombre, 'icono' => $icono, 'orden' => $orden, 'visible' => $visible, 'portada' => $rutaPortada]);
                    $categoriaId = (int) db()->lastInsertId();

                    /* Toda categoría necesita al menos una subcategoría para poder
                       asignarle productos (productos.subcategoria_id es la FK real,
                       no categoria_id) — sin esto, la categoría nueva no aparece en
                       el <select> de "Agregar producto nuevo" de productos.php hasta
                       que alguien cree una subcategoría a mano. Se crea "General"
                       automáticamente, mismo criterio ya usado en el resto del
                       catálogo (ver nota 2026-09-01 en el CLAUDE.md raíz, sección 6.1). */
                    $slugSub = slug_unico('subcategorias', 'general');
                    db()->prepare('INSERT INTO subcategorias (categoria_id, slug, nombre, orden) VALUES (:cat, :slug, :nombre, 0)')
                        ->execute(['cat' => $categoriaId, 'slug' => $slugSub, 'nombre' => 'General']);

                    db()->commit();
                    flash('ok', 'Categoría agregada.');
                } catch (Throwable $e) {
                    db()->rollBack();
                    error_log('[lirios] Error al crear categoría: ' . $e->getMessage());
                    flash('error', 'No se pudo agregar la categoría. Intenta de nuevo; si el problema continúa, contacta a soporte técnico.');
                }
            }
        }
        header('Location: categorias.php');
        exit;
    }

    if ($accion === 'eliminar_categoria') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            db()->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
            flash('ok', 'Categoría eliminada.');
        } catch (PDOException $e) {
            flash('error', 'No puedes eliminar esta categoría: todavía tiene productos en alguna de sus subcategorías. Muévelos o elimínalos primero.');
        }
        header('Location: categorias.php');
        exit;
    }

    if ($accion === 'guardar_subcategoria') {
        $id = (int) ($_POST['id'] ?? 0);
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);
        $orden = (int) ($_POST['orden'] ?? 0);

        if ($nombre === '' || $categoriaId <= 0) {
            flash('error', 'Elige una categoría y escribe un nombre para la subcategoría.');
        } elseif ($id > 0) {
            db()->prepare('UPDATE subcategorias SET categoria_id=:cat, nombre=:nombre, orden=:orden WHERE id=:id')
                ->execute(['cat' => $categoriaId, 'nombre' => $nombre, 'orden' => $orden, 'id' => $id]);
            flash('ok', 'Subcategoría actualizada.');
        } else {
            $slug = slug_unico('subcategorias', slugify($nombre));
            db()->prepare('INSERT INTO subcategorias (categoria_id, slug, nombre, orden) VALUES (:cat, :slug, :nombre, :orden)')
                ->execute(['cat' => $categoriaId, 'slug' => $slug, 'nombre' => $nombre, 'orden' => $orden]);
            flash('ok', 'Subcategoría agregada.');
        }
        header('Location: categorias.php');
        exit;
    }

    if ($accion === 'eliminar_subcategoria') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            db()->prepare('DELETE FROM subcategorias WHERE id = ?')->execute([$id]);
            flash('ok', 'Subcategoría eliminada.');
        } catch (PDOException $e) {
            flash('error', 'No puedes eliminar esta subcategoría: todavía tiene productos asociados. Muévelos o elimínalos primero.');
        }
        header('Location: categorias.php');
        exit;
    }
}

/* ---------- Datos para la página ---------- */

$categorias = db()->query('SELECT * FROM categorias ORDER BY orden, nombre')->fetchAll();

/* Portadas ya subidas, para el select "elegir foto ya subida" de cada categoría */
$fotosPortadas = is_dir(IMAGENES_CATEGORIAS_DIR)
    ? array_map('basename', glob(IMAGENES_CATEGORIAS_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE))
    : [];

$subcategorias = db()->query(
    'SELECT s.*, c.nombre AS categoria_nombre
     FROM subcategorias s JOIN categorias c ON c.id = s.categoria_id
     ORDER BY c.orden, s.orden, s.nombre'
)->fetchAll();

$conteoSubs = array_count_values(array_column($subcategorias, 'categoria_nombre'));

admin_header('Categorías y subcategorías', 'categorias.php');
?>

<p class="intro">Organiza la vitrina del catálogo en dos niveles: <strong>categorías</strong> (ej. "Ramos Florales") y sus <strong>subcategorías</strong> (ej. "Rosas"). Cada producto pertenece a una sola subcategoría. Desmarca "Visible" para ocultar una categoría del menú y del home sin borrarla — sigue accesible por su enlace directo y conserva sus productos.</p>

<div class="panel">
  <h2>Categorías (<?= count($categorias) ?>)</h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr><th>Portada</th><th>Ícono</th><th>Nombre</th><th>Orden</th><th>Visible</th><th>Subcategorías</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($categorias as $c): $fid = 'cat-' . $c['id']; $mid = 'modal-portada-' . $c['id']; ?>
        <tr>
          <td>
            <div class="portada-cell">
              <?php if (!empty($c['imagen_portada'])): ?>
                <img class="mini" src="../<?= e($c['imagen_portada']) ?>" alt="">
              <?php else: ?>
                <span class="portada-vacia">Sin portada</span>
              <?php endif; ?>
              <button type="button" class="btn mini secundario" data-abrir-modal="<?= e($mid) ?>">Cambiar foto</button>
            </div>
          </td>
          <td><input type="text" name="icono" maxlength="10" style="width:4.5rem" form="<?= e($fid) ?>" value="<?= e($c['icono'] ?? '') ?>"></td>
          <td><input type="text" name="nombre" required maxlength="100" form="<?= e($fid) ?>" value="<?= e($c['nombre']) ?>"></td>
          <td><input type="number" name="orden" style="width:5rem" form="<?= e($fid) ?>" value="<?= (int) $c['orden'] ?>"></td>
          <td><input type="checkbox" name="visible" form="<?= e($fid) ?>" <?= !empty($c['visible']) ? 'checked' : '' ?>></td>
          <td><?= (int) ($conteoSubs[$c['nombre']] ?? 0) ?></td>
          <td>
            <div class="acciones-fila">
              <button type="submit" class="btn mini" form="<?= e($fid) ?>">Guardar</button>
              <button type="button" class="btn mini peligro"
                data-confirmar-eliminar="del-<?= e($fid) ?>"
                data-confirmar-mensaje="¿Eliminar la categoría «<?= e($c['nombre']) ?>»? Solo se puede si no tiene productos.">Eliminar</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Fila para agregar categoría nueva -->
        <tr>
          <td>
            <div class="portada-cell">
              <span class="portada-vacia">Sin portada</span>
              <button type="button" class="btn mini secundario" data-abrir-modal="modal-portada-nueva">Elegir foto</button>
            </div>
          </td>
          <td><input type="text" name="icono" maxlength="10" style="width:4.5rem" form="cat-nueva" placeholder="—"></td>
          <td><input type="text" name="nombre" required maxlength="100" form="cat-nueva" placeholder="Nueva categoría…"></td>
          <td><input type="number" name="orden" style="width:5rem" form="cat-nueva" value="0"></td>
          <td><input type="checkbox" name="visible" form="cat-nueva" checked></td>
          <td>—</td>
          <td><button type="submit" class="btn mini secundario" form="cat-nueva">+ Agregar</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Formularios de categorías (fuera de la tabla, asociados por form="id") -->
  <?php foreach ($categorias as $c): $fid = 'cat-' . $c['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="categorias.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar_categoria">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    </form>
    <form id="del-<?= e($fid) ?>" method="post" action="categorias.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar_categoria">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    </form>
  <?php endforeach; ?>
  <form id="cat-nueva" method="post" action="categorias.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar_categoria">
    <input type="hidden" name="id" value="0">
  </form>

  <!-- Modales de portada (uno por categoría + uno para la fila nueva). Los
       campos de imagen se asocian con form="cat-N", así que "Guardar" desde
       el modal envía el mismo <form> de la fila (nombre/orden/visible ya
       en ese form vía el mismo atributo). -->
  <?php foreach ($categorias as $c): $fid = 'cat-' . $c['id']; $mid = 'modal-portada-' . $c['id']; ?>
    <dialog class="modal-portada" id="<?= e($mid) ?>">
      <h3>Portada de «<?= e($c['nombre']) ?>»</h3>
      <?php if (!empty($c['imagen_portada'])): ?>
        <img class="modal-portada-preview" src="../<?= e($c['imagen_portada']) ?>" alt="">
      <?php endif; ?>
      <label for="<?= e($mid) ?>-archivo">Subir foto nueva</label>
      <input type="file" id="<?= e($mid) ?>-archivo" name="imagen_portada" form="<?= e($fid) ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <small>JPG, PNG o WebP · máx. 3 MB · se convierte a WebP automáticamente.</small>
      <label for="<?= e($mid) ?>-select">O elegir una ya subida</label>
      <select id="<?= e($mid) ?>-select" name="imagen_portada_select" form="<?= e($fid) ?>">
        <option value="">— No cambiar —</option>
        <?php $portadaActual = basename($c['imagen_portada'] ?? ''); ?>
        <?php foreach ($fotosPortadas as $f): ?>
          <option value="<?= e($f) ?>" <?= $portadaActual === $f ? 'selected' : '' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="modal-portada-acciones">
        <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
        <button type="submit" class="btn mini" form="<?= e($fid) ?>">Guardar</button>
      </div>
    </dialog>
  <?php endforeach; ?>

  <dialog class="modal-portada" id="modal-portada-nueva">
    <h3>Portada de la categoría nueva</h3>
    <label for="modal-portada-nueva-archivo">Subir foto nueva</label>
    <input type="file" id="modal-portada-nueva-archivo" name="imagen_portada" form="cat-nueva" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
    <small>JPG, PNG o WebP · máx. 3 MB · se convierte a WebP automáticamente.</small>
    <label for="modal-portada-nueva-select">O elegir una ya subida</label>
    <select id="modal-portada-nueva-select" name="imagen_portada_select" form="cat-nueva">
      <option value="">— Sin portada —</option>
      <?php foreach ($fotosPortadas as $f): ?>
        <option value="<?= e($f) ?>"><?= e($f) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini secundario" form="cat-nueva">+ Agregar categoría</button>
    </div>
  </dialog>
</div>

<div class="panel">
  <h2>Subcategorías (<?= count($subcategorias) ?>)</h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr><th>Categoría</th><th>Nombre</th><th>Orden</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($subcategorias as $s): $fid = 'sub-' . $s['id']; ?>
        <tr>
          <td>
            <select name="categoria_id" form="<?= e($fid) ?>">
              <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int)$c['id'] === (int)$s['categoria_id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="text" name="nombre" required maxlength="100" form="<?= e($fid) ?>" value="<?= e($s['nombre']) ?>"></td>
          <td><input type="number" name="orden" style="width:5rem" form="<?= e($fid) ?>" value="<?= (int) $s['orden'] ?>"></td>
          <td>
            <div class="acciones-fila">
              <button type="submit" class="btn mini" form="<?= e($fid) ?>">Guardar</button>
              <button type="button" class="btn mini peligro"
                data-confirmar-eliminar="del-<?= e($fid) ?>"
                data-confirmar-mensaje="¿Eliminar la subcategoría «<?= e($s['nombre']) ?>»? Solo se puede si no tiene productos.">Eliminar</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Fila para agregar subcategoría nueva -->
        <tr>
          <td>
            <select name="categoria_id" form="sub-nueva">
              <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="text" name="nombre" required maxlength="100" form="sub-nueva" placeholder="Nueva subcategoría…"></td>
          <td><input type="number" name="orden" style="width:5rem" form="sub-nueva" value="0"></td>
          <td><button type="submit" class="btn mini secundario" form="sub-nueva">+ Agregar</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Formularios de subcategorías (fuera de la tabla, asociados por form="id") -->
  <?php foreach ($subcategorias as $s): $fid = 'sub-' . $s['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="categorias.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar_subcategoria">
      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    </form>
    <form id="del-<?= e($fid) ?>" method="post" action="categorias.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar_subcategoria">
      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    </form>
  <?php endforeach; ?>
  <form id="sub-nueva" method="post" action="categorias.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar_subcategoria">
    <input type="hidden" name="id" value="0">
  </form>
</div>

<?php admin_footer(); ?>
