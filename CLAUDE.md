# CLAUDE.md — Lirios Floristería (Sitio Web)

Este archivo es la guía de proyecto para Claude Code. Léelo completo antes de generar código. Contiene el contexto del negocio, el stack elegido, la paleta de colores y los requerimientos funcionales del sitio.

---

## 1. Resumen del proyecto

Sitio web para **Lirios Floristería**, negocio ubicado en El Progreso, Yoro, Honduras. El objetivo principal es que el cliente pueda:

1. Ver el catálogo de arreglos/ramos.
2. **Personalizar un ramo** eligiendo flores, colores, tamaño y extras.
3. Agregar productos (normales o personalizados) a un **carrito**.
4. **Compartir el pedido por WhatsApp** con el detalle de productos y el precio total, para cerrar la venta por chat (no hay pasarela de pago en línea — el pago se coordina directamente con la floristería).

No se requiere backend de pagos ni checkout tradicional. El "checkout" real es un mensaje de WhatsApp pre-llenado.

---

## 2. Información del negocio

- **Nombre:** Lirios Floristería
- **Ubicación:** Plaza Rosamanda, local 1, carretera RN21, salida hacia Santa Rita, 23201 El Progreso, Yoro, Honduras
- **Teléfono / WhatsApp:** +504 8750-2362 → formato para el link `wa.me`: **`50487502362`** (código de país 504 + número sin espacios ni guiones, sin el símbolo `+`)
- **Horario:**
  | Día | Horario |
  |---|---|
  | Lunes | 8:30 a.m. – 6 p.m. |
  | Martes | 8:30 a.m. – 6 p.m. |
  | Miércoles | 8:30 a.m. – 6 p.m. |
  | Jueves | 8:30 a.m. – 6 p.m. |
  | Viernes | 8:30 a.m. – 6 p.m. |
  | Sábado | 8:30 a.m. – 6 p.m. |
  | Domingo | 10 a.m. – 4 p.m. |
- **Instagram:** https://www.instagram.com/liriosfloristeriahn/?hl=es
- **Facebook:** https://www.facebook.com/p/Lirios-Florister%C3%ADa-61563818806306/
- **Logo:** ver `/assets/logo.png` (fondo blanco, trazo de hoja + tipografía serif)

---

## 3. Paleta de colores

**Rediseño 2026-08-02 (tres iteraciones el mismo día).** La clienta pidió primero quitar el tono amarillento del sitio y llevarlo a blanco con combinaciones más limpias y elegantes → se probó **blanco + verde salvia/botánico**. Horas después pidió un dorado "elegante y minimalista" (no el mostaza plano original) → se probó **blanco + dorado antiguo/champán**. Poco después pidió volver al verde → paleta final (por ahora): **blanco + verde salvia/botánico**, la misma de la primera iteración. Los **nombres de las variables CSS no cambiaron** en ninguna iteración (`--mostaza`, `--mostaza-dark`, `--mostaza-light`, `--crema`, `--crema-suave`, `--carbon`, `--gris-calido`, todas en `:root` de `css/styles.css` y `css/admin.css`) — solo sus valores hex — para no tener que tocar las cientos de referencias `var(--mostaza)` etc. repartidas por todo el sitio y el panel admin. Si se vuelve a tocar esta paleta, es más simple seguir reasignando estos mismos nombres que renombrarlos (y conviene buscar/reemplazar por valor hex exacto, no solo por nombre de variable — hay bastantes hex hardcodeados fuera de `:root`, sobre todo en el SVG del personalizador). El dorado elegante probado en la segunda iteración era `#B8933E` / `#8F6F2C` / `#D9C08A` con fondo `#FAF6EC` y texto `#241F17` — vale la pena tenerlo a mano por si se retoma más adelante.

| Uso | Nombre (variable) | Hex |
|---|---|---|
| Primario (marca) | `mostaza` | `#4F6144` (verde salvia/botánico) |
| Primario hover/dark | `mostaza-dark` | `#37452F` |
| Primario claro (fondos suaves, badges) | `mostaza-light` | `#C7D3BB` |
| Fondo general | `crema` | `#FFFFFF` |
| Fondo secundario / cards | `crema-suave` | `#F5F6F2` |
| Texto principal | `carbon` | `#23261F` |
| Texto secundario / gris cálido | `gris-calido` | `#6B7268` |
| Blanco | `blanco` | `#FFFFFF` |
| Éxito (agregado al carrito, WhatsApp) | `whatsapp-green` | `#25D366` (definida pero no usada en UI, ver nota abajo) |

**Nota — sin verde de WhatsApp en la UI:** aunque `--whatsapp-green` sigue en `:root`, la clienta pidió no usarlo visualmente (desentonaba con la paleta). Los CTAs de WhatsApp (`.btn-whatsapp`, `.wa-fab`) van en `--carbon` con hover `--mostaza-dark` — el tono exacto de ese hover cambia cada vez que se retoca la paleta, pero la regla en sí (nunca `--whatsapp-green`) sigue vigente.

