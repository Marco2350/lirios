/* =========================================================
   carousel.js — carrusel del hero (index.html)
   Crossfade automático con efecto Ken Burns, puntos de
   navegación, flechas, swipe táctil y pausa al pasar el
   cursor. Respeta prefers-reduced-motion (sin auto-avance).
   ========================================================= */

import { formatPrice } from './cart.js';

const root = document.getElementById('hero-carousel');

function initCarousel() {
  if (!root) return;

  const slides = [...root.querySelectorAll('.carousel-slide')];
  if (slides.length < 2) return;

  /* El tag es un <div> con un <a> adentro: se actualiza el enlace, no el div */
  const tag = root.querySelector('.carousel-tag a');
  const dotsWrap = root.querySelector('.carousel-dots');
  const frame = root.querySelector('.carousel-frame');
  const INTERVALO = 5500;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let actual = 0;
  let timer = null;

  const dots = slides.map((_, i) => {
    const dot = document.createElement('button');
    dot.type = 'button';
    dot.setAttribute('aria-label', `Ver foto ${i + 1} de ${slides.length}`);
    dot.addEventListener('click', () => {
      go(i);
      reiniciar();
    });
    dotsWrap.appendChild(dot);
    return dot;
  });

  function go(n) {
    dots[actual].classList.remove('is-active');
    actual = (n + slides.length) % slides.length;

    /* Baraja 3D: activa al frente, vecinas asomando detrás */
    const prev = (actual - 1 + slides.length) % slides.length;
    const next = (actual + 1) % slides.length;
    slides.forEach((s, i) => {
      s.classList.toggle('is-active', i === actual);
      s.classList.toggle('is-prev', i === prev);
      s.classList.toggle('is-next', i === next);
    });
    dots[actual].classList.add('is-active');

    const { nombre, href, precio } = slides[actual].dataset;
    if (tag && nombre) {
      const precioHtml = precio
        ? ` <span class="tag-price">${formatPrice(precio)}</span>`
        : '';
      tag.innerHTML = nombre + precioHtml;
      tag.href = href || '#';
    }
  }

  function detener() {
    clearInterval(timer);
    timer = null;
  }

  function reiniciar() {
    detener();
    if (!reduceMotion && !document.hidden) {
      timer = setInterval(() => go(actual + 1), INTERVALO);
    }
  }

  root.querySelector('.carousel-prev')?.addEventListener('click', () => {
    go(actual - 1);
    reiniciar();
  });
  root.querySelector('.carousel-next')?.addEventListener('click', () => {
    go(actual + 1);
    reiniciar();
  });

  /* Pausa mientras el cursor o el foco están sobre el carrusel */
  root.addEventListener('mouseenter', detener);
  root.addEventListener('mouseleave', reiniciar);
  root.addEventListener('focusin', detener);
  root.addEventListener('focusout', reiniciar);
  document.addEventListener('visibilitychange', reiniciar);

  /* Swipe táctil / arrastre con puntero */
  let inicioX = null;
  frame.addEventListener('pointerdown', (e) => {
    inicioX = e.clientX;
  });
  frame.addEventListener('pointerup', (e) => {
    if (inicioX === null) return;
    const delta = e.clientX - inicioX;
    inicioX = null;
    if (Math.abs(delta) < 40) return;
    go(delta < 0 ? actual + 1 : actual - 1);
    reiniciar();
  });

  /* Tilt 3D sutil siguiendo el cursor (solo puntero fino, sin reduced motion) */
  if (!reduceMotion && window.matchMedia('(pointer: fine)').matches) {
    let rafId = null;
    frame.addEventListener('mousemove', (e) => {
      if (rafId) return;
      rafId = requestAnimationFrame(() => {
        rafId = null;
        const rect = frame.getBoundingClientRect();
        const px = (e.clientX - rect.left) / rect.width - 0.5;
        const py = (e.clientY - rect.top) / rect.height - 0.5;
        frame.style.transform = `rotateY(${px * 7}deg) rotateX(${py * -5}deg)`;
      });
    });
    frame.addEventListener('mouseleave', () => {
      frame.style.transform = '';
    });
  }

  go(0);
  reiniciar();
}

initCarousel();
