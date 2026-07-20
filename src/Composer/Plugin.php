<?php

declare(strict_types=1);

namespace Terminal42\CodeQualityTools\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Platform;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // nothing to do here
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // nothing to do here
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // nothing to do here
    }

    public function installTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Installing tools …</warning>');
        $this->executeAllNamespaces('install', $event->getIO());
    }

    public function updateTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Updating tools …</warning>');
        $this->executeAllNamespaces('update', $event->getIO());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installTools',
            ScriptEvents::POST_UPDATE_CMD => 'updateTools',
        ];
    }

    private function executeAllNamespaces(string $command, IOInterface $io): void
    {
        $binRoots = glob(__DIR__.'/../../tools/*', GLOB_ONLYDIR);
        if (empty($binRoots)) {
            $io->writeError('<warning>Couldn\'t find any tool namespace.</warning>');

            return;
        }

        $processManager = new ProcessManager();
        $this->addNodeProcesses($processManager, $binRoots);
        $this->addComposerProcesses($processManager, $binRoots, $command);
        $processManager->run($io);
    }

    /**
     * @param list<string> $namespaces
     */
    private function addNodeProcesses(ProcessManager $processManager, array $namespaces): void
    {
        if (!$this->hasProjectFile('package.json')) {
            return;
        }

        foreach ($namespaces as $namespace) {
            if ($this->filesystem->exists($namespace.'/package.json')) {
                $process = new Process(['npm', 'install'], $namespace);
                $processManager->add($process->setTimeout(null), basename($namespace).' (npm)');
            }
        }
    }

    /**
     * @param list<string> $namespaces
     */
    private function addComposerProcesses(ProcessManager $processManager, array $namespaces, string $command): void
    {
        if (!$this->hasProjectFile('composer.json')) {
            return;
        }

        $installer = ComposerNamespaceInstaller::fromCurrentWorkingDirectory();
        $composerBinary = Platform::getEnv('COMPOSER_BINARY');

        foreach ($namespaces as $namespace) {
            if (!$this->filesystem->exists($namespace.'/composer.json')) {
                continue;
            }

            if ($installer->hasDynamicRequirements($namespace)) {
                $process = new Process([PHP_BINARY, __DIR__.'/../../bin/install-tool', $namespace, $command]);
            } else {
                $composerCommand = \is_string($composerBinary)
                    ? [PHP_BINARY, $composerBinary, $command, '--quiet']
                    : ['composer', $command, '--quiet'];
                $process = new Process($composerCommand, $namespace);
            }

            $processManager->add($process->setTimeout(null), basename($namespace).' (composer)');
        }
    }

    private function hasProjectFile(string $file): bool
    {
        $projectDirectory = getcwd();
        \assert(false !== $projectDirectory);

        return $this->filesystem->exists($projectDirectory.'/'.$file);
    }
}
