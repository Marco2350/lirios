<?php
/**
 * CRUD del catálogo de productos, sobre la base de datos MySQL
 * (tablas productos / subcategorias / categorias).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

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
        $subcategoriaId = (int) ($_POST['subcategoria_id'] ?? 0);
        $imagenSeleccionada = basename(limpiar_texto($_POST['imagen'] ?? '', 150));
        $precio = limpiar_precio($_POST['precio'] ?? '');

        if ($subcategoriaId <= 0) {
            flash('error', 'Elige una subcategoría para el producto.');
        } elseif ($precio <= 0) {
            flash('error', 'El precio debe ser mayor que cero.');
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
                    /* Solo se editan subcategoría, imagen y precio desde este
                       formulario simplificado — nombre, descripciones, incluye
                       y los indicadores de entrega/destacado no se tocan. */
                    $stmt = db()->prepare(
                        'UPDATE productos SET subcategoria_id=:sub, imagen=:imagen, precio=:precio
                         WHERE id=:id'
                    );
                    $stmt->execute([
                        'sub' => $subcategoriaId, 'imagen' => $rutaImagen,
                        'precio' => $precio, 'id' => $idOriginal,
                    ]);
                    $msj = 'Producto actualizado.';
                } else {
                    /* Nombre automático ("Subcategoría N") porque el formulario ya
                       no pide nombre — el catálogo público solo muestra el precio,
                       este nombre es interno (URL, título de la ficha, listas del
                       panel) y se puede corregir después editando el producto. */
                    $subNombreStmt = db()->prepare('SELECT nombre FROM subcategorias WHERE id = ?');
                    $subNombreStmt->execute([$subcategoriaId]);
                    $subNombre = $subNombreStmt->fetchColumn() ?: 'Producto';

                    $conteoStmt = db()->prepare('SELECT COUNT(*) FROM productos WHERE subcategoria_id = ?');
                    $conteoStmt->execute([$subcategoriaId]);
                    $nombre = $subNombre . ' ' . ((int) $conteoStmt->fetchColumn() + 1);

                    $slug = slug_unico('productos', slugify($nombre));
                    $stmt = db()->prepare(
                        'INSERT INTO productos
                            (subcategoria_id, slug, nombre, descripcion_corta, imagen,
                             incluye, precio, entrega_disponible, retiro_tienda_disponible, disponible, destacado)
                         VALUES (:sub, :slug, :nombre, :corta, :imagen, :incluye, :precio, 1, 1, 1, 0)'
                    );
                    $stmt->execute([
                        'sub' => $subcategoriaId, 'slug' => $slug, 'nombre' => $nombre,
                        'corta' => $nombre, 'imagen' => $rutaImagen,
                        'incluye' => INCLUYE_POR_DEFECTO, 'precio' => $precio,
                    ]);
                    $msj = 'Producto agregado al catálogo.';
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

    /* Carga rápida deshabilitada a pedido de la clienta (2026-08-26) — el
       código se deja intacto (comentado, no borrado) por si se necesita
       reactivar más adelante. Ver también el bloque HTML del formulario,
       envuelto en "<?php if (false): ?>" más abajo. */
    if (false && $accion === 'guardar_rapido') {
        $subcategoriaId = (int) ($_POST['subcategoria_id_rapido'] ?? 0);
        $nombresPost = $_POST['nombre_rapido'] ?? [];
        $preciosPost = $_POST['precio_rapido'] ?? [];
        /* $_FILES para un input name="imagen_rapida[]" llega como arreglos
           paralelos (['name'][i], ['tmp_name'][i], ['error'][i]...), no
           como una lista de archivos — hay que reconstruir cada fila. */
        $imagenesPost = $_FILES['imagen_rapida'] ?? null;

        if ($subcategoriaId <= 0) {
            flash('error', 'Elige una categoría/subcategoría para los productos de la carga rápida.');
            header('Location: productos.php');
            exit;
        }

        $filas = [];
        $errores = [];
        foreach ($nombresPost as $i => $nombreCrudo) {
            $nombre = limpiar_texto((string) $nombreCrudo, 150);
            $precio = limpiar_precio($preciosPost[$i] ?? '');
            if ($nombre === '' && $precio <= 0) {
                continue; // fila vacía, se ignora
            }
            if ($nombre === '' || $precio <= 0) {
                $errores[] = 'Fila ' . ($i + 1) . ': falta el nombre o el precio.';
                continue;
            }

            $rutaImagen = null;
            if ($imagenesPost && ($imagenesPost['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $archivoFila = [
                    'name' => $imagenesPost['name'][$i],
                    'type' => $imagenesPost['type'][$i],
                    'tmp_name' => $imagenesPost['tmp_name'][$i],
                    'error' => $imagenesPost['error'][$i],
                    'size' => $imagenesPost['size'][$i],
                ];
                $resultado = guardar_imagen_subida($archivoFila);
                if ($resultado['ok']) {
                    $rutaImagen = 'images/productos/' . $resultado['nombre'];
                } else {
                    $errores[] = 'Fila ' . ($i + 1) . ' (' . $nombre . '): ' . $resultado['error'];
                    continue;
                }
            }

            $filas[] = ['nombre' => $nombre, 'precio' => $precio, 'imagen' => $rutaImagen];
        }

        if (!$filas) {
            flash('error', $errores ? implode(' ', $errores) : 'Agrega al menos un producto con nombre y precio.');
        } else {
            db()->beginTransaction();
            try {
                $insertProducto = db()->prepare(
                    'INSERT INTO productos (subcategoria_id, slug, nombre, descripcion_corta, imagen, precio)
                     VALUES (:sub, :slug, :nombre, :corta, :imagen, :precio)'
                );
                foreach ($filas as $fila) {
                    $slug = slug_unico('productos', slugify($fila['nombre']));
                    $insertProducto->execute([
                        'sub' => $subcategoriaId, 'slug' => $slug,
                        'nombre' => $fila['nombre'], 'corta' => $fila['nombre'],
                        'imagen' => $fila['imagen'], 'precio' => $fila['precio'],
                    ]);
                }
                db()->commit();
                /* Las filas válidas se guardan aunque otras de la misma tanda hayan
                   fallado (fila vacía a medias, foto rechazada, etc.) — antes un solo
                   error descartaba las 5 filas de la carga rápida completa. */
                $msj = count($filas) . ' producto(s) agregados al catálogo. Puedes editarlos después para completar descripción o cambiar la foto.';
                if ($errores) {
                    $msj .= ' Se omitieron ' . count($errores) . ' fila(s): ' . implode(' ', $errores);
                }
                flash('ok', $msj);
            } catch (Throwable $e) {
                db()->rollBack();
                error_log('[lirios] Error en carga rápida de productos: ' . $e->getMessage());
                flash('error', 'No se pudo guardar. Intenta de nuevo; si el problema continúa, contacta a soporte técnico.');
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
if (!empty($_GET['editar'])) {
    $stmt = db()->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch() ?: null;
}

/* ---------- Catálogo actual (para la tabla), con filtros ---------- */

$totalProductos = (int) db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();

$categoriasFiltro = db()->query('SELECT id, nombre FROM categorias ORDER BY orden')->fetchAll();

$fCategoria = (int) ($_GET['f_categoria'] ?? 0);
$fSubcategoria = (int) ($_GET['f_subcategoria'] ?? 0);
$fEstado = $_GET['f_estado'] ?? '';
$fQ = trim((string) ($_GET['f_q'] ?? ''));
$hayFiltros = $fCategoria > 0 || $fSubcategoria > 0 || $fEstado !== '' || $fQ !== '';

$where = [];
$params = [];
if ($fCategoria > 0) {
    $where[] = 'c.id = :f_categoria';
    $params['f_categoria'] = $fCategoria;
}
if ($fSubcategoria > 0) {
    $where[] = 's.id = :f_subcategoria';
    $params['f_subcategoria'] = $fSubcategoria;
}
if ($fEstado === '1' || $fEstado === '0') {
    $where[] = 'p.disponible = :f_estado';
    $params['f_estado'] = $fEstado;
}
if ($fQ !== '') {
    $where[] = 'p.nombre LIKE :f_q';
    $params['f_q'] = '%' . $fQ . '%';
}

$sqlProductos = 'SELECT p.*, c.id AS categoria_id, c.nombre AS categoria_nombre, s.nombre AS subcategoria_nombre
     FROM productos p
     JOIN subcategorias s ON s.id = p.subcategoria_id
     JOIN categorias c ON c.id = s.categoria_id';
if ($where) {
    $sqlProductos .= ' WHERE ' . implode(' AND ', $where);
}
$sqlProductos .= ' ORDER BY p.nombre';

$stmtProductos = db()->prepare($sqlProductos);
$stmtProductos->execute($params);
$productos = $stmtProductos->fetchAll();

/* Mismo criterio del <select> "Agregar producto" (nota 2026-09-01, ocultar
   "General"): categorías con una sola subcategoría se identifican por el
   nombre de la categoría; el resto muestra "Categoría — Subcategoría". Se
   arma una sola vez y se reutiliza en el <select> de subcategoría y en las
   píldoras de filtros activos. */
$subFiltroLabel = [];
foreach ($subcategoriasPorCategoria as $catNombre => $subs) {
    if (count($subs) === 1) {
        $subFiltroLabel[(int) $subs[0]['id']] = $catNombre;
    } else {
        foreach ($subs as $s) {
            $subFiltroLabel[(int) $s['id']] = $catNombre . ' — ' . $s['nombre'];
        }
    }
}

/* Cascada categoría → subcategoría: si ya se filtró por categoría, el
   <select> de subcategoría solo enseña las suyas (menos ruido, más
   intuitivo) — se resetea a "Todas" en el navegador vía JS cuando cambia
   la categoría (ver script más abajo), así los dos filtros no chocan. */
$catNombrePorId = array_column($categoriasFiltro, 'nombre', 'id');
$subcategoriasParaFiltro = $subcategoriasPorCategoria;
if ($fCategoria > 0 && isset($catNombrePorId[$fCategoria])) {
    $catNombreFiltro = $catNombrePorId[$fCategoria];
    $subcategoriasParaFiltro = isset($subcategoriasPorCategoria[$catNombreFiltro])
        ? [$catNombreFiltro => $subcategoriasPorCategoria[$catNombreFiltro]]
        : [];
}

/* Píldoras de filtros activos: cada una enlaza a la misma vista sin ese
   filtro puntual, para poder quitarlos de a uno sin perder los demás.
   El "#form-filtros" al final es a propósito (ver también la <form> y el
   link "Limpiar" más abajo): sin él, cada filtro recargaba la página
   entera y el navegador volvía al tope — con el ancla, aterriza de
   vuelta justo en la barra de filtros. */
function quitar_filtro_url(string $param): string
{
    $q = $_GET;
    unset($q[$param], $q['editar']);
    return 'productos.php' . ($q ? ('?' . http_build_query($q)) : '') . '#form-filtros';
}

$chipsFiltro = [];
if ($fCategoria > 0 && isset($catNombrePorId[$fCategoria])) {
    $chipsFiltro[] = ['label' => 'Categoría: ' . $catNombrePorId[$fCategoria], 'param' => 'f_categoria'];
}
if ($fSubcategoria > 0 && isset($subFiltroLabel[$fSubcategoria])) {
    $chipsFiltro[] = ['label' => 'Subcategoría: ' . $subFiltroLabel[$fSubcategoria], 'param' => 'f_subcategoria'];
}
if ($fEstado === '1') {
    $chipsFiltro[] = ['label' => 'Estado: Disponible', 'param' => 'f_estado'];
} elseif ($fEstado === '0') {
    $chipsFiltro[] = ['label' => 'Estado: Agotado', 'param' => 'f_estado'];
}
if ($fQ !== '') {
    $chipsFiltro[] = ['label' => 'Buscar: "' . $fQ . '"', 'param' => 'f_q'];
}

admin_header('Productos del catálogo', 'productos.php');
?>

<p class="intro">Cada producto necesita un precio para poder mostrarse en el sitio. Los cambios que guardes aquí se ven en el catálogo público de inmediato.</p>

<div class="panel">
  <h2><?= $editando ? 'Editar producto: ' . e($editando['nombre']) : 'Agregar producto nuevo' ?></h2>
  <form method="post" action="productos.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id_original" value="<?= e((string)($editando['id'] ?? '')) ?>">

    <div class="form-grid">
      <div>
        <label for="subcategoria_id">Categoría / Subcategoría *</label>
        <select id="subcategoria_id" name="subcategoria_id" required>
          <option value="">— Elegir —</option>
          <?php foreach ($subcategoriasPorCategoria as $catNombre => $subs): ?>
            <?php if (count($subs) === 1): ?>
              <?php /* Categoría sin subclasificación real: se muestra como una sola
                      opción con el nombre de la categoría, sin exponer la subcategoría
                      interna "General" que solo existe para cumplir la FK NOT NULL. */ ?>
              <option value="<?= (int) $subs[0]['id'] ?>" <?= (int)($editando['subcategoria_id'] ?? 0) === (int)$subs[0]['id'] ? 'selected' : '' ?>><?= e($catNombre) ?></option>
            <?php else: ?>
              <optgroup label="<?= e($catNombre) ?>">
                <?php foreach ($subs as $s): ?>
                  <option value="<?= (int) $s['id'] ?>" <?= (int)($editando['subcategoria_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="precio">Precio (L.) *</label>
        <input type="number" id="precio" name="precio" min="0" step="0.01" required placeholder="L." value="<?= e($editando['precio'] ?? '') ?>">
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
    </div>

    <button type="submit" class="btn"><?= $editando ? 'Guardar cambios' : 'Agregar producto' ?></button>
    <?php if ($editando): ?>
      <a class="btn secundario" href="productos.php">Cancelar edición</a>
    <?php endif; ?>
  </form>
</div>

<?php /* Carga rápida deshabilitada a pedido de la clienta (2026-08-26) — ver
        nota junto a "guardar_rapido" arriba. Bloque comentado (if false),
        no borrado, por si se necesita reactivar más adelante. */ ?>
<?php if (false): ?>
<div class="panel">
  <h2>Carga rápida (varios productos a la vez)</h2>
  <p class="intro">Para cargar el catálogo rápido: elige una categoría/subcategoría (aplica a todas las filas) y escribe el nombre, precio y, si quieres, la foto de cada producto. Después puedes editarlos uno por uno para sumarles descripción.</p>
  <form method="post" action="productos.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar_rapido">

    <div class="form-grid">
      <div>
        <label for="subcategoria_id_rapido">Categoría / Subcategoría (para todas las filas) *</label>
        <select id="subcategoria_id_rapido" name="subcategoria_id_rapido" required>
          <option value="">— Elegir —</option>
          <?php foreach ($subcategoriasPorCategoria as $catNombre => $subs): ?>
            <?php if (count($subs) === 1): ?>
              <option value="<?= (int) $subs[0]['id'] ?>"><?= e($catNombre) ?></option>
            <?php else: ?>
              <optgroup label="<?= e($catNombre) ?>">
                <?php foreach ($subs as $s): ?>
                  <option value="<?= (int) $s['id'] ?>"><?= e($s['nombre']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="rapido-filas" id="rapido-filas">
      <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="rapido-fila">
          <input type="text" name="nombre_rapido[]" placeholder="Nombre del producto" maxlength="150">
          <input type="number" name="precio_rapido[]" placeholder="Precio (L.)" min="0" step="0.01">
          <input type="file" name="imagen_rapida[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" title="Foto (opcional)">
          <button type="button" class="btn mini secundario" data-quitar-fila aria-label="Quitar esta fila">✕</button>
        </div>
      <?php endfor; ?>
    </div>

    <button type="button" id="rapido-agregar-fila" class="btn mini secundario">+ Agregar fila</button>
    <button type="submit" class="btn">Guardar todos</button>
  </form>
</div>

<template id="rapido-fila-plantilla">
  <div class="rapido-fila">
    <input type="text" name="nombre_rapido[]" placeholder="Nombre del producto" maxlength="150">
    <input type="number" name="precio_rapido[]" placeholder="Precio (L.)" min="0" step="0.01">
    <input type="file" name="imagen_rapida[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" title="Foto (opcional)">
    <button type="button" class="btn mini secundario" data-quitar-fila aria-label="Quitar esta fila">✕</button>
  </div>
</template>

<script>
  (function () {
    var lista = document.getElementById('rapido-filas');
    var plantilla = document.getElementById('rapido-fila-plantilla');

    document.getElementById('rapido-agregar-fila').addEventListener('click', function () {
      lista.appendChild(plantilla.content.cloneNode(true));
    });

    lista.addEventListener('click', function (e) {
      var boton = e.target.closest('[data-quitar-fila]');
      if (!boton) return;
      var fila = boton.closest('.rapido-fila');
      var filas = lista.querySelectorAll('.rapido-fila');
      if (filas.length > 1) {
        fila.remove();
      } else {
        fila.querySelectorAll('input').forEach(function (input) { input.value = ''; });
      }
    });
  })();
</script>
<?php endif; ?>

<div class="panel">
  <h2>Catálogo actual (<?= $hayFiltros ? count($productos) . ' de ' . $totalProductos : $totalProductos ?>)</h2>

  <?php /* action lleva "#form-filtros": los <select> de abajo se auto-envían
          al cambiar (this.form.submit()), y sin el ancla cada filtro recargaba
          la página entera devolviendo el scroll al tope — con el ancla el
          navegador aterriza de vuelta justo aquí. */ ?>
  <form method="get" action="productos.php#form-filtros" class="filtros-toolbar" id="form-filtros">
    <div class="filtro-campo">
      <label for="f_categoria">Categoría</label>
      <select id="f_categoria" name="f_categoria" onchange="document.getElementById('f_subcategoria').value=''; this.form.submit();">
        <option value="">Todas</option>
        <?php foreach ($categoriasFiltro as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $fCategoria === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filtro-campo">
      <label for="f_subcategoria">Subcategoría</label>
      <select id="f_subcategoria" name="f_subcategoria" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($subcategoriasParaFiltro as $catNombre => $subs): ?>
          <?php if (count($subs) === 1): ?>
            <option value="<?= (int) $subs[0]['id'] ?>" <?= $fSubcategoria === (int) $subs[0]['id'] ? 'selected' : '' ?>><?= e($catNombre) ?></option>
          <?php else: ?>
            <optgroup label="<?= e($catNombre) ?>">
              <?php foreach ($subs as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $fSubcategoria === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filtro-campo">
      <label for="f_estado">Estado</label>
      <select id="f_estado" name="f_estado" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="1" <?= $fEstado === '1' ? 'selected' : '' ?>>Disponible</option>
        <option value="0" <?= $fEstado === '0' ? 'selected' : '' ?>>Agotado</option>
      </select>
    </div>
    <div class="filtro-campo filtro-buscar">
      <label for="f_q">Buscar por nombre</label>
      <div class="search-input-wrap">
        <?= admin_icono('buscar') ?>
        <input type="text" id="f_q" name="f_q" placeholder="Nombre del producto…" value="<?= e($fQ) ?>">
      </div>
    </div>
    <div class="filtro-acciones">
      <button type="submit" class="btn mini"><?= admin_icono('filtro') ?> Filtrar</button>
      <?php if ($hayFiltros): ?>
        <a class="btn mini secundario" href="productos.php#form-filtros"><?= admin_icono('cerrar') ?> Limpiar</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($chipsFiltro): ?>
    <div class="filtro-chips">
      <?php foreach ($chipsFiltro as $chip): ?>
        <a class="filtro-chip" href="<?= e(quitar_filtro_url($chip['param'])) ?>"><?= e($chip['label']) ?> <span aria-hidden="true">✕</span></a>
      <?php endforeach; ?>
      <a class="filtro-chip filtro-chip-limpiar" href="productos.php#form-filtros">Limpiar todo</a>
    </div>
  <?php endif; ?>

  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr>
          <th>Foto</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th>Subcategoría</th>
          <th>Precio</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$productos): ?>
          <tr><td colspan="7"><?= $hayFiltros ? 'Ningún producto coincide con los filtros elegidos.' : 'Aún no hay productos. Agrega el primero con el formulario de arriba.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($productos as $p): ?>
          <tr>
            <td><?php if (!empty($p['imagen'])): ?><img class="mini" src="../<?= e($p['imagen']) ?>" alt=""><?php endif; ?></td>
            <td>
              <strong><?= e($p['nombre']) ?></strong>
              <?php if (!empty($p['destacado'])): ?> <span class="badge destacado">Destacado</span><?php endif; ?>
            </td>
            <td><?= e($p['categoria_nombre']) ?></td>
            <td><?= $p['subcategoria_nombre'] === 'General' ? '—' : e($p['subcategoria_nombre']) ?></td>
            <td>L. <?= number_format((float) $p['precio'], 2) ?></td>
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
                <form class="inline" id="del-prod-<?= (int) $p['id'] ?>" method="post" action="productos.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                </form>
                <button type="button" class="btn mini peligro"
                  data-confirmar-eliminar="del-prod-<?= (int) $p['id'] ?>"
                  data-confirmar-mensaje="¿Eliminar «<?= e($p['nombre']) ?>» del catálogo? Esta acción no se puede deshacer.">Eliminar</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_footer(); ?>
