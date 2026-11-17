<?php
/* Accesos del personal — mismo diseño que sisgedov2 (vistas/v_accesos_personal.php). */
?>
      <div class="main">
        <h1 class="t">Accesos del Personal</h1>
        <div class="subt">Concede o revoca el acceso de cada usuario a los módulos del sistema (el menú lateral se adapta automáticamente)</div>

        <div class="kpis">
          <div class="kpi">
            <div class="ic t-blue"><i class="fa fa-users"></i></div>
            <div>
              <div class="lab lab-blue">Usuarios</div>
              <div class="num" style="color:#1e293b"><?= number_format(count($usuarios)) ?></div>
              <div class="ss">Con cuenta de acceso</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-purple"><i class="fa fa-th-large"></i></div>
            <div>
              <div class="lab lab-purple">Módulos</div>
              <div class="num" style="color:#1e293b"><?= $usuarioSel > 0 ? number_format(count($menus)) : '—' ?></div>
              <div class="ss">Disponibles en el sistema</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-green"><i class="fa fa-key"></i></div>
            <div>
              <div class="lab lab-green">Permitidos</div>
              <div class="num" style="color:#16a34a"><?= $usuarioSel > 0 ? number_format($concedidos) : '—' ?></div>
              <div class="ss"><?= $usuarioSel > 0 ? 'Del usuario seleccionado' : 'Seleccione un usuario' ?></div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-orange"><i class="fa fa-lock"></i></div>
            <div>
              <div class="lab lab-orange">Sin acceso</div>
              <div class="num" style="color:#f97316"><?= $usuarioSel > 0 ? number_format(count($menus) - $concedidos) : '—' ?></div>
              <div class="ss"><?= $usuarioSel > 0 ? 'Módulos restringidos' : 'Seleccione un usuario' ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld" style="min-width:280px"><label>Usuario</label>
              <div class="inp" style="padding:0"><select id="selUsuario" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px" onchange="cambiarUsuario()">
                  <option value="0">Seleccione un usuario...</option><?php foreach ($usuarios as $u) { ?><option value="<?= h($u[0]) ?>" <?= $usuarioSel === intval($u[0]) ? 'selected' : '' ?>><?= h($u[1] . ' — ' . $u[2]) ?></option><?php } ?>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" placeholder="Filtrar módulos..." autocomplete="off" /><i class="fa fa-search"></i></div>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g"><span class="muted" style="font-size:12px"><?= $usuarioSel > 0 ? 'Usa el botón de cada fila para conceder o revocar el acceso.' : 'Primero selecciona un usuario.' ?></span></div>
            <div class="g"><span class="muted" id="contadorAcc"><?= number_format(count($menus)) ?> módulo(s)</span></div>
          </div>

          <div class="rowg thead">
            <span>Grupo</span><span>Módulo</span><span>Acceso</span><span>Acción</span>
          </div>

          <div id="tbody">
            <?php if ($usuarioSel === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">Selecciona un usuario para ver y editar sus accesos.</div>
            <?php } elseif (count($menus) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay módulos configurados en la tabla de menús.</div>
            <?php }
            foreach ($menus as $m) {
              $tiene = !empty($m[4]); ?>
              <div class="row rowg" data-idmenu="<?= h($m[0]) ?>" data-idacceso="<?= h($tiene ? $m[4] : '') ?>" data-nombre="<?= h($m[1]) ?>">
                <div class="dcell"><span class="badge b-gray"><?= h($m[3] !== '' ? $m[3] : 'General') ?></span></div>
                <div class="dcell"><span class="cell-txt" style="font-weight:600;color:#1e293b"><?= h($m[1]) ?></span></div>
                <div><span class="st"><span class="dot <?= $tiene ? 'd-green' : 'd-amber' ?>"></span> <?= $tiene ? 'Permitido' : 'Sin acceso' ?></span></div>
                <div>
                  <?php if ($tiene) { ?>
                    <button class="btn btn-sm" style="color:#dc2626" onclick="revocar(this)"><i class="fa fa-times"></i> Revocar</button>
                  <?php } else { ?>
                    <button class="btn btn-p btn-sm" onclick="conceder(this)"><i class="fa fa-check"></i> Conceder</button>
                  <?php } ?>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

