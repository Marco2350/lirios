<?php
/**
 * Lógica del módulo Control de caja (tablas caja_*, ver
 * database/migraciones/2026-10-12-control-de-caja.sql).
 *
 * Hay varias sucursales, cada una con una o más cajas físicas; cada caja se
 * abre una vez por día. Flujo (tomado del módulo controlCaja de FPTRAX): se ABRE la caja del día
 * contando billetes y monedas, durante el día se registran ENTRADAS y SALIDAS
 * de efectivo, y al final se hace el ARQUEO: se vuelve a contar el efectivo
 * físico y se compara con el saldo esperado (apertura + entradas − salidas).
 *
 * Todo cálculo de dinero se hace aquí, en el servidor, a partir de las
 * cantidades contadas — nunca se confía en totales que mande el navegador.
 */

date_default_timezone_set('America/Tegucigalpa');

const CAJA_TIPOS_MOV = ['ENTRADA' => 'Entrada', 'SALIDA' => 'Salida'];
const CAJA_MAX_CANTIDAD = 100000;

function caja_hoy(): string
{
    return date('Y-m-d');
}

function lempiras(float $n): string
{
    return 'L. ' . number_format($n, 2);
}

/** Fecha Y-m-d válida o null. */
function caja_fecha_valida(?string $f): ?string
{
    if ($f && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && DateTime::createFromFormat('!Y-m-d', $f)) {
        return $f;
    }
    return null;
}

function caja_fecha_larga(string $ymd): string
{
    static $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    static $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $t = strtotime($ymd);
    return $dias[(int) date('w', $t)] . ' ' . (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

/** Etiqueta de una denominación: "Billete L. 500", "Moneda L. 0.50". */
function caja_etiqueta_denominacion(array $d): string
{
    $valor = (float) $d['valor'];
    $txt = $valor == floor($valor) ? number_format($valor, 0) : number_format($valor, 2);
    return ($d['tipo'] === 'BILLETE' ? 'Billete' : 'Moneda') . ' L. ' . $txt;
}

/** Denominaciones (de mayor a menor, billetes primero). */
function caja_denominaciones(bool $soloActivas = true): array
{
    $sql = 'SELECT * FROM caja_denominaciones' . ($soloActivas ? ' WHERE activa = 1' : '')
        . " ORDER BY tipo = 'MONEDA', valor DESC";
    return db()->query($sql)->fetchAll();
}

/** Texto "Sucursal · Caja" de una fila con sucursal_nombre y (caja_nombre o nombre). */
function caja_nombre_completo(array $fila): string
{
    return $fila['sucursal_nombre'] . ' · ' . ($fila['caja_nombre'] ?? $fila['nombre']);
}

/** Sucursales (activas o todas), por nombre. */
function caja_sucursales(bool $soloActivas = true): array
{
    return db()->query('SELECT * FROM caja_sucursales' . ($soloActivas ? ' WHERE activa = 1' : '') . ' ORDER BY nombre')->fetchAll();
}

/** Cajas con el nombre de su sucursal, ordenadas por sucursal y nombre. */
function caja_catalogo(bool $soloActivas = true): array
{
    $sql = 'SELECT c.*, s.nombre AS sucursal_nombre, s.activa AS sucursal_activa
              FROM caja_cajas c JOIN caja_sucursales s ON s.id = c.sucursal_id'
        . ($soloActivas ? ' WHERE c.activa = 1 AND s.activa = 1' : '')
        . ' ORDER BY s.nombre, c.nombre';
    return db()->query($sql)->fetchAll();
}

/** Cajas con una apertura todavía abierta (puede haber varias: una por caja). */
function cajas_abiertas(): array
{
    return db()->query(
        "SELECT d.*, c.nombre AS caja_nombre, s.nombre AS sucursal_nombre
           FROM caja_diaria d
           JOIN caja_cajas c ON c.id = d.caja_id
           JOIN caja_sucursales s ON s.id = c.sucursal_id
          WHERE d.estado = 'ABIERTA'
          ORDER BY s.nombre, c.nombre"
    )->fetchAll();
}

/**
 * Cajas que se pueden abrir hoy: activas, sin una apertura todavía abierta
 * y sin haber abierto ya en esa fecha (una apertura por caja y por día).
 */
function caja_disponibles_para_abrir(string $fecha): array
{
    $stmt = db()->prepare(
        "SELECT c.*, s.nombre AS sucursal_nombre
           FROM caja_cajas c JOIN caja_sucursales s ON s.id = c.sucursal_id
          WHERE c.activa = 1 AND s.activa = 1
            AND NOT EXISTS (SELECT 1 FROM caja_diaria d WHERE d.caja_id = c.id AND (d.estado = 'ABIERTA' OR d.fecha = ?))
          ORDER BY s.nombre, c.nombre"
    );
    $stmt->execute([$fecha]);
    return $stmt->fetchAll();
}

/** Caja con totales: ingresos, egresos y saldo esperado. */
function caja_con_resumen(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT d.*, c.nombre AS caja_nombre, s.nombre AS sucursal_nombre,
                COALESCE(SUM(CASE WHEN m.tipo = 'ENTRADA' THEN m.monto END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN m.tipo = 'SALIDA' THEN m.monto END), 0) AS egresos,
                COUNT(m.id) AS movimientos
           FROM caja_diaria d
           JOIN caja_cajas c ON c.id = d.caja_id
           JOIN caja_sucursales s ON s.id = c.sucursal_id
           LEFT JOIN caja_movimientos m ON m.caja_id = d.id
          WHERE d.id = ?
          GROUP BY d.id"
    );
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) {
        return null;
    }
    $c['esperado'] = (float) $c['saldo_apertura'] + (float) $c['ingresos'] - (float) $c['egresos'];
    return $c;
}

