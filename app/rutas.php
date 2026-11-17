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

// ---- Etapa 3: Mesa de Partes Virtual (operador) ----
$permMpv = array('permiso' => 'vistas/v_mesa_partes_virtual.php');
$r->get('mesa-partes-virtual', 'MesaPartesController@index', $permMpv);
$r->post('mesa-partes-virtual/accion', 'MesaPartesController@accion', $permMpv);

// ---- Etapa 3: páginas públicas (sin sesión) ----
$pub = array('publica' => true);
$r->get('mesa-partes', 'PublicoController@mesaPartes', $pub);
// Formulario del ciudadano: sin token CSRF, igual que sisgedov2 (no hay sesión que proteger
// y una página abierta mucho tiempo no debe fallar al enviar)
$r->post('mesa-partes/enviar', 'PublicoController@presentar', $pub + array('csrf' => false));
$r->get('consulta', 'PublicoController@consulta', $pub);
$r->get('subsanar', 'PublicoController@subsanar', $pub);
$r->post('subsanar', 'PublicoController@subsanar', $pub + array('csrf' => false));   // valida el token y avisa con un mensaje claro
$r->get('verificar', 'PublicoController@verificar', $pub);
// Direcciones de sisgedov2 (correos enviados y códigos QR ya impresos)
foreach (array('mpvirtual.php' => 'mesa-partes', 'consulta_publico.php' => 'consulta', 'pdf/consulta_publico.php' => 'consulta',
               'subsanar.php' => 'subsanar', 'verificar.php' => 'verificar', 'pdf/verificar.php' => 'verificar') as $antigua => $nueva) {
    $r->get($antigua, 'PublicoController@antigua', $pub + array('datos' => array('destino' => $nueva)));
}

// ---- Módulos del menú aún no migrados (etapas 4 a 6) ----
$migradas = array('recepcion', 'derivados', 'mesa-partes-virtual');
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
