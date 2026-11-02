<?php
/**
 * Permisos del panel: cada usuario tiene un rol y cada rol define, por
 * módulo, qué puede hacer (ver / crear / editar / eliminar). Se editan
 * desde /admin/roles.php. Un rol con es_dueno = 1 lo puede todo.
 *
 * Los permisos se leen de la base en cada petición (no se guardan en la
 * sesión), así un cambio de rol o de permisos aplica de inmediato, y un
 * usuario desactivado pierde el acceso sin esperar a que expire su sesión.
 */

/** Módulos del panel, en el orden en que aparecen en el menú y en la matriz de roles. */
const MODULOS = [
    'pedidos' => 'Pedidos',
    'codigos' => 'Códigos de ramo',
    'caja' => 'Control de caja',
    'reportes' => 'Reportes',
    'productos' => 'Productos',
    'categorias' => 'Categorías',
    'zonas' => 'Zonas de delivery',
    'imagenes' => 'Imágenes',
    'usuarios' => 'Usuarios y roles',
];

const ACCIONES_PERMISO = ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'];

/**
 * Usuario de la sesión con su rol y permisos (null si no hay sesión válida
 * o la cuenta fue desactivada). Se resuelve una vez por petición.
 */
function usuario_actual(): ?array
{
    static $cargado = false, $usuario = null;
    if ($cargado) {
        return $usuario;
    }
    $cargado = true;

    $id = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.usuario, u.nombre, u.rol_id, r.nombre AS rol_nombre, r.es_dueno
         FROM admin_usuarios u JOIN admin_roles r ON r.id = u.rol_id
         WHERE u.id = ? AND u.activo = 1'
    );
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    if (!$fila) {
        return null;
    }

    $permisos = [];
    if (!(int) $fila['es_dueno']) {
        $stmt = db()->prepare('SELECT * FROM admin_rol_permisos WHERE rol_id = ?');
        $stmt->execute([$fila['rol_id']]);
        foreach ($stmt->fetchAll() as $p) {
            foreach (array_keys(ACCIONES_PERMISO) as $a) {
                $permisos[$p['modulo']][$a] = (bool) $p['puede_' . $a];
            }
        }
    }
    $fila['permisos'] = $permisos;
    $usuario = $fila;
    return $usuario;
}

function es_dueno(): bool
{
    $u = usuario_actual();
    return $u !== null && (int) $u['es_dueno'] === 1;
}

/** ¿El usuario actual puede hacer $accion en $modulo? */
function puede(string $modulo, string $accion = 'ver'): bool
{
    $u = usuario_actual();
    if ($u === null) {
        return false;
    }
    if ((int) $u['es_dueno'] === 1) {
        return true;
    }
    // Crear, editar y eliminar solo tienen sentido si además puede ver el módulo.
    if ($accion !== 'ver' && empty($u['permisos'][$modulo]['ver'])) {
        return false;
    }
    return !empty($u['permisos'][$modulo][$accion]);
}

/**
 * Corta la petición si el usuario no tiene el permiso. Para navegar
 * (GET) redirige al inicio con un aviso; para un POST vuelve a la misma
 * página. Se valida SIEMPRE en el servidor: ocultar un botón no es
 * seguridad.
 */
function exigir_permiso(string $modulo, string $accion = 'ver'): void
{
    if (puede($modulo, $accion)) {
        return;
    }
    $nombre = MODULOS[$modulo] ?? $modulo;
    flash('error', $accion === 'ver'
        ? "Tu usuario no tiene acceso a «{$nombre}»."
        : 'Tu usuario no tiene permiso para ' . strtolower(ACCIONES_PERMISO[$accion] ?? $accion) . " en «{$nombre}». Pídele al dueño que lo habilite.");
    $destino = $accion === 'ver' ? primera_pagina_permitida() : ($_SERVER['HTTP_REFERER'] ?? primera_pagina_permitida());
    header('Location: ' . $destino);
    exit;
}

/** Página del panel a la que puede entrar este usuario (el inicio si lo puede ver, o su primer módulo). */
function primera_pagina_permitida(): string
{
    foreach (PAGINA_DE_MODULO as $modulo => $pagina) {
        if (puede($modulo, 'ver')) {
            return $pagina;
        }
    }
    return 'logout.php';
}

const PAGINA_DE_MODULO = [
    'pedidos' => 'pedidos.php',
    'caja' => 'caja.php',
    'codigos' => 'codigos.php',
    'reportes' => 'reportes.php',
    'productos' => 'productos.php',
    'categorias' => 'categorias.php',
    'zonas' => 'zonas-delivery.php',
    'imagenes' => 'subir-imagen.php',
    'usuarios' => 'usuarios.php',
];

/**
 * Para páginas con varias acciones POST: deduce el permiso necesario por el
 * nombre de la acción (eliminar, agregar, abrir, crear y guardar sin id =
 * crear; el resto = editar) y lo exige. Los GET solo piden «ver».
 */
function exigir_permiso_pagina(string $modulo): void
{
    exigir_permiso($modulo, 'ver');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $a = (string) ($_POST['accion'] ?? '');
    $sinId = empty($_POST['id']) && empty($_POST['id_original']);
    if (str_starts_with($a, 'eliminar')) {
        $necesaria = 'eliminar';
    } elseif (str_starts_with($a, 'agregar') || str_starts_with($a, 'crear') || $a === 'abrir' || (str_starts_with($a, 'guardar') && $sinId)) {
        $necesaria = 'crear';
    } else {
        $necesaria = 'editar';
    }
    exigir_permiso($modulo, $necesaria);
}

/** Iniciales para el avatar ("Sarahí Reyes" -> "SR"). */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return $ini ?: '?';
}
