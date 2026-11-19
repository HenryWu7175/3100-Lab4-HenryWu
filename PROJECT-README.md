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
| `sql/migrations/` | Versioned PostgreSQL migrations. Compose uses the first migration to initialize a fresh local database. |
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

### Connect a GCP test environment

Deploy the app and worker images to Cloud Run on port 8080, with both services connected to the same initialized PostgreSQL database. Set `ENV_MODE=CLOUD` on the app and `GCS_BUCKET_NAME` on both services. Give the app's runtime identity `roles/storage.objectCreator` on the bucket. The worker image starts Apache by default. Keep the worker's Cloud Run Invoker IAM check enabled and grant access to the Pub/Sub push identity on that service. The mock worker uses the finalized-object metadata and does not download the object.

The following PowerShell commands assume the project and private worker already exist. Set the three values for your project before running them.

```powershell
$project = 'your-gcp-project'
$bucket = 'your-unique-smart-assets-bucket'
$workerUrl = 'https://your-private-worker.run.app'
$invoker = "pubsub-invoker-sa@${project}.iam.gserviceaccount.com"

gcloud storage buckets create "gs://$bucket" --project=$project --location=us-central1 --uniform-bucket-level-access --public-access-prevention
gcloud pubsub topics create asset-upload-topic --project=$project
gcloud iam service-accounts create pubsub-invoker-sa --project=$project
gcloud run services add-iam-policy-binding ai-worker --project=$project --region=us-central1 --member="serviceAccount:$invoker" --role=roles/run.invoker
gcloud pubsub subscriptions create asset-upload-push --project=$project --topic=asset-upload-topic --push-endpoint="$workerUrl/worker.php" --push-auth-service-account=$invoker --push-auth-token-audience=$workerUrl --ack-deadline=240 --min-retry-delay=10s --max-retry-delay=120s
gcloud storage buckets notifications create "gs://$bucket" --project=$project --topic=asset-upload-topic --event-types=OBJECT_FINALIZE --payload-format=json
```

