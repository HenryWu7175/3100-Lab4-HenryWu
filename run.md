docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml down

docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml up -d --scale ai-worker=2