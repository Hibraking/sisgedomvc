<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;
use App\Models\CargoModel;
use App\Pdf\CargoExterno;
use App\Pdf\CargoInterno;
use App\Services\ArchivoService;
use App\Services\PdfService;

/**
 * Cargos PDF (mismo control de acceso que sisgedov2/pdf/acceso.php):
 *   cargo?exp=N°          personal con sesión
 *   cargo?c=XXXX-XXXX     ciudadano, con el código de verificación impreso (no se puede adivinar)
 *   cargo-interno?exp=ID  movimiento entre oficinas (solo con sesión)
 */
class CargoController extends Controller
{
    public function externo()
    {
        $codigo = $this->get('c');
        if ($codigo !== '') {
            $exp = CargoModel::expedientePorCvd($codigo);
            if ($exp === null) {
                $this->denegar('El código de verificación no es válido o no corresponde a un cargo de mesa de partes.');
            }
        } else {
            if (!Auth::logueado()) {
                $this->denegar('Para ver un cargo se necesita el código de verificación impreso en él.');
            }
            $exp = $this->get('exp');
        }
        session_write_close();                       // el PDF no modifica la sesión
        $d = null;
        try {
            $d = CargoModel::externo($exp);
        } catch (\Exception $e) {
            error_log('cargo: ' . $e->getMessage());
        }
        PdfService::cargar();
        PdfService::limpiarSalida();
        CargoExterno::generar($d, $exp);
        exit;
    }

    public function interno()
    {
        if (!Auth::logueado()) {
            $this->denegar('Este cargo es de uso interno de la municipalidad.');
        }
        session_write_close();
        $dato = null;
        try {
            $dato = CargoModel::interno((int)$this->get('exp', '0'));
            if ($dato) {
                $dato['cvd'] = ArchivoService::cvd($dato[9], $dato[10]);
            }
        } catch (\Exception $e) {
            error_log('cargo-interno: ' . $e->getMessage());
        }
        PdfService::cargar();
        PdfService::limpiarSalida();
        CargoInterno::generar($dato);
        exit;
    }

    /** Página clara (no un PDF vacío) cuando no hay permiso. */
    private function denegar($mensaje)
    {
        http_response_code(403);
        View::render('cargo/denegado', array('mensaje' => $mensaje));
        exit;
    }
}
