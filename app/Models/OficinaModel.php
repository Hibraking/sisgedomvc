<?php
namespace App\Models;

use App\Core\Database;

/** Oficinas y cargos del administrador "en nombre de" una oficina. */
class OficinaModel
{
    public static function nombre($idoficina)
    {
        return (string)Database::valor("SELECT nombre FROM oficinas WHERE idoficinas = ?", array((int)$idoficina));
    }

    public static function existe($idoficina)
    {
        return (int)Database::valor("SELECT COUNT(*) FROM oficinas WHERE idoficinas = ?", array((int)$idoficina)) > 0;
    }

    /** Oficinas con nombre (para listas). [[id, nombre], ...] */
    public static function lista()
    {
        return Database::filas("SELECT idoficinas, nombre FROM oficinas
                                 WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-') ORDER BY nombre", array(), \PDO::FETCH_NUM);
    }

    /** Nombres de varias oficinas: [id => nombre] */
    public static function nombres(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return array();
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $out = array();
        foreach (Database::filas("SELECT idoficinas, nombre FROM oficinas WHERE idoficinas IN ($marcas)", $ids, \PDO::FETCH_NUM) as $f) {
            $out[(int)$f[0]] = $f[1];
        }
        return $out;
    }

    /**
     * Cargo con el que el administrador actúa en una oficina: el suyo si ya es
     * de esa oficina; si no, uno "en nombre de" (inactivo, se crea una vez).
     */
    public static function responsableEnNombre($idoficina)
    {
        $id = Database::valor("SELECT idresponsables FROM responsables WHERE idpersonal = 1 AND idoficina = ?
                                ORDER BY (estado = 1) DESC, idresponsables ASC LIMIT 1", array((int)$idoficina));
        if ($id !== false) {
            return (int)$id;
        }
        Database::consulta("INSERT INTO responsables (idpersonal, idoficina, estado) VALUES (1, ?, 0)", array((int)$idoficina));
        return (int)Database::pdo()->lastInsertId();
    }
}
