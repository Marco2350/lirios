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

Extraída del logo (tono mostaza/dorado predominante). Definir como variables CSS en `:root` dentro de `css/styles.css`:

| Uso | Nombre | Hex |
|---|---|---|
| Primario (marca) | `mostaza` / gold | `#C8860B` |
| Primario hover/dark | `mostaza-dark` | `#A66E08` |
| Primario claro (fondos suaves, badges) | `mostaza-light` | `#E8B85C` |
| Fondo general | `crema` | `#FFF9F0` |
| Fondo secundario / cards | `crema-suave` | `#FFF3E0` |
| Texto principal | `carbon` | `#2B2118` |
| Texto secundario / gris cálido | `gris-calido` | `#7A6B58` |
| Blanco | `blanco` | `#FFFFFF` |
| Éxito (agregado al carrito, WhatsApp) | `whatsapp-green` | `#25D366` |

Tipografía sugerida: una serif elegante para títulos (similar a la del logo, ej. **Cormorant Garamond** o **Playfair Display**) + una sans-serif limpia para texto (ej. **Inter** o **Poppins**), ambas desde Google Fonts.

---

## 4. Stack tecnológico elegido

Decisión del cliente: **sin frameworks, sin build step, hosting compartido tipo cPanel, y con un panel de administración en PHP para que el catálogo y las opciones de personalización se editen sin tocar código.** El sitio debe funcionar subiendo los archivos tal cual por FTP/Administrador de Archivos de cPanel — nada de `npm run build`, nada de Node en el servidor.

**Importante — portabilidad:** al ser HTML/CSS/JS + PHP puro (sin Node, sin frameworks propietarios), el sitio se puede subir a **cualquier hosting compartido que soporte PHP** (que es prácticamente el estándar en proveedores hondureños y genéricos: Hostinger, Namecheap, GoDaddy, cPanel de cualquier proveedor local, etc.), con cualquier dominio (`.com`, `.hn`, `.net`...). No hay dependencia de un proveedor específico como Vercel.

| Capa | Tecnología | Por qué |
|---|---|---|
| Estructura | **HTML5 puro**, un archivo `.html` por página (multi-page, no SPA) | Compatible 100% con hosting compartido, cada página es indexable por Google (mejor SEO que una SPA), no requiere servidor Node |
| Estilos | **CSS puro** con variables CSS (`:root { --mostaza: #C8860B; ... }`) en `styles.css` | Control total, cero dependencias externas, carga rápida, sin build tools |
| Interactividad / carrito | **JavaScript vanilla (ES6+)**, sin frameworks | Cubre carrito, personalizador y generación del mensaje de WhatsApp sin compilar nada |
| Persistencia del carrito | **`localStorage`** del navegador (JS nativo) | El carrito sobrevive recargas de página sin backend |
| Catálogo de productos y opciones de personalización | **JSON (`/data/productos.json` y `/data/opciones-personalizacion.json`)**, generado y editado a través del **panel de administración en PHP** (ver sección 7.1) | El sitio público sigue siendo estático y rápido (lee el JSON con `fetch()`), pero Claudia/el negocio puede editarlo desde un navegador sin abrir código |
| Panel de administración | **PHP puro** (sin frameworks tipo Laravel), autenticación simple por sesión + contraseña, lee/escribe directamente los archivos JSON de `/data` | Prácticamente todo hosting compartido con cPanel soporta PHP de forma nativa y gratuita; evita configurar una base de datos para un catálogo de este tamaño |
| Imágenes | Carpeta `/images` con archivos ya optimizados (WebP cuando sea posible); subida de imágenes también desde el panel admin | No hay optimización automática al no usar frameworks, deben subirse ya comprimidas |
| Mapa de ubicación | **Google Maps Embed** (iframe, sin API key) | Gratis, funciona en HTML plano |
| Integración WhatsApp | **Deep link `https://wa.me/<numero>?text=<mensaje-codificado>`** generado con JS puro (`encodeURIComponent`) | No requiere API de WhatsApp Business, cero costo |
| Feed de Instagram | Sección "síguenos" con enlace directo a Instagram/Facebook (sin embed dinámico) | Simplicidad, sin dependencias externas que puedan romperse |
| Hosting | **Cualquier hosting compartido con PHP** (cPanel), dominio `.hn`, `.com` o el que elijan | Portabilidad total; el cliente decide después dónde y con qué dominio publicar |
| Formularios (contacto, si aplica) | Envío directo a WhatsApp o `mailto:`, o un pequeño script PHP de envío de correo si se desea más adelante | PHP ya está disponible por el panel admin, así que esto es trivial de agregar |

**Nota:** el sitio público (lo que ve el cliente final) sigue siendo 100% estático — rápido, sin lógica de servidor en cada carga. Solo el panel `/admin` usa PHP, y únicamente para leer/escribir los JSON del catálogo.

---

## 5. Estructura de páginas

