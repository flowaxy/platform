<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\Infrastructure\Security\Security;
use TestCase;

/**
 * Тести для Security
 */
final class SecurityTest extends TestCase
{
    public function testCleanEscapesHtml(): void
    {
        $input = '<script>alert("XSS")</script>';
        $output = Security::clean($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testCleanStrictRemovesHtml(): void
    {
        $input = '<p>Hello <strong>World</strong></p>';
        $output = Security::clean($input, true);

        $this->assertStringNotContainsString('<p>', $output);
        $this->assertStringNotContainsString('<strong>', $output);
        $this->assertEquals('Hello World', $output);
    }

    public function testSanitizeEmailFiltersEmail(): void
    {
        $input = 'test@example.com<script>';
        $output = Security::sanitize($input, 'email');

        $this->assertEquals('test@example.com', $output);
    }

    public function testSanitizeUrlFiltersUrl(): void
    {
        $input = 'https://example.com<script>';
        $output = Security::sanitize($input, 'url');

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testIsValidEmailValidatesEmail(): void
    {
        $this->assertTrue(Security::isValidEmail('test@example.com'));
        $this->assertFalse(Security::isValidEmail('invalid-email'));
    }

    public function testIsValidUrlValidatesUrl(): void
    {
        $this->assertTrue(Security::isValidUrl('https://example.com'));
        $this->assertFalse(Security::isValidUrl('not-a-url'));
    }

    public function testGetClientIpReturnsIp(): void
    {
        $ip = Security::getClientIp();
        $this->assertNotEmpty($ip);
        $this->assertTrue(Security::isValidIp($ip));
    }
}
