<?php
namespace App\Services;

use App\Core\Database;

/** Firma digital con FIRMA PERÚ: configuración, solicitudes y token de acceso (como sisgedov2/clases/firmaperu.php). */
class FirmaService
{
    public static function config($clave = null)
    {
        static $c = null;
        if ($c === null) {
            $c = (static function () {
                return require SISGEDO_RAIZ . '/config/firmaperu.php';
            })();
        }
        return $clave === null ? $c : (isset($c[$clave]) ? $c[$clave] : null);
    }

    /** ¿Está configurada la firma digital (existe el archivo de credenciales)? */
    public static function activo()
    {
        $f = self::config('credenciales');
        return is_file($f) && is_readable($f);
    }

    /** Solicitud de firma PENDIENTE y vigente por su token (o null). */
    public static function solicitud($token)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) return null;
        return Database::fila("SELECT f.*, de.adjunto, de.idoficina_registro
                                 FROM sgd_firma f INNER JOIN docs_emitidos de ON de.iddocs_emitidos = f.iddocs_emitidos
                                WHERE f.param_token = ? AND f.estado = 'PENDIENTE' AND f.token_expira > NOW()", array($token));
    }

    /** Token JWT de FIRMA PERÚ (se reutiliza hasta que vence, como recomienda la PCM). */
    public static function tokenAcceso()
    {
        $cache = SISGEDO_RAIZ . '/storage/logs/firmaperu_token.json';
        if (is_file($cache)) {
            $c = json_decode((string)@file_get_contents($cache), true);
            if (is_array($c) && !empty($c['token']) && isset($c['exp']) && $c['exp'] > time() + 60) return $c['token'];
        }
        $cred = json_decode((string)file_get_contents(self::config('credenciales')), true);
        if (!is_array($cred) || empty($cred['client_id']) || empty($cred['client_secret']) || empty($cred['token_url'])) {
            throw new \Exception('fwAuthorization.json incompleto (client_id, client_secret, token_url).');
        }
        $ch = curl_init($cred['token_url']);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array('client_id' => $cred['client_id'], 'client_secret' => $cred['client_secret'])),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        ));
        $token = trim((string)curl_exec($ch));
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http !== 200 || $token === '') {
            throw new \Exception('No se pudo obtener el token de FIRMA PERÚ (HTTP ' . $http . ').');
        }
        $exp = time() + 600;                                  // vencimiento del propio JWT; si no, 10 min
        $partes = explode('.', $token);
        if (count($partes) === 3) {
            $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
            if (is_array($payload) && !empty($payload['exp'])) $exp = (int)$payload['exp'];
        }
        @file_put_contents($cache, json_encode(array('token' => $token, 'exp' => $exp)), LOCK_EX);
        return $token;
    }
}
