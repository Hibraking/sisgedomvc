<?php
/* Subsanar documento observado (público). Variables: $token, $doc, $error, $exito, $vigente, $nota */
use App\Services\CalendarioService as Cal;
?>
<style>
    .obs {
      background: #fff7ed;
      border-left: 4px solid #f97316;
      border-radius: 6px;
      padding: 12px 14px;
      color: #7c2d12;
      line-height: 1.55;
    }

    .ok-box {
      text-align: center;
      padding: 10px 0;
    }

    .ok-box .fa {
      font-size: 44px;
      color: #16a34a;
    }

    .datos {
      display: grid;
      grid-template-columns: 160px 1fr;
      gap: 6px 12px;
      font-size: 14px;
      margin: 12px 0;
    }

    .datos dt {
      color: #64748b;
    }

    .datos dd {
      margin: 0;
      color: #1e293b;
    }

    .plazo {
      font-weight: 700;
      color: #c2410c;
    }

    .err {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 12px;
    }

    @media (max-width: 600px) {
      .datos {
        grid-template-columns: 1fr;
      }
    }
  </style>
<div class="pub-main">
    <div class="pub-hero">
      <h1>Subsanar documento observado</h1>
      <p>Adjunte el documento corregido. Mantendrá el mismo número de expediente y la fecha de presentación original.</p>
    </div>

    <?php if ($exito) { ?>
      <div class="pub-card ok-box">
        <i class="fa fa-check-circle"></i>
        <h2 style="margin:10px 0 6px">¡Documento corregido enviado!</h2>
        <p>Expediente N° <b><?= h($exito['exp']) ?></b>. Mesa de partes lo revisará nuevamente y le avisaremos por correo cuando sea recepcionado.</p>
        <p><a class="btn btn-p" href="<?= url('consulta') ?>?num_exp=<?= h(rawurlencode($exito['exp'])) ?>"><i class="fa fa-eye"></i> Ver el estado de mi trámite</a></p>
      </div>
    <?php } elseif (!$doc) { ?>
      <div class="pub-card">
        <div class="aviso"><i class="fa fa-exclamation-circle"></i>
          <?= $error !== '' ? h($error) . '<br>' : '' ?>
          Este enlace no es válido o ya fue usado. Si ya envió su documento corregido, puede ver el estado de su trámite en
          <a href="<?= url('consulta') ?>">Consultar expediente</a>.
        </div>
      </div>
    <?php } else { ?>
      <div class="pub-card">
        <dl class="datos">
          <dt>Expediente N°</dt>
          <dd><b><?= h($doc[1]) ?></b></dd>
          <dt>Documento</dt>
          <dd><?= h(trim($doc[2] . ' ' . $doc[3])) ?></dd>
          <dt>Asunto</dt>
          <dd><?= h($doc[9]) ?></dd>
          <dt>Plazo para subsanar</dt>
          <dd class="plazo">hasta el <?= h(Cal::texto(Cal::cierre($doc[19]))) ?></dd>
        </dl>
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin:14px 0 6px">Observación de mesa de partes</div>
        <div class="obs"><?= nl2br(h($doc[20])) ?></div>
      </div>

      <?php if (!$vigente) { ?>
        <div class="pub-card">
          <div class="aviso"><i class="fa fa-clock-o"></i>
            El plazo para subsanar venció el <b><?= h(Cal::texto(Cal::cierre($doc[19]))) ?></b>.
            El documento se considerará no presentado. Si aún desea realizar el trámite, preséntelo nuevamente por la
            <a href="<?= url('mesa-partes') ?>">Mesa de Partes Virtual</a>.
          </div>
        </div>
      <?php } else { ?>
        <div class="pub-card">
          <?php if ($error !== '') { ?><div class="err"><i class="fa fa-exclamation-triangle"></i> <?= h($error) ?></div><?php } ?>
          <form method="post" action="<?= url('subsanar') ?>" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Enviando...';">
            <input type="hidden" name="t" value="<?= h($token) ?>" />
            <?= \App\Core\Csrf::campo() ?>
            <div style="margin-bottom:12px">
              <label>Documento corregido <span style="color:#dc2626">*</span></label>
              <input type="file" name="archivo" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar" />
              <div style="font-size:12px;color:#64748b;margin-top:4px">PDF de preferencia. Máximo 15 MB. Si son varias hojas o anexos, júntelos en un solo PDF.</div>
            </div>
            <div style="margin-bottom:14px">
              <label>Nota para mesa de partes (opcional)</label>
              <textarea name="nota" rows="3" maxlength="1000" placeholder="Ej.: Adjunto el documento firmado y con los 5 folios completos."><?= h($nota) ?></textarea>
            </div>
            <button class="btn btn-p" type="submit"><i class="fa fa-paper-plane"></i> Enviar documento corregido</button>
          </form>
        </div>
      <?php } ?>
    <?php } ?>
  </div>

