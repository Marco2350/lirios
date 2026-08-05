/* =========================================================
   whatsapp.js — arma el mensaje del pedido y el link wa.me
   ⚠️ WHATSAPP_NUMBER es un placeholder: confirmar con la
   clienta el número completo con código de país (+504).
   ========================================================= */

import { formatPrice } from './cart.js';

export const WHATSAPP_NUMBER = '50487502362';

export function buildOrderMessage(cart, total, nombre = '', nota = '') {
  const lineas = cart.map((item) => {
    const detalle = item.detalle ? ` (${item.detalle})` : '';
    const cu = item.cantidad > 1 ? ' c/u' : '';
    return `${item.cantidad}x ${item.nombre}${detalle} - ${formatPrice(item.precio)}${cu}`;
  });

  return [
    '¡Hola! Quiero hacer este pedido en LIRIOS Floristería:',
    '',
    ...lineas,
    '',
    `Total: ${formatPrice(total)}`,
    '',
    `Nombre: ${nombre || '_____'}`,
    `Nota: ${nota || '_____'}`,
    '',
    '📎 Les adjunto también la imagen-resumen del pedido con la foto de cada producto.',
  ].join('\n');
}

export function buildWaLink(texto) {
  return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(texto)}`;
}
