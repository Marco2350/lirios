<?php
/**
 * CRUD de zonas de delivery (tabla zonas_delivery): cada zona es un
 * polígono dibujado a mano sobre un mapa de El Progreso (Leaflet +
 * OpenStreetMap, sin API key) con su propio precio. carrito.html usa
 * estas zonas para calcular el costo de delivery según dónde el
 * cliente marque su ubicación (ver api/zonas-delivery.php y
 * js/delivery-map.js).
 *
 * Misma convención que categorias.php: los <form> viven fuera de la
 * tabla y los inputs de cada fila se asocian con form="id".
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

verificar_csrf();

/* ---------- Acciones POST ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_zona') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = limpiar_texto($_POST['nombre'] ?? '', 100);
        $precio = limpiar_precio($_POST['precio'] ?? 0);
        $orden = (int) ($_POST['orden'] ?? 0);
        $visible = isset($_POST['visible']) ? 1 : 0;
        $poligono = json_decode((string) ($_POST['poligono'] ?? ''), true);

        $poligonoValido = is_array($poligono) && count($poligono) >= 3 && array_reduce(
            $poligono,
            fn ($ok, $p) => $ok && is_array($p) && count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1]),
            true
        );

        if ($nombre === '') {
            flash('error', 'El nombre de la zona es obligatorio.');
        } elseif ($precio <= 0) {
            flash('error', 'El precio de la zona debe ser mayor a cero.');
        } elseif (!$poligonoValido) {
            flash('error', 'Dibuja el área de la zona en el mapa (mínimo 3 puntos) antes de guardar.');
        } else {
            $poligonoJson = json_encode(array_map(
                fn ($p) => [round((float) $p[0], 6), round((float) $p[1], 6)],
                $poligono
            ));

            if ($id > 0) {
                db()->prepare('UPDATE zonas_delivery SET nombre=:nombre, precio=:precio, orden=:orden, visible=:visible, poligono=:poligono WHERE id=:id')
                    ->execute(['nombre' => $nombre, 'precio' => $precio, 'orden' => $orden, 'visible' => $visible, 'poligono' => $poligonoJson, 'id' => $id]);
                flash('ok', 'Zona actualizada.');
            } else {
                db()->prepare('INSERT INTO zonas_delivery (nombre, precio, orden, visible, poligono) VALUES (:nombre, :precio, :orden, :visible, :poligono)')
                    ->execute(['nombre' => $nombre, 'precio' => $precio, 'orden' => $orden, 'visible' => $visible, 'poligono' => $poligonoJson]);
                flash('ok', 'Zona agregada.');
            }
        }
        header('Location: zonas-delivery.php');
        exit;
    }

    if ($accion === 'eliminar_zona') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM zonas_delivery WHERE id = ?')->execute([$id]);
        flash('ok', 'Zona eliminada. Los pedidos anteriores que la usaron conservan el nombre y el costo que tenían en ese momento.');
        header('Location: zonas-delivery.php');
        exit;
    }
}

/* ---------- Datos para la página ---------- */

$zonas = db()->query('SELECT * FROM zonas_delivery ORDER BY orden, nombre')->fetchAll();

admin_header('Zonas de delivery', 'zonas-delivery.php');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw==" crossorigin="anonymous" referrerpolicy="no-referrer">

<p class="intro">Define el área de cada zona de tu ciudad y ponle un precio de delivery. Cuando un cliente marque su ubicación en <code>carrito.html</code>, el sitio detecta automáticamente en qué zona cae y le suma ese costo al pedido. Si todavía no hay ninguna zona aquí, el mapa del carrito no aparece — el cliente coordina el delivery igual que antes, sin costo calculado.</p>

<div class="panel">
  <h2>Mapa de zonas</h2>
  <div id="zonas-map" class="zonas-map"></div>
  <p class="muted" style="margin-top:.6rem">Para agregar o cambiar el área de una zona, usa el botón "Editar forma" de su fila en la tabla de abajo.</p>
</div>

