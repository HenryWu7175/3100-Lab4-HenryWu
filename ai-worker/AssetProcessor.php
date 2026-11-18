<?php
declare(strict_types=1);

function mock_asset_metadata(string $mimeType): array
{
    return match ($mimeType) {
        'image/jpeg', 'image/png' => ['type' => 'image', 'tags' => ['sample', 'image']],
        'application/pdf' => ['type' => 'document', 'summary' => 'Sample document summary.'],
        'audio/mpeg', 'audio/wav' => ['type' => 'audio', 'transcript' => 'Sample audio transcript.'],
    };
}
