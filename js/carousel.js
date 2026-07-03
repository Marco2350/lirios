/* =========================================================
   carousel.js — carrusel del hero (index.html)
   Crossfade automático con efecto Ken Burns, puntos de
   navegación, flechas, swipe táctil y pausa al pasar el
   cursor. Respeta prefers-reduced-motion (sin auto-avance).
   ========================================================= */

const root = document.getElementById('hero-carousel');

function initCarousel() {
  if (!root) return;

  const slides = [...root.querySelectorAll('.carousel-slide')];
  if (slides.length < 2) return;

  const tag = root.querySelector('.carousel-tag');
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
    slides[actual].classList.remove('is-active');
    dots[actual].classList.remove('is-active');
    actual = (n + slides.length) % slides.length;
    slides[actual].classList.add('is-active');
    dots[actual].classList.add('is-active');

    const { nombre, href } = slides[actual].dataset;
    if (tag && nombre) {
      tag.textContent = nombre;
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

  go(0);
  reiniciar();
}

initCarousel();
