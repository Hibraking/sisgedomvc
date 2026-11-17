<?php
namespace App\Models;

use App\Core\Database;

/**
 * Datos de la pantalla de Inicio (mismas consultas de sisgedov2/index.php).
 * "Recibido" = recepción directa (primer movimiento) o derivación ya recibida.
 */
class InicioModel
{
    /** Indicadores de hoy. */
    public static function hoy($idoficina)
    {
        $o = array((int)$idoficina);
        return array(
            'por_recepcionar' =>
                (int)Database::valor("SELECT COUNT(*) FROM movimientos WHERE idoficina_derivar = ? AND idmovimientoant IS NOT NULL AND estado = 0", $o)
              + (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE estado_recepcion = 0 AND idoficina = ?", $o)
              + (int)Database::valor("SELECT COUNT(*) FROM docs_emitidos WHERE estado = 0 AND idoficina = ?", $o),
            'recepcionados' => (int)Database::valor("SELECT COUNT(*) FROM movimientos WHERE idoficina_derivar = ?
                    AND (idmovimientoant IS NULL OR estado = 1) AND fecha_hora >= CURDATE() AND fecha_hora < CURDATE() + INTERVAL 1 DAY", $o),
            'derivados' => (int)Database::valor("SELECT COUNT(*) FROM movimientos m
                    JOIN movimientos ma ON m.idmovimientoant = ma.idMovimientos
                    JOIN responsables r ON ma.idresponsable = r.idresponsables
                   WHERE r.idoficina = ? AND m.fecha_hora >= CURDATE() AND m.fecha_hora < CURDATE() + INTERVAL 1 DAY", $o),
            'mesa_partes' => (int)Database::valor("SELECT COUNT(*) FROM registro_documentos
                   WHERE fecha_registro >= CURDATE() AND fecha_registro < CURDATE() + INTERVAL 1 DAY"),
        );
    }

    /** Resumen del mes (panel derecho). */
    public static function mes($idoficina)
    {
        $o = array((int)$idoficina);
        $desde = "DATE_FORMAT(NOW(),'%Y-%m-01')";
        return array(
            'recepcionados' => (int)Database::valor("SELECT COUNT(*) FROM movimientos WHERE idoficina_derivar = ?
                    AND (idmovimientoant IS NULL OR estado = 1) AND fecha_hora >= $desde AND fecha_hora < $desde + INTERVAL 1 MONTH", $o),
            'derivados' => (int)Database::valor("SELECT COUNT(*) FROM movimientos m
                    JOIN movimientos ma ON m.idmovimientoant = ma.idMovimientos
                    JOIN responsables r ON ma.idresponsable = r.idresponsables
                   WHERE r.idoficina = ? AND m.fecha_hora >= $desde AND m.fecha_hora < $desde + INTERVAL 1 MONTH", $o),
            'mesa_partes' => (int)Database::valor("SELECT COUNT(*) FROM registro_documentos
                   WHERE fecha_registro >= $desde AND fecha_registro < $desde + INTERVAL 1 MONTH"),
            'archivados' => (int)Database::valor("SELECT COUNT(*) FROM docs_archivados da
                    JOIN archivador a ON da.idarchivador = a.idarchivador
                   WHERE a.idoficina = ? AND da.fecha_hora >= $desde AND da.fecha_hora < $desde + INTERVAL 1 MONTH", $o),
        );
    }

    /**
     * Últimos 6 documentos por recepcionar: [tipo doc, nro/exp, asunto, origen, fecha].
     * Tres consultas separadas unidas en PHP (un UNION falla por collations mixtas).
     */
    public static function ultimosPendientes($idoficina, $limite = 6)
    {
        $o = array((int)$idoficina);
        $lista = Database::filas("SELECT d.nombre_documento, rd.nro_exp, rd.asunto, 'MESA DE PARTES', rd.fecha_registro
                                    FROM registro_documentos rd JOIN documentos d ON rd.iddocumento = d.iddocumentos
                                   WHERE rd.estado_recepcion = 0 AND rd.idoficina = ?
                                   ORDER BY rd.fecha_registro DESC LIMIT $limite", $o, \PDO::FETCH_NUM);
        $lista = array_merge($lista, Database::filas("SELECT d.nombre_documento, rd.nro_exp, rd.asunto,
                       COALESCE((SELECT o2.nombre FROM movimientos m2 JOIN responsables r2 ON m2.idresponsable = r2.idresponsables
                                   JOIN oficinas o2 ON r2.idoficina = o2.idoficinas WHERE m2.idMovimientos = m.idmovimientoant), 'Otra oficina'),
                       m.fecha_hora
                  FROM movimientos m
                  JOIN registro_documentos rd ON m.idregdocs = rd.idregistro_documentos
                  JOIN documentos d ON rd.iddocumento = d.iddocumentos
                 WHERE m.idoficina_derivar = ? AND m.idmovimientoant IS NOT NULL AND m.estado = 0
                 ORDER BY m.fecha_hora DESC LIMIT $limite", $o, \PDO::FETCH_NUM));
        $lista = array_merge($lista, Database::filas("SELECT d.nombre_documento, CAST(de.iddocs_emitidos AS CHAR), de.asunto,
                       COALESCE((SELECT o3.nombre FROM responsables r3 JOIN oficinas o3 ON r3.idoficina = o3.idoficinas
                                  WHERE r3.idresponsables = de.idresponsable), 'Oficina interna'),
                       de.fecha
                  FROM docs_emitidos de JOIN documentos d ON de.iddocumento = d.iddocumentos
                 WHERE de.estado = 0 AND de.idoficina = ?
                 ORDER BY de.fecha DESC LIMIT $limite", $o, \PDO::FETCH_NUM));
        usort($lista, function ($a, $b) {
            return strcmp((string)$b[4], (string)$a[4]);
        });
        return array_slice($lista, 0, $limite);
    }

    /** Movimientos hacia la oficina en los últimos 7 días: [[día, cantidad, dd/mm], ...] */
    public static function actividadSemana($idoficina)
    {
        $mapa = array();
        foreach (Database::filas("SELECT DATE(fecha_hora), COUNT(*) FROM movimientos
                                   WHERE idoficina_derivar = ? AND fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                                   GROUP BY DATE(fecha_hora)", array((int)$idoficina), \PDO::FETCH_NUM) as $f) {
            $mapa[$f[0]] = (int)$f[1];
        }
        $dias = array('Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom');
        $out = array();
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $out[] = array($dias[date('D', strtotime($d))], isset($mapa[$d]) ? $mapa[$d] : 0, date('d/m', strtotime($d)));
        }
        return $out;
    }
}
