<?php
namespace App\Services;

use App\Core\Database;

/**
 * Días hábiles y horario de atención (mismas reglas que sisgedov2/clases/calendario.php).
 *  - Día hábil: figura en SISGEDO_HORARIO, no es feriado nacional (fijos + Jueves/Viernes
 *    Santo) ni día no laborable registrado en la tabla sgd_dia_no_laborable.
 *  - Lo presentado fuera del horario cuenta desde el siguiente día hábil a la hora de inicio.
 */
class CalendarioService
{
    /** Domingo de Pascua (algoritmo gregoriano anónimo). */
    public static function pascua($anio)
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /** Feriados y días no laborables del año: ['Y-m-d' => motivo] */
    public static function feriados($anio)
    {
        static $cache = array();
        $anio = (int)$anio;
        if (isset($cache[$anio])) return $cache[$anio];
        $out = array();
        foreach ((defined('SISGEDO_FERIADOS_FIJOS') ? SISGEDO_FERIADOS_FIJOS : array()) as $md => $motivo) {
            $out[$anio . '-' . $md] = $motivo;
        }
        $pascua = strtotime(self::pascua($anio));
        $out[date('Y-m-d', strtotime('-3 days', $pascua))] = 'Jueves Santo';
        $out[date('Y-m-d', strtotime('-2 days', $pascua))] = 'Viernes Santo';
        try {
            foreach (Database::filas("SELECT fecha, motivo FROM sgd_dia_no_laborable WHERE fecha BETWEEN ? AND ?",
                array($anio . '-01-01', $anio . '-12-31'), \PDO::FETCH_NUM) as $f) {
                $out[$f[0]] = $f[1];
            }
        } catch (\Exception $e) {
            // tabla aún no creada (migración 008): solo feriados nacionales
        }
        return $cache[$anio] = $out;
    }

    /** [inicio, fin] del día o null si no es hábil. */
    public static function horario($ymd)
    {
        $horario = defined('SISGEDO_HORARIO') ? SISGEDO_HORARIO : array();
        $dow = (int)date('N', strtotime($ymd));
        if (!isset($horario[$dow])) return null;
        $fer = self::feriados((int)substr($ymd, 0, 4));
        return isset($fer[$ymd]) ? null : $horario[$dow];
    }

    public static function esHabil($ymd)
    {
        return self::horario($ymd) !== null;
    }

    public static function siguienteHabil($ymd)
    {
        $t = strtotime($ymd);
        for ($i = 1; $i <= 60; $i++) {
            $d = date('Y-m-d', strtotime("+$i day", $t));
            if (self::esHabil($d)) return $d;
        }
        return date('Y-m-d', strtotime('+1 day', $t));
    }

    /** $ymd + $n días hábiles (sin contar $ymd). */
    public static function sumarHabiles($ymd, $n)
    {
        $d = $ymd;
        for ($i = 0; $i < (int)$n; $i++) {
            $d = self::siguienteHabil($d);
        }
        return $d;
    }

    /** Fecha y hora en que se considera PRESENTADO lo enviado en $fechaHora. */
    public static function fechaPresentacion($fechaHora)
    {
        $t = strtotime((string)$fechaHora);
        $ymd = date('Y-m-d', $t);
        $hm = date('H:i', $t);
        $h = self::horario($ymd);
        if ($h !== null && $hm >= $h[0] && $hm < $h[1]) return date('Y-m-d H:i:s', $t);
        if ($h !== null && $hm < $h[0]) return $ymd . ' ' . $h[0] . ':00';
        $sig = self::siguienteHabil($ymd);
        $hs = self::horario($sig);
        return $sig . ' ' . ($hs ? $hs[0] : '08:00') . ':00';
    }

    public static function fueraDeHorario($fechaHora)
    {
        return strtotime(self::fechaPresentacion($fechaHora)) !== strtotime((string)$fechaHora);
    }

    /** Hora de cierre de un día hábil (23:59:59 si no es hábil). */
    public static function cierre($ymd)
    {
        $h = self::horario($ymd);
        return $ymd . ' ' . ($h ? $h[1] . ':00' : '23:59:59');
    }

    /** "lunes 16/11/2026 08:00 a. m." */
    public static function texto($fechaHora, $conHora = true)
    {
        $dias = array(1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo');
        $t = strtotime((string)$fechaHora);
        $txt = $dias[(int)date('N', $t)] . ' ' . date('d/m/Y', $t);
        if ($conHora) {
            $txt .= ' ' . date('h:i', $t) . (date('H', $t) < 12 ? ' a. m.' : ' p. m.');
        }
        return $txt;
    }
}
