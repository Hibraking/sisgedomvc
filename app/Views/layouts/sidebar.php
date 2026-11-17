<?php
/* Menú lateral: mismas opciones y permisos que sisgedov2, ahora con rutas limpias. */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Controllers\FotoController;
use App\Models\OficinaModel;
use App\Services\MenuService;
use App\Services\OficinaTrabajoService;

$rutaActual = Url::rutaActual();
$esAdmin = Auth::esAdmin();
$vista = $esAdmin ? (new OficinaTrabajoService())->actual() : null;
$foto = FotoController::tieneFoto() ? url('foto-perfil') : '';
?>
<div class="side" data-foto="<?= h($foto) ?>">
  <?php if ($esAdmin) { ?>
    <form class="nav-admin <?= $vista['en_otra'] ? 'nav-admin-otra' : '' ?>" method="post" action="<?= url('oficina-trabajo') ?>">
      <?= Csrf::campo() ?>
      <input type="hidden" name="volver" value="<?= h($rutaActual) ?>" />
      <label><i class="fa fa-eye"></i> <?= $vista['en_otra'] ? 'Trabajando en nombre de:' : 'Oficina de trabajo:' ?></label>
      <select name="ver_oficina" onchange="this.form.submit()" title="Como administrador puede ver y trabajar en la bandeja de cualquier oficina">
        <option value="propia" <?= !$vista['en_otra'] ? 'selected' : '' ?>>Mi oficina (<?= h($vista['propia_nombre'] !== '' ? $vista['propia_nombre'] : 'sin oficina') ?>)</option>
        <option value="todas">▸ Todas las oficinas (supervisión)</option>
        <optgroup label="Trabajar en nombre de...">
          <?php foreach (OficinaModel::lista() as $o) { ?>
            <option value="<?= h($o[0]) ?>" <?= $vista['en_otra'] && (int)$o[0] === $vista['idoficina'] ? 'selected' : '' ?>><?= h($o[1]) ?></option>
          <?php } ?>
        </optgroup>
      </select>
      <?php if ($vista['en_otra']) { ?>
        <div class="nav-admin-nota">Lo que haga quedará registrado como <b>Administrador</b> en nombre de esta oficina.</div>
      <?php } ?>
    </form>
  <?php } ?>
  <nav>
    <a href="<?= url('') ?>" <?= $rutaActual === '' ? 'class="active"' : '' ?>><i class="fa fa-home"></i> Inicio</a>
    <?php if ($esAdmin) { ?>
      <a href="<?= url('supervision') ?>" <?= $rutaActual === 'supervision' ? 'class="active"' : '' ?>><i class="fa fa-th-list"></i> Supervisión</a>
    <?php } ?>
    <?php foreach (MenuService::grupos() as $g) { ?>
      <div class="grp<?= $g['activo'] ? ' open' : '' ?>" onclick="navToggleGrupo(this)"><i class="fa <?= h($g['icono']) ?>"></i> <span class="sp"><?= h($g['grupo']) ?></span><i class="fa fa-angle-down caret"></i></div>
      <div class="subm<?= $g['activo'] ? '' : ' cerrado' ?>">
        <?php foreach ($g['items'] as $it) { ?>
          <a href="<?= h($it['url']) ?>" <?= $it['activo'] ? 'class="active"' : '' ?>><i class="fa <?= h($it['icono']) ?>"></i> <?= h($it['titulo']) ?><?php if ($it['badge'] > 0) { ?> <span class="nav-new" title="Documentos de Mesa de Partes Virtual por recepcionar o derivar"><?= $it['badge'] > 99 ? '99+' : (int)$it['badge'] ?></span><?php } ?></a>
        <?php } ?>
      </div>
    <?php } ?>
    <a href="<?= url('perfil') ?>" <?= $rutaActual === 'perfil' ? 'class="active"' : '' ?>><i class="fa fa-user"></i> Mi perfil</a>
  </nav>
  <div class="soporte">
    <span class="ic"><i class="fa fa-headphones"></i></span>
    <div style="flex:1">
      <div class="tt">Soporte</div>
      <div class="ss">¿Necesitas ayuda?</div>
    </div>
    <i class="fa fa-angle-right" style="color:#cbd5e1"></i>
  </div>
</div>
<script src="<?= asset('assets/js/nav_nuevo.js') ?>"></script>
<script src="<?= asset('assets/js/notificaciones.js') ?>" data-base="<?= h(url('')) ?>"></script>
