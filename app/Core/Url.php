<?php
namespace App\Core;

/**
 * Direcciones del sistema. Funciona igual en http://localhost/sisgedomvc/,
 * en http://sisgedomvc.test/ o en una subcarpeta del hosting.
 */
class Url
{
    /** Ruta base pública, ej. "/sisgedomvc" (o "" si está en la raíz del dominio). */
    public static function base()
    {
        static $base = null;
        if ($base === null) {
            $script = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
            // El front controller es public/index.php; la carpeta "public" no se muestra.
            $base = preg_replace('~/(public/)?index\.php$~', '', $script);
            $base = rtrim($base, '/');
        }
        return $base;
    }

    public static function a($ruta = '')
    {
        $ruta = ltrim((string)$ruta, '/');
        return self::base() . '/' . $ruta;
    }

    public static function recurso($ruta)
    {
        $ruta = ltrim((string)$ruta, '/');
        $archivo = SISGEDO_RAIZ . '/public/' . $ruta;
        $v = is_file($archivo) ? '?v=' . filemtime($archivo) : '';
        return self::base() . '/' . str_replace('%2F', '/', rawurlencode($ruta)) . $v;
    }

    /** Ruta pedida, relativa a la base y sin parámetros: "recepcion", "" (inicio)... */
    public static function rutaActual()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $uri = rawurldecode(strtok($uri, '?'));
        $base = self::base();
        if ($base !== '' && stripos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = preg_replace('~^/?(public/)?(index\.php)?~', '', $uri);
        return trim($uri, '/');
    }
}
