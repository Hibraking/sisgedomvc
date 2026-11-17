<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Respuesta;
use App\Models\ConsultaModel;
use App\Models\MesaPartesModel;
use App\Services\ArchivoService;
use App\Services\CalendarioService as Cal;
use App\Services\MesaPartesService;

/**
 * Páginas públicas (sin sesión):
 *   mesa-partes          Mesa de Partes Virtual (formulario del ciudadano)
 *   mesa-partes/enviar   POST del formulario
 *   consulta             Consulta tu expediente (?num_exp=)
 *   subsanar             Subsanar un documento observado (?t=, enlace del correo)
 *   verificar            Verificación de documentos por código (?c=)
 * Las direcciones antiguas de sisgedov2 (mpvirtual.php, consulta_publico.php,
 * subsanar.php, verificar.php) redirigen aquí: los enlaces de correos y los
 * códigos QR ya impresos siguen funcionando.
 */
class PublicoController extends Controller
{
    const LAYOUT = 'layouts/publico';

    /* ---------------- Mesa de Partes Virtual ---------------- */

    public function mesaPartes()
    {
        $ahora = date('Y-m-d H:i:s');
        $oficial = Cal::fechaPresentacion($ahora);
        $this->vista('publico/mesa_partes', array(
            'titulo' => 'Mesa de Partes Virtual',
            'subtitulo' => 'Mesa de Partes Virtual',
            'enlace' => array('consulta', 'fa-search', 'Consultar expediente'),
            'claseBody' => 'mpv',
            'fueraHorario' => strtotime($oficial) !== strtotime($ahora),
            'oficial' => $oficial,
            'tiposDoc' => MesaPartesModel::tiposDocumento(),
            'tramites' => MesaPartesModel::tiposTramite(),
        ), self::LAYOUT);
    }

    /** Respuesta para assets/js/mesa_partes_publica.js: "<expediente> CORREO_PENDIENTE" o "ERROR_...: motivo". */
    public function presentar()
    {
        Respuesta::iniciar();
        $campos = array('iddocumento', 'idtipo_tramite', 'numero_docu', 'asunto', 'folios', 'email', 'd_identidad',
                        'apellidos', 'nombres', 'direccion', 'telefono');
        $d = array();
        foreach ($campos as $c) {
            $d[$c] = (string)$this->post($c);
        }
        $srv = new MesaPartesService();
        $r = $srv->presentar($d, isset($_FILES['fichero']) ? $_FILES['fichero'] : null);
        if (!$r['ok']) {
            echo (!empty($r['archivo']) ? 'ERROR_ARCHIVO: ' : 'ERROR_VALIDACION: ') . $r['msg'];
            exit;
        }
        // El ciudadano recibe su número YA; los correos (lentos) salen después
        echo $r['exp'] . ' CORREO_PENDIENTE';
        Respuesta::terminar();
        $srv->ejecutarPendientes();
        exit;
    }

    /* ---------------- Consulta tu expediente ---------------- */

    public function consulta()
    {
        $numExp = $this->get('num_exp');
        $busco = $numExp !== '';
        $cab = null;
        $eventos = $mpvEventos = array();
        $mpvEstado = $mpvObs = $mpvOficial = null;
        if ($busco) {
            try {
                $regs = ConsultaModel::cabecera($numExp);
                if ($regs) {
                    $cab = $regs[0];
                    if ((int)$cab[7] === 1 && MesaPartesModel::moduloActivo() && ($info = ConsultaModel::estadoMpv($cab[6]))) {
                        if ($info[2] === 'NO_PRESENTADO') {
                            $mpvEstado = 'no_presentado';
                        } elseif ($info[2] === 'OBSERVADO') {
                            $mpvEstado = 'observado';
                        } else {
                            $mpvEstado = $info[0] === null ? 'pendiente' : ($info[1] === null ? 'recepcionado' : 'derivado');
                        }
                        if (Cal::fueraDeHorario($info[4])) {
                            $mpvOficial = Cal::fechaPresentacion($info[4]);
                        }
                        $mpvEventos = ConsultaModel::eventosMpv($cab[6]);
                        if ($mpvEstado === 'observado') {
                            $motivo = '';
                            foreach ($mpvEventos as $ev) {
                                if ($ev[0] === 'OBSERVADO') $motivo = $ev[1];
                            }
                            $mpvObs = array($motivo, $info[3]);
                        }
                    }
                    $eventos = ConsultaModel::recorrido(array_map(function ($r) { return $r[6]; }, $regs));
                }
            } catch (\Exception $e) {
                error_log('consulta: ' . $e->getMessage());
            }
        }
        $estadoActual = 'En mesa de partes';
        $oficinaActual = 'Mesa de Partes';
        $textos = array('pendiente' => 'En revisión de mesa de partes', 'observado' => 'Observado: debe subsanar',
                        'recepcionado' => 'Recepcionado por mesa de partes', 'no_presentado' => 'No presentado');
        if (!$eventos && $mpvEstado !== null && isset($textos[$mpvEstado])) {
            $estadoActual = $textos[$mpvEstado];
        }
        if ($eventos) {
            $ult = $eventos[count($eventos) - 1];
            $estadoActual = $ult[4] !== '--' ? $ult[4] : 'En trámite';
            $oficinaActual = $ult[2] !== '' ? $ult[2] : $oficinaActual;
        }
        $this->vista('publico/consulta', compact('numExp', 'busco', 'cab', 'eventos', 'mpvEstado', 'mpvEventos', 'mpvObs',
            'mpvOficial', 'estadoActual', 'oficinaActual') + array(
            'titulo' => 'Consulta tu Expediente',
            'subtitulo' => 'Consulta de Trámite Documentario',
            'enlace' => array('mesa-partes', 'fa-send-o', 'Mesa de Partes Virtual'),
        ), self::LAYOUT);
    }

