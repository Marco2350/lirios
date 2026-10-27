<?php
/**
 * Pedidos enviados por WhatsApp desde el carrito (tablas pedidos /
 * pedido_items) en dos vistas:
 *  - Lista: donde se EDITA. Cada pedido tiene su estado y su forma de pago
 *    (efectivo / transferencia / tarjeta, la que eligió el cliente en el carrito),
 *    que solo se guardan al pulsar «Guardar» (nada se guarda por accidente).
 *    También se agregan pedidos manuales (mostrador, llamadas) y se eliminan los manuales.
 *  - Agenda: resumen VISUAL de solo lectura, una semana con una columna por
 *    día según la fecha de entrega, con filtros y totales. Se alimenta de
 *    los mismos datos de la Lista; cada tarjeta enlaza al pedido en la Lista.
 * Complementa a reportes.php: ahí se ven los totales históricos.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

date_default_timezone_set('America/Tegucigalpa');

verificar_csrf();

const ESTADOS = ['pendiente' => 'Pendiente', 'coordinado' => 'Coordinado', 'entregado' => 'Entregado'];
const TIPOS_ENTREGA = ['Retiro en tienda', 'Delivery a domicilio'];
const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const METODOS_PAGO = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta'];
const CANALES = ['web' => 'Web', 'tienda' => 'Tienda', 'whatsapp' => 'WhatsApp'];
const PARAMS_VOLVER = ['vista', 'estado', 'semana', 'fecha', 'entrega', 'pago', 'q', 'id', 'sinfecha'];

/** Lunes (Y-m-d) de la semana de $fecha; si no es una fecha válida, la de hoy. */
function lunes_de(?string $fecha): string
{
    $d = ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
        ? DateTime::createFromFormat('!Y-m-d', $fecha)
        : false;
    if (!$d) {
        $d = new DateTime('today');
    }
    $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
    return $d->format('Y-m-d');
}

/** URL de pedidos.php con solo los parámetros permitidos y no vacíos. */
function url_pedidos(array $params): string
{
    $limpios = array_filter(
        array_intersect_key($params, array_flip(PARAMS_VOLVER)),
        fn($v) => $v !== '' && $v !== null
    );
    return 'pedidos.php' . ($limpios ? '?' . http_build_query($limpios) : '');
}

function es_retiro(?string $tipo): bool
{
    return stripos((string) $tipo, 'retiro') !== false;
}

function es_envio(?string $tipo): bool
{
    return stripos((string) $tipo, 'delivery') !== false;
}

/**
 * Forma de pago normalizada ('tarjeta' | 'efectivo' | 'transferencia' | '') a partir del
 * texto que escribió el cliente en el carrito (antes era texto libre: "Trasferencia",
 * "link de pago", "Tarjeta de Credito"...). Vacío = sin definir.
 */
function metodo_pago(?string $texto): string
{
    $t = mb_strtolower((string) $texto, 'UTF-8');
    if ($t === '') {
        return '';
    }
    if (preg_match('/tran|tras|tans|dep[oó]sito/u', $t)) {
        return 'transferencia';
    }
    if (preg_match('/tarj|cr[eé]dito|d[eé]bito|link/u', $t)) {
        return 'tarjeta';
    }
    if (str_contains($t, 'efect')) {
        return 'efectivo';
    }
    return '';
}

/* ---------- Acciones (POST) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    parse_str((string) ($_POST['volver'] ?? ''), $volver);
    $urlVolver = url_pedidos($volver);
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'guardar_pedido') {
        $estado = $_POST['estado'] ?? '';
        $metodoNuevo = $_POST['metodo_pago'] ?? '';
        $stmt = db()->prepare('SELECT forma_pago FROM pedidos WHERE id = ?');
        $stmt->execute([$id]);
        $formaActual = $stmt->fetchColumn();
        if ($formaActual !== false && isset(ESTADOS[$estado])) {
            db()->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')->execute([$estado, $id]);
            // La forma de pago solo se reescribe si la persona la cambió: así no se pierde
            // lo que el cliente escribió (ej. "link de pago") al guardar solo el estado.
            if ($metodoNuevo !== metodo_pago($formaActual) && ($metodoNuevo === '' || isset(METODOS_PAGO[$metodoNuevo]))) {
                db()->prepare('UPDATE pedidos SET forma_pago = ? WHERE id = ?')
                    ->execute([$metodoNuevo === '' ? null : METODOS_PAGO[$metodoNuevo], $id]);
            }
            flash('ok', 'Pedido guardado.');
        }
        header('Location: ' . $urlVolver);
        exit;
    }

    if ($accion === 'agregar_manual') {
        $fecha = $_POST['fecha_entrega'] ?? '';
        $fechaValida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && DateTime::createFromFormat('!Y-m-d', $fecha);
        $descripcion = limpiar_texto($_POST['descripcion'] ?? '', 500);
        $tipo = in_array($_POST['tipo_entrega'] ?? '', TIPOS_ENTREGA, true) ? $_POST['tipo_entrega'] : null;

        if (!$fechaValida || $descripcion === '') {
            flash('error', 'Para agregar un pedido necesitas al menos la fecha y la información (qué se pidió).');
            header('Location: ' . $urlVolver);
            exit;
        }

        $metodo = $_POST['metodo_pago'] ?? '';
        $total = limpiar_precio($_POST['total'] ?? 0);
        $canal = in_array($_POST['canal'] ?? '', ['tienda', 'whatsapp'], true) ? $_POST['canal'] : 'tienda';

        db()->prepare(
            'INSERT INTO pedidos (cliente_nombre, telefono, fecha_entrega, hora_entrega, tipo_entrega, estado, total,
                                  forma_pago, origen, descripcion, canal, vendedor, nota)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'manual\', ?, ?, ?, ?)'
        )->execute([
            limpiar_texto($_POST['cliente_nombre'] ?? '', 150) ?: null,
            limpiar_texto($_POST['telefono'] ?? '', 30) ?: null,
            $fecha,
            limpiar_texto($_POST['hora_entrega'] ?? '', 20) ?: null,
            $tipo,
            'coordinado',
            $total,
            isset(METODOS_PAGO[$metodo]) ? METODOS_PAGO[$metodo] : null,
            $descripcion,
            $canal,
            limpiar_texto($_POST['vendedor'] ?? '', 100) ?: null,
            limpiar_texto($_POST['nota'] ?? '', 500) ?: null,
        ]);
        flash('ok', 'Pedido agregado.');
        // Desde la agenda se vuelve a la semana del pedido nuevo; desde la lista, a la lista;
        // desde la hoja del día, al día del pedido nuevo.
        if (($volver['vista'] ?? '') === 'lista') {
            header('Location: ' . $urlVolver);
        } elseif (($volver['vista'] ?? 'dia') === 'dia') {
            header('Location: ' . url_pedidos(['vista' => 'dia', 'fecha' => $fecha]));
        } else {
            header('Location: ' . url_pedidos(['vista' => 'agenda', 'semana' => lunes_de($fecha)]));
        }
        exit;
    }

    if ($accion === 'marcar_realizado') {
        // «Realizado» de la hoja del día = estado entregado; desmarcar lo regresa a coordinado.
        db()->prepare(
            "UPDATE pedidos SET estado = IF(estado = 'entregado', 'coordinado', 'entregado') WHERE id = ?"
        )->execute([$id]);
        header('Location: ' . $urlVolver);
        exit;
    }

    if ($accion === 'eliminar_manual') {
        // Solo los pedidos manuales se pueden borrar; los del carrito se conservan siempre.
        db()->prepare('DELETE FROM pedidos WHERE id = ? AND origen = \'manual\'')->execute([$id]);
        flash('ok', 'Pedido eliminado.');
        header('Location: ' . url_pedidos(['vista' => $volver['vista'] ?? 'lista', 'estado' => $volver['estado'] ?? '', 'semana' => $volver['semana'] ?? '', 'fecha' => $volver['fecha'] ?? '']));
        exit;
    }

    header('Location: pedidos.php');
    exit;
}

/* ---------- Filtros y datos ---------- */

