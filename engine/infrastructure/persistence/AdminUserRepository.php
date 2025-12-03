<?php

declare(strict_types=1);

final class AdminUserRepository implements AdminUserRepositoryInterface
{
    private ?PDO $connection = null;

    public function __construct()
    {
        try {
            $this->connection = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            logger()->logError('AdminUserRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    public function findByUsername(string $username): ?AdminUser
    {
        if ($this->connection === null) {
            return null;
        }

        $stmt = $this->connection->prepare('SELECT id, username, password, session_token, last_activity, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findById(int $id): ?AdminUser
    {
        if ($this->connection === null) {
            return null;
        }

        $stmt = $this->connection->prepare('SELECT id, username, password, session_token, last_activity, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function updateSession(int $userId, string $token, string $lastActivity): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $stmt = $this->connection->prepare('UPDATE users SET session_token = ?, last_activity = ?, is_active = 1 WHERE id = ?');

        return $stmt->execute([$token, $lastActivity, $userId]);
    }

    public function clearSession(int $userId): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $stmt = $this->connection->prepare('UPDATE users SET session_token = NULL, last_activity = NULL, is_active = 0 WHERE id = ?');

        return $stmt->execute([$userId]);
    }

    public function markInactive(int $userId): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $stmt = $this->connection->prepare('UPDATE users SET is_active = 0 WHERE id = ?');

        return $stmt->execute([$userId]);
    }

    public function updateLastActivity(int $userId, string $timestamp): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $stmt = $this->connection->prepare('UPDATE users SET last_activity = ?, is_active = 1 WHERE id = ?');

        return $stmt->execute([$timestamp, $userId]);
    }

    /**
     * Маппінг рядка з БД до об'єкта AdminUser
     *
     * @param array<string, mixed> $row Рядок з БД
     * @return AdminUser
     */
    private function mapRow(array $row): AdminUser
    {
        return new AdminUser(
            id: (int)$row['id'],
            username: $row['username'],
            passwordHash: $row['password'],
            sessionToken: $row['session_token'] ?? null,
            lastActivity: $row['last_activity'] ?? null,
            isActive: (bool)$row['is_active']
        );
    }
}
