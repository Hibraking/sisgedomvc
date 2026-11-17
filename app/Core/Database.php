<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Conexión única a MySQL por petición (la misma base que sisgedov2).
 * Modo excepción, utf8mb4 y hora del Perú (-05:00), igual que sisgedov2.
 */
class Database
{
    private static $pdo = null;

    public static function pdo()
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $c = require SISGEDO_RAIZ . '/config/database.php';
        try {
            self::$pdo = new PDO(
                'mysql:host=' . $c['host'] . ';dbname=' . $c['nombre'] . ';charset=utf8mb4',
                $c['usuario'],
                $c['clave'],
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
            );
            self::$pdo->exec("SET NAMES utf8mb4, time_zone = '-05:00'");
        } catch (PDOException $e) {
            error_log('SISGEDO MVC - conexión BD: ' . $e->getMessage());
            http_response_code(500);
            exit(SISGEDO_ES_LOCAL ? 'Error de conexión a la base de datos: ' . h($e->getMessage())
                                   : 'No se pudo conectar con el sistema. Intente más tarde.');
        }
        return self::$pdo;
    }

    /** Ejecuta una consulta preparada y devuelve el PDOStatement. */
    public static function consulta($sql, array $params = array())
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function valor($sql, array $params = array())
    {
        return self::consulta($sql, $params)->fetchColumn();
    }

    public static function fila($sql, array $params = array())
    {
        $f = self::consulta($sql, $params)->fetch();
        return $f === false ? null : $f;
    }

    public static function filas($sql, array $params = array(), $modo = PDO::FETCH_ASSOC)
    {
        return self::consulta($sql, $params)->fetchAll($modo);
    }
}