$vista = in_array($_GET['vista'] ?? '', ['agenda', 'lista'], true) ? $_GET['vista'] : 'dia';
$estadoFiltro = isset(ESTADOS[$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
$entregaFiltro = in_array($_GET['entrega'] ?? '', ['retiro', 'envio'], true) ? $_GET['entrega'] : '';
$q = limpiar_texto($_GET['q'] ?? '', 80);
$idFiltro = (int) ($_GET['id'] ?? 0);
$soloSinFecha = !empty($_GET['sinfecha']);
$pagoFiltro = (isset(METODOS_PAGO[$_GET['pago'] ?? '']) || ($_GET['pago'] ?? '') === 'sin') ? $_GET['pago'] : '';

/** Agrega al WHERE los filtros comunes (estado, entrega, búsqueda). */
function aplicar_filtros(string &$sql, array &$params, string $estado, string $entrega, string $q): void
{
    if ($estado !== '') {
        $sql .= ' AND estado = :estado';
        $params['estado'] = $estado;
    }
    if ($entrega === 'retiro') {
        $sql .= " AND tipo_entrega LIKE '%retiro%'";
    } elseif ($entrega === 'envio') {
        $sql .= " AND tipo_entrega LIKE '%delivery%'";
    }
    if ($q !== '') {
        $sql .= ' AND (cliente_nombre LIKE :q1 OR telefono LIKE :q2 OR descripcion LIKE :q3)';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $params['q1'] = $params['q2'] = $params['q3'] = $like;
    }
}

/** Carga los pedido_items de una lista de pedidos, agrupados por pedido_id. */
function items_de_pedidos(array $pedidos): array
{
    $porPedido = [];
    if ($pedidos) {
        $ids = array_column($pedidos, 'id');
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT * FROM pedido_items WHERE pedido_id IN ($marcadores) ORDER BY id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $it) {
            $porPedido[$it['pedido_id']][] = $it;
        }
    }
    return $porPedido;
}

if ($vista === 'lista') {
    $sql = 'SELECT * FROM pedidos WHERE 1=1';
    $params = [];
    if ($idFiltro > 0) {
        $sql .= ' AND id = :id';
        $params['id'] = $idFiltro;
    }
    if ($soloSinFecha) {
        $sql .= ' AND fecha_entrega IS NULL';
    }
    aplicar_filtros($sql, $params, $estadoFiltro, $entregaFiltro, $q);
    $sql .= ' ORDER BY creado_en DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
    if ($pagoFiltro !== '') {
        $pedidos = array_values(array_filter(
            $pedidos,
            fn($p) => metodo_pago($p['forma_pago']) === ($pagoFiltro === 'sin' ? '' : $pagoFiltro)
        ));
    }
    $conteoEstados = db()->query('SELECT estado, COUNT(*) c FROM pedidos GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
    $itemsPorPedido = items_de_pedidos($pedidos);
} elseif ($vista === 'dia') {
    $hoy = date('Y-m-d');
    $f = $_GET['fecha'] ?? '';
    $diaSel = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && DateTime::createFromFormat('!Y-m-d', $f)) ? $f : $hoy;
    $diaPrev = (new DateTime($diaSel))->modify('-1 day')->format('Y-m-d');
    $diaSig = (new DateTime($diaSel))->modify('+1 day')->format('Y-m-d');
    $lunes = lunes_de($diaSel);
    $domingo = (new DateTime($lunes))->modify('+6 days')->format('Y-m-d');

    // Hoja del día: todos los pedidos (web + manuales) con esa fecha de entrega, sin filtros,
    // para que el total del día siempre sea el real.
    $stmt = db()->prepare(
        'SELECT * FROM pedidos WHERE fecha_entrega = :f ORDER BY hora_entrega IS NULL, hora_entrega, id'
    );
    $stmt->execute(['f' => $diaSel]);
    $pedidosDia = $stmt->fetchAll();
    $itemsPorPedido = items_de_pedidos($pedidosDia);

    // Columnas de la hoja: lo que dice cada pedido en su forma de pago va en su columna.
    $totDia = ['tarjeta' => 0.0, 'efectivo' => 0.0, 'transferencia' => 0.0, '' => 0.0];
    foreach ($pedidosDia as $p) {
        $totDia[metodo_pago($p['forma_pago'])] += (float) $p['total'];
    }
    $totalDia = array_sum($totDia);

    // Semana (lunes a domingo) del día elegido: total por día.
    $stmt = db()->prepare('SELECT fecha_entrega, total FROM pedidos WHERE fecha_entrega BETWEEN :d AND :h');
    $stmt->execute(['d' => $lunes, 'h' => $domingo]);
    $semanaDias = [];
    for ($i = 0; $i < 7; $i++) {
        $semanaDias[(new DateTime($lunes))->modify("+$i days")->format('Y-m-d')] = ['n' => 0, 'total' => 0.0];
    }
    foreach ($stmt->fetchAll() as $r) {
        $semanaDias[$r['fecha_entrega']]['n']++;
        $semanaDias[$r['fecha_entrega']]['total'] += (float) $r['total'];
    }
    $totalSemana = array_sum(array_column($semanaDias, 'total'));
    $pedidosSemanaN = array_sum(array_column($semanaDias, 'n'));
} else {
    $lunes = lunes_de($_GET['semana'] ?? null);
    $domingo = (new DateTime($lunes))->modify('+6 days')->format('Y-m-d');
    $semanaPrev = (new DateTime($lunes))->modify('-7 days')->format('Y-m-d');
    $semanaSig = (new DateTime($lunes))->modify('+7 days')->format('Y-m-d');
    $hoy = date('Y-m-d');
    $lunesActual = lunes_de($hoy);

    $sql = 'SELECT * FROM pedidos WHERE fecha_entrega BETWEEN :desde AND :hasta';
    $params = ['desde' => $lunes, 'hasta' => $domingo];
    aplicar_filtros($sql, $params, $estadoFiltro, $entregaFiltro, $q);
    $sql .= ' ORDER BY fecha_entrega, hora_entrega IS NULL, hora_entrega, id';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $pedidosSemana = $stmt->fetchAll();
    if ($pagoFiltro !== '') {
        // La forma de pago venía como texto libre, así que se filtra ya normalizada.
        $pedidosSemana = array_values(array_filter(
            $pedidosSemana,
            fn($p) => metodo_pago($p['forma_pago']) === ($pagoFiltro === 'sin' ? '' : $pagoFiltro)
        ));
    }
    $itemsPorPedido = items_de_pedidos($pedidosSemana);

    $porDia = [];
    foreach ($pedidosSemana as $p) {
        $porDia[$p['fecha_entrega']][] = $p;
    }

    $sinFechaCount = (int) db()->query("SELECT COUNT(*) FROM pedidos WHERE fecha_entrega IS NULL AND estado <> 'entregado'")->fetchColumn();

    // Totales de la semana (sobre lo que muestran los filtros)
    $totValor = 0.0;
    $totPorMetodo = ['efectivo' => 0.0, 'transferencia' => 0.0, 'tarjeta' => 0.0, '' => 0.0];
    foreach ($pedidosSemana as $p) {
        $totValor += (float) $p['total'];
        $totPorMetodo[metodo_pago($p['forma_pago'])] += (float) $p['total'];
    }
}

/** Texto corto de los productos de un pedido ("Ramo de rosas +2 más"). */
function resumen_productos(array $p, array $itemsPorPedido): string
{
    if ($p['origen'] === 'manual') {
        return (string) $p['descripcion'];
    }
    $items = $itemsPorPedido[$p['id']] ?? [];
    if (!$items) {
        return '—';
    }
    $texto = ((int) $items[0]['cantidad'] > 1 ? (int) $items[0]['cantidad'] . '× ' : '') . $items[0]['nombre'];
    return count($items) > 1 ? $texto . ' +' . (count($items) - 1) . ' más' : $texto;
}

/** Datos para volver a esta misma pantalla tras guardar (campo oculto "volver"). */
$volverQs = http_build_query(array_intersect_key($_GET, array_flip(PARAMS_VOLVER)));
if ($vista === 'dia') {
    $volverQs = http_build_query(['vista' => 'dia', 'fecha' => $diaSel]);
}

/* ---------- Ayudas de presentación ---------- */

const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const DIAS_LARGOS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

/** Íconos de línea (SVG inline, heredan el color del texto). */
function ico(string $nombre): string
{
    $trazos = [
        'buscar' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'tel' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 0 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'izq' => '<path d="m15 18-6-6 6-6"/>',
        'der' => '<path d="m9 18 6-6-6-6"/>',
        'mas' => '<path d="M12 5v14M5 12h14"/>',
        'regalo' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8M8.5 8a2.5 2.5 0 1 1 0-5C11 3 12 8 12 8s1-5 3.5-5a2.5 2.5 0 1 1 0 5"/>',
        'nota' => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h5"/>',
        'cerrar' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'wa' => '<path d="M12 3a9 9 0 0 0-7.7 13.6L3 21l4.5-1.2A9 9 0 1 0 12 3z"/><path d="M9 8.5c.3 2.6 2.9 5.2 5.5 5.5l1.3-1.3-2-1-.8.7c-.9-.4-1.8-1.3-2.2-2.2l.7-.8-1-2z"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($trazos[$nombre] ?? '') . '</svg>';
}

/** Número para wa.me (solo dígitos; a los de 8 dígitos se les antepone 504, Honduras). */
function numero_wa(?string $tel): string
{
    $d = preg_replace('/\D+/', '', (string) $tel);
    if (strlen($d) === 8) {
        $d = '504' . $d;
    }
    return strlen($d) >= 10 ? $d : '';
}

function fecha_corta(string $ymd): string
{
    $t = strtotime($ymd);
    return (int) date('j', $t) . ' ' . MESES_CORTOS[(int) date('n', $t) - 1];
}

/** Píldora de forma de pago. */
function pill_pago(?string $forma): string
{
    $m = metodo_pago($forma);
    return $m
        ? '<span class="pill pago-' . e($m) . '">' . e(METODOS_PAGO[$m]) . '</span>'
        : '<span class="pill pago-sin">Pago sin definir</span>';
}

/** Punto + texto del estado. */
function tag_estado(string $estado): string
{
    return '<span class="estado est-' . e($estado) . '"><i></i>' . e(ESTADOS[$estado] ?? $estado) . '</span>';
}

admin_header('Pedidos', 'pedidos.php');
?>

<!-- ============ Barra superior: vistas, buscador, filtros y acción principal ============ -->
<form class="ped-toolbar" method="get" action="pedidos.php" role="search">
  <input type="hidden" name="vista" value="<?= e($vista) ?>">
  <?php if ($vista === 'agenda'): ?><input type="hidden" name="semana" value="<?= e($lunes) ?>"><?php endif; ?>
  <?php if ($vista === 'dia'): ?><input type="hidden" name="fecha" value="<?= e($diaSel) ?>"><?php endif; ?>
  <?php if ($vista === 'lista' && $estadoFiltro): ?><input type="hidden" name="estado" value="<?= e($estadoFiltro) ?>"><?php endif; ?>

  <div class="seg" role="tablist" aria-label="Vista">
    <a href="<?= e(url_pedidos(['vista' => 'dia'])) ?>" role="tab" aria-selected="<?= $vista === 'dia' ? 'true' : 'false' ?>">Día</a>
    <a href="<?= e(url_pedidos(['vista' => 'agenda'])) ?>" role="tab" aria-selected="<?= $vista === 'agenda' ? 'true' : 'false' ?>">Agenda</a>
    <a href="<?= e(url_pedidos(['vista' => 'lista'])) ?>" role="tab" aria-selected="<?= $vista === 'lista' ? 'true' : 'false' ?>">Lista</a>
  </div>

  <?php if ($vista !== 'dia'): ?>
  <label class="ped-buscar">
    <?= ico('buscar') ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o teléfono" aria-label="Buscar cliente">
  </label>

  <?php if ($vista === 'agenda'): ?>
    <select name="estado" onchange="this.form.submit()" aria-label="Filtrar por estado" class="<?= $estadoFiltro ? 'activo' : '' ?>">
      <option value="">Estado</option>
      <?php foreach (ESTADOS as $k => $etq): ?><option value="<?= e($k) ?>" <?= $estadoFiltro === $k ? 'selected' : '' ?>><?= e($etq) ?></option><?php endforeach; ?>
    </select>
  <?php endif; ?>
  <select name="entrega" onchange="this.form.submit()" aria-label="Filtrar por recoge o envío" class="<?= $entregaFiltro ? 'activo' : '' ?>">
    <option value="">Entrega</option>
    <option value="retiro" <?= $entregaFiltro === 'retiro' ? 'selected' : '' ?>>Solo recoge</option>
    <option value="envio" <?= $entregaFiltro === 'envio' ? 'selected' : '' ?>>Solo envío</option>
  </select>
  <select name="pago" onchange="this.form.submit()" aria-label="Filtrar por forma de pago" class="<?= $pagoFiltro ? 'activo' : '' ?>">
    <option value="">Pago</option>
    <?php foreach (METODOS_PAGO as $k => $etq): ?><option value="<?= e($k) ?>" <?= $pagoFiltro === $k ? 'selected' : '' ?>><?= e($etq) ?></option><?php endforeach; ?>
    <option value="sin" <?= $pagoFiltro === 'sin' ? 'selected' : '' ?>>Sin definir</option>
  </select>
  <?php endif; ?>

  <?php if (($estadoFiltro && $vista === 'agenda') || $entregaFiltro || $pagoFiltro || $q || $idFiltro || $soloSinFecha): ?>
    <a class="ped-limpiar" href="<?= e(url_pedidos($vista === 'agenda' ? ['vista' => 'agenda', 'semana' => $lunes] : ['vista' => 'lista', 'estado' => $estadoFiltro])) ?>">Quitar filtros</a>
  <?php endif; ?>

  <button type="button" class="btn ped-nuevo" data-abrir-modal="modal-pedido-manual"><?= ico('mas') ?> Pedido</button>
</form>

<?php if ($vista === 'dia'): /* ============ Hoja del día ============ */ ?>

<?php
    $volverDia = http_build_query(['vista' => 'dia', 'fecha' => $diaSel]);
    $etqDia = DIAS_LARGOS[(int) date('N', strtotime($diaSel)) - 1] . ' ' . (int) date('j', strtotime($diaSel)) . ' de '
        . ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int) date('n', strtotime($diaSel)) - 1]
        . ' de ' . date('Y', strtotime($diaSel));
    $hayPagoSinDefinir = $totDia[''] > 0;
    $monto = fn(float $v) => $v > 0 ? number_format($v, 2) : '';
