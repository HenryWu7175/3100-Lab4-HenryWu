<?php
declare(strict_types=1);

function mock_asset_metadata(string $mimeType): ?array
{
    return match ($mimeType) {
        'image/jpeg', 'image/png' => ['type' => 'image', 'tags' => ['sample', 'image']],
        'application/pdf' => ['type' => 'document', 'summary' => 'Sample document summary.'],
        'audio/mpeg', 'audio/wav' => ['type' => 'audio', 'transcript' => 'Sample audio transcript.'],
        default => null,
    };
}

function complete_asset(PDO $db, array $asset, ?string $error = null): bool
{
    $metadata = mock_asset_metadata($asset['mime_type']);
    if ($metadata === null) {
        $error ??= 'Unsupported media type.';
    }

    $finish = $db->prepare(<<<'SQL'
        UPDATE assets
        SET status = :status, extracted_metadata = :metadata, error_message = :error,
            claim_token = NULL, claimed_until = NULL
        WHERE id = :id AND status = 'PROCESSING' AND claim_token = :token
        SQL);
    $finish->execute([
        'status' => $error === null ? 'COMPLETED' : 'FAILED',
        'metadata' => $error === null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        'error' => $error,
        'id' => $asset['id'],
        'token' => $asset['claim_token'],
    ]);
    if ($finish->rowCount() !== 1) {
        return false;
    }
    fwrite_log(($error === null ? 'Completed ' : 'Failed ') . $asset['id']);
    return true;
}

function fwrite_log(string $message): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, $message . PHP_EOL);
        return;
    }
    error_log($message);
}
