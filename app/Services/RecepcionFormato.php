<?php
namespace App\Services;

/** Textos y formatos de la bandeja de Recepción (los mismos de sisgedov2). */
class RecepcionFormato
{
    public static function estado($tipo)
    {
        $tipo = trim((string)$tipo);
        $mapa = array('1' => 'Registrado, sin recepcionar', '2' => 'Emitido, sin recepcionar', '3' => 'Derivado, sin recepcionar',
                      'R' => 'Recepcionado', 'E' => 'Derivado');
        if (isset($mapa[$tipo])) return $mapa[$tipo];
        return $tipo !== '' ? $tipo : 'Por recepcionar';
    }

    /** Punto de color del estado */
    public static function punto($tipo)
    {
        return $tipo === 'R' ? 'd-blue' : ($tipo === 'E' ? 'd-amber' : 'd-green');
    }

    /** Color de la etiqueta del tipo de documento: purple | blue | green */
    public static function color($nombre)
    {
        $n = strtoupper((string)$nombre);
        if (strpos($n, 'OFICIO') !== false) return 'purple';
        if (strpos($n, 'INFORME') !== false) return 'blue';
        return 'green';
    }

    public static function norm($texto)
    {
        return mb_strtolower(trim((string)$texto), 'UTF-8');
    }

    public static function hace($fecha)
    {
        $t = strtotime((string)$fecha);
        if (!$t) return '';
        $s = max(0, time() - $t);
        if ($s < 60) return 'Hace ' . $s . ' seg';
        if ($s < 3600) return 'Hace ' . floor($s / 60) . ' min';
        if ($s < 86400) {
            $h = floor($s / 3600);
            return 'Hace ' . $h . ' hora' . ($h > 1 ? 's' : '');
        }
        $x = floor($s / 86400);
        return 'Hace ' . $x . ' día' . ($x > 1 ? 's' : '');
    }
}
