<?php

/**
 * Обробник роутингу
 *
 * @package Engine\Core\Bootstrap
 */

declare(strict_types=1);

$routerManager = RouterManager::getInstance();
$routerManager->dispatch();
