<?php
/* Recepción de Documentos / Documentos Derivados (mismo diseño que sisgedov2).
   Variables: ver RecepcionController::index */
use App\Services\RecepcionFormato as F;

$esEnv = $vistaEstado === 'enviado';
$colores = array('blue' => array('b-blue', '#3b82f6'), 'purple' => array('b-purple', '#a855f7'), 'green' => array('b-green', '#22c55e'));
$first = isset($filas[0]) ? $filas[0] : null;
$selStyle = 'width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px';
?>
<!-- CONTENIDO -->
<div class="main">
  <h1 class="t"><?= $esEnv ? 'Documentos Derivados' : 'Recepción de Documentos' ?></h1>
  <div class="subt"><?= $esEnv ? 'Documentos derivados por tu oficina a otras dependencias' : 'Administra los documentos recibidos por la entidad' ?><?php if ($vistaEstado !== 'pendiente') { ?> — mostrando <b><?= h($sufijo) ?></b> del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?> (use los filtros de fecha para ampliar el rango)<?php } ?></div>

  <div class="kpis">
    <?php foreach ($kpis as $idx => $c) {
      $kpiOn = !$esEnv && (($idx === 0 && $vistaEstado === 'pendiente') || ($idx === 2 && $vistaEstado === 'recepcionado')); ?>
      <div class="kpi<?= $kpiOn ? ' kpi-on' : '' ?>" <?php if ($c[7] !== '') { ?> style="cursor:pointer" title="Ver <?= h(mb_strtolower($c[0], 'UTF-8')) ?>" onclick="setEstado('<?= h($c[7]) ?>')" <?php } ?>>
        <div class="ic <?= $c[4] ?>"><i class="fa <?= $c[3] ?>"></i></div>
        <div>
          <div class="lab <?= $c[6] ?>"><?= h($c[0]) ?></div>
          <div class="num" style="color:<?= $c[5] ?>"><?= $c[1] === null ? '—' : number_format((int)$c[1]) ?></div>
          <div class="ss"><?= h($c[2]) ?></div>
        </div>
      </div>
    <?php } ?>
  </div>

  <!-- Filtros -->
  <div class="card">
    <div class="filters">
      <div class="fld field-search"><label>&nbsp;</label>
        <div class="inp"><input id="buscador" value="<?= h($f['q']) ?>" placeholder="Buscar por expediente, documento, asunto..." /><i class="fa fa-search"></i></div>
      </div>
      <div class="fld"><label>Desde</label>
        <div class="inp"><input id="desde" type="date" min="<?= h($fechaMin) ?>" max="<?= h($fechaMax) ?>" value="<?= h($f['desde']) ?>" /></div>
      </div>
      <div class="fld"><label>Hasta</label>
        <div class="inp"><input id="hasta" type="date" min="<?= h($fechaMin) ?>" max="<?= h($fechaMax) ?>" value="<?= h($f['hasta']) ?>" /></div>
      </div>
      <div class="fld"><label>Tipo documento</label>
        <div class="inp" style="padding:0"><select id="tipo" style="<?= $selStyle ?>">
            <option value="Todos" <?= $f['tipo'] === 'Todos' ? 'selected' : '' ?>>Todos</option>
            <?php foreach ($tipos as $o) { ?><option value="<?= h($o) ?>" <?= F::norm($f['tipo']) === F::norm($o) ? 'selected' : '' ?>><?= h($o) ?></option><?php } ?>
          </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
      </div>
      <div class="fld"><label><?= $esEnv ? 'Destino' : 'Origen' ?></label>
        <div class="inp" style="padding:0"><select id="origen" style="<?= $selStyle ?>">
            <option value="Todos" <?= $f['origen'] === 'Todos' ? 'selected' : '' ?>>Todos</option>
            <?php foreach ($origenes as $o) { ?><option value="<?= h($o) ?>" <?= F::norm($f['origen']) === F::norm($o) ? 'selected' : '' ?>><?= h($o) ?></option><?php } ?>
          </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
      </div>
      <div class="fld"><label>Estado</label>
        <div class="inp" style="padding:0"><select id="estado" style="<?= $selStyle ?>">
            <?php $opc = $estados + array('Recepcionado' => 'Recepcionado', 'Derivado' => 'Derivado'); ?>
            <option value="Todos" <?= $f['estado'] === 'Todos' ? 'selected' : '' ?>>Pendientes (todos)</option>
            <?php foreach ($opc as $o) { ?><option value="<?= h($o) ?>" <?= F::norm($f['estado']) === F::norm($o) ? 'selected' : '' ?>><?= h($o) ?></option><?php } ?>
          </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
      </div>
      <div class="field-actions">
        <button type="button" class="btn" onclick="limpiarFiltros()"><i class="fa fa-refresh"></i> Limpiar</button>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card table-card">
    <div class="tbar">
      <div class="g">
        <label class="selrow"><input type="checkbox" class="chk" id="selall"> Seleccionar todo</label>
        <?php if ($vistaEstado === 'pendiente') { ?>
          <button class="btn btn-p btn-sm" id="btnRecepSel"><i class="fa fa-check-circle"></i> Recepcionar seleccionados<span id="cntSel" class="selcount" style="display:none"></span></button>
          <button class="btn btn-sm" id="btnDerivar"><i class="fa fa-share"></i> Derivar</button>
        <?php } else { ?>
          <span class="muted" style="font-size:12px">Bandeja de <?= h($sufijo) ?><?= $esEnv ? ' (solo consulta)' : '' ?><span id="cntSel" class="selcount"></span></span>
        <?php } ?>
        <div class="dd">
          <button class="btn btn-sm" id="btnExportar"><i class="fa fa-download"></i> Exportar <i class="fa fa-angle-down"></i></button>
          <div class="dd-menu">
            <a href="#" id="expPdf"><i class="fa fa-file-pdf-o"></i> Descargar PDF</a>
            <a href="#" id="expXls"><i class="fa fa-file-excel-o"></i> Descargar Excel (.xls)</a>
            <a href="#" id="expCsv"><i class="fa fa-file-text-o"></i> Descargar CSV</a>
            <a href="#" id="expPrint"><i class="fa fa-print"></i> Imprimir listado</a>
          </div>
        </div>
        <div class="dd">
          <button class="btn btn-sm" id="btnMas"><i class="fa fa-ellipsis-v"></i> Más acciones</button>
          <div class="dd-menu">
            <a href="#" id="masActualizar"><i class="fa fa-refresh"></i> Actualizar bandeja</a>
            <a href="#" id="masCopiar"><i class="fa fa-clipboard"></i> Copiar expedientes seleccionados</a>
            <a href="#" id="masLimpiar"><i class="fa fa-eraser"></i> Limpiar filtros</a>
          </div>
        </div>
      </div>
      <div class="g"><span class="muted">Ordenar por:</span>
        <div class="inp btn-sm" style="width:160px;padding:0"><select id="sort" style="<?= $selStyle ?>">
            <?php foreach (array('recentes' => 'Más recientes', 'antiguos' => 'Más antiguos', 'exp_asc' => 'Expediente A-Z', 'exp_desc' => 'Expediente Z-A') as $k => $t) { ?>
              <option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= $t ?></option>
            <?php } ?>
          </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div><button type="button" class="btn btn-sm" style="width:36px;padding:0" title="Aplicar orden" onclick="filtrar()"><i class="fa fa-sliders"></i></button>
      </div>
    </div>

    <div class="rowg thead">
      <span><input type="checkbox" class="chk" id="selall2"></span><span>Expediente</span><span>Documento</span><span><?= h($colOrigen) ?></span><span>Asunto</span><span>Estado</span><span><?= h($colFecha) ?></span><span>Acciones</span>
    </div>

    <div id="tbody">
      <?php if (!$hayDatos) { ?>
        <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px"><?= $vistaEstado === 'pendiente' ? 'No hay documentos por recepcionar en esta oficina.' : 'No hay documentos ' . h($sufijo) . ' en el rango de fechas seleccionado (por defecto, últimos 30 días).' ?></div>
      <?php }
      foreach ($filas as $i => $r) {
        list($bcls, $icol) = $colores[F::color($r[3])];
        $ext = stripos((string)$r[1], 'MESA DE PARTES') !== false;
        $estadoFila = F::estado($r[11]);
        $fp = explode(' ', (string)$r[9], 2);
        $adj = $r['adj']; ?>
        <div class="row rowg <?= $i === 0 ? 'sel' : '' ?>" data-codigo="<?= h($r[0]) ?>" data-tipo="<?= h($r[11]) ?>" data-exp="<?= h($r[4]) ?>" data-doc="<?= h($r[3] . ' N° ' . $r[5]) ?>" data-ori="<?= h($r[1]) ?>" data-tram="<?= h($r[2]) ?>" data-fecha="<?= h($r[9]) ?>" data-asunto="<?= h($r[6]) ?>" data-adj="<?= h($adj !== '' ? $adj : '#') ?>" data-estado="<?= h($estadoFila) ?>" data-badge="<?= h(mb_strtoupper((string)$r[3], 'UTF-8')) ?>" data-search="<?= h(mb_strtolower($r[4] . ' ' . $r[3] . ' ' . $r[6] . ' ' . $r[1] . ' ' . $estadoFila, 'UTF-8')) ?>">
          <span><input type="checkbox" class="chk" onclick="event.stopPropagation()"></span>
          <span class="exp"><?= h($r[4]) ?></span>
          <div class="dcell">
            <div style="display:flex;gap:8px"><i class="fa fa-file-text-o" style="color:<?= $icol ?>;margin-top:2px"></i><span class="nm"><?= h($r[3]) ?> <?= h($r[5]) ?></span></div><span class="badge <?= $bcls ?>"><?= h(mb_strtoupper((string)$r[3], 'UTF-8')) ?></span>
          </div>
          <div class="dcell">
            <div style="display:flex;gap:8px"><i class="fa <?= $ext ? 'fa-users' : 'fa-building-o' ?>" style="color:#94a3b8;margin-top:2px"></i><span class="cell-txt"><?= h($r[1]) ?></span></div><span class="badge b-gray"><?= $ext ? 'EXTERNO' : 'INTERNO' ?></span>
          </div>
          <div class="dcell">
            <p class="asun"><?= h($r[6]) ?></p><?php if (mb_strlen((string)$r[6], 'UTF-8') > 72) { ?><button class="vermas" onclick="event.stopPropagation();toggleAsunto(this)" style="margin-top:4px;border:0;background:none;padding:0;font-size:12px;font-weight:500;color:#2563eb;cursor:pointer">Ver más</button><?php } ?>
          </div>
          <div><span class="st"><span class="dot <?= F::punto($r[11]) ?>"></span> <?= h($estadoFila) ?></span></div>
          <div class="muted">
            <div style="font-weight:500;color:#475569"><?= h($fp[0]) ?></div>
            <div><?= isset($fp[1]) ? h($fp[1]) : '' ?></div>
            <div style="color:#94a3b8;margin-top:2px"><?= h(F::hace($r[12])) ?></div>
          </div>
          <div class="act">
            <?php if ($vistaEstado === 'pendiente') { ?>
              <button class="ab ab-g" title="Recepcionar" onclick="event.stopPropagation();abrirRecep('<?= h($r[0]) ?>','<?= h($r[11]) ?>')"><i class="fa fa-download"></i></button>
            <?php } elseif ($vistaEstado === 'recepcionado') { ?>
              <button class="ab ab-g" title="Derivar" onclick="event.stopPropagation();abrirAccionR(this,'derivar')"><i class="fa fa-paper-plane"></i></button>
              <button class="ab ab-b" title="Devolver" onclick="event.stopPropagation();abrirAccionR(this,'devolver')"><i class="fa fa-arrow-left"></i></button>
              <button class="ab ab-b" title="Archivar" onclick="event.stopPropagation();abrirArchivar(this)"><i class="fa fa-archive"></i></button>
              <a class="ab ab-b" title="Imprimir cargo" target="_blank" rel="noopener" href="<?= h($r['cargo']) ?>" onclick="event.stopPropagation()"><i class="fa fa-print"></i></a>
              <a class="ab ab-b" title="Seguimiento" target="_blank" rel="noopener" href="<?= h($r['seguimiento']) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
            <?php } else { ?>
              <a class="ab ab-b" title="Seguimiento" target="_blank" rel="noopener" href="<?= h($r['seguimiento']) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
              <a class="ab ab-b" title="Imprimir cargo de derivación" target="_blank" rel="noopener" href="<?= h($r['cargo']) ?>" onclick="event.stopPropagation()"><i class="fa fa-print"></i></a>
            <?php } ?>
            <?php if ($adj !== '') { ?><a class="ab ab-b" title="Ver adjunto" target="_blank" rel="noopener" href="<?= h($adj) ?>" onclick="event.stopPropagation()"><i class="fa fa-paperclip"></i></a><?php } else { ?><span class="ab ab-d" title="Sin adjunto"><i class="fa fa-paperclip"></i></span><?php } ?>
          </div>
        </div>
      <?php } ?>
    </div>

    <div class="pag">
      <span class="muted">Mostrando <?= $total === 0 ? 0 : $inicio + 1 ?> a <?= min($inicio + $porPagina, $total) ?> de <?= number_format($total) ?> registros</span>
      <div style="display:flex;align-items:center;gap:16px">
        <div class="inp btn-sm" style="width:120px;padding:0"><select id="per_page" style="<?= $selStyle ?>">
            <?php foreach (array(25, 50, 100) as $n) { ?><option value="<?= $n ?>" <?= $porPagina === $n ? 'selected' : '' ?>><?= $n ?> por página</option><?php } ?>
          </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
        <div style="display:flex;gap:4px">
          <a class="pg" href="<?= h($paginaUrl(1)) ?>"><i class="fa fa-angle-double-left"></i></a>
          <a class="pg <?= $pagina > 1 ? '' : 'disabled' ?>" href="<?= h($paginaUrl(max(1, $pagina - 1))) ?>"><i class="fa fa-angle-left"></i></a>
          <?php
          $ini = max(1, $pagina - 2);
          $fin = min($totalPaginas, $pagina + 2);
          if ($ini > 1) {
            echo '<a class="pg" href="' . h($paginaUrl(1)) . '">1</a>';
            if ($ini > 2) echo '<button class="pg" style="width:28px">...</button>';
          }
          for ($p = $ini; $p <= $fin; $p++) {
            echo '<a class="' . ($p === $pagina ? 'pg on' : 'pg') . '" href="' . h($paginaUrl($p)) . '">' . $p . '</a>';
          }
          if ($fin < $totalPaginas) {
            if ($fin < $totalPaginas - 1) echo '<button class="pg" style="width:28px">...</button>';
            echo '<a class="pg" href="' . h($paginaUrl($totalPaginas)) . '">' . $totalPaginas . '</a>';
          }
          ?>
          <a class="pg <?= $pagina < $totalPaginas ? '' : 'disabled' ?>" href="<?= h($paginaUrl(min($totalPaginas, $pagina + 1))) ?>"><i class="fa fa-angle-right"></i></a>
          <a class="pg" href="<?= h($paginaUrl($totalPaginas)) ?>"><i class="fa fa-angle-double-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PANEL DERECHO -->
