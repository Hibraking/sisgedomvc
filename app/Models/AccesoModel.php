<?php
namespace App\Models;

use App\Core\Database;

/** Permisos de menú (tablas menu y accesos_usuario de sisgedov2). */
class AccesoModel
{
    /**
     * Direcciones de menú permitidas al usuario; null si no se pudo consultar
     * (en ese caso no se bloquea, igual que sisgedov2).
     */
    public static function direccionesPermitidas($idusuario)
    {
        try {
            $filas = Database::filas("SELECT m.direccion FROM accesos_usuario a
                                        JOIN menu m ON a.idmenu = m.idmenu
                                       WHERE a.idusuario = ? AND a.access = 1", array((int)$idusuario), \PDO::FETCH_COLUMN);
            return array_map('trim', array_map('strval', $filas));
        } catch (\Exception $e) {
            error_log('AccesoModel: ' . $e->getMessage());
            return null;
        }
    }

    /** ¿La dirección corresponde a un ítem de menú? (si no, no está restringida) */
    public static function esItemDeMenu($direccion)
    {
        try {
            return Database::valor("SELECT COUNT(*) FROM menu WHERE direccion = ?", array($direccion)) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
