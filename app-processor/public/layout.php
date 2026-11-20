<?php
declare(strict_types=1);

function page_start(string $title, bool $signedIn = false, bool $admin = false): void
{
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    ?>
    <!doctype html>
    <html lang="en"><head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $title ?> · Smart Assets</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link href="assets/app.css" rel="stylesheet">
    </head><body>
    <a class="visually-hidden-focusable skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar bg-white border-bottom" aria-label="Main navigation"><div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><span class="brand-mark">SA</span> Smart Assets</a>
        <?php if ($signedIn): ?>
            <div class="d-flex align-items-center gap-3"><a class="nav-link" href="index.php">Dashboard</a><?php if ($admin): ?><a class="nav-link" href="admin.php">Admin</a><?php endif; ?>
            <form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-outline-secondary">Sign out</button></form>
            </div>
        <?php else: ?><a class="nav-link" href="login.php">Sign in</a><?php endif; ?>
    </div></nav>
    <main id="main-content" class="container py-4 py-md-5">
        <div class="page-intro mb-4"><span class="eyebrow">SMART ASSETS</span><p class="text-secondary mt-2 mb-0">Your account. Your assets. One place.</p></div>
    <?php
}

function page_end(): void
{
    ?>
    </main><footer class="container py-4 text-secondary small">Smart Assets · Henry Wu</footer></body></html>
    <?php
}
