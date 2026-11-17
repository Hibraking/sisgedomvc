<?php
namespace App\Core;

/**
 * Responder primero, trabajar después (igual que sisgedov2/clases/util_respuesta.php).
 * Para tareas lentas que el usuario no necesita esperar (correos):
 *     Respuesta::iniciar();  echo 'OK';  Respuesta::terminar();  enviarCorreos();
 */
class Respuesta
{
    public static function iniciar()
    {
        ob_start();
    }

    /** Entrega lo escrito hasta ahora y cierra la conexión; el script sigue en el servidor. */
    public static function terminar()
    {
        ignore_user_abort(true);
        @set_time_limit(180);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            while (ob_get_level() > 0) ob_end_flush();
            fastcgi_finish_request();
            return;
        }
        if (function_exists('litespeed_finish_request')) {
            while (ob_get_level() > 0) ob_end_flush();
            litespeed_finish_request();
            return;
        }
        $contenido = '';
        while (ob_get_level() > 0) $contenido = ob_get_clean() . $contenido;
        if (!headers_sent()) {
            header('Content-Encoding: none');
            header('Content-Length: ' . strlen($contenido));
            header('Connection: close');
        }
        echo $contenido;
        @ob_flush();
        flush();
    }
}
