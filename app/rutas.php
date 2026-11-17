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

// ---- Etapa 4: Registro (mesa de partes presencial), Emisión, firma y cargos PDF ----
$permReg = array('permiso' => 'vistas/o_reg_documentos.php');
$r->get('registro', 'RegistroController@index', $permReg);
$r->post('registro/accion', 'RegistroController@accion', $permReg);
$r->post('registro/reniec', 'RegistroController@reniec', $permReg);
$permEmi = array('permiso' => 'vistas/v_docs_emitidos.php');
$r->get('emision', 'EmisionController@index', $permEmi);
$r->post('emision/accion', 'EmisionController@accion', $permEmi);
// Firma Perú: el firmador llama sin sesión (token de un solo uso); iniciar/estado exigen sesión y CSRF en el controlador
$r->get('firma', 'FirmaController@despachar', array('publica' => true));
$r->post('firma', 'FirmaController@despachar', array('publica' => true, 'csrf' => false));
// Cargos: con sesión por N°, o el ciudadano con su código de verificación
$r->get('cargo', 'CargoController@externo', array('publica' => true));
$r->get('cargo-interno', 'CargoController@interno', array('publica' => true));
foreach (array('pdf/cargo.php' => 'cargo', 'tcpdf/examples/cargo_externo.php' => 'cargo', 'pdf/cargo_interno.php' => 'cargo-interno') as $antigua => $nueva) {
    $r->get($antigua, 'PublicoController@antigua', $pub + array('datos' => array('destino' => $nueva)));
}

// ---- Etapa 5: Archivo y Supervisión ----
$permArch = array('permiso' => 'vistas/v_archivar_docs.php');
$r->get('archivo', 'ArchivoController@index', $permArch);
$r->post('archivo/accion', 'ArchivoController@accion', $permArch);
$r->get('supervision', 'SupervisionController@index', array('admin' => true));

// ---- Etapa 6: Reportes, Mantenimiento, Configuración y Mi perfil ----
$permDe = function ($ruta) {
    $it = MenuService::itemPorRuta($ruta);
    return array('permiso' => $it[3]);
};
// Catálogos simples (config/catalogos.php)
foreach (array('oficinas', 'documentos', 'tipos-tramite', 'acciones', 'estados', 'archivadores', 'menus') as $clave) {
    $def = App\Models\CatalogoModel::definicion($clave);
    $r->get($def['ruta'], 'CatalogoController@index', $permDe($def['ruta']) + array('datos' => array('clave' => $clave)));
    $r->post($def['ruta'] . '/accion', 'CatalogoController@accion', $permDe($def['ruta']) + array('datos' => array('clave' => $clave)));
}
// Pantallas propias de mantenimiento
foreach (array('mantenimiento/remitentes' => 'remitentes', 'mantenimiento/personal' => 'personal',
               'mantenimiento/responsables' => 'responsables', 'configuracion/accesos' => 'accesos') as $ruta => $metodo) {
    $r->get($ruta, 'MantenimientoController@' . $metodo, $permDe($ruta));
    $r->post($ruta . '/accion', 'MantenimientoController@' . $metodo . 'Accion', $permDe($ruta));
}
// Reportes
$r->get('reportes/consulta-interna', 'ReporteController@consultaInterna', $permDe('reportes/consulta-interna'));
$r->get('reportes/estados', 'ReporteController@estados', $permDe('reportes/estados'));
$r->get('reportes/cuaderno-externo', 'ReporteController@cuadernoExterno', $permDe('reportes/cuaderno-externo'));
$r->get('reportes/cuaderno-externo/pdf', 'ReporteController@pdfExterno', $permDe('reportes/cuaderno-externo'));
$r->get('reportes/cuaderno-interno', 'ReporteController@cuadernoInterno', $permDe('reportes/cuaderno-interno'));
$r->get('reportes/cuaderno-interno/pdf', 'ReporteController@pdfInterno', $permDe('reportes/cuaderno-interno'));
// Mi perfil (todo usuario con sesión)
$r->get('perfil', 'PerfilController@index');
$r->post('perfil/accion', 'PerfilController@accion');

// Todas las opciones del menú están migradas (etapas 1 a 6).