/** Cantidades enviadas por el formulario: [denominacion_id => cantidad entera >= 0]. */
function caja_leer_cantidades(array $post): array
{
    $out = [];
    foreach ((array) ($post['cant'] ?? []) as $id => $cant) {
        $id = (int) $id;
        $cant = (int) $cant;
        if ($id > 0 && $cant > 0) {
            $out[$id] = min($cant, CAJA_MAX_CANTIDAD);
        }
    }
    return $out;
}

/** Total en lempiras de unas cantidades, usando el valor real de cada denominación en la BD. */
function caja_total_cantidades(array $cantidades): float
{
    if (!$cantidades) {
        return 0.0;
    }
    $marcadores = implode(',', array_fill(0, count($cantidades), '?'));
    $stmt = db()->prepare("SELECT id, valor FROM caja_denominaciones WHERE id IN ($marcadores)");
    $stmt->execute(array_keys($cantidades));
    $total = 0.0;
    foreach ($stmt->fetchAll() as $d) {
        $total += (float) $d['valor'] * $cantidades[$d['id']];
    }
    return round($total, 2);
}

/** Guarda el desglose contado (APERTURA o CIERRE). Se llama dentro de una transacción. */
function caja_guardar_desglose(int $cajaId, string $momento, array $cantidades): void
{
    if (!$cantidades) {
        return;
    }
    $marcadores = implode(',', array_fill(0, count($cantidades), '?'));
    $stmt = db()->prepare("SELECT id, valor, tipo FROM caja_denominaciones WHERE id IN ($marcadores)");
    $stmt->execute(array_keys($cantidades));
    $ins = db()->prepare(
        'INSERT INTO caja_desglose (caja_id, momento, denominacion_id, tipo, valor, cantidad) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($stmt->fetchAll() as $d) {
        $ins->execute([$cajaId, $momento, $d['id'], $d['tipo'], $d['valor'], $cantidades[$d['id']]]);
    }
}

