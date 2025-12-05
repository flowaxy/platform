<?php

/**
 * Приклад слухача: Відправка привітального email
 * 
 * @package Engine\System\Events\Examples
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/EventListener.php';
require_once dirname(__DIR__) . '/examples/UserRegisteredEvent.php';

final class SendWelcomeEmailListener extends EventListener
{
    private int $priority = 10;

    public function handle(Event $event): void
    {
        if (!$event instanceof UserRegisteredEvent) {
            return;
        }

        // Логіка відправки привітального email
        $userId = $event->getUserId();
        $email = $event->getEmail();
        $username = $event->getUsername();

        // Тут буде логіка відправки email
        // mail($email, 'Welcome!', "Hello {$username}!");
        
        if (function_exists('logger')) {
            logger()->logInfo("Welcome email sent to user {$userId} ({$email})");
        }
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function shouldHandle(Event $event): bool
    {
        return $event instanceof UserRegisteredEvent;
    }
}

