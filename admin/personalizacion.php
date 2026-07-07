<?php
/**
 * Editor de las opciones del personalizador de ramos
 * (data/opciones-personalizacion.json): tipos de flor (con su
 * forma en el dibujo), colores, papeles de envoltura, listones
 * y extras — cada uno con su precio.
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
 */
$secciones = [
    'flores' => [
        'titulo' => 'Tipos de flor',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio por tallo (L.)',
        'extraCampos' => ['kind' => ['select', 'Forma en el dibujo', $formasFlor]],
    ],
    'colores' => [
        'titulo' => 'Colores de flor',
        'campoPrecio' => null,
        'etiquetaPrecio' => null,
        'extraCampos' => ['css' => ['color', 'Color (visual)']],
    ],
    'wraps' => [
        'titulo' => 'Papeles de envoltura',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio (L.)',
        'extraCampos' => [
            'color' => ['color', 'Color del papel'],
            'description' => ['texto', 'Descripción corta'],
        ],
    ],
    'ribbons' => [
        'titulo' => 'Listones',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio (L.)',
        'extraCampos' => ['color' => ['color', 'Color del listón']],
    ],
    'extras' => [
        'titulo' => 'Extras',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio (L.)',
        'extraCampos' => [],
    ],
];

$opciones = leer_json(OPCIONES_JSON);
foreach (array_keys($secciones) as $s) {
    $opciones[$s] = $opciones[$s] ?? [];
}

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

    if ($accion === 'guardar') {
        $id = limpiar_texto($_POST['id'] ?? '', 60); // vacío = item nuevo
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 60);

        if ($nombre === '') {
            flash('error', 'El nombre es obligatorio.');
        } else {
            $item = ['id' => $id, 'nombre' => $nombre];

            if ($conf['campoPrecio']) {
                $item[$conf['campoPrecio']] = limpiar_precio($_POST['valor'] ?? 0);
            }
            foreach ($conf['extraCampos'] as $campo => $def) {
                [$tipo] = $def;
                $valor = limpiar_texto($_POST[$campo] ?? '', 120);
                if ($tipo === 'color') {
                    // Acepta hex del selector; los degradados existentes (mixto) se conservan
                    $item[$campo] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor) || str_starts_with($valor, 'linear-gradient')
                        ? $valor
                        : '#CCCCCC';
                } elseif ($tipo === 'select') {
                    $permitidos = array_keys($def[2]);
                    $item[$campo] = in_array($valor, $permitidos, true) ? $valor : $permitidos[0];
                } else {
                    $item[$campo] = $valor;
                }
            }

            $indice = null;
            if ($id !== '') {
                foreach ($opciones[$seccion] as $i => $it) {
                    if ($it['id'] === $id) {
                        $indice = $i;
                        break;
                    }
                }
            }

            if ($indice !== null) {
                $opciones[$seccion][$indice] = $item;
                $msj = 'Cambios guardados.';
            } else {
                $item['id'] = id_unico(slugify($nombre), $opciones[$seccion]);
                $opciones[$seccion][] = $item;
                $msj = 'Opción agregada.';
            }

            if (guardar_json(OPCIONES_JSON, $opciones)) {
                flash('ok', $msj);
            } else {
                flash('error', 'No se pudo escribir el archivo de opciones. Revisa permisos.');
            }
        }
        header('Location: personalizacion.php');
        exit;
    }

    if ($accion === 'eliminar') {
        $id = limpiar_texto($_POST['id'] ?? '', 60);
        if (count($opciones[$seccion]) <= 1) {
            flash('error', 'Debe quedar al menos una opción en cada sección para que el personalizador funcione.');
        } else {
            $opciones[$seccion] = array_values(array_filter($opciones[$seccion], fn($it) => $it['id'] !== $id));
            guardar_json(OPCIONES_JSON, $opciones);
            flash('ok', 'Opción eliminada.');
        }
        header('Location: personalizacion.php');
        exit;
    }
}

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
        echo '<input type="text" name="' . e($campo) . '" maxlength="120" form="' . e($fid) . '" value="' . e($valor) . '"' . $ph . '>';
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
        <?php foreach ($opciones[$clave] as $item): ?>
        <?php
          $fid = 'f-' . $clave . '-' . $item['id'];        // form de guardar
          $fdel = 'del-' . $clave . '-' . $item['id'];     // form de eliminar
        ?>
        <tr>
          <td><input type="text" name="nombre" required maxlength="60" form="<?= e($fid) ?>" value="<?= e($item['nombre']) ?>"></td>
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
          <td><input type="text" name="nombre" required maxlength="60" form="<?= e($fnew) ?>" placeholder="Nueva opción…"></td>
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
  <?php foreach ($opciones[$clave] as $item): ?>
    <form id="f-<?= e($clave . '-' . $item['id']) ?>" method="post" action="personalizacion.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="seccion" value="<?= e($clave) ?>">
      <input type="hidden" name="id" value="<?= e($item['id']) ?>">
    </form>
    <form id="del-<?= e($clave . '-' . $item['id']) ?>" method="post" action="personalizacion.php" onsubmit="return confirm('¿Eliminar «<?= e($item['nombre']) ?>»?');">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar">
      <input type="hidden" name="seccion" value="<?= e($clave) ?>">
      <input type="hidden" name="id" value="<?= e($item['id']) ?>">
    </form>
  <?php endforeach; ?>
  <form id="new-<?= e($clave) ?>" method="post" action="personalizacion.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="seccion" value="<?= e($clave) ?>">
    <input type="hidden" name="id" value="">
  </form>
</div>
<?php endforeach; ?>

<?php admin_footer(); ?>
