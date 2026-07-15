<?php

declare(strict_types=1);

namespace Terminal42\CodeQualityTools\Composer;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        // nothing to do here
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // nothing to do here
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // nothing to do here
    }

    public function installTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Installing tools …</warning>');
        $this->executeAllNamespaces(new StringInput('install'), $event->getIO(), $event->getComposer());
    }

    public function updateTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Updating tools …</warning>');
        $this->executeAllNamespaces(new StringInput('update'), $event->getIO(), $event->getComposer());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installTools',
            ScriptEvents::POST_UPDATE_CMD => 'updateTools',
        ];
    }

    private function executeAllNamespaces(InputInterface $input, IOInterface $io, Composer $composer): void
    {
        $application = new Application();
        $output = Factory::createOutput();

        $binRoots = glob(__DIR__.'/../../tools/*', GLOB_ONLYDIR);
        if (empty($binRoots)) {
            $io->writeError('<warning>Couldn\'t find any tool namespace.</warning>');

            return;
        }

        $originalWorkingDir = getcwd();

        foreach ($binRoots as $binRoot) {
            if (
                $this->filesystem->exists($binRoot.'/package.json')
                && (
                    $this->filesystem->exists($originalWorkingDir.'/layout')
                    || (
                        $this->filesystem->exists($originalWorkingDir.'/assets')
                        && !$this->isProject($composer)
                    )
                )
            ) {
                Process::fromShellCommandline('npm install')
                    ->setWorkingDirectory($binRoot)
                    ->mustRun(
                        static function (string $type, string $buffer) use ($output): void {
                            $output->write($buffer);
                        },
                    )
                ;
            }

            if ($this->filesystem->exists($binRoot.'/composer.json')) {
                $this->executeInNamespace($application, $binRoot, $input, $output);

                chdir($originalWorkingDir);
                $this->resetComposers($application);
            }
        }
    }

    private function executeInNamespace(Application $application, $namespace, InputInterface $input, OutputInterface $output): int
    {
        if (!$this->filesystem->exists($namespace)) {
            $this->filesystem->mkdir($namespace);
        }

        chdir($namespace);

        // some plugins require access to composer file e.g. Symfony Flex
        if (!$this->filesystem->exists(Factory::getComposerFile())) {
            $this->filesystem->dumpFile(Factory::getComposerFile(), '{}');
        }

        $input = new StringInput($input.' --quiet --working-dir=.');

        $output->write('<info>Run with <comment>'.$input->__toString().'</comment></info>', true, IOInterface::VERBOSE);

        return $application->doRun($input, $output);
    }

    private function resetComposers(Application $application): void
    {
        $application->resetComposer();

        foreach ($application->all() as $command) {
            if ($command instanceof BaseCommand) {
                $command->resetComposer();
            }
        }
    }

    private function isProject(Composer $composer): bool
    {
        return 'project' === $composer->getPackage()->getType();
    }
}
