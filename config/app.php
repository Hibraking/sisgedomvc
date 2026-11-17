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

    // Carpeta del sistema actual (sisgedov2): de aquí se leen los archivos
    // que se comparten mientras dura la migración (fotos de perfil y, en
    // etapas siguientes, los documentos adjuntos).
    'ruta_sisgedov2' => realpath(__DIR__ . '/../../sisgedov2') ?: (__DIR__ . '/../../sisgedov2'),

    // Dirección web del sistema actual (para los módulos aún no migrados)
    'url_sisgedov2' => SISGEDO_ES_LOCAL ? 'http://localhost/sisgedov2' : 'https://munihuaytara.gob.pe/sisgedov2',

    // Inicio de sesión: intentos fallidos permitidos por usuario+IP y ventana (seg)
    'login_max_intentos' => 6,
    'login_ventana_seg'  => 900,
);
