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
    public static function parcial($__vista, array $__datos = array())
    {
        // Nombres internos con "__": así una vista puede recibir variables llamadas
        // $datos, $archivo o $vista sin que extract() las ignore.
        $__ruta = SISGEDO_RAIZ . '/app/Views/' . $__vista . '.php';
        if (!is_file($__ruta)) {
            throw new \RuntimeException('Vista no encontrada: ' . $__vista);
        }
        unset($__datos['__vista'], $__datos['__datos'], $__datos['__ruta'], $__datos['this']);
        extract($__datos);
        ob_start();
        require $__ruta;
        return ob_get_clean();
    }
}
