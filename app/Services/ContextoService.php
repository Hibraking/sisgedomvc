<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Cargo y oficina con los que actúa el usuario en sesión — misma regla que
 * sisgedo_contexto_usuario_actual() de sisgedov2: el cargo de la sesión,
 * luego el ACTIVO, luego el más antiguo. El actor SIEMPRE sale de la sesión,
 * nunca de lo que envía el navegador.
 */
class ContextoService
{
    /** ['idpersonal' => int, 'idresponsable' => int, 'idoficina' => int] */
    public static function actual()
    {
        static $ctx = null;
        if ($ctx !== null) return $ctx;
        $ctx = array('idpersonal' => Auth::id(), 'idresponsable' => Auth::idResponsable(), 'idoficina' => Auth::idOficina());
        if ($ctx['idpersonal'] <= 0) return $ctx;
        try {
            $f = Database::fila("SELECT r.idresponsables, r.idoficina
                                   FROM personal p INNER JOIN responsables r ON p.idpersonal = r.idpersonal
                                  WHERE p.idpersonal = ?
                                  ORDER BY (r.idresponsables = ?) DESC, (r.estado = 1) DESC, r.idresponsables ASC
                                  LIMIT 1", array($ctx['idpersonal'], $ctx['idresponsable']));
            if ($f) {
                if (!empty($f['idresponsables'])) $ctx['idresponsable'] = (int)$f['idresponsables'];
                if (!empty($f['idoficina'])) $ctx['idoficina'] = (int)$f['idoficina'];
            }
        } catch (\Exception $e) {
            error_log('ContextoService: ' . $e->getMessage());
        }
        return $ctx;
    }
}
