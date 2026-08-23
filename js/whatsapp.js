/* =========================================================
   whatsapp.js — arma el mensaje del pedido y el link wa.me
   ⚠️ WHATSAPP_NUMBER es un placeholder: confirmar con la
   clienta el número completo con código de país (+504).
   ========================================================= */

import { formatPrice } from './cart.js';

export const WHATSAPP_NUMBER = '50487502362';

/** "2026-08-25" (valor nativo de <input type="date">) → "25/08/2026". */
function formatFecha(fechaISO) {
  const [anio, mes, dia] = fechaISO.split('-');
  return anio && mes && dia ? `${dia}/${mes}/${anio}` : fechaISO;
}

/**
 * Ruta relativa guardada en BD (ej. "images/productos/xxx.webp") → URL
 * absoluta, para que el enlace funcione fuera del sitio (dentro de
 * WhatsApp). Se resuelve contra location.href, no location.origin: el
 * sitio es de estructura plana (todas las páginas públicas y /images
 * viven en la misma carpeta), así que esto funciona igual si el sitio
 * está publicado en la raíz del dominio o en una subcarpeta (como en
 * este entorno de desarrollo, /PROYECTOS-PHP/lirios/).
 */
function absoluteUrl(rutaRelativa) {
  try {
    return new URL(rutaRelativa, location.href).href;
  } catch {
    return null;
  }
}

export function buildOrderMessage(cart, total, datosCliente = {}) {
  const {
    nombre = '',
    telefono = '',
    fechaEntrega = '',
    horaEntrega = '',
    tipoEntrega = '',
    direccion = '',
    dedicatoria = '',
    pago = '',
    nota = '',
  } = datosCliente;

  const lineas = cart.map((item) => {
    const cantidad = item.cantidad > 1 ? `${item.cantidad}x ` : '';
    const detalle = item.detalle ? ` (${item.detalle})` : '';
    const cu = item.cantidad > 1 ? ' c/u' : '';
    const codigo = item.codigo ? ` [Código: ${item.codigo}]` : '';
    return `- ${cantidad}${item.nombre}${detalle} - ${formatPrice(item.precio)}${cu}${codigo}`;
  });

  return [
    '*PEDIDO WEB - LIRIOS FLORISTERÍA*',
    '',
    ...lineas,
    `*Total:* ${formatPrice(total)}`,
    '',
    `*Nombre:* ${nombre || '__'}`,
    `*Teléfono:* ${telefono || '__'}`,
    `*Entrega:* ${fechaEntrega ? formatFecha(fechaEntrega) : '__'} / *Hora:* ${horaEntrega || '__'}`,
    `*Delivery o retiro:* ${tipoEntrega || '__'}`,
    `*Dirección:* ${direccion || '__'}`,
    `*Dedicatoria:* ${dedicatoria || '__'}`,
    `*Pago:* ${pago || '__'}`,
    `*Nota:* ${nota || '__'}`,
    '',
    ...buildReferenciaLineas(cart),
  ].join('\n');
}

/** Enlaces a la(s) foto(s) real(es) del/los arreglo(s) elegido(s), para que quien reciba el mensaje vea exactamente qué se pidió. */
function buildReferenciaLineas(cart) {
  const conFoto = cart
    .map((item) => ({ nombre: item.nombre, url: item.imagen ? absoluteUrl(item.imagen) : null }))
    .filter((item) => item.url);

  if (conFoto.length === 0) return ['*Referencia del arreglo:* (sin foto disponible)'];

  if (conFoto.length === 1) return [`*Referencia del arreglo:*`, conFoto[0].url];

  return [
    '*Referencia de los arreglos:*',
    ...conFoto.map((item) => `${item.nombre}: ${item.url}`),
  ];
}

export function buildWaLink(texto) {
  return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(texto)}`;
}