**Qué NO se tocó en ningún rediseño de paleta:** los colores hardcodeados que representan flores/papeles reales (ej. el amarillo del girasol en la ilustración SVG del personalizador, el listón "Dorado" en `pers_ribbons`) son contenido del catálogo, no color de marca — se dejaron intactos a propósito.

**Logo del footer (2026-08-02):** el footer tiene fondo oscuro (`--carbon`); usa `images/Logo_Negativo.png` (versión clara del logo, ya existía en `/images` junto a `Logo_Positivo.png` sin usar) directo sobre el fondo, sin caja blanca de contraste. El logo del header (fondo claro) sigue usando `images/logo.png` normal.

**Tipografía (actualizado 2026-07-25):** inspirada en el análisis tipográfico real de stampahn.com (theme Shopify) — **DM Sans** (pesos 500/700/800, con itálica) para títulos, navegación, botones y toda etiqueta/UI, y **Arimo** (400/700, con itálica) para el texto de cuerpo, ambas desde Google Fonts. Variables en `css/styles.css`: `--font-display: "DM Sans", ...` y `--font-body: "Arimo", ...`. Se descartaron las fuentes anteriores (Cormorant Garamond + Inter) a favor de un carácter más audaz y gráfico, manteniendo intacta la paleta mostaza/crema de la sección 3.

**Firma visual — cápsula "eyebrow" (actualizado 2026-07-25):** cada sección del sitio antecede su título con un pequeño badge en cápsula (fondo `--carbon`, texto `--mostaza-light`, mayúsculas, tracking amplio, punto decorativo) — clase `.eyebrow` en `styles.css`. Es la adaptación en la paleta de Lirios del lenguaje de badges oscuros y bloques de color audaces de stampahn.com. En el hero de `index.html` además hay un acorde orgánico (forma de pétalo) en degradado mostaza detrás del carrusel de fotos (`.hero-carousel::before`) — la versión floral de esos bloques de color, en vez de copiar literalmente sus formas geométricas deportivas.

---

## 4. Stack tecnológico elegido

Decisión del cliente: **sin frameworks JS, sin build step, hosting compartido tipo cPanel, y con un panel de administración en PHP para que el catálogo, la taxonomía de categorías y las opciones de personalización se editen sin tocar código.** El sitio debe funcionar subiendo los archivos tal cual por FTP/Administrador de Archivos de cPanel — nada de `npm run build`, nada de Node en el servidor.

> **Actualización de arquitectura (2026-07-25):** el catálogo creció de un puñado de productos a una taxonomía de 12 categorías con ~80 subcategorías, tallas con precio propio (S/M/L/XL) y campos adicionales por producto (descripción corta, "incluye", disponibilidad de entrega/retiro). Con ese volumen y esa normalización, se reemplazó el almacenamiento en archivos JSON por una **base de datos MySQL normalizada**, consultada **en vivo** (sin caché intermedia) tanto desde el sitio público como desde el panel admin — ver detalle abajo. La decisión de "sin frameworks / sin build step" para el front-end se mantiene: HTML + CSS + JS vanilla sin cambios; lo único que cambió es de dónde viene el dato.

**Importante — portabilidad:** al ser HTML/CSS/JS + PHP puro (sin Node, sin frameworks propietarios), el sitio se puede subir a **cualquier hosting compartido que soporte PHP** (que es prácticamente el estándar en proveedores hondureños y genéricos: Hostinger, Namecheap, GoDaddy, cPanel de cualquier proveedor local, etc.), con cualquier dominio (`.com`, `.hn`, `.net`...). No hay dependencia de un proveedor específico como Vercel.

