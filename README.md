# CIS3100 Lab 4 and Smart Assets

## Smart Assets final project

Run the standalone final project from PowerShell:

```powershell
if (-not (Test-Path .env.smart-assets.local)) {
    "SMART_ASSETS_DB_PASS=$([guid]::NewGuid().ToString('N'))" | Set-Content -Encoding ascii .env.smart-assets.local
}
docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml up --build --wait --scale ai-worker=2
```

Open http://localhost:8080 and upload a JPEG to verify the mock worker. See [PROJECT-README.md](PROJECT-README.md) for the four-phase plan, tests, and Cloud Run setup.

## Lab 4 deployment URL

Use `docker-compose.yml` for this application. Set `APP_URL` in `.env` to the
address users open in their browser:

```dotenv
# Local deployment (the web server publishes port 80)
APP_URL=http://localhost
```

For public deployment, change that same setting to your server's public address:

```dotenv
APP_URL=http://34.24.198.194
```

Use your HTTPS domain instead when HTTPS is configured. Include a port if the
public address requires one.

After changing `.env`, apply the setting on the deployment host:

```sh
docker compose up -d php
```

Docker Compose recreates the PHP container when its environment changes. A
container restart alone does not reload `.env`. Newly generated verification
emails use the new address; links in previously sent emails stay the same.

Navigation and form links are relative, so they follow the address in the browser.
`APP_URL` controls generated verification links; the public host must already be
reachable, with DNS, firewall access, and HTTPS configured as needed.

`docker-compose-lab2.yml` is a separate older stack and does not configure this
application's environment.