```
/                     → Home (hero, categorías destacadas, sobre nosotros breve, CTA a catálogo)
/catalogo             → Grid de productos con filtros (ocasión, tipo de flor, precio)
/producto/[slug]      → Detalle de producto + opción "agregar al carrito"
/personalizar         → Constructor de ramo personalizado (ver sección 6)
/carrito              → Vista del carrito + botón "Enviar pedido por WhatsApp"
/nosotros             → Historia de la floristería, fotos
/contacto             → Dirección, mapa embebido, horario, teléfono, redes sociales
```

---

## 6. Funcionalidades clave (detalle)

### 6.1 Catálogo de productos
- Cada producto: `id`, `nombre`, `descripcion`, `precio`, `categoria`, `ocasion` (cumpleaños, aniversario, condolencias, etc.), `imagen`, `disponible`.
- Filtro por categoría/ocasión y orden por precio.

### 6.2 Personalizador de ramo
- Flujo tipo wizard o formulario de un solo paso con:
  - Tipo de flor base
  - Color dominante (paleta de colores de flores, no confundir con la paleta de marca)
  - Tamaño — afecta precio
  - Extras — cada uno con precio adicional
  - Campo de texto para dedicatoria/nota especial
  - Preview del precio total actualizándose en tiempo real
  - Botón "Agregar al carrito"
- Todas estas opciones (tipos de flor, colores, tamaños, extras y sus precios) viven en `/data/opciones-personalizacion.json` y son **editables por el negocio desde el panel `/admin`** (sección 7.1) — no están fijas en el código. El personalizador simplemente lee ese JSON y arma el formulario dinámicamente.
- Valores iniciales sugeridos para precargar el JSON (el negocio los puede cambiar luego desde el panel):

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
- Genera un mensaje de texto con:
  - Listado de productos (nombre, cantidad, personalización si aplica, precio unitario)
  - Total general
  - Nombre del cliente y nota (campo opcional antes de enviar)
- Abre `https://wa.me/<numero-floristeria>?text=<mensaje-url-encoded>`.
- Ejemplo de mensaje generado:
  ```
  ¡Hola! Quiero hacer este pedido en Lirios Floristería:

  1x Ramo personalizado (Rosas rojas, mediano, + chocolates) - L. 650.00
  2x Girasoles clásicos - L. 300.00 c/u

  Total: L. 1,250.00

  Nombre: _____
  Nota: _____
  ```

### 6.5 Contacto / Ubicación
- Dirección completa, teléfono clickeable (`tel:`), horario en tabla, mapa embebido de Google Maps, íconos con enlace a Instagram y Facebook.

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
/css
  styles.css               (variables de color, estilos globales)
  catalogo.css              (estilos específicos si se necesitan, opcional)
  admin.css                  (estilos del panel de administración)
/js
  main.js                   (menú, header, lógica compartida entre páginas)
  cart.js                   (lógica del carrito: agregar, quitar, totales, localStorage)
  whatsapp.js                (arma el mensaje y el link de wa.me)
  productos.js                (carga productos.json, renderiza catálogo/detalle)
  personalizar.js              (lógica del constructor de ramo, lee opciones-personalizacion.json)
/data
  productos.json
  opciones-personalizacion.json
/images
  logo.png
  productos/               (fotos del catálogo, subidas desde el panel admin)
/admin                     → Panel de administración (ver 7.1), PROTEGER con contraseña
  index.php                 (login)
  logout.php
  dashboard.php              (menú principal del panel)
  productos.php               (CRUD de productos del catálogo)
  personalizacion.php          (editor de tipos de flor, colores, tamaños, extras)
  subir-imagen.php              (subida de fotos de producto)
  includes/
    auth.php                    (verifica sesión, protege todas las páginas del panel)
    config.php                   (contraseña admin, hasheada)
    functions.php                 (leer/escribir JSON de forma segura)
  .htaccess                  (capa extra de protección del directorio)
