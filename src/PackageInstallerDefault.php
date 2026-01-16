<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

use IfCastle\Application\Bootloader\BootManager\BootManagerInterface;
use IfCastle\Application\Bootloader\Builder\ZeroContextInterface;
use IfCastle\Application\EngineRolesEnum;
use IfCastle\Application\Runner;
use IfCastle\Exceptions\UnexpectedValueType;
use IfCastle\ServiceManager\ServiceDescriptor;

final class PackageInstallerDefault implements PackageInstallerInterface
{
    private readonly BootManagerInterface $bootManager;
    private readonly ZeroContextInterface $zeroContext;
    private readonly DeferredTasksInterface $deferredTasks;

    /**
     * @var array<string, mixed>
     */
    private array $config           = [];

    private string $packageName      = '';

    private InstallerApplication|null $installerApplication = null;

    public function __construct(
        BootManagerInterface $bootManager,
        ZeroContextInterface $zeroContext,
        DeferredTasksInterface $deferredTasks,
    ) {
        $this->bootManager   = $bootManager;
        $this->zeroContext   = $zeroContext;
        $this->deferredTasks = $deferredTasks;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return $this
     */
    public function setConfig(array $config, string $packageName): self
    {
        $this->config               = $config;
        $this->packageName          = $packageName;

        if (!empty($config[self::PACKAGE]) && !empty($config[self::PACKAGE][self::NAME])) {
            $this->packageName      = $config[self::PACKAGE][self::NAME];
        }

        return $this;
    }

    #[\Override]
    public function install(): void
    {
        $this->addOrUpdatePackage();
    }

    private function addOrUpdatePackage(bool $isUpdate = false): void
    {
        $installerConfig            = $this->config;

        if (!empty($installerConfig[self::PACKAGE])) {

            if (!empty($installerConfig[self::PACKAGE][self::GROUPS])
                && !empty($installerConfig[self::PACKAGE][self::BOOTLOADERS])) {
                throw new \RuntimeException("Group and Bootloaders cannot be defined at the same time for package {$this->packageName}");
            }

            if (!empty($installerConfig[self::PACKAGE][self::GROUPS])) {
                $this->addOrUpdateBootloaders($installerConfig[self::PACKAGE][self::GROUPS], $isUpdate);
            } elseif (!empty($installerConfig[self::PACKAGE][self::BOOTLOADERS])) {
                $this->addOrUpdateBootloaders([$installerConfig[self::PACKAGE]], $isUpdate);
            } else {
                throw new \RuntimeException("Bootloaders or Groups must be defined for package {$this->packageName}");
            }

            if (!empty($installerConfig[self::PACKAGE][self::MAIN_CONFIG])) {
                // Defer the main config application until all packages are installed
                $mainConfig = $installerConfig[self::PACKAGE][self::MAIN_CONFIG];
                $packageName = $this->packageName;

                $this->deferredTasks->addDeferredTask(
                    [
                        'type' => 'main-config',
                        'packageName' => $packageName,
                        'data' => $mainConfig,
                    ],
                    "Applying main config for package: {$packageName}"
                );
            }
        }

        if (!empty($installerConfig[self::SERVICES]) && \is_array($installerConfig[self::SERVICES])) {
            $this->addOrUpdateServices($installerConfig[self::SERVICES], $isUpdate);
        }
    }

    #[\Override]
    public function update(): void
    {
        $this->addOrUpdatePackage(true);
    }

    #[\Override]
    public function uninstall(): void
    {
        if (!empty($this->config[self::SERVICES]) && \is_array($this->config[self::SERVICES])) {
            $this->uninstallServices($this->config[self::SERVICES]);
        }

        $this->bootManager->removeComponent($this->packageName);
    }

    /**
     * @throws \Throwable
     */
    private function getInstaller(): InstallerApplication
    {
        if ($this->installerApplication === null) {

            $runner                 = new Runner(
                $this->zeroContext->getApplicationDirectory(),
                InstallerApplication::APP_CODE,
                InstallerApplication::class,
                [EngineRolesEnum::CONSOLE->value],
            );

            $application            = $runner->run();

            if (false === $application instanceof InstallerApplication) {
                throw new UnexpectedValueType('InstallerApplication', $application, InstallerApplication::class);
            }

            $this->installerApplication = $application;
        }

        return $this->installerApplication;
    }

    /**
     * @param array<array<string, mixed>> $bootloaderGroups
     */
    private function addOrUpdateBootloaders(array $bootloaderGroups, bool $isUpdate): void
    {
        if ($isUpdate) {
            $component              = $this->bootManager->getComponent($this->packageName);
            // Remove all groups
            foreach (\array_keys($component->getGroups()) as $groupId) {
                $component->deleteGroup($groupId);
            }
        } else {
            $component              = $this->bootManager->createComponent($this->packageName);
        }

        foreach ($bootloaderGroups as $group) {
            $component->add(
                bootloaders : $group[self::BOOTLOADERS],
                applications: $group[self::APPLICATIONS] ?? [],
                runtimeTags : $group[self::RUNTIME_TAGS] ?? [],
                excludeTags : $group[self::EXCLUDE_TAGS] ?? [],
                isActive    : $group[self::IS_ACTIVE] ?? true,
                group       : $group[self::GROUP] ?? null,
            );
        }

        if ($isUpdate) {
            $this->bootManager->updateComponent($component);
        } else {
            $this->bootManager->addComponent($component);
        }
    }

    /**
     * @param array<array<string, mixed>> $services
     */
    private function addOrUpdateServices(array $services, bool $isUpdate): void
    {
        $serviceManager             = $this->getInstaller()->getServiceManager();

        foreach ($services as $serviceConfig) {

            $serviceName            = $serviceConfig[Service::NAME] ?? throw new \RuntimeException('Service name is not defined');

            $serviceDescriptor      = new ServiceDescriptor(
                serviceName  : $serviceName,
                className    : $serviceConfig[Service::CLASS_NAME]  ?? throw new \RuntimeException("Service class is not found for service $serviceName"),
                isActive     : $serviceConfig[Service::IS_ACTIVE]   ?? false,
                config       : $serviceConfig[Service::CONFIG]      ?? [],
                includeTags  : $serviceConfig[Service::TAGS]        ?? [],
                excludeTags  : $serviceConfig[Service::EXCLUDE_TAGS] ?? [],
            );

            if ($isUpdate) {
                $serviceManager->updateServiceConfig($serviceDescriptor);
            } else {
                $serviceManager->installService($serviceDescriptor);
            }
        }
    }

    /**
     * @param array<array<string, mixed>> $services
     */
    private function uninstallServices(array $services): void
    {
        $serviceManager             = $this->getInstaller()->getServiceManager();

        foreach ($services as $serviceConfig) {
            $serviceName            = $serviceConfig[Service::NAME] ?? '';

            if ($serviceName === '') {
                continue;
            }

            try {
                $serviceManager->uninstallService($serviceName, $this->packageName);
            } catch (\Exception $exception) {
                echo "Error uninstalling service $serviceName: {$exception->getMessage()}\n";
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $mainConfig
     * @throws \Throwable
     */
    private function appendMainConfig(array $mainConfig): void
    {
        $configurator               = $this->getInstaller()->findMainConfigAppender();

        if ($configurator === null) {
            return;
        }

        foreach ($mainConfig as $section => $data) {

            if (!\is_array($data)) {
                continue;
            }

            $config                 = $data[self::CONFIG] ?? null;
            $comment                = $data[self::COMMENT] ?? '';

            if ($config === null) {
                continue;
            }

            $configurator->appendSectionIfNotExists($section, $config, $comment);
        }
    }
}