<div class="panel">
  <h2>Zonas (<?= count($zonas) ?>)</h2>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead>
        <tr><th>Nombre</th><th>Precio (L.)</th><th>Orden</th><th>Visible</th><th>Forma</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($zonas as $z): $fid = 'zona-' . $z['id']; $poligonoZona = json_decode($z['poligono'], true); $numPuntos = is_array($poligonoZona) ? count($poligonoZona) : 0; ?>
        <tr>
          <td><input type="text" name="nombre" required maxlength="100" form="<?= e($fid) ?>" value="<?= e($z['nombre']) ?>"></td>
          <td><input type="number" name="precio" required min="0.01" step="0.01" style="width:6.5rem" form="<?= e($fid) ?>" value="<?= e((string) $z['precio']) ?>"></td>
          <td><input type="number" name="orden" style="width:5rem" form="<?= e($fid) ?>" value="<?= (int) $z['orden'] ?>"></td>
          <td><input type="checkbox" name="visible" form="<?= e($fid) ?>" <?= !empty($z['visible']) ? 'checked' : '' ?>></td>
          <td>
            <button type="button" class="btn mini secundario" data-editar-forma="<?= e($fid) ?>">Editar forma</button>
            <div class="forma-estado" id="forma-estado-<?= e($fid) ?>"><?= $numPuntos >= 3 ? $numPuntos . ' puntos' : 'Sin definir' ?></div>
          </td>
          <td>
            <div class="acciones-fila">
              <button type="submit" class="btn mini" form="<?= e($fid) ?>">Guardar</button>
              <button type="button" class="btn mini peligro"
                data-confirmar-eliminar="del-<?= e($fid) ?>"
                data-confirmar-mensaje="¿Eliminar la zona «<?= e($z['nombre']) ?>»? Los pedidos ya recibidos conservan el nombre y precio que tenían.">Eliminar</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Fila para agregar zona nueva -->
        <tr>
          <td><input type="text" name="nombre" required maxlength="100" form="zona-nueva" placeholder="Ej: Centro"></td>
          <td><input type="number" name="precio" required min="0.01" step="0.01" style="width:6.5rem" form="zona-nueva" placeholder="50.00"></td>
          <td><input type="number" name="orden" style="width:5rem" form="zona-nueva" value="0"></td>
          <td><input type="checkbox" name="visible" form="zona-nueva" checked></td>
          <td>
            <button type="button" class="btn mini secundario" data-editar-forma="zona-nueva">Editar forma</button>
            <div class="forma-estado" id="forma-estado-zona-nueva">Sin definir</div>
          </td>
          <td><button type="submit" class="btn mini secundario" form="zona-nueva">+ Agregar</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Formularios de zonas (fuera de la tabla, asociados por form="id") -->
  <?php foreach ($zonas as $z): $fid = 'zona-' . $z['id']; ?>
    <form id="<?= e($fid) ?>" method="post" action="zonas-delivery.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar_zona">
      <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
      <input type="hidden" name="poligono" id="poligono-<?= e($fid) ?>" value='<?= e($z['poligono']) ?>'>
    </form>
    <form id="del-<?= e($fid) ?>" method="post" action="zonas-delivery.php">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="eliminar_zona">
      <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
    </form>
  <?php endforeach; ?>
  <form id="zona-nueva" method="post" action="zonas-delivery.php">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar_zona">
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="poligono" id="poligono-zona-nueva" value="">
  </form>
</div>

<!-- Editor de puntos de una zona: se abre desde el botón "Editar forma" de
     cada fila. Tres formas de agregar un punto (en orden, siguiendo el
     borde de la zona): tocar el mini-mapa, usar la ubicación GPS del
     dispositivo (ideal en celular, parado en cada esquina) o escribir la
     coordenada a mano. Al confirmar, escribe el JSON en el input oculto
     "poligono" del formulario real de esa fila (mismo patrón form="id"
     que el resto del panel). -->
