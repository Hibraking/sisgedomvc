<?php
namespace App\Core;

use App\Models\AccesoModel;

/**
 * Usuario en sesión y permisos.
 * Permisos: los MISMOS de sisgedov2 (tabla accesos_usuario + menu.direccion).
 *  - El administrador (usuario 1) puede todo.
 *  - Una dirección que no es ítem de menú no está restringida.
 *  - Ante un error de base de datos no se bloquea (igual que sisgedov2).
 */
class Auth
{
    private static $permitidos = false;   // false = sin cargar; null = todos

    public static function id()
    {
        return (int)Session::get('usuario', 0);
    }

    public static function logueado()
    {
        return self::id() > 0;
    }

    public static function esAdmin()
    {
        return self::id() === 1;
    }

    public static function alias()
    {
        return (string)Session::get('alias', 'usuario');
    }

    public static function idOficina()
    {
        return (int)Session::get('idoficina', 0);
    }

    public static function idResponsable()
    {
        return (int)Session::get('idresponsable', 0);
    }

    /** ¿Puede usar la opción de menú identificada por $permiso (ej. "vistas/v_oficinas.php")? */
    public static function puede($permiso)
    {
        if (!self::logueado()) {
            return false;
        }
        if (self::esAdmin() || $permiso === null || $permiso === '') {
            return true;
        }
        if (self::$permitidos === false) {
            self::$permitidos = AccesoModel::direccionesPermitidas(self::id());
        }
        if (self::$permitidos === null) {
            return true;
        }
        if (in_array($permiso, self::$permitidos, true)) {
            return true;
        }
        return !AccesoModel::esItemDeMenu($permiso);
    }
}
