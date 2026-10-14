<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Migrations SQL portables : un seul jeu de fichiers, jetons traduits selon le moteur.
 *   {PK}     clé primaire auto-incrémentée
 *   {ID}     type d'une clé étrangère vers {PK}
 *   {ENGINE} options de table (InnoDB/utf8mb4 sous MySQL, rien sous SQLite)
 * Les instructions sont séparées par « ; » suivi d'un retour à la ligne.
 */
final class Migrator
{
    public static function translate(string $sql, string $driver): string
    {
        $map = $driver === 'sqlite'
            ? ['{PK}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{ID}' => 'INTEGER', '{ENGINE}' => '']
            : ['{PK}' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', '{ID}' => 'INT UNSIGNED', '{ENGINE}' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
        return strtr($sql, $map);
    }

    /** @return string[] instructions */
    public static function split(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', str_replace("\r\n", "\n", $sql));
        return array_values(array_filter(array_map('trim', preg_split('/;\s*\n/', $sql . "\n")), static fn ($s) => $s !== ''));
    }

    /** @return string[] migrations appliquées lors de cet appel */
    public static function migrate(?string $dir = null): array
    {
        $dir ??= BASE_PATH . '/database/migrations';
        $driver = DB::driver();
        DB::pdo()->exec(self::translate(
            'CREATE TABLE IF NOT EXISTS migrations (id {PK}, name VARCHAR(190) NOT NULL UNIQUE, applied_at DATETIME NOT NULL) {ENGINE}',
            $driver
        ));
        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $applied = [];
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (self::split(self::translate((string) file_get_contents($file), $driver)) as $statement) {
                DB::pdo()->exec($statement);
            }
            DB::insert('migrations', ['name' => $name, 'applied_at' => gmdate('Y-m-d H:i:s')]);
            $applied[] = $name;
        }
        return $applied;
    }
}
