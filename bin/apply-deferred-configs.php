#!/usr/bin/env php
<?php

declare(strict_types=1);

// Validate arguments
if ($argc < 3) {
    fwrite(STDERR, "Usage: apply-deferred-configs.php <project-dir> <tasks-file>\n");
    exit(1);
}

$projectDir = $argv[1];
$tasksFile = $argv[2];

// Check project directory exists
if (!\is_dir($projectDir)) {
    fwrite(STDERR, "Error: Project directory not found: {$projectDir}\n");
    exit(1);
}

// Load project autoloader (NOT composer.phar!)
$autoloadPath = $projectDir . '/vendor/autoload.php';

if (!\file_exists($autoloadPath)) {
    fwrite(STDERR, "Error: Autoloader not found: {$autoloadPath}\n");
    exit(1);
}

require_once $autoloadPath;

use IfCastle\PackageInstaller\DeferredTaskExecutor;

try {
    $executor = new DeferredTaskExecutor($projectDir);
    $executor->executeFromFile($tasksFile);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
