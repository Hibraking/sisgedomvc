<?php
/* =====================================================================
   SISGEDO MVC — FIRMA PERÚ (firma digital, Ley N° 27269). Mismos valores que
   sisgedov2/conexion/firmaperu_config.php. La firma se ACTIVA sola cuando
   existe el archivo de credenciales fwAuthorization.json que entrega la PCM,
   FUERA de la carpeta pública (por defecto: <www>/firmaperu/fwAuthorization.json,
   la misma ubicación que usa sisgedov2).
   ===================================================================== */
return array(
    'credenciales' => dirname(SISGEDO_RAIZ) . '/firmaperu/fwAuthorization.json',
    'puerto'       => 48596,                                    // firmador local en la PC del usuario
    'js'           => 'https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js',
    'filtro_cert'  => '.*',
    'nivel'        => 'B',                                       // B básica · T con sello de tiempo · LTA
    'tsa_url'      => '', 'tsa_user' => '', 'tsa_pass' => '',
    'imagen'       => 'assets/images/pdf/' . SISGEDO_PDF_LOGO,   // estampado (dentro de public/)
    'minutos'      => 15,                                        // validez del enlace de firma
);
