ALTER TABLE users
    ADD COLUMN role text NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    ADD COLUMN last_login_at timestamptz,
    ADD COLUMN last_logout_at timestamptz;
