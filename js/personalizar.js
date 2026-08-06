/* =========================================================
   personalizar.js — constructor de ramo personalizado
   Múltiples flores con cantidades por color, papel, listón,
   extras y dedicatoria. Vista previa SVG dibujada en vivo.
   Datos desde /api/opciones-personalizacion.php, que lee MySQL
   en vivo (editable desde /admin). Precio = tallos + papel + listón + extras.
   ========================================================= */

import { addToCart, formatPrice, escapeHtml, showToast } from './cart.js';

let opciones = null;
let state = {
  flowerVariants: {},          // `${flowerId}-${colorId}` → { qty, flowerId, colorId }
  selectedColorForFlower: {},  // color elegido por tipo de flor (UI)
  wrapId: null,
  ribbonId: null,
  extras: new Set(),
  message: ""
};

const MAX_STEMS = 24;

/* Plantillas rápidas: combinaciones ya armadas para quien no quiere
   elegir flor por flor. Se validan contra las opciones cargadas antes
   de mostrarse, por si el negocio quitó alguna flor/color desde /admin. */
const PRESETS = [
  { id: 'clasico-rojo', nombre: 'Clásico Rojo', wrapId: 'kraft', ribbonId: 'dorado',
    stems: [{ flowerId: 'rosas', colorId: 'rojo', qty: 6 }] },
  { id: 'romance-pastel', nombre: 'Romance Pastel', wrapId: 'rosado', ribbonId: 'rosado',
    stems: [{ flowerId: 'rosas', colorId: 'rosado', qty: 4 }, { flowerId: 'gerberas', colorId: 'blanco', qty: 3 }] },
  { id: 'sol-girasoles', nombre: 'Sol de Girasoles', wrapId: 'kraft', ribbonId: 'verde',
    stems: [{ flowerId: 'girasoles', colorId: 'amarillo', qty: 7 }] },
  { id: 'elegancia-blanca', nombre: 'Elegancia Blanca', wrapId: 'blanco', ribbonId: 'blanco',
    stems: [{ flowerId: 'lirios', colorId: 'blanco', qty: 3 }, { flowerId: 'rosas', colorId: 'blanco', qty: 4 }] },
];

/* Paletas para las opciones "mixto": color y tipo varían por tallo */
const MIX_COLORS = ['#B3261E', '#E88BAD', '#E7C544', '#F5EFE6', '#B497D6'];
const MIX_KINDS = ['rose', 'gerbera', 'lily', 'daisy', 'carnation'];

async function loadOpciones() {
  const res = await fetch('api/opciones-personalizacion.php');
  if (!res.ok) throw new Error('No se pudieron cargar las opciones de personalización');
  return res.json();
}

/* =========================================================
   Utilidades de color
   ========================================================= */

/** Aclara (pct>0) u oscurece (pct<0) un color hex. */
function shade(hex, pct) {
  const n = hex.replace('#', '');
  const full = n.length === 3 ? n.split('').map(c => c + c).join('') : n;
  const target = pct < 0 ? 0 : 255;
  const p = Math.min(1, Math.abs(pct));
  const to = (i) => {
    const c = parseInt(full.substr(i, 2), 16);
    return Math.round((target - c) * p + c).toString(16).padStart(2, '0');
  };
  return '#' + to(0) + to(2) + to(4);
}

/** Convierte cualquier css de color a un hex usable en el dibujo. */
function normalizeColor(css, i = 0) {
  return /^#[0-9a-fA-F]{3,8}$/.test(css || '') ? css : MIX_COLORS[i % MIX_COLORS.length];
}

/* =========================================================
   Motor de dibujo: una flor = markup SVG centrado en (0,0),
   radio ~22 unidades. Se usa tanto en las tarjetas como en
   la vista previa del ramo.
   ========================================================= */

function rep(n, fn) {
  let s = '';
  for (let i = 0; i < n; i++) s += fn(i);
  return s;
}

