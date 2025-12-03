<?php

declare(strict_types=1);

final class Console
{
    public static function write(string $message, ?string $color = null): void
    {
        fwrite(STDOUT, self::colorize($message, $color) . PHP_EOL);
    }

    public static function colorize(string $message, ?string $color = null): string
    {
        $colors = [
            'green' => "\033[32m",
            'red' => "\033[31m",
            'yellow' => "\033[33m",
            'cyan' => "\033[36m",
            'reset' => "\033[0m",
        ];

        if ($color === null || stripos(PHP_OS, 'WIN') === 0) {
            return $message;
        }

        return ($colors[$color] ?? '') . $message . $colors['reset'];
    }
}
