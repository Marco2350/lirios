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

function precioTexto(producto) {
  if (producto.precioDesde == null) return 'Precio a consultar';
  const varias = (producto.variantes || []).filter((v) => v.disponible).length > 1;
  return (varias ? 'Desde ' : '') + formatPrice(producto.precioDesde);
}

function productCardHtml(producto) {
  const etiqueta = producto.subcategoria?.nombre || producto.categoria?.nombre || '';
  const unicaVariante = (producto.variantes || []).filter((v) => v.disponible).length === 1;
  const variante = unicaVariante ? producto.variantes.find((v) => v.disponible) : null;

  return `
  <article class="product-card reveal">
    <a class="card-img" href="producto.html?id=${encodeURIComponent(producto.slug)}" aria-label="Ver ${escapeHtml(producto.nombre)}">
      <img src="${escapeHtml(producto.imagen || 'images/logo.png')}" alt="${escapeHtml(producto.nombre)}" loading="lazy">
    </a>
    <p class="card-occasion">${escapeHtml(etiqueta)}</p>
    <h3><a href="producto.html?id=${encodeURIComponent(producto.slug)}">${escapeHtml(producto.nombre)}</a></h3>
    <p class="card-price">${precioTexto(producto)}</p>
    <div class="card-actions">
      <a class="btn btn-outline" href="producto.html?id=${encodeURIComponent(producto.slug)}">Ver detalle</a>
      ${
        variante
          ? `<button type="button" class="btn" data-add="${escapeHtml(producto.slug)}" data-talla="${escapeHtml(variante.talla)}">Agregar</button>`
          : ''
      }
    </div>
  </article>`;
}

