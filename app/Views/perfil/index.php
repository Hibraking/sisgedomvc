<?php
/* Mi perfil — mismo diseño que sisgedov2 (vistas/v_profile.php). */
?>
      <div class="main">
        <div class="pf-crumbs"><i class="fa fa-home" style="color:#94a3b8"></i> <a href="<?= h(url('')) ?>">Inicio</a> <i class="fa fa-angle-right" style="color:#cbd5e1"></i> <span>Configuración</span> <i class="fa fa-angle-right" style="color:#cbd5e1"></i> <b>Perfil de Usuario</b></div>

        <div class="pf-top">
          <div>
            <h1 class="t">Perfil de Usuario</h1>
            <div class="subt">Gestiona la información de tu cuenta y preferencias</div>
          </div>
          <button class="btn" style="color:#2563eb;border-color:#bfdbfe" onclick="abrirEditarPerfil()"><i class="fa fa-pencil"></i> Editar perfil</button>
        </div>

        <!-- HERO -->
        <div class="pf-hero">
          <div class="pf-avatar"><?php if ($fotoUrl !== '') { ?><img src="<?= h($fotoUrl) ?>" alt="Foto de perfil" /><?php } else { ?><i class="fa fa-user"></i><?php } ?></div>
          <div>
            <div class="pf-nombre"><?= h($nombreCompleto) ?></div>
            <span class="pf-rol"><i class="fa fa-check-circle"></i> <?= h($rolPrincipal) ?></span>
            <div class="pf-meta">
              <span><i class="fa fa-calendar"></i> Miembro desde <?= h($miembroDesde) ?></span>
              <span><i class="fa fa-clock-o"></i> Último acceso: <?= h($ultimoAcceso) ?></span>
            </div>
          </div>
          <div class="pf-stats">
            <div class="pf-stat"><span class="ic t-blue"><i class="fa fa-users"></i></span>
              <div>
                <div class="n"><?= number_format($nRoles) ?></div>
                <div class="l">Roles</div>
              </div>
            </div>
            <div class="pf-stat"><span class="ic t-green"><i class="fa fa-building-o"></i></span>
              <div>
                <div class="n"><?= number_format($nOficinas) ?></div>
                <div class="l">Oficinas</div>
              </div>
            </div>
            <div class="pf-stat"><span class="ic t-purple"><i class="fa fa-file-text-o"></i></span>
              <div>
                <div class="n"><?= number_format($nDocs) ?></div>
                <div class="l">Documentos</div>
              </div>
            </div>
            <div class="pf-stat"><span class="ic t-orange"><i class="fa fa-shield"></i></span>
              <div>
                <div class="n"><?= number_format($pctPermisos) ?>%</div>
                <div class="l">Permisos</div>
              </div>
            </div>
          </div>
        </div>

        <div class="pf-cards">
          <!-- INFORMACIÓN PERSONAL -->
          <div class="pf-card">
            <h2><i class="fa fa-user"></i> Información Personal</h2>
            <div class="pf-grid">
              <div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-user"></i> Usuario</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[5] !== '' ? h(strtoupper($perfil[5])) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-users"></i> Apellidos</div>
                  <div class="v"><span class="txt"><?= $perfil ? h(strtoupper($perfil[0])) : '—' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-user"></i> Nombres</div>
                  <div class="v"><span class="txt"><?= $perfil ? h(strtoupper($perfil[1])) : '—' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-map-marker"></i> Dirección</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[2] !== '' ? h($perfil[2]) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-envelope-o"></i> Email</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[3] !== '' ? h(strtoupper($perfil[3])) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
              </div>
              <div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-phone"></i> Teléfono</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[4] !== '' ? h($perfil[4]) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-building-o"></i> Oficina</div>
                  <div class="v"><span class="txt"><?php
                                                    $ofiPrincipal = '';
                                                    foreach ($oficinasResp as $o) {
                                                      if (intval($o[0]) === $idoficinaSes) {
                                                        $ofiPrincipal = $o[1];
                                                        break;
                                                      }
                                                    }
                                                    if ($ofiPrincipal === '' && $nOficinas > 0) {
                                                      $ofiPrincipal = $oficinasResp[0][1];
                                                    }
                                                    echo $ofiPrincipal !== '' ? h($ofiPrincipal) : '<span class="pf-vacio">No asignada</span>';
                                                    ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-users"></i> Rol Principal</div>
                  <div class="v"><span class="txt"><?= h($rolPrincipal) ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-check-circle"></i> Estado</div>
                  <div class="v"><span class="badge" style="background:#ecfdf3;color:#16a34a">Activo</span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-lock"></i> Contraseña</div>
                  <div class="v"><span class="txt">••••••••••••••</span><i class="fa fa-eye-slash" style="color:#94a3b8;cursor:pointer" title="La contraseña se guarda cifrada; puedes cambiarla en Editar perfil" onclick="alert('Tu contraseña se guarda cifrada y no puede mostrarse. Usa \'Editar perfil\' para cambiarla.')"></i></div>
                </div>
              </div>
            </div>
          </div>

          <!-- OFICINAS ASIGNADAS -->
          <div class="pf-card" style="flex:1;min-height:0;overflow-y:auto">
            <h2><i class="fa fa-building-o"></i> Oficinas asignadas</h2>
            <?php if ($nOficinas === 0) { ?>
              <div class="pf-vacio" style="font-size:12.5px">No estás asignado como responsable de ninguna oficina.</div>
            <?php }
            foreach ($oficinasResp as $o) {
              $ppal = intval($o[0]) === $idoficinaSes; ?>
              <div class="pf-ofi">
                <span class="oi"><i class="fa fa-building-o"></i></span>
                <div style="flex:1;min-width:0">
                  <div class="on"><?= h($o[1]) ?></div>
                  <div class="od"><?= $o[2] !== '' ? h($o[2]) : 'Oficina de la entidad' ?></div>
                </div>
                <?php if ($ppal) { ?><span class="pf-badge-ppal">Principal</span><?php } elseif (intval($o[3]) !== 1) { ?><span class="badge b-gray">Inactivo</span><?php } ?>
              </div>
            <?php } ?>
          </div>
        </div>

        <div class="pf-footer"><b>SISGEDO</b> © <?= date('Y') ?> Todos los derechos reservados. &nbsp;|&nbsp; Versión 2.0.0</div>
      </div>
    </div>
  </div>

  <!-- MODAL EDITAR PERFIL -->
  <div class="modal" id="modalPerfil">
    <div class="box">
      <div class="mh">
        <h3>Editar perfil</h3><button class="ib" style="color:#94a3b8" onclick="cerrarEditarPerfil()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Foto de perfil <span class="muted" style="font-size:11px">(JPG, PNG, GIF o WEBP — máx. 3 MB)</span></label>
          <div style="display:flex;align-items:center;gap:12px">
            <span id="ep_prev" class="pf-avatar" style="width:54px;height:54px;font-size:22px;border-width:2px;flex-shrink:0"><?php if ($fotoUrl !== '') { ?><img src="<?= h($fotoUrl) ?>" alt="" /><?php } else { ?><i class="fa fa-user"></i><?php } ?></span>
            <input id="ep_foto" type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="flex:1;font-size:12px" />
          </div>
        </div>
        <div><label>Dirección</label><input id="ep_direccion" type="text" value="<?= $perfil ? h($perfil[2]) : '' ?>" /></div>
        <div><label>Email</label><input id="ep_email" type="email" value="<?= $perfil ? h($perfil[3]) : '' ?>" /></div>
        <div><label>Teléfono</label><input id="ep_telefono" type="text" value="<?= $perfil ? h($perfil[4]) : '' ?>" /></div>
        <div style="border-top:1px solid #f1f5f9;padding-top:12px"><label>Nueva contraseña <span class="muted" style="font-size:11px">(vacía = no cambiar)</span></label><input id="ep_clave_nueva" type="password" autocomplete="new-password" /></div>
        <div><label>Contraseña actual <span class="muted" style="font-size:11px">(solo si cambias la contraseña)</span></label><input id="ep_clave_actual" type="password" autocomplete="current-password" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarEditarPerfil()">Cancelar</button><button class="btn btn-p" id="ep_guardar"><i class="fa fa-check"></i> Guardar cambios</button></div>

