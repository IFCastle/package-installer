<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

use IfCastle\Application\Bootloader\BootManager\BootManagerInterface;
use IfCastle\Application\Bootloader\Builder\ZeroContextInterface;

interface PackageInstallerInterface
{
    public const string PACKAGE     = 'package';

    public const string SERVICES    = 'services';

    public const string NAME        = 'name';

    public const string MAIN_CONFIG = 'mainConfig';

    public const string COMMENT     = 'comment';

    public const string CONFIG      = 'config';

    public const string IS_ACTIVE   = 'isActive';

    public const string RUNTIME_TAGS = 'runtimeTags';

    public const string EXCLUDE_TAGS = 'excludeTags';

    public const string BOOTLOADERS  = 'bootloaders';

    public const string APPLICATIONS = 'applications';

    public const string GROUPS       = 'groups';

    public const string GROUP        = 'group';

    public function __construct(
        BootManagerInterface $bootManager,
        ZeroContextInterface $zeroContext,
        DeferredTasksInterface $deferredTasks
    );

    public function install(): void;

    public function update(): void;

    public function uninstall(): void;
}
