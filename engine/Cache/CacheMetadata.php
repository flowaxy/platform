<?php

/**
 * Метадані для кешованих об'єктів
 *
 * Зберігає додаткову інформацію про кешовані дані:
 * - час створення
 * - час останнього доступу
 * - розмір даних
 * - версія кешу
 * - теги
 * - додаткові метадані
 *
 * @package Flowaxy\Core\Infrastructure\Cache
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Infrastructure\Cache;

final class CacheMetadata
{
    /**
     * @var int Час створення (timestamp)
     */
    public readonly int $createdAt;

    /**
     * @var int Час останнього доступу (timestamp)
     */
    public int $lastAccessedAt;

    /**
     * @var int Розмір даних в байтах
     */
    public readonly int $size;

    /**
     * @var string Версія кешу
     */
    public readonly string $version;

    /**
     * @var array<string> Теги
     */
    public readonly array $tags;

    /**
     * @var array<string, mixed> Додаткові метадані
     */
    public readonly array $extra;

    /**
     * Конструктор
     *
     * @param int $size Розмір даних в байтах
     * @param string $version Версія кешу
     * @param array<string> $tags Теги
     * @param array<string, mixed> $extra Додаткові метадані
     */
    public function __construct(
        int $size = 0,
        string $version = '1.0',
        array $tags = [],
        array $extra = []
    ) {
        $this->createdAt = time();
        $this->lastAccessedAt = time();
        $this->size = $size;
        $this->version = $version;
        $this->tags = $tags;
        $this->extra = $extra;
    }

    /**
     * Оновлення часу останнього доступу
     *
     * @return void
     */
    public function touch(): void
    {
        $this->lastAccessedAt = time();
    }

    /**
     * Перевірка, чи є тег
     *
     * @param string $tag
     * @return bool
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Перевірка, чи є хоча б один тег з масиву
     *
     * @param array<string> $tags
     * @return bool
     */
    public function hasAnyTag(array $tags): bool
    {
        return !empty(array_intersect($this->tags, $tags));
    }

    /**
     * Перевірка, чи є всі теги з масиву
     *
     * @param array<string> $tags
     * @return bool
     */
    public function hasAllTags(array $tags): bool
    {
        return empty(array_diff($tags, $this->tags));
    }

    /**
     * Серіалізація метаданих
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'created_at' => $this->createdAt,
            'last_accessed_at' => $this->lastAccessedAt,
            'size' => $this->size,
            'version' => $this->version,
            'tags' => $this->tags,
            'extra' => $this->extra,
        ];
    }

    /**
     * Десеріалізація метаданих
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $metadata = new self(
            $data['size'] ?? 0,
            $data['version'] ?? '1.0',
            $data['tags'] ?? [],
            $data['extra'] ?? []
        );

        // Відновлюємо часи (якщо вони є)
        if (isset($data['created_at'])) {
            $metadata = new class($metadata, $data['created_at'], $data['last_accessed_at'] ?? time()) extends CacheMetadata {
                public function __construct(
                    CacheMetadata $original,
                    int $createdAt,
                    int $lastAccessedAt
                ) {
                    parent::__construct(
                        $original->size,
                        $original->version,
                        $original->tags,
                        $original->extra
                    );
                    $this->createdAt = $createdAt;
                    $this->lastAccessedAt = $lastAccessedAt;
                }
            };
        }

        return $metadata;
    }

    /**
     * Отримання віку кешу в секундах
     *
     * @return int
     */
    public function getAge(): int
    {
        return time() - $this->createdAt;
    }

    /**
     * Отримання часу з останнього доступу в секундах
     *
     * @return int
     */
    public function getTimeSinceLastAccess(): int
    {
        return time() - $this->lastAccessedAt;
    }
}
