<?php
// Dependency-free unit runner: php tests/run.php
require_once __DIR__ . '/../www/account_helpers.php';
require_once __DIR__ . '/../www/ui.php';

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$passed = 0;
$failed = 0;
function test(string $name, callable $check): void
{
    global $passed, $failed;
    try {
        $check();
        ++$passed;
        echo "PASS $name\n";
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, "FAIL $name: {$error->getMessage()}\n");
    }
}
function same($expected, $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

// RFC 4226 Appendix D: independently specified HMAC-SHA1 counter vectors.
$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
foreach (['755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871', '520489'] as $counter => $expected) {
    test("Authenticator reference counter $counter", static function () use ($secret, $counter, $expected) {
        same($expected, (new PHPGangsta_GoogleAuthenticator())->getCode($secret, $counter));
    });
}
foreach (['338314', '254676', '287922'] as $code) {
    test("Accept code within clock tolerance: $code", static function () use ($secret, $code) {
        same(true, valid_authenticator_code($secret, $code, 5));
    });
}
foreach (['969429', '162583', '000000', '12345', '1234567', '12a456', "254676\ninvalid", [], null, 254676] as $code) {
    test('Reject expired, incorrect or malformed code: ' . json_encode($code), static function () use ($secret, $code) {
        same(false, valid_authenticator_code($secret, $code, 5));
    });
}
test('Allow surrounding whitespace', static function () use ($secret) {
    same(true, valid_authenticator_code($secret, ' 254676 ', 5));
});
test('Reject missing enrollment secret', static function () {
    same(false, valid_authenticator_code(null, '254676', 5));
    same(false, valid_authenticator_code('', '254676', 5));
});
foreach ([16, 32, 128] as $length) {
    test("Generate valid base32 secret of length $length", static function () use ($length) {
        $value = (new PHPGangsta_GoogleAuthenticator())->createSecret($length);
        same($length, strlen($value));
        same(1, preg_match('/^[A-Z2-7]+$/D', $value));
    });
}
foreach ([15, 129] as $length) {
    test("Reject unsupported secret length $length", static function () use ($length) {
        try {
            (new PHPGangsta_GoogleAuthenticator())->createSecret($length);
        } catch (Exception $error) {
            same('Bad secret length', $error->getMessage());
            return;
        }
        throw new RuntimeException('Expected invalid length to throw');
    });
}
test('Verification URL uses deployment origin', static function () {
    same('https://portal.example/verify_email.php?token=abc', verification_url('https://portal.example', 'abc'));
});
test('Verification URL normalizes trailing slashes and encodes token', static function () {
    same('http://localhost:8080/verify_email.php?token=a%26b%3Dc', verification_url('http://localhost:8080///', 'a&b=c'));
});
test('Shared layout escapes titles and supports mobile navigation', static function () {
    $_SESSION = [];
    $_SERVER['SCRIPT_NAME'] = '/dashboard.php';
    ob_start();
    page_start('<script>alert(1)</script>');
    page_end();
    $html = ob_get_clean();
    same(false, str_contains($html, '<script>alert(1)</script>'));
    foreach (['&lt;script&gt;', 'name="viewport"', 'navbar-toggler', 'aria-current="page"', 'href="login.php">Sign in', '</html>'] as $expected) {
        same(true, str_contains($html, $expected));
    }
});
test('Signed-in navigation offers sign out', static function () {
    $_SESSION = ['userID' => 1];
    ob_start();
    page_start('Dashboard', true);
    page_end();
    $html = ob_get_clean();
    same(true, str_contains($html, 'href="logout.php">Sign out'));
    same(true, str_contains($html, 'workspace-card'));
});

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
