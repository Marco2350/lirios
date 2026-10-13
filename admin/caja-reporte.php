<?php
/**
 * Reporte imprimible de cajas (se abre en pestaña aparte; el botón Imprimir
 * usa el diálogo del navegador, que también permite "Guardar como PDF").
 * Sin librerías: no hay build step ni Composer en este proyecto.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/caja.php';

$desde = caja_fecha_valida($_GET['desde'] ?? null);
$hasta = caja_fecha_valida($_GET['hasta'] ?? null);
$estado = in_array($_GET['estado'] ?? '', ['ABIERTA', 'CERRADA'], true) ? $_GET['estado'] : '';

$sucursalId = (int) ($_GET['sucursal'] ?? 0);
$cajaId = (int) ($_GET['caja'] ?? 0);
$cajas = array_reverse(caja_listar($desde, $hasta, $estado, $sucursalId, $cajaId, 500)); // cronológico

$tot = ['apertura' => 0.0, 'ingresos' => 0.0, 'egresos' => 0.0, 'esperado' => 0.0, 'cierre' => 0.0, 'dif' => 0.0];
foreach ($cajas as $c) {
    $tot['apertura'] += (float) $c['saldo_apertura'];
    $tot['ingresos'] += (float) $c['ingresos'];
    $tot['egresos'] += (float) $c['egresos'];
    $tot['esperado'] += $c['esperado'];
    if ($c['estado'] === 'CERRADA') {
        $tot['cierre'] += (float) $c['saldo_cierre'];
        $tot['dif'] += (float) $c['diferencia'];
    }
}

$periodo = ($desde || $hasta)
    ? ($desde ? date('d/m/Y', strtotime($desde)) : 'el inicio') . ' al ' . ($hasta ? date('d/m/Y', strtotime($hasta)) : 'hoy')
    : 'Todo el historial';
if ($cajas && ($sucursalId || $cajaId)) {
    $periodo .= ' · ' . ($cajaId ? caja_nombre_completo($cajas[0]) : $cajas[0]['sucursal_nombre']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Reporte de caja — LIRIOS</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #241F17; margin: 0; padding: 24px; font-size: 13px; }
    header { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #B8933E; padding-bottom: 12px; margin-bottom: 16px; }
    header img { height: 48px; }
    h1 { font-size: 20px; margin: 0; }
    header p { margin: 2px 0 0; color: #726A57; }
    .barra { margin-left: auto; }
    button { font: inherit; padding: 8px 16px; border: 0; border-radius: 6px; background: #241F17; color: #fff; cursor: pointer; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 7px 8px; border-bottom: 1px solid #ddd; text-align: left; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #726A57; border-bottom: 2px solid #241F17; }
    .num { text-align: right; white-space: nowrap; }
    tfoot td { font-weight: 700; border-top: 2px solid #241F17; border-bottom: 0; }
    .falta { color: #9C3630; font-weight: 700; }
    .sobra { color: #8F6F2C; font-weight: 700; }
    .vacio { padding: 30px; text-align: center; color: #726A57; }
    @media print { .barra { display: none; } body { padding: 0; } }
  </style>
</head>
<body>
  <header>
    <img src="../images/logo.png" alt="LIRIOS Floristería">
    <div>
      <h1>Reporte de control de caja</h1>
      <p>Período: <?= e($periodo) ?><?= $estado ? ' · ' . ($estado === 'ABIERTA' ? 'Solo abiertas' : 'Solo cerradas') : '' ?> · Generado el <?= e(date('d/m/Y g:i a')) ?></p>
    </div>
    <div class="barra"><button type="button" onclick="window.print()">Imprimir / Guardar PDF</button></div>
  </header>

  <?php if (!$cajas): ?>
    <p class="vacio">No hay cajas en ese período.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr><th>Fecha</th><th>Sucursal / Caja</th><th class="num">Apertura</th><th class="num">Entradas</th><th class="num">Salidas</th><th class="num">Esperado</th><th class="num">Conteo cierre</th><th class="num">Diferencia</th><th>Estado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cajas as $c): $cerrada = $c['estado'] === 'CERRADA'; $dif = $cerrada ? (float) $c['diferencia'] : null; ?>
          <tr>
            <td><?= e(date('d/m/Y', strtotime($c['fecha']))) ?></td>
            <td><?= e(caja_nombre_completo($c)) ?></td>
            <td class="num"><?= e(number_format((float) $c['saldo_apertura'], 2)) ?></td>
            <td class="num"><?= e(number_format((float) $c['ingresos'], 2)) ?></td>
            <td class="num"><?= e(number_format((float) $c['egresos'], 2)) ?></td>
            <td class="num"><?= e(number_format($c['esperado'], 2)) ?></td>
            <td class="num"><?= $cerrada ? e(number_format((float) $c['saldo_cierre'], 2)) : '—' ?></td>
            <td class="num <?= $dif === null || abs($dif) < 0.005 ? '' : ($dif < 0 ? 'falta' : 'sobra') ?>"><?= $dif === null ? '—' : e(number_format($dif, 2)) ?></td>
            <td><?= $cerrada ? 'Cerrada' : 'Abierta' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2">Totales (<?= count($cajas) ?>)</td>
          <td class="num"><?= e(number_format($tot['apertura'], 2)) ?></td>
          <td class="num"><?= e(number_format($tot['ingresos'], 2)) ?></td>
          <td class="num"><?= e(number_format($tot['egresos'], 2)) ?></td>
          <td class="num"><?= e(number_format($tot['esperado'], 2)) ?></td>
          <td class="num"><?= e(number_format($tot['cierre'], 2)) ?></td>
          <td class="num"><?= e(number_format($tot['dif'], 2)) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <p style="color:#726A57;margin-top:14px">Montos en lempiras (L.). La diferencia es conteo al cierre menos saldo esperado: negativa = falta efectivo, positiva = sobra.</p>
  <?php endif; ?>
</body>
</html>