function wireAddButtons(contenedor, productos) {
  contenedor.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-add]');
    if (!btn) return;
    const producto = productos.find((p) => p.slug === btn.dataset.add);
    const variante = producto?.variantes.find((v) => v.talla === btn.dataset.talla);
    if (!producto || !variante) return;

    addToCart({
      key: `p-${producto.slug}-${variante.talla}`,
      id: producto.slug,
      tipo: 'producto',
      nombre: `${producto.nombre} (${variante.talla})`,
      precio: variante.precio,
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

  const selCategoria = document.getElementById('filter-categoria');
  const selSubcategoria = document.getElementById('filter-subcategoria');
  const selOrden = document.getElementById('sort-precio');
  const inputBusqueda = document.getElementById('search-input');
  const countEl = document.getElementById('results-count');

  const categorias = await loadCategorias();
  categorias.forEach((c) => {
    selCategoria.insertAdjacentHTML(
      'beforeend',
      `<option value="${escapeHtml(c.slug)}">${escapeHtml((c.icono ? c.icono + ' ' : '') + c.nombre)}</option>`
    );
  });

  function poblarSubcategorias() {
    const cat = categorias.find((c) => c.slug === selCategoria.value);
    selSubcategoria.innerHTML = '<option value="">Todas las subcategorías</option>';
    selSubcategoria.disabled = !cat;
    if (cat) {
      cat.subcategorias.forEach((s) => {
        selSubcategoria.insertAdjacentHTML(
          'beforeend',
          `<option value="${escapeHtml(s.slug)}">${escapeHtml(s.nombre)}</option>`
        );
      });
    }
  }
  poblarSubcategorias();

  async function render() {
    countEl.textContent = 'Buscando arreglos…';
    const productos = await loadProductos({
      categoria: selCategoria.value,
      subcategoria: selSubcategoria.value,
      orden: selOrden.value,
      q: inputBusqueda?.value.trim() || '',
    });
    productosCache = productos;

    countEl.textContent =
      productos.length === 1 ? '1 arreglo encontrado' : `${productos.length} arreglos encontrados`;

    grid.innerHTML =
      productos.length === 0
        ? '<p class="empty-msg">No encontramos arreglos con esos filtros. Prueba con otra combinación o <a href="personalizar.html">crea tu propio ramo</a>.</p>'
        : productos.map(productCardHtml).join('');
    revealNow(grid);
    wireAddButtons(grid, productos);
  }

  selCategoria.addEventListener('change', () => {
    poblarSubcategorias();
    render();
  });
  selSubcategoria.addEventListener('change', render);
  selOrden.addEventListener('change', render);

  let busquedaTimer = null;
  inputBusqueda?.addEventListener('input', () => {
    clearTimeout(busquedaTimer);
    busquedaTimer = setTimeout(render, 300);
  });

  render();
}

/* ---------- Detalle (producto.html?id=slug) ---------- */

function tallaSelectorHtml(variantes, seleccionada) {
  return `
    <div class="talla-selector" role="group" aria-label="Elegir talla">
      ${variantes
        .map(
          (v) => `
        <button type="button" class="talla-opcion ${v.talla === seleccionada ? 'selected' : ''}"
          data-talla="${escapeHtml(v.talla)}" ${v.disponible ? '' : 'disabled'}>
          ${escapeHtml(v.talla)}${v.disponible ? '' : ' (agotada)'}
        </button>`
        )
        .join('')}
    </div>`;
}

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

  const variantesDisponibles = producto.variantes.filter((v) => v.disponible);
  let tallaActual = variantesDisponibles[0]?.talla ?? null;

  cont.innerHTML = `
    <div class="product-layout">
      <div class="arch-img reveal visible">
        <img src="${escapeHtml(producto.imagen || 'images/logo.png')}" alt="${escapeHtml(producto.nombre)}">
      </div>
      <div class="product-info">
        <p class="card-occasion">${escapeHtml(producto.subcategoria?.nombre || producto.categoria?.nombre || '')}</p>
        <h1>${escapeHtml(producto.nombre)}</h1>
        <p class="product-price" id="detail-price">${formatPrice(variantesDisponibles[0]?.precio ?? 0)}</p>
        <p class="desc">${escapeHtml(producto.descripcion || producto.descripcionCorta)}</p>
        ${disponibilidad}
        ${entregaBadges.length ? `<p class="muted" style="font-size:0.88rem">${entregaBadges.map(escapeHtml).join(' · ')}</p>` : ''}
        ${producto.incluye ? `<p class="muted" style="font-size:0.88rem"><strong>Incluye:</strong> ${escapeHtml(producto.incluye)}</p>` : ''}

        ${producto.variantes.length > 1 ? `
          <div class="talla-field">
            <label>Tamaño</label>
            ${tallaSelectorHtml(producto.variantes, tallaActual)}
          </div>` : ''}

        <div class="qty-row">
          <div class="qty-stepper" role="group" aria-label="Cantidad">
            <button type="button" id="qty-menos" aria-label="Quitar uno">−</button>
            <span class="qty-value" id="qty-value">1</span>
            <button type="button" id="qty-mas" aria-label="Agregar uno">+</button>
          </div>
          <button type="button" class="btn" id="add-detail" ${producto.disponible && tallaActual ? '' : 'disabled'}>
            Agregar al carrito
          </button>
          <button type="button" class="btn btn-outline" id="buy-now" ${producto.disponible && tallaActual ? '' : 'disabled'}>
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

  cont.querySelectorAll('.talla-opcion').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      tallaActual = btn.dataset.talla;
      cont.querySelectorAll('.talla-opcion').forEach((b) => b.classList.toggle('selected', b === btn));
      const v = producto.variantes.find((x) => x.talla === tallaActual);
      document.getElementById('detail-price').textContent = formatPrice(v.precio);
    });
  });

  function itemDelCarrito() {
    const v = producto.variantes.find((x) => x.talla === tallaActual);
    return {
      key: `p-${producto.slug}-${tallaActual}`,
      id: producto.slug,
      tipo: 'producto',
      nombre: producto.variantes.length > 1 ? `${producto.nombre} (${tallaActual})` : producto.nombre,
      precio: v.precio,
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
      relGrid.innerHTML = relacionados
        .map(
          (r) => `
        <article class="product-card reveal visible">
          <a class="card-img" href="producto.html?id=${encodeURIComponent(r.slug)}" aria-label="Ver ${escapeHtml(r.nombre)}">
            <img src="${escapeHtml(r.imagen || 'images/logo.png')}" alt="${escapeHtml(r.nombre)}" loading="lazy">
          </a>
          <h3><a href="producto.html?id=${encodeURIComponent(r.slug)}">${escapeHtml(r.nombre)}</a></h3>
          <p class="card-price">${r.precioDesde != null ? formatPrice(r.precioDesde) : 'Precio a consultar'}</p>
          <div class="card-actions">
            <a class="btn btn-outline" href="producto.html?id=${encodeURIComponent(r.slug)}">Ver detalle</a>
          </div>
        </article>`
        )
        .join('');
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

initFeatured().catch(() => reportarErrorCarga('featured-grid'));
initCatalog().catch(() => reportarErrorCarga('catalog-grid'));
initProductDetail().catch(() => reportarErrorCarga('product-content'));