?>

<div class="ag-cabecera">
  <div class="ag-nav">
    <a class="icon-btn" href="<?= e(url_pedidos(['vista' => 'dia', 'fecha' => $diaPrev])) ?>" aria-label="Día anterior"><?= ico('izq') ?></a>
    <div class="ag-rango" aria-live="polite">
      <strong><?= e($etqDia) ?></strong>
      <small><?= $diaSel === $hoy ? 'Hoy' : '' ?></small>
    </div>
    <a class="icon-btn" href="<?= e(url_pedidos(['vista' => 'dia', 'fecha' => $diaSig])) ?>" aria-label="Día siguiente"><?= ico('der') ?></a>
    <?php if ($diaSel !== $hoy): ?>
      <a class="btn mini secundario" href="<?= e(url_pedidos(['vista' => 'dia'])) ?>">Hoy</a>
    <?php endif; ?>
    <form method="get" action="pedidos.php" class="dia-ir">
      <input type="hidden" name="vista" value="dia">
      <input type="date" name="fecha" value="<?= e($diaSel) ?>" onchange="this.form.submit()" aria-label="Ir a una fecha">
    </form>
  </div>
</div>

<section class="dia-resumen" aria-label="Total del día">
  <div><span>Tarjeta</span><strong>L. <?= number_format($totDia['tarjeta'], 2) ?></strong></div>
  <div><span>Efectivo</span><strong>L. <?= number_format($totDia['efectivo'], 2) ?></strong></div>
  <div><span>Transferencia</span><strong>L. <?= number_format($totDia['transferencia'], 2) ?></strong></div>
  <?php if ($hayPagoSinDefinir): ?><div><span>Sin definir</span><strong>L. <?= number_format($totDia[''], 2) ?></strong></div><?php endif; ?>
  <div class="dia-resumen-total"><span>Total del día</span><strong>L. <?= number_format($totalDia, 2) ?></strong></div>
