#!/bin/sh
set -eu

: "${APP_IMAGE:?Set APP_IMAGE}"
: "${WORKER_IMAGE:?Set WORKER_IMAGE}"
: "${TEST_NAMESPACE:?Set TEST_NAMESPACE to a unique Docker name}"
database="$TEST_NAMESPACE-db"

docker run --rm "$APP_IMAGE" sh -euc 'find public shared -name "*.php" -type f -exec php -l {} +'
docker run --rm "$WORKER_IMAGE" sh -euc 'php -l migrate.php; php -l AssetProcessor.php; php -l local_poller.php; php -l public/worker.php'
docker network create --internal "$TEST_NAMESPACE" >/dev/null
trap 'docker rm -f "$database" >/dev/null 2>&1 || :; docker network rm "$TEST_NAMESPACE" >/dev/null 2>&1 || :' EXIT
docker run -d --name "$database" --network "$TEST_NAMESPACE" \
    -e POSTGRES_DB=smart_assets -e POSTGRES_HOST_AUTH_METHOD=trust pgvector/pgvector:pg16 >/dev/null
attempt=0
until docker exec "$database" pg_isready -h 127.0.0.1 -U postgres -d smart_assets >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 45 ]; then
        docker logs "$database"
        exit 1
    fi
    sleep 1
done

docker exec "$database" psql -U postgres -d smart_assets -v ON_ERROR_STOP=1 -c 'CREATE ROLE assets_runtime LOGIN'

run_with_db() {
    db_user=$1
    shift
    docker run --rm -i --network "$TEST_NAMESPACE" \
        -e DB_HOST="$database" -e DB_NAME=smart_assets -e DB_USER="$db_user" -e DB_PASS= \
        "$@"
}

run_with_db postgres -e DB_RUNTIME_ROLE=assets_runtime "$WORKER_IMAGE" php migrate.php
run_with_db postgres -e DB_RUNTIME_ROLE=assets_runtime "$WORKER_IMAGE" php migrate.php
test "$(docker exec "$database" psql -U postgres -d smart_assets -Atc 'SELECT count(*) FROM schema_migrations')" = 2
run_with_db assets_runtime "$APP_IMAGE" php < tests/smart-assets-phase3.php
