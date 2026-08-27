/* =========================================================
   productos.js — consulta /api/productos.php y /api/producto.php
   (MySQL en vivo) y renderiza:
   - Destacados en index.html   (#featured-grid)
   - Catálogo con filtros       (#catalog-grid)
   - Detalle de producto        (#product-content, ?id=slug)
   ========================================================= */

import { addToCart, formatPrice, escapeHtml, showToast } from './cart.js';

let categoriasCache = null;
let productosCache = null;

async function loadCategorias() {
  if (!categoriasCache) {
    const res = await fetch('api/categorias.php');
    if (!res.ok) throw new Error('No se pudieron cargar las categorías');
    const data = await res.json();
    categoriasCache = data.categorias;
  }
  return categoriasCache;
}

async function loadProductos(params = {}) {
  const qs = new URLSearchParams();
  if (params.categoria) qs.set('categoria', params.categoria);
  if (params.subcategoria) qs.set('subcategoria', params.subcategoria);
  if (params.orden) qs.set('orden', params.orden);
  if (params.q) qs.set('q', params.q);
  const url = 'api/productos.php' + (qs.toString() ? '?' + qs.toString() : '');
  const res = await fetch(url);
  if (!res.ok) throw new Error('No se pudo cargar el catálogo');
  const data = await res.json();
  return data.productos;
}

async function loadProductoDetalle(slug) {
  const res = await fetch('api/producto.php?slug=' + encodeURIComponent(slug));
  if (!res.ok) return null;
  return res.json();
}

/* ---------- Tarjetas de producto ---------- */

function productCardHtml(producto) {
  return `
  <article class="product-card reveal" data-slug="${escapeHtml(producto.slug)}">
    <a class="card-img" href="producto.html?id=${encodeURIComponent(producto.slug)}" aria-label="Ver ${escapeHtml(producto.nombre)}">
      <img src="${escapeHtml(producto.imagen || 'images/logo.png')}" alt="${escapeHtml(producto.nombre)}" loading="lazy">
    </a>
    <p class="card-price">${formatPrice(producto.precio)}</p>
    <div class="card-actions">
      <a class="btn btn-outline" href="producto.html?id=${encodeURIComponent(producto.slug)}">Ver detalle</a>
      <button type="button" class="btn" data-add="${escapeHtml(producto.slug)}">Agregar</button>
    </div>
  </article>`;
}

function wireAddButtons(contenedor, productos) {
  contenedor.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-add]');
    if (!btn) return;
    const producto = productos.find((p) => p.slug === btn.dataset.add);
    if (!producto) return;

    addToCart({
      key: `p-${producto.slug}`,
      id: producto.slug,
      tipo: 'producto',
      nombre: producto.nombre,
      precio: producto.precio,
      cantidad: 1,
      detalle: null,
      imagen: producto.imagen,
    });

    const originalText = btn.textContent;
    btn.style.transition = 'all .15s ease';
    btn.textContent = '✓ Agregado';
    btn.disabled = true;
    btn.style.backgroundColor = '#241F17';
    btn.style.borderColor = '#241F17';
    btn.style.color = '#FFFFFF';

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

/* ---------- Grilla de categorías (index.html) ----------
   Descripciones cortas editoriales — texto propio, no viene de la base
   de datos todavía (las categorías solo tienen nombre/portada en MySQL).
   Si se agrega un campo "descripción" a /admin/categorias.php más
   adelante, este mapa se puede reemplazar por el dato real de la API. */
const CATEGORY_DESCRIPTIONS = {
  'ramos-florales': 'Diseños únicos para cada ocasión especial.',
  'arreglos-en-base': 'Elegancia y frescura en cada composición.',
  'cumpleanos': 'Alegría y color para celebrar en grande.',
  'caballero': 'Detalles con estilo, pensados para él.',
  'infantil': 'Ternura y color para los más pequeños.',
  'desayuno-sorpresa': 'Empieza el día con una sorpresa especial.',
  'aniversario': 'Para celebrar el amor que perdura.',
  'chocolates-perfumes-complementos': 'Chocolates, peluches y detalles que enamoran.',
  'graduaciones': 'Para celebrar cada logro con flores.',
  'bodas': 'Flores que acompañan tu gran día.',
  'funebres': 'Acompañamos con respeto y sensibilidad.',
  'flores-preservadas': 'Belleza floral que dura para siempre.',
  'globos': 'Sorprende con flores y globos personalizados.',
};