</section>

<div class="dia-wrap">
  <table class="dia-tabla">
    <thead>
      <tr>
        <th class="c-n">N°</th>
        <th class="c-info">Información</th>
        <th class="c-chk">Realizado</th>
        <th class="c-chk">Recoge</th>
        <th class="c-chk">Envío</th>
        <th class="c-monto">Tarjeta</th>
        <th class="c-monto">Efectivo</th>
        <th class="c-monto">Transferencia</th>
        <?php if ($hayPagoSinDefinir): ?><th class="c-monto">Sin definir</th><?php endif; ?>
        <th class="c-det">Detalles</th>
        <th class="c-acc"><span class="sr-only">Acciones</span></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$pedidosDia): ?>
        <tr><td colspan="<?= $hayPagoSinDefinir ? 11 : 10 ?>" class="dia-vacio">No hay pedidos para este día. Usa «+ Pedido» para anotar una venta en tienda o por WhatsApp.</td></tr>
      <?php endif; ?>
      <?php foreach ($pedidosDia as $i => $p):
          $mp = metodo_pago($p['forma_pago']);
          $hecho = $p['estado'] === 'entregado';
          $detalles = array_filter([
              $p['hora_entrega'] ? 'Hora: ' . $p['hora_entrega'] : '',
              $p['nota'],
              $p['direccion'] && es_envio($p['tipo_entrega']) ? $p['direccion'] : '',
              $p['dedicatoria'] ? 'Dedicatoria: ' . $p['dedicatoria'] : '',
          ]);
          ?>
        <tr class="<?= $hecho ? 'dia-hecho' : '' ?>">
          <td class="c-n"><?= $i + 1 ?></td>
          <td class="c-info">
            <a href="<?= e(url_pedidos(['vista' => 'lista', 'id' => $p['id']])) ?>"><strong><?= e($p['cliente_nombre'] ?: 'Sin nombre') ?></strong></a>
            <span class="dia-prod"><?= e(resumen_productos($p, $itemsPorPedido)) ?></span>
            <span class="dia-canal canal-<?= e($p['canal'] ?? 'web') ?>"><?= e(CANALES[$p['canal'] ?? 'web'] ?? 'Web') ?><?= !empty($p['vendedor']) ? ' · ' . e($p['vendedor']) : '' ?></span>
          </td>
          <td class="c-chk">
            <form method="post" action="pedidos.php">
              <?= csrf_field() ?>
              <input type="hidden" name="accion" value="marcar_realizado">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="volver" value="<?= e($volverDia) ?>">
              <button type="submit" class="dia-check <?= $hecho ? 'on' : '' ?>" aria-pressed="<?= $hecho ? 'true' : 'false' ?>"
                      title="<?= $hecho ? 'Marcado como realizado (clic para desmarcar)' : 'Marcar como realizado' ?>"><span class="sr-only">Realizado</span></button>
            </form>
          </td>
          <td class="c-chk"><?= es_retiro($p['tipo_entrega']) ? '<span class="dia-marca" title="Recoge en tienda">✓</span>' : '' ?></td>
          <td class="c-chk"><?= es_envio($p['tipo_entrega']) ? '<span class="dia-marca" title="Envío">✓</span>' : '' ?></td>
          <td class="c-monto"><?= $mp === 'tarjeta' ? $monto((float) $p['total']) : '' ?></td>
          <td class="c-monto"><?= $mp === 'efectivo' ? $monto((float) $p['total']) : '' ?></td>
          <td class="c-monto"><?= $mp === 'transferencia' ? $monto((float) $p['total']) : '' ?></td>
          <?php if ($hayPagoSinDefinir): ?><td class="c-monto"><?= $mp === '' ? $monto((float) $p['total']) : '' ?></td><?php endif; ?>
          <td class="c-det"><?= e(implode(' · ', $detalles)) ?></td>
          <td class="c-acc">
            <?php if ($p['origen'] === 'manual'): ?>
              <button type="button" class="btn mini peligro" data-confirmar-eliminar="dia-del-<?= (int) $p['id'] ?>"
                      data-confirmar-mensaje="¿Eliminar este pedido manual? Esta acción no se puede deshacer.">Eliminar</button>
              <form id="dia-del-<?= (int) $p['id'] ?>" method="post" action="pedidos.php">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="eliminar_manual">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="volver" value="<?= e($volverDia) ?>">
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th class="c-n"></th>
        <th class="c-info">TOTAL DÍA</th>
        <th class="c-chk"></th><th class="c-chk"></th><th class="c-chk"></th>
        <th class="c-monto"><?= number_format($totDia['tarjeta'], 2) ?></th>
        <th class="c-monto"><?= number_format($totDia['efectivo'], 2) ?></th>
        <th class="c-monto"><?= number_format($totDia['transferencia'], 2) ?></th>
        <?php if ($hayPagoSinDefinir): ?><th class="c-monto"><?= number_format($totDia[''], 2) ?></th><?php endif; ?>
        <th class="c-det c-total">L. <?= number_format($totalDia, 2) ?></th>
        <th class="c-acc"></th>
      </tr>
    </tfoot>
  </table>
