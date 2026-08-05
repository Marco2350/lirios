/* =========================================================
   order-image.js — genera una imagen-resumen del pedido
   (foto + nombre + cantidad + precio de cada ítem) para que
   el cliente la adjunte manualmente en el chat de WhatsApp.

   WhatsApp no permite adjuntar archivos automáticamente vía
   el link wa.me (solo texto pre-llenado); esta imagen es el
   sustituto: se genera con <canvas> a partir de las fotos que
   ya están en /images/productos (mismo origen, no requiere
   CORS) y se descarga justo antes de abrir WhatsApp.
   ========================================================= */

import { formatPrice } from './cart.js';

const COLOR = {
  carbon: '#23261F',
  sage: '#4F6144',
  sageDark: '#37452F',
  sageLight: '#C7D3BB',
  border: '#DCE3D3',
  gris: '#6B7268',
  white: '#FFFFFF',
  bgSoft: '#F5F6F2',
};

const WIDTH = 640;
const PADDING = 32;
const ROW_H = 104;
const HEADER_H = 128;
const FOOTER_H = 128;

function loadImage(src) {
  return new Promise((resolve) => {
    if (!src) return resolve(null);
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = src;
  });
}

function roundRectPath(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

function drawCoveredImage(ctx, img, x, y, w, h) {
  const scale = Math.max(w / img.width, h / img.height);
  const sw = w / scale;
  const sh = h / scale;
  const sx = (img.width - sw) / 2;
  const sy = (img.height - sh) / 2;
  ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
}

function drawFlowerFallback(ctx, x, y, size) {
  const cx = x + size / 2;
  const cy = y + size / 2;
  const petalR = size * 0.13;
  const orbit = size * 0.19;
  for (let i = 0; i < 5; i++) {
    const a = ((i * 72) - 90) * Math.PI / 180;
    ctx.beginPath();
    ctx.arc(cx + Math.cos(a) * orbit, cy + Math.sin(a) * orbit, petalR, 0, Math.PI * 2);
    ctx.fillStyle = COLOR.sageLight;
    ctx.fill();
  }
  ctx.beginPath();
  ctx.arc(cx, cy, petalR * 0.85, 0, Math.PI * 2);
  ctx.fillStyle = COLOR.sage;
  ctx.fill();
}

function wrapLines(ctx, text, maxWidth, maxLines) {
  const words = String(text).split(' ');
  const lines = [];
  let current = '';
  for (const word of words) {
    const test = current ? `${current} ${word}` : word;
    if (current && ctx.measureText(test).width > maxWidth) {
      lines.push(current);
      current = word;
      if (lines.length === maxLines) break;
    } else {
      current = test;
    }
  }
  if (current && lines.length < maxLines) lines.push(current);
  const last = lines[lines.length - 1] ?? '';
  if (last && ctx.measureText(last).width > maxWidth) {
    let truncated = last;
    while (truncated.length > 1 && ctx.measureText(truncated + '…').width > maxWidth) {
      truncated = truncated.slice(0, -1);
    }
    lines[lines.length - 1] = truncated + '…';
  }
  return lines;
}

/**
 * Dibuja un "ticket" con foto + nombre + cantidad + precio de cada ítem
 * del carrito y devuelve el PNG resultante como blob + dataURL.
 */
export async function generateOrderImage(cart, total, nombre = '') {
  if (document.fonts?.ready) {
    try { await document.fonts.ready; } catch { /* la fuente cae a system-ui, no es crítico */ }
  }

  const images = await Promise.all(cart.map((item) => loadImage(item.imagen)));

  const height = HEADER_H + cart.length * ROW_H + FOOTER_H;
  const scale = 2;
  const canvas = document.createElement('canvas');
  canvas.width = WIDTH * scale;
  canvas.height = height * scale;
  const ctx = canvas.getContext('2d');
  ctx.scale(scale, scale);

  ctx.fillStyle = COLOR.white;
  ctx.fillRect(0, 0, WIDTH, height);

  /* Encabezado */
  ctx.fillStyle = COLOR.carbon;
  ctx.fillRect(0, 0, WIDTH, HEADER_H);
  ctx.fillStyle = COLOR.white;
  ctx.font = '800 26px "DM Sans", sans-serif';
  ctx.fillText('LIRIOS Floristería', PADDING, 52);
  ctx.fillStyle = COLOR.sageLight;
  ctx.font = '700 14px "DM Sans", sans-serif';
  ctx.fillText('RESUMEN DE TU PEDIDO', PADDING, 76);
  ctx.fillStyle = 'rgba(255,255,255,0.72)';
  ctx.font = '400 13px "Arimo", sans-serif';
  const fecha = new Date().toLocaleDateString('es-HN', { day: '2-digit', month: 'long', year: 'numeric' });
  ctx.fillText(fecha, PADDING, 98);

  /* Ítems */
  let y = HEADER_H;
  cart.forEach((item, i) => {
    const rowTop = y;
    if (i > 0) {
      ctx.strokeStyle = COLOR.border;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(PADDING, rowTop);
      ctx.lineTo(WIDTH - PADDING, rowTop);
      ctx.stroke();
    }

    const imgSize = 72;
    const imgY = rowTop + (ROW_H - imgSize) / 2;
    const img = images[i];

    roundRectPath(ctx, PADDING, imgY, imgSize, imgSize, 12);
    ctx.save();
    ctx.clip();
    ctx.fillStyle = COLOR.bgSoft;
    ctx.fillRect(PADDING, imgY, imgSize, imgSize);
    if (img) drawCoveredImage(ctx, img, PADDING, imgY, imgSize, imgSize);
    ctx.restore();
    if (!img) drawFlowerFallback(ctx, PADDING, imgY, imgSize);

    const textX = PADDING + imgSize + 18;
    const textMaxWidth = WIDTH - PADDING - 90 - textX;

    ctx.fillStyle = COLOR.carbon;
    ctx.font = '700 16px "DM Sans", sans-serif';
    const nameLines = wrapLines(ctx, item.nombre, textMaxWidth, item.detalle ? 1 : 2);
    let textY = rowTop + 34;
    nameLines.forEach((line) => {
      ctx.fillText(line, textX, textY);
      textY += 20;
    });

    if (item.detalle) {
      ctx.fillStyle = COLOR.gris;
      ctx.font = '400 12.5px "Arimo", sans-serif';
      ctx.fillText(wrapLines(ctx, item.detalle, textMaxWidth, 1)[0], textX, textY + 2);
    }

    ctx.fillStyle = COLOR.gris;
    ctx.font = '400 12.5px "Arimo", sans-serif';
    ctx.fillText(`${item.cantidad} x ${formatPrice(item.precio)}`, textX, rowTop + ROW_H - 20);

    ctx.fillStyle = COLOR.sageDark;
    ctx.font = '700 16px "DM Sans", sans-serif';
    const subtotal = formatPrice(item.precio * item.cantidad);
    const subW = ctx.measureText(subtotal).width;
    ctx.fillText(subtotal, WIDTH - PADDING - subW, rowTop + ROW_H / 2 + 6);

    y += ROW_H;
  });

  /* Total y pie */
  ctx.strokeStyle = COLOR.border;
  ctx.beginPath();
  ctx.moveTo(PADDING, y);
  ctx.lineTo(WIDTH - PADDING, y);
  ctx.stroke();

  ctx.fillStyle = COLOR.carbon;
  ctx.font = '700 17px "DM Sans", sans-serif';
  ctx.fillText('Total', PADDING, y + 38);
  ctx.fillStyle = COLOR.sageDark;
  ctx.font = '800 24px "DM Sans", sans-serif';
  const totalTxt = formatPrice(total);
  const totalW = ctx.measureText(totalTxt).width;
  ctx.fillText(totalTxt, WIDTH - PADDING - totalW, y + 40);

  if (nombre) {
    ctx.fillStyle = COLOR.gris;
    ctx.font = '400 13px "Arimo", sans-serif';
    ctx.fillText(`Pedido de: ${nombre}`, PADDING, y + 66);
  }

  ctx.fillStyle = COLOR.gris;
  ctx.font = '400 11.5px "Arimo", sans-serif';
  ctx.fillText('Coordina pago y entrega por WhatsApp: +504 8750-2362', PADDING, y + FOOTER_H - 24);

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve({ blob, dataUrl: canvas.toDataURL('image/png') }), 'image/png');
  });
}

export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 4000);
}
