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

// ---- Etapa 2: Recepción y Derivados ----
$permRecepcion = array('permiso' => 'vistas/v_recepcion_proveido.php');
$r->get('recepcion', 'RecepcionController@index', $permRecepcion + array('datos' => array('base' => 'recepcion')));
$r->get('derivados', 'RecepcionController@index', $permRecepcion + array('datos' => array('base' => 'derivados')));
$r->post('recepcion/accion', 'RecepcionController@accion', $permRecepcion);
$r->get('adjunto', 'AdjuntoController@ver');
$r->get('notificaciones', 'NotificacionController@consultar');

// ---- Módulos del menú aún no migrados (etapas 3 a 6) ----
$migradas = array('recepcion', 'derivados');
foreach (MenuService::definicion() as $grupo) {
    foreach ($grupo['items'] as $it) {
        if (in_array($it[1], $migradas, true)) continue;
        $r->get($it[1], 'PendienteController@modulo', array('permiso' => $it[3], 'datos' => array('ruta' => $it[1])));
    }
}
$r->get('supervision', 'PendienteController@modulo', array('admin' => true, 'datos' => array(
    'ruta' => 'supervision', 'titulo' => 'Supervisión de oficinas', 'icono' => 'fa-th-list', 'etapa' => 5, 'antiguo' => 'vistas/v_supervision.php')));
$r->get('perfil', 'PendienteController@modulo', array('datos' => array(
    'ruta' => 'perfil', 'titulo' => 'Mi perfil', 'icono' => 'fa-user', 'etapa' => 6, 'antiguo' => 'vistas/v_profile.php')));
