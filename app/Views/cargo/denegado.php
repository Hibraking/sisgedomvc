<?php /* Cargo sin permiso (mismo texto que sisgedov2/pdf/acceso.php). Variable: $mensaje */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cargo no disponible</title>
  <style>body{font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;color:#1e293b;margin:0;padding:40px 16px}
    .c{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:26px}h1{font-size:19px;color:#123a7a;margin:0 0 10px}
    p{font-size:14px;line-height:1.55;color:#475569}a{color:#2563eb;font-weight:600}</style>
</head>
<body><div class="c">
  <h1>Cargo no disponible</h1><p><?= h($mensaje) ?></p>
  <p>Si usted presentó el documento, use el <b>código de verificación</b> de su cargo en <a href="<?= h(url('verificar')) ?>">Verificar documento</a>.</p>
  <p>Si es personal de la municipalidad, <a href="<?= h(url('login')) ?>">inicie sesión</a> y vuelva a abrir el cargo.</p>
</div></body>
</html>
