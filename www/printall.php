<?php
session_start();
require_once __DIR__ . '/ui.php';
page_start('Session diagnostics');
?>
<h1>Session diagnostics</h1>
<p class="text-secondary">Current session values for this browser.</p>
<pre class="bg-light border rounded-3 p-3 overflow-auto"><?php echo htmlspecialchars(print_r($_SESSION, true), ENT_QUOTES, 'UTF-8'); ?></pre>
<a class="btn btn-outline-primary" href="dashboard.php">Back to dashboard</a>
<?php page_end(); ?>