function flowerMarkup(kind, color) {
  const c = normalizeColor(color);

  if (kind === 'rose') {
    const dark = shade(c, -0.28);
    const mid = shade(c, -0.1);
    const lite = shade(c, 0.16);
    return `
      ${rep(9, i => `<ellipse cx="0" cy="-10" rx="8.4" ry="13.6" fill="${mid}" stroke="${dark}" stroke-width="0.5" stroke-opacity="0.35" transform="rotate(${i * 40 + 3})"/>`)}
      ${rep(7, i => `<ellipse cx="0" cy="-6" rx="6" ry="10.2" fill="${c}" transform="rotate(${i * 51.4 + 16})"/>`)}
      ${rep(5, i => `<ellipse cx="0" cy="-3" rx="4" ry="7" fill="${lite}" opacity="0.95" transform="rotate(${i * 72 + 8})"/>`)}
      <circle cx="0" cy="0" r="4.6" fill="${shade(c, -0.35)}"/>
      <path d="M-2.6 -0.8 A2.8 2.8 0 1 1 2.4 1.6" fill="none" stroke="${shade(c, -0.52)}" stroke-width="0.9" stroke-linecap="round"/>
      <path d="M-1.3 -0.3 A1.5 1.5 0 1 1 1.3 0.9" fill="none" stroke="${shade(c, -0.52)}" stroke-width="0.7" stroke-linecap="round"/>
      <ellipse cx="-2.2" cy="-4.5" rx="3" ry="5" fill="#fff" opacity="0.16" transform="rotate(15)"/>`;
  }

  if (kind === 'sunflower') {
    const back = shade(c, -0.2);
    return `
      ${rep(13, i => `<ellipse cx="0" cy="-13" rx="3.4" ry="8.4" fill="${back}" transform="rotate(${i * 27.7 + 13.8})"/>`)}
      ${rep(13, i => `<ellipse cx="0" cy="-12" rx="3.6" ry="8.6" fill="${c}" transform="rotate(${i * 27.7})"/>`)}
      <circle cx="0" cy="0" r="9" fill="#5c4326"/>
      <circle cx="0" cy="0" r="6.2" fill="#3b2a18"/>
      ${rep(9, i => { const a = i * 40 * Math.PI / 180; return `<circle cx="${(Math.cos(a) * 4.4).toFixed(1)}" cy="${(Math.sin(a) * 4.4).toFixed(1)}" r="0.85" fill="#6b4d2a"/>`; })}
      ${rep(5, i => { const a = (i * 72 + 20) * Math.PI / 180; return `<circle cx="${(Math.cos(a) * 2).toFixed(1)}" cy="${(Math.sin(a) * 2).toFixed(1)}" r="0.7" fill="#2b2118"/>`; })}`;
  }

  if (kind === 'lily') {
    const back = shade(c, -0.14);
    const petal = 'M0 1 C-5.5 -5 -6.2 -16 0 -22 C6.2 -16 5.5 -5 0 1 Z';
    return `
      ${rep(3, i => `<path d="${petal}" fill="${back}" transform="rotate(${i * 120 + 60})"/>`)}
      ${rep(3, i => `<path d="${petal}" fill="${c}" transform="rotate(${i * 120})"/>`)}
      ${rep(3, i => `<g transform="rotate(${i * 120})"><circle cx="-1" cy="-9" r="0.55" fill="${shade(c, -0.45)}"/><circle cx="1.2" cy="-12" r="0.5" fill="${shade(c, -0.45)}"/><circle cx="-0.4" cy="-15" r="0.45" fill="${shade(c, -0.45)}"/></g>`)}
      <ellipse cx="0" cy="-1" rx="3" ry="4.4" fill="#f8f3e8" opacity="0.9"/>
      ${rep(5, i => { const a = (i * 72 - 90) * Math.PI / 180; const x = (Math.cos(a) * 7.5).toFixed(1); const y = (Math.sin(a) * 7.5).toFixed(1); return `<path d="M0 0 L${x} ${y}" stroke="#d8c690" stroke-width="0.7"/><ellipse cx="${x}" cy="${y}" rx="1.3" ry="0.8" fill="#a5581e" transform="rotate(${i * 72},${x},${y})"/>`; })}`;
  }

  if (kind === 'gerbera') {
    const inner = shade(c, 0.18);
    return `
      ${rep(18, i => `<ellipse cx="0" cy="-11.5" rx="2.1" ry="10.5" fill="${c}" transform="rotate(${i * 20})"/>`)}
      ${rep(12, i => `<ellipse cx="0" cy="-7.5" rx="1.9" ry="7.2" fill="${inner}" transform="rotate(${i * 30 + 10})"/>`)}
      <circle cx="0" cy="0" r="4.7" fill="${shade(c, -0.55)}"/>
      ${rep(10, i => { const a = i * 36 * Math.PI / 180; return `<circle cx="${(Math.cos(a) * 3.5).toFixed(1)}" cy="${(Math.sin(a) * 3.5).toFixed(1)}" r="0.6" fill="${shade(c, -0.3)}"/>`; })}
      <circle cx="0" cy="0" r="2.4" fill="#3a2416"/>`;
  }

  if (kind === 'tulip') {
    const back = shade(c, -0.16);
    const front = shade(c, 0.14);
    return `
      <path d="M-8 3 Q-9.5 -13 0 -17.5 Q9.5 -13 8 3 Q0 7 -8 3 Z" fill="${back}"/>
      <path d="M-8.5 2 Q-10 -11 -3.2 -16 Q-1 -7 -1.6 2.6 Q-5 4.6 -8.5 2 Z" fill="${c}"/>
      <path d="M8.5 2 Q10 -11 3.2 -16 Q1 -7 1.6 2.6 Q5 4.6 8.5 2 Z" fill="${c}"/>
      <path d="M-4.2 3 Q-4.8 -10 0 -14.5 Q4.8 -10 4.2 3 Q0 6 -4.2 3 Z" fill="${front}"/>
      <path d="M-1.5 -12 Q0 -13.5 1.5 -12" fill="none" stroke="#fff" stroke-width="0.7" opacity="0.4"/>`;
  }

  if (kind === 'carnation') {
    const zig = (r) => `M0 0 L-4.6 ${-r + 3.5} L-2.6 ${-r} L-0.6 ${-r + 2} L1.4 ${-r} L3.2 ${-r + 1.5} L4.6 ${-r + 3.5} Z`;
    return `
      ${rep(9, i => `<path d="${zig(16)}" fill="${shade(c, -0.16)}" transform="rotate(${i * 40 + 6})"/>`)}
      ${rep(8, i => `<path d="${zig(12.5)}" fill="${c}" transform="rotate(${i * 45 + 24})"/>`)}
      ${rep(6, i => `<path d="${zig(9)}" fill="${shade(c, 0.15)}" transform="rotate(${i * 60 + 10})"/>`)}
      ${rep(5, i => `<path d="${zig(5.5)}" fill="${shade(c, 0.28)}" transform="rotate(${i * 72 + 40})"/>`)}`;
  }

  if (kind === 'daisy') {
    return `
      ${rep(16, i => `<ellipse cx="0" cy="-10" rx="2.2" ry="9.2" fill="${c}" stroke="${shade(c, -0.12)}" stroke-width="0.35" transform="rotate(${i * 22.5})"/>`)}
      <circle cx="0" cy="0" r="4.2" fill="#E7C544"/>
      ${rep(6, i => { const a = i * 60 * Math.PI / 180; return `<circle cx="${(Math.cos(a) * 2.1).toFixed(1)}" cy="${(Math.sin(a) * 2.1).toFixed(1)}" r="0.7" fill="#c69a2b"/>`; })}
      <circle cx="0" cy="0" r="1" fill="#a87f1e"/>`;
  }

  if (kind === 'aster') {
    return `
      ${rep(6, i => `
        <g transform="rotate(${i * 60})">
          <ellipse cx="0" cy="-9" rx="3.7" ry="10" fill="${c}"/>
          ${i % 2 === 0 ? `<path d="M-0.8 -5 L-0.9 -11 M0.9 -5.5 L1 -10.5" stroke="${shade(c, -0.5)}" stroke-width="0.5"/>` : ''}
        </g>`)}
      ${rep(3, i => `<ellipse cx="0" cy="-7" rx="2.6" ry="7.5" fill="${shade(c, 0.22)}" transform="rotate(${i * 120 + 30})"/>`)}
      <circle cx="0" cy="0" r="3" fill="#e8d9a0"/>
      <circle cx="0" cy="0" r="1.4" fill="#b98a2e"/>`;
  }

  /* Flor genérica (respaldo) */
  return `
    ${rep(8, i => `<ellipse cx="0" cy="-8" rx="4.6" ry="10" fill="${c}" transform="rotate(${i * 45})"/>`)}
    <circle cx="0" cy="0" r="4.2" fill="#f8f2e3"/>
    ${rep(5, i => { const a = i * 72 * Math.PI / 180; return `<circle cx="${(Math.cos(a) * 1.8).toFixed(1)}" cy="${(Math.sin(a) * 1.8).toFixed(1)}" r="0.6" fill="#d4a860"/>`; })}`;
}

