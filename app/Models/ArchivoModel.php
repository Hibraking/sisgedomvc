<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Archivo de documentos de la oficina — mismas consultas de sisgedov2/clases/archivar_docs.php
 * (consultas separadas unidas en PHP por la mezcla de collations tras utf8mb4).
 * Por archivar (índices): 0 id · 1 clase (EMITIDO|TRAMITADO) · 2 documento · 3 número/exp · 4 asunto
 *   5 detalles · 6 fecha (texto) · 7 fecha · 8 adjunto ("reg:"/"emi:" + archivo, o "#")
 * Archivados: 0 iddocs_archivados · 1 archivador · 2..8 igual · 9 clase · 10 idarchivador
 */
class ArchivoModel
{
    /** [[id, nombre, año, detalles, n° documentos], ...] */
    public static function archivadores($idoficina)
    {
        return self::seguro(function () use ($idoficina) {
            return Database::filas("select a.idarchivador,a.nombre,a.anio,coalesce(a.detalles,'') as detalles,
                                           (select count(*) from docs_archivados da where da.idarchivador=a.idarchivador) as ndocs
                                      from archivador a where a.idoficina=? order by a.anio desc, a.nombre asc", array($idoficina), PDO::FETCH_NUM);
        });
    }

    public static function porArchivar($idoficina, $desde, $hasta)
    {
        return self::seguro(function () use ($idoficina, $desde, $hasta) {
            // a) Emitidos por la oficina sin archivar (sin ventana de fechas: es trabajo pendiente)
            $f = Database::filas("select de.iddocs_emitidos,'EMITIDO',documentos.nombre_documento,de.numero,de.asunto,coalesce(de.detalles,''),
                                         date_format(de.fecha,'%d/%m/%Y %h:%i %p'),de.fecha,
                                         if(de.adjunto is null,'#',concat('emi:',de.adjunto))
                                    from docs_emitidos de
                                   inner join responsables r on de.idresponsable=r.idresponsables
                                   inner join documentos on de.iddocumento=documentos.iddocumentos
                                   where r.idoficina=? and not exists (select 1 from docs_archivados da where da.iddocs_emitidos=de.iddocs_emitidos)",
                array($idoficina), PDO::FETCH_NUM);
            // b) Externos recibidos por la oficina (recepción directa o derivación ya recibida)
            $f = array_merge($f, Database::filas("select m.idMovimientos,'TRAMITADO',documentos.nombre_documento,rd.nro_exp,rd.asunto,coalesce(m.detalles,''),
                                         date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p'),m.fecha_hora,
                                         if(rd.adjunto is null,'#',concat('reg:',rd.adjunto))
                                    from movimientos m
                                   inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
                                   inner join documentos on rd.iddocumento=documentos.iddocumentos
                                   where m.idoficina_derivar=? and (m.idmovimientoant is null or m.estado=1) and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)
                                     and not exists (select 1 from docs_archivados da where da.idmovimientos=m.idMovimientos)",
                array($idoficina, $desde, $hasta), PDO::FETCH_NUM));
            // c) Movimientos recibidos de documentos internos
            $f = array_merge($f, Database::filas("select m.idMovimientos,'TRAMITADO',documentos.nombre_documento,de.numero,de.asunto,coalesce(m.detalles,''),
                                         date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p'),m.fecha_hora,
                                         if(de.adjunto is null,'#',concat('emi:',de.adjunto))
                                    from movimientos m
                                   inner join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
                                   inner join documentos on de.iddocumento=documentos.iddocumentos
                                   where m.idoficina_derivar=? and (m.idmovimientoant is null or m.estado=1) and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)
                                     and not exists (select 1 from docs_archivados da where da.idmovimientos=m.idMovimientos)",
                array($idoficina, $desde, $hasta), PDO::FETCH_NUM));
            usort($f, function ($a, $b) { return strcmp((string)$b[7], (string)$a[7]); });
            return $f;
        });
    }

    public static function archivados($idoficina)
    {
        return self::seguro(function () use ($idoficina) {
            $f = Database::filas("select da.iddocs_archivados,concat(a.nombre,' - ',a.anio),documentos.nombre_documento,de.numero,de.asunto,coalesce(da.detalles,''),
                                         date_format(da.fecha_hora,'%d/%m/%Y %h:%i %p'),da.fecha_hora,
                                         if(de.adjunto is null,'#',concat('emi:',de.adjunto)),'EMITIDO',a.idarchivador
                                    from docs_archivados da
                                   inner join archivador a on da.idarchivador=a.idarchivador
                                   inner join docs_emitidos de on da.iddocs_emitidos=de.iddocs_emitidos
                                   inner join documentos on de.iddocumento=documentos.iddocumentos
                                   where a.idoficina=?", array($idoficina), PDO::FETCH_NUM);
            $f = array_merge($f, Database::filas("select da.iddocs_archivados,concat(a.nombre,' - ',a.anio),documentos.nombre_documento,rd.nro_exp,rd.asunto,coalesce(da.detalles,''),
                                         date_format(da.fecha_hora,'%d/%m/%Y %h:%i %p'),da.fecha_hora,
                                         if(rd.adjunto is null,'#',concat('reg:',rd.adjunto)),'TRAMITADO',a.idarchivador
                                    from docs_archivados da
                                   inner join archivador a on da.idarchivador=a.idarchivador
                                   inner join movimientos m on da.idmovimientos=m.idMovimientos
                                   inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
                                   inner join documentos on rd.iddocumento=documentos.iddocumentos
                                   where a.idoficina=?", array($idoficina), PDO::FETCH_NUM));
            $f = array_merge($f, Database::filas("select da.iddocs_archivados,concat(a.nombre,' - ',a.anio),documentos.nombre_documento,de.numero,de.asunto,coalesce(da.detalles,''),
                                         date_format(da.fecha_hora,'%d/%m/%Y %h:%i %p'),da.fecha_hora,
                                         if(de.adjunto is null,'#',concat('emi:',de.adjunto)),'TRAMITADO',a.idarchivador
                                    from docs_archivados da
                                   inner join archivador a on da.idarchivador=a.idarchivador
                                   inner join movimientos m on da.idmovimientos=m.idMovimientos
                                   inner join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
                                   inner join documentos on de.iddocumento=documentos.iddocumentos
                                   where a.idoficina=?", array($idoficina), PDO::FETCH_NUM));
            usort($f, function ($a, $b) { return strcmp((string)$b[7], (string)$a[7]); });
            return $f;
        });
    }

    public static function contadores($idoficina)
    {
        $out = array('archivadores' => 0, 'archivados' => 0, 'emitidos_sin_archivar' => 0, 'tramitados_sin_archivar' => 0);
        try {
            $out['archivadores'] = (int)Database::valor("select count(*) from archivador where idoficina=?", array($idoficina));
            $out['archivados'] = (int)Database::valor("select count(*) from docs_archivados da inner join archivador a on da.idarchivador=a.idarchivador where a.idoficina=?", array($idoficina));
            $out['emitidos_sin_archivar'] = (int)Database::valor("select count(*) from docs_emitidos de inner join responsables r on de.idresponsable=r.idresponsables where r.idoficina=? and not exists (select 1 from docs_archivados da where da.iddocs_emitidos=de.iddocs_emitidos)", array($idoficina));
            $out['tramitados_sin_archivar'] = (int)Database::valor("select count(*) from movimientos m where m.idoficina_derivar=? and (m.idmovimientoant is null or m.estado=1) and not exists (select 1 from docs_archivados da where da.idmovimientos=m.idMovimientos)", array($idoficina));
        } catch (\Exception $e) {
            error_log('ArchivoModel contadores: ' . $e->getMessage());
        }
        return $out;
    }

    /** Oficina emisora de un documento emitido (la del responsable que lo emitió); null si no existe. */
    public static function oficinaEmisora($iddocsEmitidos)
    {
        $v = Database::valor("select r.idoficina from docs_emitidos de inner join responsables r on de.idresponsable=r.idresponsables where de.iddocs_emitidos=?", array((int)$iddocsEmitidos));
        return $v === false ? null : (int)$v;
    }

    public static function crearArchivador($idoficina, $nombre, $detalles, $anio)
    {
        Database::consulta("INSERT INTO archivador(idoficina,nombre,detalles,anio) VALUES(?,?,?,?)", array((int)$idoficina, $nombre, $detalles, $anio));
    }

    private static function seguro($fn)
    {
        try {
            return $fn();
        } catch (\Exception $e) {
            error_log('ArchivoModel: ' . $e->getMessage());
            return array();
        }
    }
}
