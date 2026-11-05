<?php
declare(strict_types=1);

namespace flight\commands;

use PDO;
use Throwable;

class MigrateCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('migrate', 'Aplica las migraciones SQL pendientes', $config);
    }

    public function execute(): void
    {
        $io   = $this->app()->io();
        $root = dirname(__DIR__, 2);

        $route = getenv('DB_PATH') ?: $root . '/storage/app.sqlite';
        if (!is_dir(dirname($route))) {
            mkdir(dirname($route), 0750, true);
        }

        $pdo = new PDO('sqlite:' . $route, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                nombre      TEXT PRIMARY KEY,
                aplicada_en TEXT NOT NULL DEFAULT (datetime('now'))
            )"
        );

        $applied = $pdo->query('SELECT nombre FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($root . '/database/migrations/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $io->info("Aplicando $name...", true);
            $pdo->exec('BEGIN');
            try {
                $pdo->exec(file_get_contents($file));
                $pdo->prepare('INSERT INTO schema_migrations (nombre) VALUES (?)')
                    ->execute([$name]);
                $pdo->exec('COMMIT');
            } catch (Throwable $e) {
                $pdo->exec('ROLLBACK');
                $io->error('Falló ' . $name . ': ' . $e->getMessage(), true);
                exit(1);
            }
        }

        $io->ok('Migraciones al día.', true);
    }
}