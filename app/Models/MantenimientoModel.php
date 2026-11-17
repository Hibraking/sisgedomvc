<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/** Remitentes, personal, responsables y accesos — mismas consultas de sisgedov2 (clases/*.php y vistas). */
class MantenimientoModel
{
    /* ---------------- Remitentes ---------------- */

    public static function kpisRemitentes()
    {
        return array(
            'total' => (int)Database::valor("SELECT COUNT(*) FROM remitente"),
            'conEmail' => (int)Database::valor("SELECT COUNT(*) FROM remitente WHERE email IS NOT NULL AND TRIM(email)<>''"),
            'nuevosMes' => (int)Database::valor("SELECT COUNT(DISTINCT idremitente) FROM registro_documentos WHERE fecha_registro >= date_format(now(),'%Y-%m-01') and fecha_registro < date_format(now(),'%Y-%m-01') + interval 1 month"),
        );
    }

    /** Paginación en servidor (formato DataTables, igual que sisgedov2). */
    public static function tablaRemitentes($draw, $start, $length, $buscar, $ordCol, $ordDir)
    {
        $colmap = array(1 => 'idremitente', 2 => 'apellidos', 3 => 'nombres', 4 => 'direccion', 5 => 'email', 6 => 'telefono');
        $orderBy = isset($colmap[$ordCol]) ? $colmap[$ordCol] : 'apellidos';
        $ordDir = $ordDir === 'DESC' ? 'DESC' : 'ASC';
        $length = ($length < 1 || $length > 200) ? 25 : (int)$length;
        $start = max(0, (int)$start);
        $where = '';
        $params = array();
        if ($buscar !== '') {
            $where = "WHERE (apellidos LIKE ? OR nombres LIKE ? OR direccion LIKE ? OR email LIKE ? OR telefono LIKE ? OR idremitente = ?)";
            $like = '%' . $buscar . '%';
            $params = array($like, $like, $like, $like, $like, ctype_digit($buscar) ? (int)$buscar : -1);
        }
        $total = (int)Database::valor("SELECT COUNT(*) FROM remitente");
        $filtrados = (int)Database::valor("SELECT COUNT(*) FROM remitente $where", $params);
        $filas = Database::filas("SELECT idremitente, apellidos, nombres, direccion, email, telefono FROM remitente $where
                                  ORDER BY $orderBy $ordDir LIMIT $length OFFSET $start", $params, PDO::FETCH_NUM);
        $chk = '<label class="pos-rel"><input type="checkbox" class="ace" /><span class="lbl"></span></label>';
        $acc = '<div class="hidden-sm hidden-xs action-buttons"><a class="green editar" href="#"><i class="ace-icon fa fa-pencil bigger-130"></i></a>'
            . '<a class="red eliminar" href="#"><i class="ace-icon fa fa-trash-o bigger-130"></i></a></div>';
        $data = array();
        foreach ($filas as $f) {
            $data[] = array($chk, h($f[0]), h($f[1]), h($f[2]), h($f[3]), h($f[4]), h($f[5]), $acc);
        }
        return array('draw' => (int)$draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtrados, 'data' => $data);
    }

    public static function guardarRemitente($codigo, $ape, $nom, $dir, $email, $tel)
    {
        if ($codigo === '') {
            Database::consulta("INSERT INTO remitente(apellidos,nombres,direccion,email,telefono) VALUES(?,?,?,?,?)", array($ape, $nom, $dir, $email, $tel));
            return (int)Database::pdo()->lastInsertId();
        }
        Database::consulta("UPDATE remitente SET apellidos=?, nombres=?, direccion=?, email=?, telefono=? WHERE idremitente=?", array($ape, $nom, $dir, $email, $tel, (int)$codigo));
        return (int)$codigo;
    }

    /* ---------------- Personal ---------------- */

    public static function personal()
    {
        $filas = Database::filas("SELECT idpersonal, apellidos, nombres, COALESCE(usuario,''), COALESCE(email,''), COALESCE(telefono,''), COALESCE(direccion,'') FROM personal ORDER BY apellidos ASC", array(), PDO::FETCH_NUM);
        $mapa = array();
        foreach (Database::filas("SELECT r.idpersonal, GROUP_CONCAT(o.nombre SEPARATOR ' • ') FROM responsables r INNER JOIN oficinas o ON r.idoficina=o.idoficinas GROUP BY r.idpersonal", array(), PDO::FETCH_NUM) as $mo) {
            $mapa[(int)$mo[0]] = (string)$mo[1];
        }
        return array(
            'filas' => $filas, 'mapaOfis' => $mapa,
            'conUsuario' => (int)Database::valor("SELECT COUNT(*) FROM personal WHERE usuario IS NOT NULL AND TRIM(usuario)<>''"),
            'respActivos' => (int)Database::valor("SELECT COUNT(DISTINCT idpersonal) FROM responsables WHERE estado=1"),
        );
    }

    /** ¿Otro personal ya usa ese nombre de usuario? */
    public static function usuarioOcupado($usuario, $excluirId)
    {
        return (int)Database::valor("SELECT COUNT(*) FROM personal WHERE usuario = ? AND idpersonal <> ?", array($usuario, (int)$excluirId)) > 0;
    }

    public static function guardarPersonal($codigo, array $d, $clave)
    {
        $hash = $clave !== '' ? password_hash($clave, PASSWORD_DEFAULT) : null;
        $usuario = $d['usuario'] !== '' ? $d['usuario'] : null;
        if ($codigo === '') {
            Database::consulta("INSERT INTO personal(apellidos,nombres,direccion,email,telefono,usuario,clave) VALUES(?,?,?,?,?,?,?)",
                array($d['apellidos'], $d['nombres'], $d['direccion'], $d['email'], $d['telefono'], $usuario, $hash));
            return;
        }
        if ($hash === null) {     // sin contraseña nueva: se conserva la actual
            Database::consulta("UPDATE personal SET apellidos=?, nombres=?, direccion=?, email=?, telefono=?, usuario=? WHERE idpersonal=?",
                array($d['apellidos'], $d['nombres'], $d['direccion'], $d['email'], $d['telefono'], $usuario, (int)$codigo));
        } else {
            Database::consulta("UPDATE personal SET apellidos=?, nombres=?, direccion=?, email=?, telefono=?, usuario=?, clave=? WHERE idpersonal=?",
                array($d['apellidos'], $d['nombres'], $d['direccion'], $d['email'], $d['telefono'], $usuario, $hash, (int)$codigo));
        }
    }

    /* ---------------- Responsables ---------------- */

    public static function responsables()
    {
        return array(
            'filas' => Database::filas("SELECT r.idresponsables, CONCAT(p.apellidos,', ',p.nombres), o.nombre, r.estado, r.idpersonal, r.idoficina
                                          FROM responsables r INNER JOIN personal p ON r.idpersonal=p.idpersonal
                                         INNER JOIN oficinas o ON r.idoficina=o.idoficinas ORDER BY o.nombre ASC, p.apellidos ASC", array(), PDO::FETCH_NUM),
            'personas' => Database::filas("SELECT idpersonal, CONCAT(apellidos,', ',nombres) FROM personal ORDER BY apellidos ASC", array(), PDO::FETCH_NUM),
            'oficinas' => OficinaModel::lista(),
        );
    }

    public static function guardarResponsable($codigo, $idpersonal, $idoficina, $estado)
    {
        if ($codigo === '') {
            Database::consulta("INSERT INTO responsables(idpersonal,idoficina,estado) VALUES(?,?,?)", array((int)$idpersonal, (int)$idoficina, (int)$estado));
        } else {
            Database::consulta("UPDATE responsables SET idpersonal=?, idoficina=?, estado=? WHERE idresponsables=?", array((int)$idpersonal, (int)$idoficina, (int)$estado, (int)$codigo));
        }
    }

    /* ---------------- Accesos ---------------- */

    public static function usuariosConCuenta()
    {
        return Database::filas("SELECT idpersonal, CONCAT(apellidos,', ',nombres), COALESCE(usuario,'') FROM personal WHERE usuario IS NOT NULL AND TRIM(usuario)<>'' ORDER BY apellidos ASC", array(), PDO::FETCH_NUM);
    }

    /** Ítems del menú con dirección y, si lo tiene, el id del acceso del usuario. */
    public static function menusDeUsuario($idusuario)
    {
        return Database::filas("SELECT m.idmenu, m.nombre_menu, m.direccion, COALESCE(m.dependencia,''), a.idaccesos_usuario
                                  FROM menu m
                                  LEFT JOIN accesos_usuario a ON a.idmenu=m.idmenu AND a.idusuario=? AND a.access=1
                                 WHERE m.direccion IS NOT NULL AND m.direccion <> '#'
                                 ORDER BY m.dependencia ASC, m.idmenu ASC", array((int)$idusuario), PDO::FETCH_NUM);
    }

    public static function concederAcceso($idmenu, $idusuario)
    {
        // Sin duplicados (doble clic)
        $ya = (int)Database::valor("SELECT COUNT(*) FROM accesos_usuario WHERE idmenu=? AND idusuario=? AND access=1", array((int)$idmenu, (int)$idusuario));
        if ($ya === 0) {
            Database::consulta("INSERT INTO accesos_usuario(idmenu,idusuario,access) VALUES(?,?,true)", array((int)$idmenu, (int)$idusuario));
        }
    }

    public static function revocarAcceso($idacceso)
    {
        Database::consulta("DELETE FROM accesos_usuario WHERE idaccesos_usuario=?", array((int)$idacceso));
    }

    /** Elimina un registro (lanza excepción si está en uso). */
    public static function eliminar($tabla, $pk, $id)
    {
        Database::consulta("DELETE FROM $tabla WHERE $pk=?", array((int)$id));
    }
}