</div>

<section class="dia-semana" aria-label="Total de la semana">
  <h3>Semana del <?= (int) date('j', strtotime($lunes)) ?> al <?= (int) date('j', strtotime($domingo)) . ' de ' . MESES_CORTOS[(int) date('n', strtotime($domingo)) - 1] ?></h3>
  <div class="dia-semana-grid">
    <?php foreach ($semanaDias as $f => $d): $i = (int) date('N', strtotime($f)) - 1; ?>
      <a class="dia-semana-dia <?= $f === $diaSel ? 'on' : '' ?> <?= $f === $hoy ? 'hoy' : '' ?>" href="<?= e(url_pedidos(['vista' => 'dia', 'fecha' => $f])) ?>">
        <span><?= e(DIAS_CORTOS[$i]) ?> <?= (int) date('j', strtotime($f)) ?></span>
        <strong>L. <?= number_format($d['total'], 2) ?></strong>
        <small><?= $d['n'] ?> <?= $d['n'] === 1 ? 'pedido' : 'pedidos' ?></small>
      </a>
    <?php endforeach; ?>
    <div class="dia-semana-total">
      <span>Total de la semana</span>
      <strong>L. <?= number_format($totalSemana, 2) ?></strong>
      <small><?= (int) $pedidosSemanaN ?> <?= $pedidosSemanaN === 1 ? 'pedido' : 'pedidos' ?></small>
    </div>
  </div>
</section>

<?php elseif ($vista === 'agenda'): ?>

<?php
    $nombreMes = fn(string $ymd) => MESES_CORTOS[(int) date('n', strtotime($ymd)) - 1];
    $rango = (int) date('j', strtotime($lunes)) . ($nombreMes($lunes) !== $nombreMes($domingo) ? ' ' . $nombreMes($lunes) : '')
        . ' – ' . (int) date('j', strtotime($domingo)) . ' ' . $nombreMes($domingo) . ' ' . date('Y', strtotime($domingo));
    $filtrosNav = ['vista' => 'agenda', 'estado' => $estadoFiltro, 'entrega' => $entregaFiltro, 'pago' => $pagoFiltro, 'q' => $q];
?>

