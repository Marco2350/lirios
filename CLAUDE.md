# CLAUDE.md — Lirios Floristería (Sitio Web)

Este archivo es la guía de proyecto para Claude Code. Léelo completo antes de generar código. Contiene el contexto del negocio, el stack elegido, la paleta de colores y los requerimientos funcionales del sitio.

---

## 1. Resumen del proyecto

Sitio web para **Lirios Floristería**, negocio ubicado en El Progreso, Yoro, Honduras. El objetivo principal es que el cliente pueda:

1. Ver el catálogo de arreglos/ramos.
2. Agregar productos a un **carrito**.
3. **Compartir el pedido por WhatsApp** con el detalle de productos y el precio total, para cerrar la venta por chat (no hay pasarela de pago en línea — el pago se coordina directamente con la floristería).

> **Nota (2026-08-22):** el sitio tuvo en algún momento un constructor de "ramo personalizado" (`/personalizar.html`, tablas `pers_*`, panel `/admin/personalizacion.php`) — la clienta pidió eliminarlo por completo ("ya no me interesa hacer eso"). Se borraron la página, su JS, el endpoint de API, la pantalla de admin, las tablas `pers_*` (en el schema y en la base remota, con backup previo en `database/backups/`) y todas las referencias de navegación/footer/CTA en el sitio y el panel. El carrito y `pedido_items` conservan el campo `tipo` (`'producto'|'personalizado'`) sin tocar — es solo metadata histórica para pedidos ya recibidos antes de la eliminación, no se usa para nada nuevo.

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

Nombres de variable y valores hex vigentes: ver tabla en `css/CLAUDE.md`. Ese archivo también documenta el historial completo de rediseños (por qué dorado y no verde salvia, el bug de paleta del 2026-08-13, etc.) — se carga solo al trabajar dentro de `css/`, no en cada sesión.

---

## 4. Stack tecnológico elegido

Decisión del cliente: **sin frameworks JS, sin build step, hosting compartido tipo cPanel, y con un panel de administración en PHP para que el catálogo y la taxonomía de categorías se editen sin tocar código.** El sitio debe funcionar subiendo los archivos tal cual por FTP/Administrador de Archivos de cPanel — nada de `npm run build`, nada de Node en el servidor.

> **Actualización de arquitectura (2026-07-25):** el catálogo creció de un puñado de productos a una taxonomía de 12 categorías con ~80 subcategorías, tallas con precio propio (S/M/L/XL) y campos adicionales por producto (descripción corta, "incluye", disponibilidad de entrega/retiro). Con ese volumen y esa normalización, se reemplazó el almacenamiento en archivos JSON por una **base de datos MySQL normalizada**, consultada **en vivo** (sin caché intermedia) tanto desde el sitio público como desde el panel admin — ver detalle abajo. La decisión de "sin frameworks / sin build step" para el front-end se mantiene: HTML + CSS + JS vanilla sin cambios; lo único que cambió es de dónde viene el dato.

**Importante — portabilidad:** al ser HTML/CSS/JS + PHP puro (sin Node, sin frameworks propietarios), el sitio se puede subir a **cualquier hosting compartido que soporte PHP** (que es prácticamente el estándar en proveedores hondureños y genéricos: Hostinger, Namecheap, GoDaddy, cPanel de cualquier proveedor local, etc.), con cualquier dominio (`.com`, `.hn`, `.net`...). No hay dependencia de un proveedor específico como Vercel.