async function initCategoryGrid() {
  const grid = document.getElementById('home-category-grid');
  if (!grid) return;
  // Solo categorías marcadas visible=1 desde /admin/categorias.php.
  const categorias = (await loadCategorias()).filter((c) => c.visible);
  grid.innerHTML = categorias
    .map((c) => {
      const bg = c.imagenPortada ? ` style="background-image: url('${c.imagenPortada}')"` : '';
      const href = `categoria.html?slug=${encodeURIComponent(c.slug)}`;
      const descripcion = CATEGORY_DESCRIPTIONS[c.slug] || 'Descubre esta colección.';
      return `<article class="category-card">
        <a class="category-card-img" href="${href}" aria-label="Ver ${escapeHtml(c.nombre)}">
          <span class="category-card-bg"${bg}></span>
        </a>
        <div class="category-card-body">
          <h3><a href="${href}">${escapeHtml(c.nombre)}</a></h3>
          <p>${escapeHtml(descripcion)}</p>
          <a class="btn" href="${href}">Ver más</a>
        </div>
      </article>`;
    })
    .join('');
}

/* ---------- Destacados (index.html) ---------- */

async function initFeatured() {
  const grid = document.getElementById('featured-grid');
  if (!grid) return;
  const productos = (await loadProductos()).filter((p) => p.destacado).slice(0, 4);
  grid.innerHTML = productos.map(productCardHtml).join('');
  wireAddButtons(grid, productos);
  revealNow(grid);
}

/* ---------- Catálogo (catalogo.html) ---------- */

async function initCatalog() {
  const grid = document.getElementById('catalog-grid');
  if (!grid) return;

  const chipsWrap = document.getElementById('category-chips');
  const selOrden = document.getElementById('sort-precio');
  const inputBusqueda = document.getElementById('search-input');
  const countEl = document.getElementById('results-count');

  const categorias = await loadCategorias();
  let categoriaActual = '';

  /* Chips de categoría (reemplazan el <select> de categoría) — se navegan
     con un toque y hacen scroll horizontal solas en móvil, en vez de abrir
     un <select> nativo. Usan el mismo ícono/emoji que ya vive en
     categorias.icono (antes solo se mostraba dentro del <option>). */
  function renderChips() {
    const opciones = [{ slug: '', nombre: 'Todas', icono: null }, ...categorias];
    chipsWrap.innerHTML = opciones
      .map(
        (c) => `
      <button type="button" class="category-chip ${c.slug === categoriaActual ? 'selected' : ''}" data-categoria="${escapeHtml(c.slug)}">
        ${c.icono ? `<span class="chip-icon">${escapeHtml(c.icono)}</span>` : ''}${escapeHtml(c.nombre)}
      </button>`
      )
      .join('');
  }
  renderChips();

  async function render() {
    countEl.textContent = 'Buscando arreglos…';
    const productos = await loadProductos({
      categoria: categoriaActual,
      orden: selOrden.value,
      q: inputBusqueda?.value.trim() || '',
    });
    productosCache = productos;

    countEl.textContent =
      productos.length === 1 ? '1 arreglo encontrado' : `${productos.length} arreglos encontrados`;

    grid.innerHTML =
      productos.length === 0
        ? '<p class="empty-msg">No encontramos arreglos con esos filtros. Prueba con otra combinación.</p>'
        : productos.map(productCardHtml).join('');
    revealNow(grid);
    wireAddButtons(grid, productos);
  }

  chipsWrap.addEventListener('click', (e) => {
    const btn = e.target.closest('.category-chip');
    if (!btn) return;
    categoriaActual = btn.dataset.categoria;
    renderChips();
    render();
  });
  selOrden.addEventListener('change', render);

  let busquedaTimer = null;
  inputBusqueda?.addEventListener('input', () => {
    clearTimeout(busquedaTimer);
    busquedaTimer = setTimeout(render, 300);
  });

  render();
}

