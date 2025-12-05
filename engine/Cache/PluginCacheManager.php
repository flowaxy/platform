<?php

declare(strict_types=1);

final class PluginCacheManager implements PluginCacheInterface
{
    public function afterInstall(string $slug): void
    {
        $this->forgetActiveLists();
        $this->clearPlugin($slug);
        $this->clearMenus();
    }

    public function afterActivate(string $slug): void
    {
        $this->forgetActiveLists();
        $this->clearPlugin($slug);
        $this->clearMenus();
    }

    public function afterDeactivate(string $slug): void
    {
        $this->forgetActiveLists();
        $this->clearPlugin($slug);
        $this->clearMenus();
    }

    public function afterUninstall(string $slug): void
    {
        $this->forgetActiveLists();
        $this->clearPlugin($slug);
        $this->clearMenus(true);
    }

    private function forgetActiveLists(): void
    {
        $keys = [
            'active_plugins',
            'active_plugins_hash',
            'active_plugins_list',
        ];

        foreach ($keys as $key) {
            if (function_exists('cache_forget')) {
                cache_forget($key);
            }
        }
    }

    private function clearPlugin(string $slug): void
    {
        if ($slug === '') {
            return;
        }

        if (function_exists('cache_forget')) {
            cache_forget('plugin_data_' . $slug);
        }

        $cacheDir = $this->cacheDir();
        if (! is_dir($cacheDir)) {
            return;
        }

        $patterns = [
            $cacheDir . 'plugin_' . $slug . '_*.cache',
            $cacheDir . $slug . '_*.cache',
        ];

        foreach ($patterns as $pattern) {
            $files = glob($pattern) ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private function clearMenus(bool $full = false): void
    {
        if (function_exists('cache_forget')) {
            cache_forget('active_plugins_hash');
        }

        $cacheDir = $this->cacheDir();
        if (is_dir($cacheDir)) {
            $patterns = [
                $cacheDir . 'admin_menu_items_*.cache',
                $cacheDir . 'active_plugins_hash*.cache',
            ];

            foreach ($patterns as $pattern) {
                $files = glob($pattern) ?: [];
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }

        if ($full) {
            $legacyPatterns = [
                'admin_menu_items_0',
                'admin_menu_items_1',
                'admin_menu_items_0_0',
                'admin_menu_items_0_1',
                'admin_menu_items_1_0',
                'admin_menu_items_1_1',
            ];

            foreach ($legacyPatterns as $pattern) {
                if (function_exists('cache_forget')) {
                    cache_forget($pattern);
                }
            }
        }
    }

    private function cacheDir(): string
    {
        return defined('CACHE_DIR')
            ? CACHE_DIR
            : dirname(__DIR__, 2) . '/storage/cache/';
    }
}
