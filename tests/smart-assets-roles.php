<?php
declare(strict_types=1);
require '/var/www/html/shared/Auth.php';
$db = connect_database();
$db->beginTransaction();
try {
    $insert = $db->prepare('INSERT INTO users (email, password_hash, email_verified_at) VALUES (?, ?, now()) RETURNING id');
    $insert->execute(['role-test-' . bin2hex(random_bytes(8)) . '@example.invalid', 'test']);
    $id = $insert->fetchColumn();
    if (user_is_admin($db, $id)) { throw new RuntimeException('New user was granted admin access.'); }
    $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$id]);
    if (!user_is_admin($db, $id)) { throw new RuntimeException('Admin role was not recognized.'); }
    $db->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$id]);
    if (user_is_admin($db, $id)) { throw new RuntimeException('Demoted user retained admin access.'); }
    $db->prepare("UPDATE users SET role = 'admin', email_verified_at = NULL WHERE id = ?")->execute([$id]);
    if (user_is_admin($db, $id)) { throw new RuntimeException('Unverified admin was accepted.'); }
} finally {
    $db->rollBack();
}
echo "Passed admin role, revocation, and email-verification checks.\n";
