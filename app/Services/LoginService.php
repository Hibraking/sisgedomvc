<?php
namespace App\Services;

use App\Core\Session;
use App\Models\UsuarioModel;

/**
 * Inicio de sesión (mismas reglas que sisgedov2):
 *  - límite de intentos fallidos por usuario+IP (6 en 15 minutos);
 *  - contraseña bcrypt; si aún está en texto plano (dato antiguo) se compara
 *    y se cifra en ese momento;
 *  - cargo activo; renovación del ID de sesión; registro del último acceso.
 */
class LoginService
{
    const OK = 0;
    const CREDENCIALES = 1;
    const BLOQUEADO = 2;
    const ERROR = 3;

    /** @return int  self::OK | CREDENCIALES | BLOQUEADO | ERROR */
    public function ingresar($usuario, $clave, $ip)
    {
        $usuario = trim((string)$usuario);
        $llave = $ip . '|' . strtolower($usuario);
        $intentos = $this->leerIntentos();
        $reg = isset($intentos[$llave]) ? $intentos[$llave] : array('n' => 0, 'ts' => time());
        if ($reg['n'] >= (int)config('login_max_intentos', 6)) {
            return self::BLOQUEADO;
        }
        try {
            $fila = UsuarioModel::buscarParaLogin($usuario);
            if (!$fila || !$this->claveCorrecta((string)$clave, $fila)) {
                $reg['n']++;
                $intentos[$llave] = $reg;
                $this->guardarIntentos($intentos);
                return self::CREDENCIALES;
            }
        } catch (\Exception $e) {
            error_log('Login: ' . $e->getMessage());
            return self::ERROR;
        }
        unset($intentos[$llave]);
        $this->guardarIntentos($intentos);

        session_regenerate_id(true);                      // evita la fijación de sesión
        Session::set('usuario', (int)$fila['idpersonal']);
        Session::set('alias', $fila['usuario']);
        Session::set('idoficina', $fila['idoficina'] !== null ? (int)$fila['idoficina'] : 0);
        Session::set('idresponsable', $fila['idresponsables'] !== null ? (int)$fila['idresponsables'] : 0);
        Session::quitar('foto_perfil');
        Session::quitar('admin_original');
        Session::quitar('ver_oficina');
        Session::set('ultimo_acceso_anterior', UsuarioModel::registrarAcceso($fila['idpersonal']));
        return self::OK;
    }

    private function claveCorrecta($plano, array $fila)
    {
        $guardada = $fila['clave'];
        if ($guardada === null || $plano === '') {
            return false;
        }
        if (preg_match('/^\$2[aby]\$/', $guardada)) {
            return password_verify($plano, $guardada);
        }
        if (hash_equals((string)$guardada, $plano)) {     // clave antigua sin cifrar: se cifra ahora
            UsuarioModel::guardarClaveCifrada($fila['idpersonal'], password_hash($plano, PASSWORD_DEFAULT));
            return true;
        }
        return false;
    }

    private function archivo()
    {
        return SISGEDO_RAIZ . '/storage/logs/login_intentos.json';
    }

    private function leerIntentos()
    {
        $j = is_file($this->archivo()) ? json_decode((string)@file_get_contents($this->archivo()), true) : array();
        $j = is_array($j) ? $j : array();
        $ventana = (int)config('login_ventana_seg', 900);
        foreach ($j as $k => $v) {
            if (!isset($v['ts']) || time() - $v['ts'] > $ventana) unset($j[$k]);
        }
        return $j;
    }

    private function guardarIntentos(array $datos)
    {
        @file_put_contents($this->archivo(), json_encode($datos), LOCK_EX);
    }
}
