<?php
/* Módulo aún no migrado. Variables: $titulo, $icono, $etapa, $antiguo */
$urlAntiguo = $antiguo !== '' ? rtrim(config('url_sisgedov2'), '/') . '/' . $antiguo : '';
$etapas = array(2 => 'Recepción y Derivados', 3 => 'Mesa de Partes Virtual', 4 => 'Registro y Emisión',
                5 => 'Archivo y Supervisión', 6 => 'Reportes, Mantenimiento y Perfil');
?>
<div class="main">
  <h1 class="t"><?= h($titulo) ?></h1>
  <div class="subt">Este módulo todavía no se ha migrado a la nueva arquitectura.</div>
  <div class="card" style="padding:34px 28px;display:flex;gap:22px;align-items:flex-start;max-width:760px">
    <span style="width:56px;height:56px;border-radius:14px;background:#eef2ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0"><i class="fa <?= h($icono) ?>"></i></span>
    <div style="font-size:14px;color:#475569;line-height:1.6">
      <p style="margin:0 0 10px"><b style="color:#1e293b">En construcción.</b>
        <?php if ($etapa) { ?>Se construye en la <b>etapa <?= (int)$etapa ?></b><?= isset($etapas[$etapa]) ? ' (' . h($etapas[$etapa]) . ')' : '' ?> de la migración a MVC.<?php } ?></p>
      <p style="margin:0 0 16px">Mientras tanto, puede usar esta opción en el sistema actual. Allí deberá iniciar sesión por separado.</p>
      <?php if ($urlAntiguo !== '') { ?>
        <a class="btn btn-p" href="<?= h($urlAntiguo) ?>" target="_blank" rel="noopener" style="text-decoration:none"><i class="fa fa-external-link"></i> Abrir en el sistema actual</a>
      <?php } ?>
    </div>
  </div>
</div>
