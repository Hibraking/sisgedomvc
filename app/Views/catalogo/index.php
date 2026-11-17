<?php
/* Catálogo genérico (Mantenimiento / Configuración) — mismo diseño que sisgedov2/partes/plantilla_catalogo.php.
   Variables: $CAT, $CAT_kpis, $CAT_grid, $CAT_ncols (ver CatalogoController::index) */
$CAT_total = count($CAT['datos']);
$CAT_first = $CAT_total > 0 ? $CAT['datos'][0] : null;
?>
<style>.rowg { grid-template-columns: <?= h($CAT_grid) ?> }</style>
      <!-- CONTENIDO -->
      <div class="main">
        <h1 class="t"><?= h($CAT['titulo']) ?></h1>
        <div class="subt"><?= h($CAT['subtitulo']) ?></div>

        <div class="kpis">
          <div class="kpi">
            <div class="ic t-blue"><i class="fa <?= $CAT['icono'] ?>"></i></div>
            <div>
              <div class="lab lab-blue">Registros</div>
              <div class="num" style="color:#1e293b"><?= number_format($CAT_total) ?></div>
              <div class="ss">Total en el catálogo</div>
            </div>
          </div>
          <?php foreach ($CAT_kpis as $k) { ?>
            <div class="kpi">
              <div class="ic <?= h($k[4]) ?>"><i class="fa <?= h($k[3]) ?>"></i></div>
              <div>
                <div class="lab <?= h($k[5]) ?>"><?= h($k[0]) ?></div>
                <div class="num" style="color:#1e293b"><?= h($k[1]) ?></div>
                <div class="ss"><?= h($k[2]) ?></div>
              </div>
            </div>
          <?php } ?>
          <div class="kpi" style="cursor:pointer" onclick="abrirNuevoCat()">
            <div class="ic t-green"><i class="fa fa-plus"></i></div>
            <div>
              <div class="lab lab-green">Nuevo</div>
              <div class="num" style="color:#16a34a;font-size:15px;padding-top:6px">Agregar <?= h($CAT['singular']) ?></div>
              <div class="ss">Crear un registro</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" placeholder="Buscar en el catálogo..." autocomplete="off" /><i class="fa fa-search"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="document.getElementById('buscador').value='';filtrarCat()"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g">
              <button class="btn btn-p btn-sm" onclick="abrirNuevoCat()"><i class="fa fa-plus"></i> Nuevo registro</button>
            </div>
            <div class="g"><span class="muted" id="contadorCat"><?= number_format($CAT_total) ?> registro(s)</span></div>
          </div>

          <div class="rowg thead">
            <?php foreach ($CAT['columnas'] as $c) { ?><span><?= h($c) ?></span><?php } ?><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if ($CAT_total === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">El catálogo está vacío. Crea el primer registro con el botón "Nuevo".</div>
            <?php }
            foreach ($CAT['datos'] as $idx => $r) { ?>
              <div class="row rowg <?= $idx === 0 ? 'sel' : '' ?>" data-id="<?= h($r[0]) ?>" <?php for ($i = 1; $i <= $CAT_ncols; $i++) { ?> data-f<?= $i ?>="<?= h(isset($r[$i]) ? $r[$i] : '') ?>" <?php } ?>>
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa <?= $CAT['icono'] ?>" style="color:#3b82f6;margin-top:2px"></i><span class="nm"><?= h(isset($r[1]) ? $r[1] : '') ?></span></div>
                </div>
                <?php for ($i = 2; $i <= $CAT_ncols; $i++) { ?>
                  <div class="dcell"><span class="cell-txt"><?= h(isset($r[$i]) && $r[$i] !== '' ? $r[$i] : '—') ?></span></div>
                <?php } ?>
                <div class="act">
                  <button class="ab ab-g" title="Editar" onclick="event.stopPropagation();abrirEditarCat(this)"><i class="fa fa-pencil"></i></button>
                  <?php if (empty($CAT['sin_borrar'])) { ?><button class="ab ab-r" title="Eliminar" onclick="event.stopPropagation();eliminarCat(this)"><i class="fa fa-trash-o"></i></button><?php } ?>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles del registro</h2>
        </div>
        <div class="bd">
          <?php if ($CAT_first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa <?= $CAT['icono'] ?>"></i></div>
              <div style="min-width:0">
                <div id="p-nombre" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($CAT_first[1]) ?></div><span class="badge b-blue" id="p-id">ID <?= h($CAT_first[0]) ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Información</div>
              <dl class="cat-dl" style="margin-top:6px">
                <?php foreach ($CAT['columnas'] as $i => $c) { ?>
                  <div>
                    <dt><?= h($c) ?></dt>
                    <dd id="p-f<?= $i + 1 ?>"><?= h(isset($CAT_first[$i + 1]) && $CAT_first[$i + 1] !== '' ? $CAT_first[$i + 1] : '—') ?></dd>
                  </div>
                <?php } ?>
              </dl>
              <div class="muted" style="font-size:11.5px;margin-top:14px"><i class="fa fa-info-circle"></i> Haz clic en cualquier fila para ver sus detalles aquí.</div>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Sin registros para mostrar.</div><?php } ?>
        </div>
        <?php if ($CAT_first) { ?>
          <div class="ftr">
            <button class="btn btn-p" style="flex:1;justify-content:center" onclick="editarSeleccionado()"><i class="fa fa-pencil"></i> Editar</button>
            <?php if (empty($CAT['sin_borrar'])) { ?><button class="btn" style="color:#dc2626" onclick="eliminarSeleccionado()"><i class="fa fa-trash-o"></i> Eliminar</button><?php } ?>
          </div>
        <?php } ?>
      </div>

  <!-- MODAL NUEVO / EDITAR -->
  <div class="modal" id="modalCat">
    <div class="box">
      <div class="mh">
        <h3 id="mc_titulo">Nuevo registro</h3><button class="ib" style="color:#94a3b8" onclick="cerrarCat()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <?php foreach ($CAT['campos'] as $i => $f) {
          $fid = 'mc_f' . ($i + 1); ?>
          <div><label><?= h($f['label']) ?> <?php if (!empty($f['req'])) { ?><span class="req">*</span><?php } ?></label>
            <?php if ($f['tipo'] === 'textarea') { ?><textarea id="<?= $fid ?>" rows="2"></textarea>
            <?php } elseif ($f['tipo'] === 'number') { ?><input id="<?= $fid ?>" type="number" />
            <?php } else { ?><input id="<?= $fid ?>" type="text" /><?php } ?>
          </div>
        <?php } ?>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarCat()">Cancelar</button><button class="btn btn-p" id="mc_guardar"><i class="fa fa-check"></i> <span id="mc_btn_txt">Guardar</span></button></div>
    </div>
  </div>

