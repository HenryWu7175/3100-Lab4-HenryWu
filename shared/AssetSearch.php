<?php
declare(strict_types=1);

function search_assets(PDO $db, string $vector, bool $textOnly, string $userId): array
{
    $column = $textOnly ? 'text_embedding' : 'multimodal_embedding';
    $statement = $db->prepare("
        SELECT id, filename, mime_type, status, extracted_metadata,
            $column <=> CAST(:vector AS vector) AS distance
        FROM assets
        WHERE user_id = :user_id AND status = 'COMPLETED' AND $column IS NOT NULL
        ORDER BY distance
        LIMIT 25
    ");
    $statement->execute(['vector' => $vector, 'user_id' => $userId]);
    $results = $statement->fetchAll();
    foreach ($results as &$asset) {
        $asset['similarity'] = 1.0 - (float) $asset['distance'];
        $asset['extracted_metadata'] = json_decode($asset['extracted_metadata'] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
        unset($asset['distance']);
    }
    return $results;
}
