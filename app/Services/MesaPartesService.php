<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Url;
use App\Models\MesaPartesModel as MPV;
use App\Services\CalendarioService as Cal;

/**
 * Mesa de Partes Virtual — reglas y correos (mismos de sisgedov2/clases/mesa_partes_virtual.php
 * y clases/mpvirtual.php).
 *   Operador: recepcionar · observar (plazo Ley 27444 art. 136) · no presentado · derivar
 *   Ciudadano: presentar (formulario público) · subsanar (enlace del correo)
 * Los correos lentos se aplazan: el controlador responde y luego llama a ejecutarPendientes().
 * Cada acción devuelve ['ok' => bool, 'msg' => ..., ...].
 */
class MesaPartesService
{
    private $pendientes = array();

    private function despues(callable $tarea)
    {
        $this->pendientes[] = $tarea;
    }

    public function hayPendientes()
    {
        return count($this->pendientes) > 0;
    }

    public function ejecutarPendientes()
    {
        foreach ($this->pendientes as $t) {
            try {
                $t();
            } catch (\Exception $e) {
                error_log('MPV tarea aplazada: ' . $e->getMessage());
            }
        }
        $this->pendientes = array();
    }

    private function faltaMigracion()
    {
        return array('ok' => false, 'msg' => 'Faltan las migraciones 007 y 008 (módulo Mesa de Partes Virtual).');
    }

    /** Explica por qué no se pudo recepcionar/observar. */
    private function motivoNoDisponible($id)
    {
        if ((int)$id <= 0) return 'No se identificó el documento. Actualice la página (F5) e intente de nuevo.';
        try {
            $x = MPV::estado($id);
        } catch (\Exception $e) {
            $x = null;
        }
        if (!$x) return 'El documento ya no existe. Actualice la página.';
        if ((int)$x[0] !== 1) return 'Este documento no llegó por la Mesa de Partes Virtual.';
        if ($x[1] !== null) {
            return 'Este documento ya fue recepcionado el ' . date('d/m/Y h:i a', strtotime($x[1])) . ($x[3] !== '' ? ' por ' . $x[3] : '') . '. Actualice la página.';
        }
        if ($x[2] === 'OBSERVADO') return 'Este documento está observado: espera que el ciudadano lo subsane. Actualice la página.';
        if ($x[2] === 'NO_PRESENTADO') return 'Este documento se marcó como no presentado. Actualice la página.';
        return 'No se pudo completar la acción. Actualice la página e intente de nuevo.';
    }

    /* =========================== Operador =========================== */

