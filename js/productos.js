/* =========================================================
   productos.js — carga productos.json y renderiza:
   - Destacados en index.html   (#featured-grid)
   - Catálogo con filtros       (#catalog-grid)
   - Detalle de producto        (#product-content, ?id=slug)
   ========================================================= */

import { addToCart, formatPrice, escapeHtml, showToast } from './cart.js';

let dataCache = null;

export async function loadData() {
  if (!dataCache) {
    const res = await fetch('data/productos.json');
    if (!res.ok) throw new Error('No se pudo cargar el catálogo');
    dataCache = await res.json();
  }
  return dataCache;
}

function nombreOcasion(data, id) {
  return data.ocasiones.find((o) => o.id === id)?.nombre ?? id;
}

function productCardHtml(data, producto) {
  return `
  <article class="product-card reveal">
    <a class="card-img" href="producto.html?id=${encodeURIComponent(producto.id)}" aria-label="Ver ${escapeHtml(producto.nombre)}">
      <img src="${escapeHtml(producto.imagen)}" alt="${escapeHtml(producto.nombre)}" loading="lazy">
    </a>
    <p class="card-occasion">${escapeHtml(nombreOcasion(data, producto.ocasion))}</p>
    <h3><a href="producto.html?id=${encodeURIComponent(producto.id)}">${escapeHtml(producto.nombre)}</a></h3>
    <p class="card-price">${formatPrice(producto.precio)}</p>
    <div class="card-actions">
      <a class="btn btn-outline" href="producto.html?id=${encodeURIComponent(producto.id)}">Ver detalle</a>
      <button type="button" class="btn" data-add="${escapeHtml(producto.id)}">Agregar</button>
    </div>
  </article>`;
}

function wireAddButtons(contenedor, data) {
  contenedor.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-add]');
    if (!btn) return;
    const producto = data.productos.find((p) => p.id === btn.dataset.add);
    if (!producto) return;

    addToCart({
      key: 'p-' + producto.id,
      id: producto.id,
      tipo: 'producto',
      nombre: producto.nombre,
      precio: producto.precio,
      cantidad: 1,
      detalle: null,
      imagen: producto.imagen,
    });

    // Feedback lujoso en el botón
    const originalText = btn.textContent;
    btn.style.transition = 'all .15s ease';
    btn.textContent = '✓ Agregado';
    btn.disabled = true;
    btn.style.backgroundColor = '#2B2118';
    btn.style.borderColor = '#2B2118';
    btn.style.color = '#FFF9F0';

    showToast(`${producto.nombre} agregado al carrito`);

    setTimeout(() => {
      btn.textContent = originalText;
      btn.disabled = false;
      btn.style.backgroundColor = '';
      btn.style.borderColor = '';
      btn.style.color = '';
    }, 1450);
  });
}

/* ---------- Destacados (index.html) ---------- */

async function initFeatured() {
  const grid = document.getElementById('featured-grid');
  if (!grid) return;
  const data = await loadData();
  const destacados = data.productos
    .filter((p) => p.destacado && p.disponible)
    .slice(0, 4);
  grid.innerHTML = destacados.map((p) => productCardHtml(data, p)).join('');
  wireAddButtons(grid, data);
  revealNow(grid);
}

/* ---------- Catálogo (catalogo.html) ---------- */

async function initCatalog() {
  const grid = document.getElementById('catalog-grid');
  if (!grid) return;
  const data = await loadData();

  const selOcasion = document.getElementById('filter-ocasion');
  const selCategoria = document.getElementById('filter-categoria');
  const selOrden = document.getElementById('sort-precio');
  const countEl = document.getElementById('results-count');

  data.ocasiones.forEach((o) => {
    selOcasion.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(o.id)}">${escapeHtml(o.nombre)}</option>`);
  });
  data.categorias.forEach((c) => {
    selCategoria.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(c.id)}">${escapeHtml(c.nombre)}</option>`);
  });

  function render() {
    let lista = data.productos.filter((p) => p.disponible);
    if (selOcasion.value) lista = lista.filter((p) => p.ocasion === selOcasion.value);
    if (selCategoria.value) lista = lista.filter((p) => p.categoria === selCategoria.value);
    if (selOrden.value === 'asc') lista = [...lista].sort((a, b) => a.precio - b.precio);
    if (selOrden.value === 'desc') lista = [...lista].sort((a, b) => b.precio - a.precio);

    countEl.textContent =
      lista.length === 1 ? '1 arreglo encontrado' : `${lista.length} arreglos encontrados`;

    grid.innerHTML =
      lista.length === 0
        ? '<p class="empty-msg">No encontramos arreglos con esos filtros. Prueba con otra combinación o <a href="personalizar.html">crea tu propio ramo</a>.</p>'
        : lista.map((p) => productCardHtml(data, p)).join('');
    revealNow(grid);
  }

  [selOcasion, selCategoria, selOrden].forEach((sel) =>
    sel.addEventListener('change', render)
  );

  render();
  wireAddButtons(grid, data);
}

