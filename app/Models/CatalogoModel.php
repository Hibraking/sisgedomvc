<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Catálogos simples (config/catalogos.php): listar, contar, crear, modificar y eliminar.
 * Los nombres de tablas/columnas vienen SOLO de la configuración (nunca del navegador).
 */
class CatalogoModel
{
    public static function definicion($clave)
    {
        static $todos = null;
        if ($todos === null) {
            $todos = (static function () {
                return require SISGEDO_RAIZ . '/config/catalogos.php';
            })();
        }
        return isset($todos[$clave]) ? $todos[$clave] : null;
    }

    private static function params($sql, $idoficina)
    {
        return strpos($sql, ':oficina') !== false ? array(':oficina' => (int)$idoficina) : array();
    }

    public static function lista(array $def, $idoficina)
    {
        try {
            return Database::filas($def['lista'], self::params($def['lista'], $idoficina), PDO::FETCH_NUM);
        } catch (\Exception $e) {
            error_log('Catálogo ' . $def['tabla'] . ': ' . $e->getMessage());
            return array();
        }
    }

    public static function kpi($sql, $idoficina)
    {
        try {
            return (int)Database::valor($sql, self::params($sql, $idoficina));
        } catch (\Exception $e) {
            error_log('Catálogo KPI: ' . $e->getMessage());
            return 0;
        }
    }

    /** ¿Ya existe otro registro con el mismo valor (sin espacios de más)? */
    public static function duplicado(array $def, $valor, $excluirId)
    {
        $col = $def['unico'];
        return (int)Database::valor("SELECT COUNT(*) FROM {$def['tabla']}
                                      WHERE REGEXP_REPLACE(TRIM($col), ' {2,}', ' ') = REGEXP_REPLACE(TRIM(?), ' {2,}', ' ')
                                        AND {$def['pk']} <> ?", array($valor, (int)$excluirId)) > 0;
    }

    /** ¿El registro pertenece a la oficina? (catálogos por oficina) */
    public static function deOficina(array $def, $id, $idoficina)
    {
        return (int)Database::valor("SELECT COUNT(*) FROM {$def['tabla']} WHERE {$def['pk']} = ? AND idoficina = ?", array((int)$id, (int)$idoficina)) > 0;
    }

    public static function crear(array $def, array $valores)
    {
        $cols = implode(',', array_keys($valores));
        $marcas = implode(',', array_fill(0, count($valores), '?'));
        Database::consulta("INSERT INTO {$def['tabla']} ($cols) VALUES ($marcas)", array_values($valores));
    }

    public static function modificar(array $def, $id, array $valores)
    {
        $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($valores)));
        Database::consulta("UPDATE {$def['tabla']} SET $set WHERE {$def['pk']} = ?", array_merge(array_values($valores), array((int)$id)));
    }

    public static function eliminar(array $def, $id)
    {
        return Database::consulta("DELETE FROM {$def['tabla']} WHERE {$def['pk']} = ?", array((int)$id))->rowCount() > 0;
    }
}
