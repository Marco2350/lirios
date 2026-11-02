<?php
/**
 * Protección de sesión del panel: incluir al inicio de toda
 * página del admin excepto index.php (login).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';

admin_session_start();

if (empty($_SESSION['lirios_admin']) || usuario_actual() === null) {
    // Sin sesión, o la cuenta fue desactivada / eliminada mientras estaba abierta.
    unset($_SESSION['lirios_admin'], $_SESSION['usuario_id']);
    header('Location: index.php');
    exit;
}

/* Token CSRF: se genera una vez por sesión y se valida en cada POST */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

function verificar_csrf(): void
{
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && (!isset($_POST['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
    ) {
        http_response_code(403);
        exit('Solicitud no válida. Vuelve a intentarlo desde el panel.');
    }
}
