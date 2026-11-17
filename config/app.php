<?php
/* =====================================================================
   SISGEDO MVC — Configuración general
   ===================================================================== */
return array(
    'nombre'        => 'SISGEDO',
    'version'       => '3.0.0-mvc',

    // Sesión propia: así no se mezcla con la de sisgedov2 aunque ambos
    // corran en el mismo dominio (localhost).
    'sesion_nombre' => 'SISGEDOMVC',

    // Carpeta PROPIA de archivos (fuera de public/, protegida de la web):
    //   reg_doc/        adjuntos de registros y de la Mesa de Partes Virtual
    //   docs_emitidos/  adjuntos de los documentos emitidos (y los firmados)
    //   perfiles/       fotos de perfil
    // Todo lo nuevo se guarda aquí.
    'ruta_archivos' => SISGEDO_RAIZ . '/storage/archivos',

    // Carpeta de sisgedov2, SOLO LECTURA y solo como respaldo: si un archivo antiguo
    // todavía no se trasladó a 'ruta_archivos', se lee de aquí. Nunca se escribe en ella.
    // Trasladar los archivos: php herramientas/trasladar_archivos.php (ver README).
    // Cuando ya no quede nada por trasladar, poner null.
    'ruta_archivos_antigua' => realpath(__DIR__ . '/../../sisgedov2') ?: null,

    // Inicio de sesión: intentos fallidos permitidos por usuario+IP y ventana (seg)
    'login_max_intentos' => 6,
    'login_ventana_seg'  => 900,
);
