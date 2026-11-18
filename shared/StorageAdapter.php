<?php
declare(strict_types=1);

use Google\Cloud\Storage\StorageClient;

final class StorageAdapter
{
    private const LOCAL_DIRECTORY = '/var/www/html/storage/uploads/';
    private readonly string $bucket;

    public function __construct()
    {
        $mode = getenv('ENV_MODE') ?: 'LOCAL';
        $this->bucket = match ($mode) {
            'LOCAL' => '',
            'CLOUD' => getenv('GCS_BUCKET_NAME') ?: throw new RuntimeException('GCS_BUCKET_NAME is required.'),
            default => throw new RuntimeException('ENV_MODE must be LOCAL or CLOUD.'),
        };
    }

    public function uri(string $id, string $filename): string
    {
        $safeName = substr(preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename), 0, 120);
        $object = $id . '_' . ($safeName ?: 'upload');

        return $this->bucket === ''
            ? self::LOCAL_DIRECTORY . $object
            : 'gs://' . $this->bucket . '/' . $object;
    }

    public function store(string $source, string $uri, string $mime): void
    {
        if ($this->bucket === '') {
            if (!move_uploaded_file($source, $uri)) {
                throw new RuntimeException('Could not store the local upload.');
            }
            return;
        }

        require_once dirname(__DIR__) . '/vendor/autoload.php';
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not open the upload.');
        }
        try {
            $client = new StorageClient();
            $client->bucket($this->bucket)->upload($stream, [
                'name' => substr($uri, strlen('gs://' . $this->bucket . '/')),
                'metadata' => ['contentType' => $mime],
                'ifGenerationMatch' => 0,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
