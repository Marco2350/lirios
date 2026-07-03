<?php
/**
 * Editor de las opciones del personalizador de ramos
 * (data/opciones-personalizacion.json): flores, colores,
 * tamaños y extras, cada uno con su precio o recargo.
 *
 * Nota de estructura: los <form> viven FUERA de la tabla y los
 * inputs de cada fila se asocian con el atributo form="id",
 * porque un <form> dentro de <tr> es HTML inválido.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

/* Definición de las secciones editables y sus campos */
$secciones = [
    'flores' => [
        'titulo' => 'Tipos de flor',
        'campoPrecio' => 'precio',
        'etiquetaPrecio' => 'Precio base (L.)',
        'extraCampos' => [],
    ],
    'colores' => [
        'titulo' => 'Colores dominantes',
        'campoPrecio' => null,
        'etiquetaPrecio' => null,
        'extraCampos' => ['css' => 'Color (visual)'],
    ],
    'tamanos' => [
        'titulo' => 'Tamaños',
        'campoPrecio' => 'recargo',
        'etiquetaPrecio' => 'Recargo (L.)',
        'extraCampos' => ['detalle' => 'Detalle (ej. 10–14 tallos)'],
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
            foreach ($conf['extraCampos'] as $campo => $etiqueta) {
                if ($campo === 'css') {
                    // Acepta un color hex del selector; los degradados existentes (mixto) se conservan
                    $css = limpiar_texto($_POST['css'] ?? '', 120);
                    $item['css'] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $css) || str_starts_with($css, 'linear-gradient')
                        ? $css
                        : '#CCCCCC';
                } else {
                    $item[$campo] = limpiar_texto($_POST[$campo] ?? '', 80);
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
?>

<p class="intro">Estas opciones arman el formulario de <strong>“Personaliza tu ramo”</strong> del sitio. El precio final del ramo es: <em>precio base de la flor + recargo del tamaño + extras marcados</em>. Los cambios se publican al guardar.</p>

<?php foreach ($secciones as $clave => $conf): ?>
<div class="panel">
  <h2><?= e($conf['titulo']) ?></h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr>
          <th>Nombre</th>
          <?php if ($conf['campoPrecio']): ?><th><?= e($conf['etiquetaPrecio']) ?></th><?php endif; ?>
          <?php foreach ($conf['extraCampos'] as $campo => $etiqueta): ?><th><?= e($etiqueta) ?></th><?php endforeach; ?>
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
          <?php foreach ($conf['extraCampos'] as $campo => $etiqueta): ?>
            <td>
              <?php if ($campo === 'css'): ?>
                <?php if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $item['css'] ?? '')): ?>
                  <input type="color" name="css" form="<?= e($fid) ?>" value="<?= e($item['css']) ?>">
                <?php else: ?>
                  <span class="color-preview" style="background:<?= e($item['css'] ?? '#CCC') ?>"></span>
                  <input type="hidden" name="css" form="<?= e($fid) ?>" value="<?= e($item['css'] ?? '') ?>">
                  <small>degradado (mixto)</small>
                <?php endif; ?>
              <?php else: ?>
                <input type="text" name="<?= e($campo) ?>" maxlength="80" form="<?= e($fid) ?>" value="<?= e($item[$campo] ?? '') ?>">
              <?php endif; ?>
            </td>
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
          <?php foreach ($conf['extraCampos'] as $campo => $etiqueta): ?>
            <td>
              <?php if ($campo === 'css'): ?>
                <input type="color" name="css" form="<?= e($fnew) ?>" value="#E88BAD">
              <?php else: ?>
                <input type="text" name="<?= e($campo) ?>" maxlength="80" form="<?= e($fnew) ?>" placeholder="<?= e($etiqueta) ?>">
              <?php endif; ?>
            </td>
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
