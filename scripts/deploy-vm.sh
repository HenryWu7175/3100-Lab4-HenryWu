#!/usr/bin/env bash
set -euo pipefail

deploy_dir=${1:?Deployment directory required}
archive=${2:?Archive path required}

# Serialize deployments to this VM. No database files or secrets are replaced.
exec 9>/var/lock/lab-web-deploy.lock
flock -w 600 9
test -d "$deploy_dir"
cd "$deploy_dir"
test -f .env || { echo 'Create the deployment .env on the VM first.'; exit 1; }
docker compose version
tar -xzf "$archive" --no-same-owner -C "$deploy_dir"
find www -type d -exec chmod 755 {} +
find www -type f -exec chmod 644 {} +
docker compose config --quiet
docker compose up -d --build
# Nginx configuration bind mounts need a restart after config updates.
docker compose restart web-server

# PHP currently installs extensions at startup; allow time for that to finish.
for attempt in $(seq 1 60); do
    if docker compose exec -T php php -r \
        'exit(extension_loaded("mysqli") && extension_loaded("pdo_mysql") ? 0 : 1);' \
        >/dev/null 2>&1 && curl --fail --silent --show-error --max-time 10 \
        http://127.0.0.1/index.php >/dev/null; then
        rm -f -- "$archive"
        echo 'Deployment completed; homepage HTTP check passed.'
        exit 0
    fi
    sleep 5
done

docker compose ps -a
docker compose logs --tail=50 web-server php
echo 'Deployment failed its HTTP check.'
exit 1