| Capa | Tecnología | Por qué |
|---|---|---|
| Estructura | **HTML5 puro**, un archivo `.html` por página (multi-page, no SPA) | Compatible 100% con hosting compartido, cada página es indexable por Google (mejor SEO que una SPA), no requiere servidor Node |
| Estilos | **CSS puro** con variables CSS (`:root { --mostaza: #C8860B; ... }`) en `styles.css` | Control total, cero dependencias externas, carga rápida, sin build tools |
| Interactividad / carrito | **JavaScript vanilla (ES6+)**, sin frameworks | Cubre carrito, personalizador y generación del mensaje de WhatsApp sin compilar nada |
| Persistencia del carrito | **`localStorage`** del navegador (JS nativo) | El carrito sobrevive recargas de página sin backend |
| Catálogo, taxonomía de categorías y opciones de personalización | **Base de datos MySQL normalizada** (`database/schema.sql`), consultada en vivo mediante **PHP 8.2 + PDO** desde `/api/*.php` (sitio público) y directamente desde `/admin` | Con 12 categorías, ~80 subcategorías, tallas con precio propio por producto y decenas de opciones de personalización, un modelo relacional evita duplicación e inconsistencias que un JSON plano ya no podía sostener con orden |
| Panel de administración | **PHP puro** (sin frameworks tipo Laravel), autenticación simple por sesión + contraseña, lee/escribe directamente en MySQL vía PDO (`config/db.php`) | Prácticamente todo hosting compartido con cPanel incluye PHP + MySQL/MariaDB de forma nativa y gratuita; el panel edita catálogo, categorías/subcategorías y personalización sin tocar código |
| Imágenes | Carpeta `/images` con archivos ya optimizados (WebP cuando sea posible); subida de imágenes también desde el panel admin | No hay optimización automática al no usar frameworks, deben subirse ya comprimidas |
| Mapa de ubicación | **Google Maps Embed** (iframe, sin API key) | Gratis, funciona en HTML plano |
| Integración WhatsApp | **Deep link `https://wa.me/<numero>?text=<mensaje-codificado>`** generado con JS puro (`encodeURIComponent`) | No requiere API de WhatsApp Business, cero costo |
| Feed de Instagram | Sección "síguenos" con enlace directo a Instagram/Facebook (sin embed dinámico) | Simplicidad, sin dependencias externas que puedan romperse |
| Hosting | **Cualquier hosting compartido con PHP** (cPanel), dominio `.hn`, `.com` o el que elijan | Portabilidad total; el cliente decide después dónde y con qué dominio publicar |
| Formularios (contacto, si aplica) | Envío directo a WhatsApp o `mailto:`, o un pequeño script PHP de envío de correo si se desea más adelante | PHP ya está disponible por el panel admin, así que esto es trivial de agregar |

**Nota:** el front-end del sitio público sigue siendo HTML + CSS + JS vanilla, sin build step. Las páginas de catálogo/producto/personalizar consultan el dato mediante `fetch()` a pequeños endpoints PHP en `/api/*.php`, que a su vez leen MySQL en cada solicitud (sin caché intermedia) — por eso el catálogo se actualiza al instante apenas se guarda algo en `/admin`. `/admin` usa PHP + PDO directamente contra la misma base de datos.

---

## 5. Estructura de páginas

```
/                     → Home (hero, categorías destacadas, sobre nosotros breve, CTA a catálogo)
/catalogo             → Grid de productos con filtros (categoría, subcategoría, precio)
/producto/[slug]      → Detalle de producto + opción "agregar al carrito"
/personalizar         → Constructor de ramo personalizado (ver sección 6)
/carrito              → Vista del carrito + botón "Enviar pedido por WhatsApp"
/nosotros             → Historia de la floristería, fotos
/contacto             → Dirección, mapa embebido, horario, teléfono, redes sociales
/politica-privacidad  → Política de privacidad (ver sección 6.7)
/terminos-condiciones → Términos y condiciones (ver sección 6.7)
```

---

## 6. Funcionalidades clave (detalle)

### 6.1 Catálogo de productos

**Taxonomía (tablas `categorias` / `subcategorias`):** el catálogo se organiza en 12 categorías con sus propias subcategorías (definidas y sembradas en `database/schema.sql`, 100% editables desde `/admin/categorias.php`): Ramos Florales, Arreglos en Base, Regalos y Complementos, Cumpleaños, Amor y Romance, Graduaciones, Condolencias, Bodas y Eventos, Caballero, Globos, Infantil y Peluches. Cada producto pertenece a **una sola subcategoría** (relación 1:N, no etiquetas múltiples).

**Producto (tabla `productos`):** `nombre`, `slug`, `descripcion_corta` (flores principales, se muestra en la tarjeta), `descripcion` (texto largo, ficha de producto), `imagen`, `incluye` (texto libre, ej. "Tarjeta personalizada y empaque premium"), `entrega_disponible` / `retiro_tienda_disponible` (booleanos independientes), `disponible`, `destacado`.

**Tallas y precio (tabla `producto_variantes`):** cada producto puede tener hasta 4 variantes de talla — **S, M, L, XL** — y **cada talla tiene su propio precio** (no es un precio único con recargo). El catálogo muestra "Desde L. X" cuando hay más de una talla disponible; la ficha de producto deja elegir la talla y el precio se actualiza en vivo. Un producto necesita al menos una talla con precio para poder publicarse.

- Filtro del catálogo por categoría → subcategoría (selects en cascada) y orden por precio.
- Botones de la ficha de producto: **"Agregar al carrito"** (suma la talla elegida al carrito) y **"Comprar ahora"** (agrega esa talla y navega directo a `/carrito.html` para revisar y enviar por WhatsApp).

### 6.2 Personalizador de ramo

**Mejoras de usabilidad (2026-07-26):**
- **Plantillas rápidas** (`PRESETS` en `js/personalizar.js`): 4 combinaciones ya armadas (ej. "Clásico Rojo", "Sol de Girasoles") que llenan flores/papel/listón con un clic, para quien no quiere elegir tallo por tallo. Se validan en el navegador contra las opciones cargadas desde `/admin`, así que si Claudia borra una flor/color usada en una plantilla, esa plantilla simplemente deja de mostrarse (no rompe la página).
- **Progreso de tallos visible:** barra + contador "X / 24 tallos" junto al encabezado de la sección de flores.
- **Total por flor y por color a la vista:** cada tarjeta de flor muestra un badge con el total de tallos de esa flor (sumando todos los colores), y cada muestra de color con tallos agregados muestra su propio contador — antes solo se veía la cantidad del color seleccionado en ese momento, lo que hacía parecer que se "perdían" tallos al cambiar de color.
- **Botón "Vaciar ramo"** junto al encabezado, para reiniciar sin recargar la página.

