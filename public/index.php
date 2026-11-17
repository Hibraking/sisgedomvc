<?php
/* =====================================================================
   SISGEDO MVC — Punto de entrada único (front controller)
   Toda petición web pasa por aquí: arranque -> rutas -> controlador.
   ===================================================================== */
require dirname(__DIR__) . '/app/bootstrap.php';

$r = new App\Core\Router();
require SISGEDO_RAIZ . '/app/rutas.php';

$r->despachar(isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET', App\Core\Url::rutaActual());
