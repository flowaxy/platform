<?php

declare(strict_types=1);

final class UpdateThemeSettingsServiceTest extends TestCase
{
    public function testRejectsUnknownTheme(): void
    {
        $themes = new FakeThemeRepository();
        $settings = new FakeThemeSettingsRepository();
        $service = new UpdateThemeSettingsService($settings, $themes);

        $this->assertFalse($service->execute('missing', ['color' => 'red']));
    }

    public function testUpdatesSettingsAndClearsCache(): void
    {
        $themes = new FakeThemeRepository();
        $themes->themes['demo'] = new Theme('demo', 'Demo', '1.0.0', 'Demo theme', true);
        $settings = new FakeThemeSettingsRepository();
        $service = new UpdateThemeSettingsService($settings, $themes);

        $result = $service->execute('demo', ['color' => 'blue', 'layout' => 'wide']);

        $this->assertTrue($result);
        $this->assertEquals('blue', $settings->getValue('demo', 'color'));
        $this->assertTrue($settings->cacheCleared);
    }
}
