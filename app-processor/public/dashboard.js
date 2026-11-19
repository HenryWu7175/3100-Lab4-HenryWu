const form = document.querySelector('#upload');
const button = form.querySelector('button');
const uploadMessage = document.querySelector('#upload-message');
const statusMessage = document.querySelector('#status-message');
const assets = document.querySelector('#assets');
const detail = document.querySelector('#asset-detail');

function assetRow(asset, state) {
    const row = document.createElement('tr');
    row.insertCell().textContent = asset.filename;
    row.insertCell().textContent = state;
    const result = row.insertCell();
    if (asset.extracted_metadata) {
        const action = document.createElement('button');
        action.type = 'button';
        action.className = 'btn btn-sm btn-outline-secondary';
        action.textContent = 'View metadata';
        action.addEventListener('click', () => {
            detail.querySelector('#detail-title').textContent = asset.filename;
            detail.querySelector('#detail-metadata').textContent = JSON.stringify(asset.extracted_metadata, null, 2);
            detail.showModal();
        });
        result.append(action);
    } else {
        result.textContent = asset.error_message || '';
    }
    return row;
}

async function refreshAssets() {
    try {
        const response = await fetch('status.php');
        if (!response.ok) {
            throw new Error('Asset status is unavailable.');
        }
        const rows = await response.json();
        assets.replaceChildren(...rows.map((asset) => assetRow(asset, asset.status)));
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

const search = document.querySelector('#search');
if (search) {
    const searchButton = search.querySelector('button');
    const searchMessage = document.querySelector('#search-message');
    const results = document.querySelector('#search-results');
    search.addEventListener('submit', async (event) => {
        event.preventDefault();
        searchButton.disabled = true;
        searchMessage.textContent = 'Searching…';
        results.hidden = true;
        try {
            const response = await fetch(search.action, {method: 'POST', body: new FormData(search)});
            const rows = await response.json();
            if (!response.ok) {
                throw new Error(rows.error);
            }
            results.tBodies[0].replaceChildren(...rows.map((asset) => assetRow(asset, asset.similarity.toFixed(3))));
            results.hidden = rows.length === 0;
            searchMessage.textContent = rows.length ? '' : 'No indexed assets found.';
        } catch (error) {
            searchMessage.textContent = error.message || 'Search failed.';
        } finally {
            searchButton.disabled = false;
        }
    });
}
