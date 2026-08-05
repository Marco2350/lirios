<?php
/**
 * Editor de las opciones del personalizador de ramos, sobre las
 * tablas MySQL pers_flores / pers_colores / pers_wraps / pers_ribbons
 * / pers_extras: tipos de flor (con su forma en el dibujo), colores,
 * papeles de envoltura, listones y extras — cada uno con su precio.
 *
 * Precio del ramo = (precio por tallo × cantidad de cada flor)
 *                 + papel + listón + extras marcados.
 *
 * Nota de estructura: los <form> viven FUERA de la tabla y los
 * inputs de cada fila se asocian con el atributo form="id",
 * porque un <form> dentro de <tr> es HTML inválido.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

/* Formas de flor disponibles en el dibujo del preview (personalizar.js) */
$formasFlor = [
    'rose' => 'Rosa',
    'sunflower' => 'Girasol',
    'lily' => 'Lirio',
    'gerbera' => 'Gerbera',
    'tulip' => 'Tulipán',
    'carnation' => 'Clavel',
    'daisy' => 'Margarita',
    'aster' => 'Astromelia',
    'mixed' => 'Mixta (varía por tallo)',
];

/*
 * Secciones editables. Cada campo extra define su tipo:
 *   ['color', 'Etiqueta']            → selector de color
 *   ['texto', 'Etiqueta']            → texto corto
 *   ['select', 'Etiqueta', opciones] → lista desplegable
 * 'columna' es el nombre real de la columna en la tabla.
 */
$secciones = [
    'flores' => [
        'tabla' => 'pers_flores',
        'titulo' => 'Tipos de flor',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio por tallo (L.)',
        'extraCampos' => ['kind' => ['select', 'Forma en el dibujo', $formasFlor]],
    ],
    'colores' => [
        'tabla' => 'pers_colores',
        'titulo' => 'Colores de flor',
        'campoPrecio' => null,
        'etiquetaPrecio' => null,
        'extraCampos' => ['css' => ['color', 'Color (visual)']],
    ],
    'wraps' => [
        'tabla' => 'pers_wraps',
        'titulo' => 'Papeles de envoltura',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio (L.)',
        'extraCampos' => [
            'color' => ['color', 'Color del papel'],
            'descripcion' => ['texto', 'Descripción corta'],
        ],
    ],
    'ribbons' => [
        'tabla' => 'pers_ribbons',
        'titulo' => 'Listones',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio (L.)',
        'extraCampos' => ['color' => ['color', 'Color del listón']],
    ],
    'extras' => [
        'tabla' => 'pers_extras',
        'titulo' => 'Extras',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio (L.)',
        'extraCampos' => [],
    ],
];

/* ---------- Acciones POST ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $seccion = $_POST['seccion'] ?? '';

    if (!isset($secciones[$seccion])) {
        flash('error', 'Sección no válida.');
        header('Location: personalizacion.php');
        exit;
    }

    $conf = $secciones[$seccion];
    $tabla = $conf['tabla'];

    if ($accion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);

        if ($nombre === '') {
            flash('error', 'El nombre es obligatorio.');
        } else {
            $campos = ['nombre' => $nombre];

            if ($conf['campoPrecio']) {
                $campos[$conf['campoPrecio']] = limpiar_precio($_POST['valor'] ?? 0);
            }
            foreach ($conf['extraCampos'] as $campo => $def) {
                [$tipo] = $def;
                $valor = limpiar_texto($_POST[$campo] ?? '', 150);
                if ($tipo === 'color') {
                    $campos[$campo] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor) || str_starts_with($valor, 'linear-gradient')
                        ? $valor
                        : '#CCCCCC';
                } elseif ($tipo === 'select') {
                    $permitidos = array_keys($def[2]);
                    $campos[$campo] = in_array($valor, $permitidos, true) ? $valor : $permitidos[0];
                } else {
                    $campos[$campo] = $valor;
                }
            }

            if ($id > 0) {
                $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($campos)));
                db()->prepare("UPDATE {$tabla} SET {$set} WHERE id = :id")
                    ->execute($campos + ['id' => $id]);
                flash('ok', 'Cambios guardados.');
            } else {
                $campos['slug'] = slug_unico($tabla, slugify($nombre));
                $campos['orden'] = (int) db()->query("SELECT COALESCE(MAX(orden),0)+1 FROM {$tabla}")->fetchColumn();
                $columnas = implode(', ', array_keys($campos));
                $marcadores = implode(', ', array_map(fn($c) => ":$c", array_keys($campos)));
                db()->prepare("INSERT INTO {$tabla} ({$columnas}) VALUES ({$marcadores})")->execute($campos);
                flash('ok', 'Opción agregada.');
            }
        }
        header('Location: personalizacion.php');
        exit;
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $total = (int) db()->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
        if ($total <= 1) {
            flash('error', 'Debe quedar al menos una opción en cada sección para que el personalizador funcione.');
        } else {
            db()->prepare("DELETE FROM {$tabla} WHERE id = ?")->execute([$id]);
            flash('ok', 'Opción eliminada.');
        }
        header('Location: personalizacion.php');
        exit;
    }
}

/* ---------- Datos de cada sección ---------- */

