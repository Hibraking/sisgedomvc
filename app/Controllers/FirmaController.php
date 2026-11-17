<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Url;
use App\Models\EmisionModel;
use App\Services\ArchivoService;
use App\Services\FirmaService;

/**
 * Integración con FIRMA PERÚ (mismo flujo que sisgedov2/clases/firmaperu.php).
 *   POST firma  accion=iniciar | estado   (usuario con sesión + token CSRF)
 *   POST firma  accion=param               (lo llama el firmador)  -> parámetros en Base64
 *   GET  firma  accion=documento&t=...     (lo llama el firmador)  -> PDF a firmar
 *   POST firma  accion=subir&t=...         (lo llama el firmador)  -> recibe el PDF firmado
 * Las llamadas del firmador no usan la sesión: se autorizan con un token de un solo uso
 * que vence en pocos minutos.
 */
class FirmaController extends Controller
{
    public function despachar()
    {
        $accion = isset($_REQUEST['accion']) ? (string)$_REQUEST['accion'] : '';
        switch ($accion) {
            case 'iniciar':
            case 'estado':
                $this->iniciarOEstado($accion);
                break;
            case 'param':
                $this->param();
                break;
            case 'documento':
                $this->documento();
                break;
            case 'subir':
                $this->subir();
                break;
        }
        $this->error('Acción no válida.', 400);
    }