<div class="ag-cabecera">
  <div class="ag-nav">
    <a class="icon-btn" href="<?= e(url_pedidos($filtrosNav + ['semana' => $semanaPrev])) ?>" aria-label="Semana anterior"><?= ico('izq') ?></a>
    <div class="ag-rango" aria-live="polite">
      <strong><?= e($rango) ?></strong>
      <small><?= $lunes === $lunesActual ? 'Esta semana' : '' ?></small>
    </div>
    <a class="icon-btn" href="<?= e(url_pedidos($filtrosNav + ['semana' => $semanaSig])) ?>" aria-label="Semana siguiente"><?= ico('der') ?></a>
    <?php if ($lunes !== $lunesActual): ?>
      <a class="btn mini secundario" href="<?= e(url_pedidos($filtrosNav)) ?>">Hoy</a>
    <?php endif; ?>
  </div>

  <?php if ($sinFechaCount): ?>
    <a class="chip-aviso" href="pedidos.php?vista=lista&amp;sinfecha=1" title="Pedidos que no tienen fecha de entrega y por eso no salen en la agenda"><?= $sinFechaCount ?> sin fecha de entrega →</a>
  <?php endif; ?>
</div>

<section class="ag-resumen" aria-label="Resumen de la semana">
  <div class="ag-resumen-principal">
    <strong><?= count($pedidosSemana) ?></strong> <span><?= count($pedidosSemana) === 1 ? 'pedido' : 'pedidos' ?></span>
    <em>L. <?= number_format($totValor, 2) ?></em>
  </div>
  <?php if ($totValor > 0): ?>
    <div class="ag-barra" role="img" aria-label="Reparto del valor por forma de pago">
      <?php foreach (['efectivo', 'transferencia', 'tarjeta', ''] as $m): if ($totPorMetodo[$m] <= 0) continue; ?>
        <span class="pago-<?= $m === '' ? 'sin' : e($m) ?>" style="width:<?= round($totPorMetodo[$m] / $totValor * 100, 2) ?>%"></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <ul class="ag-leyenda">
    <?php foreach (METODOS_PAGO as $m => $etq): ?>
      <li><i class="pago-<?= e($m) ?>"></i><?= e($etq) ?> <strong>L. <?= number_format($totPorMetodo[$m]) ?></strong></li>
    <?php endforeach; ?>
    <?php if ($totPorMetodo[''] > 0): ?>
      <li><i class="pago-sin"></i>Sin definir <strong>L. <?= number_format($totPorMetodo['']) ?></strong></li>
    <?php endif; ?>
  </ul>
</section>

<div class="ag-wrap">
  <div class="ag-grid">
    <?php for ($i = 0; $i < 7; $i++):
        $fecha = (new DateTime($lunes))->modify("+$i days")->format('Y-m-d');
        $delDia = $porDia[$fecha] ?? [];
        $totalDia = array_sum(array_map(fn($p) => (float) $p['total'], $delDia));
        ?>
      <section class="ag-col <?= $fecha === $hoy ? 'ag-hoy' : '' ?> <?= $delDia ? '' : 'ag-vacio' ?>" aria-label="<?= e(DIAS_LARGOS[$i] . ' ' . fecha_corta($fecha)) ?>">
        <header class="ag-col-cab">
          <span class="ag-dia-nombre"><?= e(DIAS_CORTOS[$i]) ?></span>
          <span class="ag-dia-num"><?= (int) date('j', strtotime($fecha)) ?></span>
          <span class="ag-dia-mes"><?= e($nombreMes($fecha)) ?></span>
          <?php if ($delDia): ?><span class="ag-dia-total">L. <?= number_format($totalDia) ?></span><?php endif; ?>
        </header>
        <div class="ag-cards">
          <?php if (!$delDia): ?><p class="ag-nada">Sin pedidos</p><?php endif; ?>
          <?php foreach ($delDia as $p): ?>
            <a class="ag-card est-<?= e($p['estado']) ?>" href="<?= e(url_pedidos(['vista' => 'lista', 'id' => $p['id']])) ?>" data-ped="<?= (int) $p['id'] ?>">
              <div class="ag-card-top">
                <span class="ag-hora"><?= e($p['hora_entrega'] ?: 'Sin hora') ?></span>
                <?= tag_estado($p['estado']) ?>
              </div>
              <strong class="ag-cliente"><?= e($p['cliente_nombre'] ?: 'Sin nombre') ?></strong>
              <span class="ag-prod"><?= e(resumen_productos($p, $itemsPorPedido)) ?></span>
              <div class="ag-card-pie">
                <span class="ag-monto">L. <?= number_format((float) $p['total']) ?></span>
                <?= pill_pago($p['forma_pago']) ?>
                <?php if (es_retiro($p['tipo_entrega'])): ?><span class="pill neutro">Recoge</span>
                <?php elseif (es_envio($p['tipo_entrega'])): ?><span class="pill neutro">Envío</span><?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endfor; ?>
  </div>
</div>

<?php if (!$pedidosSemana && ($estadoFiltro || $entregaFiltro || $pagoFiltro || $q)): ?>
  <p class="ag-sinresultados">Ningún pedido de esta semana coincide con los filtros.</p>
<?php endif; ?>

<?php
    // Datos de los pedidos de la semana para el panel de detalle (se rellena con textContent, sin HTML).
    $datosDetalle = [];
    foreach ($pedidosSemana as $p) {
        $mp = metodo_pago($p['forma_pago']);
        $datosDetalle[$p['id']] = [
            'id' => (int) $p['id'],
            'cliente' => $p['cliente_nombre'] ?: 'Sin nombre',
            'telefono' => (string) $p['telefono'],
            'wa' => numero_wa($p['telefono']),
            'fecha' => DIAS_LARGOS[(int) date('N', strtotime($p['fecha_entrega'])) - 1] . ' ' . fecha_corta($p['fecha_entrega']),
            'hora' => (string) $p['hora_entrega'],
            'tipo' => (string) $p['tipo_entrega'],
            'direccion' => (string) $p['direccion'],
            'dedicatoria' => (string) $p['dedicatoria'],
            'nota' => (string) $p['nota'],
            'pago' => $mp ? METODOS_PAGO[$mp] : ((string) $p['forma_pago'] ?: 'Pago sin definir'),
            'total' => 'L. ' . number_format((float) $p['total'], 2),
            'estado' => ESTADOS[$p['estado']] ?? $p['estado'],
            'estadoClave' => $p['estado'],
            'manual' => $p['origen'] === 'manual',
            'descripcion' => (string) $p['descripcion'],
            'items' => array_map(fn($it) => [
                'n' => (int) $it['cantidad'] . '× ' . $it['nombre'],
                'c' => (string) $it['codigo'],
                's' => 'L. ' . number_format((float) $it['subtotal'], 2),
            ], $itemsPorPedido[$p['id']] ?? []),
            'lista' => url_pedidos(['vista' => 'lista', 'id' => $p['id']]),
        ];
    }
?>

