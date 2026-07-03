/* =========================================================
   main.js — lógica compartida: menú móvil, contador del
   carrito en el header, animaciones de aparición y footer.
   ========================================================= */

import { cartCount } from './cart.js';

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
