<?php
declare(strict_types=1);

require dirname(__DIR__) . '/shared/Auth.php';
$db = connect_database();
start_auth_session($db);
require_admin($db);
header('Cache-Control: no-store');
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = $_POST['email'] ?? '';
    if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter a valid email address.';
    } else {
        $query = $db->prepare("UPDATE users SET role = 'admin' WHERE lower(email) = lower(?) AND email_verified_at IS NOT NULL AND role = 'user'");
        $query->execute([trim($email)]);
        $message = $query->rowCount() === 1 ? 'Administrator access granted.' : 'No eligible account found. The user must register and verify their email first and must not already be an admin.';
    }
}
$users = $db->query('SELECT id, email, role, email_verified_at, totp_confirmed_at, created_at, last_login_at, last_logout_at FROM users ORDER BY created_at DESC')->fetchAll();
function admin_escape(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
require __DIR__ . '/layout.php';
page_start('Admin console', true, true);
?>
<section class="card border-0 shadow-sm workspace-card"><div class="card-body p-4 p-md-5">
    <div class="header"><div><h1>Account administration</h1><p>Account overview: registration, verification, and sign-in activity.</p></div><a href="admin.php" class="btn btn-outline-primary">Refresh</a></div>
    <h3>Grant administrator access</h3>
    <p>New administrators must register normally and verify their email before you promote them.</p>
    <?php if ($message !== ''): ?><div class="alert alert-info" role="status"><?= admin_escape($message) ?></div><?php endif; ?>
    <form method="post" class="bg-light border rounded-3 p-3 mb-4">
        <input type="hidden" name="csrf" value="<?= admin_escape($_SESSION['csrf']) ?>">
        <label for="admin-email" class="form-label">Registered user's email</label>
        <input id="admin-email" type="email" name="email" required class="form-control mb-3">
        <button type="submit" class="btn btn-primary">Grant admin access</button>
    </form>
    <h3>Account registry</h3>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>
        <th scope="col">User ID</th><th scope="col">Email address</th><th scope="col">Role</th><th scope="col">Email</th><th scope="col">2FA setup</th><th scope="col">Registered</th><th scope="col">Last login</th><th scope="col">Last logout</th>
    </tr></thead><tbody>
    <?php foreach ($users as $user): ?><tr>
        <td><code><?= admin_escape($user['id']) ?></code></td><td><?= admin_escape($user['email']) ?></td><td><?= admin_escape($user['role']) ?></td>
        <td><span class="status-badge <?= $user['email_verified_at'] ? 'status-online' : 'status-offline' ?>"><?= $user['email_verified_at'] ? 'Verified' : 'Pending' ?></span></td>
        <td><?= $user['totp_confirmed_at'] ? 'Configured' : 'Inactive' ?></td><td><?= admin_escape($user['created_at']) ?></td><td><?= admin_escape($user['last_login_at'] ?? 'Never') ?></td><td><?= admin_escape($user['last_logout_at'] ?? 'Never') ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="8">No accounts found.</td></tr><?php endif; ?>
    </tbody></table></div>
</div></section><?php page_end(); ?>