/** Desglose guardado de una caja en un momento (con subtotal por fila). */
function caja_desglose(int $cajaId, string $momento): array
{
    $stmt = db()->prepare(
        "SELECT tipo, valor, cantidad, valor * cantidad AS subtotal
           FROM caja_desglose WHERE caja_id = ? AND momento = ?
          ORDER BY tipo = 'MONEDA', valor DESC"
    );
    $stmt->execute([$cajaId, $momento]);
    return $stmt->fetchAll();
}

/**
 * Efectivo de pedidos con fecha de entrega en $fecha (referencia, no se suma solo
 * a la caja: la persona decide qué registrar como entrada). La forma de pago del
 * pedido es texto libre en los pedidos viejos, de ahí el LIKE.
 */
function caja_pedidos_efectivo(string $fecha): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total
           FROM pedidos WHERE fecha_entrega = ? AND forma_pago LIKE '%efect%'"
    );
    $stmt->execute([$fecha]);
    return $stmt->fetch();
}

/** Lista de aperturas con totales, filtrable por fechas, estado, sucursal y caja. */
function caja_listar(?string $desde, ?string $hasta, string $estado, int $sucursalId = 0, int $cajaId = 0, int $limite = 200): array
{
    $sql = "SELECT d.*, c.nombre AS caja_nombre, s.nombre AS sucursal_nombre,
                   COALESCE(SUM(CASE WHEN m.tipo = 'ENTRADA' THEN m.monto END), 0) AS ingresos,
                   COALESCE(SUM(CASE WHEN m.tipo = 'SALIDA' THEN m.monto END), 0) AS egresos
              FROM caja_diaria d
              JOIN caja_cajas c ON c.id = d.caja_id
              JOIN caja_sucursales s ON s.id = c.sucursal_id
              LEFT JOIN caja_movimientos m ON m.caja_id = d.id
             WHERE 1 = 1";
    $params = [];
    if ($desde) {
        $sql .= ' AND d.fecha >= :desde';
        $params['desde'] = $desde;
    }
    if ($hasta) {
        $sql .= ' AND d.fecha <= :hasta';
        $params['hasta'] = $hasta;
    }
    if ($estado === 'ABIERTA' || $estado === 'CERRADA') {
        $sql .= ' AND d.estado = :estado';
        $params['estado'] = $estado;
    }
    if ($sucursalId > 0) {
        $sql .= ' AND c.sucursal_id = :sucursal';
        $params['sucursal'] = $sucursalId;
    }
    if ($cajaId > 0) {
        $sql .= ' AND d.caja_id = :caja';
        $params['caja'] = $cajaId;
    }
    $sql .= ' GROUP BY d.id ORDER BY d.fecha DESC, s.nombre, c.nombre LIMIT ' . (int) $limite;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$f) {
        $f['esperado'] = (float) $f['saldo_apertura'] + (float) $f['ingresos'] - (float) $f['egresos'];
    }
    return $filas;
}

/** Bloque de conteo de billetes y monedas (inputs name="cant[ID]"). El JS de caja.js calcula los subtotales. */
function caja_campos_desglose(array $denominaciones, string $idBase): void
{
    ?>
    <div class="caja-desglose" data-desglose="<?= e($idBase) ?>">
      <?php foreach ($denominaciones as $d): $valor = (float) $d['valor']; ?>
        <label class="caja-fila" for="<?= e($idBase) ?>-<?= (int) $d['id'] ?>">
          <span class="caja-fila-nombre"><?= e(caja_etiqueta_denominacion($d)) ?></span>
          <input type="number" id="<?= e($idBase) ?>-<?= (int) $d['id'] ?>" name="cant[<?= (int) $d['id'] ?>]"
                 class="caja-cant" data-valor="<?= e((string) $valor) ?>" min="0" max="<?= CAJA_MAX_CANTIDAD ?>" step="1"
                 inputmode="numeric" placeholder="0">
          <span class="caja-fila-sub" data-subtotal>L. 0.00</span>
        </label>
      <?php endforeach; ?>
      <div class="caja-desglose-total">
        <span>Total contado</span>
        <strong data-desglose-total>L. 0.00</strong>
      </div>
    </div>
    <?php
}