| Capa | Tecnología | Por qué |
|---|---|---|
| Estructura | **HTML5 puro**, un archivo `.html` por página (multi-page, no SPA) | Compatible 100% con hosting compartido, cada página es indexable por Google (mejor SEO que una SPA), no requiere servidor Node |
| Estilos | **CSS puro** con variables CSS (`:root { --mostaza: #C8860B; ... }`) en `styles.css` | Control total, cero dependencias externas, carga rápida, sin build tools |
| Interactividad / carrito | **JavaScript vanilla (ES6+)**, sin frameworks | Cubre carrito y generación del mensaje de WhatsApp sin compilar nada |
| Persistencia del carrito | **`localStorage`** del navegador (JS nativo) | El carrito sobrevive recargas de página sin backend |
| Catálogo y taxonomía de categorías | **Base de datos MySQL normalizada** (`database/schema.sql`), consultada en vivo mediante **PHP 8.2 + PDO** desde `/api/*.php` (sitio público) y directamente desde `/admin` | Con 12 categorías, ~80 subcategorías y tallas con precio propio por producto, un modelo relacional evita duplicación e inconsistencias que un JSON plano ya no podía sostener con orden |
| Panel de administración | **PHP puro** (sin frameworks tipo Laravel), autenticación simple por sesión + contraseña, lee/escribe directamente en MySQL vía PDO (`config/db.php`) | Prácticamente todo hosting compartido con cPanel incluye PHP + MySQL/MariaDB de forma nativa y gratuita; el panel edita catálogo y categorías/subcategorías sin tocar código |
| Imágenes | Carpeta `/images` con archivos ya optimizados (WebP cuando sea posible); subida de imágenes también desde el panel admin | No hay optimización automática al no usar frameworks, deben subirse ya comprimidas |
| Mapa de ubicación | **Google Maps Embed** (iframe, sin API key) | Gratis, funciona en HTML plano |
| Integración WhatsApp | **Deep link `https://wa.me/<numero>?text=<mensaje-codificado>`** generado con JS puro (`encodeURIComponent`) | No requiere API de WhatsApp Business, cero costo |
| Feed de Instagram | Sección "síguenos" con enlace directo a Instagram/Facebook (sin embed dinámico) | Simplicidad, sin dependencias externas que puedan romperse |
| Hosting | **Cualquier hosting compartido con PHP** (cPanel), dominio `.hn`, `.com` o el que elijan | Portabilidad total; el cliente decide después dónde y con qué dominio publicar |
| Formularios (contacto, si aplica) | Envío directo a WhatsApp o `mailto:`, o un pequeño script PHP de envío de correo si se desea más adelante | PHP ya está disponible por el panel admin, así que esto es trivial de agregar |

**Nota:** el front-end del sitio público sigue siendo HTML + CSS + JS vanilla, sin build step. Las páginas de catálogo/producto consultan el dato mediante `fetch()` a pequeños endpoints PHP en `/api/*.php`, que a su vez leen MySQL en cada solicitud (sin caché intermedia) — por eso el catálogo se actualiza al instante apenas se guarda algo en `/admin`. `/admin` usa PHP + PDO directamente contra la misma base de datos.

---

## 6. Funcionalidades clave (detalle)

### 6.1 Catálogo de productos

**Taxonomía (tablas `categorias` / `subcategorias`):** el catálogo se organiza en 12 categorías con sus propias subcategorías (definidas y sembradas en `database/schema.sql`, 100% editables desde `/admin/categorias.php`): Ramos Florales, Arreglos en Base, Regalos y Complementos, Cumpleaños, Amor y Romance, Graduaciones, Condolencias, Bodas y Eventos, Caballero, Globos, Infantil y Peluches. Cada producto pertenece a **una sola subcategoría** (relación 1:N, no etiquetas múltiples).

**Producto (tabla `productos`):** `nombre`, `slug`, `descripcion_corta` (flores principales, se muestra en la tarjeta), `descripcion` (texto largo, ficha de producto), `imagen`, `incluye` (texto libre, ej. "Tarjeta personalizada y empaque premium"), `entrega_disponible` / `retiro_tienda_disponible` (booleanos independientes), `disponible`, `destacado`.

**Chips de categoría en `catalogo.html` (2026-08-13):** el `<select id="filter-categoria">` se reemplazó por una fila de píldoras con scroll horizontal (`.category-chips`/`.category-chip` en `styles.css`, lógica en `initCatalog()` de `js/productos.js`) — un toque para filtrar en vez de abrir un `<select>` nativo, y en móvil se navegan arrastrando (mismo patrón `overflow-x:auto` que usa el selector de talla de la ficha de producto). El estado de la categoría activa vive en la variable `categoriaActual` (ya no en `selCategoria.value`, ese elemento no existe más); `poblarSubcategorias()` y `render()` la leen directo. Cada chip usa el emoji de `categorias.icono` si existe (hoy la semilla no tiene íconos cargados, así que los chips se ven solo con texto — probar poniéndole un emoji a una categoría desde `/admin/categorias.php` para ver el ícono en el chip). Búsqueda, subcategoría y orden siguen en `.filter-bar` sin cambios.

