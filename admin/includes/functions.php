<?php
/**
 * Utilidades del panel: lectura/escritura segura de JSON
 * (con bloqueo de archivo) y helpers de saneamiento.
 */

/** Lee un archivo JSON y lo devuelve como arreglo asociativo. */
function leer_json(string $ruta): array
{
    if (!is_file($ruta)) {
        return [];
    }
    $contenido = file_get_contents($ruta);
    $datos = json_decode($contenido, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        return [];
    }
    return $datos;
}

/**
 * Escribe un arreglo como JSON con bloqueo exclusivo (flock)
 * para evitar corrupción si dos cambios ocurren a la vez.
 */
function guardar_json(string $ruta, array $datos): bool
{
    $json = json_encode(
        $datos,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        return false;
    }
    return file_put_contents($ruta, $json, LOCK_EX) !== false;
}

/** Escapa texto para mostrarlo en HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Convierte un nombre en un slug apto para id ("Ramo Alba Rosa" → "ramo-alba-rosa"). */
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

/** Genera un id único dentro de una lista de items con clave 'id'. */
function id_unico(string $base, array $items): string
{
    $ids = array_column($items, 'id');
    $id = $base;
    $n = 2;
    while (in_array($id, $ids, true)) {
        $id = $base . '-' . $n;
        $n++;
    }
    return $id;
}