Check that the Cloud Storage service agent has `roles/pubsub.publisher` on the topic and that Pub/Sub can mint tokens for the push identity. The caller configuring the push subscription needs permission to act as that identity. [Cloud Storage notifications](https://docs.cloud.google.com/storage/docs/reporting-changes) · [Authenticated Pub/Sub push](https://docs.cloud.google.com/pubsub/docs/authenticate-push-subscriptions)

## Phase 3 — Vertex AI and cross-modal search

- Configure the model IDs and locations through environment variables. Gemini 3.5 Flash uses the `global` endpoint. Both embedding calls use `us-central1` by default.
- Use the Vertex AI SDK to generate image tags and captions, PDF summaries, and audio transcripts. Request structured JSON, validate it before persistence, and use bounded exponential backoff for HTTP 429 responses.
- Populate the 768-dimensional text vector with captions and tags, summaries, or transcripts. Use `multimodalembedding@001` to create 1,408-dimensional vectors from images directly and from extracted text for PDFs and audio.
- Add `app-processor/public/search.php` for text or transient query-image uploads. Use the same multimodal model for assets and queries, order by cosine distance, and report similarity as `1 - (multimodal_embedding <=> :vec)`. Provide a text-only search option that uses the 768-dimensional index.

**Verify.** Upload an image, a PDF, and an audio file. Check the structured metadata in the detail modal, then run a text search and a query-image search. Confirm relevant results rank higher. Exercise unsupported uploads, invalid model output, and rate-limit recovery.

### Enable Vertex AI in the GCP test environment

Enable the Vertex AI API and give the app and worker runtime identities `roles/aiplatform.user` in the model project. Grant the worker `roles/storage.objectViewer` on the asset bucket so it can send private GCS files to Vertex. Keep the asset bucket and Vertex project in the same GCP project for the live test. Set the worker request timeout to at least 240 seconds, and use the Phase 2 subscription deadline and retry settings.

| Environment variable | Configuration |
| --- | --- |
| `AI_MODE` | Set to `VERTEX` on the app and HTTP worker. The local Compose stack defaults to mock processing. |
| `GCP_PROJECT_ID` | GCP project with Vertex AI enabled |
| `GEMINI_MODEL` | Defaults to `gemini-3.5-flash` |
| `GEMINI_LOCATION` | Defaults to `global` |
| `TEXT_EMBED_MODEL` | Defaults to `gemini-embedding-001` with 768 output dimensions |
| `MULTIMODAL_EMBED_MODEL` | Defaults to `multimodalembedding@001` with 1,408 output dimensions |
| `VERTEX_EMBED_LOCATION` | Defaults to `us-central1` |

Upload new assets after enabling Vertex mode. Completed mock records have empty vectors and are excluded from search. Recreate embeddings for existing assets before switching to a different model so queries and assets share the same vector space. The cross-modal model uses the first 32 tokens of text, so the worker sends the generated summary for PDFs and audio. It also includes the transcript when creating the separate text vector. [Gemini 3.5 Flash](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/gemini/3-5-flash) · [Multimodal embedding limits](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/embeddings/get-multimodal-embeddings)

### Run the Phase 3 check locally

With the Compose stack running, use PowerShell to test SDK request serialization, rate-limit recovery with simulated responses, metadata validation, and pgvector ranking. The test fixture rows are rolled back.

```powershell
Get-Content -Raw tests/smart-assets-phase3.php | docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml exec -T app-processor php
```

## Phase 4 — Cloud Run, secrets, and CI/CD

- Provision PostgreSQL 16 on Cloud SQL, enable pgvector, and apply the schema with a versioned migration step. Use migrations for subsequent changes to existing databases.
- Deploy both images from Artifact Registry to Cloud Run. Give the app and worker separate runtime service accounts with scoped permissions. Use a dedicated `pubsub-invoker-sa` identity to invoke the private worker. Store database credentials in Secret Manager and inject the required `DB_PASS` into each service.
- Add a dedicated Cloud Build trigger for merges to `main`. Run PHP syntax checks and automated tests, build and push both images, apply migrations, and deploy the Cloud Run revisions.
- Update the top-level `README.md` with the Lab 4 and final-project run commands and configuration.

**Verify.** Merge to `main` and confirm Cloud Build deploys both services. Upload a file at the live app URL and trace the full flow through GCS, Pub/Sub, Cloud Run, Vertex AI, and Cloud SQL. Capture the passing build, worker logs, dashboard result, and search demo for the grading rubric.

### Prepare the deployment resources

1. Enable Artifact Registry, Cloud Build, Cloud Run, Cloud SQL Admin, Secret Manager, Cloud Storage, Pub/Sub, and Vertex AI in the selected project. Create the `smart-assets` Docker repository in `us-central1`.
2. Create a Cloud SQL PostgreSQL 16 instance and a `smart_assets` database. The pipeline connects through the Cloud Run Cloud SQL Unix socket. Use a Cloud SQL configuration reachable through that connector, such as an instance with a public IP. A private-IP-only instance also needs a configured network path from Cloud Run. [Cloud Run and Cloud SQL](https://docs.cloud.google.com/sql/docs/postgres/connect-run)
3. Use the Cloud SQL administrator credential for migrations. In an authenticated `psql` session, create the application database role and set its password:

   ```sql
   CREATE ROLE assets_runtime LOGIN;
   \password assets_runtime
   ```

   Store the two passwords in Secret Manager as `smart-assets-migration-db-pass` and `smart-assets-runtime-db-pass`. The migration job creates pgvector and the schema, and grants the runtime role access to read, insert, and update assets. [Cloud SQL PostgreSQL extensions](https://docs.cloud.google.com/sql/docs/postgres/extensions)
4. Create the runtime identities and apply the following grants. Bind Secret Manager access to each named secret.

   | Identity | Access |
   | --- | --- |
   | `smart-assets-app` | Cloud SQL Client, runtime DB secret, Storage Object Creator on the asset bucket, Vertex AI User |
   | `smart-assets-worker` | Cloud SQL Client, runtime DB secret, Storage Object Viewer on the asset bucket, Vertex AI User |
   | `smart-assets-migrator` | Cloud SQL Client and migration DB secret |
   | `pubsub-invoker-sa` | Cloud Run Invoker on `ai-worker`, with Pub/Sub configured to mint its push token |
   | Cloud Build trigger identity | Logs Writer, Artifact Registry Writer on the image repository, permissions to deploy and set IAM for the Cloud Run services and execute the migration job, and Service Account User on the three runtime identities |

5. Follow the Phase 2 steps to create the bucket and topic. Add the authenticated push subscription after the first deployment creates the private worker. Use `_AI_MODE=MOCK` for a first cloud smoke test if you want to validate delivery before enabling `VERTEX`.

### Run the pipeline

- **Test-only build.** `gcloud builds submit --config=cloudbuild.smart-assets.yaml` builds both images and runs syntax, migration, and search tests. The default `_DEPLOY=false` skips publication and deployment.
- **Main-branch trigger.** Create a trigger with the repository branch filter `^main$` and build configuration `cloudbuild.smart-assets.yaml`. Set `_DEPLOY=true`, `_GCS_BUCKET` to the bucket name, and `_CLOUD_SQL_INSTANCE` to `PROJECT:REGION:INSTANCE`. Override the secret names or numeric version substitutions if they differ from the defaults. The trigger builds and tests both images, publishes immutable build-ID tags, waits for the migration job, then deploys the worker and app.
- **Browser test.** The app and worker require IAM by default. Grant the tester Cloud Run Invoker on the app and run `gcloud run services proxy app-processor --region=us-central1 --port=8081`, then open http://localhost:8081. The trigger can set `_APP_PUBLIC=true` to make the app publicly accessible. The worker continues to require IAM.
- **Local migrations.** After rebuilding the local worker, run `docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml exec -T ai-worker php migrate.php`. The migration runner records applied files and checksums. Add a new numbered SQL file for each future change. [Cloud Run job deployment](https://docs.cloud.google.com/sdk/gcloud/reference/run/jobs/deploy) · [Cloud Run IAM](https://docs.cloud.google.com/run/docs/securing/managing-access)
