<?php
declare(strict_types=1);

require dirname(__DIR__) . '/shared/Auth.php';
require dirname(__DIR__) . '/shared/AssetSearch.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$db = connect_database();
start_auth_session($db);
$userId = require_user(true);

function search_response(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    search_response(405, ['error' => 'Use POST to search assets.']);
}
require_csrf();
session_write_close();
if (getenv('AI_MODE') !== 'VERTEX') {
    search_response(503, ['error' => 'Search requires Vertex AI mode.']);
}

$query = $_POST['q'] ?? '';
$mode = $_POST['mode'] ?? 'multimodal';
$image = $_FILES['query_image'] ?? null;
$hasImage = $image !== null && $image['error'] !== UPLOAD_ERR_NO_FILE;
$textOnly = $mode === 'text';

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 11 * 1024 * 1024) {
    search_response(413, ['error' => 'The query image exceeds 10 MiB.']);
}
if (!is_string($query) || !mb_check_encoding($query, 'UTF-8') || !in_array($mode, ['multimodal', 'text'], true)) {
    search_response(400, ['error' => 'Invalid search parameters.']);
}
$query = trim($query);
if ((!$hasImage && $query === '') || ($hasImage && ($query !== '' || $textOnly))) {
    search_response(400, ['error' => 'Enter search text or choose one image in cross-modal mode.']);
}
if (mb_strlen($query) > 1000) {
    search_response(400, ['error' => 'Search text is limited to 1,000 characters.']);
}
if ($hasImage) {
    if ($image['error'] === UPLOAD_ERR_INI_SIZE || $image['size'] > 10 * 1024 * 1024) {
        search_response(413, ['error' => 'The query image exceeds 10 MiB.']);
    }
    if ($image['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($image['tmp_name']) || $image['size'] === 0) {
        search_response(400, ['error' => 'Choose a valid query image.']);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($image['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        search_response(415, ['error' => 'Choose a JPEG or PNG query image.']);
    }
}

try {
    require_once dirname(__DIR__) . '/shared/VertexAi.php';
    $vertex = new VertexAi();
    if ($hasImage) {
        $bytes = file_get_contents($image['tmp_name']);
        if ($bytes === false) {
            throw new RuntimeException('Could not read the query upload.');
        }
        $vector = $vertex->imageEmbedding(['bytesBase64Encoded' => base64_encode($bytes), 'mimeType' => $mime]);
    } else {
        $vector = $textOnly ? $vertex->textEmbedding($query, true) : $vertex->multimodalTextEmbedding($query);
    }
    search_response(200, search_assets($db, $vector, $textOnly, $userId));
} catch (\Google\ApiCore\ApiException $exception) {
    error_log($exception->getMessage());
    [$status, $message] = match ($exception->getStatus()) {
        'RESOURCE_EXHAUSTED', 'UNAVAILABLE', 'DEADLINE_EXCEEDED' => [503, 'Search is temporarily unavailable. Try again shortly.'],
        'INVALID_ARGUMENT' => [422, 'Vertex AI could not embed this query.'],
        default => [502, 'Vertex AI search failed.'],
    };
    search_response($status, ['error' => $message]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    search_response(500, ['error' => 'Search is unavailable.']);
}
