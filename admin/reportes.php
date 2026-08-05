<?php
/**
 * Reportería de ventas: pedidos enviados por WhatsApp desde carrito.html,
 * registrados en las tablas pedidos/pedido_items por /api/pedidos.php.
 *
 * "Ventas" aquí significa pedidos que el cliente envió por WhatsApp, no
 * pagos confirmados (el negocio no tiene pasarela de pago en línea — el
 * pago se coordina directamente con la clienta). Se etiqueta así en la UI
 * para no confundir "pedido enviado" con "venta cobrada".
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

/** Cuenta y suma pedidos desde una fecha (inclusive), formato 'Y-m-d'. */
function resumen_desde(string $desde): array
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS ingresos
         FROM pedidos WHERE creado_en >= ?'
    );
    $stmt->execute([$desde . ' 00:00:00']);
    $fila = $stmt->fetch();
    return ['pedidos' => (int) $fila['pedidos'], 'ingresos' => (float) $fila['ingresos']];
}

$hoy = new DateTime('today');
$lunesEstaSemana = new DateTime('monday this week');
$inicioMes = new DateTime(date('Y-m-01'));

$resumenHoy = resumen_desde($hoy->format('Y-m-d'));
$resumenSemana = resumen_desde($lunesEstaSemana->format('Y-m-d'));
$resumenMes = resumen_desde($inicioMes->format('Y-m-d'));

$totalGeneral = db()->query('SELECT COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS ingresos FROM pedidos')->fetch();
$totalPedidos = (int) $totalGeneral['pedidos'];
$ticketPromedio = $totalPedidos > 0 ? ((float) $totalGeneral['ingresos']) / $totalPedidos : 0.0;

/* ---------- Series diaria / semanal / mensual, a partir de una sola consulta ---------- */

$desdeGeneral = (clone $hoy)->modify('-12 months')->modify('first day of this month');
$stmt = db()->prepare('SELECT DATE(creado_en) AS fecha, total FROM pedidos WHERE creado_en >= ?');
$stmt->execute([$desdeGeneral->format('Y-m-d') . ' 00:00:00']);
$filas = $stmt->fetchAll();

function serie_diaria(array $filas, DateTime $hoy, int $dias): array
{
    $buckets = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $dia = (clone $hoy)->modify("-{$i} days");
        $buckets[$dia->format('Y-m-d')] = ['etiqueta' => $dia->format('d/m'), 'pedidos' => 0, 'ingresos' => 0.0];
    }
    foreach ($filas as $f) {
        if (isset($buckets[$f['fecha']])) {
            $buckets[$f['fecha']]['pedidos']++;
            $buckets[$f['fecha']]['ingresos'] += (float) $f['total'];
        }
    }
    return array_values($buckets);
}

function serie_semanal(array $filas, DateTime $lunesActual, int $semanas): array
{
    $buckets = [];
    for ($i = $semanas - 1; $i >= 0; $i--) {
        $inicio = (clone $lunesActual)->modify("-{$i} weeks");
        $fin = (clone $inicio)->modify('+6 days');
        $buckets[$inicio->format('Y-m-d')] = [
            'etiqueta' => $inicio->format('d/m') . '–' . $fin->format('d/m'),
            'pedidos' => 0,
            'ingresos' => 0.0,
        ];
    }
    foreach ($filas as $f) {
        $fecha = new DateTime($f['fecha']);
        $fecha->modify('monday this week');
        $key = $fecha->format('Y-m-d');
        if (isset($buckets[$key])) {
            $buckets[$key]['pedidos']++;
            $buckets[$key]['ingresos'] += (float) $f['total'];
        }
    }
    return array_values($buckets);
}

function serie_mensual(array $filas, DateTime $inicioMes, int $meses): array
{
    $meses_es = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $buckets = [];
    for ($i = $meses - 1; $i >= 0; $i--) {
        $mes = (clone $inicioMes)->modify("-{$i} months");
        $etiqueta = $meses_es[(int) $mes->format('n') - 1] . ' ' . $mes->format('y');
        $buckets[$mes->format('Y-m')] = ['etiqueta' => $etiqueta, 'pedidos' => 0, 'ingresos' => 0.0];
    }
    foreach ($filas as $f) {
        $key = substr($f['fecha'], 0, 7);
        if (isset($buckets[$key])) {
            $buckets[$key]['pedidos']++;
            $buckets[$key]['ingresos'] += (float) $f['total'];
        }
    }
    return array_values($buckets);
}

