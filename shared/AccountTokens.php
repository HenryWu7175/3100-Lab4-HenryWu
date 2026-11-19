<?php
declare(strict_types=1);

/** Issue a single-use, time-limited email verification or password reset token. */
function issue_account_token(PDO $db, string $userId, string $purpose): string
{
    [$hashColumn, $expiryColumn, $lifetime] = match ($purpose) {
        'verify' => ['verification_token_hash', 'verification_expires_at', '24 hours'],
        'reset' => ['reset_token_hash', 'reset_expires_at', '1 hour'],
        default => throw new InvalidArgumentException('Unknown token purpose.'),
    };
    $token = bin2hex(random_bytes(32));
    $query = $db->prepare("UPDATE users SET $hashColumn = ?, $expiryColumn = now() + CAST(? AS interval) WHERE id = ?");
    $query->execute([hash('sha256', $token), $lifetime, $userId]);
    if ($query->rowCount() !== 1) {
        throw new RuntimeException('Account does not exist.');
    }
    return $token;
}

function verify_account_email(PDO $db, string $token): bool
{
    if (!preg_match('/\A[0-9a-f]{64}\z/', $token)) {
        return false;
    }
    $query = $db->prepare(<<<'SQL'
        UPDATE users SET email_verified_at = now(), verification_token_hash = NULL,
            verification_expires_at = NULL
        WHERE verification_token_hash = ? AND verification_expires_at > now()
            AND email_verified_at IS NULL
        SQL);
    $query->execute([hash('sha256', $token)]);
    return $query->rowCount() === 1;
}

function reset_account_password(PDO $db, string $token, string $password): bool
{
    if (!preg_match('/\A[0-9a-f]{64}\z/', $token) || strlen($password) < 12 || strlen($password) > 4096) {
        return false;
    }
    $query = $db->prepare(<<<'SQL'
        UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL
        WHERE reset_token_hash = ? AND reset_expires_at > now() AND email_verified_at IS NOT NULL
        SQL);
    $query->execute([password_hash($password, PASSWORD_DEFAULT), hash('sha256', $token)]);
    return $query->rowCount() === 1;
}
