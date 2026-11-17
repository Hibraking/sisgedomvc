<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Archivos adjuntos y Código de Verificación Digital (CVD) — mismas reglas que
 * sisgedov2 (clases/util_subida.php y clases/gestion_documental.php, MGD).
 * Los archivos siguen guardándose en las carpetas de sisgedov2 mientras dure la migración.
 */
class ArchivoService
{
    const PERMITIDAS = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv', 'zip', 'rar');
    const PELIGROSAS = array('php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'sh', 'exe', 'bat', 'htaccess');
    const MAX_BYTES = 15728640;   // 15 MB

    /** Carpeta en disco: 'REGISTRO' (docs adjs reg_doc) | 'EMITIDO' (docs adjs docs_emitidos) */
    public static function carpeta($origen)
    {
        return rtrim(config('ruta_sisgedov2'), '/\\') . '/' . ($origen === 'EMITIDO' ? 'docs adjs docs_emitidos' : 'docs adjs reg_doc');
    }

    /** @return true|string  true o el motivo del rechazo */
    public static function validar($file)
    {
        if (!is_array($file)) return 'No se recibió ningún archivo.';
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return 'Ocurrió un error al subir el archivo.';
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) return 'El archivo está vacío o supera el tamaño máximo permitido (15 MB).';
        foreach (explode('.', strtolower((string)$file['name'])) as $p) {
            if (in_array($p, self::PELIGROSAS, true)) return 'Nombre de archivo no permitido por seguridad.';
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::PERMITIDAS, true)) {
            return 'Tipo de archivo no permitido. Solo se aceptan: ' . implode(', ', self::PERMITIDAS) . '.';
        }
        return true;
    }

    /** Nombre aleatorio en disco (no se usa el nombre que envía el usuario). */
    public static function nombreSeguro($file)
    {
        return bin2hex(random_bytes(8)) . '_' . time() . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    }

    /**
     * Valida, guarda y registra la huella del archivo subido.
     * @return array ['ok'=>bool, 'msg'=>..., 'nombre'=>en disco, 'ruta'=>completa]
     */
    public static function guardar($file, $origen, $canal)
    {
        $v = self::validar($file);
        if ($v !== true) return array('ok' => false, 'msg' => $v);
        $nombre = self::nombreSeguro($file);
        $ruta = self::carpeta($origen) . '/' . $nombre;
        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $ruta)) {
            return array('ok' => false, 'msg' => 'No se pudo guardar el archivo. Intente nuevamente.');
        }
        self::registrar($origen, $nombre, $ruta, $file['name'], $canal);
        return array('ok' => true, 'msg' => '', 'nombre' => $nombre, 'ruta' => $ruta);
    }

    /** Metadatos y huella SHA-256 (tabla sgd_archivo). Nunca interrumpe el trámite. */
    public static function registrar($origen, $nombreAlmacenado, $ruta, $nombreOriginal = null, $canal = 'INTERNO')
    {
        try {
            if (!is_file($ruta) || !NumeracionService::migracionOk()) return null;
            $hash = hash_file('sha256', $ruta);
            $mime = function_exists('finfo_open') ? (new \finfo(FILEINFO_MIME_TYPE))->file($ruta) : null;
            Database::consulta("INSERT INTO sgd_archivo
                    (origen, nombre_almacenado, nombre_original, extension, mime, tamano_bytes, hash_sha256, canal, idpersonal, ip)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE hash_sha256 = VALUES(hash_sha256), tamano_bytes = VALUES(tamano_bytes), mime = VALUES(mime)",
                array($origen, $nombreAlmacenado, $nombreOriginal !== null ? substr(basename((string)$nombreOriginal), 0, 255) : null,
                      strtolower(pathinfo($nombreAlmacenado, PATHINFO_EXTENSION)), $mime ?: null, filesize($ruta), $hash, $canal,
                      Auth::logueado() ? Auth::id() : null, isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null));
            return $hash;
        } catch (\Exception $e) {
            error_log('ArchivoService::registrar: ' . $e->getMessage());
            return null;
        }
    }

    public static function obtener($origen, $nombreAlmacenado)
    {
        if ((string)$nombreAlmacenado === '' || !NumeracionService::migracionOk()) return null;
        return Database::fila("SELECT * FROM sgd_archivo WHERE origen = ? AND nombre_almacenado = ?", array($origen, $nombreAlmacenado));
    }

    /** true = intacto, false = alterado o ausente, null = sin huella registrada. */
    public static function integro($origen, $nombreAlmacenado)
    {
        $meta = self::obtener($origen, $nombreAlmacenado);
        if (!$meta) return null;
        $ruta = self::carpeta($origen) . '/' . $nombreAlmacenado;
        return is_file($ruta) ? hash_equals($meta['hash_sha256'], hash_file('sha256', $ruta)) : false;
    }

    /* ---------------- Código de verificación digital (CVD) ---------------- */

    public static function normalizarCvd($cvd)
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$cvd));
    }

    public static function formatoCvd($cvd)
    {
        return strlen($cvd) === 8 ? substr($cvd, 0, 4) . '-' . substr($cvd, 4) : $cvd;
    }

    /** CVD de un expediente ('EXPEDIENTE', nro_exp) o emitido ('EMITIDO', id); lo crea si no tiene. */
    public static function cvd($tipo, $referencia)
    {
        try {
            if (!NumeracionService::migracionOk()) return null;
            $buscar = function () use ($tipo, $referencia) {
                return Database::valor("SELECT cvd FROM sgd_verificacion WHERE tipo = ? AND referencia = ?", array($tipo, (string)$referencia));
            };
            if ($c = $buscar()) return $c;
            $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';     // sin 0/O ni 1/I/L
            for ($intento = 0; $intento < 5; $intento++) {
                $nuevo = '';
                for ($i = 0; $i < 8; $i++) $nuevo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                try {
                    Database::consulta("INSERT INTO sgd_verificacion (cvd, tipo, referencia) VALUES (?,?,?)", array($nuevo, $tipo, (string)$referencia));
                    return $nuevo;
                } catch (\PDOException $e) {
                    if ($c = $buscar()) return $c;              // se registró en paralelo
                }
            }
        } catch (\Exception $e) {
            error_log('ArchivoService::cvd: ' . $e->getMessage());
        }
        return null;
    }

    public static function urlVerificacion($cvd)
    {
        return \App\Core\Url::absoluta('verificar') . '?c=' . rawurlencode(self::formatoCvd($cvd));
    }
}
