<?php
declare(strict_types=1);

use Google\Auth\Credentials\InsecureCredentials;
use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;

require '/var/www/html/shared/Database.php';
require '/var/www/html/shared/AssetSearch.php';
require '/var/www/html/shared/VertexAi.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('GCP_PROJECT_ID=phase3-test');
$vertex = new VertexAi();
$requests = [];
$rateLimit = true;
$handler = static function ($request) use (&$requests, &$rateLimit) {
    $path = $request->getUri()->getPath();
    $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    $requests[] = [$path, $body];
    if (str_ends_with($path, ':generateContent')) {
        if ($rateLimit) {
            $rateLimit = false;
            $response = new Response(429, [], '{"error":{"code":429,"status":"RESOURCE_EXHAUSTED","message":"Simulated rate limit"}}');
            return Create::rejectionFor(new ClientException('Simulated rate limit', $request, $response));
        }
        $mime = $body['contents'][0]['parts'][1]['fileData']['mimeType'];
        $metadata = match ($mime) {
            'image/jpeg' => ['summary' => 'A red bicycle.', 'tags' => ['bicycle']],
            'application/pdf' => ['summary' => 'A bicycle maintenance guide.'],
            'audio/wav' => ['summary' => 'Bicycle maintenance instructions.', 'transcript' => 'Check the brakes.'],
            default => ['summary' => 'Missing required tags'],
        };
        $result = ['candidates' => [['finishReason' => 'STOP', 'content' => [
            'parts' => [['text' => json_encode($metadata, JSON_THROW_ON_ERROR)]],
        ]]]];
    } else {
        $instance = $body['instances'][0];
        if (str_contains($path, 'gemini-embedding-001')) {
            $result = ['predictions' => [['embeddings' => ['values' => unit_vector(768, 0)]]]];
        } else {
            $key = isset($instance['image']) ? 'imageEmbedding' : 'textEmbedding';
            $result = ['predictions' => [[$key => unit_vector(1408, 0)]]];
        }
    }
    return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode($result, JSON_THROW_ON_ERROR)));
};

function unit_vector(int $dimensions, int $axis): array
{
    $vector = array_fill(0, $dimensions, 0);
    $vector[$axis] = 1;
    return $vector;
}

$client = new PredictionServiceClient([
    'transport' => 'rest',
    'credentials' => new InsecureCredentials(),
    'transportConfig' => ['rest' => ['httpHandler' => $handler]],
]);
$clients = new ReflectionProperty(VertexAi::class, 'clients');
$clients->setValue($vertex, ['global' => $client, 'us-central1' => $client]);

$uri = 'gs://phase3-test/bicycle.jpg';
check($vertex->extract($uri, 'image/jpeg', 'image')['tags'] === ['bicycle'], 'Image tags were not parsed.');
check(count($requests) === 2, 'A single HTTP 429 should trigger one retry.');
check($requests[0][1]['generationConfig']['responseMimeType'] === 'application/json', 'JSON output was not requested.');
check($requests[0][1]['contents'][0]['parts'][1]['fileData']['fileUri'] === $uri, 'Gemini did not receive the GCS URI.');
check($vertex->extract('gs://phase3-test/guide.pdf', 'application/pdf', 'document')['summary'] === 'A bicycle maintenance guide.', 'PDF summary was not parsed.');
check($vertex->extract('gs://phase3-test/guide.wav', 'audio/wav', 'audio')['transcript'] === 'Check the brakes.', 'Audio transcript was not parsed.');
try {
    $vertex->extract('gs://phase3-test/invalid.png', 'image/png', 'image');
    throw new RuntimeException('Invalid model output was accepted.');
} catch (UnexpectedValueException) {
}
$textVector = $vertex->textEmbedding('bicycle', true);
[$path, $body] = end($requests);
check(str_contains($path, '/locations/us-central1/'), 'Embedding used the wrong location.');
check($body['instances'][0]['task_type'] === 'RETRIEVAL_QUERY', 'Text query task type was not set.');
check($body['parameters']['outputDimensionality'] === 768, 'Text output dimension was not set.');
$multimodal = $vertex->multimodalTextEmbedding('bicycle');
$image = $vertex->imageEmbedding(['bytesBase64Encoded' => 'YmxvYg==', 'mimeType' => 'image/jpeg']);
check($multimodal === $image, 'Mock image and text did not produce matching vectors.');
check(end($requests)[1]['instances'][0]['image']['bytesBase64Encoded'] === 'YmxvYg==', 'Query image bytes were not sent.');

$db = connect_database();
$db->beginTransaction();
try {
    $userId = $db->query("INSERT INTO users (email, password_hash) VALUES ('phase3-test@example.invalid', 'test') RETURNING id")->fetchColumn();
    $insert = $db->prepare(<<<'SQL'
        INSERT INTO assets (id, filename, mime_type, storage_uri, status, extracted_metadata, text_embedding, multimodal_embedding, user_id)
        VALUES (gen_random_uuid(), ?, 'application/pdf', 'gs://phase3-test/test.pdf', ?, '{"type":"document"}', ?, ?, ?)
        RETURNING id
        SQL);
    $insert->execute(['Bicycle guide', 'COMPLETED', $textVector, $multimodal, $userId]);
    $match = $insert->fetchColumn();
    $insert->execute(['Weather report', 'COMPLETED', json_encode(unit_vector(768, 1)), json_encode(unit_vector(1408, 1)), $userId]);
    $other = $insert->fetchColumn();
    $insert->execute(['Pending bicycle guide', 'PENDING', $textVector, $multimodal, $userId]);
    $pending = $insert->fetchColumn();
    foreach ([[$textVector, true], [$multimodal, false], [$image, false]] as [$vector, $textOnly]) {
        $rows = array_values(array_filter(search_assets($db, $vector, $textOnly, $userId),
            static fn (array $row): bool => in_array($row['id'], [$match, $other, $pending], true)));
        check(array_column($rows, 'id') === [$match, $other], 'Search ordering or completed-asset filtering failed.');
        check($rows[0]['similarity'] === 1.0 && $rows[1]['similarity'] === 0.0, 'Cosine similarity is incorrect.');
    }
} finally {
    $db->rollBack();
}

echo "Passed SDK serialization, HTTP 429 retry, metadata validation, and PostgreSQL ranking for all three query modes.\n";
