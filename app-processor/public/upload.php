<?php
declare(strict_types=1);

require dirname(__DIR__) . '/shared/Database.php';
require dirname(__DIR__) . '/shared/StorageAdapter.php';
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

try {
    $db = connect_database();
    $storage = new StorageAdapter();
    $id = $db->query('SELECT gen_random_uuid()')->fetchColumn();
    $filename = mb_scrub(str_replace("\0", '', $file['name']), 'UTF-8');
    $uri = $storage->uri($id, $filename);
    $insert = $db->prepare(
        'INSERT INTO assets (id, filename, mime_type, storage_uri) VALUES (?, ?, ?, ?)'
    );
    $cloud = str_starts_with($uri, 'gs://');
    if ($cloud) {
        $insert->execute([$id, $filename, $mime, $uri]);
    }
    try {
        $storage->store($file['tmp_name'], $uri, $mime);
    } catch (Throwable $exception) {
        if ($cloud) {
            $failed = $db->prepare(
                "UPDATE assets SET status = 'FAILED', error_message = ?
                 WHERE id = ? AND status = 'PENDING'"
            );
            $failed->execute([StorageAdapter::CLOUD_UPLOAD_FAILED, $id]);
        }
        throw $exception;
    }
    if (!$cloud) {
        $insert->execute([$id, $filename, $mime, $uri]);
    }
    respond(202, ['id' => $id, 'status' => 'PENDING']);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    respond(500, ['error' => 'The upload could not be queued.']);
}
