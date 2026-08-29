#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * See packages/core/queue/bin/worker.php for why this searches from getcwd()
 * first: a path-repository install junctions this file into the consuming
 * project's vendor tree, so __DIR__ alone can resolve to this file's
 * physical location inside the phpmodern monorepo instead.
 */
function phpmodern_find_upwards(string $startDir, string $relative): ?string
{
    $dir = $startDir;

    for ($i = 0; $i < 10; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . $relative;
        if (is_file($candidate)) {
            return $candidate;
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }

        $dir = $parent;
    }

    return null;
}

$autoload = phpmodern_find_upwards(getcwd(), 'vendor/autoload.php');

if ($autoload === null) {
    foreach ([__DIR__ . '/../../../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php'] as $candidate) {
        if (is_file($candidate)) {
            $autoload = $candidate;

            break;
        }
    }
}

if ($autoload === null) {
    fwrite(STDERR, "Could not locate vendor/autoload.php — run this from your project (or run composer install first).\n");
    exit(1);
}

require $autoload;

use PhpModern\PushHub\HubServer;

$host = $argv[1] ?? '127.0.0.1';
$port = isset($argv[2]) ? (int) $argv[2] : 8081;

(new HubServer($host, $port))->run();
