<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DatabaseSessionHandler.php';

function start_auth_session(PDO $db): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = getenv('SESSION_SECURE') === 'true';
    if (getenv('ENV_MODE') === 'CLOUD' && !$secure) {
        throw new RuntimeException('Cloud sessions require SESSION_SECURE=true.');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('smart_assets_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_set_save_handler(new DatabaseSessionHandler($db), true);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function require_user(bool $json = false): string
{
    $user = $_SESSION['user_id'] ?? null;
    if (!is_string($user) || $user === '') {
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Please sign in.']);
        } else {
            header('Location: login.php');
        }
        exit;
    }
    return $user;
}

function require_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function user_is_admin(PDO $db, string $userId): bool
{
    $query = $db->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'admin' AND email_verified_at IS NOT NULL");
    $query->execute([$userId]);
    return $query->fetchColumn() !== false;
}

function require_admin(PDO $db): string
{
    $userId = require_user();
    if (!user_is_admin($db, $userId)) {
        http_response_code(403);
        exit('Access denied. Administrators only.');
    }
    return $userId;
}
