<?php
namespace App\Services;

/**
 * Carga de la librería TCPDF (la misma carpeta de sisgedov2 mientras dura la
 * migración) con los ajustes que necesitan los PDFs del sistema.
 */
class PdfService
{
    public static function cargar()
    {
        // Un PDF es salida binaria: cualquier aviso impreso antes lo rompe. TCPDF es
        // una versión antigua y emite avisos "Deprecated" en PHP 8.4+: se apagan antes.
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        if (class_exists('TCPDF', false)) return;
        $base = rtrim(config('ruta_sisgedov2'), '/\\') . '/tcpdf';
        require_once $base . '/examples/config/tcpdf_config_alt.php';   // rutas de imágenes y fuentes
        require_once $base . '/tcpdf.php';
    }

    /** Descarta cualquier salida previa para que el PDF salga limpio. */
    public static function limpiarSalida()
    {
        while (ob_get_level() > 0) ob_end_clean();
    }
}
