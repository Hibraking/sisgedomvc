<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/** Consultas públicas: seguimiento de expediente y verificación por CVD (mismas de sisgedov2). */
class ConsultaModel
{
    /** Registros del expediente: [[documento, número, remitente, trámite, asunto, fecha, id, recepcion_web], ...] */
    public static function cabecera($nroExp)
    {
        return Database::filas("select documentos.nombre_documento, COALESCE(rd.numero_docu,''),
                                       concat(remitente.apellidos, ' ', remitente.nombres),
                                       tipo_tram.nombre, rd.asunto,
                                       date_format(rd.fecha_registro, '%d/%m/%Y %h:%i %p'),
                                       rd.idregistro_documentos, COALESCE(rd.recepcion_web, 0)
                                  from registro_documentos rd
                                 inner join documentos on rd.iddocumento=documentos.iddocumentos
                                 inner join remitente on rd.idremitente=remitente.idremitente
                                 inner join tipo_tram on rd.idtipo_tramite=tipo_tram.idtipo_tram
                                 where rd.nro_exp=? order by rd.idregistro_documentos asc", array($nroExp), PDO::FETCH_NUM);
    }

    /** [mpv_recepcion, idoficina, mpv_estado, mpv_plazo, fecha_registro] */
    public static function estadoMpv($idreg)
    {
        $r = Database::filas("select rd.mpv_recepcion, rd.idoficina, rd.mpv_estado, rd.mpv_plazo, rd.fecha_registro
                                from registro_documentos rd where rd.idregistro_documentos=?", array((int)$idreg), PDO::FETCH_NUM);
        return $r ? $r[0] : null;
    }

    /** [[tipo, detalle, plazo, fecha], ...] */
    public static function eventosMpv($idreg)
    {
        return Database::filas("select tipo, coalesce(detalle,''), plazo, fecha from sgd_mpv_evento
                                 where idregistro_documentos=? order by fecha asc, idevento asc", array((int)$idreg), PDO::FETCH_NUM);
    }

    /** Recorrido: [[fecha, hora, oficina, acción, estado, es_pendiente], ...] */
    public static function recorrido(array $ids)
    {
        if (!$ids) return array();
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        return Database::filas("select date_format(m.fecha_hora, '%d/%m/%Y'), date_format(m.fecha_hora, '%h:%i %p'),
                                       coalesce(ofi.nombre, ofid.nombre, ''), coalesce(acc.nombre_accion, '--'),
                                       coalesce(ed.nombre, '--'), case when m.estado = 0 then 1 else 0 end
                                  from movimientos m
                                  left join responsables r on m.idresponsable=r.idresponsables
                                  left join oficinas ofi on r.idoficina=ofi.idoficinas
                                  left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                  left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                  left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                                 where m.idregdocs in ($marcas)
                                 order by m.fecha_hora asc, m.idMovimientos asc", array_map('intval', $ids), PDO::FETCH_NUM);
    }

    /* ---------------- Verificación (CVD) ---------------- */

    public static function referenciaCvd($cvd)
    {
        return Database::fila("SELECT tipo, referencia FROM sgd_verificacion WHERE cvd = ?", array($cvd));
    }

    /** [documento, número, nro_exp, fecha, asunto, folios, recepcion_web, trámite, adjunto] */
    public static function expedienteVerificado($nroExp)
    {
        $r = Database::filas("SELECT d.nombre_documento, COALESCE(rd.numero_docu,''), rd.nro_exp,
                                     date_format(rd.fecha_registro, '%d/%m/%Y %h:%i %p'), rd.asunto, rd.folios,
                                     COALESCE(rd.recepcion_web,0), COALESCE(tt.nombre,''), rd.adjunto
                                FROM registro_documentos rd
                                LEFT JOIN documentos d ON rd.iddocumento = d.iddocumentos
                                LEFT JOIN tipo_tram tt ON rd.idtipo_tramite = tt.idtipo_tram
                               WHERE rd.nro_exp = ? ORDER BY rd.idregistro_documentos ASC LIMIT 1", array($nroExp), PDO::FETCH_NUM);
        return $r ? $r[0] : null;
    }

    /** [documento, número, id, fecha, asunto, oficina emisora, adjunto, destino] */
    public static function emitidoVerificado($id)
    {
        $r = Database::filas("SELECT d.nombre_documento, COALESCE(de.numero,''), de.iddocs_emitidos,
                                     date_format(de.fecha, '%d/%m/%Y %h:%i %p'), de.asunto,
                                     COALESCE(o.nombre,''), de.adjunto, COALESCE(od.nombre,'')
                                FROM docs_emitidos de
                                LEFT JOIN documentos d ON de.iddocumento = d.iddocumentos
                                LEFT JOIN oficinas o ON de.idoficina_registro = o.idoficinas
                                LEFT JOIN oficinas od ON de.idoficina = od.idoficinas
                               WHERE de.iddocs_emitidos = ?", array((int)$id), PDO::FETCH_NUM);
        return $r ? $r[0] : null;
    }

    /** Firmas digitales (FIRMA PERÚ) del mismo archivo: [[tipo, fecha, quién], ...] */
    public static function firmas($adjunto)
    {
        return Database::filas("SELECT f.tipo_firma, date_format(f.fecha_firma, '%d/%m/%Y %h:%i %p'),
                                       TRIM(CONCAT(COALESCE(p.nombres,''), ' ', COALESCE(p.apellidos,'')))
                                  FROM sgd_firma f
                                 INNER JOIN docs_emitidos g ON g.iddocs_emitidos = f.iddocs_emitidos
                                  LEFT JOIN personal p ON p.idpersonal = f.idpersonal
                                 WHERE f.estado = 'FIRMADO' AND g.adjunto = ? ORDER BY f.fecha_firma", array($adjunto), PDO::FETCH_NUM);
    }
}
