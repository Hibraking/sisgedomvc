<?php
/* =====================================================================
   SISGEDO MVC — Arranque de la aplicación
   ---------------------------------------------------------------------
   Lo carga public/index.php (el único punto de entrada web):
     1) cargador automático de clases (namespace App\ -> carpeta app/)
     2) entorno (local / producción), zona horaria y manejo de errores
     3) datos de la institución (config/institucion.php)
     4) sesión propia (SISGEDOMVC) y funciones de ayuda para las vistas
   ===================================================================== */

define('SISGEDO_RAIZ', dirname(__DIR__));

// 1) Cargador de clases: App\Core\Router -> app/Core/Router.php
spl_autoload_register(function ($clase) {
    if (strpos($clase, 'App\\') !== 0) {
        return;
    }
    $archivo = SISGEDO_RAIZ . '/app/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

// 2) Entorno (misma regla que sisgedov2)
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
define('SISGEDO_ES_LOCAL', strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || substr($host, -5) === '.test');
date_default_timezone_set('America/Lima');           // hora oficial del Perú
error_reporting(E_ALL);
ini_set('display_errors', SISGEDO_ES_LOCAL ? '1' : '0');
ini_set('log_errors', '1');
if (!SISGEDO_ES_LOCAL) {
    ini_set('error_log', SISGEDO_RAIZ . '/storage/logs/php-errors.log');
}

// 3) Institución (nombre, horario, feriados, imágenes...)
require SISGEDO_RAIZ . '/config/institucion.php';

// 4) Sesión propia
App\Core\Session::iniciar();

/* ---------------- Funciones de ayuda (vistas y controladores) ---------------- */

/** Escapa texto para mostrarlo en HTML (anti-XSS). */
function h($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/** Dirección de una ruta del sistema: url('recepcion') -> /sisgedomvc/recepcion */
function url($ruta = '')
{
    return App\Core\Url::a($ruta);
}

/** Dirección de un recurso estático con versión (evita caché vieja). */
function asset($ruta)
{
    return App\Core\Url::recurso($ruta);
}

/** Lee un valor de config/app.php */
function config($clave, $porDefecto = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require SISGEDO_RAIZ . '/config/app.php';
    }
    return array_key_exists($clave, $cfg) ? $cfg[$clave] : $porDefecto;
}
