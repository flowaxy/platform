<?php

/**
 * Приклад Job: Відправка email
 * 
 * @package Engine\System\Queue\Examples
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/Job.php';

final class EmailJob extends Job
{
    private string $to;
    private string $subject;
    private string $body;

    public function __construct(string $to, string $subject, string $body)
    {
        parent::__construct();
        $this->to = $to;
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Обробка завдання
     */
    public function handle(): void
    {
        // Логіка відправки email
        // mail($this->to, $this->subject, $this->body);
        
        if (function_exists('logger')) {
            logger()->logInfo("Email sent to {$this->to}: {$this->subject}");
        }
    }

    /**
     * Визначає, чи потрібно повторити завдання
     */
    public function shouldRetry(): bool
    {
        return $this->attempts < 3;
    }

    /**
     * Повертає затримку перед наступною спробою
     */
    public function retryAfter(): int
    {
        return 60 * ($this->attempts + 1); // 1 хвилина, 2 хвилини, 3 хвилини
    }
}