/** SVG completo de una flor para las tarjetas de selección. */
function flowerIconSVG(kind, colorCss, size = 68) {
  const c = normalizeColor(colorCss);
  return `<svg width="${size}" height="${size}" viewBox="-26 -26 52 52" aria-hidden="true">${flowerMarkup(kind, c)}</svg>`;
}

/* =========================================================
   Tarjetas de flores (selección con color y cantidad)
   ========================================================= */

function renderFlores() {
  const container = document.getElementById('opt-flores');
  if (!container || !opciones) return;

  container.innerHTML = opciones.flores.map(f => {
    const currentColorId = state.selectedColorForFlower?.[f.id] || opciones.colores[0].id;
    const currentColor = opciones.colores.find(c => c.id === currentColorId) || opciones.colores[0];

    const variantKey = `${f.id}-${currentColorId}`;
    const qty = state.flowerVariants?.[variantKey]?.qty || 0;

    const totalFlor = Object.values(state.flowerVariants)
      .filter(v => v.flowerId === f.id)
      .reduce((s, v) => s + (v.qty || 0), 0);

    return `
      <div class="flower-card" data-flower="${f.id}">
        <div class="flower-shape">
          ${flowerIconSVG(f.kind, currentColor.css, 68)}
        </div>
        <div class="flower-info">
          <p class="flower-name">${escapeHtml(f.nombre)}${totalFlor > 0 ? ` <span class="flower-total-badge">${totalFlor}</span>` : ''}</p>
          <p class="flower-price">${formatPrice(f.precio)} / tallo</p>

          <div style="display:flex; gap:4px; margin:6px 0 4px; flex-wrap:wrap;">
            ${opciones.colores.map(col => {
              const cnt = state.flowerVariants?.[`${f.id}-${col.id}`]?.qty || 0;
              return `
              <button type="button"
                class="color-swatch ${currentColorId === col.id ? 'active' : ''}"
                aria-label="${escapeHtml(f.nombre)} en color ${escapeHtml(col.nombre)}${cnt ? `, ${cnt} agregados` : ''}"
                style="background:${col.css};"
                data-flower="${f.id}" data-color="${col.id}">${cnt > 0 ? `<span class="swatch-count">${cnt}</span>` : ''}</button>
            `;
            }).join('')}
          </div>

          <div style="display:flex; align-items:center; gap:4px;">
            <div class="pers-qty">
              <button type="button" data-flower="${f.id}" data-color="${currentColorId}" data-action="minus" aria-label="Quitar un tallo">−</button>
              <span style="padding:0 5px; font-size:12px; min-width:18px; text-align:center;">${qty}</span>
              <button type="button" data-flower="${f.id}" data-color="${currentColorId}" data-action="plus" aria-label="Agregar un tallo">+</button>
            </div>
            <button type="button" class="pers-add" data-flower="${f.id}" data-color="${currentColorId}">Agregar</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  container.querySelectorAll('.color-swatch').forEach(el => {
    el.addEventListener('click', (e) => {
      e.stopImmediatePropagation();
      state.selectedColorForFlower[el.dataset.flower] = el.dataset.color;
      renderFlores();
      updateLive();
    });
  });

  container.querySelectorAll('.pers-qty button').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopImmediatePropagation();
      const key = `${btn.dataset.flower}-${btn.dataset.color}`;
      if (!state.flowerVariants[key]) {
        state.flowerVariants[key] = { qty: 0, flowerId: btn.dataset.flower, colorId: btn.dataset.color };
      }
      const total = Object.values(state.flowerVariants).reduce((s, v) => s + (v.qty || 0), 0);

      if (btn.dataset.action === 'plus') {
        if (total >= MAX_STEMS) { showToast(`Máximo ${MAX_STEMS} tallos por ramo`); return; }
        state.flowerVariants[key].qty += 1;
      } else {
        state.flowerVariants[key].qty = Math.max(0, (state.flowerVariants[key].qty || 0) - 1);
        if (state.flowerVariants[key].qty === 0) delete state.flowerVariants[key];
      }
      renderFlores();
      updateLive();
    });
  });

  container.querySelectorAll('.pers-add').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopImmediatePropagation();
      const key = `${btn.dataset.flower}-${btn.dataset.color}`;
      if (!state.flowerVariants[key]) {
        state.flowerVariants[key] = { qty: 0, flowerId: btn.dataset.flower, colorId: btn.dataset.color };
      }
      const total = Object.values(state.flowerVariants).reduce((s, v) => s + (v.qty || 0), 0);
      if (total >= MAX_STEMS) { showToast(`Máximo ${MAX_STEMS} tallos por ramo`); return; }
      state.flowerVariants[key].qty += 1;
      renderFlores();
      updateLive();
    });
  });
}

/* =========================================================
   Papel, listón y extras
   ========================================================= */

function renderWraps() {
  const container = document.getElementById('opt-wraps');
  if (!container || !opciones.wraps) return;

  container.innerHTML = opciones.wraps.map(w => `
    <button type="button" data-wrap="${w.id}" class="wrap-option ${state.wrapId === w.id ? 'selected' : ''}">
      <div class="wrap-color" style="background:${w.color}"></div>
      <div class="wrap-details">
        <div class="wrap-name">${escapeHtml(w.nombre)}</div>
        <div class="wrap-desc">${escapeHtml(w.description || '')}</div>
        <div class="wrap-price">${formatPrice(w.precio)}</div>
      </div>
    </button>
  `).join('');

  container.querySelectorAll('button[data-wrap]').forEach(btn => {
    btn.addEventListener('click', () => {
      state.wrapId = btn.dataset.wrap;
      renderWraps();
      updateLive();
    });
  });
}

function renderRibbons() {
  const container = document.getElementById('opt-ribbons');
  if (!container || !opciones.ribbons) return;

  container.innerHTML = opciones.ribbons.map(r => `
    <button type="button" data-ribbon="${r.id}" class="ribbon-option ${state.ribbonId === r.id ? 'selected' : ''}">
      <div class="ribbon-color" style="background:${r.color}"></div>
      <span class="ribbon-name">${escapeHtml(r.nombre)}</span>
      <span class="ribbon-price">${formatPrice(r.precio)}</span>
    </button>
  `).join('');

  container.querySelectorAll('button[data-ribbon]').forEach(btn => {
    btn.addEventListener('click', () => {
      state.ribbonId = btn.dataset.ribbon;
      renderRibbons();
      updateLive();
    });
  });
}

function renderExtras() {
  const container = document.getElementById('opt-extras');
  if (!container || !opciones.extras) return;

  container.innerHTML = opciones.extras.map(x => {
    const selected = state.extras.has(x.id);
    return `
      <button type="button" data-extra="${x.id}" class="extra-option ${selected ? 'selected' : ''}" aria-pressed="${selected}">
        <span class="extra-name">${escapeHtml(x.nombre)}</span>
        <span class="extra-price">+ ${formatPrice(x.precio)}</span>
      </button>
    `;
  }).join('');

  container.querySelectorAll('button[data-extra]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.extra;
      if (state.extras.has(id)) state.extras.delete(id);
      else state.extras.add(id);
      renderExtras();
      updateLive();
    });
  });
}

function renderAll() {
  if (!opciones) return;

  if (!state.wrapId && opciones.wraps?.length) state.wrapId = opciones.wraps[0].id;
  if (!state.ribbonId && opciones.ribbons?.length) state.ribbonId = opciones.ribbons[0].id;

  renderFlores();
  renderWraps();
  renderRibbons();
  renderExtras();

  const ta = document.getElementById('dedicatoria');
  if (ta) {
    ta.value = state.message;
    ta.oninput = () => {
      state.message = ta.value;
      updateLive();
    };
  }

  updateLive();
}

/* =========================================================
   Precio y resumen
   ========================================================= */

function totalStems() {
  return Object.values(state.flowerVariants).reduce((s, v) => s + (v.qty || 0), 0);
}

function calculatePrice() {
  let total = 0;

  Object.values(state.flowerVariants).forEach(v => {
    const f = opciones.flores.find(x => x.id === v.flowerId);
    if (f) total += f.precio * (v.qty || 0);
  });

  // Sin flores no hay ramo: papel/listón/extras no se cobran todavía
  if (totalStems() === 0) return 0;

  const wrap = opciones.wraps?.find(w => w.id === state.wrapId);
  if (wrap) total += wrap.precio || 0;

  const ribbon = opciones.ribbons?.find(r => r.id === state.ribbonId);
  if (ribbon) total += ribbon.precio || 0;

  state.extras.forEach(id => {
    const x = opciones.extras?.find(e => e.id === id);
    if (x) total += x.precio || 0;
  });

  return Math.round(total);
}

function updateLive() {
  const stems = totalStems();
  const price = calculatePrice();

  const stemsTexto = stems === 0 ? 'Vacío' : `${stems} ${stems === 1 ? 'flor' : 'flores'}`;
  document.getElementById('stems-count').textContent = stemsTexto;
  document.getElementById('custom-total').textContent = formatPrice(price);

  const progressFill = document.getElementById('stems-progress-fill');
  const progressLabel = document.getElementById('stems-progress-label');
  if (progressFill && progressLabel) {
    progressFill.style.width = Math.min(100, Math.round((stems / MAX_STEMS) * 100)) + '%';
    progressLabel.textContent = `${stems} / ${MAX_STEMS} tallos`;
  }

  /* Mini-barra móvil sincronizada */
  const miniFlores = document.getElementById('minibar-flores');
  const miniTotal = document.getElementById('minibar-total');
  if (miniFlores) miniFlores.textContent = stemsTexto;
  if (miniTotal) miniTotal.textContent = formatPrice(price);

  const summaryEl = document.getElementById('selection-summary');
  if (summaryEl) {
    let html = '';
    Object.values(state.flowerVariants).forEach(v => {
      const f = opciones.flores.find(x => x.id === v.flowerId);
      const c = opciones.colores.find(x => x.id === v.colorId);
      if (f && c && v.qty > 0) {
        html += `<span class="selection-chip">${escapeHtml(f.nombre)} ${escapeHtml(c.nombre)} × ${v.qty}</span>`;
      }
    });
    const w = opciones.wraps?.find(x => x.id === state.wrapId);
    if (w) html += `<span class="selection-chip wrap">${escapeHtml(w.nombre)}</span>`;
    const r = opciones.ribbons?.find(x => x.id === state.ribbonId);
    if (r) html += `<span class="selection-chip ribbon">${escapeHtml(r.nombre)}</span>`;
    state.extras.forEach(id => {
      const x = opciones.extras?.find(e => e.id === id);
      if (x) html += `<span class="selection-chip">+ ${escapeHtml(x.nombre)}</span>`;
    });
    if (state.message) html += `<span style="font-size:10px;color:#726A57;margin-left:2px;">+ dedicatoria</span>`;
    summaryEl.innerHTML = html;
  }

  updateRamoPreviewSVG();

  const btn = document.getElementById('add-to-cart-btn');
  if (btn) btn.disabled = stems === 0;
}

/* =========================================================
   Vista previa del ramo (SVG en vivo)
   ========================================================= */

let lastDrawnStems = -1;

function updateRamoPreviewSVG() {
  const svg = document.getElementById('ramo-svg');
  if (!svg) return;

  const flowersGroup = svg.querySelector('#flowers');
  const stemsGroup = svg.querySelector('#stems');
  const leavesGroup = svg.querySelector('#leaves');
  if (!flowersGroup || !stemsGroup || !leavesGroup) return;

  flowersGroup.innerHTML = '';
  stemsGroup.innerHTML = '';
  leavesGroup.innerHTML = '';

  /* --- Lista de tallos a dibujar (uno por flor) --- */
  let toDraw = [];
  let stemIndex = 0;
  Object.values(state.flowerVariants).forEach(v => {
    const f = opciones.flores.find(x => x.id === v.flowerId);
    const colObj = opciones.colores.find(x => x.id === v.colorId);
    if (!f || (v.qty || 0) <= 0) return;
    for (let i = 0; i < v.qty; i++) {
      const color = (v.colorId === 'mixto')
        ? MIX_COLORS[stemIndex % MIX_COLORS.length]
        : normalizeColor(colObj?.css, stemIndex);
      const kind = (f.kind === 'mixed')
        ? MIX_KINDS[stemIndex % MIX_KINDS.length]
        : (f.kind || 'rose');
      toDraw.push({ kind, color });
      stemIndex++;
    }
  });

  const esDemo = toDraw.length === 0;

  /* Ramo de muestra mientras el lienzo está vacío */
  if (esDemo) {
    toDraw = [
      { kind: 'rose', color: '#B3261E' },
      { kind: 'gerbera', color: '#E88BAD' },
      { kind: 'lily', color: '#F5EFE6' },
      { kind: 'rose', color: '#E88BAD' },
      { kind: 'daisy', color: '#F5EFE6' },
      { kind: 'sunflower', color: '#E7C544' },
      { kind: 'carnation', color: '#B497D6' }
    ];
  }

  const n = toDraw.length;
  const centerX = 100;
  const centerY = 86;
  const spread = Math.min(48, 16 + n * 2.6);

  /* --- Posiciones: espiral áurea (racimo natural) --- */
  const positions = [];
  if (n === 1) {
    positions.push({ x: 100, y: 86, depth: 1 });
  } else if (n === 2) {
    positions.push({ x: 87, y: 83, depth: 0.5 }, { x: 113, y: 90, depth: 1 });
  } else {
    const GOLDEN = 2.39996; // ángulo áureo en radianes
    for (let i = 0; i < n; i++) {
      const r = spread * Math.sqrt((i + 0.65) / n);
      const a = i * GOLDEN;
      const x = Math.max(32, Math.min(168, centerX + Math.cos(a) * r * 1.04));
      const y = centerY + Math.sin(a) * r * 0.62;
      const depth = (Math.sin(a) + 1) / 2; // 0 = atrás, 1 = adelante
      positions.push({ x, y, depth });
    }
  }

  const baseScale = n > 15 ? 0.62 : n > 10 ? 0.72 : n > 6 ? 0.82 : 0.92;

  /* --- Tallos (curvos, hacia el amarre del papel) --- */
  positions.forEach((p, i) => {
    const stem = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    const gatherX = 96 + (i % 3) * 4;
    stem.setAttribute('d', `M${gatherX} 178 Q${(p.x * 0.94 + gatherX * 0.06 + 2).toFixed(1)} ${(p.y + 40).toFixed(1)} ${p.x.toFixed(1)} ${(p.y + 4).toFixed(1)}`);
    stem.setAttribute('fill', 'none');
    stem.setAttribute('stroke', '#55703f');
    stem.setAttribute('stroke-width', n > 12 ? '1.5' : '2');
    stem.setAttribute('stroke-linecap', 'round');
    stem.setAttribute('opacity', '0.75');
    stemsGroup.appendChild(stem);
  });

  /* --- Follaje: eucalipto a los lados + hojas --- */
  const eucalipto = (x, y, rot, flip) => `
    <g transform="translate(${x},${y}) rotate(${rot}) scale(${flip},1)" opacity="0.85">
      <path d="M0 22 Q3 8 1 -14" fill="none" stroke="#7d9276" stroke-width="1.3"/>
      ${rep(6, i => `<circle cx="${(i % 2 === 0 ? -3.4 : 4.2).toFixed(1)}" cy="${(14 - i * 5.6).toFixed(1)}" r="3" fill="#8fa389" opacity="0.9"/>`)}
      <circle cx="0.6" cy="-15.5" r="2.6" fill="#8fa389"/>
    </g>`;

  if (n >= 2) {
    leavesGroup.insertAdjacentHTML('beforeend', eucalipto(centerX - spread - 8, centerY + 6, -26, 1));
    leavesGroup.insertAdjacentHTML('beforeend', eucalipto(centerX + spread + 8, centerY + 8, 26, -1));
  }
  if (n >= 8) {
    leavesGroup.insertAdjacentHTML('beforeend', eucalipto(centerX - 6, centerY - spread * 0.72 - 12, 4, 1));
  }

  const leafColors = ['#3f5133', '#455d38', '#54683f'];
  const nLeaves = Math.min(3 + Math.floor(n / 2), 8);
  for (let i = 0; i < nLeaves; i++) {
    const p = positions[i % positions.length];
    const lx = p.x + (i % 3 - 1) * 12;
    const ly = p.y + 22 + (i % 2) * 7;
    leavesGroup.insertAdjacentHTML('beforeend', `
      <g transform="translate(${lx.toFixed(1)},${ly.toFixed(1)}) rotate(${(i * 47) % 60 - 30})">
        <path d="M0 0 Q-9 -5 -14 2 Q-8 9 0 5 Z" fill="${leafColors[i % 3]}" opacity="0.85"/>
        <path d="M-1.5 1.5 Q-7 2.5 -11.5 3.2" stroke="${shade(leafColors[i % 3], -0.25)}" stroke-width="0.6" fill="none"/>
      </g>`);
  }

  /* --- Flores (las de atrás primero, para que las del frente tapen) --- */
  const orden = toDraw.map((f, i) => ({ f, p: positions[i] }))
    .sort((a, b) => a.p.y - b.p.y);

  const bloomNow = !esDemo && n > lastDrawnStems && lastDrawnStems >= 0;

  orden.forEach(({ f, p }, drawIdx) => {
    const s = (baseScale * (0.85 + p.depth * 0.18)).toFixed(3);
    const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    g.setAttribute('transform', `translate(${p.x.toFixed(1)}, ${p.y.toFixed(1)}) scale(${s})`);
    g.setAttribute('filter', 'url(#flowerShadow)');
    g.innerHTML = `<g class="${bloomNow ? 'bloom' : ''}" style="animation-delay:${(drawIdx * 0.04).toFixed(2)}s">${flowerMarkup(f.kind, f.color)}</g>`;
    flowersGroup.appendChild(g);
  });

  /* --- Gypsophila (nube de puntitos) en ramos llenos --- */
  if (n >= 5) {
    for (let i = 0; i < Math.min(n, 10); i += 2) {
      const p = positions[i];
      const cluster = document.createElementNS('http://www.w3.org/2000/svg', 'g');
      cluster.setAttribute('transform', `translate(${(p.x + 6).toFixed(1)}, ${(p.y + 10).toFixed(1)})`);
      cluster.setAttribute('opacity', '0.7');
      cluster.innerHTML = rep(5, k => {
        const a = k * 72 * Math.PI / 180;
        return `<circle cx="${(Math.cos(a) * (3 + k % 2 * 2.4)).toFixed(1)}" cy="${(Math.sin(a) * 3.2).toFixed(1)}" r="1.05" fill="#f3ede1"/>`;
      });
      flowersGroup.appendChild(cluster);
    }
  }

  lastDrawnStems = esDemo ? -1 : n;

  /* --- Papel (trasero y cono frontal) teñidos con el elegido --- */
  const w = opciones.wraps?.find(x => x.id === state.wrapId);
  if (w) {
    const wrap = svg.querySelector('#wrap');
    const wrapBack = svg.querySelector('#wrap-back');
    const wrapFold = svg.querySelector('#wrap-fold');
    if (wrap) {
      wrap.setAttribute('fill', w.color);
      wrap.setAttribute('stroke', shade(w.color, -0.18));
    }
    if (wrapBack) wrapBack.setAttribute('fill', shade(w.color, 0.16));
    if (wrapFold) wrapFold.setAttribute('stroke', shade(w.color, -0.2));
  }

  /* --- Listón con moño --- */
  const ribbonGroup = svg.querySelector('#ribbon-visual');
  if (!ribbonGroup) return;
  ribbonGroup.innerHTML = '';

  const r = opciones.ribbons?.find(x => x.id === state.ribbonId);
  if (r) {
    const rc = r.color;
    const rcDark = shade(rc, -0.22);
    ribbonGroup.innerHTML = `
      <rect x="41" y="172.5" width="118" height="4.8" rx="1.6" fill="${rc}"/>
      <rect x="43" y="172.8" width="48" height="1.5" fill="url(#ribbonGloss)"/>
      <path d="M70 172 Q55 157 46 168 Q57 174 71 171" fill="${rc}" stroke="${rcDark}" stroke-width="0.6"/>
      <path d="M130 172 Q145 157 154 168 Q143 174 129 171" fill="${rc}" stroke="${rcDark}" stroke-width="0.6"/>
      <path d="M93 173.5 Q77 197 65 209" fill="none" stroke="${rc}" stroke-width="3.8" stroke-linecap="round"/>
      <path d="M107 173.5 Q123 197 135 209" fill="none" stroke="${rc}" stroke-width="3.8" stroke-linecap="round"/>
      <path d="M93 173.5 Q79 193 68 204" fill="none" stroke="url(#ribbonGloss)" stroke-width="1.1"/>
      <ellipse cx="100" cy="171" rx="7.2" ry="4.4" fill="${rc}" stroke="${rcDark}" stroke-width="0.6"/>
      <ellipse cx="98.5" cy="170" rx="3" ry="1.8" fill="#fff" opacity="0.4"/>
    `;
  }
}

/* =========================================================
   Plantillas rápidas y reinicio
   ========================================================= */

function renderPresets() {
  const container = document.getElementById('presets-list');
  if (!container || !opciones) return;

  const disponibles = PRESETS.filter((p) =>
    p.stems.every(
      (s) => opciones.flores.some((f) => f.id === s.flowerId) && opciones.colores.some((c) => c.id === s.colorId)
    )
  );

  container.innerHTML = disponibles
    .map((p) => `<button type="button" class="preset-chip" data-preset="${p.id}">${escapeHtml(p.nombre)}</button>`)
    .join('');

  container.querySelectorAll('button[data-preset]').forEach((btn) => {
    btn.addEventListener('click', () => aplicarPreset(btn.dataset.preset));
  });
}

function aplicarPreset(presetId) {
  const preset = PRESETS.find((p) => p.id === presetId);
  if (!preset || !opciones) return;

  state.flowerVariants = {};
  state.selectedColorForFlower = {};
  preset.stems.forEach((s) => {
    state.flowerVariants[`${s.flowerId}-${s.colorId}`] = { qty: s.qty, flowerId: s.flowerId, colorId: s.colorId };
    state.selectedColorForFlower[s.flowerId] = s.colorId;
  });
  if (opciones.wraps?.some((w) => w.id === preset.wrapId)) state.wrapId = preset.wrapId;
  if (opciones.ribbons?.some((r) => r.id === preset.ribbonId)) state.ribbonId = preset.ribbonId;

  lastDrawnStems = -1;
  renderAll();
  showToast(`Plantilla "${preset.nombre}" aplicada — ajústala a tu gusto`);
}

function vaciarRamo() {
  state.flowerVariants = {};
  state.selectedColorForFlower = {};
  state.extras = new Set();
  state.message = '';
  lastDrawnStems = -1;
  const ta = document.getElementById('dedicatoria');
  if (ta) ta.value = '';
  renderAll();
  showToast('Ramo vaciado');
}

/* =========================================================
   Detalle para el carrito / WhatsApp
   ========================================================= */

function buildDetalle() {
  const parts = [];

  const stemsList = [];
  Object.values(state.flowerVariants).forEach(v => {
    const f = opciones.flores.find(x => x.id === v.flowerId);
    const c = opciones.colores.find(x => x.id === v.colorId);
    if (f && c && v.qty > 0) stemsList.push(`${v.qty}× ${f.nombre} ${c.nombre}`);
  });
  if (stemsList.length) parts.push(stemsList.join(', '));

  const w = opciones.wraps?.find(x => x.id === state.wrapId);
  if (w) parts.push(w.nombre);

  const r = opciones.ribbons?.find(x => x.id === state.ribbonId);
  if (r) parts.push(r.nombre);

  state.extras.forEach(id => {
    const x = opciones.extras?.find(e => e.id === id);
    if (x) parts.push(`+ ${x.nombre}`);
  });

  if (state.message) parts.push(`dedicatoria: “${state.message}”`);

  return parts.join(' • ');
}

/* =========================================================
   Inicio
   ========================================================= */

async function init() {
  const form = document.getElementById('builder-form');
  if (!form) return;

  try {
    opciones = await loadOpciones();
  } catch (err) {
    console.error(err);
    form.innerHTML = '<p class="empty-msg">No se pudieron cargar las opciones. Verifica que MySQL esté corriendo en XAMPP.</p>';
    return;
  }

  state.wrapId = opciones.wraps?.[0]?.id || null;
  state.ribbonId = opciones.ribbons?.[0]?.id || null;

  renderPresets();
  renderAll();

  document.getElementById('vaciar-ramo')?.addEventListener('click', vaciarRamo);

  const addBtn = document.getElementById('add-to-cart-btn');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      if (totalStems() === 0) return;

      addToCart({
        key: 'c-' + Date.now(),
        id: null,
        tipo: 'personalizado',
        nombre: 'Ramo personalizado',
        precio: calculatePrice(),
        cantidad: 1,
        detalle: buildDetalle(),
        imagen: null,
      });

      showToast('¡Ramo agregado al carrito!');
      state.flowerVariants = {};
      state.extras = new Set();
      state.message = '';
      lastDrawnStems = -1;
      const ta = document.getElementById('dedicatoria');
      if (ta) ta.value = '';
      renderAll();
    });
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    addBtn?.click();
  });

  /* Mini-barra móvil: visible solo cuando el preview sale de pantalla */
  const minibar = document.getElementById('preview-minibar');
  const previewEl = document.getElementById('ramo-preview');
  if (minibar && previewEl) {
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(([entry]) => {
        minibar.classList.toggle('show', !entry.isIntersecting);
        minibar.setAttribute('aria-hidden', String(entry.isIntersecting));
      }, { threshold: 0.05 });
      io.observe(previewEl);
    } else {
      minibar.classList.add('show');
    }
    document.getElementById('minibar-ver')?.addEventListener('click', () => {
      previewEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
}

init();
