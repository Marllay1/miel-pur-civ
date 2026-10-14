<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Accès base de données (MariaDB/MySQL en production, SQLite en tests/dev).
 * Toutes les requêtes sont préparées.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }
        return self::$pdo;
    }

    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$depth = 0;
    }

    public static function connect(?array $cfg = null): PDO
    {
        $cfg ??= [
            'driver' => Env::get('DB_DRIVER', 'mysql'),
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::get('DB_PORT', '3306'),
            'database' => Env::get('DB_DATABASE', 'miel_pur'),
            'username' => Env::get('DB_USERNAME', 'root'),
            'password' => Env::get('DB_PASSWORD', ''),
        ];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if ($cfg['driver'] === 'sqlite') {
            $path = $cfg['database'];
            if ($path !== ':memory:' && !str_contains($path, '/') && !str_contains($path, '\\')) {
                $path = BASE_PATH . '/storage/' . $path;
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            return $pdo;
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
        $options[PDO::ATTR_EMULATE_PREPARES] = false;
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'";
        return new PDO($dsn, $cfg['username'], $cfg['password'], $options);
    }

    public static function driver(): string
    {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach (array_values($params) as $i => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($i + 1, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $cols)),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(', ', array_map(static fn ($c) => "`$c` = ?", array_keys($data)));
        return self::run("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
    }

    /**
     * Exécute $fn dans une transaction. Les appels imbriqués réutilisent la transaction englobante.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth > 0) {
            self::$depth++;
            try {
                return $fn();
            } finally {
                self::$depth--;
            }
        }
        $pdo->beginTransaction();
        self::$depth = 1;
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::$depth = 0;
        }
    }

    public static function isUniqueViolation(\Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }
        $info = $e->errorInfo ?? [];
        return ($info[0] ?? '') === '23000'
            && (($info[1] ?? 0) === 1062 || str_contains((string) ($info[2] ?? $e->getMessage()), 'UNIQUE'));
    }
}
