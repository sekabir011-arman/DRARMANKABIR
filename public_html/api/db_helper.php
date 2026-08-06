<?php
/**
 * DB Helper
 * Thin wrapper around the Database singleton to provide convenient methods
 * for prepared statements and transactions.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/response.php';

class DB
{
    public static function fetchOne(string $sql, array $params = [])
    {
        $stmt = self::prepareAndExecute($sql, $params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res !== false ? $res : null;
    }

    public static function fetchAll(string $sql, array $params = [])
    {
        $stmt = self::prepareAndExecute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function execute(string $sql, array $params = [])
    {
        $stmt = self::prepareAndExecute($sql, $params);
        return $stmt->rowCount();
    }

    public static function prepareAndExecute(string $sql, array $params = []): PDOStatement
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            // Do not reveal SQL errors to clients
            Response::error('Internal server error', [], 500);
        }
    }

    public static function beginTransaction(): void
    {
        Database::beginTransaction();
    }

    public static function commit(): void
    {
        Database::commit();
    }

    public static function rollback(): void
    {
        Database::rollback();
    }
}