/* ---------- Página de categoría (categoria.html?slug=) ---------- */

async function initCategoryPage() {
  const grid = document.getElementById('category-grid');
  if (!grid) return;

  const heroEl = document.getElementById('category-hero');
  const eyebrowEl = document.getElementById('category-eyebrow');
  const titleEl = document.getElementById('category-title');
  const descEl = document.getElementById('category-desc');
  const chipsWrap = document.getElementById('subcategory-chips');
  const selOrden = document.getElementById('sort-precio');
  const inputBusqueda = document.getElementById('search-input');
  const countEl = document.getElementById('results-count');

  const slug = new URLSearchParams(window.location.search).get('slug') || '';
  const categorias = await loadCategorias();
  const categoria = categorias.find((c) => c.slug === slug);

  if (!categoria) {
    titleEl.textContent = 'Categoría no encontrada';
    descEl.textContent = '';
    const filterBar = document.querySelector('.filter-bar');
    if (filterBar) filterBar.style.display = 'none';
    if (chipsWrap) chipsWrap.style.display = 'none';
    grid.innerHTML = `
      <p class="empty-msg">Esta categoría ya no existe o el enlace es incorrecto.
        <a href="catalogo.html">Ver el catálogo completo</a>.
      </p>`;
    return;
  }

  document.title = `${categoria.nombre} — LIRIOS Floristería`;
  eyebrowEl.textContent = 'Colección';
  titleEl.textContent = categoria.nombre;
  descEl.textContent = 'Hechos a mano con flores frescas de temporada. Pide por WhatsApp.';
  if (categoria.imagenPortada) {
    heroEl.style.backgroundImage = `url('${categoria.imagenPortada}')`;
  }

  /* Chips de subcategoría (tipo de flor: Rosas, Lirios, Gerberas...) para
     filtrar dentro de esta categoría, en vez de mostrar todo mezclado bajo
     "Recomendados" — mismo patrón que los chips de categoría de catalogo.html. */
  let subcategoriaActual = '';

  function renderChips() {
    if (!chipsWrap) return;
    const opciones = [{ slug: '', nombre: 'Todas' }, ...(categoria.subcategorias || [])];
    chipsWrap.innerHTML = opciones
      .map(
        (s) => `
      <button type="button" class="category-chip ${s.slug === subcategoriaActual ? 'selected' : ''}" data-subcategoria="${escapeHtml(s.slug)}">
        ${escapeHtml(s.nombre)}
      </button>`
      )
      .join('');
  }
  renderChips();

  async function render() {
    countEl.textContent = 'Buscando arreglos…';
    const productos = await loadProductos({
      categoria: slug,
      subcategoria: subcategoriaActual,
      orden: selOrden.value,
      q: inputBusqueda?.value.trim() || '',
    });

    countEl.textContent =
      productos.length === 1 ? '1 arreglo encontrado' : `${productos.length} arreglos encontrados`;

    grid.innerHTML =
      productos.length === 0
        ? `<p class="empty-msg">Todavía no hay arreglos publicados en ${escapeHtml(categoria.nombre)}. Prueba con otra categoría.</p>`
        : productos.map(productCardHtml).join('');
    revealNow(grid);
    wireAddButtons(grid, productos);
  }

  chipsWrap?.addEventListener('click', (e) => {
    const btn = e.target.closest('.category-chip');
    if (!btn) return;
    subcategoriaActual = btn.dataset.subcategoria;
    renderChips();
    render();
  });
  selOrden.addEventListener('change', render);

  let busquedaTimer = null;
  inputBusqueda?.addEventListener('input', () => {
    clearTimeout(busquedaTimer);
    busquedaTimer = setTimeout(render, 300);
  });

  render();
}

/* ---------- Detalle (producto.html?id=slug) ---------- */

