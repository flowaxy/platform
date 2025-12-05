<?php

/**
 * Система тегів для сервісів DI Container
 *
 * Дозволяє групувати сервіси за тегами та отримувати їх за тегами
 *
 * @package Flowaxy\Core\System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Flowaxy\Core\System;

final class ServiceTags
{
    /**
     * @var array<string, array<string>>
     */
    private array $tags = [];

    /**
     * Додавання тегу до сервісу
     *
     * @param string $serviceName Назва сервісу
     * @param string|array<string> $tags Тег або масив тегів
     * @return void
     */
    public function tag(string $serviceName, string|array $tags): void
    {
        $tagsArray = is_array($tags) ? $tags : [$tags];

        foreach ($tagsArray as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            if (!in_array($serviceName, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $serviceName;
            }
        }
    }

    /**
     * Отримання всіх сервісів з вказаним тегом
     *
     * @param string $tag Тег
     * @return array<string> Масив назв сервісів
     */
    public function getTagged(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Отримання всіх тегів для сервісу
     *
     * @param string $serviceName Назва сервісу
     * @return array<string> Масив тегів
     */
    public function getTags(string $serviceName): array
    {
        $result = [];

        foreach ($this->tags as $tag => $services) {
            if (in_array($serviceName, $services, true)) {
                $result[] = $tag;
            }
        }

        return $result;
    }

    /**
     * Перевірка наявності тегу у сервісу
     *
     * @param string $serviceName Назва сервісу
     * @param string $tag Тег
     * @return bool
     */
    public function hasTag(string $serviceName, string $tag): bool
    {
        return isset($this->tags[$tag]) && in_array($serviceName, $this->tags[$tag], true);
    }

    /**
     * Видалення тегу з сервісу
     *
     * @param string $serviceName Назва сервісу
     * @param string $tag Тег
     * @return void
     */
    public function removeTag(string $serviceName, string $tag): void
    {
        if (isset($this->tags[$tag])) {
            $this->tags[$tag] = array_values(array_filter(
                $this->tags[$tag],
                fn(string $service) => $service !== $serviceName
            ));

            if (empty($this->tags[$tag])) {
                unset($this->tags[$tag]);
            }
        }
    }

    /**
     * Видалення всіх тегів сервісу
     *
     * @param string $serviceName Назва сервісу
     * @return void
     */
    public function removeAllTags(string $serviceName): void
    {
        foreach ($this->tags as $tag => $services) {
            $this->removeTag($serviceName, $tag);
        }
    }

    /**
     * Отримання всіх тегів
     *
     * @return array<string, array<string>>
     */
    public function getAllTags(): array
    {
        return $this->tags;
    }

    /**
     * Очищення всіх тегів
     *
     * @return void
     */
    public function flush(): void
    {
        $this->tags = [];
    }
}
