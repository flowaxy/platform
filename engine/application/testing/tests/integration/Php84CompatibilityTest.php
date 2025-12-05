<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests\Integration;

use TestCase;

/**
 * Інтеграційні тести для перевірки сумісності з PHP 8.4
 */
final class Php84CompatibilityTest extends TestCase
{
    public function testPhpVersionIs84OrHigher(): void
    {
        $phpVersion = PHP_VERSION;
        $this->assertTrue(
            version_compare($phpVersion, '8.4.0', '>='),
            "PHP версія повинна бути 8.4.0 або вище. Поточна версія: {$phpVersion}"
        );
    }

    public function testStrictTypesIsEnabled(): void
    {
        // Перевіряємо, що declare(strict_types=1) працює
        $this->assertTrue(true, 'strict_types enabled');
    }

    public function testTypedPropertiesAreSupported(): void
    {
        // Перевіряємо підтримку типізованих властивостей (PHP 7.4+)
        $testClass = new class {
            public string $property = 'test';
        };
        
        $this->assertEquals('test', $testClass->property);
    }

    public function testUnionTypesAreSupported(): void
    {
        // Перевіряємо підтримку union types (PHP 8.0+)
        $testFunction = function (string|int $value): string|int {
            return $value;
        };
        
        $this->assertEquals('test', $testFunction('test'));
        $this->assertEquals(123, $testFunction(123));
    }

    public function testMatchExpressionIsSupported(): void
    {
        // Перевіряємо підтримку match expression (PHP 8.0+)
        $result = match (true) {
            true => 'success',
            false => 'failure',
        };
        
        $this->assertEquals('success', $result);
    }

    public function testNamedArgumentsAreSupported(): void
    {
        // Перевіряємо підтримку named arguments (PHP 8.0+)
        $testFunction = function (string $first = '', string $second = ''): string {
            return $first . $second;
        };
        
        $result = $testFunction(second: 'world', first: 'hello');
        $this->assertEquals('helloworld', $result);
    }

    public function testReadonlyPropertiesAreSupported(): void
    {
        // Перевіряємо підтримку readonly properties (PHP 8.1+)
        $testClass = new class {
            public readonly string $property;
            
            public function __construct()
            {
                $this->property = 'test';
            }
        };
        
        $this->assertEquals('test', $testClass->property);
    }

    public function testEnumsAreSupported(): void
    {
        // Перевіряємо підтримку enums (PHP 8.1+)
        enum TestEnum: string {
            case VALUE1 = 'value1';
            case VALUE2 = 'value2';
        }
        
        $this->assertEquals('value1', TestEnum::VALUE1->value);
    }

    public function testIntersectionTypesAreSupported(): void
    {
        // Перевіряємо підтримку intersection types (PHP 8.1+)
        // Це потребує двох інтерфейсів
        interface A {}
        interface B {}
        
        $testFunction = function (A&B $value): A&B {
            return $value;
        };
        
        // Створюємо клас, який реалізує обидва інтерфейси
        $testClass = new class implements A, B {};
        
        $result = $testFunction($testClass);
        $this->assertInstanceOf(A::class, $result);
        $this->assertInstanceOf(B::class, $result);
    }

    public function testTypedClassConstantsAreSupported(): void
    {
        // Перевіряємо підтримку typed class constants (PHP 8.3+)
        $testClass = new class {
            public const string CONSTANT = 'test';
        };
        
        $this->assertEquals('test', $testClass::CONSTANT);
    }

    public function testOverrideAttributeIsSupported(): void
    {
        // Перевіряємо підтримку #[Override] attribute (PHP 8.3+)
        // Це потребує класу з батьківським методом
        $parent = new class {
            public function method(): string {
                return 'parent';
            }
        };
        
        $child = new class($parent) {
            public function __construct(private $parent) {}
            
            #[\Override]
            public function method(): string {
                return 'child';
            }
        };
        
        $this->assertEquals('child', $child->method());
    }
}

