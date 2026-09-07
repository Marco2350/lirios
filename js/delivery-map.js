/* =========================================================
   delivery-map.js — mapa de zonas de delivery en carrito.html
   Usa Leaflet + OpenStreetMap (CDN, sin API key — cargado como <script>
   clásico antes de este módulo, expone la variable global L) para que
   el cliente marque su ubicación con un pin arrastrable. Detecta en qué
   zona (polígono dibujado desde /admin/zonas-delivery.php) cae ese
   punto y expone el resultado en LiriosDelivery para que cart.js arme
   el total y el mensaje de WhatsApp.

   Si todavía no hay ninguna zona visible cargada en el sistema
   (Claudia no ha dibujado ninguna desde el panel todavía), el mapa ni
   se muestra: el delivery se coordina como antes, sin costo calculado
   automáticamente, para no bloquear pedidos mientras se configura.
   ========================================================= */

const CENTRO_EL_PROGRESO = [15.4004, -87.8000];
const ZOOM_INICIAL = 14;

export const LiriosDelivery = {
  zona: null,               // { id, nombre, precio } de la zona detectada, o null
  lat: null,
  lng: null,
  zonasConfiguradas: false, // true en cuanto se sabe que hay >= 1 zona visible
};

/** Ray casting: ¿el punto [lat,lng] cae dentro del polígono [[lat,lng],...]? */
function puntoEnPoligono(lat, lng, poligono) {
  let dentro = false;
  for (let i = 0, j = poligono.length - 1; i < poligono.length; j = i++) {
    const [latI, lngI] = poligono[i];
    const [latJ, lngJ] = poligono[j];
    const cruza =
      lngI > lng !== lngJ > lng &&
      lat < ((latJ - latI) * (lng - lngI)) / (lngJ - lngI) + latI;
    if (cruza) dentro = !dentro;
  }
  return dentro;
}

function detectarZona(lat, lng, zonas) {
  return zonas.find((z) => z.poligono.length >= 3 && puntoEnPoligono(lat, lng, z.poligono)) || null;
}

let map = null;
let marker = null;
let zonasCache = [];

function actualizarEstado(statusEl) {
  if (LiriosDelivery.zona) {
    statusEl.textContent = `Zona: ${LiriosDelivery.zona.nombre} — costo de delivery L. ${LiriosDelivery.zona.precio.toFixed(2)}`;
    statusEl.classList.remove('fuera-cobertura');
  } else {
    statusEl.textContent =
      'Esa ubicación está fuera de nuestra zona de cobertura. Escríbenos directo por WhatsApp para coordinar la entrega.';
    statusEl.classList.add('fuera-cobertura');
  }
  document.dispatchEvent(new CustomEvent('delivery-zone:changed'));
}

function onMarcadorMovido(statusEl) {
  const { lat, lng } = marker.getLatLng();
  LiriosDelivery.lat = lat;
  LiriosDelivery.lng = lng;
  LiriosDelivery.zona = detectarZona(lat, lng, zonasCache);
  actualizarEstado(statusEl);
}

/** Iconos por defecto de Leaflet vía CDN (si no, el pin sale roto: bug conocido al no servir leaflet.css/js desde el mismo origen). */
function configurarIconosLeaflet() {
  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  });
}

function initMap(statusEl) {
  if (map) {
    setTimeout(() => map.invalidateSize(), 0);
    return;
  }
  configurarIconosLeaflet();

  map = L.map('delivery-map').setView(CENTRO_EL_PROGRESO, ZOOM_INICIAL);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map);

  zonasCache.forEach((z) => {
    if (z.poligono.length >= 3) {
      L.polygon(z.poligono, { color: '#B8933E', weight: 1.5, fillOpacity: 0.08 }).addTo(map);
    }
  });

  marker = L.marker(CENTRO_EL_PROGRESO, { draggable: true }).addTo(map);
  marker.on('dragend', () => onMarcadorMovido(statusEl));
  onMarcadorMovido(statusEl); // posición inicial

  setTimeout(() => map.invalidateSize(), 0);
}

function initDeliveryMap() {
  const page = document.getElementById('cart-page');
  if (!page) return;

  const tipoSelect = document.getElementById('customer-delivery-type');
  const bloque = document.getElementById('delivery-map-block');
  const statusEl = document.getElementById('delivery-zone-status');
  if (!tipoSelect || !bloque || !statusEl) return;

  function actualizarVisibilidad() {
    const esDelivery = tipoSelect.value === 'Delivery a domicilio';
    if (esDelivery && LiriosDelivery.zonasConfiguradas) {
      bloque.hidden = false;
      initMap(statusEl);
    } else {
      bloque.hidden = true;
      LiriosDelivery.zona = null;
      document.dispatchEvent(new CustomEvent('delivery-zone:changed'));
    }
  }

  fetch('api/zonas-delivery.php')
    .then((r) => r.json())
    .then((data) => {
      zonasCache = Array.isArray(data.zonas) ? data.zonas : [];
      LiriosDelivery.zonasConfiguradas = zonasCache.length > 0;
      actualizarVisibilidad();
    })
    .catch(() => {
      zonasCache = [];
      LiriosDelivery.zonasConfiguradas = false;
      actualizarVisibilidad();
    });

  tipoSelect.addEventListener('change', actualizarVisibilidad);
}

initDeliveryMap();
