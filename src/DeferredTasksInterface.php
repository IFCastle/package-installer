<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

/**
 * Interface for managing deferred installation tasks.
 *
 * This allows package installers to register tasks that must be executed
 * after all packages have been installed (e.g., config merging that depends on other packages).
 */
interface DeferredTasksInterface
{
    /**
     * Register a deferred task to be executed after all packages are installed.
     *
     * @param array{type: string, packageName: string, data: mixed} $taskData The serializable task data
     * @param string $description Description of the task for logging
     */
    public function addDeferredTask(array $taskData, string $description): void;

    /**
     * Execute all deferred tasks.
     *
     * @throws \Throwable
     */
    public function executeDeferredTasks(): void;

    /**
     * Check if there are any deferred tasks.
     */
    public function hasDeferredTasks(): bool;
}
