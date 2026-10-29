<?php
function page_start(string $title, bool $wide = false): void
{
    $signedIn = isset($_SESSION['userID']);
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $escape($title) ?> · Lab Portal</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link href="assets/app.css" rel="stylesheet">
    </head>
    <body>
    <a class="visually-hidden-focusable skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-md bg-white border-bottom" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><span class="brand-mark">L4</span> Lab Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="main-nav">
                <div class="navbar-nav ms-auto gap-md-3">
                    <?php foreach (['index.php' => 'Home', 'dashboard.php' => 'Dashboard', 'admin.php' => 'Admin'] as $url => $label): ?>
                        <a class="nav-link <?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>" <?= $current === $url ? 'aria-current="page"' : '' ?>><?= $label ?></a>
                    <?php endforeach; ?>
                    <a class="nav-link" href="<?= $signedIn ? 'logout.php' : 'login.php' ?>"><?= $signedIn ? 'Sign out' : 'Sign in' ?></a>
                </div>
            </div>
        </div>
    </nav>
    <main id="main-content" class="container py-4 py-md-5">
        <div class="page-intro mb-4"><span class="eyebrow">DOCKER LAB / 04</span><p class="text-secondary mt-2 mb-0">Your account. Your access. One place.</p></div>
        <section class="card border-0 shadow-sm <?= $wide ? 'workspace-card' : 'account-card mx-auto' ?>">
            <div class="card-body p-4 p-md-5">
    <?php
}

function page_end(): void
{
    ?>
            </div>
        </section>
    </main>
    <footer class="container py-4 text-secondary small d-flex flex-wrap justify-content-between gap-2"><span>Lab Portal · Henry Wu</span><span>Web Services / Lab 4</span></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    </body>
    </html>
    <?php
}
