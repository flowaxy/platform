<?php

declare(strict_types=1);

namespace Flowaxy\Core\System\Hooks;

enum HookType: string
{
    case Action = 'action';
    case Filter = 'filter';
}
