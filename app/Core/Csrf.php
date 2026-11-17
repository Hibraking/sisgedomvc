<?php
namespace App\Core;

/** Protección CSRF (mismo esquema que sisgedov2: campo _csrf o cabecera X-CSRF-Token). */
class Csrf
{
    public static function token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** <input type="hidden"> para formularios. */
    public static function campo()
    {
        return '<input type="hidden" name="_csrf" value="' . h(self::token()) . '" />';
    }

    public static function valido()
    {
        $enviado = !empty($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN']
            : (isset($_POST['_csrf']) ? $_POST['_csrf'] : null);
        return !empty($_SESSION['csrf_token']) && is_string($enviado) && hash_equals($_SESSION['csrf_token'], $enviado);
    }

    public static function exigir()
    {
        if (!self::valido()) {
            http_response_code(403);
            exit('ERROR_CSRF: token de seguridad inválido o ausente. Recargue la página e intente de nuevo.');
        }
    }
}
