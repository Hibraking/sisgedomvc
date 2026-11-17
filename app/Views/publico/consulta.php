<?php
/* Consulta tu expediente (público). Variables: ver PublicoController::consulta */
use App\Services\CalendarioService as Cal;
?>
<div class="pub-main">
    <div class="pub-hero">
      <h1>Consulta tu Expediente</h1>
      <p>Ingresa el número de expediente que recibiste al presentar tu documento y conoce en qué oficina se encuentra tu
        trámite.</p>
    </div>

    <!-- BUSCADOR -->
    <div class="pub-card">
      <form method="get" action="<?= url('consulta') ?>"
        style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:220px">
          <label>Número de expediente</label>
          <input type="text" name="num_exp" value="<?= h($numExp) ?>" placeholder="Ej. 20263304" inputmode="numeric"
            autocomplete="off" autofocus />
        </div>
        <button class="btn btn-p" type="submit"><i class="fa fa-search"></i> Consultar</button>
      </form>
    </div>

    <?php if ($busco && $cab === null) { ?>
      <!-- NO ENCONTRADO -->
      <div class="pub-card">
        <div class="aviso">
          <i class="fa fa-exclamation-circle"></i>
          No encontramos ningún expediente con el número <b><?= h($numExp) ?></b>.<br />
          Verifica el número en tu constancia. Si el problema continúa, acércate a mesa de partes o llama a la
          municipalidad.
          <div class="aviso-anterior">
            <i class="fa fa-history"></i>
            Si presentaste tu documento <b>hasta el <?= h(SISGEDO_ANTERIOR_HASTA) ?></b>, búscalo en el sistema anterior:
            <a class="btn btn-p" href="<?= h(SISGEDO_ANTERIOR_URL) ?>" target="_blank" rel="noopener">
              <i class="fa fa-external-link"></i> Ir al SISGEDO anterior</a>
          </div>
        </div>
      </div>
    <?php } ?>

    <?php if ($cab !== null) { ?>
      <!-- CABECERA DEL EXPEDIENTE -->
      <div class="pub-card">
        <div class="exp-head">
          <span class="di"><i class="fa fa-folder-open-o"></i></span>
          <div style="flex:1;min-width:0">
            <div class="dt">Expediente N° <?= h($numExp) ?></div>
            <div class="ds"><?= h($cab[0]) ?><?= $cab[1] !== '' ? ' N° ' . h($cab[1]) : '' ?> — presentado el
              <?= h($cab[5]) ?></div>
          </div>
          <span class="badge <?= count($eventos) > 0 ? 'azul' : 'ambar' ?>"><?= h(strtoupper($estadoActual)) ?></span>
        </div>
        <dl class="exp-datos">
          <div>
            <dt>Remitente</dt>
            <dd><?= h($cab[2]) ?></dd>
          </div>
          <div>
            <dt>Tipo de trámite</dt>
            <dd><?= h($cab[3]) ?></dd>
          </div>
          <div>
            <dt>Se encuentra en</dt>
            <dd><b><?= h($oficinaActual) ?></b></dd>
          </div>
          <div>
            <dt>Movimientos</dt>
            <dd><?= number_format(count($eventos) + 1) ?></dd>
          </div>
          <div style="grid-column:1/-1">
            <dt>Asunto</dt>
            <dd><?= h($cab[4]) ?></dd>
          </div>
        </dl>
      </div>

      <!-- RECORRIDO -->
      <div class="pub-card">
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin-bottom:4px"><i class="fa fa-map-o"
            style="margin-right:8px"></i>Recorrido de tu trámite</div>
        <div class="linea">
          <div class="evt">
            <div class="ehead">
              <span class="eofi"><?= intval($cab[7]) === 1 ? 'Mesa de Partes Virtual' : 'Mesa de Partes' ?></span>
              <span class="efecha"><i class="fa fa-calendar"></i> <?= h($cab[5]) ?></span>
            </div>
            <div class="edet"><span><i class="fa fa-flag-o"></i>Documento
                <?= intval($cab[7]) === 1 ? 'presentado por internet' : 'registrado en ventanilla' ?></span>
              <?php if ($mpvOficial) { ?><span><i class="fa fa-moon-o"></i>Enviado fuera del horario de atención: se considera presentado el <?= h(Cal::texto($mpvOficial)) ?></span><?php } ?>
            </div>
          </div>
          <?php
          // Historial de la Mesa de Partes Virtual (observaciones, subsanación, recepción...)
          $mpvTitulos = array(
            'RECEPCIONADO' => array('Recepcionado por mesa de partes', 'fa-check'),
            'OBSERVADO' => array('Observado por mesa de partes', 'fa-exclamation-circle'),
            'SUBSANADO' => array('Documento corregido enviado', 'fa-refresh'),
            'NO_PRESENTADO' => array('Documento no presentado', 'fa-ban'),
            'DERIVADO' => array('Derivado', 'fa-share'),
          );
          $mpvUltimo = count($mpvEventos) - 1;
          foreach ($mpvEventos as $j => $me) {
            $tt = isset($mpvTitulos[$me[0]]) ? $mpvTitulos[$me[0]] : array($me[0], 'fa-circle');
            $esActualMpv = count($eventos) === 0 && $j === $mpvUltimo && in_array($mpvEstado, array('observado', 'no_presentado'), true); ?>
            <div class="evt <?= $esActualMpv ? 'actual' : '' ?>">
              <div class="ehead">
                <span class="eofi"><?= $me[0] === 'SUBSANADO' ? 'Ciudadano' : 'Mesa de Partes' ?></span>
                <span class="efecha"><i class="fa fa-calendar"></i> <?= h(date('d/m/Y · h:i A', strtotime($me[3]))) ?></span>
              </div>
              <div class="edet">
                <span><i class="fa <?= $tt[1] ?>"></i><?= h($tt[0]) ?><?= $me[0] === 'DERIVADO' && $me[1] !== '' ? ' a ' . h($me[1]) : '' ?></span>
                <?php if ($me[0] === 'OBSERVADO' && $me[1] !== '') { ?><span>Motivo: <?= h($me[1]) ?></span><?php } ?>
              </div>
            </div>
          <?php } ?>
          <?php foreach ($eventos as $i => $ev) {
            $esUltimo = ($i === count($eventos) - 1); ?>
            <div class="evt <?= $esUltimo ? 'actual' : '' ?>">
              <div class="ehead">
                <span class="eofi"><?= $ev[2] !== '' ? h($ev[2]) : 'Oficina de la entidad' ?></span>
                <span class="efecha"><i class="fa fa-calendar"></i> <?= h($ev[0]) ?> · <?= h($ev[1]) ?></span>
              </div>
              <div class="edet">
                <span><i class="fa fa-paper-plane"></i>Acción: <?= h($ev[3]) ?></span>
                <span><i class="fa fa-flag-o"></i>Estado: <?= h($ev[4]) ?></span>
                <?php if ($esUltimo) { ?><span class="badge verde">UBICACIÓN ACTUAL</span><?php } ?>
              </div>
            </div>
          <?php } ?>
          <?php if (count($eventos) === 0 && $mpvEstado === 'observado' && $mpvObs) { ?>
            <div class="evt actual">
              <div class="ehead"><span class="eofi">Debes subsanar tu documento</span></div>
              <div class="edet"><span><i class="fa fa-envelope-o"></i>Te enviamos un correo con la observación y un enlace para enviar el documento
                  corregido. Plazo: hasta el <b><?= h(Cal::texto(Cal::cierre($mpvObs[1]))) ?></b>. Si no lo subsanas a tiempo,
                  se considerará no presentado.</span></div>
            </div>
          <?php } elseif (count($eventos) === 0 && $mpvEstado === 'no_presentado') { ?>
            <div class="evt actual">
              <div class="ehead"><span class="eofi">Trámite concluido: no presentado</span></div>
              <div class="edet"><span><i class="fa fa-info-circle"></i>La observación no se subsanó dentro del plazo. Si aún deseas realizar el trámite,
                  preséntalo nuevamente en la <a href="<?= url('mesa-partes') ?>">Mesa de Partes Virtual</a>.</span></div>
            </div>
          <?php } elseif (count($eventos) === 0) { ?>
            <div class="evt actual">
              <?php if ($mpvEstado === 'pendiente') { ?>
                <div class="ehead"><span class="eofi">En revisión de mesa de partes</span></div>
                <div class="edet"><span><i class="fa fa-clock-o"></i>Tu documento llegó correctamente. Mesa de partes lo revisará,
                    lo recepcionará y lo derivará al área competente. Te avisaremos por correo.</span></div>
              <?php } elseif ($mpvEstado === 'recepcionado') { ?>
                <div class="ehead"><span class="eofi">Por derivar al área competente</span></div>
                <div class="edet"><span><i class="fa fa-clock-o"></i>Mesa de partes recepcionó tu documento y lo derivará
                    al área que debe atenderlo.</span></div>
              <?php } else { ?>
                <div class="ehead"><span class="eofi">En espera de recepción</span></div>
                <div class="edet"><span><i class="fa fa-clock-o"></i>Tu documento fue registrado y está pendiente de ser
                    recepcionado por la oficina destino.</span></div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </div>
    <?php } ?>

    <?php if (!$busco) { ?>
      <!-- AYUDA -->
      <div class="pub-card">
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin-bottom:12px"><i class="fa fa-question-circle"
            style="margin-right:8px"></i>¿Cómo funciona?</div>
        <div class="fgrid">
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">1.</span><span
              style="font-size:13px;color:#475569">Presenta tu documento en la <a href="<?= url('mesa-partes') ?>"
                style="color:#2563eb">Mesa de Partes Virtual</a> o de forma presencial.</span></div>
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">2.</span><span
              style="font-size:13px;color:#475569">Recibirás un <b>número de expediente</b> como constancia (también llega
              a tu correo).</span></div>
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">3.</span><span
              style="font-size:13px;color:#475569">Ingresa ese número aquí y verás cada oficina por la que pasa tu
              trámite.</span></div>
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">4.</span><span
              style="font-size:13px;color:#475569">La etiqueta <b>Ubicación actual</b> te indica dónde está tu documento
              hoy.</span></div>
        </div>
      </div>
    <?php } ?>
  </div>


  <!-- AVISO: CAMBIO DE SISTEMA (cada vez que se entra o se recarga la página) -->
  <div class="modal-anterior" id="avisoAnterior" role="dialog" aria-modal="true" aria-labelledby="avisoAnteriorTit" hidden>
    <div class="caja">
      <div class="ico"><i class="fa fa-bullhorn"></i></div>
      <h2 id="avisoAnteriorTit">Nuevo sistema de trámite documentario</h2>
      <p>Desde el <b><?= h(SISGEDO_VIGENTE_DESDE) ?></b> la municipalidad usa este nuevo sistema.</p>
      <p>Si presentaste tu documento <b>hasta el <?= h(SISGEDO_ANTERIOR_HASTA) ?></b>, consúltalo en el
        <b>SISGEDO anterior</b> con el mismo número de expediente.
      </p>
      <div class="acc">
        <a class="btn btn-p" href="<?= h(SISGEDO_ANTERIOR_URL) ?>" target="_blank" rel="noopener">
          <i class="fa fa-external-link"></i> Ir al SISGEDO anterior</a>
        <button type="button" class="btn" id="avisoAnteriorCerrar">Entendido, continuar aquí</button>
      </div>
    </div>
  </div>
  <script>
    (function() {
      // Se muestra SIEMPRE al entrar a la página y al recargarla (F5).
      // Única excepción: el resultado de una búsqueda hecha desde esta misma
      // página (botón Consultar), para no tapar el resultado en cada consulta.
      var m = document.getElementById('avisoAnterior');
      var tipo = '';
      try {
        var nav = performance.getEntriesByType('navigation');
        tipo = nav.length ? nav[0].type : (performance.navigation && performance.navigation.type === 1 ? 'reload' : 'navigate');
      } catch (e) {}
      var desdeEstaPagina = false;
      try {
        var ref = document.referrer ? new URL(document.referrer) : null;
        desdeEstaPagina = !!ref && ref.origin === location.origin && ref.pathname === location.pathname;
      } catch (e) {}
      var esBusqueda = tipo === 'navigate' && desdeEstaPagina && location.search.indexOf('num_exp=') !== -1;
      if (esBusqueda) return;
      m.hidden = false;

      function cerrar() {
        m.hidden = true;
      }
      document.getElementById('avisoAnteriorCerrar').onclick = cerrar;
      m.addEventListener('click', function(e) {
        if (e.target === m) cerrar();
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !m.hidden) cerrar();
      });
    })();
  </script>