```

**Nota sobre `/producto.html?id=`:** al no haber framework con rutas dinámicas, el detalle de producto se resuelve con un solo archivo HTML que lee el `id` de la URL (`?id=ramo-rosas-rojas`) y llena el contenido con JavaScript desde `productos.json`. Es el equivalente estático a una ruta dinámica.

### 7.1 Panel de administración (`/admin`)

Objetivo: que Claudia (o cualquier persona del negocio sin conocimientos técnicos) pueda **agregar/editar/eliminar productos del catálogo** y **editar las opciones del personalizador de ramo** (tipos de flor, colores, tamaños, extras y precios) desde el navegador, sin tocar código ni JSON a mano.

- **Login:** una sola contraseña de administrador (no se necesita sistema de usuarios múltiples para este tamaño de negocio). Contraseña guardada **hasheada** (`password_hash` de PHP) en `includes/config.php`, nunca en texto plano.
- **Sesión:** PHP `session_start()` — todas las páginas dentro de `/admin` (excepto `index.php`) verifican con `includes/auth.php` que haya sesión activa; si no, redirigen al login.
- **`productos.php`:** tabla con el catálogo actual (nombre, precio, categoría, imagen, disponible sí/no), botones para editar o eliminar cada fila, y un formulario para agregar un producto nuevo. Al guardar, reescribe `data/productos.json`.
- **`personalizacion.php`:** formularios para agregar/quitar/editar cada tipo de flor, color, tamaño (con su recargo) y extra (con su precio). Al guardar, reescribe `data/opciones-personalizacion.json`. Esta es la pantalla que resuelve el pedido de "que ellos puedan editar las opciones de personalización".
- **`subir-imagen.php`:** input de tipo archivo que sube la foto a `/images/productos/` y valida tipo/tamaño de archivo antes de guardarla.
- **Seguridad mínima recomendada:**
  - Contraseña fuerte, cambiable desde `config.php` (o mejor, desde una pantalla de "cambiar contraseña" dentro del panel).
  - `.htaccess` en `/admin` como capa extra (aunque la sesión ya protege el acceso).
  - Servir el sitio con HTTPS (la mayoría de hosting con cPanel ofrece SSL gratis vía Let's Encrypt — hay que activarlo).
  - Validar y sanitizar todo lo que entra por los formularios antes de escribirlo en los JSON (evitar que un campo de texto rompa el formato JSON).
- **Por qué JSON y no una base de datos MySQL:** para el volumen de datos de esta floristería (un catálogo de decenas de productos, no miles), leer/escribir un archivo JSON desde PHP es más simple de mantener, no requiere configurar una base de datos en cPanel, y el sitio público sigue sirviendo el JSON de forma estática y rápida. Si el catálogo creciera mucho (cientos de productos, múltiples usuarios editando a la vez), ahí sí valdría la pena migrar a MySQL — no es el caso ahora.

---

## 8. Convenciones de código

- HTML semántico (`<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`), sin librerías externas salvo que sea estrictamente necesario.
- JavaScript vanilla ES6+ (funciones, `fetch`, módulos con `<script type="module">` si conviene separar responsabilidades entre archivos).
- Colores SIEMPRE como variables CSS definidas en `:root` dentro de `styles.css` (sección 3), nunca hardcodear hex sueltos dentro de otros archivos o inline styles.
- Mobile-first: la mayoría de los clientes probablemente entrarán desde el celular para luego escribir por WhatsApp.
- Nombrar archivos, IDs y clases CSS en inglés o kebab-case neutro (convención de código: `cart.js`, `.product-card`), pero todo el contenido/UI visible en **español** (Honduras).
- Formatear precios en **Lempiras (L.)**, con separador de miles.
- Evitar dependencia de CDNs externos cuando sea posible (fuentes de Google Fonts está bien, pero frameworks CSS/JS externos no, ya que se pidió "puro").
- Cada página HTML debe incluir metaetiquetas básicas de SEO (`title`, `description`) y Open Graph, ya que es un negocio local que se beneficia de aparecer bien en Google/redes.
- El sitio público (`.html`) y el panel (`/admin/*.php`) se mantienen claramente separados: el sitio público nunca escribe directamente en los JSON, solo los lee; toda escritura pasa por `/admin` con sesión verificada.
- En PHP: usar `json_encode`/`json_decode` con manejo de errores, escribir archivos con bloqueo (`flock`) para evitar corrupción si dos cambios ocurren casi al mismo tiempo, y siempre validar/sanitizar entradas de formularios antes de guardarlas.

---

## 9. Pendiente de definir con el cliente (Claudia)

- [x] Número de WhatsApp completo con código de país → **+504 8750-2362** (`50487502362`)
- [x] Opciones de personalización de ramo → resuelto: quedan precargadas con valores por defecto (sección 6.2) y son 100% editables desde el panel `/admin`
- [x] Dominio y proveedor de hosting → resuelto: el sitio es portable a cualquier hosting con PHP, se decide después sin afectar el desarrollo
- [x] Edición del catálogo sin programador → resuelto: se construye el panel `/admin` en PHP (sección 7.1)
- [ ] Catálogo real de productos: nombres, precios, fotos (mínimo 8-10 para poblar el sitio inicial) — mientras tanto se puede arrancar con productos de ejemplo/placeholder
- [ ] Contraseña definitiva del panel de administración (se genera una temporal para desarrollo y se cambia antes de publicar)
- [ ] Textos de la sección "Nosotros" (historia de la floristería) y fotos para esa página
- [ ] Confirmar si desean activar HTTPS/SSL gratis (Let's Encrypt) una vez elijan el hosting final — muy recomendado, sobre todo por el panel admin

---

## 10. Roadmap sugerido

1. **Fase 1 (MVP):** estructura de páginas públicas, catálogo estático desde JSON (con datos placeholder), carrito, personalizador con opciones precargadas, integración WhatsApp, página de contacto con mapa y redes.
2. **Fase 1.5 (parte del mismo alcance, no opcional):** panel `/admin` en PHP funcional — login, CRUD de productos, editor de opciones de personalización, subida de imágenes. Esto reemplaza la edición manual de los JSON.
3. **Fase 2:** pulir el panel admin si hace falta (ej. pantalla de "cambiar contraseña", reportes simples de qué se agregó al carrito más), y activar HTTPS en el hosting final.
4. **Fase 3:** feed real de Instagram, sistema de reseñas de clientes, notificaciones (ej. floristería recibe pedidos también por correo además de WhatsApp).