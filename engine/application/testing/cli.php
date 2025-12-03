#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/core/TestRunner.php';

$options = getopt('', ['filter::', 'list', 'help']);

if (isset($options['help'])) {
    echo "Flowaxy Test Runner\n";
    echo "Usage:\n";
    echo "  php engine/application/testing/cli.php [--filter=Hook] [--list]\n";
    exit(0);
}

$filter = $options['filter'] ?? null;
$runner = new TestRunner(__DIR__ . '/tests', $filter);

if (isset($options['list'])) {
    $runner->listTests();
    exit(0);
}

$success = $runner->run();
exit($success ? 0 : 1);
