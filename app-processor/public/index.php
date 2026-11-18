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
    <h2 class="h4">Recent assets</h2>
    <p id="status-message" role="status"></p>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th scope="col">File</th><th scope="col">Status</th><th scope="col">Result</th></tr></thead>
            <tbody id="assets"></tbody>
        </table>
    </div>
</main>
</body>
</html>
