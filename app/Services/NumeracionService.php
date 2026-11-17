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

    /* ---------------- Documentos emitidos (correlativo por oficina + tipo + año) ---------------- */

    /** Formato del número emitido: {NUM} correlativo, {ANIO} año, {SIGLAS} de la oficina. Ej.: 045-2026-MPH/GM */
    const FORMATO_NUMERO = '{NUM}-{ANIO}-{SIGLAS}';
    const DIGITOS_NUMERO = 3;

    /** Siglas (nomenclatura) de la oficina; '' si no se configuraron. */
    public static function siglasOficina($idoficina)
    {
        if (!self::migracionOk()) return '';
        $v = Database::valor("SELECT COALESCE(TRIM(siglas), '') FROM oficinas WHERE idoficinas = ?", array((int)$idoficina));
        return $v === false ? '' : (string)$v;
    }

    /** Correlativo de un número escrito a mano, solo si es del año indicado ("027-2026-MPH/GM" -> 27). */
    public static function extraerCorrelativo($numero, $anio)
    {
        if (preg_match('/(\d{1,6})\s*-\s*(\d{4})/u', (string)$numero, $m)) {
            return ((int)$m[2] === (int)$anio) ? (int)$m[1] : 0;
        }
        return 0;
    }

    private static function semillaEmitidos($idoficina, $iddocumento, $anio)
    {
        $max = 0;
        foreach (Database::filas("SELECT numero FROM docs_emitidos WHERE idoficina_registro = ? AND iddocumento = ? AND fecha >= ?",
            array((int)$idoficina, (int)$iddocumento, $anio . '-01-01'), \PDO::FETCH_COLUMN) as $num) {
            $max = max($max, self::extraerCorrelativo($num, $anio));
        }
        return $max;
    }

    private static function claveEmitidos($idoficina, $iddocumento, $anio)
    {
        return 'EMI-' . (int)$idoficina . '-' . (int)$iddocumento . '-' . (int)$anio;
    }

    private static function formatearNumero($n, $anio, $siglas)
    {
        $num = str_pad((string)(int)$n, self::DIGITOS_NUMERO, '0', STR_PAD_LEFT);
        return rtrim(str_replace(array('{NUM}', '{ANIO}', '{SIGLAS}'), array($num, (string)$anio, $siglas), self::FORMATO_NUMERO), '-/ ');
    }

    /** Vista previa del próximo número (NO lo reserva). */
    public static function previsualizarEmitido($idoficina, $iddocumento)
    {
        $anio = (int)date('Y');
        $siglas = self::siglasOficina($idoficina);
        if ($siglas === '') {
            return array('ok' => false, 'numero' => '', 'correlativo' => 0, 'siglas' => '',
                'mensaje' => self::migracionOk()
                    ? 'Su oficina no tiene siglas configuradas (Mantenimiento > Oficinas). Ingrese el número manualmente.'
                    : 'La numeración automática aún no está habilitada (falta la migración 001). Ingrese el número manualmente.');
        }
        $actual = self::actual(self::claveEmitidos($idoficina, $iddocumento, $anio));
        if ($actual === null) {
            $actual = self::semillaEmitidos($idoficina, $iddocumento, $anio);
        }
        $n = $actual + 1;
        return array('ok' => true, 'numero' => self::formatearNumero($n, $anio, $siglas), 'correlativo' => $n, 'siglas' => $siglas, 'mensaje' => '');
    }

    /** Reserva y devuelve el siguiente número; null si la oficina no tiene siglas. */
    public static function reservarEmitido($idoficina, $iddocumento)
    {
        $anio = (int)date('Y');
        $siglas = self::siglasOficina($idoficina);
        if ($siglas === '') return null;
        $clave = self::claveEmitidos($idoficina, $iddocumento, $anio);
        $semilla = self::actual($clave) === null ? self::semillaEmitidos($idoficina, $iddocumento, $anio) : 0;
        return self::formatearNumero(self::siguiente($clave, $semilla), $anio, $siglas);
    }

    /** Número manual: adelanta el contador para no repetirlo después. */
    public static function registrarNumeroManual($idoficina, $iddocumento, $numero)
    {
        if (!self::migracionOk()) return;
        $anio = (int)date('Y');
        $n = self::extraerCorrelativo($numero, $anio);
        if ($n > 0) {
            Database::consulta("INSERT INTO sgd_secuencia (clave, ultimo) VALUES (?, ?) ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, ?)",
                array(self::claveEmitidos($idoficina, $iddocumento, $anio), $n, $n));
        }
    }

    /** ¿Ya existe ese número para la oficina y tipo de documento este año? */
    public static function numeroEmitidoExiste($idoficina, $iddocumento, $numero)
    {
        return (int)Database::valor("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro = ? AND iddocumento = ? AND fecha >= ? AND TRIM(numero) = TRIM(?)",
            array((int)$idoficina, (int)$iddocumento, date('Y') . '-01-01', (string)$numero)) > 0;
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