- Flujo tipo wizard o formulario de un solo paso con:
  - Tipo de flor base
  - Color dominante (paleta de colores de flores, no confundir con la paleta de marca)
  - Tamaño — afecta precio
  - Extras — cada uno con precio adicional
  - Campo de texto para dedicatoria/nota especial
  - Preview del precio total actualizándose en tiempo real
  - Botón "Agregar al carrito"
- Todas estas opciones (tipos de flor, colores, papeles de envoltura, listones y extras, con sus precios) viven en las tablas `pers_flores`, `pers_colores`, `pers_wraps`, `pers_ribbons` y `pers_extras` de la base de datos, y son **editables por el negocio desde el panel `/admin/personalizacion.php`** (sección 7.1) — no están fijas en el código. El personalizador consulta `/api/opciones-personalizacion.php` (que lee MySQL en vivo) y arma el formulario dinámicamente.
- Valores iniciales sugeridos para precargar la base de datos (el negocio los puede cambiar luego desde el panel):

  **Tipos de flor:** Rosas, Girasoles, Lirios, Astromelias, Mixto

  **Colores:** Rojo, Rosado, Blanco, Amarillo, Mixto

  **Tamaños:** Pequeño (+L. 0.00), Mediano (+L. 150.00), Grande (+L. 300.00)

  **Extras:**
  | Extra | Precio sugerido |
  |---|---|
  | Chocolates | L. 120.00 |
  | Peluche pequeño | L. 200.00 |
  | Globo metálico | L. 80.00 |
  | Tarjeta con dedicatoria | L. 30.00 |
  | Florero de vidrio | L. 150.00 |

  ⚠️ Estos precios son placeholders razonables para poblar el sitio inicial — Claudia debe ajustarlos a los precios reales desde el panel antes de publicar.

### 6.3 Carrito
- Lista de items (productos normales + ramos personalizados) con cantidad editable y opción de eliminar.
- Subtotal por item y total general.
- Persistencia en `localStorage` (JS vanilla: `localStorage.setItem/getItem` con JSON) para que no se pierda al recargar.
- Contador de items visible en el header (ícono de carrito).

### 6.4 Compartir por WhatsApp
- Botón principal en `/carrito`: **"Enviar pedido por WhatsApp"**.
- Al hacer clic, además de abrir WhatsApp, se envía (fire-and-forget, sin bloquear ni esperar respuesta) un `POST` a `/api/pedidos.php` que registra el pedido en las tablas `pedidos`/`pedido_items` — esto es lo que alimenta la reportería de ventas del panel admin (sección 6.6). Si ese registro falla (sin conexión, servidor caído), el pedido por WhatsApp se envía igual: nunca se bloquea la venta por un problema de tracking.
- Genera un mensaje de texto con:
  - Listado de productos (nombre, cantidad, personalización si aplica, precio unitario)
  - Total general
  - Nombre del cliente y nota (campo opcional antes de enviar)
- Abre `https://wa.me/<numero-floristeria>?text=<mensaje-url-encoded>`.
- Ejemplo de mensaje generado:
  ```
  ¡Hola! Quiero hacer este pedido en LIRIOS Floristería:

  1x Ramo personalizado (Rosas rojas, mediano, + chocolates) - L. 650.00
  2x Girasoles clásicos - L. 300.00 c/u

  Total: L. 1,250.00

  Nombre: _____
  Nota: _____
  ```
- **Imagen-resumen del pedido (2026-08-02):** WhatsApp no permite adjuntar archivos vía el link `wa.me` (solo texto), así que `js/order-image.js` dibuja en `<canvas>` un "ticket" con foto+nombre+cantidad+precio de cada ítem. Si el navegador soporta `navigator.canShare({files})` (móvil y algunos navegadores de escritorio), se usa `navigator.share({files, text})` para compartir la imagen y el texto juntos directo a WhatsApp desde el selector nativo del sistema, en un solo toque — el cliente elige el chat de LIRIOS él mismo. Si no hay soporte, cae a un flujo de respaldo: se descarga el PNG y aparece un modal (`#order-modal` en `carrito.html`) con la vista previa y un botón "Abrir WhatsApp", pidiendo adjuntar la imagen a mano.

### 6.5 Contacto / Ubicación
- Dirección completa, teléfono clickeable (`tel:`), horario en tabla, mapa embebido de Google Maps, íconos con enlace a Instagram y Facebook.

### 6.6bis Mejoras del 2026-07-26