<div class="rp">
  <div class="hd">
    <h2>Detalles del documento</h2><button class="ib" style="color:#94a3b8"><i class="fa fa-times"></i></button>
  </div>
  <div class="bd">
    <?php if ($first) { ?>
      <div class="docbox">
        <div class="ic"><i class="fa fa-file-text-o"></i></div>
        <div style="min-width:0">
          <div id="d-doc" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[3] . ' N° ' . $first[5]) ?></div><span id="d-badge" class="badge <?= $colores[F::color($first[3])][0] ?>"><?= h(mb_strtoupper((string)$first[3], 'UTF-8')) ?></span>
        </div>
      </div>
      <div class="rp-scroll">
        <div class="sect">Información general</div>
        <dl>
          <div><dt><?= h($colOrigen) ?></dt><dd id="d-ori"><?= h($first[1]) ?></dd></div>
          <div><dt><?= h($colFecha) ?></dt><dd id="d-fecha"><?= h($first[9]) ?></dd></div>
          <div><dt>Trámite</dt><dd id="d-tram"><?= h($first[2]) ?></dd></div>
          <div><dt>N° Expediente</dt><dd id="d-exp"><?= h($first[4]) ?></dd></div>
          <div><dt>Estado actual</dt><dd><span class="st"><span class="dot <?= F::punto($first[11]) ?>"></span> <?= h(F::estado($first[11])) ?></span></dd></div>
        </dl>
        <div class="sect">Asunto</div>
        <p id="d-asunto" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[6]) ?></p>
        <div class="sect">Archivos adjuntos</div>
        <div id="d-adjbox">
          <?php if ($first['adj'] !== '') { ?>
            <a class="adj" href="<?= h($first['adj']) ?>" target="_blank" rel="noopener" style="text-decoration:none"><i class="fa fa-file-pdf-o" style="color:#ef4444;font-size:20px"></i>
              <div style="flex:1">
                <div class="nm">Documento adjunto</div>
                <div class="sz">Ver archivo</div>
              </div><i class="fa fa-download" style="color:#94a3b8"></i>
            </a>
          <?php } else { ?><div class="muted" style="margin-top:8px">Sin archivos adjuntos.</div><?php } ?>
        </div>
        <div style="margin-top:16px">
          <div class="coll"><span class="lbl"><i class="fa fa-comment-o" style="color:#94a3b8"></i> Observaciones <span class="pill">0</span></span><i class="fa fa-angle-down" style="color:#94a3b8"></i></div>
          <div class="coll" id="historialToggle" role="button" tabindex="0"><span class="lbl"><i class="fa fa-clock-o" style="color:#94a3b8"></i> Historial <span class="pill" id="historial-count">0</span></span><i class="fa fa-angle-down" id="historial-arrow" style="color:#94a3b8"></i></div>
          <div id="historial-list" class="history-box" aria-live="polite"></div>
        </div>
      </div>
    <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Selecciona un documento para ver sus detalles.</div><?php } ?>
  </div>
  <div class="ftr">
    <?php if ($vistaEstado === 'pendiente') { ?>
      <button class="btn btn-p" style="flex:1;justify-content:center" onclick="<?= $first ? "abrirRecep('" . h($first[0]) . "','" . h($first[11]) . "')" : '' ?>"><i class="fa fa-check-circle"></i> Recepcionar</button>
      <button class="btn" onclick="abrirDeriv()"><i class="fa fa-share"></i> Derivar</button>
      <button class="btn" style="padding:0 10px"><i class="fa fa-ellipsis-v"></i></button>
    <?php } else { ?>
      <div class="muted" style="flex:1;text-align:center;font-size:12px">Documento <?= $vistaEstado === 'recepcionado' ? 'ya recepcionado' : 'derivado a otra oficina' ?>.</div>
    <?php } ?>
  </div>
