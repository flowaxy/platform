<?php

/**
 * Генератор CSP (Content Security Policy) політики
 * 
 * @package Engine\Infrastructure\Security
 * @version 1.0.0
 */

declare(strict_types=1);

final class CSPGenerator
{
    private array $directives = [];

    /**
     * Додавання директиви CSP
     * 
     * @param string $directive Назва директиви
     * @param string|array<string> $sources Джерела
     * @return self
     */
    public function addDirective(string $directive, string|array $sources): self
    {
        if (is_string($sources)) {
            $sources = [$sources];
        }

        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = [];
        }

        $this->directives[$directive] = array_merge($this->directives[$directive], $sources);
        $this->directives[$directive] = array_unique($this->directives[$directive]);

        return $this;
    }

    /**
     * Генерація CSP заголовка
     * 
     * @return string
     */
    public function generate(): string
    {
        $parts = [];

        foreach ($this->directives as $directive => $sources) {
            if (!empty($sources)) {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Встановлення стандартної політики
     * 
     * @return self
     */
    public function setDefaultPolicy(): self
    {
        $this->addDirective('default-src', ["'self'"]);
        $this->addDirective('script-src', ["'self'", "'unsafe-inline'", "'unsafe-eval'"]);
        $this->addDirective('style-src', ["'self'", "'unsafe-inline'"]);
        $this->addDirective('img-src', ["'self'", 'data:', 'https:']);
        $this->addDirective('font-src', ["'self'", 'data:']);
        $this->addDirective('connect-src', ["'self'"]);
        $this->addDirective('frame-ancestors', ["'none'"]);

        return $this;
    }

    /**
     * Очищення всіх директив
     * 
     * @return self
     */
    public function clear(): self
    {
        $this->directives = [];
        return $this;
    }
}

