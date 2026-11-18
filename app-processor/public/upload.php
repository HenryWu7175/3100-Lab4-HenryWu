<?php
declare(strict_types=1);

require dirname(__DIR__) . '/shared/Database.php';
header('Content-Type: application/json');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to upload a file.']);
}

$file = $_FILES['asset'] ?? null;
$requestSize = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($requestSize > 11 * 1024 * 1024 || ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE) {
    respond(413, ['error' => 'The upload exceeds 10 MiB.']);
}
if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    respond(400, ['error' => 'Choose a valid file to upload.']);
}
if ($file['size'] === 0 || $file['size'] > 10 * 1024 * 1024) {
    respond(400, ['error' => 'Choose a nonempty file no larger than 10 MiB.']);
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$mime = $mime === 'audio/x-wav' ? 'audio/wav' : $mime;
if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf', 'audio/mpeg', 'audio/wav'], true)) {
    respond(415, ['error' => 'Supported formats are JPEG, PNG, PDF, MP3, and WAV.']);
}

$stored = false;
try {
    $db = connect_database();
    $id = $db->query('SELECT gen_random_uuid()')->fetchColumn();
    $path = dirname(__DIR__) . '/storage/uploads/' . $id;
    $stored = move_uploaded_file($file['tmp_name'], $path);
    if (!$stored) {
        throw new RuntimeException('Could not store upload.');
    }
    $insert = $db->prepare(
        'INSERT INTO assets (id, filename, mime_type, storage_uri) VALUES (?, ?, ?, ?) RETURNING status'
    );
    $filename = mb_scrub(str_replace("\0", '', $file['name']), 'UTF-8');
    $insert->execute([$id, $filename, $mime, $path]);
    respond(202, ['id' => $id, 'status' => $insert->fetchColumn()]);
} catch (Throwable $exception) {
    // A lost database connection can leave the INSERT outcome unknown.
    // Retain its file so a committed asset can still be processed.
    if ($stored && $exception instanceof PDOException && str_starts_with((string) $exception->getCode(), '22')) {
        unlink($path);
    }
    error_log($exception->getMessage());
    respond(500, ['error' => 'The upload could not be queued.']);
}
