<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Registro de documentos (mesa de partes presencial) y remitentes — mismas consultas
 * de sisgedov2/clases/reg_docs.php y clases/remitentes.php.
 *
 * Fila (índices): 0 id · 1 tipo doc · 2 remitente · 3 trámite · 4 oficina destino · 5 nro_exp
 * 6 n° documento · 7 asunto · 8 para su · 9 observación · 10 folios · 11 fecha (texto) · 12 fecha
 * 13 estado_recepcion · 14 adjunto ("reg:<archivo>" o "#") · 15 iddocumento · 16 idremitente
 * 17 idtipo_tramite · 18 idoficina · 19 procedencia · 20 recepcion_web · 21 oficina de quien registró
 */
class RegistroModel
{
    public static function registrados($desde, $hasta)
    {
        $sql = "select rd.idregistro_documentos,documentos.nombre_documento,concat(remitente.apellidos,', ',remitente.nombres),
                       tipo_tram.nombre,coalesce(o.nombre,''),rd.nro_exp,coalesce(rd.numero_docu,''),rd.asunto,
                       coalesce(rd.parasu,''),coalesce(rd.observacion,''),coalesce(rd.folios,0),
                       date_format(rd.fecha_registro,'%d/%m/%Y %h:%i %p'),rd.fecha_registro,
                       rd.estado_recepcion,
                       if(rd.adjunto is null,'#',concat('reg:',rd.adjunto)),
                       rd.iddocumento,rd.idremitente,rd.idtipo_tramite,coalesce(rd.idoficina,0),coalesce(rd.lugar_procedencia,''),
                       coalesce(rd.recepcion_web,0),
                       coalesce((select o2.nombre from responsables rr inner join oficinas o2 on rr.idoficina=o2.idoficinas where rr.idpersonal=rd.idpersonal limit 1),'')
                  from registro_documentos rd
                 inner join documentos on rd.iddocumento=documentos.iddocumentos
                 inner join remitente on rd.idremitente=remitente.idremitente
                 inner join tipo_tram on rd.idtipo_tramite=tipo_tram.idtipo_tram
                  left join oficinas o on rd.idoficina=o.idoficinas ";
        try {
            if (MesaPartesModel::moduloActivo()) {
                // Con la Mesa de Partes Virtual, Registro es solo lo PRESENCIAL
                return Database::filas($sql . " where rd.fecha_registro >= ? and rd.fecha_registro < date_add(?, interval 1 day)
                                                  and coalesce(rd.recepcion_web,0)=0 order by rd.fecha_registro desc",
                    array($desde, $hasta), PDO::FETCH_NUM);
            }
            return Database::filas($sql . " where rd.fecha_registro >= ? and rd.fecha_registro < date_add(?, interval 1 day)
                                               or (coalesce(rd.recepcion_web,0)=1 and rd.idoficina is null and rd.estado_recepcion=0
                                                   and rd.fecha_registro >= date_sub(now(), interval ? day))
                                             order by rd.fecha_registro desc",
                array($desde, $hasta, (int)SISGEDO_DIAS_NUEVO), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('RegistroModel: ' . $e->getMessage());
            return array();
        }
    }

    public static function contadores()
    {
        $out = array('hoy' => 0, 'semana' => 0, 'pendientes' => 0, 'anio' => 0);
        try {
            $pres = MesaPartesModel::moduloActivo() ? " and recepcion_web=0" : "";
            $out['hoy'] = (int)Database::valor("select count(*) from registro_documentos where fecha_registro >= curdate() and fecha_registro < curdate() + interval 1 day" . $pres);
            $out['semana'] = (int)Database::valor("select count(*) from registro_documentos where fecha_registro>=date_sub(now(),interval 7 day)" . $pres);
            $out['pendientes'] = (int)Database::valor("select count(*) from registro_documentos where estado_recepcion=0" . $pres);
            $out['anio'] = (int)Database::valor("select count(*) from registro_documentos where fecha_registro >= makedate(year(now()),1) and fecha_registro < makedate(year(now())+1,1)" . $pres);
        } catch (\Exception $e) {
            error_log('RegistroModel contadores: ' . $e->getMessage());
        }
        return $out;
    }

    public static function tiposDocumento()
    {
        return Database::filas("SELECT iddocumentos,nombre_documento FROM documentos WHERE TRIM(COALESCE(nombre_documento,'')) <> '' ORDER BY nombre_documento ASC", array(), PDO::FETCH_NUM);
    }

    public static function tramites()
    {
        return Database::filas("SELECT idtipo_tram,nombre FROM tipo_tram ORDER BY nombre ASC", array(), PDO::FETCH_NUM);
    }

    /** Solo se modifica/elimina si nadie lo recepcionó ni tiene movimientos. */
    public static function editable($codigo)
    {
        $er = Database::valor("select estado_recepcion from registro_documentos where idregistro_documentos=?", array((int)$codigo));
        if ($er === false || (int)$er === 1) return false;
        return (int)Database::valor("select count(*) from movimientos where idregdocs=?", array((int)$codigo)) === 0;
    }

    public static function insertar(array $d)
    {
        Database::consulta("INSERT INTO registro_documentos (iddocumento,idremitente,idtipo_tramite,idoficina,nro_exp,numero_docu,asunto,parasu,observacion,folios,adjunto,idpersonal,lugar_procedencia)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array($d['iddocumento'], $d['idremitente'], $d['idtipo_tramite'], $d['idoficina'], $d['nro_exp'], $d['numero_docu'], $d['asunto'],
                  $d['parasu'], $d['observacion'], $d['folios'], $d['adjunto'], $d['idpersonal'], $d['procedencia']));
    }

