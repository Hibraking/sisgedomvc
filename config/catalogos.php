<?php
/* =====================================================================
   SISGEDO MVC — Catálogos de Mantenimiento y Configuración
   ---------------------------------------------------------------------
   Lo usan CatalogoController / CatalogoModel con la vista catalogo/index
   (mismo diseño que sisgedov2/partes/plantilla_catalogo.php). Por catálogo:
     ruta      dirección en el sistema        permiso   menu.direccion (accesos)
     tabla/pk  tabla y clave primaria          lista     SELECT: pk, col1, col2...
     columnas  títulos de la tabla              campos    [post, label, tipo, req, columna]
     kpis      [etiqueta, SQL de conteo, subtítulo, icono, tinte, lab]
     unico     columna que no puede repetirse (opcional)
     oficina   true = solo los de la oficina en sesión (columna idoficina)
     extra     columnas fijas al guardar: [columna => post]  (opcional)
   Los SQL de "lista" y "kpis" con ":oficina" reciben la oficina en sesión.
   ===================================================================== */
$anio = "makedate(year(now()),1) and %s < makedate(year(now())+1,1)";
$deEsteAnio = function ($col) use ($anio) { return "$col >= " . sprintf($anio, $col); };

return array(
    'oficinas' => array(
        'ruta' => 'mantenimiento/oficinas', 'permiso' => 'vistas/v_oficinas.php',
        'titulo' => 'Oficinas', 'subtitulo' => 'Catálogo de oficinas y dependencias de la entidad',
        'icono' => 'fa-building-o', 'singular' => 'la oficina',
        'tabla' => 'oficinas', 'pk' => 'idoficinas',
        'lista' => "SELECT idoficinas, nombre, COALESCE(descripcion,''), COALESCE(siglas,'') FROM oficinas ORDER BY nombre ASC",
        'columnas' => array('Nombre', 'Descripción', 'Siglas (numeración)'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre'),
            array('Descripcion', 'Descripción', 'textarea', false, 'descripcion'),
            array('Siglas', 'Siglas para numerar documentos (ej. MPH/GM, MPH-OTIyC)', 'text', false, 'siglas'),
        ),
        'vacio_nulo' => array('siglas'),        // siglas vacías = NULL (sin numeración automática)
        'kpis' => array(
            array('Con responsable', "SELECT COUNT(DISTINCT idoficina) FROM responsables WHERE estado=1", 'Responsable activo asignado', 'fa-user', 't-green', 'lab-green'),
            array('Activas este mes', "SELECT COUNT(DISTINCT idoficina_derivar) FROM movimientos WHERE fecha_hora >= date_format(now(),'%Y-%m-01') and fecha_hora < date_format(now(),'%Y-%m-01') + interval 1 month", 'Recibieron documentos', 'fa-inbox', 't-purple', 'lab-purple'),
        ),
    ),
    'documentos' => array(
        'ruta' => 'mantenimiento/documentos', 'permiso' => 'vistas/v_documentos.php',
        'titulo' => 'Tipos de Documento', 'subtitulo' => 'Catálogo de tipos de documento (oficio, informe, solicitud, etc.)',
        'icono' => 'fa-file-text-o', 'singular' => 'el tipo de documento',
        'tabla' => 'documentos', 'pk' => 'iddocumentos', 'unico' => 'nombre_documento',
        'lista' => "SELECT iddocumentos, nombre_documento, COALESCE(descripcion,''), COALESCE(dias_atension,0) FROM documentos ORDER BY nombre_documento ASC",
        'columnas' => array('Nombre', 'Descripción', 'Días de atención'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre_documento'),
            array('Descripcion', 'Descripción', 'textarea', false, 'descripcion'),
            array('Dias', 'Días de atención', 'number', false, 'dias_atension'),
        ),
        'kpis' => array(
            array('En uso este año', "SELECT COUNT(DISTINCT iddocumento) FROM registro_documentos WHERE " . $deEsteAnio('fecha_registro'), 'Tipos con registros ' . date('Y'), 'fa-check-circle', 't-green', 'lab-green'),
            array('Con plazo', "SELECT COUNT(*) FROM documentos WHERE COALESCE(dias_atension,0) > 0", 'Tienen días de atención', 'fa-clock-o', 't-orange', 'lab-orange'),
        ),
    ),
    'tipos-tramite' => array(
        'ruta' => 'mantenimiento/tipos-tramite', 'permiso' => 'vistas/v_tipo_tramite.php',
        'titulo' => 'Tipos de Trámite', 'subtitulo' => 'Catálogo de tipos de trámite de la mesa de partes',
        'icono' => 'fa-random', 'singular' => 'el tipo de trámite',
        'tabla' => 'tipo_tram', 'pk' => 'idtipo_tram', 'unico' => 'nombre',
        'lista' => "SELECT idtipo_tram, nombre, COALESCE(descripcion,'') FROM tipo_tram ORDER BY nombre ASC",
        'columnas' => array('Nombre', 'Descripción'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre'),
            array('Descripcion', 'Descripción', 'textarea', false, 'descripcion'),
        ),
        'kpis' => array(
            array('Trámites ' . date('Y'), "SELECT COUNT(*) FROM registro_documentos WHERE " . $deEsteAnio('fecha_registro'), 'Registrados este año', 'fa-file-text-o', 't-green', 'lab-green'),
            array('Tipos en uso', "SELECT COUNT(DISTINCT idtipo_tramite) FROM registro_documentos WHERE " . $deEsteAnio('fecha_registro'), 'Con registros este año', 'fa-check-circle', 't-purple', 'lab-purple'),
        ),
    ),
    'acciones' => array(
        'ruta' => 'mantenimiento/acciones', 'permiso' => 'vistas/v_accion.php',
        'titulo' => 'Acciones de Documento', 'subtitulo' => 'Catálogo de acciones al recepcionar o derivar (atención, conocimiento, etc.)',
        'icono' => 'fa-bolt', 'singular' => 'la acción',
        'tabla' => 'aciones_documento', 'pk' => 'idaciones_documento',
        'lista' => "SELECT idaciones_documento, nombre_accion, COALESCE(descripcion,'') FROM aciones_documento ORDER BY nombre_accion ASC",
        'columnas' => array('Nombre', 'Descripción'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre_accion'),
            array('Descripcion', 'Descripción', 'textarea', false, 'descripcion'),
        ),
        'kpis' => array(
            array('Usos ' . date('Y'), "SELECT COUNT(*) FROM movimientos WHERE idacciondocs IS NOT NULL AND " . $deEsteAnio('fecha_hora'), 'Movimientos con acción', 'fa-check-circle', 't-green', 'lab-green'),
            array('En uso', "SELECT COUNT(DISTINCT idacciondocs) FROM movimientos WHERE idacciondocs IS NOT NULL AND " . $deEsteAnio('fecha_hora'), 'Acciones aplicadas este año', 'fa-bolt', 't-orange', 'lab-orange'),
        ),
    ),
    'estados' => array(
        'ruta' => 'mantenimiento/estados', 'permiso' => 'vistas/v_estados_doc.php',
        'titulo' => 'Estados de Documento', 'subtitulo' => 'Catálogo de estados del trámite (recepcionado, derivado, archivado, etc.). Cuidado: los flujos del sistema dependen de estos estados.',
        'icono' => 'fa-flag-o', 'singular' => 'el estado',
        'tabla' => 'estado_documento', 'pk' => 'idestado_documento',
        'lista' => "SELECT idestado_documento, nombre, COALESCE(descripcion,'') FROM estado_documento ORDER BY idestado_documento ASC",
        'columnas' => array('Nombre', 'Descripción'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre'),
            array('Descripcion', 'Descripción', 'textarea', false, 'descripcion'),
        ),
        'kpis' => array(
            array('Usos ' . date('Y'), "SELECT COUNT(*) FROM movimientos WHERE idestadodocs IS NOT NULL AND " . $deEsteAnio('fecha_hora'), 'Movimientos con estado', 'fa-check-circle', 't-green', 'lab-green'),
            array('En uso', "SELECT COUNT(DISTINCT idestadodocs) FROM movimientos WHERE idestadodocs IS NOT NULL AND " . $deEsteAnio('fecha_hora'), 'Estados aplicados este año', 'fa-flag-o', 't-purple', 'lab-purple'),
        ),
    ),
    'archivadores' => array(
        'ruta' => 'mantenimiento/archivadores', 'permiso' => 'vistas/v_archivador.php',
        'titulo' => 'Archivadores', 'subtitulo' => 'Archivadores de tu oficina (los documentos archivados se guardan aquí)',
        'icono' => 'fa-archive', 'singular' => 'el archivador',
        'tabla' => 'archivador', 'pk' => 'idarchivador', 'oficina' => true,
        'lista' => "SELECT idarchivador, nombre, COALESCE(detalles,''), COALESCE(anio,'') FROM archivador WHERE idoficina = :oficina ORDER BY anio DESC, nombre ASC",
        'columnas' => array('Nombre', 'Detalles', 'Año'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre'),
            array('Detalles', 'Detalles', 'textarea', false, 'detalles'),
            array('anio', 'Año', 'number', true, 'anio'),
        ),
        'kpis' => array(
            array('Docs. archivados', "SELECT COUNT(*) FROM docs_archivados da INNER JOIN archivador a ON da.idarchivador=a.idarchivador WHERE a.idoficina=:oficina", 'Guardados en tus archivadores', 'fa-file-text-o', 't-green', 'lab-green'),
            array('Del ' . date('Y'), "SELECT COUNT(*) FROM archivador WHERE idoficina=:oficina AND anio=year(now())", 'Archivadores del año actual', 'fa-calendar', 't-purple', 'lab-purple'),
        ),
    ),
    'menus' => array(
        'ruta' => 'configuracion/menus', 'permiso' => 'vistas/v_menu.php',
        'titulo' => 'Menús del Sistema', 'subtitulo' => 'Ítems del menú y sus rutas. Cuidado: los permisos de acceso y la navegación dependen de esta tabla.',
        'icono' => 'fa-bars', 'singular' => 'el ítem de menú',
        'tabla' => 'menu', 'pk' => 'idmenu',
        'lista' => "SELECT idmenu, nombre_menu, COALESCE(direccion,''), COALESCE(dependencia,''), COALESCE(idmodulo,0), COALESCE(clase_llamar,'') FROM menu ORDER BY idmenu ASC",
        'columnas' => array('Nombre', 'Ruta (dirección)', 'Grupo (dependencia)', 'Módulo', 'Clase'),
        'campos' => array(
            array('Nombre', 'Nombre', 'text', true, 'nombre_menu'),
            array('Ruta', 'Ruta (p. ej. vistas/v_oficinas.php, o # para grupos)', 'text', false, 'direccion'),
            array('Dependencia', 'Grupo (dependencia: número del ítem grupo)', 'number', false, 'dependencia'),
            array('Modulo', 'Módulo (número)', 'number', false, 'idmodulo'),
            array('Clase', 'Clase / icono', 'text', false, 'clase_llamar'),
        ),
        'extra' => array('font_icon' => 'Clase'),
        'kpis' => array(
            array('Módulos con ruta', "SELECT COUNT(*) FROM menu WHERE direccion IS NOT NULL AND direccion <> '#'", 'Ítems navegables', 'fa-link', 't-green', 'lab-green'),
            array('Grupos', "SELECT COUNT(*) FROM menu WHERE COALESCE(direccion,'#')='#'", 'Encabezados del menú', 'fa-folder-o', 't-purple', 'lab-purple'),
        ),
    ),
);
