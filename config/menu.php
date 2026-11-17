<?php
/* =====================================================================
   SISGEDO MVC — Menú lateral y rutas de los módulos
   ---------------------------------------------------------------------
   Cada opción:  título, ruta, icono, permiso, etapa, dirección antigua
     - permiso: la MISMA dirección que usa la tabla menu/accesos_usuario de
       sisgedov2, así los permisos ya asignados siguen valiendo.
     - etapa:   en qué etapa de la migración se construye el módulo. Mientras
       no esté listo, la ruta muestra un aviso con enlace al sistema actual.
     - antiguo: la pantalla equivalente en sisgedov2.
   ===================================================================== */
return array(
    array('grupo' => 'Operaciones', 'icono' => 'fa-list', 'items' => array(
        array('Mesa de Partes Virtual', 'mesa-partes-virtual', 'fa-globe', 'vistas/v_mesa_partes_virtual.php', 3, 'vistas/v_mesa_partes_virtual.php'),
        array('Recepción', 'recepcion', 'fa-inbox', 'vistas/v_recepcion_proveido.php', 2, 'vistas/v_recepcion_proveido.php'),
        array('Archivo', 'archivo', 'fa-folder', 'vistas/v_archivar_docs.php', 5, 'vistas/v_archivar_docs.php'),
        array('Emisión', 'emision', 'fa-folder-o', 'vistas/v_docs_emitidos.php', 4, 'vistas/v_docs_emitidos.php'),
        array('Derivados', 'derivados', 'fa-share-alt', 'vistas/v_recepcion_proveido.php', 2, 'vistas/v_recepcion_proveido.php?estado=Derivado'),
        array('Registro', 'registro', 'fa-list-alt', 'vistas/o_reg_documentos.php', 4, 'vistas/o_reg_documentos.php'),
    )),
    array('grupo' => 'Reportes', 'icono' => 'fa-bar-chart', 'items' => array(
        array('Consulta Interna', 'reportes/consulta-interna', 'fa-search', 'reportes/consulta_interna.php', 6, 'reportes/consulta_interna.php'),
        array('Estados de Atención', 'reportes/estados', 'fa-tasks', 'vistas/v_reporte_estados_documento.php', 6, 'vistas/v_reporte_estados_documento.php'),
        array('Cuaderno Externo', 'reportes/cuaderno-externo', 'fa-book', 'vistas/v_reporte_cuadernoexterno.php', 6, 'vistas/v_reporte_cuadernoexterno.php'),
        array('Cuaderno Interno', 'reportes/cuaderno-interno', 'fa-book', 'vistas/v_reporte_cuadernointerno.php', 6, 'vistas/v_reporte_cuadernointerno.php'),
    )),
    array('grupo' => 'Mantenimiento', 'icono' => 'fa-wrench', 'items' => array(
        array('Oficinas', 'mantenimiento/oficinas', 'fa-building-o', 'vistas/v_oficinas.php', 6, 'vistas/v_oficinas.php'),
        array('Personal', 'mantenimiento/personal', 'fa-users', 'vistas/v_personal.php', 6, 'vistas/v_personal.php'),
        array('Documentos', 'mantenimiento/documentos', 'fa-file-text-o', 'vistas/v_documentos.php', 6, 'vistas/v_documentos.php'),
        array('Tipos de Trámite', 'mantenimiento/tipos-tramite', 'fa-random', 'vistas/v_tipo_tramite.php', 6, 'vistas/v_tipo_tramite.php'),
        array('Remitentes', 'mantenimiento/remitentes', 'fa-user', 'vistas/v_remitentes.php', 6, 'vistas/v_remitentes.php'),
        array('Responsables', 'mantenimiento/responsables', 'fa-sitemap', 'vistas/v_responsables.php', 6, 'vistas/v_responsables.php'),
        array('Acciones Doc.', 'mantenimiento/acciones', 'fa-bolt', 'vistas/v_accion.php', 6, 'vistas/v_accion.php'),
        array('Estados Doc.', 'mantenimiento/estados', 'fa-flag-o', 'vistas/v_estados_doc.php', 6, 'vistas/v_estados_doc.php'),
        array('Archivadores', 'mantenimiento/archivadores', 'fa-archive', 'vistas/v_archivador.php', 6, 'vistas/v_archivador.php'),
    )),
    array('grupo' => 'Configuración', 'icono' => 'fa-cog', 'items' => array(
        array('Menús', 'configuracion/menus', 'fa-bars', 'vistas/v_menu.php', 6, 'vistas/v_menu.php'),
        array('Accesos del Personal', 'configuracion/accesos', 'fa-key', 'vistas/v_accesos_personal.php', 6, 'vistas/v_accesos_personal.php'),
    )),
);
