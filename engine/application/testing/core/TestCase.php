<?php

declare(strict_types=1);

require_once __DIR__ . '/AssertionFailed.php';

abstract class TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    final public function runTestMethod(string $method): void
    {
        $this->setUp();

        try {
            $this->$method();
        } finally {
            $this->tearDown();
        }
    }

    /**
     * @return array<int,string>
     */
    public function getTestMethods(): array
    {
        $methods = [];
        foreach (get_class_methods($this) as $method) {
            if (str_starts_with($method, 'test')) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        if (! $condition) {
            throw new AssertionFailed($message ?: 'Значення має бути true');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(! $condition, $message ?: 'Значення має бути false');
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $msg = $message ?: sprintf('Очікувалося %s, отримано %s', var_export($expected, true), var_export($actual, true));

            throw new AssertionFailed($msg);
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: 'Очікувалося, що значення будуть ідентичними';

            throw new AssertionFailed($msg);
        }
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            throw new AssertionFailed($message ?: 'Значення не повинно бути null');
        }
    }

    protected function assertInstanceOf(string $class, mixed $value, string $message = ''): void
    {
        if (! $value instanceof $class) {
            $msg = $message ?: sprintf('Очікувався інстанс %s', $class);

            throw new AssertionFailed($msg);
        }
    }
}
