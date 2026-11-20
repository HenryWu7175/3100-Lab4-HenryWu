<?php
declare(strict_types=1);

/** Store PHP session state in PostgreSQL so requests can use different instances. */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private ?string $lockedId = null;

    public function __construct(private PDO $db, private int $lifetime = 3600) {}

    private function key(string $id): string
    {
        return hash('sha256', $id);
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool
    {
        if ($this->lockedId !== null) {
            $query = $this->db->prepare('SELECT pg_advisory_unlock(hashtextextended(?, 0))');
            $query->execute([$this->lockedId]);
            $this->lockedId = null;
        }
        return true;
    }

    public function read(string $id): string|false
    {
        // Serialize simultaneous requests for the same session, including polls.
        $this->close();
        $this->lockedId = $this->key($id);
        $lock = $this->db->prepare('SELECT pg_advisory_lock(hashtextextended(?, 0))');
        $lock->execute([$this->lockedId]);
        $query = $this->db->prepare('SELECT data FROM auth_sessions WHERE id_hash = ? AND expires_at > now()');
        $query->execute([$this->lockedId]);
        $data = $query->fetchColumn();
        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        $query = $this->db->prepare(<<<'SQL'
            INSERT INTO auth_sessions (id_hash, data, expires_at)
            VALUES (?, ?, now() + (? * interval '1 second'))
            ON CONFLICT (id_hash) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at
            SQL);
        return $query->execute([$this->key($id), $data, $this->lifetime]);
    }

    public function destroy(string $id): bool
    {
        $query = $this->db->prepare('DELETE FROM auth_sessions WHERE id_hash = ?');
        return $query->execute([$this->key($id)]);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->db->exec('DELETE FROM auth_sessions WHERE expires_at <= now()');
    }

    public function validateId(string $id): bool
    {
        $query = $this->db->prepare('SELECT 1 FROM auth_sessions WHERE id_hash = ? AND expires_at > now()');
        $query->execute([$this->key($id)]);
        return $query->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
