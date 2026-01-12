<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

use IfCastle\Application\ApplicationAbstract;
use IfCastle\Application\Bootloader\BootManager\MainConfigAppenderInterface;
use IfCastle\Application\EngineRolesEnum;
use IfCastle\Application\Environment\SystemEnvironmentInterface;
use IfCastle\DI\Exceptions\DependencyNotFound;
use IfCastle\ServiceManager\ServiceManagerInterface;

/**
 * Class for instantiating the application core for working with services in installation mode.
 */
final class InstallerApplication extends ApplicationAbstract
{
    public const string APP_CODE    = 'installer';

    public function getSystemEnvironment(): SystemEnvironmentInterface
    {
        return $this->systemEnvironment;
    }

    /**
     * @throws DependencyNotFound
     */
    public function getServiceManager(): ServiceManagerInterface
    {
        return $this->systemEnvironment->resolveDependency(ServiceManagerInterface::class);
    }

    /**
     * @throws \Throwable
     */
    public function findMainConfigAppender(): MainConfigAppenderInterface|null
    {
        return $this->systemEnvironment->findDependency(MainConfigAppenderInterface::class);
    }

    #[\Override]
    protected function defineEngineRole(): EngineRolesEnum
    {
        return EngineRolesEnum::CONSOLE;
    }
}
