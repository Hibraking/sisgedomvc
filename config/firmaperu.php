<?php
/* =====================================================================
   SISGEDO MVC — FIRMA PERÚ (firma digital, Ley N° 27269). Mismos valores que
   sisgedov2/conexion/firmaperu_config.php. La firma se ACTIVA sola cuando
   existe el archivo de credenciales fwAuthorization.json que entrega la PCM.
   Ubicación: config/firmaperu/fwAuthorization.json, dentro de sisgedomvc,
   bloqueada para la web (config/.htaccess) y fuera de Git (.gitignore).
   ===================================================================== */
return array(
    'credenciales' => SISGEDO_RAIZ . '/config/firmaperu/fwAuthorization.json',
    'puerto'       => 48596,                                    // firmador local en la PC del usuario
    'js'           => 'https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js',
    'filtro_cert'  => '.*',
    'nivel'        => 'B',                                       // B básica · T con sello de tiempo · LTA
    'tsa_url'      => '',
    'tsa_user' => '',
    'tsa_pass' => '',
    'imagen'       => 'assets/images/pdf/' . SISGEDO_PDF_LOGO,   // estampado (dentro de public/)
    'minutos'      => 15,                                        // validez del enlace de firma
);