**Tallas y precio (tabla `producto_variantes`):** cada producto puede tener hasta 4 variantes de talla — **S, M, L, XL** — y **cada talla tiene su propio precio** (no es un precio único con recargo). La ficha de producto deja elegir la talla y el precio se actualiza en vivo. Un producto necesita al menos una talla con precio para poder publicarse.

- Filtro del catálogo por categoría → subcategoría (selects en cascada) y orden por precio.
- Botones de la ficha de producto: **"Agregar al carrito"** (suma la talla elegida al carrito) y **"Comprar ahora"** (agrega esa talla y navega directo a `/carrito.html` para revisar y enviar por WhatsApp).

**Tarjeta de producto — chips de talla y tipografía tipo Stampa (2026-08-13):** la clienta pidió acercar la tipografía y presentación de las tarjetas del catálogo a la referencia visual de stampahn.com (nombre del producto en mayúsculas bold, precio en peso normal, swatches de variante bajo el precio). LIRIOS no maneja color por producto, así que el rol de esos swatches lo cumplen **chips de talla** (`.card-tallas` / `.talla-chip` en `styles.css`, lógica en `productCardHtml`/`wireTallaChips` de `js/productos.js`): se muestran solo cuando el producto tiene más de una talla disponible, y al hacer clic actualizan en vivo el precio mostrado en la tarjeta (`data-card-price`) y la talla que agrega el botón "Agregar" (`data-add`/`data-talla`), sin necesidad de entrar al detalle. `product-card h3` pasó a mayúsculas/800 y `.card-price` a peso normal (antes semibold) para el look tipo Stampa. Mismo componente reutilizado en destacados del home, catálogo y relacionados de `producto.html` (antes el bloque de relacionados duplicaba su propio HTML a mano). El catálogo semilla actual solo tiene 1 talla por producto, así que los chips no se ven todavía con datos reales — probar creando un producto con 2+ tallas desde `/admin/productos.php`.

