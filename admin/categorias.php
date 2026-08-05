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

        if ($nombre === '') {
            flash('error', 'El nombre de la categoría es obligatorio.');
        } elseif ($id > 0) {
            db()->prepare('UPDATE categorias SET nombre=:nombre, icono=:icono, orden=:orden WHERE id=:id')
                ->execute(['nombre' => $nombre, 'icono' => $icono, 'orden' => $orden, 'id' => $id]);
            flash('ok', 'Categoría actualizada.');
        } else {
            $slug = slug_unico('categorias', slugify($nombre));
            db()->prepare('INSERT INTO categorias (slug, nombre, icono, orden) VALUES (:slug, :nombre, :icono, :orden)')
                ->execute(['slug' => $slug, 'nombre' => $nombre, 'icono' => $icono, 'orden' => $orden]);
            flash('ok', 'Categoría agregada.');
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

$subcategorias = db()->query(
    'SELECT s.*, c.nombre AS categoria_nombre
     FROM subcategorias s JOIN categorias c ON c.id = s.categoria_id
     ORDER BY c.orden, s.orden, s.nombre'
)->fetchAll();

$conteoSubs = array_count_values(array_column($subcategorias, 'categoria_nombre'));

admin_header('Categorías y subcategorías', 'categorias.php');
?>

<p class="intro">Organiza la vitrina del catálogo en dos niveles: <strong>categorías</strong> (ej. "Ramos Florales") y sus <strong>subcategorías</strong> (ej. "Rosas"). Cada producto pertenece a una sola subcategoría.</p>

<div class="panel">
  <h2>Categorías (<?= count($categorias) ?>)</h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr><th>Ícono</th><th>Nombre</th><th>Orden</th><th>Subcategorías</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($categorias as $c): $fid = 'cat-' . $c['id']; ?>
        <tr>
          <td><input type="text" name="icono" maxlength="10" style="width:4.5rem" form="<?= e($fid) ?>" value="<?= e($c['icono'] ?? '') ?>"></td>
          <td><input type="text" name="nombre" required maxlength="100" form="<?= e($fid) ?>" value="<?= e($c['nombre']) ?>"></td>
          <td><input type="number" name="orden" style="width:5rem" form="<?= e($fid) ?>" value="<?= (int) $c['orden'] ?>"></td>
          <td><?= (int) ($conteoSubs[$c['nombre']] ?? 0) ?></td>
          <td>
            <div class="acciones-fila">
              <button type="submit" class="btn mini" form="<?= e($fid) ?>">Guardar</button>
              <button type="submit" class="btn mini peligro" form="del-<?= e($fid) ?>">Eliminar</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Fila para agregar categoría nueva -->
        <tr>
          <td><input type="text" name="icono" maxlength="10" style="width:4.5rem" form="cat-nueva" placeholder="—"></td>
          <td><input type="text" name="nombre" required maxlength="100" form="cat-nueva" placeholder="Nueva categoría…"></td>
          <td><input type="number" name="orden" style="width:5rem" form="cat-nueva" value="0"></td>
          <td>—</td>
          <td><button type="submit" class="btn mini secundario" form="cat-nueva">+ Agregar</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Formularios de categorías (fuera de la tabla, asociados por form="id") -->
  <?php foreach ($categorias as $c): $fid = 'cat-' . $c['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="categorias.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar_categoria">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    </form>
    <form id="del-<?= e($fid) ?>" method="post" action="categorias.php" onsubmit="return confirm('¿Eliminar la categoría «<?= e($c['nombre']) ?>»? Solo se puede si no tiene productos.');">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar_categoria">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    </form>
  <?php endforeach; ?>
  <form id="cat-nueva" method="post" action="categorias.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar_categoria">
    <input type="hidden" name="id" value="0">
  </form>
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
              <button type="submit" class="btn mini peligro" form="del-<?= e($fid) ?>">Eliminar</button>
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
    <form id="del-<?= e($fid) ?>" method="post" action="categorias.php" onsubmit="return confirm('¿Eliminar la subcategoría «<?= e($s['nombre']) ?>»? Solo se puede si no tiene productos.');">
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
