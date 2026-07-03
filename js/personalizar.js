/* =========================================================
   personalizar.js — constructor de ramo personalizado (inspirado en versión Next.js)
   Soporta múltiples flores con cantidades, wraps, ribbons y dedicatoria.
   Todo vanilla, datos desde JSON.
   ========================================================= */

import { addToCart, formatPrice, escapeHtml, showToast } from './cart.js';

let opciones = null;
let state = {
  flowerVariants: {},  // key: `${flowerId}-${colorId}` = { qty, flowerId, colorId, colorCss, kind, name }
  selectedColorForFlower: {}, // for UI: current selected color per flower type
  wrapId: null,
  ribbonId: null,
  message: ""
};

const MAX_STEMS = 24;

async function loadOpciones() {
  const res = await fetch('data/opciones-personalizacion.json');
  if (!res.ok) throw new Error('No se pudieron cargar las opciones de personalización');
  return res.json();
}

function getFlowerShape(kind, color, size = 70) {
  const s = size;
  if (kind === 'rose') {
    // Premium layered rose for cards (matches live preview)
    return `
      <svg width="${s}" height="${s}" viewBox="0 0 70 70">
        <g transform="translate(35,36)">
          <!-- Outer layer -->
          ${Array.from({length:9}).map((_,i) => `
            <ellipse cx="0" cy="-8.5" rx="8.4" ry="12.8" fill="${color}" stroke="#000" stroke-width="0.4" stroke-opacity="0.06" transform="rotate(${i * 40})"/>
          `).join('')}
          <!-- Mid layer -->
          ${Array.from({length:6}).map((_,i) => `
            <ellipse cx="0" cy="-4.8" rx="5.8" ry="9.3" fill="${color}" opacity="0.97" transform="rotate(${i * 60 + 14})"/>
          `).join('')}
          <!-- Inner layer -->
          ${Array.from({length:5}).map((_,i) => `
            <ellipse cx="0" cy="-2" rx="3.7" ry="6.3" fill="${color}" opacity="0.9" transform="rotate(${i * 72 + 6})"/>
          `).join('')}
          <!-- Center -->
          <circle cx="0" cy="0.5" r="4.4" fill="#4f2727"/>
          <circle cx="0" cy="0" r="2.1" fill="#3a1c1c"/>
          <!-- stamens -->
          ${Array.from({length:5}).map((_,i) => {
            const a = i * 72; const cx = Math.cos(a*Math.PI/180)*1.65; const cy = Math.sin(a*Math.PI/180)*1.65 - 0.3;
            return `<circle cx="${cx}" cy="${cy}" r="0.85" fill="#c9a06b" opacity="0.85"/>`;
          }).join('')}
        </g>
      </svg>
    `;
  } else if (kind === 'sunflower') {
    return `
      <svg width="${s}" height="${s}" viewBox="0 0 70 70">
        <g transform="translate(35,35)">
          <!-- Petals -->
          ${Array.from({length:14}).map((_,i) => `
            <ellipse cx="0" cy="-14" rx="3.8" ry="7.5" fill="${color}" transform="rotate(${i * (360/14)})" opacity="0.96"/>
          `).join('')}
          <circle cx="0" cy="0" r="9.5" fill="#5c4326"/>
          <!-- Seed texture -->
          ${Array.from({length:7}).map((_,i) => `<circle cx="${Math.cos(i*51.4*Math.PI/180)*4.2}" cy="${Math.sin(i*51.4*Math.PI/180)*4.2}" r="1.3" fill="#3d2b18"/>`).join('')}
        </g>
      </svg>
    `;
  } else if (kind === 'lily') {
    return `
      <svg width="${s}" height="${s}" viewBox="0 0 70 70">
        <g transform="translate(35,37)">
          ${[ -42, -14, 14, 42, -28, 28 ].map(rot => `
            <ellipse cx="0" cy="-11" rx="4.5" ry="16.5" fill="${color}" transform="rotate(${rot})"/>
          `).join('')}
          <ellipse cx="0" cy="-4" rx="3.2" ry="5" fill="#f8f3e8"/>
          <circle cx="0" cy="1.5" r="2.4" fill="#5c4326"/>
        </g>
      </svg>
    `;
  } else if (kind === 'aster') {
    return `
      <svg width="${s}" height="${s}" viewBox="0 0 70 70">
        <g transform="translate(35,35)">
          ${Array.from({length:12}).map((_,i) => `
            <ellipse cx="0" cy="-9.5" rx="2.9" ry="8.8" fill="${color}" transform="rotate(${i * 30})"/>
          `).join('')}
          <circle cx="0" cy="0" r="5.5" fill="#f2e9d6"/>
          <circle cx="0" cy="0" r="2.8" fill="#d6b05f"/>
        </g>
      </svg>
    `;
  } else {
    // graceful default bloom
    return `
      <svg width="${s}" height="${s}" viewBox="0 0 70 70">
        <g transform="translate(35,35)">
          ${Array.from({length:7}).map((_,i) => `
            <ellipse cx="0" cy="-7" rx="4.2" ry="10" fill="${color || '#E8B85C'}" transform="rotate(${i * (360/7)})"/>
          `).join('')}
          <circle cx="0" cy="0" r="5" fill="#f8f1e3"/>
        </g>
      </svg>
    `;
  }
}

