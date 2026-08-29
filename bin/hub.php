#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../../../../vendor/autoload.php', // running from the workspace root
    __DIR__ . '/../vendor/autoload.php',          // running the package standalone
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        break;
    }
}

use PhpModern\PushHub\HubServer;

$host = $argv[1] ?? '127.0.0.1';
$port = isset($argv[2]) ? (int) $argv[2] : 8081;

(new HubServer($host, $port))->run();
