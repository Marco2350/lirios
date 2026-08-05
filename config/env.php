<?php
/**
 * Lector mínimo de archivos .env (sin dependencias/Composer).
 * Lee /.env una sola vez por request y expone env('CLAVE', valorPorDefecto).
 * Si no existe .env (ej. entorno local recién clonado), env() simplemente
 * devuelve el valor por defecto que le pase cada llamada.
 */

function cargar_env(string $ruta): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    if (!is_readable($ruta)) {
        return $cache;
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);
        $envuelta = strlen($valor) >= 2 && (
            ($valor[0] === '"' && str_ends_with($valor, '"'))
            || ($valor[0] === "'" && str_ends_with($valor, "'"))
        );
        if ($envuelta) {
            $valor = substr($valor, 1, -1);
        }
        $cache[$clave] = $valor;
    }
    return $cache;
}

function env(string $clave, ?string $porDefecto = null): ?string
{
    $valores = cargar_env(dirname(__DIR__) . '/.env');
    return $valores[$clave] ?? $porDefecto;
}
