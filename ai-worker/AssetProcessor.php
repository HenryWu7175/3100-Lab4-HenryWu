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
    $textEmbedding = null;
    $multimodalEmbedding = null;
    if ($metadata === null) {
        $error ??= 'Unsupported media type.';
    }
    if ($error === null && getenv('AI_MODE') === 'VERTEX') {
        require_once __DIR__ . '/shared/VertexAi.php';
        try {
            $vertex = new VertexAi();
            $metadata = $vertex->extract($asset['storage_uri'], $asset['mime_type'], $metadata['type']);
            $text = trim(implode("\n", [
                $metadata['summary'],
                implode(', ', $metadata['tags'] ?? []),
                $metadata['transcript'] ?? '',
            ]));
            $textEmbedding = $vertex->textEmbedding($text);
            $multimodalEmbedding = $metadata['type'] === 'image'
                ? $vertex->imageEmbedding(['gcsUri' => $asset['storage_uri'], 'mimeType' => $asset['mime_type']])
                : $vertex->multimodalTextEmbedding($metadata['summary']);
        } catch (UnexpectedValueException $exception) {
            error_log($exception->getMessage());
            $error = 'Vertex AI returned incomplete or invalid results.';
        } catch (\Google\ApiCore\ApiException $exception) {
            if ($exception->getStatus() !== 'INVALID_ARGUMENT') {
                throw $exception;
            }
            error_log($exception->getMessage());
            $error = 'Vertex AI rejected the uploaded media.';
        }
    }

    $finish = $db->prepare(<<<'SQL'
        UPDATE assets
        SET status = :status, extracted_metadata = :metadata, error_message = :error,
            text_embedding = :text_embedding, multimodal_embedding = :multimodal_embedding,
            claim_token = NULL, claimed_until = NULL
        WHERE id = :id AND status = 'PROCESSING' AND claim_token = :token
        SQL);
    $finish->execute([
        'status' => $error === null ? 'COMPLETED' : 'FAILED',
        'metadata' => $error === null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        'error' => $error,
        'text_embedding' => $error === null ? $textEmbedding : null,
        'multimodal_embedding' => $error === null ? $multimodalEmbedding : null,
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
