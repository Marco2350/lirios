<?php
/**
 * Utilidades del panel: saneamiento de formularios y helpers
 * para trabajar con la base de datos MySQL (ver config/db.php).
 */

/** Escapa texto para mostrarlo en HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Convierte un nombre en un slug apto para URL ("Ramo Alba Rosa" → "ramo-alba-rosa"). */
function slugify(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
    ]);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-') ?: 'item-' . time();
}

/** Limpia un texto de formulario: sin etiquetas y con espacios normalizados. */
function limpiar_texto(?string $texto, int $max = 500): string
{
    $texto = strip_tags(trim((string) $texto));
    $texto = preg_replace('/\s+/', ' ', $texto);
    return mb_substr($texto, 0, $max, 'UTF-8');
}

/** Convierte una entrada de formulario en precio válido (>= 0). */
function limpiar_precio($valor): float
{
    $numero = (float) str_replace(',', '', (string) $valor);
    return max(0, round($numero, 2));
}

/**
 * Genera un slug único dentro de una tabla, agregando "-2", "-3"...
 * si ya existe (opcionalmente ignorando la propia fila al editar).
 */
function slug_unico(string $tabla, string $slugBase, ?int $excluirId = null): string
{
    $slug = $slugBase;
    $n = 2;
    $sql = "SELECT COUNT(*) FROM {$tabla} WHERE slug = :slug" . ($excluirId ? ' AND id != :id' : '');
    $stmt = db()->prepare($sql);
    while (true) {
        $params = ['slug' => $slug];
        if ($excluirId) {
            $params['id'] = $excluirId;
        }
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $slugBase . '-' . $n;
        $n++;
    }
}

/* ---------- Subida de fotos de producto (compartida por productos.php y subir-imagen.php) ---------- */

const IMAGEN_MAX_BYTES = 3 * 1024 * 1024; // 3 MB
const IMAGEN_EXTENSIONES_VALIDAS = ['jpg', 'jpeg', 'png', 'webp'];
const IMAGEN_MIMES_VALIDOS = ['image/jpeg', 'image/png', 'image/webp'];

/** Carga una imagen (jpg/png/webp) como recurso GD según su mime real. */
function cargar_imagen_gd(string $ruta, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($ruta),
        'image/png' => @imagecreatefrompng($ruta),
        'image/webp' => @imagecreatefromwebp($ruta),
        default => false,
    };
}

/** Genera un nombre de archivo único dentro de IMAGENES_DIR para $base.$ext. */
function nombre_imagen_unico(string $base, string $ext): array
{
    $nombre = $base . '.' . $ext;
    $n = 2;
    while (file_exists(IMAGENES_DIR . '/' . $nombre)) {
        $nombre = $base . '-' . $n . '.' . $ext;
        $n++;
    }
    return [$nombre, IMAGENES_DIR . '/' . $nombre];
}

/**
 * Valida y guarda una foto subida ($_FILES[...]) en /images/productos,
 * convirtiéndola a WebP automáticamente si el servidor tiene GD con
 * soporte WebP; si no, la guarda tal cual se subió.
 * Devuelve ['ok' => bool, 'nombre' => string|null, 'error' => string|null].
 */
function guardar_imagen_subida(array $archivo): array
{
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'nombre' => null, 'error' => 'La subida falló. Verifica que el archivo no pase de 3 MB e intenta de nuevo.'];
    }
    if ($archivo['size'] > IMAGEN_MAX_BYTES) {
        return ['ok' => false, 'nombre' => null, 'error' => 'La foto pesa más de 3 MB. Comprímela antes de subirla (ej. tinypng.com o squoosh.app).'];
    }

    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($archivo['tmp_name']);
    if (!in_array($ext, IMAGEN_EXTENSIONES_VALIDAS, true) || !in_array($mime, IMAGEN_MIMES_VALIDOS, true)) {
        return ['ok' => false, 'nombre' => null, 'error' => 'Formato no permitido. Sube imágenes JPG, PNG o WebP.'];
    }

    if (!is_dir(IMAGENES_DIR)) {
        mkdir(IMAGENES_DIR, 0755, true);
    }

    $base = slugify(pathinfo($archivo['name'], PATHINFO_FILENAME));
    $convertirAWebp = $mime !== 'image/webp' && function_exists('imagewebp');
    $extFinal = $convertirAWebp ? 'webp' : $ext;
    [$nombre, $destino] = nombre_imagen_unico($base, $extFinal);

    $guardada = false;
    if ($convertirAWebp) {
        $imagen = cargar_imagen_gd($archivo['tmp_name'], $mime);
        if ($imagen !== false) {
            imagepalettetotruecolor($imagen);
            imagealphablending($imagen, true);
            imagesavealpha($imagen, true);
            $guardada = imagewebp($imagen, $destino, 82);
            imagedestroy($imagen);
        }
    }
    if (!$guardada) {
        // GD no disponible, formato no soportado, o ya era WebP: se guarda tal cual.
        $extFinal = $ext;
        [$nombre, $destino] = nombre_imagen_unico($base, $extFinal);
        $guardada = move_uploaded_file($archivo['tmp_name'], $destino);
    }

    if (!$guardada) {
        error_log('[lirios] No se pudo guardar la imagen en ' . IMAGENES_DIR . ' — revisar permisos de la carpeta.');
        return ['ok' => false, 'nombre' => null, 'error' => 'No se pudo guardar la foto en el servidor. Contacta a soporte técnico si el problema continúa.'];
    }

    return ['ok' => true, 'nombre' => $nombre, 'error' => null];
}
