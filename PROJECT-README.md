# CIS3100 Final Project — Multimodal Smart Asset Platform

The platform accepts images, PDFs, and audio, processes them asynchronously, and lets users search the resulting assets with text or a query image. This document is the implementation plan. The commands and verification checks apply once the corresponding phase is implemented.

## Repository layout

The final project will run alongside Lab 4. Lab 4 uses `docker-compose.yml` on port 80 and deploys through `cloudbuild.yaml`. The final project will use its own Compose stack on port 8080 and a dedicated Cloud Build trigger.

| Planned path | Purpose |
| --- | --- |
| `docker-compose.smart-assets.yml` | Local web app, worker, and PostgreSQL services |
| `app-processor/` | PHP/Apache image, Bootstrap 5 dashboard, upload, status, and search endpoints |
| `ai-worker/` | Worker image, local queue poller, Pub/Sub push endpoint, and asset processing |
| `shared/` | PDO connection, storage adapter, and shared queue operations |
| `init.sql` | Initial PostgreSQL schema for a fresh local database |
| `cloudbuild.smart-assets.yaml` | Tests, image publication, database migration, and Cloud Run deployment |

## Phase 1 — Local foundation and atomic queue

Start the local stack from PowerShell. The first command creates an ignored local environment file if one does not already exist. Keep that file with the same PostgreSQL volume. The dashboard is available at http://localhost:8080.

```powershell
if (-not (Test-Path .env.smart-assets.local)) {
    "SMART_ASSETS_DB_PASS=$([guid]::NewGuid().ToString('N'))" | Set-Content -Encoding ascii .env.smart-assets.local
}
docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml up --build --wait --scale ai-worker=2
```

Use `docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml down` to stop the stack while retaining the database and uploads.

- Add `app-processor`, `ai-worker`, and `pgvector/pgvector:pg16` to the standalone Compose file. Build both PHP images with multi-stage Dockerfiles and run as the non-root user `appuser`. Configure Apache to listen on port 8080 and explicitly set its document root to `/var/www/html/public`.
- Create the `assets` table with a UUID, file metadata, storage URI, status, extracted JSONB metadata, timestamps, a claim token, and a `claimed_until` timestamp with time zone. Add `vector(768)` and `vector(1408)` columns and HNSW cosine indexes.
- Validate uploads by MIME type and size, store them outside the public directory, and return the asset ID for status polling. Support JPEG, PNG, PDF, MP3, and WAV.
- Implement `local_poller.php` with a single `UPDATE … RETURNING` claim. Select the oldest pending or expired processing row using `FOR UPDATE SKIP LOCKED`, then assign a fresh token and a five-minute lease in that transaction. Commit before running the processor. Require a matching claim token when saving results so an expired worker cannot overwrite a newer worker's result.
- Implement deterministic mock output in `AssetProcessor.php`. Show asset states and extracted metadata in the Bootstrap 5 dashboard.

**Verify.** Upload a JPEG at `http://localhost:8080` and observe `PENDING → PROCESSING → COMPLETED` with mock JSON tags. With two worker replicas running, confirm that ordinary uploads are each processed once. Stop a worker after it claims a row, then verify that another worker completes the asset after the lease expires.

## Phase 2 — Storage and event decoupling

- Add a `StorageAdapter` in `shared/`. Use `ENV_MODE=LOCAL` to write to the shared volume at `/var/www/html/storage/uploads/<uuid>_<name>` and `ENV_MODE=CLOUD` to write the same object name to a private GCS bucket.
- Create and commit the asset row before writing to GCS so the finalized-object event can always find it. Record a storage failure if the upload fails, and make that state visible in the dashboard.
- Configure the bucket to send `OBJECT_FINALIZE` notifications to `asset-upload-topic`. Route authenticated Pub/Sub pushes to the worker.
- Implement `ai-worker/worker.php` to validate the push envelope, check the bucket and object name, extract the UUID, and claim the row before running the mock processor. Acknowledge completed replays and permanent failures with HTTP 200. Return a retryable response for transient errors or an active incomplete claim so processing can recover after the lease expires.

**Verify.** Upload in cloud mode and confirm the mock result appears. For a direct GCS copy test, create a matching asset row first. Replay the event after completion and send duplicate deliveries concurrently. Confirm only one result is committed and that transient errors are retried.

## Phase 3 — Vertex AI and cross-modal search

- Configure `GEMINI_MODEL`, `TEXT_EMBED_MODEL`, and `MULTIMODAL_EMBED_MODEL` through environment variables. Before implementation, verify model availability in `us-central1`, supported media, and output dimensions.
- Use the Vertex AI SDK to generate image tags and captions, PDF summaries, and audio transcripts. Request structured JSON, validate it before persistence, and use bounded exponential backoff for HTTP 429 responses.
- Populate the 768-dimensional text vector with captions and tags, summaries, or transcripts. Use `multimodalembedding@001` to create 1,408-dimensional vectors from images directly and from extracted text for PDFs and audio.
- Add `app-processor/public/search.php` for text or transient query-image uploads. Use the same multimodal model for assets and queries, order by cosine distance, and report similarity as `1 - (multimodal_embedding <=> :vec)`. Provide a text-only search option that uses the 768-dimensional index.

**Verify.** Upload an image, a PDF, and an audio file. Check the structured metadata in the detail modal, then run a text search and a query-image search. Confirm relevant results rank higher. Exercise unsupported uploads, invalid model output, and rate-limit recovery.

## Phase 4 — Cloud Run, secrets, and CI/CD

- Provision PostgreSQL 16 on Cloud SQL, enable pgvector, and apply the schema with a versioned migration step. Use migrations for subsequent changes to existing databases.
- Deploy both images from Artifact Registry to Cloud Run. Give the app and worker separate runtime service accounts with scoped permissions. Use a dedicated `pubsub-invoker-sa` identity to invoke the private worker. Store database credentials in Secret Manager and inject the required `DB_PASS` into each service.
- Add a dedicated Cloud Build trigger for merges to `main`. Run PHP syntax checks and automated tests, build and push both images, apply migrations, and deploy the Cloud Run revisions.
- Update the top-level `README.md` with the Lab 4 and final-project run commands and configuration.

**Verify.** Merge to `main` and confirm Cloud Build deploys both services. Upload a file at the live app URL and trace the full flow through GCS, Pub/Sub, Cloud Run, Vertex AI, and Cloud SQL. Capture the passing build, worker logs, dashboard result, and search demo for the grading rubric.
