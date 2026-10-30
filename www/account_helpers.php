<?php

require_once __DIR__ . '/GoogleAuthenticator.php';

function verification_url(string $baseUrl, string $token): string
{
    return rtrim($baseUrl, '/') . '/verify_email.php?token=' . urlencode($token);
}

function valid_authenticator_code(?string $secret, $submittedCode, ?int $timeSlice = null): bool
{
    if (empty($secret) || !is_string($submittedCode)) {
        return false;
    }
    $code = trim($submittedCode);
    return preg_match('/^[0-9]{6}$/D', $code) === 1
        && (new PHPGangsta_GoogleAuthenticator())->verifyCode($secret, $code, 1, $timeSlice);
}