foreach ($secciones as $clave => &$conf) {
    $conf['items'] = db()->query("SELECT * FROM {$conf['tabla']} ORDER BY orden, nombre")->fetchAll();
}
unset($conf);

admin_header('Opciones de personalización', 'personalizacion.php');

/* Pinta la celda de un campo extra (para filas existentes y fila nueva) */
function celda_campo(string $fid, string $campo, array $def, ?array $item): void
{
    [$tipo, $etiqueta] = $def;
    $valor = $item[$campo] ?? '';

    if ($tipo === 'color') {
        if ($item !== null && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor)) {
            // Valor no editable con el selector (ej. degradado "mixto"): se conserva
            echo '<span class="color-preview" style="background:' . e($valor ?: '#CCC') . '"></span>';
            echo '<input type="hidden" name="' . e($campo) . '" form="' . e($fid) . '" value="' . e($valor) . '">';
            echo '<small>degradado</small>';
        } else {
            $hex = $item === null ? '#E88BAD' : $valor;
            echo '<input type="color" name="' . e($campo) . '" form="' . e($fid) . '" value="' . e($hex) . '">';
        }
    } elseif ($tipo === 'select') {
        echo '<select name="' . e($campo) . '" form="' . e($fid) . '">';
        foreach ($def[2] as $clave => $nombre) {
            $sel = ($valor === $clave) ? ' selected' : '';
            echo '<option value="' . e($clave) . '"' . $sel . '>' . e($nombre) . '</option>';
        }
        echo '</select>';
    } else {
        $ph = $item === null ? ' placeholder="' . e($etiqueta) . '"' : '';
        echo '<input type="text" name="' . e($campo) . '" maxlength="150" form="' . e($fid) . '" value="' . e($valor) . '"' . $ph . '>';
    }
}
?>

<p class="intro">Estas opciones arman el constructor de <strong>“Personaliza tu ramo”</strong> del sitio. El precio del ramo es: <em>precio por tallo × cantidad de cada flor + papel de envoltura + listón + extras marcados</em>. Los cambios se publican al guardar.</p>

<?php foreach ($secciones as $clave => $conf): ?>
<div class="panel">
  <h2><?= e($conf['titulo']) ?></h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr>
          <th>Nombre</th>
          <?php if ($conf['campoPrecio']): ?><th><?= e($conf['etiquetaPrecio']) ?></th><?php endif; ?>
          <?php foreach ($conf['extraCampos'] as $campo => $def): ?><th><?= e($def[1]) ?></th><?php endforeach; ?>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($conf['items'] as $item): ?>
        <?php
          $fid = 'f-' . $clave . '-' . $item['id'];        // form de guardar
          $fdel = 'del-' . $clave . '-' . $item['id'];     // form de eliminar
        ?>
        <tr>
          <td><input type="text" name="nombre" required maxlength="100" form="<?= e($fid) ?>" value="<?= e($item['nombre']) ?>"></td>
          <?php if ($conf['campoPrecio']): ?>
            <td style="max-width:140px"><input type="number" name="valor" min="0" step="0.01" form="<?= e($fid) ?>" value="<?= e((string)($item[$conf['campoPrecio']] ?? 0)) ?>"></td>
          <?php endif; ?>
          <?php foreach ($conf['extraCampos'] as $campo => $def): ?>
            <td><?php celda_campo($fid, $campo, $def, $item); ?></td>
          <?php endforeach; ?>
          <td>
            <div class="acciones-fila">
              <button type="submit" class="btn mini" form="<?= e($fid) ?>">Guardar</button>
              <button type="submit" class="btn mini peligro" form="<?= e($fdel) ?>">Eliminar</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Fila para agregar nueva opción -->
        <?php $fnew = 'new-' . $clave; ?>
        <tr>
          <td><input type="text" name="nombre" required maxlength="100" form="<?= e($fnew) ?>" placeholder="Nueva opción…"></td>
          <?php if ($conf['campoPrecio']): ?>
            <td style="max-width:140px"><input type="number" name="valor" min="0" step="0.01" form="<?= e($fnew) ?>" placeholder="0.00"></td>
          <?php endif; ?>
          <?php foreach ($conf['extraCampos'] as $campo => $def): ?>
            <td><?php celda_campo($fnew, $campo, $def, null); ?></td>
          <?php endforeach; ?>
          <td><button type="submit" class="btn mini secundario" form="<?= e($fnew) ?>">+ Agregar</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Formularios de esta sección (fuera de la tabla, asociados por form="id") -->
  <?php foreach ($conf['items'] as $item): ?>
    <form id="f-<?= e($clave . '-' . $item['id']) ?>" method="post" action="personalizacion.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="seccion" value="<?= e($clave) ?>">
      <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    </form>
    <form id="del-<?= e($clave . '-' . $item['id']) ?>" method="post" action="personalizacion.php" onsubmit="return confirm('¿Eliminar «<?= e($item['nombre']) ?>»?');">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar">
      <input type="hidden" name="seccion" value="<?= e($clave) ?>">
      <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    </form>
  <?php endforeach; ?>
  <form id="new-<?= e($clave) ?>" method="post" action="personalizacion.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="seccion" value="<?= e($clave) ?>">
    <input type="hidden" name="id" value="0">
  </form>
</div>
<?php endforeach; ?>

<?php admin_footer(); ?>
