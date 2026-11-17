<?php
namespace App\Services;

/** Carga de la librería TCPDF propia (app/Libs/tcpdf) con la configuración de config/tcpdf.php. */
class PdfService
{
    public static function cargar()
    {
        // Un PDF es salida binaria: cualquier aviso impreso antes lo rompe. TCPDF es
        // una versión antigua y emite avisos "Deprecated" en PHP 8.4+: se apagan antes.
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        if (class_exists('TCPDF', false)) return;
        if (!is_dir(SISGEDO_RAIZ . '/storage/cache')) @mkdir(SISGEDO_RAIZ . '/storage/cache', 0755, true);
        require_once SISGEDO_RAIZ . '/config/tcpdf.php';
        require_once SISGEDO_RAIZ . '/app/Libs/tcpdf/tcpdf.php';
    }

    /** Descarta cualquier salida previa para que el PDF salga limpio. */
    public static function limpiarSalida()
    {
        while (ob_get_level() > 0) ob_end_clean();
    }
}