</div>

<!-- MODAL RECEPCIONAR -->
<div class="modal" id="modalRecep">
  <div class="box">
    <div class="mh">
      <h3>Recepcionar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarRecep()"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb">
      <div class="muted" id="rc_info" style="font-size:12px"></div>
      <div><label>Acción a realizar <span class="req">*</span></label><select id="rc_accion">
          <option value="">Seleccione una acción...</option>
        </select></div>
      <div><label>Folios <span class="req">*</span></label><input id="rc_folio" type="number" min="1" placeholder="Cantidad de folios" /></div>
      <div><label>Detalles / Proveído</label><textarea id="rc_detalles" rows="3" placeholder="Observaciones (opcional)"></textarea></div>
    </div>
    <div class="mf"><button class="btn" onclick="cerrarRecep()">Cancelar</button><button class="btn btn-p" id="rc_guardar"><i class="fa fa-check-circle"></i> Confirmar recepción</button></div>
  </div>
</div>

<!-- MODAL DERIVAR (recepciona el documento y lo deriva a otra oficina en un solo paso) -->
<div class="modal" id="modalDeriv">
  <div class="box">
    <div class="mh">
      <h3 id="dv_titulo">Derivar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarDeriv()"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb">
      <div class="muted" id="dv_info" style="font-size:12px"></div>
      <div><label>Oficina destino <span class="req">*</span></label><select id="dv_oficina">
          <option value="">Seleccione oficina...</option>
        </select></div>
      <div><label>Acción <span class="req">*</span></label><select id="dv_accion">
          <option value="">Seleccione una acción...</option>
        </select></div>
      <div><label>Estado <span class="req">*</span></label><select id="dv_estado">
          <option value="">Seleccione estado...</option>
        </select></div>
      <div id="dv_folio_wrap"><label>Folios <span class="req">*</span></label><input id="dv_folio" type="number" min="1" placeholder="Cantidad de folios" /></div>
      <div><label>Detalles / Proveído</label><textarea id="dv_detalles" rows="3" placeholder="Indicaciones para la oficina destino (opcional)"></textarea></div>
    </div>
    <div class="mf"><button class="btn" onclick="cerrarDeriv()">Cancelar</button><button class="btn btn-p" id="dv_guardar"><i class="fa fa-share"></i> <span id="dv_btn_txt">Recepcionar y derivar</span></button></div>
  </div>
</div>

<!-- MODAL ARCHIVAR (guarda el documento recepcionado en un archivador de la oficina) -->
<div class="modal" id="modalArch">
  <div class="box">
    <div class="mh">
      <h3>Archivar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarArchivar()"><i class="fa fa-times"></i></button>
    </div>
    <div class="mb">
      <div class="muted" id="ar_info" style="font-size:12px"></div>
      <div><label>Archivador <span class="req">*</span></label><select id="ar_archivador">
          <option value="">Seleccione archivador...</option>
        </select></div>
      <div><label>Estado <span class="req">*</span></label><select id="ar_estado">
          <option value="">Seleccione estado...</option>
        </select></div>
      <div><label>Detalles</label><textarea id="ar_detalles" rows="3" placeholder="Observaciones del archivado (opcional)"></textarea></div>
    </div>
    <div class="mf"><button class="btn" onclick="cerrarArchivar()">Cancelar</button><button class="btn btn-p" id="ar_guardar"><i class="fa fa-archive"></i> Archivar</button></div>
  </div>
</div>