    public function recepcionar($id, $idpersonal)
    {
        if (!MPV::moduloActivo()) return $this->faltaMigracion();
        $st = Database::consulta("UPDATE registro_documentos
                                     SET mpv_recepcion = NOW(), mpv_idpersonal = ?, mpv_estado = NULL, mpv_token = NULL
                                   WHERE idregistro_documentos = ? AND recepcion_web = 1 AND mpv_recepcion IS NULL
                                     AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO')", array((int)$idpersonal, (int)$id));
        if ($st->rowCount() === 0) return array('ok' => false, 'msg' => $this->motivoNoDisponible($id));
        MPV::evento($id, 'RECEPCIONADO', null, null, null, (int)$idpersonal);
        $this->despues(function () use ($id) { $this->correoRecepcionado((int)$id); });
        return array('ok' => true, 'msg' => 'Recepcionado.', 'correo' => 'CORREO_PENDIENTE');
    }

    public function observar($id, $motivo, $idpersonal)
    {
        if (!MPV::moduloActivo()) return $this->faltaMigracion();
        $motivo = trim(preg_replace('/\s+/u', ' ', (string)$motivo));
        if (mb_strlen($motivo, 'UTF-8') < 10) {
            return array('ok' => false, 'msg' => 'Escriba el motivo de la observación (al menos 10 caracteres): el ciudadano lo leerá para corregir su documento.');
        }
        $motivo = mb_substr($motivo, 0, 1000, 'UTF-8');
        $plazo = Cal::sumarHabiles(date('Y-m-d'), (int)SISGEDO_PLAZO_SUBSANAR);
        $token = bin2hex(random_bytes(20));
        $st = Database::consulta("UPDATE registro_documentos SET mpv_estado = 'OBSERVADO', mpv_plazo = ?, mpv_token = ?
                                   WHERE idregistro_documentos = ? AND recepcion_web = 1 AND mpv_recepcion IS NULL
                                     AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO')", array($plazo, $token, (int)$id));
        if ($st->rowCount() === 0) return array('ok' => false, 'msg' => $this->motivoNoDisponible($id));
        $adj = Database::valor("SELECT adjunto FROM registro_documentos WHERE idregistro_documentos = ?", array((int)$id));
        MPV::evento($id, 'OBSERVADO', $motivo, $adj ?: null, $plazo, (int)$idpersonal);
        // Igual que sisgedov2: este correo se envía en el momento (lleva el enlace para subsanar)
        return array('ok' => true, 'msg' => 'Observado.', 'plazo' => $plazo, 'correo' => $this->correoObservado((int)$id, $motivo, $plazo, $token));
    }

    public function noPresentado($id, $idpersonal)
    {
        if (!MPV::moduloActivo()) return $this->faltaMigracion();
        $x = Database::fila("SELECT mpv_estado, mpv_plazo FROM registro_documentos WHERE idregistro_documentos = ? AND recepcion_web = 1", array((int)$id));
        if (!$x || $x['mpv_estado'] !== 'OBSERVADO') {
            return array('ok' => false, 'msg' => 'Solo se puede marcar como no presentado un documento observado. Actualice la página.');
        }
        if (time() <= strtotime(Cal::cierre($x['mpv_plazo']))) {
            return array('ok' => false, 'msg' => 'El plazo para subsanar vence el ' . Cal::texto(Cal::cierre($x['mpv_plazo'])) . '. Podrá marcarlo como no presentado después de esa fecha.');
        }
        $st = Database::consulta("UPDATE registro_documentos SET mpv_estado = 'NO_PRESENTADO', mpv_token = NULL
                                   WHERE idregistro_documentos = ? AND mpv_estado = 'OBSERVADO'", array((int)$id));
        if ($st->rowCount() === 0) return array('ok' => false, 'msg' => 'El documento cambió de estado. Actualice la página.');
        MPV::evento($id, 'NO_PRESENTADO', 'No se subsanó dentro del plazo (Ley 27444, art. 136).', null, null, (int)$idpersonal);
        $this->despues(function () use ($id) { $this->correoNoPresentado((int)$id); });
        return array('ok' => true, 'msg' => 'No presentado.', 'correo' => 'CORREO_PENDIENTE');
    }

    public function derivar($id, $idoficina, $idpersonal)
    {
        if (!MPV::moduloActivo()) return $this->faltaMigracion();
        $nomOfi = Database::valor("SELECT nombre FROM oficinas WHERE idoficinas = ?", array((int)$idoficina));
        if ($nomOfi === false) return array('ok' => false, 'msg' => 'Seleccione una oficina válida.');
        $st = Database::consulta("UPDATE registro_documentos SET idoficina = ?, idpersonal = ?
                                   WHERE idregistro_documentos = ? AND recepcion_web = 1
                                     AND mpv_recepcion IS NOT NULL AND idoficina IS NULL AND COALESCE(estado_recepcion,0) = 0",
            array((int)$idoficina, (int)$idpersonal, (int)$id));
        if ($st->rowCount() === 0) {
            return array('ok' => false, 'msg' => 'No se pudo derivar: el documento debe estar recepcionado y sin derivar. Actualice la página.');
        }
        MPV::evento($id, 'DERIVADO', $nomOfi, null, null, (int)$idpersonal);
        return array('ok' => true, 'msg' => 'Derivado.');
    }

    /* =========================== Ciudadano =========================== */

    /**
     * Presentación por el formulario público. $d: datos del POST; $archivo: $_FILES['fichero'] o null.
     * Devuelve ['ok', 'msg', 'exp'].
     */
    public function presentar(array $d, $archivo)
    {
        $errores = array();
        if ((int)$d['iddocumento'] <= 0) $errores[] = 'Seleccione el tipo de documento.';
        if ((int)$d['idtipo_tramite'] <= 0) $errores[] = 'Seleccione el tipo de trámite.';
        if ($d['numero_docu'] === '') $errores[] = 'Ingrese el número de documento.';
        if ($d['asunto'] === '') $errores[] = 'Ingrese el asunto.';
        if (!is_numeric($d['folios']) || (int)$d['folios'] < 1) $errores[] = 'Ingrese un número de folios válido.';
        if ($d['email'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo electrónico válido.';
        if ($d['d_identidad'] === '') $errores[] = 'Ingrese el DNI o RUC.';
        if ($errores) return array('ok' => false, 'msg' => implode(' ', $errores));

        $adjunto = null;
        if ($archivo && !empty($archivo['tmp_name']) && is_uploaded_file($archivo['tmp_name'])) {
            $g = ArchivoService::guardar($archivo, 'REGISTRO', 'VIRTUAL');
            if (!$g['ok']) return array('ok' => false, 'msg' => $g['msg'], 'archivo' => true);
            $adjunto = $g['nombre'];
        }
        try {
            // Remitente: solo se reutiliza si coinciden correo Y DNI/RUC (nunca un id enviado por el navegador)
            $idremit = MPV::buscarRemitente($d['email'], $d['d_identidad']);
            if ($idremit <= 0) {
                $idremit = MPV::crearRemitente($d['d_identidad'], $d['apellidos'], $d['nombres'], $d['direccion'], $d['email'], $d['telefono']);
            }
        } catch (\Exception $e) {
            error_log('MPV remitente: ' . $e->getMessage());
            $idremit = 0;
        }
        if ($idremit <= 0) {
            return array('ok' => false, 'msg' => 'No se pudieron guardar sus datos. Revise que no haya caracteres especiales e intente nuevamente.');
        }
        try {
            $exp = NumeracionService::nuevoExpediente();
            MPV::crearRegistro((int)$d['iddocumento'], $idremit, (int)$d['idtipo_tramite'], $d['numero_docu'], $d['asunto'], (int)$d['folios'], $adjunto, $exp);
            ArchivoService::cvd('EXPEDIENTE', $exp);
        } catch (\Exception $e) {
            error_log('MPV registro: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'No se pudo registrar tu documento. Inténtalo nuevamente o acércate a mesa de partes.');
        }
        $email = $d['email'];
        $asunto = $d['asunto'];
        $this->despues(function () use ($email, $exp, $asunto) {
            $this->correoConstancia($email, $exp, $asunto);   // constancia al ciudadano
            $this->avisoMesaPartes($exp);                     // aviso interno con el adjunto
        });
        return array('ok' => true, 'msg' => '', 'exp' => $exp);
    }

    /** El ciudadano sube el documento corregido: vuelve a "Por recepcionar" como SUBSANADO. */
    public function subsanar($token, $archivo, $nota)
    {
        $doc = MPV::porToken($token);
        if (!$doc) return array('ok' => false, 'msg' => 'El enlace no es válido o ya fue usado.');
        if (!self::plazoVigente($doc[19])) {
            return array('ok' => false, 'msg' => 'El plazo para subsanar venció el ' . Cal::texto(Cal::cierre($doc[19])) . '.');
        }
        if (empty($archivo) || empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            return array('ok' => false, 'msg' => 'Adjunte el documento corregido.');
        }
        $g = ArchivoService::guardar($archivo, 'REGISTRO', 'VIRTUAL');
        if (!$g['ok']) return array('ok' => false, 'msg' => $g['msg']);
        $nota = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$nota)), 0, 1000, 'UTF-8');
        $st = Database::consulta("UPDATE registro_documentos SET adjunto = ?, mpv_estado = 'SUBSANADO', mpv_token = NULL
                                   WHERE idregistro_documentos = ? AND mpv_token = ? AND mpv_estado = 'OBSERVADO'",
            array($g['nombre'], (int)$doc[0], $token));
        if ($st->rowCount() === 0) {
            @unlink($g['ruta']);
            return array('ok' => false, 'msg' => 'El enlace ya fue usado.');
        }
        MPV::evento($doc[0], 'SUBSANADO', $nota !== '' ? $nota : null, $g['nombre'], null, null);
        $id = (int)$doc[0];
        $ruta = $g['ruta'];
        $original = $archivo['name'];
        $this->despues(function () use ($id, $ruta, $original, $nota) {
            $this->correoSubsanacionRecibida($id);
            $this->avisoSubsanacion($id, $ruta, $original, $nota);
        });
        return array('ok' => true, 'msg' => 'Subsanado.', 'exp' => $doc[1], 'id' => $id);
    }

    public static function plazoVigente($plazo)
    {
        return $plazo !== null && time() <= strtotime(Cal::cierre($plazo));
    }

    /* =========================== Correos =========================== */

    private function urlSeguimiento($nroExp)
    {
        return Url::absoluta('consulta') . '?num_exp=' . rawurlencode((string)$nroExp);
    }

    /** Correo al ciudadano del documento: 'OK' | 'FALLO' | 'SIN_CORREO'. */
    private function correoCiudadano($id, $asunto, $titulo, array $parrafos, array $filas, $boton, $tipo)
    {
        $x = MPV::datosCorreo($id);
        if (!$x || !filter_var(trim((string)$x['email']), FILTER_VALIDATE_EMAIL)) return 'SIN_CORREO';
        $saludo = trim((string)$x['ciudadano']) !== '' ? 'Estimado(a) ' . h($x['ciudadano']) . ':' : 'Estimado(a) ciudadano(a):';
        $html = '<p style="font-size:15px;line-height:1.5">' . $saludo . '</p>';
        foreach ($parrafos as $p) {
            $html .= '<p style="font-size:15px;line-height:1.5">' . $p . '</p>';
        }
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:45%">Número de expediente</td><td style="padding:6px 0;font-size:18px"><strong>' . h($x['nro_exp']) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . h($x['asunto']) . '</td></tr>';
        foreach ($filas as $etq => $val) {
            $html .= '<tr><td style="padding:6px 0;color:#52606d">' . h($etq) . '</td><td style="padding:6px 0">' . $val . '</td></tr>';
        }
        $html .= '</table>';
        if ($boton) {
            $html .= '<p style="text-align:center;margin:24px 0"><a href="' . h($boton[1]) . '" style="background:' . $boton[2] . ';color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;display:inline-block">' . h($boton[0]) . '</a></p>'
                . '<p style="font-size:12px;color:#7b8794">Si el botón no funciona, copie este enlace en su navegador:<br>' . h($boton[1]) . '</p>';
        }
        $ok = CorreoService::enviar(trim($x['email']), $asunto . ' - Expediente N° ' . $x['nro_exp'],
            CorreoService::plantilla($titulo, $html), array('tipo' => $tipo, 'referencia' => $x['nro_exp']));
        return $ok ? 'OK' : 'FALLO';
    }

    private function correoRecepcionado($id)
    {
        $x = MPV::datosCorreo($id);
        return $this->correoCiudadano($id, 'Documento recepcionado', 'Su documento fue recepcionado',
            array('La Mesa de Partes de la ' . h(SISGEDO_ENTIDAD) . ' <strong>recepcionó su documento</strong>. En breve será derivado al área competente para su atención.'),
            array('Presentado el' => h(Cal::texto(Cal::fechaPresentacion($x['fecha_registro']))), 'Recepcionado el' => h(Cal::texto($x['mpv_recepcion']))),
            array('Ver el estado de mi trámite', $this->urlSeguimiento($x['nro_exp']), '#1f5bbf'), 'MPV_RECEPCIONADO');
    }

    private function correoObservado($id, $motivo, $plazo, $token)
    {
        $vence = Cal::texto(Cal::cierre($plazo));
        $n = (int)SISGEDO_PLAZO_SUBSANAR;
        return $this->correoCiudadano($id, 'Documento OBSERVADO', 'Su documento tiene una observación',
            array(
                'La Mesa de Partes de la ' . h(SISGEDO_ENTIDAD) . ' revisó su documento y encontró una <strong>observación</strong> que debe corregir:',
                '<span style="display:block;background:#fff7ed;border-left:4px solid #f97316;padding:10px 14px;color:#7c2d12">' . nl2br(h($motivo)) . '</span>',
                'Tiene <strong>' . $n . ' días hábiles</strong> para subsanarla (Ley N° 27444, art. 136): <strong>hasta el ' . h($vence) . '</strong>. '
                    . 'Pulse el botón, adjunte el documento corregido y envíelo. Mantendrá el mismo número de expediente y la fecha de presentación original. '
                    . 'Si no lo subsana dentro del plazo, el documento se considerará <strong>no presentado</strong>.',
            ),
            array('Plazo para subsanar' => '<strong>' . h($vence) . '</strong>'),
            array('Subsanar mi documento', Url::absoluta('subsanar') . '?t=' . $token, '#ea580c'), 'MPV_OBSERVADO');
    }

    private function correoNoPresentado($id)
    {
        return $this->correoCiudadano($id, 'Documento NO PRESENTADO', 'Su documento se considera no presentado',
            array(
                'Su documento fue observado y <strong>no se subsanó dentro del plazo</strong>. Por ello se considera <strong>no presentado</strong> (Ley N° 27444, art. 136).',
                'Si aún desea realizar el trámite, puede presentarlo nuevamente por la Mesa de Partes Virtual de la ' . h(SISGEDO_ENTIDAD) . '.',
            ),
            array(), array('Ir a la Mesa de Partes Virtual', Url::absoluta('mesa-partes'), '#1f5bbf'), 'MPV_NO_PRESENTADO');
    }

    private function correoSubsanacionRecibida($id)
    {
        $x = MPV::datosCorreo($id);
        return $this->correoCiudadano($id, 'Subsanación recibida', 'Recibimos su documento corregido',
            array('Recibimos el documento corregido. La Mesa de Partes de la ' . h(SISGEDO_ENTIDAD) . ' lo revisará nuevamente y le avisaremos por correo cuando sea recepcionado.'),
            array('Recibido el' => h(Cal::texto(date('Y-m-d H:i:s')))),
            array('Ver el estado de mi trámite', $this->urlSeguimiento($x ? $x['nro_exp'] : ''), '#1f5bbf'), 'MPV_SUBSANADO');
    }

    /** Aviso a mesa de partes: llegó una subsanación (con el archivo). */
    private function avisoSubsanacion($id, $rutaArchivo, $nombreOriginal, $nota)
    {
        $destino = trim((string)CorreoService::config('aviso_mpv'));
        $x = MPV::datosCorreo($id);
        if ($destino === '' || !$x) return false;
        $cuerpo = '<p style="font-size:15px;line-height:1.5">El ciudadano <strong>subsanó</strong> el documento observado. Volvió a la bandeja <em>Por recepcionar</em> de la Mesa de Partes Virtual.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:38%">Expediente N°</td><td style="padding:6px 0"><strong>' . h($x['nro_exp']) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#52606d">Ciudadano</td><td style="padding:6px 0">' . h($x['ciudadano']) . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . h($x['asunto']) . '</td></tr>'
            . ($nota !== '' ? '<tr><td style="padding:6px 0;color:#52606d">Nota del ciudadano</td><td style="padding:6px 0">' . h($nota) . '</td></tr>' : '')
            . '</table><p style="font-size:13px;color:#52606d">Se adjunta el documento corregido.</p>';
        $responder = filter_var(trim((string)$x['email']), FILTER_VALIDATE_EMAIL) ? trim($x['email']) : '';
        return CorreoService::enviar($destino, 'MPV - Subsanado - Exp. N° ' . $x['nro_exp'] . ' - ' . $x['ciudadano'],
            CorreoService::plantilla('Documento subsanado', $cuerpo),
            array('tipo' => 'MPV_AVISO_SUBSANADO', 'referencia' => $x['nro_exp'], 'responder_a' => $responder,
                  'adjuntos' => array(array('ruta' => $rutaArchivo, 'nombre' => $nombreOriginal)), 'copia_enviados' => false));
    }

    /** Constancia de presentación al ciudadano. */
    private function correoConstancia($destino, $numexp, $asuntoDoc)
    {
        $url = $this->urlSeguimiento($numexp);
        $cvd = '';
        if ($c = ArchivoService::cvd('EXPEDIENTE', $numexp)) {
            $cvd = '<tr><td style="padding:6px 0;color:#52606d">Código de verificación</td>'
                . '<td style="padding:6px 0"><strong>' . h(ArchivoService::formatoCvd($c)) . '</strong><br>'
                . '<a href="' . h(ArchivoService::urlVerificacion($c)) . '" style="font-size:12px;color:#1f5bbf">Verificar autenticidad</a></td></tr>';
        }
        $ahora = date('Y-m-d H:i:s');
        $oficial = Cal::fechaPresentacion($ahora);
        $fuera = strtotime($oficial) !== strtotime($ahora);
        $cuerpo = '<p style="font-size:15px;line-height:1.5">Su documento fue <strong>recibido correctamente</strong> en la Mesa de Partes Virtual de la '
            . h(SISGEDO_ENTIDAD) . '. Guarde este correo como constancia de presentación. Le enviaremos otro correo cuando mesa de partes lo recepcione.</p>'
            . ($fuera ? '<p style="font-size:14px;line-height:1.5;background:#f5f3ff;border-left:4px solid #7c3aed;padding:10px 14px;color:#4c1d95">'
                . 'Lo envió <strong>fuera del horario de atención</strong> (' . h(SISGEDO_HORARIO_TEXTO) . '). '
                . 'Para el cómputo de plazos se considera presentado el <strong>' . h(Cal::texto($oficial)) . '</strong>.</p>' : '')
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:45%">Número de expediente</td><td style="padding:6px 0;font-size:18px"><strong>' . h($numexp) . '</strong></td></tr>'
            . ($asuntoDoc !== '' ? '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . h($asuntoDoc) . '</td></tr>' : '')
            . '<tr><td style="padding:6px 0;color:#52606d">Fecha y hora de envío</td><td style="padding:6px 0">' . h(date('d/m/Y H:i', strtotime($ahora))) . '</td></tr>'
            . ($fuera ? '<tr><td style="padding:6px 0;color:#52606d">Se considera presentado</td><td style="padding:6px 0"><strong>' . h(Cal::texto($oficial)) . '</strong></td></tr>' : '')
            . $cvd . '</table>'
            . '<p style="text-align:center;margin:24px 0"><a href="' . h($url) . '" style="background:#1f5bbf;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;display:inline-block">Ver el estado de mi trámite</a></p>'
            . '<p style="font-size:12px;color:#7b8794">Si el botón no funciona, copie este enlace en su navegador:<br>' . h($url) . '</p>';
        return CorreoService::enviar($destino, 'Constancia de recepción - Expediente N° ' . $numexp,
            CorreoService::plantilla('Mesa de Partes Virtual', $cuerpo), array('tipo' => 'MPV_RECEPCION', 'referencia' => $numexp));
    }

    /** Aviso interno a mesa de partes con los datos del expediente y el adjunto. */
    private function avisoMesaPartes($numexp)
    {
        $destino = trim((string)CorreoService::config('aviso_mpv'));
        if ($destino === '') return false;
        try {
            $x = MPV::datosAviso($numexp);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        if (!$x) return false;
        $fila = function ($etq, $val, $negrita = false) {
            if (trim((string)$val) === '') return '';
            return '<tr><td style="padding:6px 8px;color:#52606d;width:38%;border-bottom:1px solid #eef1f5">' . h($etq) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef1f5">' . ($negrita ? '<strong>' . h($val) . '</strong>' : h($val)) . '</td></tr>';
        };
        $ciudadano = trim($x['apellidos'] . ' ' . $x['nombres']);
        $adjuntos = array();
        $nota = 'El ciudadano no adjuntó archivo.';
        if (!empty($x['adjunto'])) {
            $ruta = ArchivoService::ruta('REGISTRO', $x['adjunto']);
            $max = (int)CorreoService::config('aviso_adjunto_max_mb') * 1048576;
            if (is_file($ruta) && filesize($ruta) <= $max) {
                $nombre = trim((string)$x['nombre_original']) !== '' ? $x['nombre_original'] : $x['adjunto'];
                $adjuntos[] = array('ruta' => $ruta, 'nombre' => $nombre);
                $nota = 'Se adjunta el documento presentado (' . $nombre . ').';
            } elseif (is_file($ruta)) {
                $nota = 'El documento pesa más de ' . (int)($max / 1048576) . ' MB: ábralo desde el sistema.';
            }
        }
        $url = $this->urlSeguimiento($x['nro_exp']);
        $cuerpo = '<p style="font-size:15px;line-height:1.5">Ingresó un nuevo documento por la <strong>Mesa de Partes Virtual</strong>.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0;width:100%;border-collapse:collapse">'
            . $fila('Expediente N°', $x['nro_exp'], true)
            . $fila('Fecha y hora', $x['fecha_registro'] ? date('d/m/Y H:i', strtotime($x['fecha_registro'])) : date('d/m/Y H:i'))
            . ($x['fecha_registro'] && Cal::fueraDeHorario($x['fecha_registro'])
                ? $fila('Fuera de horario: se considera presentado', Cal::texto(Cal::fechaPresentacion($x['fecha_registro'])), true) : '')
            . $fila('Tipo de documento', trim($x['nombre_documento'] . ' ' . $x['numero_docu']))
            . $fila('Tipo de trámite', $x['tramite'])
            . $fila('Asunto', $x['asunto'], true)
            . $fila('Folios', $x['folios'])
            . $fila('Remitente', $ciudadano)
            . $fila('DNI / RUC', $x['d_identidad'])
            . $fila('Correo', $x['email'])
            . $fila('Teléfono', $x['telefono'])
            . $fila('Dirección', $x['direccion'])
            . '</table>'
            . '<p style="font-size:13px;color:#52606d">' . h($nota) . ' Para responder al ciudadano use "Responder": la respuesta irá a su correo.</p>'
            . '<p style="text-align:center;margin:20px 0"><a href="' . h($url) . '" style="background:#1f5bbf;color:#ffffff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:bold;display:inline-block">Ver el expediente</a></p>'
            . '<p style="font-size:12px;color:#7b8794">Para recepcionarlo y derivarlo ingrese al SISGEDO &gt; Operaciones &gt; Mesa de Partes Virtual.</p>';
        $asunto = mb_strimwidth('MPV - Exp. N° ' . $x['nro_exp'] . ' - ' . ($ciudadano !== '' ? $ciudadano . ': ' : '') . $x['asunto'], 0, 150, '…', 'UTF-8');
        $responder = filter_var(trim((string)$x['email']), FILTER_VALIDATE_EMAIL) ? trim($x['email']) : '';
        return CorreoService::enviar($destino, $asunto, CorreoService::plantilla('Nuevo documento por Mesa de Partes Virtual', $cuerpo),
            array('tipo' => 'MPV_AVISO_INTERNO', 'referencia' => $x['nro_exp'], 'responder_a' => $responder, 'adjuntos' => $adjuntos, 'copia_enviados' => false));
    }
}
