<?php
/* =====================================================================
   SISGEDO MVC — Prueba rápida ("de humo"), sin modificar datos
   Uso:  php tests/humo.php [http://localhost/sisgedomvc]
   Revisa: rutas, recursos, bloqueo de carpetas privadas, login sin sesión,
   rechazo sin token CSRF y que ninguna página muestre avisos de PHP.
   ===================================================================== */
$B = rtrim(isset($argv[1]) ? $argv[1] : 'http://localhost/sisgedomvc', '/');

function pedir($url, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $cuerpo = (string)curl_exec($ch);
    return array((int)curl_getinfo($ch, CURLINFO_HTTP_CODE), $cuerpo, (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL));
}

$casos = array(
    array('Login visible', 'login', null, 200),
    array('Inicio sin sesión lleva al login', '', null, 302),
    array('Módulo sin sesión lleva al login', 'recepcion', null, 302),
    array('Ruta inexistente', 'no-existe-xyz', null, 404),
    array('CSS del tema', 'assets/css/sisgedo-theme.css', null, 200),
    array('Logo', 'assets/images/logo_web.png', null, 200),
    array('Carpeta app/ bloqueada', 'app/bootstrap.php', null, 403),
    array('Carpeta config/ bloqueada', 'config/database.php', null, 403),
    array('Carpeta storage/ bloqueada', 'storage/logs/', null, 403),
    array('POST de login sin token CSRF', 'login', array('usuario' => 'x', 'password' => 'y'), 403),
    // Etapa 2
    array('Derivados sin sesión lleva al login', 'derivados', null, 302),
    array('Notificaciones sin sesión', 'notificaciones', null, 302),
    array('Adjunto sin sesión', 'adjunto?c=reg&f=x.pdf', null, 302),
    array('Acción de recepción sin sesión', 'recepcion/accion', array('carga_aciones' => '-'), 401),
    array('JS de recepción', 'assets/js/recepcion.js', null, 200),
    array('JS de notificaciones', 'assets/js/notificaciones.js', null, 200),
    // Etapa 3
    array('Mesa de Partes Virtual (pública)', 'mesa-partes', null, 200),
    array('Consulta de expediente (pública)', 'consulta?num_exp=99999999', null, 200),
    array('Verificación (pública)', 'verificar?c=ZZZZ-ZZZZ', null, 200),
    array('Subsanar con enlace inválido', 'subsanar?t=abc', null, 200),
    array('Envío MPV sin datos se rechaza', 'mesa-partes/enviar', array('asunto' => ''), 200),
    array('Dirección antigua mpvirtual.php', 'mpvirtual.php', null, 301),
    array('Dirección antigua verificar.php', 'verificar.php?c=X', null, 301),
    array('MPV interna sin sesión lleva al login', 'mesa-partes-virtual', null, 302),
    array('Acción MPV sin sesión', 'mesa-partes-virtual/accion', array('historial' => '-'), 401),
);
$fallas = 0;
foreach ($casos as $c) {
    list($codigo, $cuerpo) = pedir($B . '/' . $c[1], $c[2]);
    $avisos = preg_match('~<b>(Warning|Notice|Deprecated|Fatal error)</b>|Uncaught~', $cuerpo);
    $ok = ($codigo === $c[3]) && !$avisos;
    if (!$ok) $fallas++;
    printf("  %s  %-40s HTTP %d (esperado %d)%s\n", $ok ? 'OK   ' : 'FALLA', $c[0], $codigo, $c[3], $avisos ? ' + AVISO PHP' : '');
}
echo $fallas ? "\n$fallas falla(s)\n" : "\nTodo correcto\n";
exit($fallas ? 1 : 0);
