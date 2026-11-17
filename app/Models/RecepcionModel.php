<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Recepción y Derivados: SOLO consultas (las mismas de sisgedov2,
 * clases/recepcion_proveido.php y vistas/v_recepcion_proveido.php).
 *
 * Cada fila de bandeja tiene 13 columnas (índice numérico):
 *   0 codigo · 1 origen/destino · 2 trámite · 3 documento · 4 n° exp · 5 número
 *   6 asunto · 7 detalles · 8 respuesta · 9 fecha (texto) · 10 adjunto · 11 tipo · 12 fecha (cruda)
 * tipo: '1' registro, '2' emitido, '3' derivación (pendientes) · 'R' recepcionado · 'E' derivado
 *
 * Columna 10: aquí queda como "reg:<archivo>", "emi:<archivo>" o "#"; el
 * controlador la convierte en la dirección de descarga.
 */
class RecepcionModel
{
    /* ===================== Bandejas ===================== */

    /** Pendientes de recepcionar en la oficina (derivaciones, registros y emitidos). */
    public static function porRecepcionar($idoficina)
    {
        $sql = "(select movimientos.idMovimientos as codigo,
                oant.nombre as oficina,
                'INTERNO' as tramite,documentos.nombre_documento,docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                movimientos.detalle_derivado as detalles,'--' as Respuesta,
                date_format(movimientos.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha,if(adjunto is null,'#', concat('emi:',adjunto)) as adjunto,'3' as tipo,
                movimientos.fecha_hora as fecha_horaa
                from movimientos inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                left join movimientos mant on mant.idMovimientos=movimientos.idmovimientoant left join oficinas oant on oant.idoficinas=mant.idoficina_derivar
                where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                union all
                (select movimientos.idMovimientos as codigo,
                oant.nombre as oficina,
                tipo_tram.nombre as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,
                registro_documentos.asunto,movimientos.detalle_derivado as detalles,'--' as Respuesta,
                date_format(movimientos.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha,if(adjunto is null,'#', concat('reg:',adjunto)) as adjunto,'3' as tipo,
                movimientos.fecha_hora as fecha_horaa
                from movimientos inner join registro_documentos on movimientos.idregdocs= registro_documentos.idregistro_documentos inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                left join movimientos mant on mant.idMovimientos=movimientos.idmovimientoant left join oficinas oant on oant.idoficinas=mant.idoficina_derivar
                where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                union all
                (select registro_documentos.idregistro_documentos as codigo,
                if(coalesce(registro_documentos.recepcion_web,0)=1,'MESA DE PARTES VIRTUAL',
                   coalesce((select o9.nombre from responsables rr9 inner join oficinas o9 on rr9.idoficina=o9.idoficinas where rr9.idpersonal=registro_documentos.idpersonal limit 1),'MESA DE PARTES')) AS oficina,
                tipo_tram.nombre as tramite,documentos.nombre_documento,
                registro_documentos.nro_exp ,registro_documentos.numero_docu as numero ,registro_documentos.asunto,registro_documentos.observacion as detalles,'--' as Respuesta,
                date_format(registro_documentos.fecha_registro,'%d/%m/%Y %h:%i %p') as fecha,if(adjunto is null,'#', concat('reg:',adjunto)) as adjunto,'1' as tipo,
                registro_documentos.fecha_registro as fecha_horaa
                from registro_documentos inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                where registro_documentos.estado_recepcion=0 and idoficina=?)
                union all
                (select docs_emitidos.iddocs_emitidos as codigo,oficinas.nombre as oficina,'INTERNO' AS tramite,documentos.nombre_documento,
                docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,docs_emitidos.detalles,'--' as Respuesta,
                date_format(docs_emitidos.fecha,'%d/%m/%Y %h:%i %p') as fecha,if(adjunto is null,'#', concat('emi:',adjunto)) as adjunto,'2' as tipo,
                docs_emitidos.fecha as fecha_horaa
                from docs_emitidos inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                where docs_emitidos.estado=0 and docs_emitidos.idoficina=?)
                order by fecha_horaa desc";
        return self::seguro(function () use ($sql, $idoficina) {
            return Database::filas($sql, array($idoficina, $idoficina, $idoficina, $idoficina), PDO::FETCH_NUM);
        });
    }

    /**
     * Recibido por la oficina en el rango: recepción directa (primer movimiento)
     * o derivación ya recibida. Dos consultas unidas en PHP (un UNION falla
     * por la mezcla de collations tras la migración a utf8mb4).
     */
    public static function recepcionados($idoficina, $desde, $hasta)
    {
        $sql = "select m.idMovimientos as codigo,
                if(m.idmovimientoant is null,'MESA DE PARTES',
                  (select o2.nombre from movimientos m2 inner join responsables r2 on m2.idresponsable=r2.idresponsables inner join oficinas o2 on r2.idoficina=o2.idoficinas where m2.idMovimientos=m.idmovimientoant)) as origen,
                'EXTERNO' as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,registro_documentos.asunto,
                m.detalles,ifnull(aciones_documento.nombre_accion,'--') as Respuesta,
                date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha,
                if(registro_documentos.adjunto is null,'#',concat('reg:',registro_documentos.adjunto)) as adjunto,
                'R' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join registro_documentos on m.idregdocs=registro_documentos.idregistro_documentos
                inner join oficinas on m.idoficina_derivar=oficinas.idoficinas
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                left join aciones_documento on m.idacciondocs=aciones_documento.idaciones_documento
                where oficinas.idoficinas=? and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)
                  and (m.idmovimientoant is null or m.estado=1)";
        $sql2 = "select m.idMovimientos as codigo,
                if(m.idmovimientoant is null,
                  (select o3.nombre from responsables r3 inner join oficinas o3 on r3.idoficina=o3.idoficinas where r3.idresponsables=docs_emitidos.idresponsable),
                  (select o2.nombre from movimientos m2 inner join responsables r2 on m2.idresponsable=r2.idresponsables inner join oficinas o2 on r2.idoficina=o2.idoficinas where m2.idMovimientos=m.idmovimientoant)) as origen,
                'INTERNO' as tramite,documentos.nombre_documento,cast(docs_emitidos.iddocs_emitidos as char) as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                m.detalles,ifnull(aciones_documento.nombre_accion,'--') as Respuesta,
                date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha,
                if(docs_emitidos.adjunto is null,'#',concat('emi:',docs_emitidos.adjunto)) as adjunto,
                'R' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join docs_emitidos on m.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                inner join oficinas on m.idoficina_derivar=oficinas.idoficinas
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                left join aciones_documento on m.idacciondocs=aciones_documento.idaciones_documento
                where oficinas.idoficinas=? and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)
                  and (m.idmovimientoant is null or m.estado=1)";
        return self::dosPartes($sql, $sql2, array($idoficina, $desde, $hasta));
    }

    /** Derivado por la oficina en el rango (columna 1 = oficina destino). */
    public static function derivados($idoficina, $desde, $hasta)
    {
        $sql = "select m.idMovimientos as codigo,
                (select o2.nombre from oficinas o2 where o2.idoficinas=m.idoficina_derivar) as destino,
                'EXTERNO' as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,registro_documentos.asunto,
                m.detalle_derivado as detalles,ifnull(estado_documento.nombre,'--') as Respuesta,
                date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha,
                if(registro_documentos.adjunto is null,'#',concat('reg:',registro_documentos.adjunto)) as adjunto,
                'E' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join movimientos ma on m.idmovimientoant=ma.idMovimientos
                inner join responsables r on ma.idresponsable=r.idresponsables
                inner join registro_documentos on m.idregdocs=registro_documentos.idregistro_documentos
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                left join estado_documento on m.idestadodocs=estado_documento.idestado_documento
                where r.idoficina=? and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)";
        $sql2 = "select m.idMovimientos as codigo,
                (select o2.nombre from oficinas o2 where o2.idoficinas=m.idoficina_derivar) as destino,
                'INTERNO' as tramite,documentos.nombre_documento,cast(docs_emitidos.iddocs_emitidos as char) as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                m.detalle_derivado as detalles,ifnull(estado_documento.nombre,'--') as Respuesta,
                date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha,
                if(docs_emitidos.adjunto is null,'#',concat('emi:',docs_emitidos.adjunto)) as adjunto,
                'E' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join movimientos ma on m.idmovimientoant=ma.idMovimientos
                inner join responsables r on ma.idresponsable=r.idresponsables
                inner join docs_emitidos on m.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                left join estado_documento on m.idestadodocs=estado_documento.idestado_documento
                where r.idoficina=? and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)";
        return self::dosPartes($sql, $sql2, array($idoficina, $desde, $hasta));
    }

    /* ===================== Indicadores ===================== */

    /** Total por recepcionar (para el KPI cuando se ve otra bandeja). */
    public static function contarPendientes($idoficina)
    {
        return (int)self::seguro(function () use ($idoficina) {
            return (int)Database::valor("select count(*) from movimientos where idoficina_derivar=? and idmovimientoant is not null and estado=0", array($idoficina))
                + (int)Database::valor("select count(*) from registro_documentos where estado_recepcion=0 and idoficina=?", array($idoficina))
                + (int)Database::valor("select count(*) from docs_emitidos where estado=0 and idoficina=?", array($idoficina));
        }, 0);
    }

    /** ['recepcionados' => histórico recibido, 'enviados' => histórico de movimientos de la oficina] */
    public static function contadores($idoficina)
    {
        return self::seguro(function () use ($idoficina) {
            return array(
                'recepcionados' => (int)Database::valor("select count(*) from movimientos where idoficina_derivar=? and (idmovimientoant is null or estado=1)", array($idoficina)),
                'enviados' => (int)Database::valor("select count(*) from movimientos m inner join responsables r on m.idresponsable=r.idresponsables where r.idoficina=?", array($idoficina)),
            );
        }, array('recepcionados' => 0, 'enviados' => 0));
    }

    public static function recepcionadosHoy($idoficina)
    {
        return (int)self::seguro(function () use ($idoficina) {
            return Database::valor("select count(*) from movimientos where idoficina_derivar=? and (idmovimientoant is null or estado=1) and fecha_hora >= curdate() and fecha_hora < curdate() + interval 1 day", array($idoficina));
        }, 0);
    }

    /** ['hoy' => n, 'mes' => n, 'destinos' => oficinas distintas este mes] */
    public static function kpisDerivados($idoficina)
    {
        $base = "from movimientos m inner join movimientos ma on m.idmovimientoant=ma.idMovimientos inner join responsables r on ma.idresponsable=r.idresponsables where r.idoficina=?";
        $mes = " and m.fecha_hora >= date_format(now(),'%Y-%m-01') and m.fecha_hora < date_format(now(),'%Y-%m-01') + interval 1 month";
        return self::seguro(function () use ($idoficina, $base, $mes) {
            return array(
                'hoy' => (int)Database::valor("select count(*) $base and m.fecha_hora >= curdate() and m.fecha_hora < curdate() + interval 1 day", array($idoficina)),
                'mes' => (int)Database::valor("select count(*) $base $mes", array($idoficina)),
                'destinos' => (int)Database::valor("select count(distinct m.idoficina_derivar) $base $mes", array($idoficina)),
            );
        }, array('hoy' => 0, 'mes' => 0, 'destinos' => 0));
    }

    /* ===================== Catálogos ===================== */

    public static function acciones()
    {
        return self::seguro(function () {
            return Database::filas("SELECT idaciones_documento,nombre_accion FROM aciones_documento", array(), PDO::FETCH_BOTH);
        });
    }

    public static function estados()
    {
        return self::seguro(function () {
            return Database::filas("SELECT idestado_documento,nombre FROM estado_documento", array(), PDO::FETCH_BOTH);
        });
    }

    public static function oficinas()
    {
        return self::seguro(function () {
            return Database::filas("SELECT idoficinas,nombre FROM oficinas", array(), PDO::FETCH_BOTH);
        });
    }

    public static function archivadores($idoficina)
    {
        return self::seguro(function () use ($idoficina) {
            return Database::filas("select idarchivador,nombre from archivador where idoficina=?", array($idoficina), PDO::FETCH_BOTH);
        });
    }

    public static function archivadorDeOficina($idarchivador, $idoficina)
    {
        return (int)Database::valor("SELECT COUNT(*) FROM archivador WHERE idarchivador=? AND idoficina=?", array((int)$idarchivador, (int)$idoficina)) > 0;
    }

    /** idregdocs / iddocs_emitidos de un movimiento (para derivar lo ya recepcionado). */
    public static function documentoDeMovimiento($idmovimiento)
    {
        return self::seguro(function () use ($idmovimiento) {
            return Database::filas("SELECT idregdocs,iddocs_emitidos FROM movimientos where idMovimientos=?", array((int)$idmovimiento), PDO::FETCH_BOTH);
        });
    }

    /** Oficina donde está el movimiento (idoficina_derivar). null si no existe. */
    public static function oficinaDeMovimiento($idmovimiento)
    {
        $v = Database::valor("SELECT idoficina_derivar FROM movimientos WHERE idMovimientos=?", array((int)$idmovimiento));
        return $v === false ? null : (int)$v;
    }

    /* ===================== Historial ===================== */

    /** Filas: [codigo, fecha, hora, oficina, acción, estado, detalles, evento] */
    public static function historial($codigo, $tipo)
    {
        $codigo = (int)$codigo;
        $tipo = trim((string)$tipo);
        if ($codigo <= 0) return array();

        $regA = "select rd.idregistro_documentos as codigo,
                        date_format(rd.fecha_registro,'%d/%m/%Y') as fecha,
                        date_format(rd.fecha_registro,'%h:%i %p') as hora,
                        'Secretaría General' as oficina, 'Registro' as accion,
                        'Registrado, sin recepcionar' as estado,
                        coalesce(rd.observacion,'') as detalles, 'Registro' as evento,
                        rd.fecha_registro as orden
                   from registro_documentos rd where rd.idregistro_documentos=?";
        $emiA = "select de.iddocs_emitidos as codigo,
                        date_format(de.fecha,'%d/%m/%Y') as fecha,
                        date_format(de.fecha,'%h:%i %p') as hora,
                        coalesce(ofi.nombre,'') as oficina, 'Registro' as accion,
                        'Emitido, sin recepcionar' as estado,
                        coalesce(de.detalles,'') as detalles, 'Registro' as evento,
                        de.fecha as orden
                   from docs_emitidos de
                   left join oficinas ofi on de.idoficina_registro=ofi.idoficinas
                  where de.iddocs_emitidos=?";
        $movs = "select m.idMovimientos as codigo,
                        date_format(m.fecha_hora,'%d/%m/%Y') as fecha,
                        date_format(m.fecha_hora,'%h:%i %p') as hora,
                        coalesce(ofi.nombre,ofid.nombre,'') as oficina,
                        coalesce(acc.nombre_accion,'--') as accion,
                        coalesce(est.nombre,'--') as estado,
                        coalesce(m.detalles,m.detalle_derivado,'') as detalles,
                        case when m.idmovimientoant is null then 'Origen'
                             when m.estado=0 then 'Ubicación actual'
                             else 'Movimiento' end as evento,
                        m.fecha_hora as orden
                   from movimientos m
                   left join responsables r on m.idresponsable=r.idresponsables
                   left join oficinas ofi on r.idoficina=ofi.idoficinas
                   left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                   left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                   left join estado_documento est on m.idestadodocs=est.idestado_documento
                  where m.{columna}=?";

        return self::seguro(function () use ($codigo, $tipo, $regA, $emiA, $movs) {
            // Documento aún sin movimientos: solo su registro
            if ($tipo === '1' || $tipo === '2') {
                $filas = Database::filas($tipo === '1' ? $regA : $emiA, array($codigo), PDO::FETCH_NUM);
                foreach ($filas as &$f) array_pop($f);   // sin la columna de orden (igual que sisgedov2)
                return $filas;
            }
            $mov = Database::fila("select idregdocs,iddocs_emitidos from movimientos where idMovimientos=? limit 1", array($codigo));
            if (!$mov) return array();
            if ((int)$mov['idregdocs'] > 0) {
                $id = (int)$mov['idregdocs'];
                $h = array_merge(Database::filas($regA, array($id), PDO::FETCH_NUM), Database::filas(str_replace('{columna}', 'idregdocs', $movs), array($id), PDO::FETCH_NUM));
            } elseif ((int)$mov['iddocs_emitidos'] > 0) {
                $id = (int)$mov['iddocs_emitidos'];
                $h = array_merge(Database::filas($emiA, array($id), PDO::FETCH_NUM), Database::filas(str_replace('{columna}', 'iddocs_emitidos', $movs), array($id), PDO::FETCH_NUM));
            } else {
                return array();
            }
            usort($h, function ($a, $b) {
                $c = strcmp((string)$a[8], (string)$b[8]);
                return $c !== 0 ? $c : ((int)$a[0] - (int)$b[0]);
            });
            return $h;
        });
    }

    /* ===================== Apoyo ===================== */

    private static function dosPartes($sql1, $sql2, array $params)
    {
        return self::seguro(function () use ($sql1, $sql2, $params) {
            $filas = array_merge(Database::filas($sql1, $params, PDO::FETCH_NUM), Database::filas($sql2, $params, PDO::FETCH_NUM));
            usort($filas, function ($a, $b) {
                return strcmp((string)$b[12], (string)$a[12]);   // más recientes primero
            });
            return $filas;
        });
    }

    /** Ejecuta una consulta de solo lectura; ante un error lo registra y devuelve $siFalla (como sisgedov2). */
    private static function seguro($fn, $siFalla = array())
    {
        try {
            return $fn();
        } catch (\Exception $e) {
            error_log('RecepcionModel: ' . $e->getMessage());
            return $siFalla;
        }
    }
}
