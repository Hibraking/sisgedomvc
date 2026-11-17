<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\UsuarioModel;

/**
 * Foto de perfil del usuario en sesión. Se lee de la carpeta de sisgedov2
 * (images/perfiles) para no duplicar archivos mientras dura la migración.
 */
class FotoController extends Controller
{
    public function perfil()
    {
        $archivo = Session::get('foto_perfil');
        if ($archivo === null) {
            $archivo = UsuarioModel::foto(Auth::id());
            Session::set('foto_perfil', $archivo);
        }
        $ruta = config('ruta_sisgedov2') . '/images/perfiles/' . basename((string)$archivo);
        if ($archivo === '' || !is_file($ruta)) {
            http_response_code(404);
            exit;
        }
        $tipos = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif');
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if (!isset($tipos[$ext])) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $tipos[$ext]);
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;
    }

    /** ¿El usuario tiene foto? (para que la cabecera la pida o use el ícono) */
    public static function tieneFoto()
    {
        $archivo = Session::get('foto_perfil');
        if ($archivo === null) {
            $archivo = UsuarioModel::foto(Auth::id());
            Session::set('foto_perfil', $archivo);
        }
        return $archivo !== '' && is_file(config('ruta_sisgedov2') . '/images/perfiles/' . basename((string)$archivo));
    }
}
