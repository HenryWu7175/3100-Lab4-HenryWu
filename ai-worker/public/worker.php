<?php
declare(strict_types=1);

require dirname(__DIR__) . '/shared/Database.php';
require dirname(__DIR__) . '/shared/StorageAdapter.php';
require dirname(__DIR__) . '/AssetProcessor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    return;
}

$bucket = getenv('GCS_BUCKET_NAME');
if (!$bucket) {
    http_response_code(503);
    return;
}

$envelope = json_decode(file_get_contents('php://input', false, null, 0, 65537), true);
$message = $envelope['message'] ?? null;
$attributes = $message['attributes'] ?? null;
$encoded = $message['data'] ?? null;
$decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
$object = $decoded === false ? null : json_decode($decoded, true);
$name = $object['name'] ?? null;
$objectId = '/\A([0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})_[a-zA-Z0-9._-]{1,120}\z/';

if (($attributes['eventType'] ?? null) !== 'OBJECT_FINALIZE'
    || ($attributes['bucketId'] ?? null) !== $bucket
    || ($object['bucket'] ?? null) !== $bucket
    || !is_string($name)
    || ($attributes['objectId'] ?? null) !== $name
    || !preg_match($objectId, $name, $match)
) {
    http_response_code(200);
    return;
}

try {
    $db = connect_database();
    $uri = 'gs://' . $bucket . '/' . $name;
    $claim = $db->prepare(<<<'SQL'
        UPDATE assets
        SET status = 'PROCESSING', claim_token = gen_random_uuid(), error_message = NULL,
            claimed_until = now() + interval '5 minutes'
        WHERE id = :id AND storage_uri = :uri AND (
            status = 'PENDING'
            OR (status = 'FAILED' AND error_message = :upload_error)
            OR (status = 'PROCESSING' AND claimed_until <= now())
        )
        RETURNING id, mime_type, storage_uri, claim_token
        SQL);
    $claim->execute(['id' => $match[1], 'uri' => $uri, 'upload_error' => StorageAdapter::CLOUD_UPLOAD_FAILED]);
    $asset = $claim->fetch();
    if ($asset) {
        fwrite_log('Claimed ' . $asset['id']);
        http_response_code(complete_asset($db, $asset) ? 200 : 503);
        return;
    }

    $state = $db->prepare('SELECT status FROM assets WHERE id = ? AND storage_uri = ?');
    $state->execute([$match[1], $uri]);
    http_response_code($state->fetchColumn() === 'PROCESSING' ? 503 : 200);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    if ($exception instanceof \Google\ApiCore\ApiException
        && in_array($exception->getStatus(), ['RESOURCE_EXHAUSTED', 'UNAVAILABLE', 'DEADLINE_EXCEEDED'], true)
        && !empty($asset)
    ) {
        try {
            $release = $db->prepare("UPDATE assets SET status = 'PENDING', claim_token = NULL, claimed_until = NULL
                                     WHERE id = ? AND claim_token = ?");
            $release->execute([$asset['id'], $asset['claim_token']]);
        } catch (PDOException $releaseError) {
            error_log($releaseError->getMessage());
        }
        http_response_code(503);
    } else {
        http_response_code(500);
    }
}
