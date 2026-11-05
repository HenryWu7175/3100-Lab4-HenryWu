# Deployment URL

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
