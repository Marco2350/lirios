# CLAUDE.md — admin/ (Lirios Floristería)

Documentación del panel de administración. Migrado desde el `CLAUDE.md` raíz (antes "### 7.1 Panel de administración") — solo es relevante cuando se trabaja dentro de `/admin`.

## Panel de administración (`/admin`)

Objetivo: que Claudia (o cualquier persona del negocio sin conocimientos técnicos) pueda **agregar/editar/eliminar productos del catálogo** (incluyendo tallas y precios) y **organizar la taxonomía de categorías/subcategorías** desde el navegador, sin tocar código ni SQL a mano.

> **Nota (2026-08-22):** el panel tuvo una pantalla `personalizacion.php` para editar las opciones del constructor de "ramo personalizado" (tablas `pers_*`). Se eliminó junto con todo el feature de personalización a pedido de la clienta — ver la nota en el `CLAUDE.md` raíz, sección 1.

- **Login:** una sola contraseña de administrador (no se necesita sistema de usuarios múltiples para este tamaño de negocio). Contraseña guardada **hasheada** (`password_hash` de PHP) en `includes/config.php`, nunca en texto plano.
- **Sesión:** PHP `session_start()` — todas las páginas dentro de `/admin` (excepto `index.php`) verifican con `includes/auth.php` que haya sesión activa; si no, redirigen al login.
- **`productos.php`:** tabla con el catálogo actual (nombre, categoría/subcategoría, tallas y precios, imagen, disponible sí/no), botones para editar o eliminar cada fila, y un formulario para agregar un producto nuevo — incluyendo hasta 4 variantes de talla (S/M/L/XL) con su propio precio, el campo "incluye" y los checkboxes de entrega/retiro. Al guardar, escribe en las tablas `productos` y `producto_variantes` (MySQL).
- **`categorias.php`:** CRUD de categorías y subcategorías (nombre, ícono, orden). Antes de eliminar una categoría o subcategoría, el sistema verifica que no tenga productos asociados (restricción de llave foránea).
- **`subir-imagen.php`:** input de tipo archivo que sube la foto a `/images/productos/` y valida tipo/tamaño de archivo antes de guardarla.
- **Seguridad mínima recomendada:**
  - Contraseña fuerte, cambiable desde una pantalla de "cambiar contraseña" dentro del propio panel (`/admin/cambiar-password.php`, resuelto 2026-07-26) — ya no hace falta editar código para cambiarla.
  - `.htaccess` en `/admin` como capa extra (aunque la sesión ya protege el acceso).
  - Servir el sitio con HTTPS (la mayoría de hosting con cPanel ofrece SSL gratis vía Let's Encrypt — hay que activarlo).
  - Validar y sanitizar todo lo que entra por los formularios antes de escribirlo en la base de datos (consultas siempre parametrizadas con PDO, nunca concatenadas).
- **Por qué MySQL y no JSON plano:** con 12 categorías, ~80 subcategorías y tallas con precio propio por producto, el catálogo dejó de ser "un puñado de productos" para ser un modelo relacional real — normalizarlo en MySQL evita duplicar nombres de categoría en cada producto, permite validar relaciones con llaves foráneas (ej. no puedes borrar una subcategoría con productos activos) y hace que los reportes/filtros por categoría sean consultas SQL directas en vez de recorrer arreglos en JS. Prácticamente todo hosting compartido con cPanel incluye MySQL/MariaDB sin costo adicional, así que la portabilidad no se ve afectada.
