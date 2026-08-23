<?php
/** Panel principal: estado del día, KPIs del catálogo, actividad y accesos rápidos. */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

const ESTADOS_PEDIDO = ['pendiente' => 'Pendiente', 'coordinado' => 'Coordinado', 'entregado' => 'Entregado'];

$totalProductos = (int) db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$totalDisponibles = (int) db()->query('SELECT COUNT(*) FROM productos WHERE disponible = 1')->fetchColumn();
$totalCategorias = (int) db()->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
$totalSubcategorias = (int) db()->query('SELECT COUNT(*) FROM subcategorias')->fetchColumn();

$imagenes = is_dir(IMAGENES_DIR)
    ? glob(IMAGENES_DIR . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE)
    : [];

$stmtHoy = db()->prepare('SELECT COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS ingresos FROM pedidos WHERE creado_en >= ?');
$stmtHoy->execute([date('Y-m-d') . ' 00:00:00']);
$hoy = $stmtHoy->fetch();
$pedidosHoy = (int) $hoy['pedidos'];
$ingresosHoy = (float) $hoy['ingresos'];

$pedidosPendientes = (int) db()->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();

$pedidosRecientes = db()->query(
    'SELECT id, cliente_nombre, total, estado, creado_en FROM pedidos ORDER BY creado_en DESC LIMIT 6'
)->fetchAll();

$accesos = [
    ['pedidos.php', 'pedidos', 'Pedidos', 'Estado de cada pedido enviado por WhatsApp'],
    ['reportes.php', 'reportes', 'Reportes', 'Ventas por día, semana y mes'],
    ['productos.php', 'productos', 'Productos', 'Catálogo, tallas y precios'],
    ['categorias.php', 'categorias', 'Categorías', 'Taxonomía del catálogo'],
    ['subir-imagen.php', 'imagenes', 'Imágenes', 'Fotos disponibles para el catálogo'],
];

admin_header('Resumen general', 'dashboard.php');
?>

<div class="dash-intro-row">
  <p class="intro">Desde aquí administras el catálogo, las categorías y las fotos del sitio. Cada cambio que guardes se publica de inmediato.</p>
  <?php if ($pedidosPendientes > 0): ?>
    <a href="pedidos.php" class="estado-pill alerta"><?= admin_icono('pedidos') ?> <?= $pedidosPendientes ?> <?= $pedidosPendientes === 1 ? 'pedido por coordinar' : 'pedidos por coordinar' ?></a>
  <?php else: ?>
    <span class="estado-pill ok"><?= admin_icono('check') ?> Todo al día</span>
  <?php endif; ?>
</div>

<div class="dash-section">
  <span class="section-label">Hoy</span>
  <div class="hero-grid">
    <a href="pedidos.php" class="hero-tile <?= $pedidosPendientes > 0 ? 'alerta' : '' ?>">
      <span class="hero-icono"><?= admin_icono($pedidosPendientes > 0 ? 'pedidos' : 'check') ?></span>
      <div>
        <span class="hero-num"><?= $pedidosPendientes ?></span>
        <span class="hero-lbl"><?= $pedidosPendientes > 0 ? ($pedidosPendientes === 1 ? 'pedido pendiente por coordinar' : 'pedidos pendientes por coordinar') : 'pedidos pendientes — no hay nada esperando' ?></span>
        <span class="hero-cta">Revisar pedidos →</span>
      </div>
    </a>
    <div class="hero-stack">
      <div class="mini-tile">
        <span class="kpi-icono"><?= admin_icono('reportes') ?></span>
        <div>
          <span class="num"><?= $pedidosHoy ?></span>
          <span class="lbl">pedidos recibidos hoy</span>
        </div>
      </div>
      <div class="mini-tile">
        <span class="kpi-icono azul"><?= admin_icono('reportes') ?></span>
        <div>
          <span class="num">L. <?= number_format($ingresosHoy, 0) ?></span>
          <span class="lbl">solicitado hoy</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="dash-section">
  <span class="section-label">Catálogo</span>
  <div class="kpi-grid">
    <div class="kpi-tile">
      <span class="kpi-icono"><?= admin_icono('productos') ?></span>
      <span class="num"><?= $totalDisponibles ?> / <?= $totalProductos ?></span>
      <span class="lbl">productos disponibles</span>
    </div>
    <div class="kpi-tile">
      <span class="kpi-icono salvia"><?= admin_icono('categorias') ?></span>
      <span class="num"><?= $totalCategorias ?> / <?= $totalSubcategorias ?></span>
      <span class="lbl">categorías / subcategorías</span>
    </div>
    <div class="kpi-tile">
      <span class="kpi-icono terracota"><?= admin_icono('imagenes') ?></span>
      <span class="num"><?= count($imagenes) ?></span>
      <span class="lbl">fotos disponibles</span>
    </div>
  </div>
</div>

<div class="dash-body">
  <div class="dash-section" style="margin-bottom:0">
    <span class="section-label">Actividad reciente</span>
    <div class="panel">
      <div class="panel-header">
        <h2>Últimos pedidos</h2>
        <a class="ver-todo" href="pedidos.php">Ver todos →</a>
      </div>
      <?php if (!$pedidosRecientes): ?>
        <div class="panel-empty">
          <span class="panel-empty-icono"><?= admin_icono('pedidos') ?></span>
          <p>Todavía no ha entrado ningún pedido. En cuanto una clienta envíe uno por WhatsApp, aparecerá aquí.</p>
        </div>
      <?php else: ?>
        <div class="activity-list">
          <?php foreach ($pedidosRecientes as $p): ?>
            <a class="activity-row" href="pedidos.php">
              <div class="activity-main">
                <strong><?= e($p['cliente_nombre'] ?: 'Sin nombre') ?></strong>
                <span><?= e(date('d/m/Y, g:i a', strtotime($p['creado_en']))) ?></span>
              </div>
              <div class="activity-meta">
                <span class="badge <?= e($p['estado']) ?>"><?= e(ESTADOS_PEDIDO[$p['estado']] ?? $p['estado']) ?></span>
                <span class="monto">L. <?= number_format((float) $p['total'], 2) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="dash-section" style="margin-bottom:0">
    <span class="section-label">Accesos rápidos</span>
    <div class="panel" style="padding:0.4rem 1.2rem">
      <nav class="rail-menu">
        <?php foreach ($accesos as [$url, $icono, $nombre, $descripcion]): ?>
          <a href="<?= e($url) ?>">
            <span class="rail-icono"><?= admin_icono($icono) ?></span>
            <span class="rail-texto"><strong><?= e($nombre) ?></strong><small><?= e($descripcion) ?></small></span>
            <span class="chevron"><?= admin_icono('chevron') ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
</div>

<?php admin_footer(); ?>
