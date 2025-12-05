<?php

declare(strict_types=1);

final class AdminUserRepository implements AdminUserRepositoryInterface
{
    private ?PDO $connection = null;

    public function __construct()
    {
        try {
            // Використовуємо повний namespace для Database
            if (class_exists(\Flowaxy\Core\Infrastructure\Persistence\Database::class)) {
                $this->connection = \Flowaxy\Core\Infrastructure\Persistence\Database::getInstance()->getConnection();
            } elseif (class_exists('Database')) {
                $this->connection = Database::getInstance()->getConnection();
            } else {
                // Fallback через DatabaseHelper
                $this->connection = DatabaseHelper::getConnection();
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('AdminUserRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
            } elseif (function_exists('logger')) {
                logger()->logError('AdminUserRepository ctor error: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    public function findByUsername(string $username): ?AdminUser
    {
        if ($this->connection === null) {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::findByUsername: Connection is null', ['username' => $username]);
            }
            return null;
        }

        try {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::findByUsername: Searching for user', ['username' => $username]);
            }

            $stmt = $this->connection->prepare('SELECT id, username, password, session_token, last_activity, is_active FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                if (function_exists('logDebug')) {
                    logDebug('AdminUserRepository::findByUsername: User found', [
                        'username' => $row['username'],
                        'user_id' => $row['id'],
                    ]);
                }
                if (function_exists('logInfo')) {
                    logInfo('AdminUserRepository::findByUsername: User retrieved successfully', ['user_id' => $row['id']]);
                }
                return $this->mapRow($row);
            } else {
                if (function_exists('logDebug')) {
                    logDebug('AdminUserRepository::findByUsername: User not found', ['username' => $username]);
                }
                return null;
            }
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('AdminUserRepository::findByUsername error: ' . $e->getMessage(), [
                    'username' => $username,
                    'exception' => $e,
                ]);
            } elseif (function_exists('logError')) {
                logError('AdminUserRepository::findByUsername error: ' . $e->getMessage(), [
                    'username' => $username,
                    'exception' => $e,
                ]);
            }
            return null;
        }
    }

    public function findById(int $id): ?AdminUser
    {
        if ($this->connection === null) {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::findById: Connection is null', ['user_id' => $id]);
            }
            return null;
        }

        try {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::findById: Searching for user', ['user_id' => $id]);
            }

            $stmt = $this->connection->prepare('SELECT id, username, password, session_token, last_activity, is_active FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                if (function_exists('logInfo')) {
                    logInfo('AdminUserRepository::findById: User retrieved successfully', ['user_id' => $id]);
                }
                return $this->mapRow($row);
            } else {
                if (function_exists('logDebug')) {
                    logDebug('AdminUserRepository::findById: User not found', ['user_id' => $id]);
                }
                return null;
            }
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('AdminUserRepository::findById error: ' . $e->getMessage(), [
                    'user_id' => $id,
                    'exception' => $e,
                ]);
            }
            return null;
        }
    }

    public function updateSession(int $userId, string $token, string $lastActivity): bool
    {
        if ($this->connection === null) {
            if (function_exists('logWarning')) {
                logWarning('AdminUserRepository::updateSession: Connection is null', ['user_id' => $userId]);
            }
            return false;
        }

        try {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::updateSession: Updating session', ['user_id' => $userId]);
            }

            $stmt = $this->connection->prepare('UPDATE users SET session_token = ?, last_activity = ?, is_active = 1 WHERE id = ?');
            $result = $stmt->execute([$token, $lastActivity, $userId]);

            if ($result) {
                if (function_exists('logInfo')) {
                    logInfo('AdminUserRepository::updateSession: Session updated successfully', ['user_id' => $userId]);
                }
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('AdminUserRepository::updateSession error: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    public function clearSession(int $userId): bool
    {
        if ($this->connection === null) {
            if (function_exists('logWarning')) {
                logWarning('AdminUserRepository::clearSession: Connection is null', ['user_id' => $userId]);
            }
            return false;
        }

        try {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::clearSession: Clearing session', ['user_id' => $userId]);
            }

            $stmt = $this->connection->prepare('UPDATE users SET session_token = NULL, last_activity = NULL, is_active = 0 WHERE id = ?');
            $result = $stmt->execute([$userId]);

            if ($result && function_exists('logInfo')) {
                logInfo('AdminUserRepository::clearSession: Session cleared successfully', ['user_id' => $userId]);
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('AdminUserRepository::clearSession error: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    public function markInactive(int $userId): bool
    {
        if ($this->connection === null) {
            if (function_exists('logWarning')) {
                logWarning('AdminUserRepository::markInactive: Connection is null', ['user_id' => $userId]);
            }
            return false;
        }

        try {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::markInactive: Marking user as inactive', ['user_id' => $userId]);
            }

            $stmt = $this->connection->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
            $result = $stmt->execute([$userId]);

            if ($result && function_exists('logInfo')) {
                logInfo('AdminUserRepository::markInactive: User marked as inactive', ['user_id' => $userId]);
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('AdminUserRepository::markInactive error: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
            return false;
        }
    }

    public function updateLastActivity(int $userId, string $timestamp): bool
    {
        if ($this->connection === null) {
            if (function_exists('logWarning')) {
                logWarning('AdminUserRepository::updateLastActivity: Connection is null', ['user_id' => $userId]);
            }
            return false;
        }

        try {
            if (function_exists('logDebug')) {
                logDebug('AdminUserRepository::updateLastActivity: Updating last activity', [
                    'user_id' => $userId,
                    'timestamp' => $timestamp,
                ]);
            }

            $stmt = $this->connection->prepare('UPDATE users SET last_activity = ?, is_active = 1 WHERE id = ?');
            $result = $stmt->execute([$timestamp, $userId]);

            if ($result && function_exists('logInfo')) {
                logInfo('AdminUserRepository::updateLastActivity: Last activity updated', ['user_id' => $userId]);
            }

            return $result;
        } catch (\Exception $e) {
            if (function_exists('logDbError')) {
                logDbError('AdminUserRepository::updateLastActivity error: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
            return false;
        }
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
