<?php
namespace App\Core;

/**
 * Plantillas PHP (app/Views). Una vista puede pedir un layout:
 *   View::render('inicio/index', $datos, 'layouts/app')
 * El layout recibe el HTML de la vista en $contenido.
 */
class View
{
    public static function render($vista, array $datos = array(), $layout = null)
    {
        $contenido = self::parcial($vista, $datos);
        if ($layout === null) {
            echo $contenido;
            return;
        }
        echo self::parcial($layout, array_merge($datos, array('contenido' => $contenido)));
    }

    /** Devuelve el HTML de una plantilla (para incluir piezas dentro de otras). */
    public static function parcial($vista, array $datos = array())
    {
        $archivo = SISGEDO_RAIZ . '/app/Views/' . $vista . '.php';
        if (!is_file($archivo)) {
            throw new \RuntimeException('Vista no encontrada: ' . $vista);
        }
        extract($datos, EXTR_SKIP);
        ob_start();
        require $archivo;
        return ob_get_clean();
    }
}
