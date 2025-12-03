<?php

declare(strict_types=1);

final class PluginLifecycleServiceTest extends TestCase
{
    public function testInstallRunsMigrationsAndCache(): void
    {
        $repo = new FakePluginRepository();
        $filesystem = new FakePluginFilesystem([
            'demo' => [
                'slug' => 'demo',
                'name' => 'Demo',
                'version' => '1.0.0',
            ],
        ]);
        $cache = new FakePluginCache();

        $service = $this->makeLifecycleService($repo, $filesystem, $cache);

        $result = $service->install('demo');

        $this->assertTrue($result);
        $this->assertTrue($filesystem->migrated);
        $this->assertEquals(['demo'], $cache->events['plugin_installed']);
    }

    public function testActivateTriggersCache(): void
    {
        $repo = new FakePluginRepository();
        $repo->install(new Plugin('demo', 'Demo', '1.0.0', false));
        $filesystem = new FakePluginFilesystem();
        $cache = new FakePluginCache();

        $service = $this->makeLifecycleService($repo, $filesystem, $cache);

        $this->assertTrue($service->activate('demo'));
        $this->assertEquals(['demo'], $cache->events['plugin_activated']);
    }

    private function makeLifecycleService(
        PluginRepositoryInterface $repo,
        PluginFilesystemInterface $fs,
        PluginCacheInterface $cache
    ): PluginLifecycleService {
        $install = new InstallPluginService($repo);
        $activate = new ActivatePluginService($repo);
        $deactivate = new DeactivatePluginService($repo);
        $uninstall = new UninstallPluginService($repo);

        return new PluginLifecycleService($fs, $cache, $install, $activate, $deactivate, $uninstall);
    }
}
