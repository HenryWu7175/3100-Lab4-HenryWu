CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE assets (
    id uuid PRIMARY KEY,
    filename text NOT NULL,
    mime_type text NOT NULL,
    storage_uri text NOT NULL,
    status text NOT NULL DEFAULT 'PENDING'
        CHECK (status IN ('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED')),
    extracted_metadata jsonb,
    error_message text,
    claim_token uuid,
    claimed_until timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    text_embedding vector(768),
    multimodal_embedding vector(1408)
);

CREATE INDEX ON assets USING hnsw (text_embedding vector_cosine_ops);
CREATE INDEX ON assets USING hnsw (multimodal_embedding vector_cosine_ops);