    public static function modificar($codigo, array $d)
    {
        Database::consulta("UPDATE registro_documentos SET iddocumento=?, idremitente=?, idtipo_tramite=?, idoficina=?, numero_docu=?, asunto=?,
                                   parasu=?, observacion=?, folios=?, idpersonal=?, lugar_procedencia=? WHERE idregistro_documentos=?",
            array($d['iddocumento'], $d['idremitente'], $d['idtipo_tramite'], $d['idoficina'], $d['numero_docu'], $d['asunto'],
                  $d['parasu'], $d['observacion'], $d['folios'], $d['idpersonal'], $d['procedencia'], (int)$codigo));
    }

    public static function actualizarAdjunto($codigo, $adjunto)
    {
        Database::consulta("UPDATE registro_documentos SET adjunto = ? WHERE idregistro_documentos = ?", array($adjunto, (int)$codigo));
    }

    public static function eliminar($codigo)
    {
        return Database::consulta("DELETE FROM registro_documentos WHERE idregistro_documentos=?", array((int)$codigo))->rowCount() > 0;
    }

    /* ---------------- Remitentes ---------------- */

    /** [[id, "APELLIDOS, NOMBRES"], ...] (lo usa la lista de búsqueda del formulario) */
    public static function remitentes()
    {
        return Database::filas("SELECT remitente.idremitente,concat(remitente.apellidos,', ',remitente.nombres) as ape_nom FROM remitente", array(), PDO::FETCH_BOTH);
    }

    public static function remitentePorEmail($email)
    {
        $v = Database::valor("select idremitente from remitente where email=?", array($email));
        return $v === false ? null : (int)$v;
    }

    /** Remitente con el mismo nombre completo (sin mayúsculas/tildes/espacios de más); 0 si no hay. */
    public static function remitentePorNombre($apellidos, $nombres)
    {
        $completo = trim(preg_replace('/\s+/u', ' ', trim((string)$apellidos) . ' ' . trim((string)$nombres)));
        if ($completo === '') return 0;
        return (int)Database::valor("SELECT idremitente FROM remitente
                                      WHERE REGEXP_REPLACE(TRIM(CONCAT_WS(' ', TRIM(apellidos), TRIM(nombres))), ' {2,}', ' ') = ?
                                      ORDER BY (TRIM(IFNULL(d_identidad,'')) <> '') DESC, idremitente ASC LIMIT 1", array($completo));
    }

    public static function crearRemitente($apellidos, $nombres, $direccion, $email, $telefono)
    {
        Database::consulta("INSERT INTO remitente(apellidos,nombres,direccion,email,telefono) VALUES(?,?,?,?,?)",
            array($apellidos, $nombres, $direccion, $email, $telefono));
        return (int)Database::pdo()->lastInsertId();
    }
}
