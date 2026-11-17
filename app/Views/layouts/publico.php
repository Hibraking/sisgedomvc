<?php
/* Plantilla de las páginas públicas (sin sesión): cabecera municipal + contenido + pie.
   Variables: $titulo, $subtitulo, $enlace [ruta, icono, texto], $contenido,
   opcionales: $claseBody, $noIndexar, $pie, $jsExtra */
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php if (!empty($noIndexar)) { ?><meta name="robots" content="noindex" /><?php } ?>
  <title><?= h($titulo) ?> — <?= h(SISGEDO_ENTIDAD) ?></title>
  <link rel="icon" type="image/png" href="<?= asset(SISGEDO_IMG_FAVICON) ?>" />
  <link rel="stylesheet" href="<?= asset('assets/vendor/font-awesome/4.5.0/css/font-awesome.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/css/publico.css') ?>" />
  <script src="<?= asset('assets/vendor/jquery/jquery-2.1.4.min.js') ?>"></script>
</head>

<body<?= !empty($claseBody) ? ' class="' . h($claseBody) . '"' : '' ?>>
  <!-- CABECERA MUNICIPAL -->
  <div class="pub-hdr">
    <img src="<?= asset(SISGEDO_IMG_LOGO) ?>" alt="<?= h(SISGEDO_ENTIDAD) ?>" />
    <div class="th">
      <div class="t1"><?= h(SISGEDO_ENTIDAD_MAYUS) ?></div>
      <div class="t2"><?= h($subtitulo) ?></div>
    </div>
    <?php if (!empty($enlace)) { ?>
      <a class="enl" href="<?= url($enlace[0]) ?>"><i class="fa <?= h($enlace[1]) ?>"></i> <?= h($enlace[2]) ?></a>
    <?php } ?>
  </div>

  <?= $contenido ?>

  <div class="pub-footer">
    <b><?= h(SISGEDO_MUNI_SIGLAS) ?> — <?= h(SISGEDO_ENTIDAD) ?></b> · <?= h(isset($pie) ? $pie : $subtitulo) ?> © <?= date('Y') ?>
  </div>
  <?php foreach (isset($jsExtra) ? $jsExtra : array() as $js) { ?>
    <script src="<?= asset($js) ?>"></script>
  <?php } ?>
</body>

</html>
