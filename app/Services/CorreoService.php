<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envío de correos por SMTP autenticado (PHPMailer), igual que sisgedov2/clases/correo.php.
 * Cada intento queda en la bitácora sgd_correo. Configuración: config/correo.php.
 *
 *   CorreoService::enviar('ciudadano@x.com', 'Asunto', CorreoService::plantilla('Título', $html),
 *                         ['tipo' => 'MPV_RECEPCION', 'referencia' => $exp]);
 */
class CorreoService
{
    private static $ultimoError = '';

    public static function config($clave = null)
    {
        static $c = null;
        if ($c === null) {
            // require dentro de una función aparte: las variables del archivo de
            // configuración no deben pisar las de este método.
            $c = (static function () {
                return require SISGEDO_RAIZ . '/config/correo.php';
            })();
        }
        return $clave === null ? $c : (isset($c[$clave]) ? $c[$clave] : null);
    }

    public static function ultimoError()
    {
        return self::$ultimoError;
    }

    private static function cargarLibreria()
    {
        if (!class_exists(PHPMailer::class, false)) {
            require_once SISGEDO_RAIZ . '/app/Libs/PHPMailer/Exception.php';
            require_once SISGEDO_RAIZ . '/app/Libs/PHPMailer/PHPMailer.php';
            require_once SISGEDO_RAIZ . '/app/Libs/PHPMailer/SMTP.php';
        }
    }

