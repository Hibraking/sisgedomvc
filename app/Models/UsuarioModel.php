<?php
namespace App\Models;

use App\Core\Database;

/** Personal que inicia sesión (tablas personal y responsables). */
class UsuarioModel
{
    /**
     * Usuario y su cargo. Si tiene varios cargos se usa el ACTIVO
     * (entre varios activos, el más antiguo), igual que sisgedov2.
     */
    public static function buscarParaLogin($usuario)
    {
        return Database::fila("SELECT p.idpersonal, p.usuario, p.clave, r.idoficina, r.idresponsables
                                 FROM personal p
                                 LEFT JOIN responsables r ON p.idpersonal = r.idpersonal
                                WHERE p.usuario = ?
                                ORDER BY (r.estado = 1) DESC, r.idresponsables ASC
                                LIMIT 1", array($usuario));
    }

    /** Guarda la contraseña cifrada (bcrypt). */
    public static function guardarClaveCifrada($idpersonal, $hash)
    {
        Database::consulta("UPDATE personal SET clave = ? WHERE idpersonal = ?", array($hash, (int)$idpersonal));
    }

    /** Devuelve el último acceso anterior y registra el actual. */
    public static function registrarAcceso($idpersonal)
    {
        try {
            $anterior = Database::valor("SELECT ultimo_acceso FROM personal WHERE idpersonal = ?", array((int)$idpersonal));
            Database::consulta("UPDATE personal SET ultimo_acceso = NOW() WHERE idpersonal = ?", array((int)$idpersonal));
            return $anterior;
        } catch (\Exception $e) {
            error_log('ultimo_acceso: ' . $e->getMessage());   // columna aún no creada: no afecta el ingreso
            return null;
        }
    }

    /** Nombre del archivo de la foto de perfil ('' si no tiene). */
    public static function foto($idpersonal)
    {
        try {
            return (string)Database::valor("SELECT COALESCE(imagen,'') FROM personal WHERE idpersonal = ?", array((int)$idpersonal));
        } catch (\Exception $e) {
            return '';
        }
    }
}
