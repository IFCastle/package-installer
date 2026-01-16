<?php

declare(strict_types=1);

namespace IfCastle\PackageInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Installer|null $installer;

    #[\Override]
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->installer            = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    #[\Override]
    public function deactivate(Composer $composer, IOInterface $io): void {}

    #[\Override]
    public function uninstall(Composer $composer, IOInterface $io): void {}

    /**
     * @return array<string, string|array{0: string, 1?: int}>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd'  => 'onPostInstallCmd',
            'post-update-cmd'   => 'onPostUpdateCmd',
        ];
    }

    /**
     * Apply all deferred main configs after all packages have been installed.
     */
    public function onPostInstallCmd(Event $event): void
    {
        $this->applyDeferredConfigs();
    }

    /**
     * Apply all deferred main configs after all packages have been updated.
     */
    public function onPostUpdateCmd(Event $event): void
    {
        $this->applyDeferredConfigs();
    }

    /**
     * Apply all deferred tasks.
     */
    private function applyDeferredConfigs(): void
    {
        if ($this->installer !== null && $this->installer->hasDeferredTasks()) {
            $this->installer->executeDeferredTasks();
        }
    }
}