    /* ---------------- Subsanar ---------------- */

    public function subsanar()
    {
        $token = isset($_REQUEST['t']) ? trim((string)$_REQUEST['t']) : '';
        $srv = new MesaPartesService();
        $doc = MesaPartesModel::porToken($token);
        $error = '';
        $exito = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Respuesta::iniciar();                // para entregar la página antes de enviar los correos
            if (!Csrf::valido()) {
                $error = 'La página caducó. Vuelva a abrir el enlace del correo e intente de nuevo.';
            } else {
                $r = $srv->subsanar($token, isset($_FILES['archivo']) ? $_FILES['archivo'] : null, $this->post('nota'));
                if ($r['ok']) {
                    $exito = $r;
                    $doc = null;
                } else {
                    $error = $r['msg'];
                    $doc = MesaPartesModel::porToken($token);
                }
            }
        }
        $this->vista('publico/subsanar', array(
            'titulo' => 'Subsanar documento',
            'subtitulo' => 'Mesa de Partes Virtual — Subsanación',
            'enlace' => array('consulta', 'fa-search', 'Consultar expediente'),
            'noIndexar' => true,
            'token' => $token, 'doc' => $doc, 'error' => $error, 'exito' => $exito,
            'vigente' => $doc ? MesaPartesService::plazoVigente($doc[19]) : false,
            'nota' => (string)$this->post('nota'),
        ), self::LAYOUT);
        if ($exito) {
            Respuesta::terminar();
            $srv->ejecutarPendientes();     // confirmación al ciudadano + aviso a mesa de partes
        }
    }

    /* ---------------- Verificar ---------------- */

    public function verificar()
    {
        $cvdIn = $this->get('c');
        $cvd = ArchivoService::normalizarCvd($cvdIn);
        $busco = $cvdIn !== '';
        $tipo = $doc = $archivo = $integro = null;
        $destinos = $firmas = array();
        if ($busco && strlen($cvd) === 8) {
            try {
                $ref = ConsultaModel::referenciaCvd($cvd);
                if ($ref) {
                    $tipo = $ref['tipo'];
                    if ($tipo === 'EXPEDIENTE') {
                        $doc = ConsultaModel::expedienteVerificado($ref['referencia']);
                        if ($doc && !empty($doc[8])) {
                            $archivo = ArchivoService::obtener('REGISTRO', $doc[8]);
                            $integro = ArchivoService::integro('REGISTRO', $doc[8]);
                        }
                    } else {
                        $doc = ConsultaModel::emitidoVerificado($ref['referencia']);
                        if ($doc) {
                            if ($doc[7] !== '') $destinos[] = $doc[7];
                            if (!empty($doc[6])) {
                                $firmas = ConsultaModel::firmas($doc[6]);
                                $archivo = ArchivoService::obtener('EMITIDO', $doc[6]);
                                $integro = ArchivoService::integro('EMITIDO', $doc[6]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log('verificar: ' . $e->getMessage());
            }
        }
        $this->vista('publico/verificar', compact('cvdIn', 'cvd', 'busco', 'tipo', 'doc', 'archivo', 'integro', 'destinos', 'firmas') + array(
            'valido' => (bool)$doc,
            'titulo' => 'Verificación de documentos',
            'subtitulo' => 'Verificación de Documentos',
            'enlace' => array('consulta', 'fa-search', 'Consulta tu expediente'),
            'noIndexar' => true,
        ), self::LAYOUT);
    }

    /* ---------------- Direcciones antiguas de sisgedov2 ---------------- */

    public function antigua(array $p)
    {
        $qs = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
        header('Location: ' . url($p['destino']) . ($qs !== '' ? '?' . $qs : ''), true, 301);
        exit;
    }
}
