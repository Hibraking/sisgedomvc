<?php
/* Inicio. Variables: $saludo, $fecha, $oficina, $hoy, $mes, $pendientes, $actividad, $maxActividad, $totalSemana */
use App\Core\Auth;

function inicio_hace($f)
{
    $t = strtotime((string)$f);
    if (!$t) return '';
    $s = max(0, time() - $t);
    if ($s < 3600) return 'Hace ' . max(1, (int)floor($s / 60)) . ' min';
    if ($s < 86400) { $h = (int)floor($s / 3600); return 'Hace ' . $h . ' hora' . ($h > 1 ? 's' : ''); }
    $d = (int)floor($s / 86400);
    return 'Hace ' . $d . ' día' . ($d > 1 ? 's' : '');
}
$hoyFiltro = 'desde=' . date('Y-m-d') . '&hasta=' . date('Y-m-d');   // las tarjetas "hoy" abren su lista del día
?>
<!-- CONTENIDO -->
<div class="main">
  <div class="tb-hola">
    <div>
      <h1 class="t"><?= h($saludo) ?>, <?= h(Auth::alias()) ?> 👋</h1>
      <div class="subt"><?= $oficina !== '' ? 'Oficina: ' . h($oficina) . ' — ' : '' ?>este es el movimiento documentario de hoy</div>
    </div>
    <div class="tb-fecha"><i class="fa fa-calendar"></i> <?= h($fecha) ?></div>
  </div>

  <div class="kpis">
    <div class="kpi" style="cursor:pointer" title="Ver los documentos por recepcionar" onclick="location='<?= url('recepcion') ?>'">
      <div class="ic t-orange"><i class="fa fa-inbox"></i></div>
      <div>
        <div class="lab lab-orange">Por recepcionar</div>
        <div class="num" style="color:#f97316"><?= number_format($hoy['por_recepcionar']) ?></div>
        <div class="ss">En tu bandeja</div>
      </div>
    </div>
    <div class="kpi" style="cursor:pointer" title="Ver lo recepcionado hoy" onclick="location='<?= url('recepcion') ?>?estado=Recepcionado&<?= $hoyFiltro ?>'">
      <div class="ic t-green"><i class="fa fa-check-circle"></i></div>
      <div>
        <div class="lab lab-green">Recepcionados hoy</div>
        <div class="num" style="color:#16a34a"><?= number_format($hoy['recepcionados']) ?></div>
        <div class="ss">Por tu oficina</div>
      </div>
    </div>
    <div class="kpi" style="cursor:pointer" title="Ver lo derivado hoy" onclick="location='<?= url('derivados') ?>?<?= $hoyFiltro ?>'">
      <div class="ic t-purple"><i class="fa fa-paper-plane"></i></div>
      <div>
        <div class="lab lab-purple">Derivados hoy</div>
        <div class="num" style="color:#6d28d9"><?= number_format($hoy['derivados']) ?></div>
        <div class="ss">Desde tu oficina</div>
      </div>
    </div>
    <div class="kpi" style="cursor:pointer" title="Ver lo registrado hoy en mesa de partes" onclick="location='<?= url('registro') ?>?<?= $hoyFiltro ?>'">
      <div class="ic t-blue"><i class="fa fa-list-alt"></i></div>
      <div>
        <div class="lab lab-blue">Mesa de partes hoy</div>
        <div class="num" style="color:#1e293b"><?= number_format($hoy['mesa_partes']) ?></div>
        <div class="ss">Toda la entidad</div>
      </div>
    </div>
  </div>

  <!-- ÚLTIMOS POR RECEPCIONAR -->
  <div class="card table-card" style="flex:0 1 auto">
    <div class="tbar">
      <div class="g"><span style="font-size:13px;font-weight:700;color:#1e293b"><i class="fa fa-inbox" style="color:#2563eb"></i> Últimos documentos por recepcionar</span></div>
      <div class="g"><a class="btn btn-sm" href="<?= url('recepcion') ?>" style="text-decoration:none"><i class="fa fa-arrow-right"></i> Ver bandeja completa</a></div>
    </div>
    <div class="rowg thead">
      <span>Documento</span><span>Asunto</span><span>Origen</span><span>Recibido</span>
    </div>
    <div>
      <?php if (count($pendientes) === 0) { ?>
        <div style="padding:36px;text-align:center;color:#94a3b8;font-size:13px"><i class="fa fa-check-circle" style="color:#22c55e"></i> ¡Bandeja al día! No tienes documentos pendientes.</div>
      <?php }
      foreach ($pendientes as $p) { ?>
        <div class="row rowg" style="cursor:pointer" onclick="location='<?= url('recepcion') ?>'">
          <div class="dcell">
            <div style="display:flex;gap:8px"><i class="fa fa-file-text-o" style="color:#3b82f6;margin-top:2px"></i><span class="nm"><?= h($p[0]) ?> <?= h($p[1]) ?></span></div>
          </div>
          <div class="dcell">
            <p class="asun"><?= h($p[2]) ?></p>
          </div>
          <div class="dcell"><?php if (stripos((string)$p[3], 'MESA') !== false) { ?><span class="badge b-blue">MESA DE PARTES</span><?php } else { ?><span class="cell-txt" style="font-size:12px;color:#475569"><i class="fa fa-building-o" style="color:#94a3b8;margin-right:6px"></i><?= h($p[3]) ?></span><?php } ?></div>
          <div class="muted"><?= h(inicio_hace($p[4])) ?></div>
        </div>
      <?php } ?>
    </div>
  </div>

  <!-- ACTIVIDAD 7 DÍAS -->
  <div class="card" style="padding:16px 20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
      <span style="font-size:13px;font-weight:700;color:#1e293b"><i class="fa fa-bar-chart" style="color:#2563eb"></i> Actividad de tu oficina — últimos 7 días</span>
      <span class="muted" style="font-size:12px"><?= number_format($totalSemana) ?> movimiento(s) en la semana</span>
    </div>
    <div class="tb-grafico">
      <?php foreach ($actividad as $a) {
        $alto = (int)round($a[1] * 100 / $maxActividad); ?>
        <div class="tb-bar" title="<?= h($a[2]) ?>: <?= (int)$a[1] ?> movimiento(s)">
          <span class="bv"><?= $a[1] > 0 ? number_format($a[1]) : '' ?></span>
          <div class="bb <?= $a[1] === 0 ? 'cero' : '' ?>" style="height:<?= max(4, $alto) ?>%"></div>
          <span class="bl"><?= h($a[0]) ?></span>
          <span class="bf"><?= h($a[2]) ?></span>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<!-- PANEL DERECHO -->
<div class="rp">
  <div class="hd">
    <h2>Resumen</h2>
  </div>
  <div class="bd">
    <div class="rp-scroll">
      <div class="docbox" style="margin-bottom:12px">
        <div class="ic"><i class="fa fa-building-o"></i></div>
        <div style="min-width:0">
          <div style="font-size:13px;font-weight:700;color:#1e293b"><?= $oficina !== '' ? h($oficina) : 'Sin oficina asignada' ?></div>
          <div class="muted" style="font-size:11.5px">Tu oficina en el sistema</div>
        </div>
      </div>

      <div class="sect">Este mes</div>
      <div style="margin:8px 0 16px">
        <div class="rp-mini"><span class="mi t-green"><i class="fa fa-check-circle"></i></span><span class="mt">Recepcionados</span><span class="mn"><?= number_format($mes['recepcionados']) ?></span></div>
        <div class="rp-mini"><span class="mi t-purple"><i class="fa fa-paper-plane"></i></span><span class="mt">Derivados</span><span class="mn"><?= number_format($mes['derivados']) ?></span></div>
        <div class="rp-mini"><span class="mi t-blue"><i class="fa fa-list-alt"></i></span><span class="mt">Mesa de partes</span><span class="mn"><?= number_format($mes['mesa_partes']) ?></span></div>
        <div class="rp-mini"><span class="mi t-orange"><i class="fa fa-archive"></i></span><span class="mt">Archivados</span><span class="mn"><?= number_format($mes['archivados']) ?></span></div>
      </div>

      <div class="sect">Accesos rápidos</div>
      <div style="margin-top:8px">
        <a class="rp-link" href="<?= url('recepcion') ?>"><i class="fa fa-inbox"></i> Recepción de documentos <i class="fa fa-angle-right"></i></a>
        <a class="rp-link" href="<?= url('registro') ?>"><i class="fa fa-list-alt"></i> Registrar en mesa de partes <i class="fa fa-angle-right"></i></a>
        <a class="rp-link" href="<?= url('emision') ?>"><i class="fa fa-folder-o"></i> Emitir documento interno <i class="fa fa-angle-right"></i></a>
        <a class="rp-link" href="<?= url('archivo') ?>"><i class="fa fa-archive"></i> Archivo de documentos <i class="fa fa-angle-right"></i></a>
        <a class="rp-link" href="<?= url('reportes/consulta-interna') ?>"><i class="fa fa-search"></i> Consulta de expedientes <i class="fa fa-angle-right"></i></a>
        <a class="rp-link" href="<?= url('reportes/estados') ?>"><i class="fa fa-tasks"></i> Estados de atención <i class="fa fa-angle-right"></i></a>
      </div>
    </div>
  </div>
  <div class="ftr">
    <a class="btn btn-p" style="flex:1;justify-content:center;text-decoration:none" href="<?= url('recepcion') ?>"><i class="fa fa-inbox"></i> Ir a mi bandeja</a>
  </div>
</div>
