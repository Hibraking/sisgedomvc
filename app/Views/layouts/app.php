<?php
/* Plantilla general de las pantallas internas: cabecera + menú lateral + contenido.
   Variables: $titulo, $contenido (HTML de la vista), $cssExtra (CSS opcionales),
   $jsExtra (JS opcionales, al final), $claseBody (clase del body), $bodyData (atributos data-* del body: ['endpoint' => ...]) */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;

$aviso = Session::flash('aviso');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h(isset($titulo) ? $titulo : 'Inicio') ?> — SISGEDO</title>
  <link rel="icon" type="image/png" href="<?= asset(SISGEDO_IMG_FAVICON) ?>" />
  <link rel="stylesheet" href="<?= asset('assets/vendor/font-awesome/4.5.0/css/font-awesome.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/css/sisgedo-theme.css') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/css/v_recepcion_proveido.css') ?>" />
  <?php foreach (isset($cssExtra) ? $cssExtra : array() as $css) { ?>
    <link rel="stylesheet" href="<?= asset($css) ?>" />
  <?php } ?>
  <script src="<?= asset('assets/vendor/jquery/jquery-2.1.4.min.js') ?>"></script>
</head>

<body<?= !empty($claseBody) ? ' class="' . h($claseBody) . '"' : '' ?> data-csrf="<?= h(Csrf::token()) ?>" data-base="<?= h(url('')) ?>"<?php
  foreach (isset($bodyData) ? $bodyData : array() as $k => $v) {
    echo ' data-' . h(strtolower(preg_replace('~([a-z])([A-Z])~', '$1-$2', $k))) . '="' . h($v) . '"';
  } ?>>
  <div class="app">
    <div class="hdr">
      <div class="l">
        <button class="ib"><i class="fa fa-bars"></i></button>
        <div class="brand"><i class="fa fa-leaf"></i> SISGEDO</div>
        <span class="sub">Sistema de Gestión Documentario</span>
      </div>
      <div class="r">
        <div class="usr">
          <span class="av"><i class="fa fa-user"></i></span>
          <div>
            <div class="wl">Bienvenido,</div>
            <div class="nm"><?= h(Auth::alias()) ?></div>
          </div>
          <a href="<?= url('salir') ?>" class="ib" title="Salir"><i class="fa fa-sign-out"></i></a>
        </div>
      </div>
    </div>

    <div class="body">
      <?= App\Core\View::parcial('layouts/sidebar') ?>
      <?php if ($aviso) { ?>
        <div class="mvc-aviso" role="alert"><i class="fa fa-info-circle"></i> <?= h($aviso) ?></div>
      <?php } ?>
      <?= $contenido ?>
    </div>
  </div>
  <?php foreach (isset($jsExtra) ? $jsExtra : array() as $js) { ?>
    <script src="<?= asset($js) ?>"></script>
  <?php } ?>
  <style>
    .mvc-aviso { position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 2000; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; padding: 10px 16px; border-radius: 10px; font-size: 13px; box-shadow: 0 8px 24px rgba(15, 23, 42, .15); }
  </style>
</body>

</html>
