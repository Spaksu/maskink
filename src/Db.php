<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = mk_config();
            if ($cfg === null) {
                throw new RuntimeException(t('Not configured (config.php is missing).'));
            }
            self::$pdo = self::connect($cfg['db'], true);
        }
        return self::$pdo;
    }

    public static function connect(array $c, bool $withDb): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4%s',
            $c['host'],
            (int)($c['port'] ?? 3306),
            $withDb ? ';dbname=' . $c['name'] : ''
        );
        return new PDO($dsn, (string)$c['user'], (string)$c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function all(string $sql, array $p = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $p = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function exec(string $sql, array $p = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st->rowCount();
    }

    public static function lastId(): int
    {
        return (int)self::pdo()->lastInsertId();
    }
}
