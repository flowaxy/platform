<?php

/**
 * Клас для представлення часового поясу
 * 
 * Містить інформацію про часовий пояс на основі PHP timezone identifier
 *
 * @package Engine\Core\Support
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Timezone
{
    private string $identifier;
    private ?\DateTimeZone $dateTimeZone = null;

    /**
     * Конструктор
     *
     * @param string $identifier PHP timezone identifier (наприклад, "Europe/Kyiv")
     */
    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
        try {
            $this->dateTimeZone = new \DateTimeZone($identifier);
        } catch (\Exception $e) {
            // Якщо невалідний ідентифікатор, використовуємо UTC
            $this->identifier = 'UTC';
            $this->dateTimeZone = new \DateTimeZone('UTC');
        }
    }

    /**
     * Отримання PHP timezone identifier
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->identifier;
    }

    /**
     * Отримання зсуву від UTC (в секундах) для поточного моменту
     *
     * @return int
     */
    public function getOffset(): int
    {
        $now = new \DateTime('now', $this->dateTimeZone);
        return $this->dateTimeZone->getOffset($now);
    }

    /**
     * Отримання зсуву від UTC (в годинах) для поточного моменту
     *
     * @return float
     */
    public function getOffsetHours(): float
    {
        return $this->getOffset() / 3600;
    }

    /**
     * Форматування зсуву для відображення (наприклад, "+02:00" або "-05:00")
     *
     * @return string
     */
    public function getFormattedOffset(): string
    {
        $offset = $this->getOffset();
        $hours = (int)floor(abs($offset) / 3600);
        $minutes = (int)((abs($offset) % 3600) / 60);
        $sign = $offset >= 0 ? '+' : '-';
        
        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    /**
     * Отримання текстового опису для відображення
     * Формат: (UTC+02:00) Europe/Kyiv
     *
     * @return string
     */
    public function getText(): string
    {
        $offset = $this->getFormattedOffset();
        return sprintf('(UTC%s) %s', $offset, $this->identifier);
    }

    /**
     * Отримання назви міста/регіону (частина після слеша)
     *
     * @return string
     */
    public function getLocation(): string
    {
        $parts = explode('/', $this->identifier);
        return end($parts);
    }

    /**
     * Отримання континенту (частина до слеша)
     *
     * @return string
     */
    public function getContinent(): string
    {
        $parts = explode('/', $this->identifier);
        return $parts[0] ?? '';
    }

    /**
     * Перевірка, чи є літній час (DST) для поточного моменту
     *
     * @return bool
     */
    public function isDst(): bool
    {
        $now = new \DateTime('now', $this->dateTimeZone);
        $transitions = $this->dateTimeZone->getTransitions($now->getTimestamp(), $now->getTimestamp());
        
        if (!empty($transitions)) {
            return (bool)($transitions[0]['isdst'] ?? false);
        }
        
        return false;
    }

    /**
     * Отримання DateTimeZone об'єкта
     *
     * @return \DateTimeZone
     */
    public function getDateTimeZone(): \DateTimeZone
    {
        return $this->dateTimeZone;
    }

    /**
     * Отримання даних у вигляді масиву
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->identifier,
            'offset' => $this->getOffset(),
            'offset_hours' => $this->getOffsetHours(),
            'formatted_offset' => $this->getFormattedOffset(),
            'text' => $this->getText(),
            'location' => $this->getLocation(),
            'continent' => $this->getContinent(),
            'isdst' => $this->isDst(),
        ];
    }
}
