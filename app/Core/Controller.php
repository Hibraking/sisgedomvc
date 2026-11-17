<?php
namespace App\Core;

/** Base de los controladores: mostrar vistas, redirigir y responder JSON. */
abstract class Controller
{
    protected function vista($vista, array $datos = array(), $layout = 'layouts/app')
    {
        View::render($vista, $datos, $layout);
    }

    protected function redirigir($ruta)
    {
        header('Location: ' . (preg_match('~^https?://~', $ruta) ? $ruta : url($ruta)));
        exit;
    }

    protected function json($datos, $codigo = 200)
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos);
        exit;
    }

    protected function post($clave, $porDefecto = '')
    {
        return isset($_POST[$clave]) ? (is_string($_POST[$clave]) ? trim($_POST[$clave]) : $_POST[$clave]) : $porDefecto;
    }

    protected function get($clave, $porDefecto = '')
    {
        return isset($_GET[$clave]) && is_string($_GET[$clave]) ? trim($_GET[$clave]) : $porDefecto;
    }
}
