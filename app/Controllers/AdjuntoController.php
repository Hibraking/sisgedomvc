<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * Muestra un documento adjunto guardado por sisgedov2 (mismas carpetas):
 *   adjunto?c=reg&f=<archivo>  → "docs adjs reg_doc"        (registros / mesa de partes)
 *   adjunto?c=emi&f=<archivo>  → "docs adjs docs_emitidos"  (documentos emitidos)
 * Requiere sesión. Solo se acepta el nombre del archivo (sin rutas).
 */
class AdjuntoController extends Controller
{
    private static $carpetas = array('reg' => 'docs adjs reg_doc', 'emi' => 'docs adjs docs_emitidos');

    public function ver()
    {
        $c = $this->get('c');
        $f = basename(str_replace('\\', '/', $this->get('f')));
        if (!isset(self::$carpetas[$c]) || $f === '' || $f[0] === '.') {
            $this->noEncontrado();
        }
        $ruta = rtrim(config('ruta_sisgedov2'), '/\\') . '/' . self::$carpetas[$c] . '/' . $f;
        if (!is_file($ruta)) {
            $this->noEncontrado();
        }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $tipos = array('pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                       'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain; charset=utf-8');
        $enLinea = isset($tipos[$ext]);
        header('Content-Type: ' . ($enLinea ? $tipos[$ext] : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f));
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;
    }

    private function noEncontrado()
    {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
}