<dialog class="modal-puntos" id="modal-editor-puntos">
  <h3>Puntos de la zona: <span id="editor-zona-nombre">—</span></h3>
  <p class="muted">Agrega los puntos en orden, siguiendo el borde de la zona (por ejemplo, parado en cada esquina). Puedes tocar el mapa, usar tu ubicación GPS o escribir coordenadas a mano.</p>

  <div id="editor-mapa-mini" class="editor-mapa-mini"></div>

  <button type="button" class="btn mini" id="btn-agregar-gps">📍 Usar mi ubicación GPS</button>

  <div class="editor-manual-fila">
    <input type="number" step="any" id="input-lat-manual" placeholder="Latitud (ej. 15.4030)">
    <input type="number" step="any" id="input-lng-manual" placeholder="Longitud (ej. -87.8030)">
    <button type="button" class="btn mini secundario" id="btn-agregar-manual">+ Agregar</button>
  </div>

  <ol class="lista-puntos" id="lista-puntos"></ol>

  <p class="editor-aviso" id="editor-aviso" hidden></p>

  <div class="modal-portada-acciones">
    <button type="button" class="btn mini secundario" data-cerrar-modal>Cancelar</button>
    <button type="button" class="btn mini" id="btn-usar-forma">Usar esta forma</button>
  </div>
