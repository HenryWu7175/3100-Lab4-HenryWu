<?php
declare(strict_types=1);

require __DIR__ . '/shared/Database.php';

$files = glob(__DIR__ . '/migrations/*.sql');
if (!$files) {
    throw new RuntimeException('No database migrations found.');
}

$db = connect_database();
$db->beginTransaction();
try {
    // Serialize concurrent deployment jobs for this database.
    $db->query("SELECT pg_advisory_xact_lock(hashtext('smart-assets-schema-migrations'))");
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version text PRIMARY KEY,
            checksum text NOT NULL,
            applied_at timestamptz NOT NULL DEFAULT now()
        )
        SQL);
    $applied = $db->query('SELECT version, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $record = $db->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
    $newMigrations = [];
    foreach ($files as $file) {
        $version = basename($file);
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Could not read migration $version.");
        }
        $checksum = hash('sha256', str_replace("\r\n", "\n", $sql));
        if (isset($applied[$version])) {
            if ($applied[$version] !== $checksum) {
                throw new RuntimeException("Applied migration $version was changed.");
            }
            continue;
        }
        $db->exec($sql);
        $record->execute([$version, $checksum]);
        $newMigrations[] = $version;
    }

    $runtimeRole = getenv('DB_RUNTIME_ROLE');
    if ($runtimeRole) {
        if (!preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/', $runtimeRole)) {
            throw new RuntimeException('DB_RUNTIME_ROLE must be a lowercase PostgreSQL identifier.');
        }
        $db->exec("GRANT USAGE ON SCHEMA public TO \"$runtimeRole\"");
        $db->exec("GRANT SELECT, INSERT, UPDATE ON TABLE public.assets TO \"$runtimeRole\"");
    }
    $db->commit();
} catch (Throwable $exception) {
    $db->rollBack();
    throw $exception;
}

foreach ($newMigrations as $version) {
    fwrite(STDOUT, "Applied $version\n");
}
if (!$newMigrations) {
    fwrite(STDOUT, "Schema is up to date.\n");
}
