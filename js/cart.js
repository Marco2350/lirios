/* =========================================================
   cart.js — lógica del carrito de Lirios Floristería
   Persistencia en localStorage. Item:
   { key, id, tipo: 'producto'|'personalizado', nombre,
     precio (unitario), cantidad, detalle, imagen }
   ========================================================= */

import { buildOrderMessage, buildWaLink } from './whatsapp.js';
import { generateOrderImage, downloadBlob } from './order-image.js';

const STORAGE_KEY = 'lirios-cart';

/* ---------- API del carrito ---------- */

export function formatPrice(monto) {
  return 'L. ' + Number(monto).toLocaleString('es-HN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export function getCart() {
  try {
    const data = JSON.parse(localStorage.getItem(STORAGE_KEY));
    return Array.isArray(data) ? data : [];
  } catch {
    return [];
  }
}

function saveCart(cart) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
  document.dispatchEvent(new CustomEvent('cart:updated'));
}

export function addToCart(item) {
  const cart = getCart();
  const existente = cart.find((i) => i.key === item.key);
  if (existente) {
    existente.cantidad += item.cantidad;
  } else {
    cart.push(item);
  }
  saveCart(cart);
}

export function removeFromCart(key) {
  saveCart(getCart().filter((i) => i.key !== key));
}

export function setQty(key, cantidad) {
  const cart = getCart();
  const item = cart.find((i) => i.key === key);
  if (!item) return;
  item.cantidad = Math.min(99, Math.max(1, cantidad));
  saveCart(cart);
}

export function cartCount() {
  return getCart().reduce((sum, i) => sum + i.cantidad, 0);
}

export function cartTotal() {
  return getCart().reduce((sum, i) => sum + i.precio * i.cantidad, 0);
}

/**
 * Registra el pedido en el servidor (para la reportería de ventas del
 * panel admin) justo antes de abrir WhatsApp. Es "fire and forget": no
 * se espera la respuesta ni se bloquea el envío por WhatsApp si falla
 * (perder el registro de una venta es mucho menos grave que impedir
 * que el pedido llegue al negocio).
 */
function registrarPedido(cart, cliente, nota) {
  const items = cart.map((item) => ({
    id: item.id,
    tipo: item.tipo,
    nombre: item.nombre,
    precio: item.precio,
    cantidad: item.cantidad,
    detalle: item.detalle,
  }));

  fetch('api/pedidos.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cliente, nota, items }),
  }).catch(() => {
    /* Sin conexión o servidor caído: el pedido por WhatsApp sigue su curso */
  });
}

/* ---------- Utilidades compartidas ---------- */

