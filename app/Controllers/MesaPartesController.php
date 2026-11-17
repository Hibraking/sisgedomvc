<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Respuesta;
use App\Models\MesaPartesModel as MPV;
use App\Models\OficinaModel;
use App\Services\CalendarioService as Cal;
use App\Services\MesaPartesService;

/**
 * Mesa de Partes Virtual (operador).
 *   GET  mesa-partes-virtual          bandejas: ?b=pendientes|observados|por_derivar|derivados
 *   POST mesa-partes-virtual/accion   recepcionar · observar · no_presentado · derivar · historial · novedades
 *                                     (mismas claves que sisgedov2: el JavaScript se reutiliza)
 */
class MesaPartesController extends Controller
{
    const BANDEJAS = array(
        'pendientes'  => array('Por recepcionar', 'fa-inbox'),
        'observados'  => array('Observados', 'fa-exclamation-circle'),
        'por_derivar' => array('Por derivar', 'fa-share'),
        'derivados'   => array('Historial', 'fa-history'),
    );

    public function index()
    {
        $bandeja = isset(self::BANDEJAS[$this->get('b')]) ? $this->get('b') : 'pendientes';
        $q = $this->get('q');
        $desde = preg_match('~^\d{4}-\d{2}-\d{2}$~', $this->get('desde')) ? $this->get('desde') : '';
        $hasta = preg_match('~^\d{4}-\d{2}-\d{2}$~', $this->get('hasta')) ? $this->get('hasta') : '';
        $orden = $this->get('orden') === 'antiguos' ? 'antiguos' : 'recientes';
        $datos = MPV::lista($bandeja, $q, $desde, $hasta, $orden);
        $urlAdj = url('adjunto') . '?c=reg&f=';

        // Datos derivados de cada fila (mismo cálculo que sisgedov2)
        $fila = function ($r) use ($urlAdj) {
            $oficial = Cal::fechaPresentacion($r[11]);
            $cierre = $r[19] ? Cal::cierre($r[19]) : null;
            $vencido = $cierre && time() > strtotime($cierre);
            $fh = function ($f) { return $f ? date('d/m/Y h:i a', strtotime($f)) : ''; };
            return array(
                'adj' => $r[12] !== '' ? $urlAdj . rawurlencode($r[12]) : '',
                'presentado' => $fh($r[11]),
                'oficial' => strtotime($oficial) !== strtotime($r[11]) ? Cal::texto($oficial) : '',
                'recep' => $fh($r[13]),
                'estado' => $r[18],
                'plazo' => $cierre ? Cal::texto($cierre) : '',
                'vencido' => $vencido,
                'por_vencer' => $cierre && !$vencido && date('Y-m-d') === $r[19],
            );
        };
        $mpvUrl = function ($b, array $extra = array()) {
            $p = array_merge(array('b' => $b), $extra);
            foreach ($p as $k => $v) {
                if ($v === '' || ($k === 'b' && $v === 'pendientes')) unset($p[$k]);
            }
            $qs = http_build_query($p);
            return url('mesa-partes-virtual') . ($qs !== '' ? '?' . $qs : '');
        };
        $hace = function ($fecha) {
            $s = time() - strtotime((string)$fecha);
            if ($s < 3600) return 'hace ' . max(1, (int)floor($s / 60)) . ' min';
            if ($s < 86400) return 'hace ' . (int)floor($s / 3600) . ' h';
            $d = (int)floor($s / 86400);
            return 'hace ' . $d . ($d === 1 ? ' día' : ' días');
        };
        $first = isset($datos[0]) ? $datos[0] : null;

        $this->vista('mesa_partes/index', array(
            'titulo' => 'Mesa de Partes Virtual',
            'cssExtra' => array('assets/css/v_mesa_partes_virtual.css'),
            'jsExtra' => array('assets/js/mesa_partes.js', 'assets/js/mesa_partes_en_vivo.js'),
            'bodyData' => array(
                'bandeja' => $bandeja,
                'plazoDias' => (int)SISGEDO_PLAZO_SUBSANAR,
                'plazoObs' => Cal::texto(Cal::cierre(Cal::sumarHabiles(date('Y-m-d'), (int)SISGEDO_PLAZO_SUBSANAR))),
                'endpoint' => url('mesa-partes-virtual/accion'),
                'urlBandeja' => url('mesa-partes-virtual'),
                'urlAdjunto' => $urlAdj,
                'urlCargo' => rtrim(config('url_sisgedov2'), '/') . '/pdf/cargo.php?exp=',   // cargos PDF: etapa 4
            ),
            'bandejas' => self::BANDEJAS,
            'bandeja' => $bandeja,
            'migracionOk' => MPV::moduloActivo(),
            'qFiltro' => $q, 'desdeFiltro' => $desde, 'hastaFiltro' => $hasta, 'ordenFiltro' => $orden,
            'hoy' => date('Y-m-d'),
            'datos' => $datos,
            'cont' => MPV::contadores(),
            'catOficinas' => OficinaModel::lista(),
            'first' => $first,
            'firstF' => $first ? $fila($first) : null,
            'fila' => $fila, 'mpvUrl' => $mpvUrl, 'hace' => $hace,
            'urlV2' => rtrim(config('url_sisgedov2'), '/'),
        ));
    }

    public function accion()
    {
        $srv = new MesaPartesService();
        $idpersonal = Auth::id();
        $id = (int)$this->post('id', 0);

        if (isset($_POST['historial'])) {
            $this->json(MPV::historial($id));
        }
        if (isset($_POST['novedades'])) {
            session_write_close();            // no bloquear las demás peticiones de la sesión
            header('Cache-Control: no-store');
            $this->json(MPV::novedades());
        }
        header('Content-Type: text/plain; charset=utf-8');
        if (isset($_POST['observar']) || isset($_POST['derivar'])) {
            $r = isset($_POST['observar'])
                ? $srv->observar($id, (string)$this->post('motivo'), $idpersonal)
                : $srv->derivar($id, (int)$this->post('idoficina', 0), $idpersonal);
            echo $r['ok'] ? trim('OK ' . (isset($r['correo']) ? $r['correo'] : '')) : 'ERROR_VALIDACION: ' . $r['msg'];
            exit;
        }
        if (isset($_POST['recepcionar']) || isset($_POST['no_presentado'])) {
            Respuesta::iniciar();
            $r = isset($_POST['recepcionar']) ? $srv->recepcionar($id, $idpersonal) : $srv->noPresentado($id, $idpersonal);
            echo $r['ok'] ? 'OK ' . $r['correo'] : 'ERROR_VALIDACION: ' . $r['msg'];
            Respuesta::terminar();            // el operador no espera el correo
            $srv->ejecutarPendientes();
            exit;
        }
        echo 'ERROR_VALIDACION: Acción no reconocida.';
        exit;
    }
}