- **`/admin/pedidos.php`:** lista cada pedido individual (fecha, cliente, nota, items, total) con un **estado** editable (`pendiente` / `coordinado` / `entregado`, columna `pedidos.estado`) — complementa a `reportes.php`, que solo agrega totales.
- **Límite de frecuencia en `/api/pedidos.php`:** máximo 5 pedidos por IP cada 10 minutos (columna `pedidos.ip`), responde `429` si se excede — evita que el único endpoint público de escritura se pueda inundar de pedidos falsos.
- **Notificación por correo:** además de WhatsApp, cada pedido nuevo intenta enviarse por `mail()` a `NOTIFICACION_EMAIL` (constante en `config/db.php`, hoy un placeholder — **cambiar por el correo real del negocio antes de publicar**). No bloquea ni falla el pedido si el envío no funciona (normal en XAMPP local sin SMTP configurado; en cPanel real `mail()` suele funcionar sin configuración extra).
- **Buscador de texto en el catálogo:** `/api/productos.php?q=texto` filtra por `nombre`/`descripcion_corta`/`descripcion` (SQL `LIKE`), combinable con los filtros de categoría/subcategoría/orden.
- **Cambiar contraseña del panel:** `/admin/cambiar-password.php` reescribe `ADMIN_PASSWORD_HASH` en `includes/config.php` (pide la contraseña actual, valida mínimo 8 caracteres, cierra la sesión al terminar). Ojo si se toca a mano: usar `preg_replace_callback`, no `preg_replace`, porque el hash bcrypt trae `$` seguidos de dígitos que `preg_replace` interpretaría como referencias de grupo y lo corrompería.
- **SEO:** `sitemap.php` (dinámico: páginas fijas + un `<url>` por producto disponible, URL base autodetectada del request) y `robots.txt` (bloquea `/admin`, `/api`, `/config`, `/database`); JSON-LD `Florist` en `index.html` con dirección/horario/redes — **el dominio dentro del JSON-LD y del `Sitemap:` de robots.txt es un placeholder, hay que reemplazarlo cuando se elija el dominio real**.
- **Fotos a WebP automáticamente:** `/admin/subir-imagen.php` convierte cada foto subida a WebP (calidad 82) usando GD, si el hosting la tiene disponible (`function_exists('imagewebp')`); si no, guarda el archivo tal cual se subió. En este entorno de desarrollo hubo que habilitar la extensión `gd` en `php.ini` (estaba instalada pero desactivada) y reiniciar Apache — revisar lo mismo en el hosting final si esta función no aparece disponible.
- **Accesibilidad:** se corrigieron combinaciones de color que no llegaban al contraste mínimo AA (texto pequeño en mostaza sobre blanco, verde de disponibilidad sobre crema) — nuevas variables `--mostaza-texto` y `--exito-texto` en `styles.css`, pensadas solo para texto chico sobre fondos claros (los botones/bordes/íconos siguen usando los colores de marca originales, ahí el contraste sí era correcto).

### 6.7 Páginas legales y aviso de cookies (2026-08-02)

- **`/politica-privacidad.html` y `/terminos-condiciones.html`:** páginas estáticas con la misma estructura de header/footer que el resto del sitio (sin depender de PHP ni de MySQL). Usan la clase `.legal-content` (nueva en `styles.css`) para la tipografía de texto largo. Contenido redactado a partir de cómo funciona realmente el sitio hoy: sin pasarela de pago, el pedido se confirma por WhatsApp, `POST /api/pedidos.php` guarda nombre/nota/items/IP (esta última solo para el límite de frecuencia de la sección 6.6bis), y el mapa de Google en `contacto.html` como único tercero que puede instalar cookies. **Ojo:** ambos textos son un borrador razonable para un negocio pequeño en Honduras, no asesoría legal — conviene que Claudia los revise (o un abogado) antes de publicar el sitio, sobre todo si el negocio crece o empieza a operar fuera de Honduras.
- **Enlaces en el footer:** las 9 páginas HTML (7 existentes + las 2 nuevas) tienen un `<span class="footer-legal">` en `.footer-bottom-inner` con los dos enlaces. Como el sitio no tiene un include compartido de footer, cualquier cambio futuro al footer hay que replicarlo a mano en las 9 páginas (igual que ya pasaba con el resto del footer).
- **Aviso de cookies:** banner compartido inyectado por `js/main.js` (junto al FAB de WhatsApp), visible en las 9 páginas públicas. Se decidió que **sí aplica** un aviso porque el mapa de Google en `contacto.html` puede instalar sus propias cookies de terceros — pero **no** se implementó un banner de consentimiento bloqueante ni granular (no hace falta para este nivel de uso de cookies ni es el estándar típico para un negocio local hondureño sin tráfico de la UE): es solo una nota informativa con enlace a la Política de Privacidad, que se descarta con un clic y se recuerda en `localStorage` (`lirios-cookie-notice-dismissed`) para no volver a mostrarse. El carrito en sí usa `localStorage`, no cookies, así que no necesita aviso.

### 6.6 Reportería de ventas (`/admin/reportes.php`)