function renderFlores() {
  const container = document.getElementById('opt-flores');
  if (!container || !opciones) return;

  container.innerHTML = opciones.flores.map(f => {
    const currentColorId = state.selectedColorForFlower?.[f.id] || opciones.colores[0].id;
    const currentColor = opciones.colores.find(c => c.id === currentColorId) || opciones.colores[0];

    const variantKey = `${f.id}-${currentColorId}`;
    const qty = state.flowerVariants?.[variantKey]?.qty || 0;

    const shapeHtml = getFlowerShape(f.kind, currentColor.css, 68);

    return `
      <div class="flower-card" data-flower="${f.id}">
        <div class="flower-shape">
          ${shapeHtml}
        </div>
        <div class="flower-info">
          <p class="flower-name">${escapeHtml(f.nombre)}</p>
          <p class="flower-price">${formatPrice(f.precio)} / tallo</p>

          <div style="display:flex; gap:4px; margin:6px 0 4px;">
            ${opciones.colores.map(col => `
              <button type="button" 
                class="color-swatch ${currentColorId === col.id ? 'active' : ''}" 
                style="background:${col.css};"
                data-flower="${f.id}" data-color="${col.id}"></button>
            `).join('')}
          </div>

          <div style="display:flex; align-items:center; gap:4px;">
            <div class="pers-qty">
              <button type="button" data-flower="${f.id}" data-color="${currentColorId}" data-action="minus">−</button>
              <span style="padding:0 5px; font-size:12px; min-width:18px; text-align:center;">${qty}</span>
              <button type="button" data-flower="${f.id}" data-color="${currentColorId}" data-action="plus">+</button>
            </div>
            <button type="button" class="pers-add" data-flower="${f.id}" data-color="${currentColorId}">Agregar</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  // Color swatches
  container.querySelectorAll('.color-swatch').forEach(el => {
    el.addEventListener('click', (e) => {
      e.stopImmediatePropagation();
      const flowerId = el.dataset.flower;
      const colorId = el.dataset.color;
      state.selectedColorForFlower[flowerId] = colorId;
      renderFlores();
      updateLive();
    });
  });

  // Qty and add
  container.querySelectorAll('.pers-qty button').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopImmediatePropagation();
      const flowerId = btn.dataset.flower;
      const colorId = btn.dataset.color;
      const action = btn.dataset.action;
      const key = `${flowerId}-${colorId}`;

      if (!state.flowerVariants) state.flowerVariants = {};
      if (!state.flowerVariants[key]) state.flowerVariants[key] = { qty: 0, flowerId, colorId };

      const total = Object.values(state.flowerVariants).reduce((s, v) => s + (v.qty || 0), 0);

      if (action === 'plus') {
        if (total >= MAX_STEMS) return alert(`Máximo ${MAX_STEMS} tallos`);
        state.flowerVariants[key].qty = (state.flowerVariants[key].qty || 0) + 1;
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
      const flowerId = btn.dataset.flower;
      const colorId = btn.dataset.color;
      const key = `${flowerId}-${colorId}`;

      if (!state.flowerVariants) state.flowerVariants = {};
      if (!state.flowerVariants[key]) state.flowerVariants[key] = { qty: 0, flowerId, colorId };

      const total = Object.values(state.flowerVariants).reduce((s, v) => s + (v.qty || 0), 0);
      if (total >= MAX_STEMS) return alert(`Máximo ${MAX_STEMS} tallos`);

      const current = state.flowerVariants[key].qty || 0;
      state.flowerVariants[key].qty = current + 1;

      renderFlores();
      updateLive();
    });
  });
}

function renderWraps() {
  const container = document.getElementById('opt-wraps');
  if (!container || !opciones.wraps) return;

  container.innerHTML = opciones.wraps.map(w => {
    const selected = state.wrapId === w.id;
    return `
      <button type="button" data-wrap="${w.id}" class="wrap-option ${selected ? 'selected' : ''}">
        <div class="wrap-color" style="background:${w.color}"></div>
        <div class="wrap-details">
          <div class="wrap-name">${escapeHtml(w.nombre)}</div>
          <div class="wrap-desc">${escapeHtml(w.description || '')}</div>
          <div class="wrap-price">${formatPrice(w.precio)}</div>
        </div>
      </button>
    `;
  }).join('');

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

  container.innerHTML = opciones.ribbons.map(r => {
    const selected = state.ribbonId === r.id;
    return `
      <button type="button" data-ribbon="${r.id}" class="ribbon-option ${selected ? 'selected' : ''}">
        <div class="ribbon-color" style="background:${r.color}"></div>
        <span class="ribbon-name">${escapeHtml(r.nombre)}</span>
        <span class="ribbon-price">${formatPrice(r.precio)}</span>
      </button>
    `;
  }).join('');

  container.querySelectorAll('button[data-ribbon]').forEach(btn => {
    btn.addEventListener('click', () => {
      state.ribbonId = btn.dataset.ribbon;
      renderRibbons();
      updateLive();
    });
  });
}

function renderAll() {
  if (!opciones) return;

  // default selections if empty
  if (!state.wrapId && opciones.wraps?.length) state.wrapId = opciones.wraps[0].id;
  if (!state.ribbonId && opciones.ribbons?.length) state.ribbonId = opciones.ribbons[0].id;

  renderFlores();
  renderWraps();
  renderRibbons();

  // dedicatoria
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

function calculatePrice() {
  let total = 0;

  // flowers (variants)
  if (state.flowerVariants) {
    Object.values(state.flowerVariants).forEach(v => {
      const f = opciones.flores.find(x => x.id === v.flowerId);
      if (f) total += f.precio * (v.qty || 0);
    });
  }

  // wrap
  const wrap = opciones.wraps?.find(w => w.id === state.wrapId);
  if (wrap) total += wrap.precio || 0;

  // ribbon
  const ribbon = opciones.ribbons?.find(r => r.id === state.ribbonId);
  if (ribbon) total += ribbon.precio || 0;

  return Math.round(total);
}

function updateLive() {
  const totalStems = state.flowerVariants ? Object.values(state.flowerVariants).reduce((a, v) => a + (v.qty || 0), 0) : 0;
  const price = calculatePrice();

  // summary
  document.getElementById('stems-count').textContent = totalStems === 0 ? 'Vacío' : `${totalStems} ${totalStems === 1 ? 'flor' : 'flores'}`;
  document.getElementById('custom-total').textContent = formatPrice(price);

  // small summary chips (clean vanilla classes)
  const summaryEl = document.getElementById('selection-summary');
  if (summaryEl) {
    let html = '';
    if (state.flowerVariants) {
      Object.entries(state.flowerVariants).forEach(([key, v]) => {
        const f = opciones.flores.find(x => x.id === v.flowerId);
        const c = opciones.colores.find(x => x.id === v.colorId);
        if (f && c && v.qty > 0) {
          html += `<span class="selection-chip">${escapeHtml(f.nombre)} ${c.nombre} × ${v.qty}</span>`;
        }
      });
    }
    const w = opciones.wraps?.find(x => x.id === state.wrapId);
    if (w) html += `<span class="selection-chip wrap">${escapeHtml(w.nombre)}</span>`;
    const r = opciones.ribbons?.find(x => x.id === state.ribbonId);
    if (r) html += `<span class="selection-chip ribbon">${escapeHtml(r.nombre)}</span>`;
    if (state.message) html += `<span style="font-size:10px;color:#7A6B58;margin-left:2px;">+ dedicatoria</span>`;
    summaryEl.innerHTML = html;
  }

  // preview
  updateRamoPreviewSVG();

  // Force re-paint for Safari/WebKit
  const previewEl = document.getElementById('ramo-preview');
  if (previewEl) {
    previewEl.style.display = 'none';
    // eslint-disable-next-line no-unused-expressions
    previewEl.offsetHeight;
    previewEl.style.display = '';
  }

  // button
  const btn = document.getElementById('add-to-cart-btn');
  if (btn) btn.disabled = totalStems === 0;
}

function updateRamoPreviewSVG() {
  const svg = document.getElementById('ramo-svg');
  if (!svg) return;

  const flowersGroup = svg.querySelector('#flowers');
  const stemsGroup = svg.querySelector('#stems');
  const leavesGroup = svg.querySelector('#leaves');
  if (!flowersGroup || !stemsGroup || !leavesGroup) return;

  // Clear dynamic groups
  flowersGroup.innerHTML = '';
  stemsGroup.innerHTML = '';
  leavesGroup.innerHTML = '';

  // Collect flowers (one entry per individual stem)
  let toDraw = [];
  if (state.flowerVariants) {
    Object.values(state.flowerVariants).forEach(v => {
      const f = opciones.flores.find(x => x.id === v.flowerId);
      const colObj = opciones.colores.find(x => x.id === v.colorId);
      if (f && (v.qty || 0) > 0) {
        // Support css gradient strings gracefully by using a representative solid for preview
        let color = (colObj && colObj.css) ? colObj.css : '#C8860B';
        if (color.startsWith('linear-gradient')) {
          // pick dominant hue for SVG
          color = '#C8860B';
        }
        for (let i = 0; i < (v.qty || 0); i++) {
          toDraw.push({ kind: f.kind || 'rose', color });
        }
      }
    });
  }

  // Beautiful demo if nothing selected (showcases premium preview)
  if (toDraw.length === 0) {
    toDraw = [
      { kind: 'rose', color: '#B3261E' },
      { kind: 'rose', color: '#E88BAD' },
      { kind: 'rose', color: '#C8860B' },
      { kind: 'rose', color: '#B3261E' },
      { kind: 'lily', color: '#F5EFE6' },
      { kind: 'sunflower', color: '#E7C544' },
      { kind: 'aster', color: '#E88BAD' }
    ];
  }

  const n = toDraw.length;

  // === Premium organic layout ===
  // Center the bouquet head higher for elegant proportions
  const centerX = 100;
  const centerY = 89;
  const spread = Math.min(46, 16 + n * 2.1);

  const positions = [];
  for (let i = 0; i < n; i++) {
    const t = n > 1 ? i / (n - 1) : 0.5;
    // Beautiful clustered arc (more natural than perfect circle)
    const angle = (t - 0.5) * 2.05 + (i % 3 - 1) * 0.06;
    let r = spread * (0.55 + Math.sin(t * Math.PI) * 0.38);
    if (i % 4 === 1) r *= 0.8; // inner cluster flowers

    const x = centerX + Math.sin(angle) * r * 0.96 + (i % 2 - 0.5) * 2.8;
    const y = centerY + Math.cos(angle) * r * 0.57 + (i % 3 - 1) * 1.9;

    // Richer size variation + shrink for large bouquets
    let scale = n > 15 ? 0.72 : n > 10 ? 0.82 : 0.94 + Math.sin(i) * 0.07;
    if (i % 5 === 0) scale *= 0.88;

    positions.push({ x: Math.max(32, Math.min(168, x)), y, scale });
  }

  // Draw stems first (behind flowers)
  for (let i = 0; i < n; i++) {
    const p = positions[i];
    const stem = document.createElementNS("http://www.w3.org/2000/svg", "path");
    // Natural curving stems going into the wrap
    const endX = p.x + (p.x - centerX) * 0.07;
    const d = `M${endX} 173 Q${p.x * 0.96 + 3} ${p.y + 36} ${p.x} ${p.y + 3}`;
    stem.setAttribute("d", d);
    stem.setAttribute("fill", "none");
    stem.setAttribute("stroke", "#4f3f2e");
    stem.setAttribute("stroke-width", n > 13 ? "1.55" : "2.0");
    stem.setAttribute("stroke-linecap", "round");
    stem.setAttribute("opacity", "0.64");
    stemsGroup.appendChild(stem);
  }

  // Elegant leaves (scattered tastefully)
  const leafColors = ["#3f5133", "#455d38", "#3a4c2f"];
  const leafDefs = [
    "M0 0 Q-7 -6 -12 -2 Q-6 7 1 4",
    "M0 0 Q8 -5 13 -1 Q7 6 0 3",
    "M0 0 Q-5 -8 -9 -3 Q-3 6 2 2"
  ];
  for (let i = 0; i < Math.min(n + 1, 7); i++) {
    const idx = i % positions.length;
    const p = positions[idx];
    const lx = p.x + (i % 3 - 1) * 10.5;
    const ly = p.y + 27 + (i % 2) * 5;

    const leaf = document.createElementNS("http://www.w3.org/2000/svg", "path");
    leaf.setAttribute("d", leafDefs[i % leafDefs.length]);
    leaf.setAttribute("transform", `translate(${lx},${ly}) scale(0.82)`);
    leaf.setAttribute("fill", leafColors[i % leafColors.length]);
    leaf.setAttribute("opacity", "0.78");
    leavesGroup.appendChild(leaf);

    // second smaller leaf sometimes
    if (i % 2 === 0) {
      const leaf2 = document.createElementNS("http://www.w3.org/2000/svg", "path");
      leaf2.setAttribute("d", leafDefs[(i + 1) % leafDefs.length]);
      leaf2.setAttribute("transform", `translate(${lx + 7},${ly + 8}) scale(0.55)`);
      leaf2.setAttribute("fill", leafColors[(i + 1) % leafColors.length]);
      leaf2.setAttribute("opacity", "0.55");
      leavesGroup.appendChild(leaf2);
    }
  }

  // Draw each flower beautifully
  toDraw.forEach((f, i) => {
    const pos = positions[i] || positions[i % positions.length];
    const { x, y, scale } = pos;
    const g = document.createElementNS("http://www.w3.org/2000/svg", "g");
    g.setAttribute("transform", `translate(${x}, ${y}) scale(${scale})`);
    g.setAttribute("filter", "url(#flowerShadow)");

    const kind = f.kind || 'rose';
    const col = f.color || '#C8860B';

    if (kind === 'rose') {
      // ===== LUXURIOUS MULTI-LAYERED ROSE =====
      const rose = col;

      // Outer lush petals (9)
      for (let k = 0; k < 9; k++) {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-9.5");
        pet.setAttribute("rx", "8.2");
        pet.setAttribute("ry", "13.5");
        pet.setAttribute("fill", rose);
        pet.setAttribute("stroke", "#000");
        pet.setAttribute("stroke-width", "0.45");
        pet.setAttribute("stroke-opacity", "0.075");
        pet.setAttribute("transform", `rotate(${k * 40 + 3})`);
        g.appendChild(pet);
      }

      // Second layer (7)
      for (let k = 0; k < 7; k++) {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-5.5");
        pet.setAttribute("rx", "5.6");
        pet.setAttribute("ry", "10");
        pet.setAttribute("fill", rose);
        pet.setAttribute("transform", `rotate(${k * 51 + 14})`);
        // subtle light wash
        pet.setAttribute("opacity", "0.96");
        g.appendChild(pet);
      }

      // Inner tight petals (5)
      for (let k = 0; k < 5; k++) {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-2.5");
        pet.setAttribute("rx", "3.6");
        pet.setAttribute("ry", "6.2");
        pet.setAttribute("fill", rose);
        pet.setAttribute("transform", `rotate(${k * 72})`);
        g.appendChild(pet);
      }

      // Highlight wash on inner petals
      const hl = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
      hl.setAttribute("cx", "-1.5");
      hl.setAttribute("cy", "-3");
      hl.setAttribute("rx", "3.8");
      hl.setAttribute("ry", "5.5");
      hl.setAttribute("fill", "url(#petalLight)");
      hl.setAttribute("transform", "rotate(18)");
      g.appendChild(hl);

      // Deep rich center
      const center = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      center.setAttribute("cx", "0");
      center.setAttribute("cy", "0.5");
      center.setAttribute("r", "4.3");
      center.setAttribute("fill", "#4f2727");
      g.appendChild(center);

      // Stamen dots
      const stamen = document.createElementNS("http://www.w3.org/2000/svg", "g");
      for (let s = 0; s < 5; s++) {
        const dot = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        const sa = s * 72;
        dot.setAttribute("cx", Math.cos(sa * Math.PI / 180) * 1.7);
        dot.setAttribute("cy", Math.sin(sa * Math.PI / 180) * 1.7 - 0.4);
        dot.setAttribute("r", "0.95");
        dot.setAttribute("fill", "#c9a16f");
        dot.setAttribute("opacity", "0.85");
        stamen.appendChild(dot);
      }
      g.appendChild(stamen);

    } else if (kind === 'sunflower') {
      // ===== RICH SUNFLOWER =====
      // Petals
      for (let k = 0; k < 14; k++) {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-12");
        pet.setAttribute("rx", "3.2");
        pet.setAttribute("ry", "8");
        pet.setAttribute("fill", col);
        pet.setAttribute("transform", `rotate(${k * (360/14)})`);
        g.appendChild(pet);
      }
      // Outer darker ring
      const outer = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      outer.setAttribute("cx", "0");
      outer.setAttribute("cy", "0");
      outer.setAttribute("r", "8.5");
      outer.setAttribute("fill", "#5c4326");
      g.appendChild(outer);
      // Seed texture center
      const seed = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      seed.setAttribute("cx", "0");
      seed.setAttribute("cy", "0");
      seed.setAttribute("r", "5.6");
      seed.setAttribute("fill", "#3c2a19");
      g.appendChild(seed);

      // light seed pattern
      for (let k = 0; k < 6; k++) {
        const sd = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        const a = k * 59;
        sd.setAttribute("cx", Math.cos(a * Math.PI / 180) * 2.9);
        sd.setAttribute("cy", Math.sin(a * Math.PI / 180) * 2.8);
        sd.setAttribute("r", "1.15");
        sd.setAttribute("fill", "#2b2118");
        sd.setAttribute("opacity", "0.7");
        g.appendChild(sd);
      }

    } else if (kind === 'lily') {
      // ===== ELEGANT LILY =====
      const lpetals = [-44, -15, 14, 43, -29, 29];
      lpetals.forEach((rot, idx) => {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-11.5");
        pet.setAttribute("rx", "4.1");
        pet.setAttribute("ry", "16.8");
        pet.setAttribute("fill", col);
        pet.setAttribute("transform", `rotate(${rot})`);
        if (idx % 2 === 0) pet.setAttribute("opacity", "0.95");
        g.appendChild(pet);
      });
      // Throat
      const throat = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
      throat.setAttribute("cx", "0");
      throat.setAttribute("cy", "-2.5");
      throat.setAttribute("rx", "3.3");
      throat.setAttribute("ry", "5.5");
      throat.setAttribute("fill", "#f5f0e7");
      g.appendChild(throat);

      const pistil = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      pistil.setAttribute("cx", "0");
      pistil.setAttribute("cy", "1.6");
      pistil.setAttribute("r", "2.3");
      pistil.setAttribute("fill", "#52422f");
      g.appendChild(pistil);

    } else if (kind === 'aster') {
      // ===== ASTROMELIA / DAISY-STYLE =====
      for (let k = 0; k < 11; k++) {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-8.5");
        pet.setAttribute("rx", "2.6");
        pet.setAttribute("ry", "8.5");
        pet.setAttribute("fill", col);
        pet.setAttribute("transform", `rotate(${k * 32.7})`);
        g.appendChild(pet);
      }
      const cen = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      cen.setAttribute("cx", "0");
      cen.setAttribute("cy", "0");
      cen.setAttribute("r", "4.6");
      cen.setAttribute("fill", "#f4e9d0");
      g.appendChild(cen);
      const cen2 = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      cen2.setAttribute("cx", "0");
      cen2.setAttribute("cy", "0");
      cen2.setAttribute("r", "2.2");
      cen2.setAttribute("fill", "#d4a05a");
      g.appendChild(cen2);

    } else {
      // graceful generic bloom
      for (let k = 0; k < 8; k++) {
        const pet = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
        pet.setAttribute("cx", "0");
        pet.setAttribute("cy", "-7");
        pet.setAttribute("rx", "4.4");
        pet.setAttribute("ry", "9.5");
        pet.setAttribute("fill", col);
        pet.setAttribute("transform", `rotate(${k * 45})`);
        g.appendChild(pet);
      }
      const c = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      c.setAttribute("cx", "0");
      c.setAttribute("cy", "0");
      c.setAttribute("r", "4");
      c.setAttribute("fill", "#f8f2e3");
      g.appendChild(c);
    }

    flowersGroup.appendChild(g);
  });

  // Delicate filler accents (baby's breath style) — only when bouquet is full
  if (n >= 7) {
    for (let i = 0; i < Math.min(n, 11); i += 2) {
      const p = positions[i];
      const filler = document.createElementNS("http://www.w3.org/2000/svg", "g");
      filler.setAttribute("transform", `translate(${p.x + 4.5}, ${p.y + 9})`);
      filler.setAttribute("opacity", "0.45");
      for (let k = 0; k < 3; k++) {
        const dot = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        const a = k * 120;
        dot.setAttribute("cx", Math.cos(a * Math.PI / 180) * 4.5);
        dot.setAttribute("cy", Math.sin(a * Math.PI / 180) * 3);
        dot.setAttribute("r", "1.15");
        dot.setAttribute("fill", "#e9e1d4");
        filler.appendChild(dot);
      }
      flowersGroup.appendChild(filler);
    }
  }

  // === DYNAMIC ELEGANT WRAP ===
  const wrap = svg.querySelector('#wrap');
  const wrapRim = svg.querySelector('#wrap-rim');
  const w = opciones.wraps?.find(x => x.id === state.wrapId);
  if (wrap && w) {
    wrap.setAttribute('fill', w.color);
    // subtle darker edge on wrap
    wrap.setAttribute('stroke', w.color === '#f5f0e6' ? '#d8d0c0' : '#c8b69b');
  }
  if (wrapRim) {
    // Keep rim always light
    wrapRim.setAttribute('stroke', '#fff');
  }

  // === LUXURIOUS RIBBON BOW ===
  let ribbonGroup = svg.querySelector('#ribbon-visual');
  if (!ribbonGroup) {
    ribbonGroup = document.createElementNS("http://www.w3.org/2000/svg", "g");
    ribbonGroup.setAttribute("id", "ribbon-visual");
    svg.appendChild(ribbonGroup);
  }
  ribbonGroup.innerHTML = '';

  const r = opciones.ribbons?.find(x => x.id === state.ribbonId);
  if (r) {
    const rc = r.color;

    // Main horizontal band across wrap
    const band = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    band.setAttribute("x", "41");
    band.setAttribute("y", "172.5");
    band.setAttribute("width", "118");
    band.setAttribute("height", "4.8");
    band.setAttribute("rx", "1.6");
    band.setAttribute("fill", rc);
    ribbonGroup.appendChild(band);

    // Gloss on band
    const glossBand = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    glossBand.setAttribute("x", "43");
    glossBand.setAttribute("y", "172.8");
    glossBand.setAttribute("width", "48");
    glossBand.setAttribute("height", "1.5");
    glossBand.setAttribute("fill", "url(#ribbonGloss)");
    ribbonGroup.appendChild(glossBand);

    // Beautiful bow: left loop
    const leftLoop = document.createElementNS("http://www.w3.org/2000/svg", "path");
    leftLoop.setAttribute("d", "M70 172 Q55 157 46 168 Q57 174 71 171");
    leftLoop.setAttribute("fill", rc);
    leftLoop.setAttribute("stroke", "#fff");
    leftLoop.setAttribute("stroke-width", "0.7");
    leftLoop.setAttribute("opacity", "0.92");
    ribbonGroup.appendChild(leftLoop);

    // right loop
    const rightLoop = document.createElementNS("http://www.w3.org/2000/svg", "path");
    rightLoop.setAttribute("d", "M130 172 Q145 157 154 168 Q143 174 129 171");
    rightLoop.setAttribute("fill", rc);
    rightLoop.setAttribute("stroke", "#fff");
    rightLoop.setAttribute("stroke-width", "0.7");
    rightLoop.setAttribute("opacity", "0.92");
    ribbonGroup.appendChild(rightLoop);

    // Center knot (double)
    const knot1 = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
    knot1.setAttribute("cx", "100");
    knot1.setAttribute("cy", "171");
    knot1.setAttribute("rx", "7.2");
    knot1.setAttribute("ry", "4.4");
    knot1.setAttribute("fill", rc);
    ribbonGroup.appendChild(knot1);

    const knot2 = document.createElementNS("http://www.w3.org/2000/svg", "ellipse");
    knot2.setAttribute("cx", "100");
    knot2.setAttribute("cy", "171");
    knot2.setAttribute("rx", "3.5");
    knot2.setAttribute("ry", "2.2");
    knot2.setAttribute("fill", "#fff");
    knot2.setAttribute("opacity", "0.55");
    ribbonGroup.appendChild(knot2);

    // Graceful long tails
    const tailL = document.createElementNS("http://www.w3.org/2000/svg", "path");
    tailL.setAttribute("d", "M93 173.5 Q77 197 65 209");
    tailL.setAttribute("fill", "none");
    tailL.setAttribute("stroke", rc);
    tailL.setAttribute("stroke-width", "3.8");
    tailL.setAttribute("stroke-linecap", "round");
    ribbonGroup.appendChild(tailL);

    const tailR = document.createElementNS("http://www.w3.org/2000/svg", "path");
    tailR.setAttribute("d", "M107 173.5 Q123 197 135 209");
    tailR.setAttribute("fill", "none");
    tailR.setAttribute("stroke", rc);
    tailR.setAttribute("stroke-width", "3.8");
    tailR.setAttribute("stroke-linecap", "round");
    ribbonGroup.appendChild(tailR);

    // Tail gloss accents
    const tailGlossL = document.createElementNS("http://www.w3.org/2000/svg", "path");
    tailGlossL.setAttribute("d", "M93 173.5 Q79 193 68 204");
    tailGlossL.setAttribute("fill", "none");
    tailGlossL.setAttribute("stroke", "url(#ribbonGloss)");
    tailGlossL.setAttribute("stroke-width", "1.1");
    ribbonGroup.appendChild(tailGlossL);
  }
}

function buildDetalle() {
  const parts = [];

  if (state.flowerVariants) {
    const stemsList = [];
    Object.values(state.flowerVariants).forEach(v => {
      const f = opciones.flores.find(x => x.id === v.flowerId);
      const c = opciones.colores.find(x => x.id === v.colorId);
      if (f && c && v.qty > 0) {
        stemsList.push(`${v.qty}× ${f.nombre} ${c.nombre}`);
      }
    });
    if (stemsList.length) parts.push(stemsList.join(', '));
  }

  const w = opciones.wraps?.find(x => x.id === state.wrapId);
  if (w) parts.push(w.nombre);

  const r = opciones.ribbons?.find(x => x.id === state.ribbonId);
  if (r) parts.push(r.nombre);

  if (state.message) parts.push(`dedicatoria: “${state.message}”`);

  return parts.join(' • ');
}

function calculateTotalPrice() {
  return calculatePrice();
}

async function init() {
  const form = document.getElementById('builder-form');
  if (!form) return;

  try {
    opciones = await loadOpciones();
  } catch (err) {
    console.error(err);
    form.innerHTML = `<p class="empty-msg">No se pudieron cargar las opciones. Usa XAMPP y verifica data/opciones-personalizacion.json.</p>`;
    return;
  }

  // init defaults
  state.wrapId = opciones.wraps?.[0]?.id || null;
  state.ribbonId = opciones.ribbons?.[0]?.id || null;
  if (!state.flowerVariants) state.flowerVariants = {};
  if (!state.selectedColorForFlower) state.selectedColorForFlower = {};

  renderAll();

  // live updates
  form.addEventListener('change', updateLive);
  form.addEventListener('input', updateLive);

  // add to cart
  const addBtn = document.getElementById('add-to-cart-btn');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      const totalStems = state.flowerVariants ? Object.values(state.flowerVariants).reduce((a,v)=>a+(v.qty||0),0) : 0;
      if (totalStems === 0) return;

      const price = calculatePrice();
      const detalle = buildDetalle();

      addToCart({
        key: 'c-' + Date.now(),
        tipo: 'personalizado',
        nombre: 'Ramo personalizado',
        precio: price,
        cantidad: 1,
        detalle,
        imagen: null,
      });

      showToast('¡Ramo agregado al carrito!');
      // reset
      state.flowerVariants = {};
      state.message = '';
      if (document.getElementById('dedicatoria')) document.getElementById('dedicatoria').value = '';
      renderAll();
    });
  }

  // also support form submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    addBtn?.click();
  });

  // initial preview
  setTimeout(updateLive, 60);
}

init();