### 6.3 Carrito
- Lista de items con cantidad editable y opción de eliminar.
- Subtotal por item y total general.
- Persistencia en `localStorage` (JS vanilla: `localStorage.setItem/getItem` con JSON) para que no se pierda al recargar.
- Contador de items visible en el header (ícono de carrito).
- **Campos del cliente antes de enviar (2026-08-22):** además de nombre y nota, `/carrito.html` pide teléfono, fecha/hora de entrega, delivery o retiro, dirección, dedicatoria y forma de pago. Los valores se leen en `initCartPage()` de `js/cart.js` y viajan tanto al mensaje de WhatsApp (`buildOrderMessage()` en `js/whatsapp.js`, recibe un objeto `datosCliente` en vez de parámetros sueltos) como al `POST /api/pedidos.php`, que los guarda en columnas nuevas de la tabla `pedidos` (`telefono`, `fecha_entrega`, `hora_entrega`, `tipo_entrega`, `direccion`, `dedicatoria`, `forma_pago`) y los muestra en `/admin/pedidos.php`. Los campos que el cliente deja vacíos salen como `__` en el mensaje de WhatsApp y "(sin especificar)" en el correo de notificación.
- **Campos obligatorios marcados con `*` (2026-08-22):** de todos los campos de arriba, solo **Nombre, Teléfono, Fecha de entrega y Delivery o retiro** son obligatorios — Hora, Dirección, Dedicatoria, Forma de pago y Nota quedan opcionales. La etiqueta "(opcional)" se quitó de los campos opcionales (ya es el estado por defecto) y se reemplazó por un `<span class="required-mark">*</span>` solo en los 4 obligatorios, con una leyenda "* Campos obligatorios" arriba del formulario. `validarCamposObligatorios()` en `js/cart.js` corre al hacer clic en "Enviar pedido por WhatsApp": si falta alguno, le pone borde rojo (`.form-field.invalid`, variable `--error-texto`), enfoca el primero vacío y muestra un toast — sin abrir WhatsApp ni registrar el pedido. Esto es una excepción deliberada al principio de "nunca bloquear la venta" de la sección 6.4: ahí se refiere a no bloquear el envío por WhatsApp si falla el registro en la base de datos (problema técnico), no a permitir pedidos sin datos mínimos de contacto/logística (decisión de negocio).
- **Rediseño de `/carrito.html` (2026-08-22):** la clienta pidió mejorar la vista ("no me gusta como se ve"), sin más detalle — se hizo una auditoría UI/UX completa y dos cambios estructurales:
  - **Lista de productos como recibo, no como tarjetas sueltas:** antes cada item era su propia tarjeta blanca con sombra (N cajas apiladas). Ahora `.cart-items` es una sola tarjeta contenedora y cada `.cart-item` es una fila separada por un borde inferior, con hover sutil (fondo `--crema-suave`) — se lee como un recibo/lista de pedido. La imagen pasó de 84×100px a 92×108px con la forma "arco" de marca (`var(--arch)`, ya usada en otras partes del sitio); el código de ramo pasó de texto monospace suelto a una píldora (`.item-code`); el botón "Eliminar" ganó un ícono de basurero. El cambio requirió tocar `renderCartPage()` en `js/cart.js` (la plantilla de cada `.cart-item`), no solo CSS.
  - **Formulario acortado con divulgación progresiva:** el formulario de 9 campos apilados ("muro de inputs") se reorganizó en 3 niveles de prioridad visual: Nombre/Teléfono sueltos arriba, **Entrega** (fecha, hora, delivery-o-retiro, dirección) agrupado dentro de un bloque con fondo `--crema-suave` y esquinas redondeadas (`.form-section`, ya no solo una etiqueta con línea divisoria), y Dedicatoria/Forma de pago/Nota — los 3 campos verdaderamente opcionales — escondidos por defecto detrás de un `<details class="extra-details">` ("Dedicatoria, forma de pago o nota (opcional)", con el mismo chevron rotatorio `.nav-dropdown-caret` que ya usa el submenú de categorías del header). Esto acorta el formulario visible de 9 a 6 campos sin quitar funcionalidad — los campos obligatorios nunca quedan escondidos detrás del `<details>`. Los `id` de todos los inputs no cambiaron, así que `js/cart.js` no necesitó tocarse para la lectura de valores ni la validación (`.closest('.form-field')` sigue encontrando el wrapper correcto sin importar cuántos niveles de `.form-section`/`<details>` haya alrededor).

### 6.4 Compartir por WhatsApp
- Botón principal en `/carrito`: **"Enviar pedido por WhatsApp"**.
- Al hacer clic, además de abrir WhatsApp, se envía (fire-and-forget, sin bloquear ni esperar respuesta) un `POST` a `/api/pedidos.php` que registra el pedido en las tablas `pedidos`/`pedido_items` — esto es lo que alimenta la reportería de ventas del panel admin (sección 6.6). Si ese registro falla (sin conexión, servidor caído), el pedido por WhatsApp se envía igual: nunca se bloquea la venta por un problema de tracking.
- Genera un mensaje de texto con:
  - Listado de productos (nombre, cantidad, precio unitario, **código de ramo**)
  - Total general
  - Nombre, teléfono, fecha/hora de entrega, delivery o retiro, dirección, dedicatoria, forma de pago y nota — todos los campos que el cliente llenó en `/carrito.html` (sección 6.3); cualquiera que haya dejado vacío sale como `__` en el mensaje
  - Un enlace directo a la foto real del arreglo (o una lista de enlaces, uno por producto, si el carrito tiene más de un item) — ver el punto de "Referencia del arreglo" más abajo
- Abre `https://wa.me/<numero-floristeria>?text=<mensaje-url-encoded>`.
- Ejemplo de mensaje generado (`buildOrderMessage()` en `js/whatsapp.js`, formato pedido por la clienta el 2026-08-22):
  ```
  *PEDIDO WEB - LIRIOS FLORISTERÍA*

  - Ramo de rosas rojas (mediano) - L. 650.00 [Código: RM-4K2P9]
  - 2x Girasoles clásicos - L. 300.00 c/u [Código: RM-9X0A1]
  *Total:* L. 1,250.00

  *Nombre:* Aldo Noe Perez Moreno
  *Teléfono:* 9988-7766
  *Entrega:* 25/08/2026 / *Hora:* 14:30
  *Delivery o retiro:* Delivery a domicilio
  *Dirección:* Barrio El Centro, 2da calle
  *Dedicatoria:* __
  *Pago:* __
  *Nota:* __

  *Referencia del arreglo:*
  https://liriosfloristeria.com/images/productos/96d2186b-19b2-4e64-9b34-70f7a8526f84.webp
  ```
