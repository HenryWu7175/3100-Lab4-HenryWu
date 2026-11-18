<?php
declare(strict_types=1);

require __DIR__ . '/shared/Database.php';
require __DIR__ . '/AssetProcessor.php';

$db = connect_database();
$finish = $db->prepare(<<<'SQL'
    UPDATE assets
    SET status = :status, extracted_metadata = :metadata, error_message = :error,
        claim_token = NULL, claimed_until = NULL
    WHERE id = :id AND status = 'PROCESSING' AND claim_token = :token
    SQL);

while (true) {
    $asset = $db->query(<<<'SQL'
        UPDATE assets
        SET status = 'PROCESSING', claim_token = gen_random_uuid(),
            claimed_until = now() + interval '5 minutes'
        WHERE id = (
            SELECT id FROM assets
            WHERE status = 'PENDING' OR (status = 'PROCESSING' AND claimed_until <= now())
            ORDER BY created_at, id
            LIMIT 1 FOR UPDATE SKIP LOCKED
        )
        RETURNING id, mime_type, storage_uri, claim_token
        SQL)->fetch();

    if (!$asset) {
        sleep(1);
        continue;
    }

    fwrite(STDOUT, "Claimed {$asset['id']}\n");
    // Leave the mock job visible long enough to observe it in the dashboard.
    sleep(2);
    $readable = is_readable($asset['storage_uri']) && filesize($asset['storage_uri']) > 0;
    $finish->execute([
        'status' => $readable ? 'COMPLETED' : 'FAILED',
        'metadata' => $readable ? json_encode(mock_asset_metadata($asset['mime_type']), JSON_THROW_ON_ERROR) : null,
        'error' => $readable ? null : 'The stored upload could not be read.',
        'id' => $asset['id'],
        'token' => $asset['claim_token'],
    ]);
    if ($finish->rowCount() === 1) {
        fwrite(STDOUT, ($readable ? 'Completed ' : 'Failed ') . $asset['id'] . PHP_EOL);
    }
}