    /** Guarda el intento en la bitácora sgd_correo (o en el log si no existe la tabla). */
    public static function registrar($tipo, $referencia, $destinatario, $asunto, $estado, $detalle = null, $messageId = null)
    {
        $cortar = function ($s, $n) {
            return $s === null ? null : mb_substr((string)$s, 0, $n, 'UTF-8');
        };
        try {
            Database::consulta("INSERT INTO sgd_correo (tipo, referencia, destinatario, asunto, estado, detalle, message_id, servidor, idpersonal, ip)
                                VALUES (?,?,?,?,?,?,?,?,?,?)", array(
                $cortar($tipo, 40), $cortar($referencia, 45), $cortar($destinatario, 255), $cortar($asunto, 255),
                $estado, $cortar($detalle, 1000), $cortar($messageId, 255),
                $cortar(self::config('host') . ':' . self::config('puerto'), 120),
                Auth::logueado() ? Auth::id() : null,
                isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            ));
            return;
        } catch (\Exception $e) {
            error_log('SISGEDO correo: no se pudo guardar en bitácora: ' . $e->getMessage());
        }
        error_log("SISGEDO correo [$estado] $tipo ref=$referencia a=$destinatario" . ($detalle ? " — $detalle" : ''));
    }

    /** Plantilla HTML institucional. $cuerpoHtml ya debe venir escapado. Logo como cid:logo. */
    public static function plantilla($titulo, $cuerpoHtml)
    {
        $firma = '';
        if (defined('SISGEDO_MUNI_FIRMA') && SISGEDO_MUNI_FIRMA !== '') $firma .= h(SISGEDO_MUNI_FIRMA) . '<br>';
        if (defined('SISGEDO_MUNI_GESTION') && SISGEDO_MUNI_GESTION !== '') $firma .= h(SISGEDO_MUNI_GESTION);
        return '<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . h($titulo) . '</title></head>
<body style="margin:0;padding:0;background:#f2f4f8;font-family:Arial,Helvetica,sans-serif;color:#1f2933">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f8;padding:24px 0">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden">
  <tr><td style="background:#123a7a;padding:18px 24px;color:#ffffff">
    <img src="cid:logo" alt="" height="48" style="height:48px;vertical-align:middle;margin-right:12px;border:0">
    <span style="font-size:16px;font-weight:bold;vertical-align:middle">' . h(SISGEDO_ENTIDAD_MAYUS) . '</span>
  </td></tr>
  <tr><td style="padding:24px">
    <h1 style="font-size:20px;color:#123a7a;margin:0 0 16px">' . h($titulo) . '</h1>
    ' . $cuerpoHtml . '
    ' . ($firma !== '' ? '<p style="font-size:14px;color:#52606d;margin-top:24px">' . $firma . '</p>' : '') . '
  </td></tr>
  <tr><td style="background:#f7f9fc;padding:14px 24px;font-size:11px;color:#7b8794">
    Sistema de Trámite Documentario - ' . h(SISGEDO_ENTIDAD) . '.<br>
    Este es un mensaje automático; no es necesario responderlo.
  </td></tr>
</table>
</td></tr></table>
</body></html>';
    }

    /**
     * Envía un correo HTML. true si el servidor SMTP lo aceptó.
     * $opc: tipo, referencia, texto, responder_a, adjuntos [['ruta','nombre']], copia_enviados (bool)
     */
    public static function enviar($destino, $asunto, $html, array $opc = array())
    {
        $tipo = isset($opc['tipo']) ? $opc['tipo'] : 'GENERAL';
        $referencia = isset($opc['referencia']) ? (string)$opc['referencia'] : null;
        $destino = trim((string)$destino);
        self::$ultimoError = '';
        $c = self::config();

        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            self::$ultimoError = 'Dirección de correo no válida.';
            self::registrar($tipo, $referencia, $destino === '' ? '(vacío)' : $destino, $asunto, 'ERROR', self::$ultimoError);
            return false;
        }
        if (!$c['activo']) {
            self::$ultimoError = 'El envío de correos está desactivado (config/correo.php).';
            self::registrar($tipo, $referencia, $destino, $asunto, 'DESACTIVADO');
            return false;
        }
        if ($c['auth'] && $c['clave'] === '') {
            self::$ultimoError = 'Falta la contraseña SMTP (crear config/correo_clave.php).';
            self::registrar($tipo, $referencia, $destino, $asunto, 'ERROR', self::$ultimoError);
            return false;
        }

        self::cargarLibreria();
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $c['host'];
            $mail->Port = (int)$c['puerto'];
            $mail->SMTPAuth = (bool)$c['auth'];
            $mail->Timeout = (int)$c['timeout'];
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            if ($c['auth']) {
                $mail->Username = $c['usuario'];
                $mail->Password = $c['clave'];
            }
            if ($c['seguridad'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($c['seguridad'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            if (!$c['verificar_ssl']) {
                $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
            }
            $mail->setFrom($c['desde'], $c['nombre']);
            $responder = isset($opc['responder_a']) ? $opc['responder_a'] : $c['responder_a'];
            if ($responder !== '' && filter_var($responder, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($responder);
            }
            $mail->addAddress($destino);
            $logo = SISGEDO_RAIZ . '/public/assets/images/logo_correo.png';   // versión liviana (~10 KB)
            if (is_file($logo)) {
                $mail->addEmbeddedImage($logo, 'logo', basename($logo));
            }
            if (!empty($opc['adjuntos']) && is_array($opc['adjuntos'])) {
                foreach ($opc['adjuntos'] as $adj) {
                    if (!empty($adj['ruta']) && is_file($adj['ruta'])) {
                        $mail->addAttachment($adj['ruta'], !empty($adj['nombre']) ? $adj['nombre'] : basename($adj['ruta']));
                    }
                }
            }
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $html;
            $mail->AltBody = isset($opc['texto']) ? $opc['texto']
                : trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/h1|/tr)\s*/?>~i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
            $mail->send();
            self::registrar($tipo, $referencia, $destino, $asunto, 'ENVIADO', null, $mail->getLastMessageID());
            $copia = !isset($opc['copia_enviados']) || $opc['copia_enviados'];
            if ($copia && $c['guardar_enviados'] && !self::guardarEnEnviados($mail->getSentMIMEMessage(), $errImap)) {
                error_log('SISGEDO correo: no se guardó copia en Enviados (' . $referencia . '): ' . $errImap);
            }
            return true;
        } catch (PHPMailerException $e) {
            $detalle = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        } catch (\Exception $e) {
            $detalle = $e->getMessage();
        }
        self::$ultimoError = $detalle;
        self::registrar($tipo, $referencia, $destino, $asunto, 'ERROR', $detalle);
        return false;
    }

    /**
     * Copia del mensaje enviado en la carpeta "Enviados" (IMAP APPEND por socket,
     * sin la extensión imap retirada en PHP 8.4). true si el servidor la guardó.
     */
    public static function guardarEnEnviados($mime, &$error = '')
    {
        $error = '';
        $c = self::config();
        $mime = preg_replace("/\r\n|\r|\n/", "\r\n", (string)$mime);
        if ($mime === '') {
            $error = 'Mensaje vacío.';
            return false;
        }
        $host = $c['imap_host'];
        $esquema = $c['imap_seguridad'] === 'ssl' ? 'ssl' : 'tcp';
        $ctx = stream_context_create(array('ssl' => $c['verificar_ssl']
            ? array('verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true)
            : array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)));
        $espera = max(3, min(10, (int)$c['timeout']));
        $s = @stream_socket_client("$esquema://$host:" . (int)$c['imap_puerto'], $errno, $errstr, $espera, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) {
            $error = "No se pudo conectar a $host ($errstr).";
            return false;
        }
        stream_set_timeout($s, $espera);
        $n = 0;
        $leer = function () use ($s) {
            return fgets($s, 8192);
        };
        $cmd = function ($texto) use ($s, &$n, $leer) {
            $tag = 'A' . (++$n);
            fwrite($s, $tag . ' ' . $texto . "\r\n");
            while (($l = $leer()) !== false) {
                if (strpos($l, $tag . ' ') === 0) return rtrim($l);
            }
            return $tag . ' NO (sin respuesta del servidor)';
        };
        $q = function ($v) {
            return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), (string)$v) . '"';
        };
        $ok = function ($r) {
            return (bool)preg_match('/^A\d+ OK/i', $r);
        };
        try {
            $saludo = $leer();
            if ($saludo === false || stripos($saludo, '* OK') !== 0) {
                $error = 'El servidor IMAP no respondió el saludo.';
                return false;
            }
            $r = $cmd('LOGIN ' . $q($c['usuario']) . ' ' . $q($c['clave']));
            if (!$ok($r)) {
                $error = 'IMAP rechazó el usuario/contraseña.';
                return false;
            }
            $tag = 'A' . (++$n);
            fwrite($s, $tag . ' APPEND ' . $q($c['carpeta_enviados']) . ' (\\Seen) {' . strlen($mime) . "}\r\n");
            $resp = '';
            while (($l = $leer()) !== false) {
                if ($l[0] === '+') break;
                if (strpos($l, $tag . ' ') === 0) { $resp = rtrim($l); break; }
            }
            if ($resp === '') {
                fwrite($s, $mime . "\r\n");
                while (($l = $leer()) !== false) {
                    if (strpos($l, $tag . ' ') === 0) { $resp = rtrim($l); break; }
                }
            }
            if (!$ok($resp)) {
                $error = 'IMAP no guardó la copia: ' . ($resp !== '' ? $resp : 'sin respuesta');
                return false;
            }
            return true;
        } finally {
            @$cmd('LOGOUT');
            @fclose($s);
        }
    }
}
