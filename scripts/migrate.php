<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(100) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$migrationDir = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

foreach ($files as $file) {
    $version = basename($file);
    $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
    $check->execute([$version]);
    if ($check->fetchColumn()) {
        echo "skip {$version}\n";
        continue;
    }

    $sql = (string) file_get_contents($file);
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        $record = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $record->execute([$version]);
        echo "applied {$version}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "failed {$version}: {$e->getMessage()}\n");
        exit(1);
    }
}
