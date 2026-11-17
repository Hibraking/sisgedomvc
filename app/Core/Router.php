<?php
namespace App\Core;

/**
 * Enrutador simple.
 *   $r->get('recepcion', 'RecepcionController@index', ['permiso' => 'vistas/v_recepcion_proveido.php']);
 *   $r->post('login', 'AuthController@login', ['publica' => true]);
 * Opciones:
 *   publica  => true  : no exige sesión (login, páginas públicas)
 *   permiso  => '...' : permiso de menú requerido (tabla accesos_usuario)
 *   admin    => true  : solo el administrador
 *   csrf     => false : desactiva la verificación CSRF de un POST (por defecto se exige)
 *   datos    => [...] : datos fijos que se pasan al método del controlador
 */
class Router
{
    private $rutas = array();

    public function get($patron, $accion, array $opc = array())
    {
        $this->agregar('GET', $patron, $accion, $opc);
    }

    public function post($patron, $accion, array $opc = array())
    {
        $this->agregar('POST', $patron, $accion, $opc);
    }

    private function agregar($metodo, $patron, $accion, array $opc)
    {
        $patron = trim($patron, '/');
        $regex = '~^' . preg_replace('~\{([a-z_]+)\}~', '(?P<$1>[^/]+)', $patron) . '$~i';
        $this->rutas[] = array($metodo, $regex, $accion, $opc);
    }

    public function despachar($metodo, $ruta)
    {
        $metodoValido = false;
        foreach ($this->rutas as $r) {
            if (!preg_match($r[1], $ruta, $m)) {
                continue;
            }
            if ($r[0] !== $metodo) {
                $metodoValido = true;
                continue;
            }
            $opc = $r[3];
            // Seguridad: sesión, administrador, permiso de menú y CSRF
            if (empty($opc['publica']) && !Auth::logueado()) {
                if ($metodo === 'GET') {
                    header('Location: ' . url('login'));
                    exit;
                }
                http_response_code(401);
                exit('ERROR_SESION: sesión no iniciada o expirada. Vuelva a iniciar sesión.');
            }
            if (!empty($opc['admin']) && !Auth::esAdmin()) {
                $this->sinPermiso($metodo);
            }
            if (!empty($opc['permiso']) && !Auth::puede($opc['permiso'])) {
                $this->sinPermiso($metodo);
            }
            if ($metodo === 'POST' && (!isset($opc['csrf']) || $opc['csrf'] !== false)) {
                Csrf::exigir();
            }
            $params = array();
            foreach ($m as $k => $v) {
                if (is_string($k)) $params[$k] = $v;
            }
            if (!empty($opc['datos'])) {
                $params = array_merge($opc['datos'], $params);
            }
            list($clase, $accion) = explode('@', $r[2]);
            $clase = 'App\\Controllers\\' . $clase;
            $ctrl = new $clase();
            $ctrl->$accion($params);
            return;
        }
        http_response_code($metodoValido ? 405 : 404);
        View::render('errores/404', array('titulo' => 'Página no encontrada'), Auth::logueado() ? 'layouts/app' : null);
    }

    private function sinPermiso($metodo)
    {
        if ($metodo === 'GET') {
            Session::flash('aviso', 'No tiene permiso para entrar a esa opción.');
            header('Location: ' . url(''));
            exit;
        }
        http_response_code(403);
        exit('ERROR_ACCESO: no tiene permisos para realizar esta acción.');
    }
}