- **Sin emoji ni íconos, formato con negritas de WhatsApp (2026-08-22):** la primera versión de este mensaje usaba emoji "modernos" (🌸💐💰👤📱📅🚚📍💌💳📝📸, rango astral U+1F000+, 4 bytes UTF-8) y, tras un reporte de "�" en WhatsApp Web (bug conocido del prellenado con caracteres astrales — se verificó con inspección de bytes que el link `wa.me` que genera el sitio está perfectamente codificado, el problema era de WhatsApp), se cambiaron por símbolos BMP (`❀ • ☎ ✈ ✉ ✎`). La clienta pidió después quitar **todos** los íconos ("no me gusta") y formatear mejor el mensaje. Versión final: cero emoji/símbolos decorativos — las etiquetas de cada campo (`*Nombre:*`, `*Total:*`, etc.) usan el markdown nativo de WhatsApp (asteriscos → negrita) para la jerarquía visual, y cada producto lleva un guion `-` como viñeta. Es texto 100% ASCII salvo los acentos del español, así que no puede volver a pasar el bug de WhatsApp Web con caracteres astrales.
- **Referencia del arreglo = foto real, no placeholder (2026-08-22):** `buildReferenciaLineas()` en `js/whatsapp.js` toma el campo `imagen` de cada item del carrito (ruta relativa tal como la guarda `/admin/subir-imagen.php`, ej. `images/productos/xxx.webp`) y la convierte en URL absoluta con `new URL(ruta, location.href)` — se resuelve contra `location.href` y no contra `location.origin` a propósito, porque el sitio es de estructura plana (todas las páginas públicas e `/images` viven en la misma carpeta) y así el enlace sale correcto tanto si el sitio está publicado en la raíz del dominio como si está en una subcarpeta (como este entorno de desarrollo, `/PROYECTOS-PHP/lirios/`). Con 1 producto en el carrito sale un solo enlace en "Referencia del arreglo"; con 2+ sale una lista "Referencia de los arreglos:" con un enlace por producto, precedido de su nombre. Si algún item no tiene foto (`imagen` es `null`), simplemente no aparece en la lista; si ningún item del carrito tiene foto, la línea dice "(sin foto disponible)".
- **Código de ramo por ítem (2026-08-05):** cada item del carrito lleva un código corto (ej. `RM-4K2P9`), calculado en el navegador con un hash determinista de la `key` del item (`codigoRamo()` en `js/cart.js`) — el mismo producto+talla siempre produce el mismo código. Se genera 100% en el cliente (nunca se espera al servidor) para no romper el principio de "el WhatsApp nunca se bloquea por el registro del pedido" de arriba: el código va en el mensaje de WhatsApp y, en paralelo, se manda también a `/api/pedidos.php`, que lo guarda en `pedido_items.codigo` (columna `VARCHAR(20)`, saneada con whitelist `[A-Z0-9-]`). `/admin/codigos.php` es el reporte para buscar un código y ver a qué ramo corresponde, con la lista de pedidos donde apareció — así Claudia puede identificar el ramo exacto a partir de lo que el cliente copió y pegó de WhatsApp.
- **Ya NO se envía imagen-resumen del pedido (2026-08-05):** hasta el 2026-08-02 existía `js/order-image.js`, que dibujaba en `<canvas>` un "ticket" con foto de cada producto y lo compartía junto al texto (o lo descargaba con un modal de respaldo). Se eliminó por pedido de la clienta a favor del código de ramo de arriba, que resuelve el mismo problema (identificar qué pidió el cliente) sin depender de que el cliente adjunte una imagen a mano. El flujo de "Enviar pedido por WhatsApp" ahora es directo: registra el pedido (fire-and-forget) y abre `wa.me` de inmediato, sin pasos intermedios.

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
/config
  db.php                    (conexión PDO compartida a MySQL — la usan /api/*.php y /admin)
/api                        → Endpoints PHP para el sitio público (JSON, MySQL en vivo)
  categorias.php             (árbol categoría > subcategoría — solo lectura)
  productos.php               (listado del catálogo, con filtros ?categoria=&subcategoria=&orden= — solo lectura)
  producto.php                 (detalle de un producto por ?slug=, con variantes y relacionados — solo lectura)
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
  codigos.php                      (reporte: código de ramo → a qué ramo corresponde, ver sección 6.4)
  reportes.php                     (ventas diarias/semanales/mensuales, ver sección 6.6)
  productos.php                       (CRUD de productos y sus tallas/precios)
  categorias.php                         (CRUD de categorías y subcategorías)
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

Ver `admin/CLAUDE.md` para el detalle completo (login/sesión, qué escribe cada pantalla, seguridad, por qué MySQL) — se carga solo al trabajar dentro de `/admin`.

---

## 8. Convenciones de código

- HTML semántico (`<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`), sin librerías externas salvo que sea estrictamente necesario.
- JavaScript vanilla ES6+ (funciones, `fetch`, módulos con `<script type="module">` si conviene separar responsabilidades entre archivos).
- Colores SIEMPRE como variables CSS definidas en `:root` dentro de `styles.css` (ver sección 3 y `css/CLAUDE.md`), nunca hardcodear hex sueltos dentro de otros archivos o inline styles.
- Mobile-first: la mayoría de los clientes probablemente entrarán desde el celular para luego escribir por WhatsApp.
- Nombrar archivos, IDs y clases CSS en inglés o kebab-case neutro (convención de código: `cart.js`, `.product-card`), pero todo el contenido/UI visible en **español** (Honduras).
- Formatear precios en **Lempiras (L.)**, con separador de miles.
- El nombre de la marca se escribe siempre **"LIRIOS"** en mayúscula en todo texto visible del sitio y del panel admin (títulos, encabezados, footer, mensajes de WhatsApp) — ej. "LIRIOS Floristería". Excepción: cuando "Lirios" aparece como nombre de una flor (el lirio) dentro del catálogo, se deja en formato de oración normal, ya que ahí es un sustantivo común y no la marca.
- Evitar dependencia de CDNs externos cuando sea posible (fuentes de Google Fonts está bien, pero frameworks CSS/JS externos no, ya que se pidió "puro").
- Cada página HTML debe incluir metaetiquetas básicas de SEO (`title`, `description`) y Open Graph, ya que es un negocio local que se beneficia de aparecer bien en Google/redes.
- El sitio público (`.html` + `/api/*.php`) y el panel (`/admin/*.php`) se mantienen claramente separados: los endpoints de `/api` son de **solo lectura**, con una única excepción deliberada — `/api/pedidos.php` (POST), que registra el pedido que un cliente anónimo envía por WhatsApp desde `carrito.html` (equivalente a un formulario de contacto: cualquiera puede escribir ahí, pero solo para crear ese tipo de registro, nunca para leer o modificar el catálogo). Toda otra escritura pasa por `/admin` con sesión verificada.
- **Cache-busting manual:** al no haber build step, los `<script src="js/...">` y `<link href="css/...">` propios llevan un query string `?v=N` (ej. `js/productos.js?v=18`). Cada vez que se edite un archivo `.js` o `.css` existente, hay que subir ese número en **todas** las páginas que lo referencian — si no, los navegadores que ya visitaron el sitio pueden seguir sirviendo la versión vieja desde caché y no ver el cambio (nos pasó durante el desarrollo).
- En PHP: acceso a datos siempre vía **PDO con consultas parametrizadas** (`config/db.php`), nunca SQL concatenado con variables de usuario; usar transacciones (`beginTransaction`/`commit`/`rollBack`) cuando una acción escribe en más de una tabla (ej. producto + sus variantes de talla); siempre validar/sanitizar entradas de formularios antes de guardarlas.

---

## 9. Pendiente de definir con el cliente (Claudia)

- [x] Número de WhatsApp completo con código de país → **+504 8750-2362** (`50487502362`)
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