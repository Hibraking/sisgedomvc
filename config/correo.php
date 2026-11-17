<?php
/* =====================================================================
   SISGEDO MVC — Correo saliente (mismos valores que sisgedov2/conexion/correo_config.php)
   - LOCAL (Laragon): Mailpit, sin autenticación. Ver http://localhost:8025
   - PRODUCCIÓN: SMTP de la cuenta institucional. La contraseña va en
     config/correo_clave.php (copiar de correo_clave.ejemplo.php; NO se sube a Git).
   ===================================================================== */
$correoClave = is_file(__DIR__ . '/correo_clave.php') ? include __DIR__ . '/correo_clave.php' : '';

$comun = array(
  'activo'          => true,                                   // false = no se envía nada (queda DESACTIVADO)
  'nombre'          => SISGEDO_ENTIDAD . ' - Mesa de Partes',  // remitente que ve el ciudadano
  'desde'           => SISGEDO_MUNI_EMAIL,                     // debe ser la cuenta que se autentica
  'responder_a'     => '',
  'usuario'         => SISGEDO_MUNI_EMAIL,
  'clave'           => is_string($correoClave) ? $correoClave : '',
  'verificar_ssl'   => true,
  'timeout'         => 15,
  'carpeta_enviados' => 'INBOX.Sent',                          // copia por IMAP en "Enviados"
  'imap_puerto'     => 993,
  'imap_seguridad'  => 'ssl',
  'aviso_mpv'       => SISGEDO_MUNI_EMAIL,                     // aviso interno de cada ingreso por MPV ('' = no)
  'aviso_adjunto_max_mb' => 15,
);

if (SISGEDO_ES_LOCAL) {
  return $comun + array(
    'host' => '127.0.0.1',
    'puerto' => 1025,
    'seguridad' => '',
    'auth' => false,
    'guardar_enviados' => false,
    'imap_host' => '127.0.0.1'
  );
}
// cPanel > Cuentas de correo > Connect Devices. Es "munihuaytara.gob.pe" (no "mail.").
return $comun + array(
  'host' => 'munihuaytara.gob.pe',
  'puerto' => 465,
  'seguridad' => 'ssl',
  'auth' => true,
  'guardar_enviados' => true,
  'imap_host' => 'munihuaytara.gob.pe'
);