$serieDiaria = serie_diaria($filas, $hoy, 14);
$serieSemanal = serie_semanal($filas, $lunesEstaSemana, 12);
$serieMensual = serie_mensual($filas, $inicioMes, 12);

/* ---------- Productos más vendidos (todo el historial) ---------- */

$topProductos = db()->query(
    "SELECT nombre, SUM(cantidad) AS unidades, SUM(subtotal) AS ingresos
     FROM pedido_items
     GROUP BY nombre
     ORDER BY unidades DESC
     LIMIT 10"
)->fetchAll();

/** Pinta una lista de barras horizontales L.-proporcionales al máximo de la serie. */
function pintar_barras(array $serie): void
{
    $max = max(array_column($serie, 'ingresos')) ?: 1;
    foreach ($serie as $punto) {
        $ancho = max(2, round(($punto['ingresos'] / $max) * 100));
        echo '<div class="chart-row">';
        echo '<span class="chart-label">' . e($punto['etiqueta']) . '</span>';
        echo '<div class="chart-track"><div class="chart-fill" style="width:' . $ancho . '%"></div></div>';
        echo '<span class="chart-value">L. ' . number_format($punto['ingresos'], 0) . '<small>· ' . $punto['pedidos'] . ' ped.</small></span>';
        echo '</div>';
    }
}

admin_header('Reportes de ventas', 'reportes.php');
?>

<p class="intro">"Ventas" = pedidos enviados por WhatsApp desde el carrito del sitio. El pago y la entrega se coordinan directamente con la clienta, así que estas cifras reflejan pedidos solicitados, no cobros confirmados.</p>

<?php if ($totalPedidos === 0): ?>
  <div class="panel">
    <h2>Todavía no hay pedidos registrados</h2>
    <p class="intro" style="margin:0">En cuanto un cliente use el botón «Enviar pedido por WhatsApp» del carrito, aparecerá aquí automáticamente. No hace falta hacer nada más.</p>
  </div>
<?php else: ?>

<div class="stats">
  <div class="stat"><span class="num"><?= $resumenHoy['pedidos'] ?></span><span class="lbl">pedidos hoy</span></div>
  <div class="stat"><span class="num">L. <?= number_format($resumenHoy['ingresos'], 0) ?></span><span class="lbl">enviado hoy</span></div>
  <div class="stat"><span class="num"><?= $resumenSemana['pedidos'] ?></span><span class="lbl">pedidos esta semana</span></div>
  <div class="stat"><span class="num">L. <?= number_format($resumenSemana['ingresos'], 0) ?></span><span class="lbl">enviado esta semana</span></div>
  <div class="stat"><span class="num"><?= $resumenMes['pedidos'] ?></span><span class="lbl">pedidos este mes</span></div>
  <div class="stat"><span class="num">L. <?= number_format($resumenMes['ingresos'], 0) ?></span><span class="lbl">enviado este mes</span></div>
  <div class="stat"><span class="num">L. <?= number_format($ticketPromedio, 0) ?></span><span class="lbl">ticket promedio (histórico)</span></div>
</div>

<div class="panel">
  <h2>Últimos 14 días</h2>
  <div class="chart-bars"><?php pintar_barras($serieDiaria); ?></div>
</div>

<div class="panel">
  <h2>Últimas 12 semanas</h2>
  <div class="chart-bars"><?php pintar_barras($serieSemanal); ?></div>
</div>

<div class="panel">
  <h2>Últimos 12 meses</h2>
  <div class="chart-bars"><?php pintar_barras($serieMensual); ?></div>
</div>

<div class="panel">
  <h2>Productos más pedidos</h2>
  <?php if (!$topProductos): ?>
    <p>Aún no hay suficientes datos.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla">
        <thead><tr><th>Producto</th><th>Unidades</th><th>Ingresos</th></tr></thead>
        <tbody>
          <?php foreach ($topProductos as $p): ?>
            <tr>
              <td><?= e($p['nombre']) ?></td>
              <td><?= (int) $p['unidades'] ?></td>
              <td>L. <?= number_format((float) $p['ingresos'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php admin_footer(); ?>
