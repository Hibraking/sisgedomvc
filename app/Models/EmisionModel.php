<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Emisión de documentos — mismas consultas de sisgedov2/clases/docs_emitidos.php.
 * Fila (índices): 0 id · 1 tipo doc · 2 número · 3 asunto · 4 detalles · 5 destino · 6 estado (0/1)
 * 7 adjunto ("emi:<archivo>" o "#") · 8 fecha (texto) · 9 fecha · 10 iddocumento · 11 idoficina destino
 */
class EmisionModel
{
    public static function emitidos($idoficina, $desde, $hasta)
    {
        try {
            return Database::filas("select de.iddocs_emitidos,documentos.nombre_documento,de.numero,de.asunto,coalesce(de.detalles,''),
                                           coalesce((select o2.nombre from oficinas o2 where o2.idoficinas=de.idoficina),''),
                                           de.estado,
                                           if(de.adjunto is null,'#',concat('emi:',de.adjunto)),
                                           date_format(de.fecha,'%d/%m/%Y %h:%i %p'),
                                           de.fecha,de.iddocumento,coalesce(de.idoficina,0)
                                      from docs_emitidos de
                                     inner join documentos on de.iddocumento=documentos.iddocumentos
                                     where de.idoficina_registro=? and de.fecha >= ? and de.fecha < date_add(?, interval 1 day)
                                     order by de.fecha desc", array($idoficina, $desde, $hasta), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('EmisionModel: ' . $e->getMessage());
            return array();
        }
    }

    public static function contadores($idoficina)
    {
        try {
            $f = Database::filas("select coalesce(sum(idoficina is not null and estado=0),0), coalesce(sum(estado=1),0), coalesce(sum(idoficina is null),0)
                                    from docs_emitidos where idoficina_registro=?", array($idoficina), PDO::FETCH_NUM);
            return array('por_recepcionar' => (int)$f[0][0], 'recepcionados' => (int)$f[0][1], 'sin_destino' => (int)$f[0][2]);
        } catch (\Exception $e) {
            error_log('EmisionModel contadores: ' . $e->getMessage());
            return array('por_recepcionar' => 0, 'recepcionados' => 0, 'sin_destino' => 0);
        }
    }

    public static function tiposDocumento()
    {
        return Database::filas("SELECT iddocumentos,nombre_documento FROM documentos WHERE TRIM(COALESCE(nombre_documento,'')) <> '' ORDER BY nombre_documento ASC", array(), PDO::FETCH_NUM);
    }

    /** Estado de firma de los documentos (las filas de un mismo envío comparten el adjunto). [id => "Firmado + V°B°"] */
    public static function firmas(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return array();
        $out = array();
        try {
            foreach (Database::filas("SELECT de.iddocs_emitidos, GROUP_CONCAT(DISTINCT IF(f.tipo_firma='FIRMA','Firmado','V°B°') ORDER BY f.tipo_firma SEPARATOR ' + ')
                                        FROM docs_emitidos de
                                       INNER JOIN docs_emitidos g ON g.adjunto = de.adjunto AND g.idoficina_registro = de.idoficina_registro
                                       INNER JOIN sgd_firma f ON f.iddocs_emitidos = g.iddocs_emitidos AND f.estado = 'FIRMADO'
                                       WHERE de.adjunto IS NOT NULL AND de.iddocs_emitidos IN (" . implode(',', $ids) . ")
                                       GROUP BY de.iddocs_emitidos", array(), PDO::FETCH_NUM) as $f) {
                $out[$f[0]] = $f[1];
            }
        } catch (\Exception $e) {
            error_log('EmisionModel firmas: ' . $e->getMessage());
        }
        return $out;
    }

    /** Solo se modifica/elimina si nadie lo recepcionó ni tiene movimientos. */
    public static function editable($codigo)
    {
        $estado = Database::valor("select estado from docs_emitidos where iddocs_emitidos=?", array((int)$codigo));
        if ($estado === false || (int)$estado === 1) return false;
        return (int)Database::valor("select count(*) from movimientos where iddocs_emitidos=? or iddocrespuesta=?", array((int)$codigo, (int)$codigo)) === 0;
    }

    /** Oficina del responsable (la que numera el documento). */
    public static function oficinaResponsable($idresponsable)
    {
        return (int)Database::valor("SELECT idoficina FROM responsables WHERE idresponsables = ?", array((int)$idresponsable));
    }

    public static function insertar($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $adjunto)
    {
        Database::consulta("INSERT INTO docs_emitidos(iddocumento,idresponsable,idoficina,asunto,detalles,numero,adjunto) VALUES(?,?,?,?,?,?,?)",
            array($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $adjunto));
        return (int)Database::pdo()->lastInsertId();
    }

    public static function modificar($codigo, $iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero)
    {
        Database::consulta("UPDATE docs_emitidos SET iddocumento=?, idresponsable=?, idoficina=?, asunto=?, detalles=?, numero=? WHERE iddocs_emitidos=?",
            array($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, (int)$codigo));
    }

    public static function eliminar($codigo)
    {
        Database::consulta("DELETE FROM docs_emitidos WHERE iddocs_emitidos=?", array((int)$codigo));
    }

    /* ---------------- Firma digital ---------------- */

    public static function documentoParaFirma($id)
    {
        return Database::fila("SELECT iddocs_emitidos, adjunto, idoficina_registro, estado FROM docs_emitidos WHERE iddocs_emitidos = ?", array((int)$id));
    }
}
