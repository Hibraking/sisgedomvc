<?php
namespace App\Services;

use App\Core\Database;

/**
 * Numeración (MGD) — mismas reglas que sisgedov2/clases/gestion_documental.php.
 * Secuencias atómicas en la tabla sgd_secuencia: no se repiten números aunque
 * dos personas registren en el mismo instante.
 */
class NumeracionService
{
    /** ¿Existe sgd_secuencia? (migración 001). Sin ella, modo compatibilidad. */
    public static function migracionOk()
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = (int)Database::valor("SELECT COUNT(*) FROM information_schema.tables
                                             WHERE table_schema = DATABASE() AND table_name = 'sgd_secuencia'") > 0;
            } catch (\Exception $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** ¿movimientos.num_exp admite el formato con siglas? (migración 013) */
    public static function registroConSiglasOk()
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = (int)Database::valor("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns
                                             WHERE table_schema = DATABASE() AND table_name = 'movimientos' AND column_name = 'num_exp'") >= 30;
            } catch (\Exception $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** Siguiente valor de la secuencia $clave (atómico); si no existe arranca desde $semilla. */
    public static function siguiente($clave, $semilla = 0)
    {
        $db = Database::pdo();
        $up = $db->prepare("UPDATE sgd_secuencia SET ultimo = LAST_INSERT_ID(ultimo + 1) WHERE clave = ?");
        $up->execute(array($clave));
        if ($up->rowCount() > 0) {
            return (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        }
        $ins = $db->prepare("INSERT INTO sgd_secuencia (clave, ultimo) VALUES (?, LAST_INSERT_ID(?))
                             ON DUPLICATE KEY UPDATE ultimo = LAST_INSERT_ID(ultimo + 1)");
        $ins->execute(array($clave, (int)$semilla + 1));
        return (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
    }

    public static function actual($clave)
    {
        $v = Database::valor("SELECT ultimo FROM sgd_secuencia WHERE clave = ?", array($clave));
        return $v === false ? null : (int)$v;
    }

    /** Prefijo del registro de la oficina: sus siglas sin "MPH/" (ej. GODUyR); "OF<id>" si no tiene. */
    public static function prefijoRegistro($idoficina)
    {
        $s = '';
        if (self::migracionOk()) {
            $v = Database::valor("SELECT COALESCE(TRIM(siglas), '') FROM oficinas WHERE idoficinas = ?", array((int)$idoficina));
            $s = $v === false ? '' : (string)$v;
        }
        $s = preg_replace('~^' . preg_quote(SISGEDO_MUNI_SIGLAS, '~') . '\s*/\s*~i', '', $s);
        $s = trim(preg_replace('~[^A-Za-z0-9]+~', '-', $s), '-');
        return $s !== '' ? mb_substr($s, 0, 14) : 'OF' . (int)$idoficina;
    }

    /** Número de expediente (mesa de partes presencial y virtual): AÑO + correlativo de 4+ dígitos. */
    public static function nuevoExpediente()
    {
        $anio = (int)date('Y');
        $maxUsado = function () use ($anio) {
            // comparación NUMÉRICA (MAX sobre texto fallaba al pasar de 9999)
            return (int)Database::valor("SELECT MAX(CAST(SUBSTRING(nro_exp, 5) AS UNSIGNED)) FROM registro_documentos
                                          WHERE nro_exp LIKE ? AND fecha_registro >= ?", array($anio . '%', $anio . '-01-01'));
        };
        if (!self::migracionOk()) {
            return $anio . str_pad((string)($maxUsado() + 1), 4, '0', STR_PAD_LEFT);
        }
        $clave = 'EXP-' . $anio;
        $semilla = self::actual($clave) === null ? $maxUsado() : 0;
        return $anio . str_pad((string)self::siguiente($clave, $semilla), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Número de registro interno de la oficina al recepcionar: SIGLAS-AÑO-NNNN
     * (o el formato antiguo AÑONNNN mientras no se aplique la migración 013).
     */
    public static function registroInterno($idoficina)
    {
        $idoficina = (int)$idoficina;
        $anio = (int)date('Y');
        $clave = 'MOV-' . $idoficina . '-' . $anio;
        $semilla = 0;
        $sinMigracion = !self::migracionOk();
        if ($sinMigracion || self::actual($clave) === null) {
            // Mayor correlativo ya usado este año, en el formato antiguo o en el nuevo
            $semilla = (int)Database::valor("SELECT MAX(CASE WHEN m.num_exp REGEXP '^[0-9]+$'
                                                    THEN CAST(SUBSTRING(m.num_exp, 5) AS UNSIGNED)
                                                    ELSE CAST(SUBSTRING_INDEX(m.num_exp, '-', -1) AS UNSIGNED) END)
                                               FROM movimientos m
                                               INNER JOIN responsables r ON m.idresponsable = r.idresponsables
                                              WHERE r.idoficina = ? AND m.fecha_hora >= ?
                                                AND (m.num_exp LIKE ? OR m.num_exp LIKE ?)",
                array($idoficina, $anio . '-01-01', $anio . '%', '%-' . $anio . '-%'));
        }
        $n = $sinMigracion ? $semilla + 1 : self::siguiente($clave, $semilla);
        $corr = str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        if ($sinMigracion || !self::registroConSiglasOk()) {
            return $anio . $corr;
        }
        return self::prefijoRegistro($idoficina) . '-' . $anio . '-' . $corr;
    }
}
