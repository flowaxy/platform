<?php

declare(strict_types=1);

final class ServiceConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flowaxy-tests-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    public function testOverrideMergesConfig(): void
    {
        $base = $this->tempDir . '/base.php';
        $override = $this->tempDir . '/override.php';

        file_put_contents($base, "<?php return ['singletons' => ['foo' => 'Foo']];");
        file_put_contents($override, "<?php return ['singletons' => ['foo' => 'Bar']];");

        $config = ServiceConfig::load($base, $override);

        $this->assertEquals('Bar', $config['singletons']['foo']);
    }
}
