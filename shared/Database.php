<?php
declare(strict_types=1);

function connect_database(): PDO
{
    $host = getenv('DB_HOST');
    $port = str_starts_with($host, '/') ? '' : ';port=' . (getenv('DB_PORT') ?: '5432');
    return new PDO(
        sprintf('pgsql:host=%s;dbname=%s%s', $host, getenv('DB_NAME'), $port),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
