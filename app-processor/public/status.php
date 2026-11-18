<?php
declare(strict_types=1);

require dirname(__DIR__) . '/shared/Database.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $assets = connect_database()->query(<<<'SQL'
        SELECT id, filename, mime_type, status, extracted_metadata, error_message
        FROM assets ORDER BY created_at DESC, id DESC LIMIT 50
        SQL)->fetchAll();
    foreach ($assets as &$asset) {
        $asset['extracted_metadata'] = json_decode($asset['extracted_metadata'] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
    }
    echo json_encode($assets, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo '{"error":"Asset status is unavailable."}';
}
