<?php
namespace App\Models;

use App\Core\Database;
use App\Services\ArchivoService;
use App\Services\NumeracionService;
use PDO;

/** Datos de los cargos PDF (mismas consultas de sisgedov2/pdf/cargo.php y cargo_interno.php). */
class CargoModel
{
    /** N° de expediente al que pertenece un código de verificación (o null). */
    public static function expedientePorCvd($codigo)
    {
        $cvd = ArchivoService::normalizarCvd($codigo);
        if (strlen($cvd) !== 8 || !NumeracionService::migracionOk()) return null;
        $exp = Database::valor("SELECT referencia FROM sgd_verificacion WHERE cvd = ? AND tipo = 'EXPEDIENTE' LIMIT 1", array($cvd));
        return $exp === false ? null : (string)$exp;
    }

    /** Datos del cargo de un expediente externo, o null si no existe. */
    public static function externo($numexp)
    {
        $filas = Database::filas("SELECT date_format(rd.fecha_registro, '%d/%m/%Y'), date_format(rd.fecha_registro, '%H:%i:%s'),
                                         rd.nro_exp, rd.folios, d.nombre_documento, rd.numero_docu, tt.nombre,
                                         TRIM(CONCAT(COALESCE(r.apellidos,''), ' ', COALESCE(r.nombres,''))),
                                         rd.asunto, o.nombre, COALESCE(rd.recepcion_web, 0), rd.idpersonal
                                    FROM registro_documentos rd
                                    LEFT JOIN documentos d  ON d.iddocumentos = rd.iddocumento
                                    LEFT JOIN remitente r   ON r.idremitente = rd.idremitente
                                    LEFT JOIN tipo_tram tt  ON tt.idtipo_tram = rd.idtipo_tramite
                                    LEFT JOIN oficinas o    ON o.idoficinas = rd.idoficina
                                   WHERE rd.nro_exp = ? ORDER BY rd.idregistro_documentos", array($numexp), PDO::FETCH_NUM);
        if (!$filas) return null;
        $d = $filas[0];
        $oficinas = array();
        foreach ($filas as $f) {
            if (!empty($f[9])) $oficinas[] = $f[9];
        }
        $oficinas = array_values(array_unique($oficinas));
        $virtual = (int)$d[10] === 1;
        $registro = $virtual ? 'Ciudadano, por Mesa de Partes Virtual' : 'Mesa de Partes';
        if (!$virtual && !empty($d[11])) {
            $p = Database::filas("SELECT TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),
                                         (SELECT o.nombre FROM responsables rs JOIN oficinas o ON o.idoficinas = rs.idoficina
                                           WHERE rs.idpersonal = p.idpersonal ORDER BY rs.estado DESC, rs.idresponsables LIMIT 1)
                                    FROM personal p WHERE p.idpersonal = ?", array((int)$d[11]), PDO::FETCH_NUM);
            if ($p && trim((string)$p[0][0]) !== '') {
                $registro = trim($p[0][0]) . (!empty($p[0][1]) ? ' · ' . $p[0][1] : '');
            }
        }
        return array(
            'fecha' => $d[0], 'hora' => $d[1], 'exp' => (string)$d[2], 'folios' => (string)$d[3],
            'tipo' => (string)$d[4], 'numero' => trim((string)$d[5]), 'tramite' => (string)$d[6],
            'remitente' => (string)$d[7], 'asunto' => (string)$d[8],
            'derivar' => $oficinas ? implode(' / ', $oficinas) : 'Por asignar',
            'canal' => $virtual ? 'Mesa de Partes Virtual' : 'Ventanilla',
            'registro' => $registro, 'cvd' => ArchivoService::cvd('EXPEDIENTE', $d[2]),
        );
    }

    /**
     * Movimiento para el cargo interno: [fecha, hora, reg. de la oficina, folio, documento, número,
     * trámite, asunto, OFICINA, tipo CVD (EMITIDO|EXPEDIENTE), referencia]. Dos consultas
     * separadas (no UNION) por la mezcla de collations tras utf8mb4.
     */
    public static function interno($idmov)
    {
        $a = Database::filas("select date_format(m.fecha_hora, '%d/%m/%Y'), date_format(m.fecha_hora, '%H:%i:%s'),
                                     m.num_exp, m.folio, documentos.nombre_documento, de.numero,
                                     'INTERNO', de.asunto, upper(o.nombre), 'EMITIDO', de.iddocs_emitidos
                                from movimientos m
                               inner join docs_emitidos de on m.iddocs_emitidos = de.iddocs_emitidos
                               inner join responsables r on m.idresponsable = r.idresponsables
                               inner join oficinas o on r.idoficina = o.idoficinas
                                left join documentos on de.iddocumento = documentos.iddocumentos
                               where m.idMovimientos = ?", array($idmov), PDO::FETCH_NUM);
        if ($a) return $a[0];
        $b = Database::filas("select date_format(m.fecha_hora, '%d/%m/%Y'), date_format(m.fecha_hora, '%H:%i:%s'),
                                     m.num_exp, m.folio, documentos.nombre_documento, rd.numero_docu,
                                     coalesce(tipo_tram.nombre, 'EXTERNO'), rd.asunto, upper(o.nombre), 'EXPEDIENTE', rd.nro_exp
                                from movimientos m
                               inner join registro_documentos rd on m.idregdocs = rd.idregistro_documentos
                               inner join responsables r on m.idresponsable = r.idresponsables
                               inner join oficinas o on r.idoficina = o.idoficinas
                                left join documentos on rd.iddocumento = documentos.iddocumentos
                                left join tipo_tram on rd.idtipo_tramite = tipo_tram.idtipo_tram
                               where m.idMovimientos = ?", array($idmov), PDO::FETCH_NUM);
        return $b ? $b[0] : null;
    }
}
