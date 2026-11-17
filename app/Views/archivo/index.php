<?php
/* Archivo de documentos — mismo diseño que sisgedov2. Variables: ver ArchivoController::index */
?>
      <div class="main">
        <h1 class="t">Archivo de Documentos</h1>
        <div class="subt">Archiva y consulta los documentos de tu oficina<?php if ($bandeja === 'porarchivar') { ?> — tramitados del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?> (amplía con los filtros de fecha)<?php } ?></div>

        <div class="kpis">
          <?php
          $cards = [
            ["Por archivar", number_format($porArchivar), "Emitidos y tramitados", "fa-inbox", "t-orange", "#f97316", "lab-orange", "porarchivar"],
            ["Archivados", number_format($cont['archivados']), "Total en archivadores", "fa-archive", "t-green", "#16a34a", "lab-green", "archivados"],
            ["Archivadores", number_format($cont['archivadores']), "De esta oficina", "fa-folder-open", "t-blue", "#1e293b", "lab-blue", ""],
            ["Emitidos sin archivar", number_format($cont['emitidos_sin_archivar']), "Documentos propios", "fa-folder-o", "t-purple", "#6d28d9", "lab-purple", "porarchivar"],
          ];
          foreach ($cards as $idx => $c) {
            $kpiOn = ($c[7] !== '' && $c[7] === $bandeja && $idx < 2);
          ?>
            <div class="kpi<?= $kpiOn ? ' kpi-on' : '' ?>" <?php if ($c[7] !== '') { ?> style="cursor:pointer" onclick="setBandeja('<?= $c[7] ?>')" <?php } ?>>
              <div class="ic <?= $c[4] ?>"><i class="fa <?= $c[3] ?>"></i></div>
              <div>
                <div class="lab <?= $c[6] ?>"><?= $c[0] ?></div>
                <div class="num" style="color:<?= $c[5] ?>"><?= $c[1] ?></div>
                <div class="ss"><?= $c[2] ?></div>
              </div>
            </div>
          <?php } ?>
        </div>

        <!-- Filtros -->
        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por documento, asunto, detalles..." /><i class="fa fa-search"></i></div>
            </div>
            <div class="fld"><label>Desde</label>
              <div class="inp"><input id="desde" type="date" max="<?= h($hoy) ?>" value="<?= h($desdeFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Hasta</label>
              <div class="inp"><input id="hasta" type="date" max="<?= h($hoy) ?>" value="<?= h($hastaFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Bandeja</label>
              <div class="inp" style="padding:0"><select id="bandeja" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="porarchivar" <?= $bandeja === 'porarchivar' ? 'selected' : '' ?>>Por archivar</option>
                  <option value="archivados" <?= $bandeja === 'archivados' ? 'selected' : '' ?>>Archivados</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="fld"><label>Archivador</label>
              <div class="inp" style="padding:0"><select id="archivador" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="0">Todos</option><?php foreach ($archivadores as $a) { ?><option value="<?= h($a[0]) ?>" <?= $archFiltro === intval($a[0]) ? 'selected' : '' ?>><?= h($a[1] . ' - ' . $a[2] . ' (' . $a[4] . ')') ?></option><?php } ?>
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
              <button class="btn btn-p btn-sm" id="btnNuevoArch"><i class="fa fa-plus"></i> Nuevo archivador</button>
              <div class="dd">
                <button class="btn btn-sm" id="btnExportar"><i class="fa fa-download"></i> Exportar <i class="fa fa-angle-down"></i></button>
                <div class="dd-menu">
                  <a href="#" id="expPdf"><i class="fa fa-file-pdf-o"></i> Descargar PDF</a>
                  <a href="#" id="expXls"><i class="fa fa-file-excel-o"></i> Descargar Excel (.xls)</a>
                  <a href="#" id="expCsv"><i class="fa fa-file-text-o"></i> Descargar CSV</a>
                  <a href="#" id="expPrint"><i class="fa fa-print"></i> Imprimir listado</a>
                </div>
              </div>
            </div>
            <div class="g"><span class="muted">Ordenar por:</span>
              <div class="inp btn-sm" style="width:160px;padding:0"><select id="sort" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="recentes" <?= $sortFiltro === 'recentes' ? 'selected' : '' ?>>Más recientes</option>
                  <option value="antiguos" <?= $sortFiltro === 'antiguos' ? 'selected' : '' ?>>Más antiguos</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
          </div>

          <div class="rowg thead">
            <span>Documento</span><span>Asunto</span><span>Detalles</span><span><?= $bandeja === 'archivados' ? 'Archivador' : 'Clase' ?></span><span>Fecha</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($datos) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px"><?= $bandeja === 'archivados' ? 'Aún no hay documentos archivados en esta oficina.' : 'No hay documentos por archivar en el rango seleccionado (por defecto, últimos 30 días para tramitados).' ?></div>
            <?php }
            foreach ($datosPagina as $i => $r) {
              $esArchivados = ($bandeja === 'archivados');
              $clase = $esArchivados ? $r[9] : $r[1];
              $etiqueta = $esArchivados ? $r[1] : ($clase === 'EMITIDO' ? 'Emitido' : 'Tramitado');
              $adj = $r[8];
            ?>
              <div class="row rowg <?= $i === 0 ? 'sel' : '' ?>" data-id="<?= h($r[0]) ?>" data-clase="<?= h($clase) ?>" data-doc="<?= h($r[2] . ' ' . $r[3]) ?>" data-asunto="<?= h($r[4]) ?>" data-det="<?= h($r[5]) ?>" data-fecha="<?= h($r[6]) ?>" data-adj="<?= h($adj) ?>" data-eti="<?= h($etiqueta) ?>">
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa fa-file-text-o" style="color:#3b82f6;margin-top:2px"></i><span class="nm"><?= h($r[2]) ?> <?= h($r[3]) ?></span></div><span class="badge <?= $clase === 'EMITIDO' ? 'b-purple' : 'b-blue' ?>"><?= h($clase) ?></span>
                </div>
                <div class="dcell">
                  <p class="asun"><?= h($r[4]) ?></p>
                </div>
                <div class="dcell">
                  <p class="asun"><?= h($r[5]) ?></p>
                </div>
                <div><span class="badge b-gray"><?= h($etiqueta) ?></span></div>
                <div class="muted"><?= h($r[6]) ?></div>
                <div class="act">
                  <?php if (!$esArchivados) { ?><button class="ab ab-g" title="Archivar" onclick="event.stopPropagation();abrirArchivar(this)"><i class="fa fa-archive"></i></button><?php } ?>
                  <?php if ($adj !== '#' && $adj !== '' && $adj !== null) { ?><a class="ab ab-b" title="Ver adjunto" target="_blank" href="<?= h($adj) ?>" onclick="event.stopPropagation()"><i class="fa fa-paperclip"></i></a><?php } else { ?><span class="ab ab-d" title="Sin adjunto"><i class="fa fa-paperclip"></i></span><?php } ?>
                </div>
              </div>
            <?php } ?>
          </div>

          <div class="pag">
            <?php $desdeN = $totalTabla === 0 ? 0 : $inicioPagina + 1;
            $hastaN = min($inicioPagina + $porPagina, $totalTabla); ?>
            <span class="muted">Mostrando <?= $desdeN ?> a <?= $hastaN ?> de <?= number_format($totalTabla) ?> registros</span>
            <div style="display:flex;align-items:center;gap:16px">
              <div class="inp btn-sm" style="width:120px;padding:0"><select id="per_page" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 por página</option>
                  <option value="50" <?= $porPagina === 50 ? 'selected' : '' ?>>50 por página</option>
                  <option value="100" <?= $porPagina === 100 ? 'selected' : '' ?>>100 por página</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
              <div style="display:flex;gap:4px">
                <a class="pg" href="<?= h($paginaUrl(1)) ?>"><i class="fa fa-angle-double-left"></i></a>
                <a class="pg <?= $paginaActual > 1 ? '' : 'disabled' ?>" href="<?= h($paginaUrl(max(1, $paginaActual - 1))) ?>"><i class="fa fa-angle-left"></i></a>
                <?php
                $ventana = 2;
                $inicioBotones = max(1, $paginaActual - $ventana);
                $finBotones = min($totalPaginas, $paginaActual + $ventana);
                if ($inicioBotones > 1) {
                  echo '<a class="pg" href="' . h($paginaUrl(1)) . '">1</a>';
                  if ($inicioBotones > 2) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                }
                for ($p = $inicioBotones; $p <= $finBotones; $p++) {
                  $cls = $p === $paginaActual ? 'pg on' : 'pg';
                  echo '<a class="' . $cls . '" href="' . h($paginaUrl($p)) . '">' . $p . '</a>';
                }
                if ($finBotones < $totalPaginas) {
                  if ($finBotones < $totalPaginas - 1) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                  echo '<a class="pg" href="' . h($paginaUrl($totalPaginas)) . '">' . $totalPaginas . '</a>';
                }
                ?>
                <a class="pg <?= $paginaActual < $totalPaginas ? '' : 'disabled' ?>" href="<?= h($paginaUrl(min($totalPaginas, $paginaActual + 1))) ?>"><i class="fa fa-angle-right"></i></a>
                <a class="pg" href="<?= h($paginaUrl($totalPaginas)) ?>"><i class="fa fa-angle-double-right"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles</h2>
        </div>
        <div class="bd">
          <?php if ($first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-file-text-o"></i></div>
              <div style="min-width:0">
                <div id="d-doc" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[2] . ' ' . $first[3]) ?></div><span id="d-badge" class="badge b-blue"><?= h($bandeja === 'archivados' ? $first[9] : $first[1]) ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Información</div>
              <dl>
                <div>
                  <dt><?= $bandeja === 'archivados' ? 'Archivador' : 'Clase' ?></dt>
                  <dd id="d-eti"><?= h($bandeja === 'archivados' ? $first[1] : ($first[1] === 'EMITIDO' ? 'Emitido' : 'Tramitado')) ?></dd>
                </div>
                <div>
                  <dt>Fecha</dt>
                  <dd id="d-fecha"><?= h($first[6]) ?></dd>
                </div>
              </dl>
              <div class="sect">Asunto</div>
              <p id="d-asunto" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[4]) ?></p>
              <div class="sect">Detalles</div>
              <p id="d-det" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[5]) ?></p>
              <div class="sect">Archivadores de la oficina</div>
              <div style="margin-top:8px">
                <?php if (count($archivadores) === 0) { ?><div class="muted">No hay archivadores. Crea el primero con el botón "Nuevo archivador".</div><?php } ?>
                <?php foreach ($archivadores as $a) { ?>
                  <div class="arch-item" onclick="verArchivador(<?= intval($a[0]) ?>)" title="Ver documentos de este archivador">
                    <i class="fa fa-folder" style="color:#f59e0b"></i>
                    <div style="flex:1;min-width:0">
                      <div class="nm"><?= h($a[1]) ?> — <?= h($a[2]) ?></div>
                      <div class="ss"><?= h($a[3]) ?></div>
                    </div>
                    <span class="pill"><?= intval($a[4]) ?></span>
                  </div>
                <?php } ?>
              </div>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Selecciona un documento para ver sus detalles.</div><?php } ?>
        </div>
      </div>

  <!-- MODAL ARCHIVAR -->
  <div class="modal" id="modalArch">
    <div class="box">
      <div class="mh">
        <h3>Archivar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarArchivar()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div class="muted" id="ar_info" style="font-size:12px"></div>
        <div><label>Archivador <span class="req">*</span></label><select id="ar_archivador">
            <option value="">Seleccione archivador...</option><?php foreach ($archivadores as $a) { ?><option value="<?= h($a[0]) ?>"><?= h($a[1] . ' - ' . $a[2]) ?></option><?php } ?>
          </select></div>
        <div id="ar_estado_wrap"><label>Estado <span class="req">*</span></label><select id="ar_estado">
            <option value="">Seleccione estado...</option>
          </select></div>
        <div><label>Detalles</label><textarea id="ar_detalles" rows="3" placeholder="Observaciones del archivado (opcional)"></textarea></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarArchivar()">Cancelar</button><button class="btn btn-p" id="ar_guardar"><i class="fa fa-archive"></i> Archivar</button></div>
    </div>
  </div>

  <!-- MODAL NUEVO ARCHIVADOR -->
  <div class="modal" id="modalNuevoArch">
    <div class="box">
      <div class="mh">
        <h3>Nuevo archivador</h3><button class="ib" style="color:#94a3b8" onclick="cerrarNuevoArch()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Nombre <span class="req">*</span></label><input id="na_nombre" type="text" placeholder="Ej. Oficios recibidos" /></div>
        <div><label>Año <span class="req">*</span></label><input id="na_anio" type="number" min="2000" max="2100" value="<?= h(date('Y')) ?>" /></div>
        <div><label>Detalles</label><textarea id="na_detalles" rows="2" placeholder="Descripción (opcional)"></textarea></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarNuevoArch()">Cancelar</button><button class="btn btn-p" id="na_guardar"><i class="fa fa-plus"></i> Crear archivador</button></div>
    </div>
  </div>

