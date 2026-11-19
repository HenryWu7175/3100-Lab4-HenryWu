<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Assets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="dashboard.js" defer></script>
</head>
<body class="bg-body-tertiary">
<main class="container py-5">
    <h1>Smart Assets</h1>
    <form id="upload" action="upload.php" method="post" enctype="multipart/form-data" class="card card-body my-4">
        <label for="asset" class="form-label">Choose a file</label>
        <input id="asset" name="asset" type="file" required class="form-control"
               accept=".jpg,.jpeg,.png,.pdf,.mp3,.wav" aria-describedby="file-help">
        <div id="file-help" class="form-text">JPEG, PNG, PDF, MP3, or WAV. Up to 10 MiB.</div>
        <div><button class="btn btn-primary mt-3">Upload</button></div>
        <p id="upload-message" class="mb-0" role="status"></p>
    </form>
    <?php if (getenv('AI_MODE') === 'VERTEX'): ?>
        <section class="card card-body mb-4">
            <h2 class="h4">Search assets</h2>
            <form id="search" action="search.php" method="post" enctype="multipart/form-data">
                <label class="form-label" for="query">Search text</label>
                <input class="form-control mb-3" id="query" name="q" maxlength="1000">
                <label class="form-label" for="query-image">Or choose a query image</label>
                <input class="form-control mb-3" id="query-image" name="query_image" type="file" accept=".jpg,.jpeg,.png">
                <label class="form-label" for="search-mode">Search mode</label>
                <select class="form-select mb-3" id="search-mode" name="mode">
                    <option value="multimodal">Cross-modal</option>
                    <option value="text">Text only</option>
                </select>
                <button class="btn btn-primary">Search</button>
            </form>
            <p id="search-message" class="mt-2 mb-0" role="status"></p>
            <div class="table-responsive">
                <table class="table table-striped" id="search-results" hidden>
                    <thead><tr><th scope="col">File</th><th scope="col">Similarity</th><th scope="col">Result</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
    <h2 class="h4">Recent assets</h2>
    <p id="status-message" role="status"></p>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th scope="col">File</th><th scope="col">Status</th><th scope="col">Result</th></tr></thead>
            <tbody id="assets"></tbody>
        </table>
    </div>
    <dialog id="asset-detail" class="border rounded shadow p-4 w-75">
        <h2 class="h5" id="detail-title"></h2>
        <pre class="text-wrap" id="detail-metadata"></pre>
        <form method="dialog"><button class="btn btn-secondary">Close</button></form>
    </dialog>
</main>
</body>
</html>
