<?php
/**
 * Envío del correo de aviso de "pedido nuevo" a NOTIFICACION_EMAIL, vía SMTP
 * de Gmail (PHPMailer vendorizado en /lib/PHPMailer, sin Composer). Se llama
 * desde api/pedidos.php de forma fire-and-forget: si falla (SMTP caído,
 * credenciales mal puestas, sin conexión), el pedido ya quedó registrado y
 * el cliente ya envió su WhatsApp — nunca se bloquea ni se revierte nada
 * por un error de correo, solo se deja constancia en el log del servidor.
 *
 * Credenciales en /.env (ver .env.example): SMTP_USER y SMTP_PASS son el
 * correo de Gmail remitente y su "contraseña de aplicación" de 16
 * caracteres (nunca la contraseña normal de la cuenta). Si falta cualquiera
 * de SMTP_USER, SMTP_PASS o NOTIFICACION_EMAIL, la función no hace nada.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function notificar_pedido_por_correo(int $pedidoId, array $pedido): void
{
    $usuario = env('SMTP_USER');
    $clave = env('SMTP_PASS');
    $destino = env('NOTIFICACION_EMAIL');

    if (!$usuario || !$clave || !$destino) {
        return;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = $usuario;
        $mail->Password = $clave;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) env('SMTP_PORT', '587');
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        $mail->setFrom($usuario, 'LIRIOS Floristería - Web');
        $mail->addAddress($destino);
        $mail->Subject = 'Pedido nuevo #' . $pedidoId . ' - LIRIOS Floristería';
        $mail->Body = construir_cuerpo_correo_pedido($pedidoId, $pedido);

        $mail->send();
    } catch (Throwable $e) {
        error_log('[lirios] No se pudo enviar el correo de aviso del pedido #' . $pedidoId . ': ' . $e->getMessage());
    }
}

function construir_cuerpo_correo_pedido(int $pedidoId, array $pedido): string
{
    $vacio = fn(?string $v) => ($v === null || $v === '') ? '__' : $v;

    $lineas = [];
    $lineas[] = 'Pedido nuevo recibido desde la web (#' . $pedidoId . ')';
    $lineas[] = '';

    foreach ($pedido['items'] as $item) {
        $codigo = $item['codigo'] ? ' [Código: ' . $item['codigo'] . ']' : '';
        $prefijoCantidad = $item['cantidad'] > 1 ? $item['cantidad'] . 'x ' : '';
        $sufijoUnitario = $item['cantidad'] > 1 ? ' c/u' : '';
        $lineas[] = '- ' . $prefijoCantidad . $item['nombre'] . ' - L. ' . number_format($item['precio'], 2) . $sufijoUnitario . $codigo;
    }

    $lineas[] = 'Total: L. ' . number_format($pedido['total'], 2);
    $lineas[] = '';
    $lineas[] = 'Nombre: ' . $vacio($pedido['cliente']);
    $lineas[] = 'Teléfono: ' . $vacio($pedido['telefono']);
    $lineas[] = 'Entrega: ' . $vacio($pedido['fechaEntrega']) . ' / Hora: ' . $vacio($pedido['horaEntrega']);
    $lineas[] = 'Delivery o retiro: ' . $vacio($pedido['tipoEntrega']);
    if ($pedido['zonaDeliveryNombre']) {
        $lineas[] = 'Zona de entrega: ' . $pedido['zonaDeliveryNombre'] . ' (L. ' . number_format($pedido['costoDelivery'], 2) . ')';
    }
    $lineas[] = 'Dirección: ' . $vacio($pedido['direccion']);
    $lineas[] = 'Dedicatoria: ' . $vacio($pedido['dedicatoria']);
    $lineas[] = 'Pago: ' . $vacio($pedido['pago']);
    $lineas[] = 'Nota: ' . $vacio($pedido['nota']);
    $lineas[] = '';
    $lineas[] = 'Ver detalle completo en el panel: /admin/pedidos.php';

    return implode("\n", $lineas);
}
