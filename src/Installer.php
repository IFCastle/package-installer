<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use IfCastle\Application\Bootloader\BootManager\BootManagerByDirectory;
use IfCastle\Application\Bootloader\BootManager\BootManagerInterface;
use IfCastle\Application\Bootloader\BootManager\Exceptions\PackageAlreadyExists;
use IfCastle\Application\Bootloader\BootManager\Exceptions\PackageNotFound;
use IfCastle\OsUtilities\Safe;

final class Installer extends LibraryInstaller implements DeferredTasksInterface
{
    public const string PREFIX        = '  - ';

    public const string IFCASTLE      = '<bg=bright-blue;options=bold> IfCastle </>';

    /**
     * @var array<array{taskData: array, description: string}>
     */
    private array $deferredTasks = [];

    #[\Override]
    public function supports(string $packageType)
    {
        return \str_starts_with($packageType, 'ifcastle-');
    }

    #[\Override]
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return parent::install($repo, $package)->then(function () use ($package) {

            $extraConfig            = $package->getExtra();

            if ($extraConfig === [] || empty($extraConfig['ifcastle-installer'])) {
                return;
            }

            $packageInstaller       = $this->instantiatePackageInstaller($extraConfig['ifcastle-installer'], $package);

            $this->installOrUpdatePackage($package, $packageInstaller, false);

            $this->io->write(self::PREFIX . self::IFCASTLE . " installed package: <info>{$package->getName()}</info>");
        });
    }

    #[\Override]
    public function update(
        InstalledRepositoryInterface $repo,
        PackageInterface             $initial,
        PackageInterface             $target
    ) {
        return parent::update($repo, $initial, $target)->then(function () use ($target) {

            $extraConfig            = $target->getExtra();

            if ($extraConfig === [] || empty($extraConfig['ifcastle-installer'])) {
                return;
            }

            $packageInstaller       = $this->instantiatePackageInstaller($extraConfig['ifcastle-installer'], $target);

            $this->installOrUpdatePackage($target, $packageInstaller, true);

            $this->io->write(self::PREFIX . self::IFCASTLE . " updated package: <info>{$target->getName()}</info>");
        });
    }

    #[\Override]
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $extraConfig                = $package->getExtra();

        if ($extraConfig === [] || empty($extraConfig['ifcastle-installer'])) {
            return null;
        }

        $packageInstaller           = $this->instantiatePackageInstaller($extraConfig['ifcastle-installer'], $package);

        try {
            $packageInstaller->uninstall();
            $this->io->write(self::PREFIX . self::IFCASTLE . " uninstalled package: <info>{$package->getName()}</info>");
        } catch (PackageNotFound) {
        }

        return parent::uninstall($repo, $package);
    }

    /**
     * @param array<mixed>              $installerConfig
     *
     */
    #[\Override]
    public function addDeferredTask(array $taskData, string $description): void
    {
        $this->deferredTasks[] = [
            'taskData'    => $taskData,
            'description' => $description,
        ];
    }

    #[\Override]
    public function executeDeferredTasks(): void
    {
        if ($this->deferredTasks === []) {
            return;
        }

        // Save tasks to temporary JSON file
        $tasksFile = $this->saveDeferredTasksToFile();

        // Execute tasks in a separate PHP process
        $scriptPath = __DIR__ . '/../bin/apply-deferred-configs.php';
        $projectDir = $this->getProjectDir();

        $command = \sprintf(
            '%s %s %s %s 2>&1',
            \PHP_BINARY,
            \escapeshellarg($scriptPath),
            \escapeshellarg($projectDir),
            \escapeshellarg($tasksFile)
        );

        $this->io->write(self::PREFIX . self::IFCASTLE . ' <info>Executing deferred tasks in separate process...</info>');

        \exec($command, $output, $returnCode);

        // Clean up temporary file
        \unlink($tasksFile);

        // Display output
        foreach ($output as $line) {
            if (\str_contains($line, 'Error:') || \str_contains($line, 'Critical error:')) {
                $this->io->writeError(self::PREFIX . self::IFCASTLE . " <error>$line</error>");
            } else {
                $this->io->write(self::PREFIX . self::IFCASTLE . " $line");
            }
        }

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to execute deferred tasks');
        }

        $this->io->write(self::PREFIX . self::IFCASTLE . ' <info>All deferred tasks completed successfully</info>');

        $this->deferredTasks = [];
    }

    /**
     * Save deferred tasks to a temporary JSON file.
     */
    private function saveDeferredTasksToFile(): string
    {
        $tasksFile = \sys_get_temp_dir() . '/ifcastle_deferred_' . \uniqid() . '.json';

        $data = \json_encode([
            'tasks' => $this->deferredTasks,
            'projectDir' => $this->getProjectDir(),
        ], \JSON_THROW_ON_ERROR);

        \file_put_contents($tasksFile, $data);

        return $tasksFile;
    }

    #[\Override]
    public function hasDeferredTasks(): bool
    {
        return $this->deferredTasks !== [];
    }

    /**
     * @param array<mixed>              $installerConfig
     *
     */
    private function instantiatePackageInstaller(array $installerConfig, PackageInterface $package): PackageInstallerInterface
    {
        if (empty($installerConfig['installer-class'])) {
            return new PackageInstallerDefault(
                $this->instantiateBootManager(),
                new ZeroContext($this->getProjectDir()),
                $this
            )->setConfig($installerConfig, $package->getName());
        }

        $installerClass             = $installerConfig['installer-class'];

        if (!\class_exists($installerClass)) {
            throw new \RuntimeException(
                "Installer class {$installerClass} not found for package {$package->getName()}"
            );
        }

        if (\is_subclass_of($installerClass, PackageInstallerInterface::class)) {
            throw new \RuntimeException(
                "Installer class {$installerClass} must implement PackageInstallerInterface for package {$package->getName()}"
            );
        }

        return new $installerClass($this->instantiateBootManager(), new ZeroContext($this->getProjectDir()), $this);
    }

    private function getProjectDir(): string
    {
        return \realpath($this->vendorDir . '/..');
    }

    private function instantiateBootManager(): BootManagerInterface
    {
        $bootloaderDir              = $this->getProjectDir() . '/bootloader';

        if (!\is_dir($bootloaderDir)) {
            Safe::execute(static fn() => \mkdir($bootloaderDir));
        }

        if (!\is_dir($bootloaderDir)) {
            throw new \RuntimeException('Bootloader directory is not exist: ' . $bootloaderDir);
        }

        $bootManagerFile            = $bootloaderDir . '/boot-manager.php';

        if (\file_exists($bootManagerFile)) {
            return include $bootManagerFile;
        }

        return new BootManagerByDirectory($bootloaderDir);
    }

    private function installOrUpdatePackage(PackageInterface $package, PackageInstallerInterface $packageInstaller, bool $isUpdate): void
    {
        try {
            if ($isUpdate) {
                $packageInstaller->update();
            } else {
                $packageInstaller->install();
            }
        } catch (PackageAlreadyExists) {
            $this->io->write(self::PREFIX . self::IFCASTLE . " <warning>Another package already exists in the loader for {$package->getName()}</warning>");
        }
    }
}
