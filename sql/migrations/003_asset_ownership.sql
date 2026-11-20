-- The previous assets have no account owners and are intentionally discarded.
DELETE FROM assets;
ALTER TABLE assets ADD COLUMN user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX assets_user_created_idx ON assets (user_id, created_at DESC);
