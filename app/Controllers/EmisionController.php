<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\EmisionModel;
use App\Models\OficinaModel;
use App\Services\ArchivoService;
use App\Services\EmisionService;
use App\Services\FirmaService;
use App\Services\PdfService;
use App\Services\RecepcionFormato as F;

/**
 * Emisión de documentos de la oficina.
 *   GET  emision          listado (filtros q, desde, hasta, estado, sort, per_page, page; export=csv|xls|pdf)
 *   POST emision/accion   previsualizar_numero · Insertar · Modificar · Emilinar (mismas claves que sisgedov2)
 */
class EmisionController extends Controller
{
    const DEFECTOS = array('q' => '', 'desde' => '', 'hasta' => '', 'estado' => 'Todos', 'sort' => 'recentes', 'per_page' => '25');

    /** 'Recepcionado' | 'Sin destino' | 'Por recepcionar' */
    public static function estado($r)
    {
        if ((int)$r[6] === 1) return 'Recepcionado';
        if ((int)$r[11] === 0) return 'Sin destino';
        return 'Por recepcionar';
    }

    public function index()
    {
        $f = array();
        foreach (self::DEFECTOS as $k => $def) $f[$k] = $this->get($k, $def);
        $porPagina = max(10, min(100, (int)$f['per_page']));
        $hoy = date('Y-m-d');
        $desdeSql = preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['desde']) ? $f['desde'] : date('Y-m-d', strtotime('-30 days'));
        $hastaSql = preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['hasta']) ? $f['hasta'] : $hoy;
        $idoficina = Auth::idOficina();

        $datos = EmisionModel::emitidos($idoficina, $desdeSql, $hastaSql);
        $q = F::norm($f['q']);
        $filtrados = array_values(array_filter($datos, function ($r) use ($f, $q) {
            if ($f['estado'] !== 'Todos' && F::norm(self::estado($r)) !== F::norm($f['estado'])) return false;
            if ($q !== '' && strpos(F::norm(implode(' ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, $r))), $q) === false) return false;
            return true;
        }));
        if ($f['sort'] === 'antiguos') {
            usort($filtrados, function ($a, $b) { return strcmp((string)$a[9], (string)$b[9]); });
        }
        $exp = $this->get('export');
        if (in_array($exp, array('csv', 'xls', 'pdf'), true)) {
            $this->exportar($exp, $filtrados);
        }

        $total = count($filtrados);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, (int)$this->get('page', '1')), $totalPaginas);
        $inicio = ($pagina - 1) * $porPagina;
        $urlAdj = url('adjunto') . '?c=emi&f=';
        $filas = array_map(function ($r) use ($urlAdj) {
            $r[7] = $r[7] !== '#' ? $urlAdj . rawurlencode(substr($r[7], 4)) : '#';
            return $r;
        }, array_slice($filtrados, $inicio, $porPagina));

        // Código de verificación y firma de los documentos de esta página
        $cvdMap = array();
        foreach ($filas as $r) {
            if ($c = ArchivoService::cvd('EMITIDO', $r[0])) $cvdMap[$r[0]] = ArchivoService::formatoCvd($c);
        }
        $firmaActiva = FirmaService::activo();

        $this->vista('emision/index', array(
            'titulo' => 'Emisión de Documentos',
            'cssExtra' => array('assets/css/v_docs_emitidos.css'),
            'jsExtra' => array('assets/js/emision.js'),
            'bodyData' => array('idoficina' => $idoficina, 'endpoint' => url('emision/accion'), 'urlFirma' => url('firma'),
                                'urlVerificar' => url('verificar')),
            'qFiltro' => $f['q'], 'desdeFiltro' => $f['desde'], 'hastaFiltro' => $f['hasta'], 'estadoFiltro' => $f['estado'],
            'sortFiltro' => $f['sort'], 'porPagina' => $porPagina, 'hoy' => $hoy, 'desdeSql' => $desdeSql, 'hastaSql' => $hastaSql,
            'datos' => $datos, 'datosPagina' => $filas, 'first' => isset($filas[0]) ? $filas[0] : null,
            'totalTabla' => $total, 'paginaActual' => $pagina, 'totalPaginas' => $totalPaginas, 'inicioPagina' => $inicio,
            'emitidosVentana' => count($datos),
            'cont' => EmisionModel::contadores($idoficina),
            'catDocumentos' => EmisionModel::tiposDocumento(),
            'catOficinas' => OficinaModel::lista(),
            'cvdMap' => $cvdMap,
            'firmaMap' => EmisionModel::firmas(array_map(function ($r) { return $r[0]; }, $filas)),
            'firmaActiva' => $firmaActiva,
            'firmaJs' => FirmaService::config('js'),
            'urlV2' => rtrim(config('url_sisgedov2'), '/'),
            'paginaUrl' => function ($n) use ($f) { return $this->direccion($f + array('page' => $n)); },
        ));
    }

    public function accion()
    {
        $srv = new EmisionService();
        if (isset($_POST['previsualizar_numero'])) {
            $this->json($srv->previsualizar((int)$this->post('Documento', 0)));
        }
        header('Content-Type: text/plain; charset=utf-8');
        if (isset($_POST['Insertar'])) {
            $r = $srv->emitir($_POST, isset($_FILES['fichero']) ? $_FILES['fichero'] : null);
            exit($r !== false ? $r : 'ERROR_VALIDACION: ' . $srv->error);
        }
        if (isset($_POST['Modificar'])) {
            exit($srv->modificar((int)$this->post('Codigo', 0), $_POST) ? 'OK' : 'ERROR_VALIDACION: ' . $srv->error);
        }
        if (isset($_POST['Emilinar'])) {
            exit($srv->eliminar((int)$this->post('Codigo', 0)) ? 'OK' : 'ERROR_VALIDACION: ' . $srv->error);
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
        return url('emision') . ($qs !== '' ? '?' . $qs : '');
    }

    private function exportar($fmt, array $filas)
    {
        $nombre = 'emision_' . date('Ymd_His');
        if ($fmt === 'csv' || $fmt === 'xls') {
            $cab = array('Documento', 'Numero', 'Asunto', 'Detalles', 'Destino', 'Estado', 'Fecha');
            $val = function ($r) { return array($r[1], $r[2], $r[3], $r[4], $r[5], self::estado($r), $r[8]); };
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
        $pdf->SetTitle('Emisión de documentos');
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'SISGEDO — Documentos emitidos', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($filas), 0, 1);
        $pdf->Ln(2);
        $html = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
            . '<th width="18%">Documento</th><th width="12%">Numero</th><th width="28%">Asunto</th><th width="16%">Destino</th><th width="12%">Estado</th><th width="14%">Fecha</th></tr></thead><tbody>';
        foreach ($filas as $r) {
            $html .= '<tr><td width="18%">' . h($r[1]) . '</td><td width="12%">' . h($r[2]) . '</td><td width="28%">' . h($r[3]) . '</td><td width="16%">' . h($r[5])
                . '</td><td width="12%">' . h(self::estado($r)) . '</td><td width="14%">' . h($r[8]) . '</td></tr>';
        }
        $pdf->writeHTML($html . '</tbody></table>', true, false, true, false, '');
        $pdf->Output($nombre . '.pdf', 'I');
        exit;
    }
}
