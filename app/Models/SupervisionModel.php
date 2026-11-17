<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/** Supervisión de oficinas (solo administrador) — mismas consultas de sisgedov2/vistas/v_supervision.php. */
class SupervisionModel
{
    /** Indicadores por oficina, ordenados por pendientes (mayor primero). */
    public static function oficinas()
    {
        $ofis = array();
        foreach (Database::filas("SELECT idoficinas, nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-')", array(), PDO::FETCH_NUM) as $o) {
            $ofis[(int)$o[0]] = array('id' => (int)$o[0], 'nombre' => $o[1], 'ext' => 0, 'int' => 0, 'der' => 0, 'viejos' => 0,
                'antiguo' => null, 'hoy' => 0, 'mes' => 0);
        }
        $sumar = function ($sql, $campo, $conFecha = false) use (&$ofis) {
            try {
                foreach (Database::filas($sql, array(), PDO::FETCH_NUM) as $r) {
                    $id = (int)$r[0];
                    if (!isset($ofis[$id])) continue;
                    $ofis[$id][$campo] += (int)$r[1];
                    if ($conFecha) {
                        $ofis[$id]['viejos'] += (int)$r[3];
                        if ($r[2] !== null && ($ofis[$id]['antiguo'] === null || $r[2] < $ofis[$id]['antiguo'])) {
                            $ofis[$id]['antiguo'] = $r[2];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log('supervision: ' . $e->getMessage());
            }
        };
        // Por recepcionar: externos (registro), internos (emitidos) y derivaciones
        $sumar("SELECT idoficina, COUNT(*), MIN(fecha_registro), SUM(fecha_registro < NOW() - INTERVAL 30 DAY)
                  FROM registro_documentos WHERE estado_recepcion = 0 AND idoficina IS NOT NULL GROUP BY idoficina", 'ext', true);
        $sumar("SELECT idoficina, COUNT(*), MIN(fecha), SUM(fecha < NOW() - INTERVAL 30 DAY)
                  FROM docs_emitidos WHERE estado = 0 AND idoficina IS NOT NULL GROUP BY idoficina", 'int', true);
        $sumar("SELECT idoficina_derivar, COUNT(*), MIN(fecha_hora), SUM(fecha_hora < NOW() - INTERVAL 30 DAY)
                  FROM movimientos WHERE estado = 0 AND idmovimientoant IS NOT NULL GROUP BY idoficina_derivar", 'der', true);
        // Recibidos hoy (recepción directa o derivación ya recibida) y derivaciones del mes
        $sumar("SELECT idoficina_derivar, COUNT(*) FROM movimientos
                 WHERE (idmovimientoant IS NULL OR estado = 1)
                   AND fecha_hora >= CURDATE() AND fecha_hora < CURDATE() + INTERVAL 1 DAY GROUP BY idoficina_derivar", 'hoy');
        $sumar("SELECT r.idoficina, COUNT(*) FROM movimientos m
                  JOIN movimientos ma ON m.idmovimientoant = ma.idMovimientos
                  JOIN responsables r ON ma.idresponsable = r.idresponsables
                 WHERE m.fecha_hora >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY r.idoficina", 'mes');
        foreach ($ofis as &$o) {
            $o['pend'] = $o['ext'] + $o['int'] + $o['der'];
        }
        unset($o);
        usort($ofis, function ($a, $b) {
            return $b['pend'] - $a['pend'] ?: strcmp($a['nombre'], $b['nombre']);
        });
        return $ofis;
    }

    /** Mesa de Partes Virtual (global): por atender y observados vencidos. */
    public static function mpv()
    {
        $mpv = array('pend' => 0, 'vencidos' => 0);
        if (MesaPartesModel::moduloActivo()) {
            $mpv['pend'] = (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_recepcion IS NULL AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO')")
                + (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND idoficina IS NULL AND mpv_idpersonal IS NOT NULL AND mpv_recepcion IS NOT NULL AND COALESCE(estado_recepcion,0) = 0");
            $mpv['vencidos'] = (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_estado = 'OBSERVADO' AND mpv_plazo < CURDATE()");
        }
        return $mpv;
    }

    /** Oficinas sin un responsable activo (lo que se les deriva no lo recibe nadie). */
    public static function sinResponsable()
    {
        try {
            return Database::filas("SELECT o.idoficinas, o.nombre FROM oficinas o
                                     WHERE TRIM(COALESCE(o.nombre,'')) NOT IN ('','-')
                                       AND NOT EXISTS (SELECT 1 FROM responsables r WHERE r.idoficina = o.idoficinas AND r.estado = 1 AND r.idpersonal <> 1)
                                     ORDER BY o.nombre", array(), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('supervision sinResponsable: ' . $e->getMessage());
            return array();
        }
    }

    /** Personas con más de un cargo activo: [[nombre, "Oficina A · Oficina B"], ...] */
    public static function variosCargos()
    {
        try {
            return Database::filas("SELECT TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),
                                           GROUP_CONCAT(o.nombre ORDER BY o.nombre SEPARATOR ' · ')
                                      FROM responsables r JOIN personal p ON p.idpersonal = r.idpersonal
                                      JOIN oficinas o ON o.idoficinas = r.idoficina
                                     WHERE r.estado = 1 AND r.idpersonal <> 1
                                     GROUP BY r.idpersonal HAVING COUNT(*) > 1", array(), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('supervision variosCargos: ' . $e->getMessage());
            return array();
        }
    }
}
