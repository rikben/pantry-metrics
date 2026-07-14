<?php
// /database/migrate.php

declare(strict_types=1);

require dirname(__DIR__) . '/public_html/app/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$migrationDirectory = __DIR__ . '/migrations';
$migrations = glob($migrationDirectory . '/*.sql') ?: [];
sort($migrations, SORT_NATURAL);

$appliedStatement = $pdo->query('SELECT version FROM schema_migrations');
$applied = array_flip($appliedStatement->fetchAll(PDO::FETCH_COLUMN));

foreach ($migrations as $migration) {
    $version = basename($migration);

    if (isset($applied[$version])) {
        echo "Skipped {$version}\n";
        continue;
    }

    $sql = file_get_contents($migration);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration: {$migration}");
    }

    echo "Applying {$version}...\n";

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);

        $statement = $pdo->prepare(
            'INSERT IGNORE INTO schema_migrations (version) VALUES (:version)'
        );
        $statement->execute(['version' => $version]);

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Migrations complete.\n";
