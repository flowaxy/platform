<?php

/**
 * Менеджер для роботи з часовими поясами
 * 
 * Використовує вбудовані PHP функції для роботи з часовими поясами
 * На основі timezone_identifiers_list()
 *
 * @package Engine\Core\Support\Managers
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

require_once __DIR__ . '/../Timezone.php';

class TimezoneManager
{
    private static ?self $instance = null;
    private ?array $timezones = null;
    private ?array $timezoneCache = [];

    /**
     * Конструктор (приватний для Singleton)
     */
    private function __construct()
    {
    }

    /**
     * Отримання екземпляра класу (Singleton)
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Завантаження часових поясів з PHP timezone_identifiers_list()
     *
     * @return array Масив об'єктів Timezone
     */
    private function loadTimezones(): array
    {
        if ($this->timezones !== null) {
            return $this->timezones;
        }

        $identifiers = timezone_identifiers_list();
        $this->timezones = [];

        foreach ($identifiers as $identifier) {
            try {
                $this->timezones[] = new Timezone($identifier);
            } catch (\Exception $e) {
                // Пропускаємо невалідні ідентифікатори
                continue;
            }
        }

        return $this->timezones;
    }

    /**
     * Отримання всіх часових поясів
     *
     * @return array Масив об'єктів Timezone
     */
    public function getAll(): array
    {
        return $this->loadTimezones();
    }

    /**
     * Пошук часового поясу за ідентифікатором
     *
     * @param string $identifier PHP timezone identifier (наприклад, "Europe/Kyiv")
     * @return Timezone|null
     */
    public function find(string $identifier): ?Timezone
    {
        // Перевіряємо кеш
        if (isset($this->timezoneCache[$identifier])) {
            return $this->timezoneCache[$identifier];
        }

        // Перевіряємо, чи існує такий ідентифікатор
        if (!in_array($identifier, timezone_identifiers_list(), true)) {
            return null;
        }

        try {
            $timezone = new Timezone($identifier);
            $this->timezoneCache[$identifier] = $timezone;
            return $timezone;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Пошук часового поясу за значенням (alias для find)
     *
     * @param string $value PHP timezone identifier
     * @return Timezone|null
     */
    public function findByValue(string $value): ?Timezone
    {
        return $this->find($value);
    }

    /**
     * Пошук часового поясу за UTC ідентифікатором (alias для find)
     *
     * @param string $utcIdentifier PHP timezone identifier
     * @return Timezone|null
     */
    public function findByUtcIdentifier(string $utcIdentifier): ?Timezone
    {
        return $this->find($utcIdentifier);
    }

    /**
     * Отримання списку часових поясів для використання в select/dropdown
     *
     * @param string|null $selectedValue Вибране значення
     * @return array Масив ['value' => 'text'] для використання в HTML select
     */
    public function getOptions(?string $selectedValue = null): array
    {
        $timezones = $this->loadTimezones();
        $options = [];

        foreach ($timezones as $timezone) {
            $value = $timezone->getValue();
            $text = $timezone->getText();
            $options[$value] = $text;
        }

        // Сортуємо за текстом
        asort($options);

        return $options;
    }

    /**
     * Отримання списку часових поясів згрупованих за зсувом
     *
     * @return array Масив ['offset' => [Timezone, ...]]
     */
    public function getGroupedByOffset(): array
    {
        $timezones = $this->loadTimezones();
        $grouped = [];

        foreach ($timezones as $timezone) {
            $offset = $timezone->getOffset();
            if (!isset($grouped[$offset])) {
                $grouped[$offset] = [];
            }
            $grouped[$offset][] = $timezone;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Отримання поточного часового поясу з БД
     *
     * @return Timezone|null
     */
    public function getCurrentSystemTimezone(): ?Timezone
    {
        // Використовуємо timezone з БД замість системного
        $timezone = $this->getTimezoneFromDatabase();
        return $this->find($timezone);
    }

    /**
     * Отримання часового поясу з налаштувань БД
     * 
     * Завантажує timezone з бази даних через SettingsManager
     * 
     * @return string Часовий пояс (наприклад, "Europe/Kyiv")
     */
    public function getTimezoneFromDatabase(): string
    {
        $defaultTimezone = 'Europe/Kyiv';
        
        // Отримуємо timezone з налаштувань БД через SettingsManager
        if (class_exists('SettingsManager') && function_exists('settingsManager')) {
            try {
                $settingsManager = settingsManager();
                
                // Очищаємо кеш перед отриманням timezone, щоб гарантувати свіжі дані
                // Це важливо, бо timezone може бути змінено в налаштуваннях
                if (method_exists($settingsManager, 'clearCache')) {
                    $settingsManager->clearCache();
                }
                
                // Примусово перезавантажуємо налаштування з БД
                if (method_exists($settingsManager, 'load')) {
                    $settingsManager->load(true); // force = true для примусового перезавантаження
                } elseif (method_exists($settingsManager, 'reloadSettings')) {
                    $settingsManager->reloadSettings();
                }
                
                // Отримуємо свіжі налаштування
                $allSettings = $settingsManager->all();
                
                if (array_key_exists('timezone', $allSettings)) {
                    $tz = trim($allSettings['timezone']);
                    
                    // Перевіряємо, чи це валідний часовий пояс
                    if (!empty($tz) && in_array($tz, timezone_identifiers_list(), true)) {
                        return $tz;
                    }
                }
            } catch (\Exception $e) {
                // Якщо помилка, використовуємо системний часовий пояс як fallback
                logger()->logWarning('TimezoneManager: Помилка завантаження timezone з налаштувань БД - ' . $e->getMessage(), ['exception' => $e]);
            }
        }
        
        // Якщо не вдалося отримати з БД, використовуємо системний часовий пояс як fallback
        $systemTimezone = date_default_timezone_get();
        if (!empty($systemTimezone) && in_array($systemTimezone, timezone_identifiers_list(), true)) {
            return $systemTimezone;
        }
        
        // Останній fallback - дефолтний часовий пояс
        return $defaultTimezone;
    }

    /**
     * Отримання об'єкта Timezone з налаштувань БД
     * 
     * @return Timezone|null
     */
    public function getTimezoneFromDatabaseAsObject(): ?Timezone
    {
        $timezoneString = $this->getTimezoneFromDatabase();
        return $this->find($timezoneString);
    }

    /**
     * Конвертація дати/часу з одного часового поясу в інший
     *
     * @param string|\DateTime $dateTime Дата/час
     * @param string $fromTimezone З якого часового поясу
     * @param string $toTimezone В який часовий пояс
     * @param string|null $format Формат виводу (якщо null, повертається DateTime)
     * @return string|\DateTime
     */
    public function convert($dateTime, string $fromTimezone, string $toTimezone, ?string $format = null)
    {
        if (is_string($dateTime)) {
            $dt = new \DateTime($dateTime, new \DateTimeZone($fromTimezone));
        } elseif ($dateTime instanceof \DateTime) {
            $dt = clone $dateTime;
            $dt->setTimezone(new \DateTimeZone($fromTimezone));
        } else {
            throw new \InvalidArgumentException('Невірний тип дати/часу');
        }

        $dt->setTimezone(new \DateTimeZone($toTimezone));

        if ($format !== null) {
            return $dt->format($format);
        }

        return $dt;
    }

    /**
     * Конвертація дати/часу з UTC в часовий пояс з БД
     *
     * @param string|\DateTime $dateTime Дата/час в UTC
     * @param string|null $format Формат виводу (якщо null, повертається DateTime)
     * @return string|\DateTime
     */
    public function convertFromUtc($dateTime, ?string $format = null)
    {
        // Використовуємо timezone з БД замість системного
        $timezone = $this->getTimezoneFromDatabase();
        return $this->convert($dateTime, 'UTC', $timezone, $format);
    }

    /**
     * Конвертація дати/часу з часового поясу з БД в UTC
     *
     * @param string|\DateTime $dateTime Дата/час в часовому поясі з БД
     * @param string|null $format Формат виводу (якщо null, повертається DateTime)
     * @return string|\DateTime
     */
    public function convertToUtc($dateTime, ?string $format = null)
    {
        // Використовуємо timezone з БД замість системного
        $timezone = $this->getTimezoneFromDatabase();
        return $this->convert($dateTime, $timezone, 'UTC', $format);
    }

    /**
     * Очищення кешу завантажених часових поясів
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->timezones = null;
        $this->timezoneCache = [];
    }
}
