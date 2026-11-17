<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Services\LoginService;

/** Ingreso y salida del sistema. */
class AuthController extends Controller
{
    public function mostrar()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        if (Auth::logueado()) {
            $this->redirigir('');
        }
        View::render('auth/login', array('error' => (int)Session::flash('login_error')));
    }

    public function ingresar()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'desconocida';
        $r = (new LoginService())->ingresar($this->post('usuario'), isset($_POST['password']) ? (string)$_POST['password'] : '', $ip);
        if ($r === LoginService::OK) {
            $this->redirigir('');
        }
        Session::flash('login_error', $r);
        $this->redirigir('login');
    }

    public function salir()
    {
        Session::destruir();
        $this->redirigir('login');
    }
}