async function initProductDetail() {
  const cont = document.getElementById('product-content');
  if (!cont) return;

  const id = new URLSearchParams(window.location.search).get('id');
  const data = id ? await loadProductoDetalle(id) : null;
  const producto = data?.producto ?? null;

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

  document.title = `${producto.nombre} — LIRIOS Floristería`;

  const disponibilidad = producto.disponible
    ? '<span class="availability">✓ Disponible para pedido</span>'
    : '<span class="availability out">Agotado por el momento</span>';

  const entregaBadges = [
    producto.entregaDisponible ? 'Entrega a domicilio' : null,
    producto.retiroDisponible ? 'Retiro en tienda' : null,
  ].filter(Boolean);

  cont.innerHTML = `
    <div class="product-layout">
      <div class="arch-img reveal visible">
        <img src="${escapeHtml(producto.imagen || 'images/logo.png')}" alt="${escapeHtml(producto.nombre)}">
      </div>
      <div class="product-info">
        <p class="card-occasion">${escapeHtml(producto.subcategoria?.nombre || producto.categoria?.nombre || '')}</p>
        <h1>${escapeHtml(producto.nombre)}</h1>
        <p class="product-price" id="detail-price">${formatPrice(producto.precio)}</p>
        <p class="desc">${escapeHtml(producto.descripcion || producto.descripcionCorta)}</p>
        ${disponibilidad}
        ${entregaBadges.length ? `<p class="muted" style="font-size:0.88rem">${entregaBadges.map(escapeHtml).join(' · ')}</p>` : ''}
        ${producto.incluye ? `<p class="muted" style="font-size:0.88rem"><strong>Incluye:</strong> ${escapeHtml(producto.incluye)}</p>` : ''}

        <div class="qty-row">
          <div class="qty-stepper" role="group" aria-label="Cantidad">
            <button type="button" id="qty-menos" aria-label="Quitar uno">−</button>
            <span class="qty-value" id="qty-value">1</span>
            <button type="button" id="qty-mas" aria-label="Agregar uno">+</button>
          </div>
          <button type="button" class="btn" id="add-detail" ${producto.disponible ? '' : 'disabled'}>
            Agregar al carrito
          </button>
          <button type="button" class="btn btn-outline" id="buy-now" ${producto.disponible ? '' : 'disabled'}>
            Comprar ahora
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

  function itemDelCarrito() {
    return {
      key: `p-${producto.slug}`,
      id: producto.slug,
      tipo: 'producto',
      nombre: producto.nombre,
      precio: producto.precio,
      cantidad,
      detalle: null,
      imagen: producto.imagen,
    };
  }

  document.getElementById('add-detail').addEventListener('click', () => {
    addToCart(itemDelCarrito());
    showToast(`${producto.nombre} agregado al carrito`);
  });

  document.getElementById('buy-now').addEventListener('click', () => {
    addToCart(itemDelCarrito());
    window.location.href = 'carrito.html';
  });

  /* Relacionados: misma subcategoría */
  const relGrid = document.getElementById('related-grid');
  const relacionados = data.relacionados || [];
  if (relGrid) {
    if (relacionados.length === 0) {
      document.getElementById('related-section')?.setAttribute('hidden', '');
    } else {
      relGrid.innerHTML = relacionados.map(productCardHtml).join('');
      revealNow(relGrid);
      wireAddButtons(relGrid, relacionados);
    }
  }
}

/* Las tarjetas insertadas por JS ya deben verse (el observer de main.js corre antes) */
function revealNow(contenedor) {
  contenedor.querySelectorAll('.reveal').forEach((el) => el.classList.add('visible'));
}

/* Si la API no responde (servidor caído, MySQL apagado), mostrar el fallo en la página */
function reportarErrorCarga(contenedorId) {
  const el = document.getElementById(contenedorId);
  if (el) {
    el.innerHTML =
      '<p class="empty-msg">No se pudo cargar el catálogo. Recarga la página o inténtalo de nuevo en unos minutos.</p>';
  }
}

initCategoryGrid().catch(() => reportarErrorCarga('home-category-grid'));
initFeatured().catch(() => reportarErrorCarga('featured-grid'));
initCatalog().catch(() => reportarErrorCarga('catalog-grid'));
initCategoryPage().catch(() => reportarErrorCarga('category-grid'));
initProductDetail().catch(() => reportarErrorCarga('product-content'));
