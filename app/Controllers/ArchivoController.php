<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ArchivoModel;
use App\Models\RecepcionModel;
use App\Services\ArchivadorService;
use App\Services\ContextoService;
use App\Services\PdfService;
use App\Services\RecepcionFormato as F;
use App\Services\RecepcionService;

/**
 * Archivo de documentos de la oficina.
 *   GET  archivo          bandejas ?bandeja=porarchivar|archivados (filtros q, desde, hasta, archivador, sort,
 *                         per_page, page; export=csv|xls|pdf)
 *   POST archivo/accion   carga_estados · insert_archivar (tramitado) · insertar (emitido) · Codigo='' (nuevo archivador)
 */
class ArchivoController extends Controller
{
    const DEFECTOS = array('q' => '', 'desde' => '', 'hasta' => '', 'bandeja' => 'porarchivar', 'archivador' => '0', 'sort' => 'recentes', 'per_page' => '25');

    public function index()
    {
        $f = array();
        foreach (self::DEFECTOS as $k => $def) $f[$k] = $this->get($k, $def);
        $bandeja = $f['bandeja'] === 'archivados' ? 'archivados' : 'porarchivar';
        $f['bandeja'] = $bandeja;
        $archFiltro = (int)$f['archivador'];
        $f['archivador'] = (string)$archFiltro;
        $porPagina = max(10, min(100, (int)$f['per_page']));
        $hoy = date('Y-m-d');
        $fecha = function ($s) { return preg_match('~^\d{4}-\d{2}-\d{2}$~', $s) ? $s : ''; };
        $desdeSql = $fecha($f['desde']) ?: date('Y-m-d', strtotime('-30 days'));
        $hastaSql = $fecha($f['hasta']) ?: $hoy;
        $idoficina = ContextoService::actual()['idoficina'];

        $datos = $bandeja === 'archivados' ? ArchivoModel::archivados($idoficina) : ArchivoModel::porArchivar($idoficina, $desdeSql, $hastaSql);
        if ($bandeja === 'archivados' && $archFiltro > 0) {
            $datos = array_values(array_filter($datos, function ($r) use ($archFiltro) { return (int)$r[10] === $archFiltro; }));
        }
        $q = F::norm($f['q']);
        $filtrados = array_values(array_filter($datos, function ($r) use ($f, $q) {
            if ($q !== '' && strpos(F::norm(implode(' ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, $r))), $q) === false) return false;
            $ts = isset($r[7]) ? strtotime($r[7]) : false;
            if ($f['desde'] !== '' && ($ts === false || $ts < strtotime($f['desde'] . ' 00:00:00'))) return false;
            if ($f['hasta'] !== '' && ($ts === false || $ts > strtotime($f['hasta'] . ' 23:59:59'))) return false;
            return true;
        }));
        if ($f['sort'] === 'antiguos') {
            usort($filtrados, function ($a, $b) { return strcmp((string)$a[7], (string)$b[7]); });
        }
        $exp = $this->get('export');
        if (in_array($exp, array('csv', 'xls', 'pdf'), true)) {
            $this->exportar($exp, $filtrados, $bandeja);
        }

        $total = count($filtrados);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, (int)$this->get('page', '1')), $totalPaginas);
        $inicio = ($pagina - 1) * $porPagina;
        $urlAdj = url('adjunto');
        $filas = array_map(function ($r) use ($urlAdj) {
            if (preg_match('~^(reg|emi):(.+)$~s', (string)$r[8], $m)) $r[8] = $urlAdj . '?c=' . $m[1] . '&f=' . rawurlencode($m[2]);
            return $r;
        }, array_slice($filtrados, $inicio, $porPagina));
        $cont = ArchivoModel::contadores($idoficina);

        $this->vista('archivo/index', array(
            'titulo' => 'Archivo de Documentos',
            'cssExtra' => array('assets/css/v_archivar_docs.css'),
            'jsExtra' => array('assets/js/archivo.js'),
            'bodyData' => array('idoficina' => $idoficina, 'endpoint' => url('archivo/accion')),
            'qFiltro' => $f['q'], 'desdeFiltro' => $f['desde'], 'hastaFiltro' => $f['hasta'], 'bandeja' => $bandeja,
            'archFiltro' => $archFiltro, 'sortFiltro' => $f['sort'], 'porPagina' => $porPagina, 'hoy' => $hoy,
            'desdeSql' => $desdeSql, 'hastaSql' => $hastaSql,
            'datos' => $datos, 'datosPagina' => $filas, 'first' => isset($filas[0]) ? $filas[0] : null,
            'totalTabla' => $total, 'paginaActual' => $pagina, 'totalPaginas' => $totalPaginas, 'inicioPagina' => $inicio,
            'archivadores' => ArchivoModel::archivadores($idoficina),
            'cont' => $cont,
            'porArchivar' => $cont['emitidos_sin_archivar'] + $cont['tramitados_sin_archivar'],
            'paginaUrl' => function ($n) use ($f) { return $this->direccion($f + array('page' => $n)); },
        ));
    }

    public function accion()
    {
        if (isset($_POST['carga_estados'])) {
            $this->json(RecepcionModel::estados());
        }
        header('Content-Type: text/plain; charset=utf-8');
        if (isset($_POST['insert_archivar'])) {          // tramitado: archiva y actualiza el estado del movimiento
            $srv = new RecepcionService();
            $ok = $srv->archivar((int)$this->post('idarchivador', 0), (int)$this->post('idmovimiento', 0), (string)$this->post('detalles'), (int)$this->post('idestadodoc', 0));
            exit($ok ? '' : 'ERROR_VALIDACION: ' . $srv->error);
        }
        $srv = new ArchivadorService();
        if (isset($_POST['insertar'])) {                 // emitido propio
            $ok = $srv->archivarEmitido((int)$this->post('idarchivador', 0), (int)$this->post('iddocs_emitidos', 0), (string)$this->post('detalles'));
            exit($ok ? '' : 'ERROR_VALIDACION: ' . $srv->error);
        }
        if (isset($_POST['Codigo']) && $this->post('Codigo') === '') {   // nuevo archivador (editar: Mantenimiento, etapa 6)
            $ok = $srv->crearArchivador($this->post('Nombre'), $this->post('Detalles'), $this->post('anio'));
            exit($ok ? '' : 'ERROR_VALIDACION: ' . $srv->error);
        }
        http_response_code(400);
        exit('ERROR_VALIDACION: Acción no reconocida.');
    }

    private function direccion(array $params)
    {
        $out = array();
        foreach ($params as $k => $v) {
            if ($k === 'page' ? (int)$v <= 1 : (!isset(self::DEFECTOS[$k]) || (string)$v === self::DEFECTOS[$k])) continue;
            $out[$k] = $v;
        }
        $qs = http_build_query($out);
        return url('archivo') . ($qs !== '' ? '?' . $qs : '');
    }

    private function exportar($fmt, array $filas, $bandeja)
    {
        $nombre = 'archivo_' . $bandeja . '_' . date('Ymd_His');
        $colClase = $bandeja === 'archivados' ? 'Archivador' : 'Clase';
        if ($fmt === 'csv' || $fmt === 'xls') {
            $cab = array($colClase, 'Documento', 'Numero/Exp', 'Asunto', 'Detalles', 'Fecha');
            $val = function ($r) { return array($r[1], $r[2], $r[3], $r[4], $r[5], $r[6]); };
            if ($fmt === 'csv') {
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $nombre . '.csv"');
                echo "\xEF\xBB\xBF";
                $out = fopen('php://output', 'w');
                fputcsv($out, $cab, ';', '"', '\\');
                foreach ($filas as $r) fputcsv($out, $val($r), ';', '"', '\\');
                fclose($out);
                exit;
            }
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nombre . '.xls"');
            echo "\xEF\xBB\xBF<table border=\"1\"><tr style=\"background:#e2e8f0;font-weight:bold\">";
            foreach ($cab as $c) echo '<th>' . h($c) . '</th>';
            echo '</tr>';
            foreach ($filas as $r) {
                echo '<tr>';
                foreach ($val($r) as $v) echo '<td>' . h($v) . '</td>';
                echo '</tr>';
            }
            exit('</table>');
        }
        PdfService::cargar();
        PdfService::limpiarSalida();
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('SISGEDO');
        $pdf->SetTitle('Archivo de documentos — ' . $bandeja);
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'SISGEDO — Archivo de documentos (' . ($bandeja === 'archivados' ? 'archivados' : 'por archivar') . ')', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($filas), 0, 1);
        $pdf->Ln(2);
        $html = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
            . '<th width="16%">' . h($colClase) . '</th><th width="16%">Documento</th><th width="12%">Numero/Exp</th><th width="26%">Asunto</th><th width="18%">Detalles</th><th width="12%">Fecha</th></tr></thead><tbody>';
        foreach ($filas as $r) {
            $html .= '<tr><td width="16%">' . h($r[1]) . '</td><td width="16%">' . h($r[2]) . '</td><td width="12%">' . h($r[3]) . '</td><td width="26%">' . h($r[4])
                . '</td><td width="18%">' . h($r[5]) . '</td><td width="12%">' . h($r[6]) . '</td></tr>';
        }
        $pdf->writeHTML($html . '</tbody></table>', true, false, true, false, '');
        $pdf->Output($nombre . '.pdf', 'I');
        exit;
    }
}
