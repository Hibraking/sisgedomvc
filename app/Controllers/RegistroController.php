<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\OficinaModel;
use App\Models\RegistroModel;
use App\Services\PdfService;
use App\Services\RecepcionFormato as F;
use App\Services\RegistroService;

/**
 * Registro de documentos (mesa de partes presencial).
 *   GET  registro          listado (filtros q, desde, hasta, estado, sort, per_page, page; export=csv|xls|pdf)
 *   POST registro/accion   Insertar · Modificar · Emilinar · carga_remitente · insertar (remitente)
 *   POST registro/reniec   nombres por DNI
 */
class RegistroController extends Controller
{
    const DEFECTOS = array('q' => '', 'desde' => '', 'hasta' => '', 'estado' => 'Todos', 'sort' => 'recentes', 'per_page' => '25');

    public function index()
    {
        $f = array();
        foreach (self::DEFECTOS as $k => $def) $f[$k] = $this->get($k, $def);
        $porPagina = max(10, min(100, (int)$f['per_page']));
        $hoy = date('Y-m-d');
        $desdeSql = preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['desde']) ? $f['desde'] : date('Y-m-d', strtotime('-7 days'));
        $hastaSql = preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['hasta']) ? $f['hasta'] : $hoy;

        $datos = RegistroModel::registrados($desdeSql, $hastaSql);
        $q = F::norm($f['q']);
        $filtrados = array_values(array_filter($datos, function ($r) use ($f, $q) {
            if ($f['estado'] !== 'Todos') {
                $est = (int)$r[13] === 1 ? 'Recepcionado' : 'Pendiente';
                if (F::norm($est) !== F::norm($f['estado'])) return false;
            }
            if ($q !== '' && strpos(F::norm(implode(' ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, $r))), $q) === false) return false;
            return true;
        }));
        if ($f['sort'] === 'antiguos') {
            usort($filtrados, function ($a, $b) { return strcmp((string)$a[12], (string)$b[12]); });
        }
        $exp = $this->get('export');
        if (in_array($exp, array('csv', 'xls', 'pdf'), true)) {
            $this->exportar($exp, $filtrados);
        }

        $total = count($filtrados);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, (int)$this->get('page', '1')), $totalPaginas);
        $inicio = ($pagina - 1) * $porPagina;
        $urlAdj = url('adjunto') . '?c=reg&f=';
        $filas = array_map(function ($r) use ($urlAdj) {
            $r[14] = $r[14] !== '#' ? $urlAdj . rawurlencode(substr($r[14], 4)) : '#';
            return $r;
        }, array_slice($filtrados, $inicio, $porPagina));

        $this->vista('registro/index', array(
            'titulo' => 'Registro de Documentos',
            'cssExtra' => array('assets/css/o_reg_documentos.css'),
            'jsExtra' => array('assets/js/registro.js'),
            'bodyData' => array('idoficina' => \App\Core\Auth::idOficina(), 'endpoint' => url('registro/accion'),
                                'urlReniec' => url('registro/reniec'), 'urlCargo' => url('cargo') . '?exp=', 'urlBase' => url('registro')),
            'qFiltro' => $f['q'], 'desdeFiltro' => $f['desde'], 'hastaFiltro' => $f['hasta'], 'estadoFiltro' => $f['estado'],
            'sortFiltro' => $f['sort'], 'porPagina' => $porPagina, 'hoy' => $hoy, 'desdeSql' => $desdeSql, 'hastaSql' => $hastaSql,
            'datos' => $datos, 'datosPagina' => $filas, 'first' => isset($filas[0]) ? $filas[0] : null,
            'totalTabla' => $total, 'paginaActual' => $pagina, 'totalPaginas' => $totalPaginas, 'inicioPagina' => $inicio,
            'cont' => RegistroModel::contadores(),
            'catDocumentos' => RegistroModel::tiposDocumento(),
            'catTramites' => RegistroModel::tramites(),
            'catOficinas' => OficinaModel::lista(),
            'paginaUrl' => function ($n) use ($f) { return $this->direccion($f + array('page' => $n)); },
        ));
    }

    public function accion()
    {
        $srv = new RegistroService();
        if (isset($_POST['carga_remitente'])) {
            $this->json(RegistroModel::remitentes());
        }
        header('Content-Type: text/plain; charset=utf-8');
        if (isset($_POST['Insertar'])) {
            $exp = $srv->registrar($_POST, isset($_FILES['fichero']) ? $_FILES['fichero'] : null);
            exit($exp !== false ? 'OK ' . $exp : 'ERROR_VALIDACION: ' . $srv->error);
        }
        if (isset($_POST['Modificar'])) {
            $ok = $srv->modificar((int)$this->post('Codigo', 0), $_POST,
                isset($_FILES['fichero']) ? $_FILES['fichero'] : null);
            exit($ok ? 'OK' : 'ERROR_VALIDACION: ' . $srv->error);
        }
        if (isset($_POST['Emilinar'])) {
            exit($srv->eliminar((int)$this->post('Codigo', 0)) ? 'OK' : 'ERROR_ELIMINAR: ' . $srv->error);
        }
        if (isset($_POST['insertar'])) {      // nuevo remitente (responde su id)
            $ape = (string)$this->post('apellidos');
            $nom = (string)$this->post('nombres');
            if ($ape === '' || $nom === '') exit('ERROR_VALIDACION: Ingrese apellidos y nombres.');
            exit((string)$srv->remitente($ape, $nom, (string)$this->post('direccion'), (string)$this->post('email'), (string)$this->post('telefono')));
        }
        http_response_code(400);
        exit('ERROR_VALIDACION: Acción no reconocida.');
    }

    public function reniec()
    {
        $this->json(RegistroService::consultarDni((string)$this->post('dni')));
    }

    private function direccion(array $params)
    {
        $out = array();
        foreach ($params as $k => $v) {
            if ($k === 'page' ? (int)$v <= 1 : (!isset(self::DEFECTOS[$k]) || (string)$v === self::DEFECTOS[$k])) continue;
            $out[$k] = $v;
        }
        $qs = http_build_query($out);
        return url('registro') . ($qs !== '' ? '?' . $qs : '');
    }

    private function exportar($fmt, array $filas)
    {
        $nombre = 'registro_mesa_partes_' . date('Ymd_His');
        $est = function ($r) { return (int)$r[13] === 1 ? 'Recepcionado' : 'Pendiente'; };
        if ($fmt === 'csv' || $fmt === 'xls') {
            $cab = array('N Expediente', 'Documento', 'N Documento', 'Remitente', 'Tramite', 'Oficina destino', 'Asunto', 'Folios', 'Estado', 'Fecha');
            $val = function ($r) use ($est) { return array($r[5], $r[1], $r[6], $r[2], $r[3], $r[4], $r[7], $r[10], $est($r), $r[11]); };
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
        $pdf->SetTitle('Registro de documentos — Mesa de partes');
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'SISGEDO — Registro de documentos (mesa de partes)', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($filas), 0, 1);
        $pdf->Ln(2);
        $html = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
            . '<th width="10%">Expediente</th><th width="16%">Documento</th><th width="16%">Remitente</th><th width="14%">Destino</th><th width="24%">Asunto</th><th width="9%">Estado</th><th width="11%">Fecha</th></tr></thead><tbody>';
        foreach ($filas as $r) {
            $html .= '<tr><td width="10%">' . h($r[5]) . '</td><td width="16%">' . h($r[1] . ' ' . $r[6]) . '</td><td width="16%">' . h($r[2]) . '</td><td width="14%">' . h($r[4])
                . '</td><td width="24%">' . h($r[7]) . '</td><td width="9%">' . $est($r) . '</td><td width="11%">' . h($r[11]) . '</td></tr>';
        }
        $pdf->writeHTML($html . '</tbody></table>', true, false, true, false, '');
        $pdf->Output($nombre . '.pdf', 'I');
        exit;
    }
}
