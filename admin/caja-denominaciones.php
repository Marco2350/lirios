<?php
/**
 * Denominaciones de billetes y monedas que se cuentan al abrir y cerrar la caja.
 * Se desactivan en vez de borrarse: el historial de cajas conserva su desglose.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/caja.php';

verificar_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $valor = limpiar_precio($_POST['valor'] ?? 0);
        $tipo = ($_POST['tipo'] ?? '') === 'MONEDA' ? 'MONEDA' : 'BILLETE';
        if ($valor <= 0) {
            flash('error', 'El valor debe ser mayor que cero.');
        } else {
            try {
                db()->prepare('INSERT INTO caja_denominaciones (valor, tipo) VALUES (?, ?)')->execute([$valor, $tipo]);
                flash('ok', 'Denominación agregada.');
            } catch (PDOException $e) {
                flash('error', 'Esa denominación ya existe.');
            }
        }
    } elseif ($accion === 'alternar') {
        db()->prepare('UPDATE caja_denominaciones SET activa = 1 - activa WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('ok', 'Denominación actualizada.');
    }
    header('Location: caja-denominaciones.php');
    exit;
}

$denominaciones = caja_denominaciones(false);

admin_header('Denominaciones de caja', 'caja.php');
?>

<p class="caja-volver-fila"><a class="caja-volver" href="caja.php">← Control de caja</a></p>
<p class="intro">Estas son las denominaciones que aparecen al contar el efectivo. Desactiva las que ya no uses: las cajas anteriores conservan su desglose tal como se contó.</p>

<div class="panel">
  <h2>Agregar denominación</h2>
  <form method="post" action="caja-denominaciones.php" class="caja-form-den">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="agregar">
    <div>
      <label for="den-tipo">Tipo</label>
      <select id="den-tipo" name="tipo"><option value="BILLETE">Billete</option><option value="MONEDA">Moneda</option></select>
    </div>
    <div>
      <label for="den-valor">Valor (L.)</label>
      <input type="number" id="den-valor" name="valor" min="0.01" step="0.01" inputmode="decimal" required placeholder="Ej. 200">
    </div>
    <button type="submit" class="btn">Agregar</button>
  </form>
</div>

<div class="panel">
  <h2>Denominaciones</h2>
  <div class="tabla-scroll">
    <table class="tabla caja-tabla">
      <thead><tr><th>Denominación</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($denominaciones as $d): ?>
          <tr>
            <td data-label="Denominación"><strong><?= e(caja_etiqueta_denominacion($d)) ?></strong></td>
            <td data-label="Estado"><?= $d['activa'] ? '<span class="badge si">Activa</span>' : '<span class="caja-estado cerrada"><i></i> Desactivada</span>' ?></td>
            <td>
              <form method="post" action="caja-denominaciones.php" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="alternar">
                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                <button type="submit" class="btn mini secundario"><?= $d['activa'] ? 'Desactivar' : 'Activar' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_footer(); ?>