/* ---------- Detalle (producto.html?id=slug) ---------- */

async function initProductDetail() {
  const cont = document.getElementById('product-content');
  if (!cont) return;
  const data = await loadData();

  const id = new URLSearchParams(window.location.search).get('id');
  const producto = data.productos.find((p) => p.id === id);

  if (!producto) {
    document.getElementById('related-section')?.setAttribute('hidden', '');
    cont.innerHTML = `
      <div class="empty-msg">
        <h1>Producto no encontrado</h1>
        <p>El arreglo que buscas ya no está disponible o el enlace es incorrecto.</p>
        <a class="btn" href="catalogo.html">Volver al catálogo</a>
      </div>`;
    return;
  }

  document.title = `${producto.nombre} — Lirios Floristería`;

  const disponibilidad = producto.disponible
    ? '<span class="availability">✓ Disponible para pedido</span>'
    : '<span class="availability out">Agotado por el momento</span>';

  cont.innerHTML = `
    <div class="product-layout">
      <div class="arch-img reveal visible">
        <img src="${escapeHtml(producto.imagen)}" alt="${escapeHtml(producto.nombre)}">
      </div>
      <div class="product-info">
        <p class="card-occasion">${escapeHtml(nombreOcasion(data, producto.ocasion))}</p>
        <h1>${escapeHtml(producto.nombre)}</h1>
        <p class="product-price">${formatPrice(producto.precio)}</p>
        <p class="desc">${escapeHtml(producto.descripcion)}</p>
        ${disponibilidad}
        <div class="qty-row">
          <div class="qty-stepper" role="group" aria-label="Cantidad">
            <button type="button" id="qty-menos" aria-label="Quitar uno">−</button>
            <span class="qty-value" id="qty-value">1</span>
            <button type="button" id="qty-mas" aria-label="Agregar uno">+</button>
          </div>
          <button type="button" class="btn" id="add-detail" ${producto.disponible ? '' : 'disabled'}>
            Agregar al carrito
          </button>
        </div>
        <p class="muted" style="font-size:0.88rem">
          El pedido se coordina por WhatsApp: agrega al carrito y envíanos tu orden con un clic.
        </p>
      </div>
    </div>`;

  let cantidad = 1;
  const qtyValue = document.getElementById('qty-value');
  document.getElementById('qty-mas').addEventListener('click', () => {
    cantidad = Math.min(99, cantidad + 1);
    qtyValue.textContent = cantidad;
  });
  document.getElementById('qty-menos').addEventListener('click', () => {
    cantidad = Math.max(1, cantidad - 1);
    qtyValue.textContent = cantidad;
  });

  document.getElementById('add-detail').addEventListener('click', () => {
    addToCart({
      key: 'p-' + producto.id,
      id: producto.id,
      tipo: 'producto',
      nombre: producto.nombre,
      precio: producto.precio,
      cantidad,
      detalle: null,
      imagen: producto.imagen,
    });
    showToast(`${producto.nombre} agregado al carrito`);
  });

  /* Relacionados: misma ocasión o categoría */
  const relGrid = document.getElementById('related-grid');
  if (relGrid) {
    const relacionados = data.productos
      .filter(
        (p) =>
          p.id !== producto.id &&
          p.disponible &&
          (p.ocasion === producto.ocasion || p.categoria === producto.categoria)
      )
      .slice(0, 3);
    if (relacionados.length === 0) {
      document.getElementById('related-section')?.setAttribute('hidden', '');
    } else {
      relGrid.innerHTML = relacionados.map((p) => productCardHtml(data, p)).join('');
      wireAddButtons(relGrid, data);
      revealNow(relGrid);
    }
  }
}

/* Las tarjetas insertadas por JS ya deben verse (el observer de main.js corre antes) */
function revealNow(contenedor) {
  contenedor.querySelectorAll('.reveal').forEach((el) => el.classList.add('visible'));
}

/* Si el JSON no carga (servidor caído, archivo dañado), mostrar el fallo en la página */
function reportarErrorCarga(contenedorId) {
  const el = document.getElementById(contenedorId);
  if (el) {
    el.innerHTML =
      '<p class="empty-msg">No se pudo cargar el catálogo. Recarga la página o inténtalo de nuevo en unos minutos.</p>';
  }
}

initFeatured().catch(() => reportarErrorCarga('featured-grid'));
initCatalog().catch(() => reportarErrorCarga('catalog-grid'));
initProductDetail().catch(() => reportarErrorCarga('product-content'));
