/* =========================================================
   main.js — lógica compartida: menú móvil, contador del
   carrito en el header, animaciones de aparición y footer.
   ========================================================= */

import { cartCount } from './cart.js';
import { buildWaLink } from './whatsapp.js';

/* Botón flotante de WhatsApp: presente en todas las páginas,
   abre la conversación con un saludo ya escrito */
const fab = document.createElement('a');
fab.className = 'wa-fab';
fab.href = buildWaLink('¡Hola! Vengo del sitio web de Lirios Floristería y quiero más información.');
fab.target = '_blank';
fab.rel = 'noopener';
fab.setAttribute('aria-label', 'Chatear por WhatsApp');
fab.innerHTML =
  '<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a9.9 9.9 0 0 0-8.5 15.1L2 22l5-1.4A10 10 0 1 0 12 2Zm5.2 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.2c.1.2.1.4 0 .6l-.4.6-.5.5c-.2.2-.3.4-.1.7.2.3.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.8.3.2.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l2.1 1c.3.2.5.3.6.4.1.2.1.7-.1 1.3Z"/></svg>';
document.body.appendChild(fab);

/* Contador del carrito en el header */
function updateCartBadge() {
  const badge = document.getElementById('cart-count');
  if (!badge) return;
  const count = cartCount();
  badge.textContent = count;
  badge.hidden = count === 0;
}

updateCartBadge();
document.addEventListener('cart:updated', updateCartBadge);

/* Menú hamburguesa (móvil) */
const toggle = document.getElementById('nav-toggle');
const nav = document.getElementById('site-nav');

toggle?.addEventListener('click', () => {
  const abierto = nav.classList.toggle('open');
  toggle.setAttribute('aria-expanded', String(abierto));
  toggle.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
});

/* Año actual en el footer */
const yearEl = document.getElementById('year');
if (yearEl) yearEl.textContent = new Date().getFullYear();

/* Aparición suave al hacer scroll (respeta prefers-reduced-motion) */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reduceMotion && 'IntersectionObserver' in window) {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );
  document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
} else {
  document.querySelectorAll('.reveal').forEach((el) => el.classList.add('visible'));
}
