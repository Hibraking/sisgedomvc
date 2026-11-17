<?php
/* =====================================================================
   SISGEDO MVC — Base de datos (la MISMA que usa sisgedov2)
   ---------------------------------------------------------------------
   Local (Laragon): sisgedov2 / root sin contraseña.
   Producción: misma base y usuario que sisgedov2; la contraseña NO va en el
   repositorio: se lee de config/db_clave.php (copiar db_clave.ejemplo.php).
   ===================================================================== */
if (SISGEDO_ES_LOCAL) {
    return array(
        'host'    => 'localhost',
        'nombre'  => 'sisgedov2',
        'usuario' => 'root',
        'clave'   => '',
    );
}
$archivo = __DIR__ . '/db_clave.php';
$clave = is_file($archivo) ? include $archivo : '';
return array(
    'host'    => 'localhost',
    'nombre'  => 'xyltrylx_sisgedov2',
    'usuario' => 'xyltrylx_sisgedov2',
    'clave'   => is_string($clave) ? $clave : '',
);
