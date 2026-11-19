#!/usr/bin/env bash
set -euo pipefail

if [[ "${DEPLOY:-false}" != true ]]; then
    echo 'Cloud deployment is disabled for this build.'
    exit 0
fi

if [[ "$GCS_BUCKET" == SET_ME || "$CLOUD_SQL_INSTANCE" != *:*:* ]]; then
    echo 'Set _GCS_BUCKET and _CLOUD_SQL_INSTANCE on the Cloud Build trigger.' >&2
    exit 1
fi

case "$AI_MODE" in
    MOCK|VERTEX) ;;
    *) echo 'Set _AI_MODE to MOCK or VERTEX.' >&2; exit 1 ;;
esac
case "$APP_PUBLIC" in
    true) app_access=--allow-unauthenticated ;;
    false) app_access=--no-allow-unauthenticated ;;
    *) echo 'Set _APP_PUBLIC to true or false.' >&2; exit 1 ;;
esac

db_env="DB_HOST=/cloudsql/$CLOUD_SQL_INSTANCE,DB_NAME=$DB_NAME"
runtime_env="$db_env,DB_USER=$DB_RUNTIME_USER,ENV_MODE=CLOUD,AI_MODE=$AI_MODE,GCP_PROJECT_ID=$GCP_PROJECT_ID,GCS_BUCKET_NAME=$GCS_BUCKET"
cloud_args=(--project="$GCP_PROJECT_ID" --region="$REGION" --quiet)
iam_domain="$GCP_PROJECT_ID.iam.gserviceaccount.com"

gcloud run jobs deploy smart-assets-migrate "${cloud_args[@]}" \
    --image="$WORKER_IMAGE" --service-account="smart-assets-migrator@$iam_domain" \
    --set-cloudsql-instances="$CLOUD_SQL_INSTANCE" \
    --update-env-vars="$db_env,DB_USER=$DB_MIGRATION_USER,DB_RUNTIME_ROLE=$DB_RUNTIME_USER" \
    --update-secrets="DB_PASS=$MIGRATION_DB_SECRET" \
    --command=php --args=/var/www/html/migrate.php \
    --tasks=1 --max-retries=0 --task-timeout=300s --execute-now --wait

service_args=(
    "${cloud_args[@]}"
    --execution-environment=gen2 --port=8080 --invoker-iam-check
    --set-cloudsql-instances="$CLOUD_SQL_INSTANCE"
    --update-env-vars="$runtime_env" --update-secrets="DB_PASS=$RUNTIME_DB_SECRET"
)

gcloud run deploy ai-worker "${service_args[@]}" \
    --image="$WORKER_IMAGE" --service-account="smart-assets-worker@$iam_domain" \
    --no-allow-unauthenticated --timeout=240s --concurrency=4 --max-instances=5

gcloud run deploy app-processor "${service_args[@]}" \
    --image="$APP_IMAGE" --service-account="smart-assets-app@$iam_domain" \
    "$app_access" --timeout=60s --max-instances=5
