-- Authentication state is shared by all app instances through PostgreSQL.
CREATE TABLE users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email text NOT NULL,
    password_hash text NOT NULL,
    email_verified_at timestamptz,
    verification_token_hash text,
    verification_expires_at timestamptz,
    reset_token_hash text,
    reset_expires_at timestamptz,
    totp_secret text,
    totp_confirmed_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX users_email_lower_idx ON users (lower(email));
CREATE UNIQUE INDEX users_verification_token_idx ON users (verification_token_hash)
    WHERE verification_token_hash IS NOT NULL;
CREATE UNIQUE INDEX users_reset_token_idx ON users (reset_token_hash)
    WHERE reset_token_hash IS NOT NULL;

-- Session IDs are stored as hashes; session data includes pending MFA state.
CREATE TABLE auth_sessions (
    id_hash text PRIMARY KEY,
    data text NOT NULL,
    expires_at timestamptz NOT NULL
);
CREATE INDEX auth_sessions_expiry_idx ON auth_sessions (expires_at);
