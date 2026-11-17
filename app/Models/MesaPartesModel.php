<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Mesa de Partes Virtual: SOLO consultas (las mismas de sisgedov2/clases/mesa_partes_virtual.php
 * y clases/mpvirtual.php). Requiere las migraciones 007 y 008.
 *
 * Fila de bandeja (índices): 0 id · 1 nro_exp · 2 documento · 3 número · 4 remitente · 5 DNI/RUC
 * 6 correo · 7 teléfono · 8 trámite · 9 asunto · 10 folios · 11 fecha_registro · 12 adjunto
 * 13 mpv_recepcion · 14 quién recepcionó · 15 oficina destino · 16 estado_recepcion · 17 dirección
 * 18 mpv_estado · 19 mpv_plazo · 20 último motivo de observación
 */
class MesaPartesModel
{
    const BANDEJAS = array('pendientes', 'observados', 'por_derivar', 'derivados');

    /** ¿Están aplicadas las migraciones 007 y 008 de la Mesa de Partes Virtual? */
    public static function moduloActivo()
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = (int)Database::valor("SELECT COUNT(*) FROM information_schema.columns
                        WHERE table_schema = DATABASE() AND table_name = 'registro_documentos'
                          AND column_name IN ('mpv_recepcion', 'mpv_estado')") === 2;
            } catch (\Exception $e) {
                $ok = false;
            }
            if (!$ok) {
                error_log('SISGEDO: faltan migraciones 007 y/o 008 (módulo Mesa de Partes Virtual).');
            }
        }
        return $ok;
    }

    /**
     * Número rojo del menú: por recepcionar (incluye subsanados) + observados con
     * plazo vencido + recepcionados por derivar.
     */
    public static function porAtender()
    {
        if (!self::moduloActivo()) return 0;
        return (int)Database::valor("SELECT
              (SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_recepcion IS NULL
                 AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO' OR (mpv_estado = 'OBSERVADO' AND mpv_plazo < CURDATE())))
            + (SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND idoficina IS NULL AND mpv_idpersonal IS NOT NULL
                 AND mpv_recepcion IS NOT NULL AND COALESCE(estado_recepcion,0) = 0)");
    }

    /* ===================== Bandejas ===================== */

    private static function sqlBase()
    {
        return "SELECT rd.idregistro_documentos, rd.nro_exp, COALESCE(d.nombre_documento,''), COALESCE(rd.numero_docu,''),
                       TRIM(CONCAT(COALESCE(r.apellidos,''),' ',COALESCE(r.nombres,''))), COALESCE(r.d_identidad,''),
                       COALESCE(r.email,''), COALESCE(r.telefono,''), COALESCE(t.nombre,''), COALESCE(rd.asunto,''),
                       COALESCE(rd.folios,0), rd.fecha_registro, COALESCE(rd.adjunto,''), rd.mpv_recepcion,
                       COALESCE(TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),''),
                       COALESCE(o.nombre,''), COALESCE(rd.estado_recepcion,0), COALESCE(r.direccion,''),
                       COALESCE(rd.mpv_estado,''), rd.mpv_plazo,
                       COALESCE((SELECT e.detalle FROM sgd_mpv_evento e
                                  WHERE e.idregistro_documentos = rd.idregistro_documentos AND e.tipo = 'OBSERVADO'
                                  ORDER BY e.fecha DESC, e.idevento DESC LIMIT 1),'')
                  FROM registro_documentos rd
                  LEFT JOIN documentos d ON d.iddocumentos = rd.iddocumento
                  LEFT JOIN remitente r  ON r.idremitente = rd.idremitente
                  LEFT JOIN tipo_tram t  ON t.idtipo_tram = rd.idtipo_tramite
                  LEFT JOIN personal p   ON p.idpersonal = rd.mpv_idpersonal
                  LEFT JOIN oficinas o   ON o.idoficinas = rd.idoficina
                 WHERE rd.recepcion_web = 1 ";
    }

    private static function condicion($bandeja)
    {
        switch ($bandeja) {
            case 'pendientes':
                return " AND rd.mpv_recepcion IS NULL AND (rd.mpv_estado IS NULL OR rd.mpv_estado = 'SUBSANADO')";
            case 'observados':
                return " AND rd.mpv_recepcion IS NULL AND rd.mpv_estado = 'OBSERVADO'";
            case 'por_derivar':
                return " AND rd.mpv_recepcion IS NOT NULL AND rd.mpv_idpersonal IS NOT NULL
                         AND rd.idoficina IS NULL AND COALESCE(rd.estado_recepcion,0) = 0";
            default: // historial
                return " AND (rd.idoficina IS NOT NULL OR rd.mpv_estado = 'NO_PRESENTADO')";
        }
    }

    /** Documentos de una bandeja (máx. 500). El historial filtra por fechas (por defecto, 30 días). */
    public static function lista($bandeja, $q = '', $desde = '', $hasta = '', $orden = 'recientes')
    {
        if (!self::moduloActivo()) return array();
        $sql = self::sqlBase() . self::condicion($bandeja);
        $p = array();
        $dir = $orden === 'antiguos' ? 'ASC' : 'DESC';
        if ($bandeja === 'pendientes') {
            $ord = " ORDER BY rd.fecha_registro $dir";
        } elseif ($bandeja === 'observados') {
            $ord = " ORDER BY rd.mpv_plazo ASC, rd.fecha_registro ASC";
        } elseif ($bandeja === 'por_derivar') {
            $ord = " ORDER BY rd.mpv_recepcion $dir";
        } else {
            $sql .= " AND rd.fecha_registro >= ? AND rd.fecha_registro < DATE_ADD(?, INTERVAL 1 DAY)";
            $p[] = $desde !== '' ? $desde : date('Y-m-d', strtotime('-30 days'));
            $p[] = $hasta !== '' ? $hasta : date('Y-m-d');
            $ord = " ORDER BY rd.fecha_registro $dir";
        }
        $q = trim((string)$q);
        if ($q !== '') {
            $sql .= " AND (rd.nro_exp LIKE ? OR rd.asunto LIKE ? OR r.d_identidad LIKE ?
                           OR CONCAT(COALESCE(r.apellidos,''),' ',COALESCE(r.nombres,'')) LIKE ?)";
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like);
        }
        try {
            return Database::filas($sql . $ord . " LIMIT 500", $p, PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('MPV lista: ' . $e->getMessage());
            return array();
        }
    }

    public static function contadores()
    {
        $out = array('pendientes' => 0, 'observados' => 0, 'vencidos' => 0, 'por_derivar' => 0, 'hoy' => 0, 'mes' => 0);
        if (!self::moduloActivo()) return $out;
        try {
            $base = "SELECT COUNT(*) FROM registro_documentos rd WHERE rd.recepcion_web = 1 ";
            $out['pendientes'] = (int)Database::valor($base . self::condicion('pendientes'));
            $out['observados'] = (int)Database::valor($base . self::condicion('observados'));
            $out['vencidos'] = (int)Database::valor($base . self::condicion('observados') . " AND rd.mpv_plazo < CURDATE()");
            $out['por_derivar'] = (int)Database::valor($base . self::condicion('por_derivar'));
            $out['hoy'] = (int)Database::valor($base . " AND rd.fecha_registro >= CURDATE() AND rd.fecha_registro < CURDATE() + INTERVAL 1 DAY");
            $out['mes'] = (int)Database::valor($base . " AND rd.fecha_registro >= DATE_FORMAT(NOW(),'%Y-%m-01')");
        } catch (\Exception $e) {
            error_log('MPV contadores: ' . $e->getMessage());
        }
        return $out;
    }

    /** Huella para la actualización en vivo: cambia con cada llegada o acción. */
    public static function novedades()
    {
        $out = array('firma' => '', 'pendientes' => 0, 'ultimo' => null);
        if (!self::moduloActivo()) return $out;
        try {
            $cont = self::contadores();
            $maxDoc = (int)Database::valor("SELECT COALESCE(MAX(idregistro_documentos),0) FROM registro_documentos WHERE recepcion_web = 1");
            $maxEvt = (int)Database::valor("SELECT COALESCE(MAX(idevento),0) FROM sgd_mpv_evento");
            $out['firma'] = implode('-', array($maxDoc, $maxEvt, $cont['pendientes'], $cont['observados'], $cont['por_derivar']));
            $out['pendientes'] = $cont['pendientes'];
            $out['ultimo'] = NotificacionModel::ultimoMpv();
        } catch (\Exception $e) {
            error_log('MPV novedades: ' . $e->getMessage());
        }
        return $out;
    }

    /** [[tipo, detalle, archivo, plazo, fecha, quién], ...] */
    public static function historial($id)
    {
        if (!self::moduloActivo()) return array();
        try {
            return Database::filas("SELECT e.tipo, COALESCE(e.detalle,''), COALESCE(e.archivo,''), e.plazo, e.fecha,
                                           COALESCE(TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),'')
                                      FROM sgd_mpv_evento e LEFT JOIN personal p ON p.idpersonal = e.idpersonal
                                     WHERE e.idregistro_documentos = ? ORDER BY e.fecha ASC, e.idevento ASC", array((int)$id), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('MPV historial: ' . $e->getMessage());
            return array();
        }
    }

    /** Documento observado que corresponde al enlace de subsanación (o null). */
    public static function porToken($token)
    {
        if (!self::moduloActivo() || !preg_match('/^[a-f0-9]{40}$/', (string)$token)) return null;
        try {
            $r = Database::filas(self::sqlBase() . " AND rd.mpv_token = ? AND rd.mpv_estado = 'OBSERVADO' LIMIT 1", array($token), PDO::FETCH_NUM);
            return $r ? $r[0] : null;
        } catch (\Exception $e) {
            error_log('MPV porToken: ' . $e->getMessage());
            return null;
        }
    }

    /** [recepcion_web, mpv_recepcion, mpv_estado, quién recepcionó] para explicar un rechazo. */
    public static function estado($id)
    {
        $r = Database::filas("SELECT rd.recepcion_web, rd.mpv_recepcion, rd.mpv_estado,
                                     TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,'')))
                                FROM registro_documentos rd LEFT JOIN personal p ON p.idpersonal = rd.mpv_idpersonal
                               WHERE rd.idregistro_documentos = ?", array((int)$id), PDO::FETCH_NUM);
        return $r ? $r[0] : null;
    }

    /** Datos para los correos al ciudadano. */
    public static function datosCorreo($id)
    {
        return Database::fila("SELECT rd.nro_exp, rd.asunto, rd.fecha_registro, rd.mpv_recepcion, r.email,
                                      TRIM(CONCAT(COALESCE(r.nombres,''),' ',COALESCE(r.apellidos,''))) AS ciudadano
                                 FROM registro_documentos rd LEFT JOIN remitente r ON r.idremitente = rd.idremitente
                                WHERE rd.idregistro_documentos = ?", array((int)$id));
    }

    public static function evento($idreg, $tipo, $detalle = null, $archivo = null, $plazo = null, $idpersonal = null)
    {
        try {
            Database::consulta("INSERT INTO sgd_mpv_evento (idregistro_documentos, tipo, detalle, archivo, plazo, idpersonal)
                                VALUES (?, ?, ?, ?, ?, ?)", array((int)$idreg, $tipo, $detalle, $archivo, $plazo, $idpersonal));
        } catch (\Exception $e) {
            error_log('MPV evento: ' . $e->getMessage());
        }
    }

    /* ===================== Formulario público ===================== */

    public static function tiposDocumento()
    {
        return Database::filas("SELECT iddocumentos, TRIM(nombre_documento) FROM documentos
                                 WHERE TRIM(COALESCE(nombre_documento,'')) <> '' ORDER BY nombre_documento ASC", array(), PDO::FETCH_NUM);
    }

    public static function tiposTramite()
    {
        return Database::filas("SELECT idtipo_tram, TRIM(nombre) FROM tipo_tram
                                 WHERE TRIM(COALESCE(nombre,'')) <> '' ORDER BY nombre ASC", array(), PDO::FETCH_NUM);
    }

    /** Remitente con el MISMO correo y DNI/RUC (se reutiliza); 0 si no existe. */
    public static function buscarRemitente($email, $dni)
    {
        return (int)Database::valor("SELECT idremitente FROM remitente WHERE email = ? AND TRIM(d_identidad) = TRIM(?)
                                      ORDER BY idremitente DESC LIMIT 1", array($email, $dni));
    }

    public static function crearRemitente($dni, $apellidos, $nombres, $direccion, $email, $telefono)
    {
        Database::consulta("INSERT INTO remitente(idremitente,d_identidad,apellidos,nombres,direccion,email,telefono) VALUES(null,?,?,?,?,?,?)",
            array($dni, $apellidos, $nombres, $direccion, $email, $telefono));
        return (int)Database::pdo()->lastInsertId();
    }

    public static function crearRegistro($iddocumento, $idremitente, $idtramite, $numero, $asunto, $folios, $adjunto, $nroExp)
    {
        Database::consulta("INSERT INTO registro_documentos(iddocumento,idremitente,idtipo_tramite,numero_docu,asunto,folios,adjunto,nro_exp,recepcion_web)
                            VALUES(?,?,?,?,?,?,?,?,1)", array($iddocumento, $idremitente, $idtramite, $numero, $asunto, $folios, $adjunto, $nroExp));
        return (int)Database::pdo()->lastInsertId();
    }

    /** Datos completos de un expediente recién registrado (aviso interno a mesa de partes). */
    public static function datosAviso($nroExp)
    {
        return Database::fila("SELECT rd.nro_exp, rd.numero_docu, rd.asunto, rd.folios, rd.adjunto, rd.fecha_registro,
                                      d.nombre_documento, t.nombre AS tramite,
                                      r.d_identidad, r.apellidos, r.nombres, r.email, r.telefono, r.direccion,
                                      (SELECT a.nombre_original FROM sgd_archivo a
                                        WHERE a.origen = 'REGISTRO' AND a.nombre_almacenado = rd.adjunto LIMIT 1) AS nombre_original
                                 FROM registro_documentos rd
                                 LEFT JOIN documentos d ON d.iddocumentos = rd.iddocumento
                                 LEFT JOIN tipo_tram t  ON t.idtipo_tram = rd.idtipo_tramite
                                 LEFT JOIN remitente r  ON r.idremitente = rd.idremitente
                                WHERE rd.nro_exp = ? ORDER BY rd.idregistro_documentos DESC LIMIT 1", array((string)$nroExp));
    }
}
