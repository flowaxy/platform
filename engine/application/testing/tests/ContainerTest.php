<?php

declare(strict_types=1);

namespace Flowaxy\Core\Application\Testing\Tests;

use Flowaxy\Core\System\Container;
use TestCase;

final class ContainerTest extends TestCase
{
    public function testSingletonReturnsSameInstance(): void
    {
        $container = new Container();
        $container->singleton(stdClass::class, static fn () => new stdClass());

        $first = $container->make(stdClass::class);
        $second = $container->make(stdClass::class);

        $this->assertSame($first, $second, 'Singleton повинен повертати один і той самий інстанс');
    }

    public function testBindResolvesConcrete(): void
    {
        $container = new Container();
        $container->bind('cache', static fn () => new ArrayObject());

        $cache = $container->make('cache');

        $this->assertInstanceOf(ArrayObject::class, $cache);
    }
}