**Qué mide:** cada pedido que un cliente envía por WhatsApp desde `/carrito.html` queda registrado (tablas `pedidos` / `pedido_items`, ver sección 6.4). Como el negocio no cobra en línea, esto es "pedidos solicitados", no "ventas cobradas" — la UI del reporte lo aclara explícitamente para no confundir a Claudia.

- **Tarjetas de resumen:** pedidos e ingresos de hoy, de la semana (lunes a hoy) y del mes, más el ticket promedio histórico.
- **Tres series con gráfico de barras (CSS puro, sin librerías externas):** últimos 14 días, últimas 12 semanas (lunes a domingo) y últimos 12 meses — cada barra proporcional al máximo de su propia serie, con el monto y la cantidad de pedidos a la par.
- **Productos más pedidos:** top 10 por unidades vendidas (histórico), tomado de `pedido_items.nombre` (incluye variantes de talla y ramos personalizados).
- **Estado vacío:** si todavía no hay pedidos, se muestra un mensaje explicando que el reporte se llena solo en cuanto entre el primer pedido — no requiere ninguna acción manual ni carga de datos.
- Todas las series se calculan trayendo los pedidos de los últimos 12 meses en una sola consulta y agrupándolos en PHP (por día/semana/mes), en vez de repetir `GROUP BY` distintos en SQL — más simple de mantener para el volumen de datos de esta floristería.

---

## 7. Estructura de carpetas sugerida

Estructura plana, lista para subir tal cual a cPanel (todo dentro de `public_html/` en el hosting):

```
/index.html               → Home
/catalogo.html            → Grid de productos
/producto.html            → Detalle de producto (recibe ?id= por query string)
/personalizar.html        → Constructor de ramo
/carrito.html             → Vista del carrito
/nosotros.html            → Sobre la floristería
/contacto.html            → Ubicación, horario, redes
/politica-privacidad.html → Política de privacidad (ver sección 6.7)
/terminos-condiciones.html → Términos y condiciones (ver sección 6.7)
/sitemap.php              → Sitemap XML dinámico (páginas fijas + productos desde MySQL)
/robots.txt               → Bloquea /admin, /api, /config, /database; apunta a /sitemap.php
/css
  styles.css               (variables de color, estilos globales)
  catalogo.css              (estilos específicos si se necesitan, opcional)
  admin.css                  (estilos del panel de administración)
/js
  main.js                   (menú, header, lógica compartida entre páginas)
  cart.js                   (lógica del carrito: agregar, quitar, totales, localStorage)
  whatsapp.js                (arma el mensaje y el link de wa.me)
  productos.js                (consume /api/productos.php y /api/producto.php, renderiza catálogo/detalle)
  personalizar.js              (lógica del constructor de ramo, lee /api/opciones-personalizacion.php)
/config
  db.php                    (conexión PDO compartida a MySQL — la usan /api/*.php y /admin)
/api                        → Endpoints PHP para el sitio público (JSON, MySQL en vivo)
  categorias.php             (árbol categoría > subcategoría — solo lectura)
  productos.php               (listado del catálogo, con filtros ?categoria=&subcategoria=&orden= — solo lectura)
  producto.php                 (detalle de un producto por ?slug=, con variantes y relacionados — solo lectura)
  opciones-personalizacion.php  (flores, colores, papeles, listones y extras del personalizador — solo lectura)
  pedidos.php                    (POST — único endpoint de escritura: registra el pedido enviado por WhatsApp)
/database
  schema.sql                (esquema completo + datos semilla: categorías, subcategorías, catálogo y opciones)
/images
  logo.png
  productos/               (fotos del catálogo, subidas desde el panel admin)
/admin                     → Panel de administración (ver 7.1), PROTEGER con contraseña
  index.php                 (login)
  logout.php
  cambiar-password.php       (reescribe ADMIN_PASSWORD_HASH en includes/config.php)
  dashboard.php                (menú principal del panel)
  pedidos.php                    (lista de pedidos individuales + estado)
  reportes.php                     (ventas diarias/semanales/mensuales, ver sección 6.6)
  productos.php                       (CRUD de productos y sus tallas/precios)
  categorias.php                         (CRUD de categorías y subcategorías)
  personalizacion.php                       (editor de tipos de flor, colores, papeles, listones, extras)
  subir-imagen.php                            (subida de fotos, con conversión automática a WebP)
  includes/
    auth.php                    (verifica sesión, protege todas las páginas del panel)
    config.php                   (contraseña admin hasheada; incluye config/db.php)
    functions.php                 (saneamiento de formularios y helpers sobre PDO)
  .htaccess                  (capa extra de protección del directorio)
```

> Nota: `/data/productos.json` y `/data/opciones-personalizacion.json` quedaron obsoletos con el pivote a MySQL (sección 4) y ya no los lee ninguna página; se conservan solo como referencia histórica del catálogo inicial hasta que se confirme que no hace falta volver a ellos.

