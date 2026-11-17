<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\OficinaModel;
use App\Models\RecepcionModel;
use App\Services\ContextoService;
use App\Services\RecepcionFormato as F;
use App\Services\RecepcionService;

/**
 * Recepción de documentos y Documentos derivados.
 *   GET  recepcion        → bandeja de pendientes (o recepcionados con ?estado=Recepcionado)
 *   GET  derivados        → bandeja de derivados por la oficina
 *   POST recepcion/accion → acciones de la bandeja (mismas claves que sisgedov2,
 *                           así el JavaScript de la pantalla se reutiliza tal cual)
 */
class RecepcionController extends Controller
{
    const DEFECTOS = array('q' => '', 'desde' => '', 'hasta' => '', 'tipo' => 'Todos', 'origen' => 'Todos',
                           'estado' => 'Todos', 'sort' => 'recentes', 'per_page' => '25');

    /* =========================== Bandejas =========================== */

    public function index(array $p)
    {
        $base = $p['base'];                                     // 'recepcion' | 'derivados'
        $estadoGet = strtolower($this->get('estado', ''));
        $esDerivado = in_array($estadoGet, array('derivado', 'enviado'), true);

        // Una sola dirección por bandeja: /derivados para lo derivado, /recepcion para lo demás
        if ($base === 'recepcion' && $esDerivado) {
            $this->irA($this->direccion('derivados', array_diff_key($_GET, array('estado' => 1))));
        }
        if ($base === 'derivados' && $estadoGet !== '' && !$esDerivado) {
            $this->irA($this->direccion('recepcion', $_GET));
        }

        $ctx = ContextoService::actual();
        $idoficina = $ctx['idoficina'];

        // ---- Filtros ----
        $f = array();
        foreach (self::DEFECTOS as $k => $def) {
            $f[$k] = $this->get($k, $def);
        }
        $f['per_page'] = (string)max(10, min(100, (int)$f['per_page']));
        $vista = $base === 'derivados' ? 'enviado' : (strtolower($f['estado']) === 'recepcionado' ? 'recepcionado' : 'pendiente');
        if ($vista === 'enviado') {
            $f['estado'] = 'Derivado';
        }

        // ---- Datos según la bandeja (por defecto, últimos 30 días para recepcionados/derivados) ----
        $hoy = date('Y-m-d');
        $desdeSql = $this->fechaValida($f['desde']) ?: date('Y-m-d', strtotime('-30 days'));
        $hastaSql = $this->fechaValida($f['hasta']) ?: $hoy;
        if ($vista === 'recepcionado') {
            $datos = RecepcionModel::recepcionados($idoficina, $desdeSql, $hastaSql);
        } elseif ($vista === 'enviado') {
            $datos = RecepcionModel::derivados($idoficina, $desdeSql, $hastaSql);
        } else {
            $datos = RecepcionModel::porRecepcionar($idoficina);
        }

        // ---- Rango de fechas permitido en los filtros ----
        $fechaMin = $hoy;
        foreach ($datos as $d) {
            if (!empty($d[12]) && ($t = strtotime($d[12])) !== false && date('Y-m-d', $t) < $fechaMin) {
                $fechaMin = date('Y-m-d', $t);
            }
        }
        if ($vista !== 'pendiente') {
            $fechaMin = date('Y-m-d', strtotime('-5 years'));   // puede ampliar la ventana hacia atrás
        }
        foreach (array('desde', 'hasta') as $k) {
            if ($f[$k] !== '') {
                $f[$k] = min(max($f[$k], $fechaMin), $hoy);
            }
        }

        // ---- Opciones de los combos (de lo que hay en la bandeja) ----
        $tipos = $origenes = $estados = array();
        foreach ($datos as $r) {
            $t = strtoupper(trim((string)$r[3]));
            $o = trim((string)$r[1]);
            $e = F::estado($r[11]);
            if ($t !== '') $tipos[$t] = $t;
            if ($o !== '') $origenes[$o] = $o;
            $estados[$e] = $e;
        }
        ksort($tipos);
        asort($origenes);
        ksort($estados);

        $filtrados = $this->filtrar($datos, $f);

        // ---- Exportaciones (todos los filtrados, no solo la página) ----
        $sufijo = $vista === 'pendiente' ? 'pendientes' : ($vista === 'recepcionado' ? 'recepcionados' : 'derivados');
        $colOrigen = $vista === 'enviado' ? 'Oficina destino' : 'Origen';
        $colFecha = $vista === 'enviado' ? 'Fecha de derivación' : 'Fecha recepción';
        $exp = $this->get('export');
        if (in_array($exp, array('csv', 'xls', 'pdf'), true)) {
            $this->exportar($exp, $filtrados, $sufijo, $colOrigen, $colFecha, OficinaModel::nombre($idoficina));
        }

        // ---- Indicadores ----
        $cont = RecepcionModel::contadores($idoficina);
        $kpiDer = $vista === 'enviado' ? RecepcionModel::kpisDerivados($idoficina) : null;
        $urgentes = null;
        if ($vista === 'pendiente') {
            $urgentes = 0;
            $lim7 = strtotime('-7 days');
            foreach ($datos as $d) {
                $t = !empty($d[12]) ? strtotime($d[12]) : false;
                if ($t !== false && $t < $lim7) $urgentes++;
            }
        }
        if ($vista === 'enviado') {
            $kpis = array(
                array('Derivados hoy', $kpiDer['hoy'], 'Documentos derivados hoy', 'fa-paper-plane', 't-purple', '#6d28d9', 'lab-purple', ''),
                array('Este mes', $kpiDer['mes'], 'Derivaciones de ' . date('m/Y'), 'fa-calendar', 't-blue', '#1e293b', 'lab-blue', ''),
                array('Destinos', $kpiDer['destinos'], 'Oficinas distintas este mes', 'fa-building-o', 't-green', '#16a34a', 'lab-green', ''),
                array('Histórico', $cont['enviados'], 'Total de derivaciones', 'fa-archive', 't-orange', '#f97316', 'lab-orange', ''),
            );
        } else {
            $kpis = array(
                array('Pendientes', $vista === 'pendiente' ? count($datos) : RecepcionModel::contarPendientes($idoficina), 'Por recepcionar', 'fa-inbox', 't-blue', '#1e293b', 'lab-blue', 'Todos'),
                array('Recepcionados hoy', RecepcionModel::recepcionadosHoy($idoficina), 'Completados el día de hoy', 'fa-calendar-check-o', 't-purple', '#6d28d9', 'lab-purple', 'Recepcionado'),
                array('Recepcionados', $cont['recepcionados'], 'Completados (histórico)', 'fa-check-circle', 't-green', '#16a34a', 'lab-green', 'Recepcionado'),
                array('Urgentes', $urgentes, 'Requieren atención', 'fa-exclamation-triangle', 't-orange', '#f97316', 'lab-orange', 'Todos'),
            );
        }

        // ---- Paginación ----
        $porPagina = (int)$f['per_page'];
        $total = count($filtrados);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, (int)$this->get('page', '1')), $totalPaginas);
        $inicio = ($pagina - 1) * $porPagina;

        $filtrosUrl = $f;
        if ($vista === 'enviado') {
            unset($filtrosUrl['estado']);                        // implícito en /derivados
        }

        $this->vista('recepcion/index', array(
            'titulo' => $vista === 'enviado' ? 'Documentos Derivados' : 'Recepción de Documentos',
            'jsExtra' => array('assets/js/recepcion.js'),
            'bodyData' => array('idoficina' => $idoficina, 'idresponsable' => $ctx['idresponsable'],
                'endpoint' => url('recepcion/accion'), 'urlRecepcion' => url('recepcion'), 'urlDerivados' => url('derivados')),
            'vistaEstado' => $vista,
            'base' => $base,
            'f' => $f,
            'idoficina' => $idoficina,
            'idresponsable' => $ctx['idresponsable'],
            'sufijo' => $sufijo,
            'colOrigen' => $colOrigen,
            'colFecha' => $colFecha,
            'desdeSql' => $desdeSql,
            'hastaSql' => $hastaSql,
            'fechaMin' => $fechaMin,
            'fechaMax' => $hoy,
            'tipos' => $tipos,
            'origenes' => $origenes,
            'estados' => $estados,
            'kpis' => $kpis,
            'hayDatos' => count($datos) > 0,
            'filas' => array_map(array($this, 'enlaces'), array_slice($filtrados, $inicio, $porPagina)),
            'total' => $total,
            'pagina' => $pagina,
            'totalPaginas' => $totalPaginas,
            'inicio' => $inicio,
            'porPagina' => $porPagina,
            'paginaUrl' => function ($n) use ($base, $filtrosUrl) {
                return $this->direccion($base, $filtrosUrl + array('page' => $n));
            },
        ));
    }

    /* =========================== Acciones (POST) =========================== */

    public function accion()
    {
        $srv = new RecepcionService();
        $ctx = ContextoService::actual();

        // ---- Consultas (JSON) ----
        if (isset($_POST['carga_aciones'])) $this->json(RecepcionModel::acciones());
        if (isset($_POST['carga_estados'])) $this->json(RecepcionModel::estados());
        if (isset($_POST['carga_oficinas'])) $this->json(RecepcionModel::oficinas());
        if (isset($_POST['historial_documento'])) $this->json(RecepcionModel::historial($this->post('codigo', 0), $this->post('tipo')));
        if (isset($_POST['Obtener'])) $this->json(RecepcionModel::documentoDeMovimiento($this->post('Codigo', 0)));
        // Archivadores de la oficina EN SESIÓN (ya no la que envía el navegador)
        if (isset($_POST['get_archivadores'])) $this->json(RecepcionModel::archivadores($ctx['idoficina']));

        header('Content-Type: text/plain; charset=utf-8');

        // ---- Recepcionar un registro o un emitido ----
        if (isset($_POST['insertar'])) {
            $folio = $this->post('Folio');
            if (!is_numeric($folio) || (int)$folio < 1) $this->texto('ERROR_VALIDACION: Ingrese una cantidad de folios válida (entero mayor a 0).');
            if ((int)$this->post('idacciondocs', 0) < 1) $this->texto('ERROR_VALIDACION: Seleccione una acción a realizar.');
            $id = $srv->recepcionar($this->post('idregdocs', 0), $this->post('iddocs_emitidos', 0), (int)$this->post('idacciondocs'), (int)$folio, (string)$this->post('detalles'));
            $this->texto($id > 0 ? (string)$id : 'ERROR_VALIDACION: ' . $srv->error);
        }

        // ---- Recibir una derivación ----
        if (isset($_POST['recibir_derivado'])) {
            $folio = $this->post('Folio');
            if (!is_numeric($folio) || (int)$folio < 1) $this->texto('ERROR_VALIDACION: Ingrese una cantidad de folios válida (entero mayor a 0).');
            $ok = $srv->recibirDerivado((int)$this->post('codigo', 0), (int)$folio, (string)$this->post('detalles'));
            $this->texto($ok ? '' : 'ERROR_VALIDACION: ' . $srv->error);
        }

        // ---- Derivar / devolver ----
        if (isset($_POST['insert_deriv_resp_dev'])) {
            if ((int)$this->post('Documento', 0) > 0 || !empty($_FILES['fichero']['name'])) {
                // Responder con documento (y adjunto) se migra con Emisión (etapa 4)
                $this->texto('ERROR_VALIDACION: Responder con documento aún no está disponible en esta versión. Use el sistema actual.');
            }
            $ok = $srv->derivar((int)$this->post('Codigo', 0), $this->post('idregdocs', 0), $this->post('iddocs_emitidos', 0),
                (int)$this->post('idacciondocs', 0), (int)$this->post('idestadodocs', 0), (int)$this->post('idoficina_derivar', 0),
                (string)$this->post('detalles'), (int)$this->post('NuevoEstado', 0));
            $this->texto($ok ? '' : 'ERROR_VALIDACION: ' . $srv->error);
        }

        // ---- Archivar ----
        if (isset($_POST['insert_archivar'])) {
            $ok = $srv->archivar((int)$this->post('idarchivador', 0), (int)$this->post('idmovimiento', 0), (string)$this->post('detalles'), (int)$this->post('idestadodoc', 0));
            $this->texto($ok ? '' : 'ERROR_VALIDACION: ' . $srv->error);
        }

        http_response_code(400);
        $this->texto('ERROR_VALIDACION: acción no reconocida.');
    }

    /* =========================== Apoyo =========================== */

    /** Redirige a una dirección ya completa (url() ya aplicado). */
    private function irA($direccion)
    {
        header('Location: ' . $direccion);
        exit;
    }

    private function texto($s)
    {
        echo $s;
        exit;
    }

    private function fechaValida($s)
    {
        return preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)$s) ? $s : '';
    }

    /** Dirección limpia: omite los parámetros con su valor por defecto (y page=1). */
    private function direccion($ruta, array $params)
    {
        $out = array();
        foreach ($params as $k => $v) {
            if (!is_string($v) && !is_int($v)) continue;
            if ($k === 'page' && (int)$v <= 1) continue;
            if (isset(self::DEFECTOS[$k]) && (string)$v === self::DEFECTOS[$k]) continue;
            if (!isset(self::DEFECTOS[$k]) && $k !== 'page') continue;
            $out[$k] = $v;
        }
        $qs = http_build_query($out);
        return url($ruta) . ($qs !== '' ? '?' . $qs : '');
    }

    private function filtrar(array $datos, array $f)
    {
        $q = F::norm($f['q']);
        $filas = array_values(array_filter($datos, function ($r) use ($f, $q) {
            if ($q !== '') {
                $texto = F::norm(implode(' ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, $r)));
                if (strpos($texto, $q) === false) return false;
            }
            if ($f['tipo'] !== 'Todos' && F::norm($r[3]) !== F::norm($f['tipo'])) return false;
            if ($f['origen'] !== 'Todos' && F::norm($r[1]) !== F::norm($f['origen'])) return false;
            if ($f['estado'] !== 'Todos' && F::norm(F::estado($r[11])) !== F::norm($f['estado'])) return false;
            $ts = isset($r[12]) ? strtotime($r[12]) : false;
            if ($f['desde'] !== '' && ($ts === false || $ts < strtotime($f['desde'] . ' 00:00:00'))) return false;
            if ($f['hasta'] !== '' && ($ts === false || $ts > strtotime($f['hasta'] . ' 23:59:59'))) return false;
            return true;
        }));
        $orden = $f['sort'];
        usort($filas, function ($a, $b) use ($orden) {
            $ta = strtotime((string)$a[12]);
            $tb = strtotime((string)$b[12]);
            switch ($orden) {
                case 'antiguos': return $ta <=> $tb;
                case 'exp_asc': return strnatcmp((string)$a[4], (string)$b[4]);
                case 'exp_desc': return strnatcmp((string)$b[4], (string)$a[4]);
                default: return $tb <=> $ta;
            }
        });
        return $filas;
    }

    /** Agrega a la fila las direcciones de adjunto, seguimiento y cargo. */
    private function enlaces(array $r)
    {
        $r['adj'] = '';
        if (preg_match('~^(reg|emi):(.+)$~s', (string)$r[10], $m)) {
            $r['adj'] = url('adjunto') . '?c=' . $m[1] . '&f=' . rawurlencode($m[2]);
        }
        $antiguo = rtrim(config('url_sisgedov2'), '/');
        // Seguimiento y cargo: se migran en las etapas 3, 4 y 6; mientras, se abren en sisgedov2
        $r['seguimiento'] = strtoupper(trim((string)$r[2])) === 'INTERNO'
            ? $antiguo . '/reportes/consulta_interna.php?codigo_doc=' . rawurlencode((string)$r[4])
            : $antiguo . '/consulta_publico.php?num_exp=' . rawurlencode((string)$r[4]);
        $r['cargo'] = $antiguo . '/pdf/cargo_interno.php?exp=' . rawurlencode((string)$r[0]);
        return $r;
    }

    private function exportar($fmt, array $filas, $sufijo, $colOrigen, $colFecha, $oficina)
    {
        $nombre = 'recepcion_' . $sufijo . '_' . date('Ymd_His');
        $cab = array('Expediente', 'Documento', 'Numero', $colOrigen, 'Tramite', 'Asunto', 'Detalles', 'Estado', $colFecha);
        if ($fmt === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nombre . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, $cab, ';', '"', '\\');
            foreach ($filas as $r) {
                fputcsv($out, array($r[4], $r[3], $r[5], $r[1], $r[2], $r[6], $r[7], F::estado($r[11]), $r[9]), ';', '"', '\\');
            }
            fclose($out);
            exit;
        }
        if ($fmt === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nombre . '.xls"');
            echo "\xEF\xBB\xBF<table border=\"1\"><tr style=\"background:#e2e8f0;font-weight:bold\">";
            foreach ($cab as $c) echo '<th>' . h($c) . '</th>';
            echo '</tr>';
            foreach ($filas as $r) {
                echo '<tr>';
                foreach (array($r[4], $r[3], $r[5], $r[1], $r[2], $r[6], $r[7], F::estado($r[11]), $r[9]) as $v) echo '<td>' . h($v) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }
        // PDF con la librería TCPDF de sisgedov2 (se trae al proyecto cuando se migren los cargos, etapa 4)
        $tcpdf = rtrim(config('ruta_sisgedov2'), '/\\') . '/tcpdf/tcpdf.php';
        if (!is_file($tcpdf)) {
            http_response_code(500);
            exit('No se encontró la librería TCPDF.');
        }
        error_reporting(E_ALL & ~E_DEPRECATED);
        ini_set('display_errors', '0');
        require_once $tcpdf;
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('SISGEDO');
        $pdf->SetTitle('Recepción de documentos — ' . ucfirst($sufijo));
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'SISGEDO — Documentos ' . $sufijo, 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Oficina: ' . $oficina . '  |  Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($filas), 0, 1);
        $pdf->Ln(2);
        $html = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
            . '<th width="9%">Expediente</th><th width="16%">Documento</th><th width="16%">' . h($colOrigen) . '</th><th width="33%">Asunto</th><th width="12%">Estado</th><th width="14%">' . h($colFecha) . '</th></tr></thead><tbody>';
        foreach ($filas as $r) {
            $html .= '<tr><td width="9%">' . h($r[4]) . '</td><td width="16%">' . h($r[3] . ' ' . $r[5]) . '</td><td width="16%">' . h($r[1]) . '</td><td width="33%">' . h($r[6]) . '</td><td width="12%">' . h(F::estado($r[11])) . '</td><td width="14%">' . h($r[9]) . '</td></tr>';
        }
        $pdf->writeHTML($html . '</tbody></table>', true, false, true, false, '');
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($nombre . '.pdf', 'I');
        exit;
    }
}