    private function iniciarOEstado($accion)
    {
        if (!Auth::logueado()) $this->error('ERROR_SESION: sesión no iniciada o expirada.', 401);
        if (!Csrf::valido()) $this->error('ERROR_CSRF: token de seguridad inválido.', 403);
        $iddoc = (int)$this->post('id', 0);
        $doc = EmisionModel::documentoParaFirma($iddoc);
        if (!$doc) $this->json(array('ok' => false, 'mensaje' => 'Documento no encontrado.'));
        if (!Auth::esAdmin() && (int)$doc['idoficina_registro'] !== Auth::idOficina()) {
            $this->json(array('ok' => false, 'mensaje' => 'Solo la oficina emisora puede firmar este documento.'), 403);
        }
        if ($accion === 'estado') {
            $f = Database::fila("SELECT estado, tipo_firma, fecha_firma FROM sgd_firma WHERE iddocs_emitidos = ? ORDER BY idfirma DESC LIMIT 1", array($iddoc));
            $this->json(array('ok' => true, 'firma' => $f));
        }
        if (!FirmaService::activo()) {
            $this->json(array('ok' => false, 'mensaje' => 'La firma digital aún no está configurada (falta el archivo de credenciales de FIRMA PERÚ).'));
        }
        if (empty($doc['adjunto']) || strtolower(pathinfo($doc['adjunto'], PATHINFO_EXTENSION)) !== 'pdf') {
            $this->json(array('ok' => false, 'mensaje' => 'Para firmar digitalmente, el adjunto del documento debe ser un PDF.'));
        }
        if (!is_file(ArchivoService::carpeta('EMITIDO') . '/' . $doc['adjunto'])) {
            $this->json(array('ok' => false, 'mensaje' => 'No se encontró el archivo adjunto en el servidor.'));
        }
        if ((int)$doc['estado'] === 1) {
            $this->json(array('ok' => false, 'mensaje' => 'El documento ya fue recepcionado por su destino; no puede modificarse.'));
        }
        $tipo = $this->post('tipo') === 'VISTO_BUENO' ? 'VISTO_BUENO' : 'FIRMA';
        $token = bin2hex(random_bytes(32));
        Database::consulta("INSERT INTO sgd_firma (iddocs_emitidos, idpersonal, tipo_firma, param_token, token_expira, archivo_origen)
                            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)",
            array($iddoc, Auth::id() ?: null, $tipo, $token, (int)FirmaService::config('minutos'), $doc['adjunto']));
        $inicial = array('param_url' => Url::absoluta('firma') . '?accion=param', 'param_token' => $token, 'document_extension' => 'pdf');
        $this->json(array('ok' => true, 'puerto' => FirmaService::config('puerto'), 'param' => base64_encode(json_encode($inicial))));
    }

    private function param()
    {
        $sol = FirmaService::solicitud((string)$this->post('param_token'));
        if (!$sol) $this->error('Solicitud de firma inválida o vencida.', 403);
        try {
            $jwt = FirmaService::tokenAcceso();
        } catch (\Exception $e) {
            error_log('firma param: ' . $e->getMessage());
            $this->error('No se pudo autenticar con FIRMA PERÚ.', 502);
        }
        $c = FirmaService::config();
        $base = Url::absoluta('firma');
        $esVB = $sol['tipo_firma'] === 'VISTO_BUENO';
        $param = array(
            'signatureFormat' => 'PAdES', 'signatureLevel' => $c['nivel'], 'signaturePackaging' => 'enveloped',
            'documentToSign' => $base . '?accion=documento&t=' . $sol['param_token'],
            'certificateFilter' => $c['filtro_cert'],
            'webTsa' => $c['tsa_url'], 'userTsa' => $c['tsa_user'], 'passwordTsa' => $c['tsa_pass'],
            'theme' => 'claro', 'visiblePosition' => true, 'contactInfo' => '',
            'signatureReason' => $esVB ? 'Doy V° B°' : 'Soy el autor del documento',
            'bachtOperation' => false,   // nombre de campo tal como lo define FIRMA PERÚ
            'oneByOne' => true, 'signatureStyle' => 1,
            'imageToStamp' => Url::absoluta(implode('/', array_map('rawurlencode', explode('/', $c['imagen'])))),
            'stampTextSize' => 14, 'stampWordWrap' => 37, 'role' => $esVB ? 'Visto bueno' : '',
            'stampPage' => 1, 'positionx' => 20, 'positiony' => 20,
            'uploadDocumentSigned' => $base . '?accion=subir&t=' . $sol['param_token'],
            'certificationSignature' => false, 'token' => $jwt,
        );
        header('Content-Type: text/plain; charset=utf-8');
        exit(base64_encode(json_encode($param)));
    }

    private function documento()
    {
        $sol = FirmaService::solicitud($this->get('t'));
        if (!$sol) $this->error('Solicitud de firma inválida o vencida.', 403);
        $ruta = ArchivoService::carpeta('EMITIDO') . '/' . basename($sol['adjunto']);
        if (!is_file($ruta)) $this->error('Archivo no encontrado.', 404);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: inline; filename="documento.pdf"');
        readfile($ruta);
        exit;
    }

    private function subir()
    {
        $sol = FirmaService::solicitud($this->get('t'));
        if (!$sol) $this->error('Solicitud de firma inválida o vencida.', 403);
        if (!isset($_FILES['signed_file']) || !is_uploaded_file($_FILES['signed_file']['tmp_name'])) {
            $this->error('No se recibió el documento firmado.', 400);
        }
        $tmp = $_FILES['signed_file']['tmp_name'];
        if ((string)@file_get_contents($tmp, false, null, 0, 5) !== '%PDF-' || filesize($tmp) > 30 * 1024 * 1024) {
            Database::consulta("UPDATE sgd_firma SET estado='ERROR', detalle='Archivo recibido no es un PDF válido' WHERE idfirma=?", array($sol['idfirma']));
            $this->error('El archivo firmado no es un PDF válido.', 400);
        }
        // Se guarda como archivo NUEVO; el original se conserva para trazabilidad
        $nuevo = bin2hex(random_bytes(8)) . '_' . time() . '_firmado.pdf';
        $ruta = ArchivoService::carpeta('EMITIDO') . '/' . $nuevo;
        if (!move_uploaded_file($tmp, $ruta)) $this->error('No se pudo guardar el documento firmado.', 500);
        $hash = ArchivoService::registrar('EMITIDO', $nuevo, $ruta, 'firmado.pdf', 'INTERNO');
        $db = Database::pdo();
        $db->beginTransaction();
        // Todas las filas del mismo envío (un destino por fila) comparten el adjunto
        $db->prepare("UPDATE docs_emitidos SET adjunto = ? WHERE adjunto = ? AND idoficina_registro = ?")
           ->execute(array($nuevo, $sol['adjunto'], $sol['idoficina_registro']));
        $db->prepare("UPDATE sgd_firma SET estado='FIRMADO', archivo_firmado=?, hash_sha256=?, fecha_firma=NOW() WHERE idfirma=?")
           ->execute(array($nuevo, $hash, $sol['idfirma']));
        $db->commit();
        header('Content-Type: text/plain; charset=utf-8');
        exit('OK');
    }

    private function error($msg, $codigo)
    {
        http_response_code($codigo);
        header('Content-Type: text/plain; charset=utf-8');
        exit($msg);
    }
}