**Nota sobre `/producto.html?id=`:** al no haber framework con rutas dinámicas, el detalle de producto se resuelve con un solo archivo HTML que lee el `id` (slug) de la URL (`?id=ramo-rosas-rojas`) y llena el contenido con JavaScript, consultando `/api/producto.php?slug=` (MySQL en vivo). Es el equivalente estático a una ruta dinámica.

### 7.1 Panel de administración (`/admin`)

Objetivo: que Claudia (o cualquier persona del negocio sin conocimientos técnicos) pueda **agregar/editar/eliminar productos del catálogo** (incluyendo tallas y precios), **organizar la taxonomía de categorías/subcategorías** y **editar las opciones del personalizador de ramo** (tipos de flor, colores, papeles, listones, extras y precios) desde el navegador, sin tocar código ni SQL a mano.

- **Login:** una sola contraseña de administrador (no se necesita sistema de usuarios múltiples para este tamaño de negocio). Contraseña guardada **hasheada** (`password_hash` de PHP) en `includes/config.php`, nunca en texto plano.
- **Sesión:** PHP `session_start()` — todas las páginas dentro de `/admin` (excepto `index.php`) verifican con `includes/auth.php` que haya sesión activa; si no, redirigen al login.
- **`productos.php`:** tabla con el catálogo actual (nombre, categoría/subcategoría, tallas y precios, imagen, disponible sí/no), botones para editar o eliminar cada fila, y un formulario para agregar un producto nuevo — incluyendo hasta 4 variantes de talla (S/M/L/XL) con su propio precio, el campo "incluye" y los checkboxes de entrega/retiro. Al guardar, escribe en las tablas `productos` y `producto_variantes` (MySQL).
- **`categorias.php`:** CRUD de categorías y subcategorías (nombre, ícono, orden). Antes de eliminar una categoría o subcategoría, el sistema verifica que no tenga productos asociados (restricción de llave foránea).
- **`personalizacion.php`:** formularios para agregar/quitar/editar cada tipo de flor, color, papel de envoltura, listón y extra (con su precio). Al guardar, escribe en las tablas `pers_*` (MySQL). Esta es la pantalla que resuelve el pedido de "que ellos puedan editar las opciones de personalización".
- **`subir-imagen.php`:** input de tipo archivo que sube la foto a `/images/productos/` y valida tipo/tamaño de archivo antes de guardarla.
- **Seguridad mínima recomendada:**
  - Contraseña fuerte, cambiable desde una pantalla de "cambiar contraseña" dentro del propio panel (`/admin/cambiar-password.php`, resuelto 2026-07-26) — ya no hace falta editar código para cambiarla.
  - `.htaccess` en `/admin` como capa extra (aunque la sesión ya protege el acceso).
  - Servir el sitio con HTTPS (la mayoría de hosting con cPanel ofrece SSL gratis vía Let's Encrypt — hay que activarlo).
  - Validar y sanitizar todo lo que entra por los formularios antes de escribirlo en la base de datos (consultas siempre parametrizadas con PDO, nunca concatenadas).
- **Por qué MySQL y no JSON plano:** con 12 categorías, ~80 subcategorías, tallas con precio propio por producto y varias tablas de opciones de personalización, el catálogo dejó de ser "un puñado de productos" para ser un modelo relacional real — normalizarlo en MySQL evita duplicar nombres de categoría en cada producto, permite validar relaciones con llaves foráneas (ej. no puedes borrar una subcategoría con productos activos) y hace que los reportes/filtros por categoría sean consultas SQL directas en vez de recorrer arreglos en JS. Prácticamente todo hosting compartido con cPanel incluye MySQL/MariaDB sin costo adicional, así que la portabilidad (sección 1) no se ve afectada.

---

## 8. Convenciones de código

- HTML semántico (`<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`), sin librerías externas salvo que sea estrictamente necesario.
- JavaScript vanilla ES6+ (funciones, `fetch`, módulos con `<script type="module">` si conviene separar responsabilidades entre archivos).
- Colores SIEMPRE como variables CSS definidas en `:root` dentro de `styles.css` (sección 3), nunca hardcodear hex sueltos dentro de otros archivos o inline styles.
- Mobile-first: la mayoría de los clientes probablemente entrarán desde el celular para luego escribir por WhatsApp.
- Nombrar archivos, IDs y clases CSS en inglés o kebab-case neutro (convención de código: `cart.js`, `.product-card`), pero todo el contenido/UI visible en **español** (Honduras).
- Formatear precios en **Lempiras (L.)**, con separador de miles.
- El nombre de la marca se escribe siempre **"LIRIOS"** en mayúscula en todo texto visible del sitio y del panel admin (títulos, encabezados, footer, mensajes de WhatsApp) — ej. "LIRIOS Floristería". Excepción: cuando "Lirios" aparece como nombre de una flor (el lirio) dentro del catálogo o el personalizador, se deja en formato de oración normal, ya que ahí es un sustantivo común y no la marca.
- Evitar dependencia de CDNs externos cuando sea posible (fuentes de Google Fonts está bien, pero frameworks CSS/JS externos no, ya que se pidió "puro").
- Cada página HTML debe incluir metaetiquetas básicas de SEO (`title`, `description`) y Open Graph, ya que es un negocio local que se beneficia de aparecer bien en Google/redes.
- El sitio público (`.html` + `/api/*.php`) y el panel (`/admin/*.php`) se mantienen claramente separados: los endpoints de `/api` son de **solo lectura**, con una única excepción deliberada — `/api/pedidos.php` (POST), que registra el pedido que un cliente anónimo envía por WhatsApp desde `carrito.html` (equivalente a un formulario de contacto: cualquiera puede escribir ahí, pero solo para crear ese tipo de registro, nunca para leer o modificar el catálogo). Toda otra escritura pasa por `/admin` con sesión verificada.
- **Cache-busting manual:** al no haber build step, los `<script src="js/...">` y `<link href="css/...">` propios llevan un query string `?v=N` (ej. `js/personalizar.js?v=2`). Cada vez que se edite un archivo `.js` o `.css` existente, hay que subir ese número en **todas** las páginas que lo referencian — si no, los navegadores que ya visitaron el sitio pueden seguir sirviendo la versión vieja desde caché y no ver el cambio (nos pasó durante el desarrollo: ver sección de personalización).
- En PHP: acceso a datos siempre vía **PDO con consultas parametrizadas** (`config/db.php`), nunca SQL concatenado con variables de usuario; usar transacciones (`beginTransaction`/`commit`/`rollBack`) cuando una acción escribe en más de una tabla (ej. producto + sus variantes de talla); siempre validar/sanitizar entradas de formularios antes de guardarlas.

---

## 9. Pendiente de definir con el cliente (Claudia)

- [x] Número de WhatsApp completo con código de país → **+504 8750-2362** (`50487502362`)
- [x] Opciones de personalización de ramo → resuelto: quedan precargadas con valores por defecto (sección 6.2) y son 100% editables desde el panel `/admin`
- [x] Dominio y proveedor de hosting → resuelto: el sitio es portable a cualquier hosting con PHP, se decide después sin afectar el desarrollo
- [x] Edición del catálogo sin programador → resuelto: se construye el panel `/admin` en PHP (sección 7.1)
- [x] Taxonomía de categorías del catálogo → resuelto (2026-07-22): la clienta definió 12 categorías con sus subcategorías (sección 6.1), ya sembradas en `database/schema.sql`
- [x] Modelo de datos para tallas/precio y campos por producto (nombre, precio, descripción corta, tamaño, "incluye", disponibilidad) → resuelto (2026-07-25): base de datos MySQL normalizada, ver sección 4
- [x] Contraseña definitiva del panel de administración → resuelto (2026-07-26): `RosadoRamo252!`, cambiable desde `/admin/cambiar-password.php` sin tocar código
- [ ] Catálogo real de productos: nombres, precios por talla, fotos (mínimo 8-10 para poblar el sitio inicial) — mientras tanto se puede arrancar con los 7 productos de ejemplo/placeholder ya cargados
- [ ] Textos de la sección "Nosotros" (historia de la floristería) y fotos para esa página
- [ ] Confirmar si desean activar HTTPS/SSL gratis (Let's Encrypt) una vez elijan el hosting final — muy recomendado, sobre todo por el panel admin
- [ ] Confirmar que el hosting final incluya MySQL/MariaDB (estándar en cPanel, pero hay que verificarlo al contratar) y las credenciales de esa base de datos para actualizar `config/db.php` al desplegar
- [ ] Correo real del negocio para `NOTIFICACION_EMAIL` en `config/db.php` (hoy es un placeholder, `pedidos@liriosfloristeria.com`)
- [ ] Dominio final, para reemplazar los placeholders en el JSON-LD de `index.html` y en el `Sitemap:` de `robots.txt`
- [ ] Confirmar que el hosting final tenga la extensión GD de PHP con soporte WebP habilitada (si no, `/admin/subir-imagen.php` sigue funcionando, solo que sin convertir las fotos automáticamente)

---

## 10. Roadmap sugerido

1. **Fase 1 (MVP):** estructura de páginas públicas, catálogo servido en vivo desde MySQL vía `/api` (con datos placeholder), carrito, personalizador con opciones precargadas, integración WhatsApp, página de contacto con mapa y redes.
2. **Fase 1.5 (parte del mismo alcance, no opcional):** panel `/admin` en PHP funcional — login, CRUD de productos y de categorías/subcategorías, editor de opciones de personalización, subida de imágenes. Esto reemplaza la edición manual de la base de datos.
3. **Fase 2:** pulir el panel admin si hace falta (ej. pantalla de "cambiar contraseña", reportes simples de qué se agregó al carrito más), y activar HTTPS en el hosting final.
4. **Fase 3:** feed real de Instagram, sistema de reseñas de clientes, notificaciones (ej. floristería recibe pedidos también por correo además de WhatsApp).