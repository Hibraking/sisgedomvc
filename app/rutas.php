<?php
/* =====================================================================
   SISGEDO MVC — Rutas
   ---------------------------------------------------------------------
   Cada etapa de la migración reemplaza aquí las rutas "pendientes" por
   su controlador real.
   ===================================================================== */
use App\Services\MenuService;

/** @var App\Core\Router $r */

// ---- Acceso ----
$r->get('login', 'AuthController@mostrar', array('publica' => true));
$r->post('login', 'AuthController@ingresar', array('publica' => true));
$r->get('salir', 'AuthController@salir');

// ---- Inicio y piezas comunes ----
$r->get('', 'InicioController@index');
$r->post('oficina-trabajo', 'OficinaTrabajoController@cambiar', array('admin' => true));
$r->get('foto-perfil', 'FotoController@perfil');

// ---- Módulos del menú aún no migrados (etapas 2 a 6) ----
foreach (MenuService::definicion() as $grupo) {
    foreach ($grupo['items'] as $it) {
        $r->get($it[1], 'PendienteController@modulo', array('permiso' => $it[3], 'datos' => array('ruta' => $it[1])));
    }
}
$r->get('supervision', 'PendienteController@modulo', array('admin' => true, 'datos' => array(
    'ruta' => 'supervision', 'titulo' => 'Supervisión de oficinas', 'icono' => 'fa-th-list', 'etapa' => 5, 'antiguo' => 'vistas/v_supervision.php')));
$r->get('perfil', 'PendienteController@modulo', array('datos' => array(
    'ruta' => 'perfil', 'titulo' => 'Mi perfil', 'icono' => 'fa-user', 'etapa' => 6, 'antiguo' => 'vistas/v_profile.php')));
