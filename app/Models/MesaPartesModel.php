<?php
namespace App\Models;

use App\Core\Database;

/** Mesa de Partes Virtual (en la etapa 1 solo lo que usa el menú lateral). */
class MesaPartesModel
{
    /** ¿Están aplicadas las migraciones 007 y 008 de la Mesa de Partes Virtual? */
    public static function moduloActivo()
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = (int)Database::valor("SELECT COUNT(*) FROM information_schema.columns
                        WHERE table_schema = DATABASE() AND table_name = 'registro_documentos'
                          AND column_name IN ('mpv_recepcion', 'mpv_estado')") === 2;
            } catch (\Exception $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Número rojo del menú: por recepcionar (incluye subsanados) + observados con
     * plazo vencido + recepcionados por derivar. Dos conteos que usan índices.
     */
    public static function porAtender()
    {
        if (!self::moduloActivo()) return 0;
        return (int)Database::valor("SELECT
              (SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_recepcion IS NULL
                 AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO' OR (mpv_estado = 'OBSERVADO' AND mpv_plazo < CURDATE())))
            + (SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND idoficina IS NULL AND mpv_idpersonal IS NOT NULL
                 AND mpv_recepcion IS NOT NULL AND COALESCE(estado_recepcion,0) = 0)");
    }
}
