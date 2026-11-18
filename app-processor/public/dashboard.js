const form = document.querySelector('#upload');
const button = form.querySelector('button');
const uploadMessage = document.querySelector('#upload-message');
const statusMessage = document.querySelector('#status-message');
const assets = document.querySelector('#assets');

async function refreshAssets() {
    try {
        const response = await fetch('status.php');
        if (!response.ok) {
            throw new Error('Asset status is unavailable.');
        }
        const rows = await response.json();
        assets.replaceChildren(...rows.map((asset) => {
            const row = document.createElement('tr');
            const result = asset.error_message || (asset.extracted_metadata
                ? JSON.stringify(asset.extracted_metadata)
                : '');
            for (const value of [asset.filename, asset.status, result]) {
                row.insertCell().textContent = value;
            }
            return row;
        }));
        statusMessage.textContent = rows.length ? '' : 'No uploads yet.';
    } catch {
        statusMessage.textContent = 'Asset status is unavailable. Retrying…';
    } finally {
        setTimeout(refreshAssets, 1000);
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    button.disabled = true;
    uploadMessage.textContent = 'Uploading…';
    try {
        const response = await fetch(form.action, {method: 'POST', body: new FormData(form)});
        const asset = await response.json();
        if (!response.ok) {
            throw new Error(asset.error);
        }
        form.reset();
        uploadMessage.textContent = 'Upload queued.';
    } catch (error) {
        uploadMessage.textContent = error.message || 'Upload failed.';
    } finally {
        button.disabled = false;
    }
});

refreshAssets();