export function escapeHtml(texto) {
  return String(texto)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

let toastTimer = null;

export function showToast(mensaje) {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    document.body.appendChild(toast);
  }
  toast.innerHTML =
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>' +
    escapeHtml(mensaje);
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

/* =========================================================
   Renderizado de la página del carrito (carrito.html)
   ========================================================= */

const FLOWER_ICON =
  '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="2.5"/><path d="M12 5.5C12 3.6 10.4 2 8.5 2c0 1.9 1.6 3.5 3.5 3.5Zm0 0C12 3.6 13.6 2 15.5 2c0 1.9-1.6 3.5-3.5 3.5Zm-2.5 2C7.6 7.5 6 5.9 6 4c1.9 0 3.5 1.6 3.5 3.5Zm5 0C14.5 5.6 16.1 4 18 4c0 1.9-1.6 3.5-3.5 3.5ZM12 10.5V22m0-6c-2.5 0-4.5-2-4.5-4.5M12 19c2.5 0 4.5-2 4.5-4.5"/></svg>';

function renderCartPage() {
  const lista = document.getElementById('cart-items');
  const layout = document.getElementById('cart-layout');
  const vacio = document.getElementById('cart-empty');
  const totalEl = document.getElementById('cart-total');
  const cart = getCart();

  if (cart.length === 0) {
    layout.hidden = true;
    vacio.hidden = false;
    return;
  }

  layout.hidden = false;
  vacio.hidden = true;

  lista.innerHTML = cart
    .map((item) => {
      const img = item.imagen
        ? `<img src="${escapeHtml(item.imagen)}" alt="">`
        : FLOWER_ICON;
      const detalle = item.detalle
        ? `<p class="item-detail">${escapeHtml(item.detalle)}</p>`
        : '';
      return `
      <article class="cart-item" data-key="${escapeHtml(item.key)}">
        <div class="item-img">${img}</div>
        <div>
          <h3>${escapeHtml(item.nombre)}</h3>
          ${detalle}
          <p class="item-unit">${formatPrice(item.precio)} c/u</p>
        </div>
        <div class="item-side">
          <span class="item-subtotal">${formatPrice(item.precio * item.cantidad)}</span>
          <div class="qty-stepper" role="group" aria-label="Cantidad de ${escapeHtml(item.nombre)}">
            <button type="button" data-action="menos" aria-label="Quitar uno">−</button>
            <span class="qty-value">${item.cantidad}</span>
            <button type="button" data-action="mas" aria-label="Agregar uno">+</button>
          </div>
          <button type="button" class="remove-btn" data-action="eliminar">Eliminar</button>
        </div>
      </article>`;
    })
    .join('');

  totalEl.textContent = formatPrice(cartTotal());
}

function initCartPage() {
  const page = document.getElementById('cart-page');
  if (!page) return;

  renderCartPage();
  document.addEventListener('cart:updated', renderCartPage);

  page.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const key = btn.closest('.cart-item')?.dataset.key;
    if (!key) return;

    const item = getCart().find((i) => i.key === key);
    if (!item) return;

    if (btn.dataset.action === 'mas') setQty(key, item.cantidad + 1);
    if (btn.dataset.action === 'menos') setQty(key, item.cantidad - 1);
    if (btn.dataset.action === 'eliminar') {
      removeFromCart(key);
      showToast('Producto eliminado del carrito');
    }
  });

  const enviarBtn = document.getElementById('send-whatsapp');
  const modal = document.getElementById('order-modal');
  const modalPreview = document.getElementById('order-modal-preview');
  const modalOpenWa = document.getElementById('order-modal-open-wa');
  const modalClose = document.getElementById('order-modal-close');
  let pendingWaLink = null;

  function closeModal() {
    modal.hidden = true;
  }

  modalClose?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
  });

  modalOpenWa?.addEventListener('click', () => {
    if (pendingWaLink) window.open(pendingWaLink, '_blank', 'noopener');
    closeModal();
  });

  enviarBtn?.addEventListener('click', async () => {
    const cart = getCart();
    if (cart.length === 0) return;
    const nombre = document.getElementById('customer-name')?.value.trim() ?? '';
    const nota = document.getElementById('customer-note')?.value.trim() ?? '';
    const total = cartTotal();
    const mensaje = buildOrderMessage(cart, total, nombre, nota);

    registrarPedido(cart, nombre, nota);
    pendingWaLink = buildWaLink(mensaje);

    const originalText = enviarBtn.textContent;
    enviarBtn.disabled = true;
    enviarBtn.textContent = 'Generando imagen del pedido…';

    try {
      const { blob, dataUrl } = await generateOrderImage(cart, total, nombre);
      const filename = `pedido-lirios-${Date.now()}.png`;
      const file = new File([blob], filename, { type: 'image/png' });

      /* En celular (la mayoría de clientes), el navegador puede compartir la
         imagen directo al elegir WhatsApp desde su propio menú "Compartir":
         un solo toque, sin pasar por la carpeta de Descargas. El cliente elige
         el chat de LIRIOS él mismo (esta API no permite abrir un chat
         específico), así que el texto del pedido va incluido en el share. */
      if (navigator.canShare?.({ files: [file] })) {
        try {
          await navigator.share({ files: [file], text: mensaje });
          return;
        } catch (shareErr) {
          if (shareErr?.name === 'AbortError') return; // el cliente canceló el compartir
          /* si el share falla por otra razón, seguimos con la descarga + modal de respaldo */
        }
      }

      downloadBlob(blob, filename);
      modalPreview.src = dataUrl;
      modal.hidden = false;
    } catch {
      /* Si falla el canvas (ej. imagen bloqueada), no se pierde el pedido:
         se abre WhatsApp igual con el texto, solo sin la imagen adjunta. */
      window.open(pendingWaLink, '_blank', 'noopener');
    } finally {
      enviarBtn.disabled = false;
      enviarBtn.textContent = originalText;
    }
  });
}

initCartPage();
