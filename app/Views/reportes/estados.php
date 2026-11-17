<?php
/* Estados de atención — mismo diseño que sisgedov2 (vistas/v_reporte_estados_documento.php). */
?>
      <div class="main">
        <h1 class="t">Estados de Atención</h1>
        <div class="subt">Documentos tramitados por tu oficina según su estado — del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?></div>

        <div class="kpis">
          <?php $tints = array('t-blue' => '#1e293b', 't-green' => '#16a34a', 't-purple' => '#6d28d9', 't-orange' => '#f97316');
          $labs = array('lab-blue', 'lab-green', 'lab-purple', 'lab-orange');
          $ics = array_keys($tints);
          $i = 0;
          foreach (array_slice($porEstado, 0, 4) as $pe) {
            $tt = $ics[$i % 4]; ?>
            <div class="kpi">
              <div class="ic <?= $tt ?>"><i class="fa fa-flag-o"></i></div>
              <div>
                <div class="lab <?= $labs[$i % 4] ?>"><?= h($pe[0]) ?></div>
                <div class="num" style="color:<?= $tints[$tt] ?>"><?= number_format($pe[1]) ?></div>
                <div class="ss">En el período</div>
              </div>
            </div>
          <?php $i++;
          }
          if (count($porEstado) === 0) { ?>
            <div class="kpi">
              <div class="ic t-blue"><i class="fa fa-flag-o"></i></div>
              <div>
                <div class="lab lab-blue">Sin datos</div>
                <div class="num" style="color:#1e293b">0</div>
                <div class="ss">En el período</div>
              </div>
            </div>
          <?php } ?>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por expediente, asunto..." /><i class="fa fa-search"></i></div>
            </div>
            <div class="fld"><label>Desde</label>
              <div class="inp"><input id="desde" type="date" max="<?= h($hoy) ?>" value="<?= h($desdeFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Hasta</label>
              <div class="inp"><input id="hasta" type="date" max="<?= h($hoy) ?>" value="<?= h($hastaFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Estado</label>
              <div class="inp" style="padding:0"><select id="estado" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="0">Todos</option><?php foreach ($estados as $e) { ?><option value="<?= h($e[0]) ?>" <?= $estadoFiltro === intval($e[0]) ? 'selected' : '' ?>><?= h($e[1]) ?></option><?php } ?>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="window.location='<?= h(url('reportes/estados')) ?>'"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g">
              <div class="dd">
                <button class="btn btn-sm" id="btnExportar"><i class="fa fa-download"></i> Exportar <i class="fa fa-angle-down"></i></button>
                <div class="dd-menu">
                  <a href="#" id="expCsv"><i class="fa fa-file-text-o"></i> Descargar CSV</a>
                  <a href="#" id="expPrint"><i class="fa fa-print"></i> Imprimir listado</a>
                </div>
              </div>
            </div>
            <div class="g"><span class="muted"><?= number_format(count($filas)) ?> registro(s)</span></div>
          </div>

          <div class="rowg thead">
            <span>Expediente</span><span>Documento</span><span>Asunto</span><span>Estado</span><span>Fecha</span><span>Trámite</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($filas) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay documentos tramitados con esos filtros.</div>
            <?php }
            foreach (array_slice($filas, 0, 500) as $r) { ?>
              <div class="row rowg">
                <span class="exp"><?= h($r[1]) ?></span>
                <div class="dcell"><span class="cell-txt"><?= h($r[2]) ?></span></div>
                <div class="dcell">
                  <p class="asun"><?= h($r[3]) ?></p>
                </div>
                <div><span class="badge b-blue"><?= h(strtoupper($r[4])) ?></span></div>
                <div class="muted"><?= h($r[5]) ?></div>
                <div><span class="badge b-gray"><?= h($r[7]) ?></span></div>
                <div class="act">
                  <?php $segUrlR = ($r[7] === 'INTERNO') ? url('reportes/consulta-interna') . '?codigo_doc=' . rawurlencode((string)$r[1]) : url('consulta') . '?num_exp=' . rawurlencode((string)$r[1]); ?>
                  <a class="ab ab-b" title="Seguimiento" target="_blank" href="<?= h($segUrlR) ?>"><i class="fa fa-eye"></i></a>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

