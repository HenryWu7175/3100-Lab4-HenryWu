<?php
declare(strict_types=1);
require dirname(__DIR__) . '/shared/Auth.php';
$db = connect_database();
start_auth_session($db);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();
if (isset($_SESSION['user_id']) && is_string($_SESSION['user_id'])) {
    $db->prepare('UPDATE users SET last_logout_at = now() WHERE id = ?')->execute([$_SESSION['user_id']]);
}
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', ['expires' => 1, 'path' => '/', 'secure' => getenv('SESSION_SECURE') === 'true', 'httponly' => true, 'samesite' => 'Lax']);
header('Location: login.php');