<dialog class="modal-detalle" id="modal-detalle" aria-labelledby="det-cliente">
  <button type="button" class="icon-btn det-cerrar" data-cerrar-modal aria-label="Cerrar"><?= ico('cerrar') ?></button>
  <p class="det-fecha" id="det-fecha"></p>
  <h2 id="det-cliente"></h2>
  <div class="det-chips" id="det-chips"></div>
  <dl class="det-datos" id="det-datos"></dl>
  <div class="det-items" id="det-items"></div>
  <div class="det-total"><span>Total</span><strong id="det-total"></strong></div>
  <div class="det-acciones">
    <a class="btn mini secundario" id="det-wa" target="_blank" rel="noopener"><?= ico('wa') ?> WhatsApp</a>
    <a class="btn mini" id="det-lista">Editar en la Lista</a>
  </div>
</dialog>

<script>
(function () {
  var datos = <?= json_encode($datosDetalle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  var dlg = document.getElementById('modal-detalle');
  function el(tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; }
  function fila(dl, k, v) { if (!v) return; dl.appendChild(el('dt', null, k)); dl.appendChild(el('dd', null, v)); }

  document.querySelectorAll('.ag-card[data-ped]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      var d = datos[a.dataset.ped];
      if (!d || !dlg.showModal) return;       // sin soporte de <dialog>: sigue el enlace a la Lista
      ev.preventDefault();
      document.getElementById('det-fecha').textContent = d.fecha + (d.hora ? ' · ' + d.hora : '');
      document.getElementById('det-cliente').textContent = d.cliente;
      var chips = document.getElementById('det-chips'); chips.textContent = '';
      var est = el('span', 'estado est-' + d.estadoClave); est.appendChild(el('i')); est.appendChild(document.createTextNode(d.estado));
      chips.appendChild(est);
      if (d.tipo) chips.appendChild(el('span', 'pill neutro', d.tipo));
      chips.appendChild(el('span', 'pill', d.pago));
      if (d.manual) chips.appendChild(el('span', 'pill neutro', 'Pedido manual'));
      var dl = document.getElementById('det-datos'); dl.textContent = '';
      fila(dl, 'Teléfono', d.telefono); fila(dl, 'Dirección', d.direccion);
      fila(dl, 'Dedicatoria', d.dedicatoria); fila(dl, 'Nota', d.nota);
      var it = document.getElementById('det-items'); it.textContent = '';
      if (d.manual && d.descripcion) { it.appendChild(el('p', 'det-item', d.descripcion)); }
      d.items.forEach(function (i) {
        var p = el('p', 'det-item'); p.appendChild(el('span', null, i.n));
        if (i.c) p.appendChild(el('code', null, i.c));
        p.appendChild(el('b', null, i.s)); it.appendChild(p);
      });
      document.getElementById('det-total').textContent = d.total;
      var wa = document.getElementById('det-wa');
      if (d.wa) { wa.href = 'https://wa.me/' + d.wa; wa.hidden = false; } else { wa.hidden = true; }
      document.getElementById('det-lista').href = d.lista;
      dlg.showModal();
    });
  });
})();
</script>

<?php else: /* ============ Vista Lista (editable) ============ */ ?>

<?php $totalPedidos = array_sum($conteoEstados); ?>
<nav class="ped-tabs" aria-label="Estado del pedido">
  <a href="<?= e(url_pedidos(['vista' => 'lista', 'q' => $q, 'entrega' => $entregaFiltro, 'pago' => $pagoFiltro])) ?>" class="<?= $estadoFiltro === '' ? 'on' : '' ?>">Todos <span><?= (int) $totalPedidos ?></span></a>
  <?php foreach (ESTADOS as $clave => $etq): ?>
    <a href="<?= e(url_pedidos(['vista' => 'lista', 'estado' => $clave, 'q' => $q, 'entrega' => $entregaFiltro, 'pago' => $pagoFiltro])) ?>" class="<?= $estadoFiltro === $clave ? 'on' : '' ?>"><?= tag_estado($clave) ?> <span><?= (int) ($conteoEstados[$clave] ?? 0) ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if ($soloSinFecha): ?><p class="ped-nota">Mostrando solo los pedidos que no tienen fecha de entrega.</p><?php endif; ?>
<?php if ($idFiltro): ?><p class="ped-nota">Mostrando un solo pedido.</p><?php endif; ?>

<?php if (!$pedidos): ?>
  <div class="ped-vacio"><?= ico('regalo') ?><p>No hay pedidos con esos filtros.</p></div>
