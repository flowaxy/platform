<?php

declare(strict_types=1);

enum HookType: string
{
    case Action = 'action';
    case Filter = 'filter';
}
