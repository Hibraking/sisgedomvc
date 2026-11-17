<?php
/* Supervisión de oficinas (solo administrador) — mismo diseño que sisgedov2. Variables: ver SupervisionController::index */
?>
      <div class="main" style="max-width:none">
        <h1 class="t">Supervisión de oficinas</h1>
        <div class="subt">Situación de todas las áreas. Solo visible para el administrador.</div>

        <div class="sup-ayuda"><i class="fa fa-info-circle"></i>
          Pulse <b>Entrar</b> para trabajar en la bandeja de una oficina (Recepción, Archivo, Emisión, Derivados).
          Lo que haga allí quedará registrado como <b>Administrador en nombre de esa oficina</b>.
          Para volver a la suya, elija <b>Mi oficina</b> en el selector del menú lateral.
        </div>

        <div class="kpis">
          <?php
          $cards = array(
            array('Por recepcionar', $tot['pend'], 'En ' . $tot['con_pend'] . ' oficinas', 'fa-inbox', 't-orange', '#f97316', 'lab-orange'),
            array('Más de 30 días', $tot['viejos'], 'Pendientes antiguos', 'fa-exclamation-triangle', 't-orange', '#dc2626', 'lab-orange'),
            array('Recibidos hoy', $tot['hoy'], 'En todas las oficinas', 'fa-check-circle', 't-green', '#16a34a', 'lab-green'),
            array('Mesa de Partes Virtual', $mpv['pend'], $mpv['vencidos'] > 0 ? $mpv['vencidos'] . ' observados vencidos' : 'Por atender', 'fa-globe', 't-purple', '#6d28d9', 'lab-purple'),
          );
          foreach ($cards as $c) { ?>
            <div class="kpi">
              <div class="ic <?= $c[4] ?>"><i class="fa <?= $c[3] ?>"></i></div>
              <div>
                <div class="lab <?= $c[6] ?>"><?= h($c[0]) ?></div>
                <div class="num" style="color:<?= $c[5] ?>"><?= number_format($c[1]) ?></div>
                <div class="ss"><?= h($c[2]) ?></div>
              </div>
            </div>
          <?php } ?>
        </div>

        <?php if ($sinResp || $variosCargos) { ?>
          <details class="sup-revisar">
            <summary><i class="fa fa-user-times"></i> Revisar responsables:
              <?php if ($sinResp) { ?><b><?= count($sinResp) ?></b> oficina(s) sin responsable activo<?php } ?>
              <?php if ($sinResp && $variosCargos) { ?> · <?php } ?>
              <?php if ($variosCargos) { ?><b><?= count($variosCargos) ?></b> persona(s) con más de un cargo activo<?php } ?>
              <span class="sup-det">(clic para ver)</span></summary>
            <div class="sup-rev-cuerpo">
              <?php if ($sinResp) { ?>
                <p><b>Oficinas sin responsable activo.</b> Lo que se les derive no lo recibe nadie y su aviso de documento nuevo no le llega a nadie.
                  Asigne a alguien en <a href="<?= h(url('mantenimiento/responsables')) ?>">Mantenimiento &gt; Responsables</a> (o trabaje en su nombre desde el selector del menú).</p>
                <ul>
                  <?php foreach ($sinResp as $s) { $pp = isset($pendPorOfi[(int)$s[0]]) ? $pendPorOfi[(int)$s[0]] : 0; ?>
                    <li><?= h($s[1]) ?><?php if ($pp > 0) { ?> — <span class="sup-pend"><?= number_format($pp) ?> por recepcionar</span><?php } ?></li>
                  <?php } ?>
                </ul>
              <?php } ?>
              <?php if ($variosCargos) { ?>
                <p><b>Personas con más de un cargo activo.</b> Al iniciar sesión trabajan solo como uno de ellos. Si de verdad atienden ambas oficinas está bien; si no, desactive el cargo que ya no corresponde.</p>
                <ul>
                  <?php foreach ($variosCargos as $v) { ?><li><?= h($v[0]) ?> — <?= h($v[1]) ?></li><?php } ?>
                </ul>
              <?php } ?>
            </div>
          </details>
        <?php } ?>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscar" placeholder="Buscar oficina..." oninput="filtrarSup(this.value)" /><i class="fa fa-search"></i></div>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="sup-scroll">
            <table class="sup-tabla" id="tablaSup">
              <thead>
                <tr>
                  <th>Oficina</th>
                  <th class="num">Por recepcionar</th>
                  <th class="num">Más de 30 días</th>
                  <th>Pendiente más antiguo</th>
                  <th class="num">Recibidos hoy</th>
                  <th class="num">Derivados este mes</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ofis as $o) { ?>
                  <tr data-n="<?= h(mb_strtolower($o['nombre'], 'UTF-8')) ?>">
                    <td><b><?= h($o['nombre']) ?></b></td>
                    <td class="num">
                      <span class="<?= $o['pend'] > 0 ? 'sup-pend' : 'sup-cero' ?>"><?= number_format($o['pend']) ?></span>
                      <?php if ($o['pend'] > 0) { ?><div class="sup-det" title="Externos / internos (emitidos) / derivados de otras oficinas"><?= $o['ext'] ?> ext · <?= $o['int'] ?> int · <?= $o['der'] ?> der</div><?php } ?>
                    </td>
                    <td class="num <?= $o['viejos'] > 0 ? 'sup-viejo' : 'sup-cero' ?>"><?= number_format($o['viejos']) ?></td>
                    <td><?= $o['antiguo'] ? h(date('d/m/Y', strtotime($o['antiguo']))) . ' <span class="sup-det">' . h($supHace($o['antiguo'])) . '</span>' : '<span class="sup-cero">—</span>' ?></td>
                    <td class="num <?= $o['hoy'] ? '' : 'sup-cero' ?>"><?= number_format($o['hoy']) ?></td>
                    <td class="num <?= $o['mes'] ? '' : 'sup-cero' ?>"><?= number_format($o['mes']) ?></td>
                    <td style="text-align:right">
                      <form method="post" action="<?= h(url('oficina-trabajo')) ?>" style="margin:0">
                        <input type="hidden" name="_csrf" value="<?= h($tok) ?>" />
                        <input type="hidden" name="ver_oficina" value="<?= (int)$o['id'] ?>" />
                        <input type="hidden" name="volver" value="recepcion" />
                        <button class="btn btn-sm btn-p" type="submit" title="Trabajar en la bandeja de esta oficina"><i class="fa fa-sign-in"></i> Entrar</button>
                      </form>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
              <tfoot>
                <tr>
                  <td>Total (<?= count($ofis) ?> oficinas)</td>
                  <td class="num"><?= number_format($tot['pend']) ?></td>
                  <td class="num"><?= number_format($tot['viejos']) ?></td>
                  <td></td>
                  <td class="num"><?= number_format($tot['hoy']) ?></td>
                  <td class="num"><?= number_format($tot['mes']) ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
  <script>
    function filtrarSup(t) {
      t = (t || '').trim().toLowerCase();
      document.querySelectorAll('#tablaSup tbody tr').forEach(function(tr) {
        tr.style.display = !t || tr.dataset.n.indexOf(t) !== -1 ? '' : 'none';
      });
    }
  </script>