<?php else: ?>
  <div class="ped-lista">
    <?php foreach ($pedidos as $p):
        $fid = 'ped-' . $p['id'];
        $mpActual = metodo_pago($p['forma_pago']);
        $items = $itemsPorPedido[$p['id']] ?? [];
        ?>
      <article class="ped est-<?= e($p['estado']) ?>" id="<?= e($fid) ?>-fila">
        <div class="ped-fecha">
          <?php if ($p['fecha_entrega']): ?>
            <b><?= (int) date('j', strtotime($p['fecha_entrega'])) ?></b>
            <span><?= e(MESES_CORTOS[(int) date('n', strtotime($p['fecha_entrega'])) - 1]) ?></span>
            <small><?= e($p['hora_entrega'] ?: 'Sin hora') ?></small>
          <?php else: ?>
            <span class="ped-sinfecha">Sin fecha</span>
          <?php endif; ?>
        </div>

        <div class="ped-main">
          <h3>
            <?= e($p['cliente_nombre'] ?: 'Sin nombre') ?>
            <?php if ($p['origen'] === 'manual'): ?><span class="pill neutro">Manual</span><?php endif; ?>
            <?php if (es_retiro($p['tipo_entrega'])): ?><span class="pill neutro">Recoge</span>
            <?php elseif (es_envio($p['tipo_entrega'])): ?><span class="pill neutro">Envío</span><?php endif; ?>
          </h3>
          <p class="ped-linea">
            <?php if ($p['telefono']): ?><span><?= ico('tel') ?><?= e($p['telefono']) ?></span><?php endif; ?>
            <span><?= ico('regalo') ?><?= e(resumen_productos($p, $itemsPorPedido)) ?></span>
          </p>
          <details class="ped-mas">
            <summary>Ver detalle</summary>
            <div class="ped-detalle">
              <?php if ($p['origen'] !== 'manual' && $items): ?>
                <ul>
                  <?php foreach ($items as $it): ?>
                    <li><?= (int) $it['cantidad'] ?>× <?= e($it['nombre']) ?><?php if ($it['codigo']): ?> <code><?= e($it['codigo']) ?></code><?php endif; ?> <b>L. <?= number_format((float) $it['subtotal'], 2) ?></b>
                      <?php if ($it['detalle']): ?><small class="muted"><?= e($it['detalle']) ?></small><?php endif; ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if ($p['direccion']): ?><p><?= ico('pin') ?><?= e($p['direccion']) ?></p><?php endif; ?>
              <?php if ($p['zona_delivery_nombre']): ?><p><?= ico('pin') ?>Zona: <?= e($p['zona_delivery_nombre']) ?> — L. <?= number_format((float) $p['costo_delivery'], 2) ?></p><?php endif; ?>
              <?php if ($p['dedicatoria']): ?><p><?= ico('nota') ?>Dedicatoria: <?= e($p['dedicatoria']) ?></p><?php endif; ?>
              <?php if ($p['nota']): ?><p><?= ico('nota') ?>Nota: <?= e($p['nota']) ?></p><?php endif; ?>
              <?php if ($p['forma_pago'] && $mpActual === ''): ?><p><?= ico('nota') ?>El cliente escribió como pago: «<?= e($p['forma_pago']) ?>»</p><?php endif; ?>
              <small class="muted">Recibido el <?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></small>
            </div>
          </details>
        </div>

        <div class="ped-total">L. <?= number_format((float) $p['total'], 2) ?></div>

        <div class="ped-edit">
          <select name="metodo_pago" form="<?= e($fid) ?>" aria-label="Forma de pago">
            <option value="">Pago sin definir</option>
            <?php foreach (METODOS_PAGO as $k => $etq): ?>
              <option value="<?= e($k) ?>" <?= $mpActual === $k ? 'selected' : '' ?>><?= e($etq) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="estado" form="<?= e($fid) ?>" aria-label="Estado del pedido" class="sel-estado est-<?= e($p['estado']) ?>">
            <?php foreach (ESTADOS as $clave => $etq): ?>
              <option value="<?= e($clave) ?>" <?= $p['estado'] === $clave ? 'selected' : '' ?>><?= e($etq) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="ped-botones">
            <button type="submit" form="<?= e($fid) ?>" class="btn mini secundario lista-guardar">Guardar</button>
            <?php if ($p['origen'] === 'manual'): ?>
              <button type="button" class="btn mini peligro" data-confirmar-eliminar="del-<?= (int) $p['id'] ?>"
                      data-confirmar-mensaje="¿Eliminar este pedido manual? Esta acción no se puede deshacer.">Eliminar</button>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <!-- Formularios (fuera de la lista, asociados por form="id") -->
  <?php foreach ($pedidos as $p): $fid = 'ped-' . $p['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="pedidos.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar_pedido">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <input type="hidden" name="volver" value="<?= e($volverQs) ?>">
    </form>
    <?php if ($p['origen'] === 'manual'): ?>
      <form id="del-<?= (int) $p['id'] ?>" method="post" action="pedidos.php">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="eliminar_manual">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="volver" value="<?= e($volverQs) ?>">
      </form>
    <?php endif; ?>
  <?php endforeach; ?>

  <script>
    // Al cambiar el estado o la forma de pago, el botón «Guardar» de ese pedido se resalta ("falta guardar").
    document.querySelectorAll('.ped-edit select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var art = sel.closest('.ped');
        art.querySelector('.lista-guardar').classList.add('sucio');
        if (sel.classList.contains('sel-estado')) sel.className = 'sel-estado est-' + sel.value;
      });
    });
  </script>
<?php endif; ?>

<?php endif; ?>

<!-- ============ Modal compartido: agregar pedido manual ============ -->
<dialog class="modal-portada modal-pedido" id="modal-pedido-manual">
  <form method="post" action="pedidos.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="agregar_manual">
    <input type="hidden" name="volver" value="<?= e($volverQs) ?>">
    <h3>Agregar pedido manual</h3>
    <p class="modal-ayuda">Para ventas en tienda o por WhatsApp que no pasaron por el carrito.</p>
    <div class="form-grid">
      <div class="full">
        <label for="pm-desc">¿Qué se pidió? *</label>
        <input type="text" id="pm-desc" name="descripcion" maxlength="500" required placeholder="Ej. Ramo con dinero">
      </div>
      <div>
        <label for="pm-fecha">Fecha de entrega *</label>
        <input type="date" id="pm-fecha" name="fecha_entrega" required value="<?= e($vista === 'dia' ? $diaSel : date('Y-m-d')) ?>">
      </div>
      <div>
        <label for="pm-hora">Hora</label>
        <input type="text" id="pm-hora" name="hora_entrega" maxlength="20" placeholder="Ej. 2:30 p.m.">
      </div>
      <div>
        <label for="pm-cliente">Cliente</label>
        <input type="text" id="pm-cliente" name="cliente_nombre" maxlength="150">
      </div>
      <div>
        <label for="pm-tel">Teléfono</label>
        <input type="text" id="pm-tel" name="telefono" maxlength="30" inputmode="tel">
      </div>
      <div>
        <label for="pm-tipo">Recoge o envío</label>
        <select id="pm-tipo" name="tipo_entrega">
          <option value="">Sin especificar</option>
          <?php foreach (TIPOS_ENTREGA as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="pm-metodo">Forma de pago</label>
        <select id="pm-metodo" name="metodo_pago">
          <option value="">Sin definir</option>
          <?php foreach (METODOS_PAGO as $k => $etq): ?><option value="<?= e($k) ?>"><?= e($etq) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="pm-canal">¿Por dónde se vendió?</label>
        <select id="pm-canal" name="canal">
          <option value="tienda">Tienda (en físico)</option>
          <option value="whatsapp">WhatsApp</option>
        </select>
      </div>
      <div>
        <label for="pm-vendedor">Vendido por</label>
        <input type="text" id="pm-vendedor" name="vendedor" maxlength="100" placeholder="Nombre del empleado">
      </div>
      <div>
        <label for="pm-total">Total del pedido (L.)</label>
        <input type="number" id="pm-total" name="total" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
      </div>
      <div class="full">
        <label for="pm-nota">Detalles</label>
        <input type="text" id="pm-nota" name="nota" maxlength="500" placeholder="Ej. Paga el resto al recoger, entrega en Megapaca">
      </div>
    </div>
    <div class="modal-portada-acciones">
      <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
      <button type="submit" class="btn mini">Agregar pedido</button>
    </div>
  </form>
</dialog>

<?php admin_footer(); ?>
