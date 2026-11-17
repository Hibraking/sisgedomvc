<?php
/* Cuaderno externo — mismo diseño que sisgedov2 (vistas/v_reporte_cuadernoexterno.php). */
?>
      <div class="main">
        <h1 class="t">Cuaderno de Registro Externo</h1>
        <div class="subt">Genera el PDF del cuaderno diario de documentos registrados en mesa de partes</div>

        <div class="kpis">
          <div class="kpi">
            <div class="ic t-blue"><i class="fa fa-calendar-check-o"></i></div>
            <div>
              <div class="lab lab-blue">Registrados hoy</div>
              <div class="num" style="color:#1e293b"><?= number_format($regHoy) ?></div>
              <div class="ss">Mesa de partes</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-purple"><i class="fa fa-filter"></i></div>
            <div>
              <div class="lab lab-purple">En el rango</div>
              <div class="num" style="color:#1e293b"><?= number_format($enRango) ?></div>
              <div class="ss">Del <?= h(date('d/m', strtotime($desdeSel))) ?> al <?= h(date('d/m', strtotime($hastaSel))) ?></div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-green"><i class="fa fa-book"></i></div>
            <div>
              <div class="lab lab-green">Este mes</div>
              <div class="num" style="color:#16a34a"><?= number_format($regMes) ?></div>
              <div class="ss">Documentos externos</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="generarCuaderno()">
            <div class="ic t-orange"><i class="fa fa-file-pdf-o"></i></div>
            <div>
              <div class="lab lab-orange">Cuaderno</div>
              <div class="num" style="color:#f97316;font-size:15px;padding-top:6px">Generar PDF</div>
              <div class="ss">Formato oficial</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld"><label>Desde</label>
              <div class="inp"><input id="f_inicio" type="date" value="<?= h($desdeSel) ?>" max="<?= h($hoy) ?>" /></div>
            </div>
            <div class="fld"><label>Hasta</label>
              <div class="inp"><input id="f_fin" type="date" value="<?= h($hastaSel) ?>" max="<?= h($hoy) ?>" /></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn btn-p" onclick="aplicarRango()"><i class="fa fa-search"></i> Ver rango</button>
              <button type="button" class="btn" onclick="window.location='<?= h(url('reportes/cuaderno-externo')) ?>'"><i class="fa fa-refresh"></i> Hoy</button>
              <button type="button" class="btn" onclick="generarCuaderno()"><i class="fa fa-file-pdf-o"></i> PDF</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g"><span style="font-size:13px;font-weight:700;color:#1e293b"><i class="fa fa-book" style="color:#2563eb"></i> Vista previa del cuaderno — <?= h(date('d/m/Y', strtotime($desdeSel))) ?> al <?= h(date('d/m/Y', strtotime($hastaSel))) ?></span></div>
            <div class="g"><span class="muted"><?= number_format($enRango) ?> registro(s)<?= $enRango > 100 ? ' — mostrando los últimos 100 (el PDF incluye todos)' : '' ?></span></div>
          </div>
          <div class="rowg thead">
            <span>Expediente</span><span>Documento</span><span>Remitente</span><span>Asunto</span><span>Oficina destino</span><span>Fecha</span><span></span>
          </div>
          <div>
            <?php if (count($vista) === 0) { ?>
              <div style="padding:44px;text-align:center;color:#94a3b8;font-size:13px">No hay documentos registrados en el rango seleccionado.</div>
            <?php }
            foreach ($vista as $v) { ?>
              <div class="row rowg">
                <span class="exp"><?= h($v[0]) ?></span>
                <div class="dcell"><span class="cell-txt"><?= h($v[1]) ?></span></div>
                <div class="dcell"><span class="cell-txt"><?= h($v[2]) ?></span></div>
                <div class="dcell">
                  <p class="asun"><?= h($v[3]) ?></p>
                </div>
                <div class="dcell"><span class="cell-txt"><?= $v[4] !== '' ? h($v[4]) : '—' ?></span></div>
                <div class="muted"><?= h($v[5]) ?></div>
                <div class="act"><a class="ab ab-b" title="Seguimiento" target="_blank" href="<?= h(url('consulta')) ?>?num_exp=<?= h(rawurlencode((string)$v[0])) ?>"><i class="fa fa-eye"></i></a></div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

