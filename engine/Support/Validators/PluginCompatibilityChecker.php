<?php

/**
 * Перевірка сумісності плагінів з ядром
 *
 * Перевіряє версію CMS, PHP, залежності та інші вимоги сумісності.
 *
 * @package Flowaxy\Core\Support\Validators
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\Support\Validators;

final class PluginCompatibilityChecker
{
    /**
     * Версія ядра CMS
     *
     * @var string
     */
    private static string $coreVersion = '1.0.0';

    /**
     * Мінімальна версія PHP
     *
     * @var string
     */
    private static string $minPhpVersion = '8.4.0';

    /**
     * Встановлення версії ядра
     *
     * @param string $version
     * @return void
     */
    public static function setCoreVersion(string $version): void
    {
        self::$coreVersion = $version;
    }

    /**
     * Отримання версії ядра
     *
     * @return string
     */
    public static function getCoreVersion(): string
    {
        return self::$coreVersion;
    }

    /**
     * Встановлення мінімальної версії PHP
     *
     * @param string $version
     * @return void
     */
    public static function setMinPhpVersion(string $version): void
    {
        self::$minPhpVersion = $version;
    }

    /**
     * Повна перевірка сумісності плагіна
     *
     * @param array<string, mixed> $pluginConfig Конфігурація плагіна
     * @return array<string, mixed> Результат перевірки
     */
    public static function checkCompatibility(array $pluginConfig): array
    {
        $result = [
            'compatible' => true,
            'errors' => [],
            'warnings' => [],
            'checks' => [],
        ];

        // Перевірка версії CMS
        $cmsCheck = self::checkCmsVersion($pluginConfig);
        $result['checks']['cms_version'] = $cmsCheck;
        if (!$cmsCheck['compatible']) {
            $result['compatible'] = false;
            $result['errors'][] = $cmsCheck['message'];
        }

        // Перевірка версії PHP
        $phpCheck = self::checkPhpVersion($pluginConfig);
        $result['checks']['php_version'] = $phpCheck;
        if (!$phpCheck['compatible']) {
            $result['compatible'] = false;
            $result['errors'][] = $phpCheck['message'];
        }

        // Перевірка PHP розширень
        $extensionsCheck = self::checkPhpExtensions($pluginConfig);
        $result['checks']['php_extensions'] = $extensionsCheck;
        if (!$extensionsCheck['compatible']) {
            $result['compatible'] = false;
            $result['errors'] = array_merge($result['errors'], $extensionsCheck['errors']);
        }

        // Перевірка необхідних функцій
        $functionsCheck = self::checkRequiredFunctions($pluginConfig);
        $result['checks']['required_functions'] = $functionsCheck;
        if (!$functionsCheck['compatible']) {
            $result['warnings'] = array_merge($result['warnings'], $functionsCheck['warnings']);
        }

        // Перевірка необхідних класів
        $classesCheck = self::checkRequiredClasses($pluginConfig);
        $result['checks']['required_classes'] = $classesCheck;
        if (!$classesCheck['compatible']) {
            $result['warnings'] = array_merge($result['warnings'], $classesCheck['warnings']);
        }

        return $result;
    }

    /**
     * Перевірка сумісності версії CMS
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function checkCmsVersion(array $pluginConfig): array
    {
        $requiredVersion = $pluginConfig['min_cms_version'] ?? $pluginConfig['requires'] ?? null;

        if ($requiredVersion === null) {
            return [
                'compatible' => true,
                'message' => 'Версія CMS не вказана',
                'required' => null,
                'current' => self::$coreVersion,
            ];
        }

        $compatible = version_compare(self::$coreVersion, $requiredVersion, '>=');

        return [
            'compatible' => $compatible,
            'message' => $compatible
                ? "Сумісний з CMS версією {$requiredVersion}"
                : "Потрібна CMS версія {$requiredVersion} або вища (поточна: " . self::$coreVersion . ")",
            'required' => $requiredVersion,
            'current' => self::$coreVersion,
        ];
    }

    /**
     * Перевірка сумісності версії PHP
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function checkPhpVersion(array $pluginConfig): array
    {
        $requiredVersion = $pluginConfig['requires_php'] ?? $pluginConfig['php_version'] ?? self::$minPhpVersion;
        $currentVersion = PHP_VERSION;

        $compatible = version_compare($currentVersion, $requiredVersion, '>=');

        return [
            'compatible' => $compatible,
            'message' => $compatible
                ? "Сумісний з PHP версією {$requiredVersion}"
                : "Потрібна PHP версія {$requiredVersion} або вища (поточна: {$currentVersion})",
            'required' => $requiredVersion,
            'current' => $currentVersion,
        ];
    }

    /**
     * Перевірка необхідних PHP розширень
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function checkPhpExtensions(array $pluginConfig): array
    {
        $requiredExtensions = $pluginConfig['requires_extensions'] ?? $pluginConfig['extensions'] ?? [];

        if (empty($requiredExtensions) || !is_array($requiredExtensions)) {
            return [
                'compatible' => true,
                'errors' => [],
                'missing' => [],
            ];
        }

        $missing = [];
        $errors = [];

        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
                $errors[] = "Потрібне PHP розширення: {$extension}";
            }
        }

        return [
            'compatible' => empty($missing),
            'errors' => $errors,
            'missing' => $missing,
            'required' => $requiredExtensions,
        ];
    }

    /**
     * Перевірка необхідних функцій
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function checkRequiredFunctions(array $pluginConfig): array
    {
        $requiredFunctions = $pluginConfig['requires_functions'] ?? $pluginConfig['functions'] ?? [];

        if (empty($requiredFunctions) || !is_array($requiredFunctions)) {
            return [
                'compatible' => true,
                'warnings' => [],
                'missing' => [],
            ];
        }

        $missing = [];
        $warnings = [];

        foreach ($requiredFunctions as $function) {
            if (!function_exists($function)) {
                $missing[] = $function;
                $warnings[] = "Функція недоступна: {$function}";
            }
        }

        return [
            'compatible' => empty($missing),
            'warnings' => $warnings,
            'missing' => $missing,
            'required' => $requiredFunctions,
        ];
    }

    /**
     * Перевірка необхідних класів
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function checkRequiredClasses(array $pluginConfig): array
    {
        $requiredClasses = $pluginConfig['requires_classes'] ?? $pluginConfig['classes'] ?? [];

        if (empty($requiredClasses) || !is_array($requiredClasses)) {
            return [
                'compatible' => true,
                'warnings' => [],
                'missing' => [],
            ];
        }

        $missing = [];
        $warnings = [];

        foreach ($requiredClasses as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                $missing[] = $class;
                $warnings[] = "Клас/інтерфейс недоступний: {$class}";
            }
        }

        return [
            'compatible' => empty($missing),
            'warnings' => $warnings,
            'missing' => $missing,
            'required' => $requiredClasses,
        ];
    }

    /**
     * Перевірка тестової версії CMS
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function checkTestedVersion(array $pluginConfig): array
    {
        $testedVersion = $pluginConfig['tested'] ?? $pluginConfig['tested_cms_version'] ?? null;

        if ($testedVersion === null) {
            return [
                'tested' => false,
                'message' => 'Версія CMS не протестована',
                'tested_version' => null,
                'current' => self::$coreVersion,
            ];
        }

        $tested = version_compare(self::$coreVersion, $testedVersion, '<=');

        return [
            'tested' => $tested,
            'message' => $tested
                ? "Протестовано з CMS версією {$testedVersion}"
                : "Протестовано з CMS версією {$testedVersion} (поточна: " . self::$coreVersion . ")",
            'tested_version' => $testedVersion,
            'current' => self::$coreVersion,
        ];
    }

    /**
     * Отримання детального звіту про сумісність
     *
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    public static function getCompatibilityReport(array $pluginConfig): array
    {
        $check = self::checkCompatibility($pluginConfig);
        $tested = self::checkTestedVersion($pluginConfig);

        return [
            'compatible' => $check['compatible'],
            'tested' => $tested['tested'],
            'errors' => $check['errors'],
            'warnings' => $check['warnings'],
            'cms_version' => $check['checks']['cms_version'],
            'php_version' => $check['checks']['php_version'],
            'php_extensions' => $check['checks']['php_extensions'],
            'required_functions' => $check['checks']['required_functions'],
            'required_classes' => $check['checks']['required_classes'],
            'tested_version' => $tested,
        ];
    }
}
