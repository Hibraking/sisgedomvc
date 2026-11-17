<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/** Consultas de las notificaciones en vivo (mismas de sisgedov2/clases/notificaciones.php). */
class NotificacionModel
{
    /** "conteo.suma" de cada fuente de pendientes: cambia cuando llega o sale un documento. */
    public static function firmaRecepcion($idoficina)
    {
        return Database::consulta("SELECT
              (SELECT CONCAT(COUNT(*), '.', COALESCE(SUM(idMovimientos),0)) FROM movimientos
                WHERE estado = 0 AND idoficina_derivar = :o1 AND idmovimientoant IS NOT NULL),
              (SELECT CONCAT(COUNT(*), '.', COALESCE(SUM(idregistro_documentos),0)) FROM registro_documentos
                WHERE estado_recepcion = 0 AND idoficina = :o2),
              (SELECT CONCAT(COUNT(*), '.', COALESCE(SUM(iddocs_emitidos),0)) FROM docs_emitidos
                WHERE idoficina = :o3 AND estado = 0)", array(':o1' => $idoficina, ':o2' => $idoficina, ':o3' => $idoficina))
            ->fetch(PDO::FETCH_NUM);
    }

    /** Los 5 documentos por recepcionar que llegaron más recientemente. */
    public static function recientes($idoficina)
    {
        $llegadaMpv = MesaPartesModel::moduloActivo()
            ? "IF(rd.recepcion_web = 1,
                  COALESCE((SELECT MAX(e.fecha) FROM sgd_mpv_evento e
                             WHERE e.idregistro_documentos = rd.idregistro_documentos AND e.tipo = 'DERIVADO'),
                           rd.mpv_recepcion, rd.fecha_registro),
                  rd.fecha_registro)"
            : "rd.fecha_registro";
        $sql = "(SELECT CONCAT('D', m.idMovimientos) AS clave, m.fecha_hora AS llegada,
                        COALESCE(rd.nro_exp, de.iddocs_emitidos) AS exp,
                        TRIM(CONCAT(COALESCE(d1.nombre_documento, d2.nombre_documento, ''), ' ', COALESCE(rd.numero_docu, de.numero, ''))) AS doc,
                        COALESCE(rd.asunto, de.asunto, '') AS asunto,
                        COALESCE(oant.nombre, '') AS origen,
                        COALESCE(ac.nombre_accion, '') AS accion
                   FROM movimientos m
                   LEFT JOIN movimientos mant ON mant.idMovimientos = m.idmovimientoant
                   LEFT JOIN oficinas oant ON oant.idoficinas = mant.idoficina_derivar
                   LEFT JOIN aciones_documento ac ON ac.idaciones_documento = m.idacciondocs
                   LEFT JOIN registro_documentos rd ON rd.idregistro_documentos = m.idregdocs
                   LEFT JOIN documentos d1 ON d1.iddocumentos = rd.iddocumento
                   LEFT JOIN docs_emitidos de ON de.iddocs_emitidos = m.iddocs_emitidos
                   LEFT JOIN documentos d2 ON d2.iddocumentos = de.iddocumento
                  WHERE m.estado = 0 AND m.idoficina_derivar = :o1 AND m.idmovimientoant IS NOT NULL
                  ORDER BY m.fecha_hora DESC LIMIT 5)
                UNION ALL
                (SELECT CONCAT('R', x.idregistro_documentos), x.llegada, x.nro_exp, x.doc, x.asunto, x.origen, ''
                   FROM (SELECT rd.idregistro_documentos, rd.nro_exp, COALESCE(rd.asunto, '') AS asunto,
                                TRIM(CONCAT(COALESCE(d.nombre_documento, ''), ' ', COALESCE(rd.numero_docu, ''))) AS doc,
                                IF(rd.recepcion_web = 1, 'Mesa de Partes Virtual', 'Mesa de Partes') AS origen,
                                $llegadaMpv AS llegada
                           FROM registro_documentos rd
                           LEFT JOIN documentos d ON d.iddocumentos = rd.iddocumento
                          WHERE rd.estado_recepcion = 0 AND rd.idoficina = :o2) x
                  ORDER BY x.llegada DESC LIMIT 5)
                UNION ALL
                (SELECT CONCAT('E', de.iddocs_emitidos), de.fecha, de.iddocs_emitidos,
                        TRIM(CONCAT(COALESCE(d.nombre_documento, ''), ' ', COALESCE(de.numero, ''))),
                        COALESCE(de.asunto, ''), COALESCE(oe.nombre, ''), ''
                   FROM docs_emitidos de
                   LEFT JOIN documentos d ON d.iddocumentos = de.iddocumento
                   LEFT JOIN oficinas oe ON oe.idoficinas = de.idoficina_registro
                  WHERE de.idoficina = :o3 AND de.estado = 0
                  ORDER BY de.fecha DESC LIMIT 5)
                ORDER BY llegada DESC LIMIT 5";
        $out = array();
        foreach (Database::filas($sql, array(':o1' => $idoficina, ':o2' => $idoficina, ':o3' => $idoficina), PDO::FETCH_NUM) as $r) {
            $out[] = array('clave' => $r[0], 'llegada' => (string)$r[1], 'exp' => (string)$r[2],
                'doc' => (string)$r[3], 'asunto' => (string)$r[4], 'origen' => (string)$r[5], 'accion' => (string)$r[6]);
        }
        return $out;
    }

    /** Último documento llegado por la Mesa de Partes Virtual. */
    public static function ultimoMpv()
    {
        $u = Database::filas("SELECT rd.idregistro_documentos, rd.nro_exp,
                                     TRIM(CONCAT(COALESCE(r.nombres,''),' ',COALESCE(r.apellidos,''))), COALESCE(rd.asunto,'')
                                FROM registro_documentos rd LEFT JOIN remitente r ON r.idremitente = rd.idremitente
                               WHERE rd.recepcion_web = 1 ORDER BY rd.idregistro_documentos DESC LIMIT 1", array(), PDO::FETCH_NUM);
        return $u ? array('id' => (int)$u[0][0], 'exp' => (string)$u[0][1], 'remitente' => (string)$u[0][2], 'asunto' => (string)$u[0][3]) : null;
    }
}