</dialog>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  });

  const ZONAS = <?= json_encode(array_map(function ($z) {
      $poligono = json_decode($z['poligono'], true);
      return [
          'id' => (int) $z['id'],
          'nombre' => $z['nombre'],
          'poligono' => is_array($poligono) ? $poligono : [],
      ];
  }, $zonas), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

  const COLORES = ['#B8933E', '#5B7A8C', '#8E5B7A', '#5B8C6C', '#8C6B5B', '#6B5B8C'];

  const map = L.map('zonas-map').setView([15.4004, -87.8000], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map);

  const capasPorZona = {};
  ZONAS.forEach((zona, idx) => {
    if (!zona.poligono || zona.poligono.length < 3) return;
    const capa = L.polygon(zona.poligono, { color: COLORES[idx % COLORES.length], weight: 2, fillOpacity: 0.15 }).addTo(map);
    capa.bindPopup('<strong>' + zona.nombre + '</strong>');
    capasPorZona[zona.id] = capa;
  });

  /* ---------- Editor de puntos (modal) ---------- */

  const dialogo = document.getElementById('modal-editor-puntos');
  const listaPuntos = document.getElementById('lista-puntos');
  const nombreSpan = document.getElementById('editor-zona-nombre');
  const avisoEl = document.getElementById('editor-aviso');
  const inputLat = document.getElementById('input-lat-manual');
  const inputLng = document.getElementById('input-lng-manual');
  const botonGps = document.getElementById('btn-agregar-gps');

  let puntos = []; // [[lat,lng], ...] en el orden en que se agregaron
  let objetivoActual = null;
  let miniMap = null;
  let miniPoligono = null;
  let miniMarcadores = [];

  function redondear(n) {
    return Math.round(n * 1e6) / 1e6;
  }

  function ocultarAviso() {
    avisoEl.hidden = true;
  }

  function mostrarAviso(mensaje) {
    avisoEl.textContent = mensaje;
    avisoEl.hidden = false;
  }

  function initMiniMapa() {
    if (miniMap) return;
    miniMap = L.map('editor-mapa-mini').setView([15.4004, -87.8000], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(miniMap);
    miniMap.on('click', function (e) {
      agregarPunto(e.latlng.lat, e.latlng.lng);
    });
  }

  function redibujarMiniMapa() {
    miniMarcadores.forEach(function (m) { miniMap.removeLayer(m); });
    miniMarcadores = [];
    if (miniPoligono) { miniMap.removeLayer(miniPoligono); miniPoligono = null; }

    puntos.forEach(function (p, i) {
      const marcador = L.circleMarker(p, { radius: 7, color: '#2E7D9A', weight: 2, fillColor: '#fff', fillOpacity: 1 })
        .bindTooltip(String(i + 1), { permanent: true, direction: 'top', offset: [0, -4] })
        .addTo(miniMap);
      miniMarcadores.push(marcador);
    });

    if (puntos.length >= 2) {
      miniPoligono = L.polygon(puntos, { color: '#2E7D9A', weight: 2, fillOpacity: 0.15 }).addTo(miniMap);
    }
    if (puntos.length >= 1) {
      miniMap.fitBounds(L.featureGroup(miniMarcadores).getBounds().pad(0.5), { maxZoom: 17 });
    }
  }

  function renderListaPuntos() {
    listaPuntos.innerHTML = puntos.map(function (p, i) {
      return '<li>' + p[0].toFixed(6) + ', ' + p[1].toFixed(6)
        + ' <button type="button" class="btn mini peligro" data-quitar-punto="' + i + '">Quitar</button></li>';
    }).join('');
  }

  function agregarPunto(lat, lng) {
    puntos.push([redondear(lat), redondear(lng)]);
    ocultarAviso();
    renderListaPuntos();
    redibujarMiniMapa();
  }

  listaPuntos.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-quitar-punto]');
    if (!btn) return;
    puntos.splice(Number(btn.dataset.quitarPunto), 1);
    renderListaPuntos();
    redibujarMiniMapa();
  });

  document.getElementById('btn-agregar-manual').addEventListener('click', function () {
    const lat = parseFloat(inputLat.value);
    const lng = parseFloat(inputLng.value);
    if (!isFinite(lat) || !isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
      mostrarAviso('Ingresa una latitud y una longitud válidas (ej. de Google Maps: mantén presionado un punto en el mapa y copia las coordenadas que aparecen).');
      return;
    }
    agregarPunto(lat, lng);
    inputLat.value = '';
    inputLng.value = '';
  });

  botonGps.addEventListener('click', function () {
    if (!navigator.geolocation) {
      mostrarAviso('Este navegador no soporta ubicación GPS. Usa el mapa o escribe la coordenada a mano.');
      return;
    }
    const textoOriginal = botonGps.textContent;
    botonGps.disabled = true;
    botonGps.textContent = 'Obteniendo ubicación…';
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        agregarPunto(pos.coords.latitude, pos.coords.longitude);
        botonGps.disabled = false;
        botonGps.textContent = textoOriginal;
      },
      function (err) {
        botonGps.disabled = false;
        botonGps.textContent = textoOriginal;
        let mensaje = 'No se pudo obtener tu ubicación. Intenta de nuevo o usa el mapa / coordenadas a mano.';
        if (err.code === err.PERMISSION_DENIED) {
          mensaje = 'Bloqueaste el permiso de ubicación del navegador. Actívalo desde la configuración del sitio para usar esta opción, o marca el punto en el mapa / escríbelo a mano.';
        }
        mostrarAviso(mensaje);
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  });

  document.querySelectorAll('[data-editar-forma]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      objetivoActual = btn.dataset.editarForma;

      const nombreInput = document.querySelector('input[name="nombre"][form="' + objetivoActual + '"]');
      nombreSpan.textContent = (nombreInput && nombreInput.value.trim()) ? nombreInput.value.trim() : 'zona nueva';

      const input = document.getElementById('poligono-' + objetivoActual);
      let existentes = [];
      try { existentes = JSON.parse(input.value || '[]'); } catch (e) { existentes = []; }
      puntos = Array.isArray(existentes) ? existentes.slice() : [];

      ocultarAviso();
      renderListaPuntos();
      dialogo.showModal();
      initMiniMapa();
      setTimeout(function () {
        miniMap.invalidateSize();
        redibujarMiniMapa();
      }, 50);
    });
  });

  document.getElementById('btn-usar-forma').addEventListener('click', function () {
    if (puntos.length < 3) {
      mostrarAviso('Agrega al menos 3 puntos para formar el área de la zona.');
      return;
    }
    const input = document.getElementById('poligono-' + objetivoActual);
    input.value = JSON.stringify(puntos);
    const estado = document.getElementById('forma-estado-' + objetivoActual);
    if (estado) estado.textContent = puntos.length + ' puntos';
    dialogo.close();
  });
})();
</script>

<?php admin_footer(); ?>
