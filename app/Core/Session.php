<?php
namespace App\Core;

/**
 * Sesión del sistema. Usa un nombre propio (SISGEDOMVC) para no mezclarse con
 * la sesión de sisgedov2. Las claves son las mismas que usa sisgedov2
 * (usuario, alias, idoficina, idresponsable...) para reutilizar su lógica.
 */
class Session
{
    public static function iniciar()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        session_name(config('sesion_nombre', 'SISGEDOMVC'));
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        ));
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function get($clave, $porDefecto = null)
    {
        return isset($_SESSION[$clave]) ? $_SESSION[$clave] : $porDefecto;
    }

    public static function set($clave, $valor)
    {
        $_SESSION[$clave] = $valor;
    }

    public static function quitar($clave)
    {
        unset($_SESSION[$clave]);
    }

    /** Cierra la sesión por completo (datos y cookie). */
    public static function destruir()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Mensaje de una sola vez (se muestra en la siguiente pantalla). */
    public static function flash($clave, $valor = null)
    {
        if ($valor !== null) {
            $_SESSION['_flash'][$clave] = $valor;
            return null;
        }
        $v = isset($_SESSION['_flash'][$clave]) ? $_SESSION['_flash'][$clave] : null;
        unset($_SESSION['_flash'][$clave]);
        return $v;
    }
}
