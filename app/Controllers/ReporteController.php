<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\ContextoService;
use App\Services\PdfService;
use PDO;

/**
 * Reportes (mismas consultas y diseño que sisgedov2):
 *   reportes/consulta-interna   ?codigo_doc= (interno) | ?num_exp= (externo)
 *   reportes/estados            estados de atención de la oficina (?desde, hasta, estado, q; export=csv)
 *   reportes/cuaderno-externo   registro de mesa de partes (+ /pdf)
 *   reportes/cuaderno-interno   documentos de la oficina (+ /pdf)
 */
class ReporteController extends Controller
{
    private function fecha($s, $porDefecto)
    {
        return preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)$s) ? $s : $porDefecto;
    }

    /** Movimientos de un documento (recorrido): [fecha, hora, oficina, acción, estado, detalles, evento, orden] */
    private function movimientos($columna, array $ids)
    {
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        return Database::filas("select date_format(m.fecha_hora,'%d/%m/%Y'), date_format(m.fecha_hora,'%h:%i %p'),
                                       coalesce(ofi.nombre, ofid.nombre, ''), coalesce(acc.nombre_accion,'--'),
                                       coalesce(ed.nombre,'--'), coalesce(m.detalles, m.detalle_derivado, ''),
                                       case when m.idmovimientoant is null then 'Origen' when m.estado=0 then 'Ubicación actual' else 'Movimiento' end,
                                       m.fecha_hora
                                  from movimientos m
                                  left join responsables r on m.idresponsable=r.idresponsables
                                  left join oficinas ofi on r.idoficina=ofi.idoficinas
                                  left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                  left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                  left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                                 where m.$columna in ($marcas) order by m.fecha_hora asc, m.idMovimientos asc", array_map('intval', $ids), PDO::FETCH_NUM);
    }

    public function consultaInterna()
    {
        $codigoDoc = (int)$this->get('codigo_doc', '0');
        $numExp = $this->get('num_exp');
        $busco = $codigoDoc > 0 || $numExp !== '';
        $cab = null;
        $eventos = $sugExternos = $sugInternos = array();
        try {
            if ($codigoDoc > 0) {
                $d = Database::filas("select de.iddocs_emitidos, documentos.nombre_documento, de.numero, de.asunto, coalesce(o.nombre,''),
                                             date_format(de.fecha,'%d/%m/%Y %h:%i %p'), de.fecha, coalesce(de.detalles,'')
                                        from docs_emitidos de inner join documentos on de.iddocumento=documentos.iddocumentos
                                        left join oficinas o on de.idoficina_registro=o.idoficinas where de.iddocs_emitidos=?", array($codigoDoc), PDO::FETCH_NUM);
                if ($d) {
                    $d = $d[0];
                    $cab = array('INTERNO', $d[1], 'N° ' . $d[2], $d[3], $d[4], $d[5]);
                    // (igual que sisgedov2: el registro se muestra con +2 horas)
                    $eventos[] = array(date('d/m/Y', strtotime($d[6] . ' +2 hours')), date('h:i A', strtotime($d[6] . ' +2 hours')), $d[4], 'Registro', 'Emitido', $d[7], 'Registro', $d[6]);
                    $eventos = array_merge($eventos, $this->movimientos('iddocs_emitidos', array($codigoDoc)));
                }
            } elseif ($numExp !== '') {
                $regs = Database::filas("select rd.idregistro_documentos, documentos.nombre_documento, rd.nro_exp, rd.asunto,
                                                concat(remitente.apellidos,', ',remitente.nombres), date_format(rd.fecha_registro,'%d/%m/%Y %h:%i %p'),
                                                rd.fecha_registro, coalesce(rd.observacion,''), coalesce(rd.recepcion_web,0)
                                           from registro_documentos rd
                                          inner join documentos on rd.iddocumento=documentos.iddocumentos
                                          inner join remitente on rd.idremitente=remitente.idremitente
                                          where rd.nro_exp=? order by rd.idregistro_documentos asc", array($numExp), PDO::FETCH_NUM);
                if ($regs) {
                    $d = $regs[0];
                    $cab = array('EXTERNO', $d[1], 'Exp. ' . $d[2], $d[3], 'Remitente: ' . $d[4], $d[5]);
                    $eventos[] = array(date('d/m/Y', strtotime($d[6] . ' +2 hours')), date('h:i A', strtotime($d[6] . ' +2 hours')),
                        (int)$d[8] === 1 ? 'Mesa de Partes Virtual' : 'Mesa de Partes', 'Registro', 'Registrado', $d[7], 'Registro', $d[6]);
                    $eventos = array_merge($eventos, $this->movimientos('idregdocs', array_map(function ($r) { return $r[0]; }, $regs)));
                }
            } else {
                $sugExternos = Database::filas("select rd.nro_exp, documentos.nombre_documento, rd.asunto, date_format(rd.fecha_registro,'%d/%m/%Y')
                                                  from registro_documentos rd inner join documentos on rd.iddocumento=documentos.iddocumentos
                                                 order by rd.fecha_registro desc limit 5", array(), PDO::FETCH_NUM);
                $sugInternos = Database::filas("select de.iddocs_emitidos, documentos.nombre_documento, de.asunto, date_format(de.fecha,'%d/%m/%Y')
                                                  from docs_emitidos de inner join documentos on de.iddocumento=documentos.iddocumentos
                                                 order by de.fecha desc limit 5", array(), PDO::FETCH_NUM);
            }
        } catch (\Exception $e) {
            error_log('consulta interna: ' . $e->getMessage());
        }
        $this->vista('reportes/consulta_interna', compact('codigoDoc', 'numExp', 'busco', 'cab', 'eventos', 'sugExternos', 'sugInternos') + array(
            'titulo' => 'Consulta Interna',
            'cssExtra' => array('assets/css/consulta_interna.css'),
            'jsExtra' => array('assets/js/consulta_interna.js'),
            'bodyData' => array('urlBase' => url('reportes/consulta-interna')),
        ));
    }

    public function estados()
    {
        $hoy = date('Y-m-d');
        $desdeFiltro = $this->fecha($this->get('desde'), '');
        $hastaFiltro = $this->fecha($this->get('hasta'), '');
        $estadoFiltro = (int)$this->get('estado', '0');
        $qFiltro = $this->get('q');
        $desdeSql = $desdeFiltro ?: date('Y-m-d', strtotime('-30 days'));
        $hastaSql = $hastaFiltro ?: $hoy;
        $idoficina = ContextoService::actual()['idoficina'];
        $estados = $filas = $porEstado = array();
        try {
            $estados = Database::filas("SELECT idestado_documento, nombre FROM estado_documento ORDER BY idestado_documento", array(), PDO::FETCH_NUM);
            $cond = $estadoFiltro > 0 ? " and m.idestadodocs = " . $estadoFiltro : "";
            $base = "where m.idoficina_derivar=? and (m.idmovimientoant is null or m.estado=1) and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)";
            $p = array($idoficina, $desdeSql, $hastaSql);
            $filas = array_merge(
                Database::filas("select m.idMovimientos, rd.nro_exp, documentos.nombre_documento, rd.asunto, coalesce(ed.nombre,'--'),
                                        date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p'), m.fecha_hora, 'EXTERNO'
                                   from movimientos m inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
                                  inner join documentos on rd.iddocumento=documentos.iddocumentos
                                   left join estado_documento ed on m.idestadodocs=ed.idestado_documento $base$cond", $p, PDO::FETCH_NUM),
                Database::filas("select m.idMovimientos, cast(de.iddocs_emitidos as char), documentos.nombre_documento, de.asunto, coalesce(ed.nombre,'--'),
                                        date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p'), m.fecha_hora, 'INTERNO'
                                   from movimientos m inner join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
                                  inner join documentos on de.iddocumento=documentos.iddocumentos
                                   left join estado_documento ed on m.idestadodocs=ed.idestado_documento $base$cond", $p, PDO::FETCH_NUM));
            usort($filas, function ($a, $b) { return strcmp((string)$b[6], (string)$a[6]); });
            $porEstado = Database::filas("select coalesce(ed.nombre,'Sin estado'), count(*) from movimientos m
                                            left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                                          $base group by ed.nombre order by count(*) desc", $p, PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('estados: ' . $e->getMessage());
        }
        if ($qFiltro !== '') {
            $qn = mb_strtolower($qFiltro, 'UTF-8');
            $filas = array_values(array_filter($filas, function ($r) use ($qn) {
                return strpos(mb_strtolower(implode(' ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, $r)), 'UTF-8'), $qn) !== false;
            }));
        }
        if ($this->get('export') === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="estados_atencion_' . date('Ymd_His') . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, array('Expediente', 'Documento', 'Tramite', 'Asunto', 'Estado', 'Fecha'), ';', '"', '\\');
            foreach ($filas as $r) fputcsv($out, array($r[1], $r[2], $r[7], $r[3], $r[4], $r[5]), ';', '"', '\\');
            fclose($out);
            exit;
        }
        $this->vista('reportes/estados', compact('hoy', 'desdeFiltro', 'hastaFiltro', 'estadoFiltro', 'qFiltro', 'desdeSql', 'hastaSql', 'estados', 'filas', 'porEstado') + array(
            'titulo' => 'Estados de Atención', 'claseBody' => 'v-reporte-estados-documento',
            'cssExtra' => array('assets/css/v_vistas.css'), 'jsExtra' => array('assets/js/estados_atencion.js'),
        ));
    }

    public function cuadernoExterno()
    {
        $hoy = date('Y-m-d');
        $desdeSel = $this->fecha($this->get('desde'), $hoy);
        $hastaSel = $this->fecha($this->get('hasta'), $hoy);
        $regHoy = $regMes = $enRango = 0;
        $vista = array();
        try {
            $regHoy = (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE fecha_registro >= curdate() and fecha_registro < curdate() + interval 1 day");
            $regMes = (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE fecha_registro >= date_format(now(),'%Y-%m-01') and fecha_registro < date_format(now(),'%Y-%m-01') + interval 1 month");
            $enRango = (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE fecha_registro >= ? and fecha_registro < date_add(?, interval 1 day)", array($desdeSel, $hastaSel));
            $vista = Database::filas("SELECT rd.nro_exp, documentos.nombre_documento, CONCAT(remitente.apellidos,', ',remitente.nombres), rd.asunto,
                                             COALESCE(o.nombre,''), date_format(rd.fecha_registro,'%d/%m/%Y %h:%i %p')
                                        FROM registro_documentos rd
                                       INNER JOIN documentos ON rd.iddocumento=documentos.iddocumentos
                                       INNER JOIN remitente ON rd.idremitente=remitente.idremitente
                                        LEFT JOIN oficinas o ON rd.idoficina=o.idoficinas
                                       WHERE rd.fecha_registro >= ? and rd.fecha_registro < date_add(?, interval 1 day)
                                       ORDER BY rd.fecha_registro DESC LIMIT 100", array($desdeSel, $hastaSel), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('cuaderno externo: ' . $e->getMessage());
        }
        $this->vista('reportes/cuaderno_externo', compact('hoy', 'desdeSel', 'hastaSel', 'regHoy', 'regMes', 'enRango', 'vista') + array(
            'titulo' => 'Cuaderno Externo', 'claseBody' => 'v-reporte-cuadernoexterno',
            'cssExtra' => array('assets/css/v_vistas.css'), 'jsExtra' => array('assets/js/cuaderno_externo.js'),
            'bodyData' => array('urlPdf' => url('reportes/cuaderno-externo/pdf')),
        ));
    }

    public function cuadernoInterno()
    {
        $hoy = date('Y-m-d');
        $desdeSel = $this->fecha($this->get('desde'), $hoy);
        $hastaSel = $this->fecha($this->get('hasta'), $hoy);
        $idoficina = ContextoService::actual()['idoficina'];
        $emiHoy = $emiMes = $enRango = 0;
        $vista = array();
        try {
            $emiHoy = (int)Database::valor("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro=? AND fecha >= curdate() and fecha < curdate() + interval 1 day", array($idoficina));
            $emiMes = (int)Database::valor("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro=? AND fecha >= date_format(now(),'%Y-%m-01') and fecha < date_format(now(),'%Y-%m-01') + interval 1 month", array($idoficina));
            $enRango = (int)Database::valor("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro=? AND fecha >= ? and fecha < date_add(?, interval 1 day)", array($idoficina, $desdeSel, $hastaSel));
            $vista = Database::filas("SELECT de.iddocs_emitidos, documentos.nombre_documento, de.numero, de.asunto,
                                             COALESCE((SELECT o2.nombre FROM oficinas o2 WHERE o2.idoficinas=de.idoficina),'—'),
                                             date_format(de.fecha,'%d/%m/%Y %h:%i %p'), de.estado
                                        FROM docs_emitidos de INNER JOIN documentos ON de.iddocumento=documentos.iddocumentos
                                       WHERE de.idoficina_registro=? AND de.fecha >= ? and de.fecha < date_add(?, interval 1 day)
                                       ORDER BY de.fecha DESC LIMIT 100", array($idoficina, $desdeSel, $hastaSel), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('cuaderno interno: ' . $e->getMessage());
        }
        $this->vista('reportes/cuaderno_interno', compact('hoy', 'desdeSel', 'hastaSel', 'emiHoy', 'emiMes', 'enRango', 'vista') + array(
            'titulo' => 'Cuaderno Interno', 'claseBody' => 'v-reporte-cuadernointerno',
            'cssExtra' => array('assets/css/v_vistas.css'), 'jsExtra' => array('assets/js/cuaderno_interno.js'),
            'bodyData' => array('idoficina' => $idoficina, 'urlPdf' => url('reportes/cuaderno-interno/pdf')),
        ));
    }

    /* ---------------- PDF de los cuadernos (mismo formato que tcpdf/examples/reporte_diario*.php) ---------------- */

    private function pdf($titulo, $html)
    {
        PdfService::cargar();
        PdfService::limpiarSalida();
        $pdf = new \App\Pdf\ReporteDiario(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('SISGEDO');
        $pdf->SetAuthor(SISGEDO_ENTIDAD);
        $pdf->SetTitle($titulo);
        $pdf->SetSubject('Sistema de Gestión Documentario');
        $pdf->SetKeywords('SISGEDO, Trámite Documentario, ' . SISGEDO_MUNI_LUGAR);
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('times', 'B', 12);
        $pdf->AddPage('L', 'A4');
        $pdf->Write(0, 'REGISTRO DE DOCUMENTOS', '', 0, 'C', true, 0, false, false, 0);
        $pdf->SetFont('times', '', 8);
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('Reporte_diario.pdf', 'I');
        exit;
    }

    private static function tabla(array $cabecera, array $filas)
    {
        $html = '<table border="1"><thead><tr style="background-color:#B4764B;">';
        foreach ($cabecera as $c) $html .= '<th width="' . $c[1] . '" align="center"><b>' . h($c[0]) . '</b></th>';
        $html .= '</tr></thead>';
        foreach ($filas as $f) {
            $html .= '<tr>';
            // Sin volver a codificar entidades ya guardadas en datos antiguos (ej. "J&amp;C"): se ven igual que en sisgedov2
            foreach ($cabecera as $i => $c) $html .= '<td width="' . $c[1] . '" align="' . $c[2] . '">' . htmlspecialchars((string)$f[$i], ENT_QUOTES, 'UTF-8', false) . '</td>';
            $html .= '</tr>';
        }
        return $html . '</table>';
    }

    public function pdfExterno()
    {
        $ini = $this->fecha($this->get('f_inicio'), date('Y-m-d'));
        $fin = $this->fecha($this->get('f_fin'), date('Y-m-d'));
        $filas = array();
        try {
            foreach (Database::filas("select rd.nro_exp, concat(remitente.apellidos,', ',remitente.nombres), documentos.nombre_documento, rd.numero_docu, rd.asunto,
                                             tipo_tram.nombre, rd.observacion, rd.folios, date_format(rd.fecha_registro,'%d/%m/%y %h:%i %p'), oficinas.nombre,
                                             (select date_format(min(movimientos.fecha_hora),'%d/%m/%y %h:%i %p') from movimientos where idregdocs=rd.idregistro_documentos)
                                        from registro_documentos rd
                                        left join documentos on rd.iddocumento=documentos.iddocumentos
                                       inner join remitente on rd.idremitente=remitente.idremitente
                                       inner join tipo_tram on rd.idtipo_tramite=tipo_tram.idtipo_tram
                                       inner join oficinas on rd.idoficina=oficinas.idoficinas
                                       where rd.fecha_registro >= ? and rd.fecha_registro < date_add(?, interval 1 day)
                                       order by rd.nro_exp asc", array($ini, $fin), PDO::FETCH_NUM) as $r) {
                $filas[] = $r;
            }
        } catch (\Exception $e) {
            error_log('reporte diario: ' . $e->getMessage());
        }
        $this->pdf('Reporte diario', self::tabla(array(
            array('Exp.', 45, 'center'), array('Remitente', 160, 'left'), array('Tipo Doc.', 80, 'center'), array('N° Doc.', 40, 'center'),
            array('Asunto', 200, 'justify'), array('Trámite', 100, 'left'), array('Observación', 90, 'center'), array('Folios', 35, 'center'),
            array('Fecha Registro', 50, 'center'), array('Oficina de atención', 120, 'center'), array('Fecha Recepción', 50, 'center'),
        ), $filas));
    }

    public function pdfInterno()
    {
        $ini = $this->fecha($this->get('f_inicio'), date('Y-m-d'));
        $fin = $this->fecha($this->get('f_fin'), date('Y-m-d'));
        // La oficina es la de la sesión (antes llegaba por la dirección y se podía pedir la de otra oficina)
        $ofi = Auth::esAdmin() && (int)$this->get('idoficina', '0') > 0 ? (int)$this->get('idoficina') : ContextoService::actual()['idoficina'];
        $filas = array();
        $ext = "select registro_documentos.nro_exp, m.num_exp, documentos.nombre_documento, registro_documentos.numero_docu, registro_documentos.asunto, m.detalles,
                       'Externo', aciones_documento.nombre_accion,
                       if(m.idmovimientoant is null, 'Secretaria General',
                          (select o.nombre from movimientos ma inner join responsables r on ma.idresponsable=r.idresponsables
                             inner join oficinas o on ma.idoficina_derivar=o.idoficinas where ma.idMovimientos=m.idmovimientoant)),
                       date_format(m.fecha_hora,'%d/%m/%y %h:%i %p'), m.fecha_hora
                  from movimientos m
                 inner join registro_documentos on m.idregdocs=registro_documentos.idregistro_documentos
                 inner join oficinas on m.idoficina_derivar=oficinas.idoficinas
                 inner join aciones_documento on m.idacciondocs=aciones_documento.idaciones_documento
                 inner join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
                 inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                 where oficinas.idoficinas=? and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)";
        $int = "select docs_emitidos.iddocs_emitidos, m.num_exp, documentos.nombre_documento, docs_emitidos.numero, docs_emitidos.asunto, m.detalles,
                       'Interno', aciones_documento.nombre_accion,
                       if(m.idmovimientoant is null,
                          (select o.nombre from movimientos mm inner join docs_emitidos d2 on mm.iddocs_emitidos=d2.iddocs_emitidos
                             inner join responsables r on d2.idresponsable=r.idresponsables inner join oficinas o on mm.idoficina_derivar=o.idoficinas
                            where mm.idMovimientos=m.idMovimientos),
                          (select o.nombre from movimientos ma inner join responsables r on ma.idresponsable=r.idresponsables
                             inner join oficinas o on r.idoficina=o.idoficinas where ma.idMovimientos=m.idmovimientoant)),
                       date_format(m.fecha_hora,'%d/%m/%y %h:%i %p'), m.fecha_hora
                  from movimientos m
                 inner join docs_emitidos on m.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                 inner join responsables on m.idresponsable=responsables.idresponsables
                 inner join oficinas on m.idoficina_derivar=oficinas.idoficinas
                 inner join aciones_documento on m.idacciondocs=aciones_documento.idaciones_documento
                 inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                 where oficinas.idoficinas=? and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)";
        try {
            // Dos consultas unidas en PHP (un UNION falla por la mezcla de collations tras utf8mb4)
            $filas = array_merge(Database::filas($ext, array($ofi, $ini, $fin), PDO::FETCH_NUM), Database::filas($int, array($ofi, $ini, $fin), PDO::FETCH_NUM));
            usort($filas, function ($a, $b) { return strcmp((string)$a[10], (string)$b[10]); });
        } catch (\Exception $e) {
            error_log('reporte diario interno: ' . $e->getMessage());
        }
        $this->pdf('Reporte diario', self::tabla(array(
            array('Cod. SGD.', 45, 'center'), array('Exp.', 45, 'center'), array('Tipo Doc.', 90, 'left'), array('N° Doc.', 90, 'left'),
            array('Asunto', 180, 'justify'), array('Detalles', 250, 'justify'), array('Tipo de Trámite', 50, 'center'), array('Acción', 80, 'left'),
            array('Of. de Origen', 80, 'center'), array('Fecha y Hora', 50, 'center'),
        ), $filas));
    }
}
