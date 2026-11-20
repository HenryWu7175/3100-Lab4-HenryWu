# CIS3100 Lab 4 and Smart Assets

## Smart Assets final project

Run the standalone final project from PowerShell:

```powershell
if (-not (Test-Path .env.smart-assets.local)) {
    "SMART_ASSETS_DB_PASS=$([guid]::NewGuid().ToString('N'))" | Set-Content -Encoding ascii .env.smart-assets.local
}
docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml up --build --wait --scale ai-worker=2
```

The merged app is under development. Configure `APP_URL`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, and `SMTP_FROM` in the ignored `.env.smart-assets.local` before registering; `SMTP_SECURITY` defaults to `tls`. Restart the app with `docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml up -d app-processor` after changing them. Open http://localhost:8080, register, verify your email, and enroll an authenticator to reach the dashboard. Use a real inbox or a mail-testing service to receive the verification link.

The ownership migration deletes existing asset records. Previously uploaded files are not automatically deleted from local volumes or cloud storage. See [PROJECT-README.md](PROJECT-README.md) for the project plan, tests, and Cloud Run setup; its pre-authentication instructions are being updated.

### Administrator access

New accounts have the `user` role. To bootstrap the first administrator locally, first register and verify the account, then open PostgreSQL:

```powershell
docker compose --env-file .env.smart-assets.local -f docker-compose.smart-assets.yml exec postgres-db psql -U assets -d smart_assets
```

At the `psql` prompt, replace the example email with the verified account and run:

```sql
UPDATE users SET role = 'admin' WHERE lower(email) = lower('admin@example.com') AND email_verified_at IS NOT NULL;
```

An `UPDATE 1` result means the account was promoted. Enter `\q` to exit. After completing MFA and signing in, the account can open the Admin dashboard and grant administrator access to other verified users. Cloud administrators must bootstrap the first account through an authorized Cloud SQL administrator connection.

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
