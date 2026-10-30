# Unit tests

Run with PHP 8.2 or later:

```sh
php tests/run.php
```

Or from PowerShell using Docker:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/app,readonly" -w /app php:8.2-cli php tests/run.php
```

No database, credentials, or Composer installation is required. The runner exits
nonzero when a test fails, including when PHP reports a warning.

Coverage includes independent authenticator reference vectors, clock tolerance,
malformed codes, secret generation boundaries, deployed email verification URLs,
and shared layout output. Cloud Build runs these tests before deployment.

These are unit tests; they do not verify MySQL migrations, SMTP delivery, session
redirects, or full browser registration/login flows.
