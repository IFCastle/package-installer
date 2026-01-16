<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

use IfCastle\Application\Bootloader\BootManager\BootManagerByDirectory;
use IfCastle\Application\Bootloader\BootManager\BootManagerInterface;
use IfCastle\Application\EngineRolesEnum;
use IfCastle\Application\Runner;
use IfCastle\Exceptions\CompositeException;
use IfCastle\Exceptions\UnexpectedValueType;
use IfCastle\OsUtilities\Safe;

/**
 * Executes deferred installation tasks in a separate process.
 * This avoids autoloader conflicts between composer.phar and project vendor/.
 */
final class DeferredTaskExecutor
{
    private readonly BootManagerInterface $bootManager;
    private readonly ZeroContext $zeroContext;

    public function __construct(
        private readonly string $projectDir
    ) {
        $this->bootManager  = $this->instantiateBootManager();
        $this->zeroContext  = new ZeroContext($projectDir);
    }

    /**
     * Execute deferred tasks from a JSON file.
     *
     * @throws \Throwable
     */
    public function executeFromFile(string $tasksFile): void
    {
        if (!\file_exists($tasksFile)) {
            throw new \RuntimeException("Tasks file not found: {$tasksFile}");
        }

        $content = \file_get_contents($tasksFile);

        if ($content === false) {
            throw new \RuntimeException("Failed to read tasks file: {$tasksFile}");
        }

        $data = \json_decode($content, true);

        if (!\is_array($data) || empty($data['tasks'])) {
            throw new \RuntimeException("Invalid tasks file format");
        }

        $this->executeTasks($data['tasks']);
    }

    /**
     * Execute tasks and collect exceptions.
     *
     * @param array<array{taskData: array, description: string}> $tasks
     * @throws CompositeException
     */
    private function executeTasks(array $tasks): void
    {
        $collectedExceptions = [];

        foreach ($tasks as $task) {
            $description = $task['description'] ?? 'Unknown task';
            echo "Applying: {$description}\n";

            try {
                $this->executeTask($task['taskData']);
            } catch (\Exception $exception) {
                // Collect regular exceptions
                echo "Error: {$description} - {$exception->getMessage()}\n";
                $collectedExceptions[] = $exception;
            } catch (\Throwable $throwable) {
                // Critical errors - stop immediately
                echo "Critical error: {$description} - {$throwable->getMessage()}\n";
                throw $throwable;
            }
        }

        if ($collectedExceptions !== []) {
            throw new CompositeException(
                'Failed to execute some deferred tasks',
                ...$collectedExceptions
            );
        }
    }

    /**
     * Execute a single task based on its type.
     *
     * @param array{type: string, packageName: string, data: mixed} $taskData
     * @throws \Throwable
     */
    private function executeTask(array $taskData): void
    {
        $type = $taskData['type'] ?? throw new \RuntimeException('Task type is missing');

        match ($type) {
            'main-config' => $this->applyMainConfig($taskData['data']),
            default => throw new \RuntimeException("Unknown task type: {$type}"),
        };
    }

    /**
     * Apply main configuration.
     *
     * @param array<string, array<string, mixed>> $mainConfig
     * @throws \Throwable
     */
    private function applyMainConfig(array $mainConfig): void
    {
        $installer = $this->getInstaller();
        $configurator = $installer->findMainConfigAppender();

        if ($configurator === null) {
            return;
        }

        foreach ($mainConfig as $section => $data) {

            if (!\is_array($data)) {
                continue;
            }

            $config   = $data[PackageInstallerInterface::CONFIG] ?? null;
            $comment  = $data[PackageInstallerInterface::COMMENT] ?? '';

            if ($config === null) {
                continue;
            }

            $configurator->appendSectionIfNotExists($section, $config, $comment);
        }
    }

    /**
     * Get InstallerApplication instance.
     *
     * @throws \Throwable
     */
    private function getInstaller(): InstallerApplication
    {
        $runner = new Runner(
            $this->projectDir,
            InstallerApplication::APP_CODE,
            InstallerApplication::class,
            [EngineRolesEnum::CONSOLE->value],
        );

        $application = $runner->run();

        if (false === $application instanceof InstallerApplication) {
            throw new UnexpectedValueType('InstallerApplication', $application, InstallerApplication::class);
        }

        return $application;
    }

    private function instantiateBootManager(): BootManagerInterface
    {
        $bootloaderDir = $this->projectDir . '/bootloader';

        if (!\is_dir($bootloaderDir)) {
            Safe::execute(static fn() => \mkdir($bootloaderDir));
        }

        if (!\is_dir($bootloaderDir)) {
            throw new \RuntimeException('Bootloader directory does not exist: ' . $bootloaderDir);
        }

        $bootManagerFile = $bootloaderDir . '/boot-manager.php';

        if (\file_exists($bootManagerFile)) {
            return include $bootManagerFile;
        }

        return new BootManagerByDirectory($bootloaderDir);
    }
}
