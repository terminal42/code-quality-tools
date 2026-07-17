<?php

declare(strict_types=1);

namespace Terminal42\CodeQualityTools\Composer;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const TOOL_REQUIREMENTS = [
        'rector' => [
            'contao/manager-bundle' => [
                'contao/contao-rector' => 'dev-main',
            ],
            'contao/core-bundle' => [
                'contao/contao-rector' => 'dev-main',
            ],
        ],
    ];

    private Filesystem $filesystem;

    private RootComposerJson|null $rootComposerJson = null;

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
        $this->rootComposerJson = RootComposerJson::fromCurrentWorkingDirectory();

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
                $this->executeInNamespace($application, $binRoot, $input);

                chdir($originalWorkingDir);
                $this->resetComposers($application);
            }
        }
    }

    private function addToolRequirements(Application $application, string $namespace): void
    {
        foreach ($this->resolveToolRequirements($namespace) as $package => $constraints) {
            $this->addToolRequirement($application->getComposer(), $package, $constraints);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function resolveToolRequirements(string $namespace): array
    {
        $resolvedRequirements = [];
        $requirementsByRootPackage = self::TOOL_REQUIREMENTS[basename($namespace)] ?? [];

        foreach ($requirementsByRootPackage as $rootPackage => $requirements) {
            if (!$this->rootComposerJson?->hasRequirement($rootPackage)) {
                continue;
            }

            foreach ($requirements as $package => $constraint) {
                $resolvedRequirements[$package][] = $constraint;
                $resolvedRequirements[$package] = array_values(array_unique($resolvedRequirements[$package]));
            }
        }

        return $resolvedRequirements;
    }

    /**
     * @param non-empty-list<string> $constraints
     */
    private function addToolRequirement(Composer $composer, string $packageName, array $constraints): void
    {
        $package = $composer->getPackage();
        $requires = $package->getRequires();
        $versionParser = new VersionParser();
        $parsedConstraints = array_map($versionParser->parseConstraints(...), $constraints);
        $prettyConstraint = implode(' && ', $constraints);
        $requires[$packageName] = new Link(
            $package->getName(),
            $packageName,
            MultiConstraint::create($parsedConstraints),
            Link::TYPE_REQUIRE,
            $prettyConstraint,
        );
        $package->setRequires($requires);
    }

    private function executeInNamespace(Application $application, string $namespace, InputInterface $input): int
    {
        if (!$this->filesystem->exists($namespace)) {
            $this->filesystem->mkdir($namespace);
        }

        chdir($namespace);

        // some plugins require access to composer file e.g. Symfony Flex
        if (!$this->filesystem->exists(Factory::getComposerFile())) {
            $this->filesystem->dumpFile(Factory::getComposerFile(), '{}');
        }

        $this->addToolRequirements($application, $namespace);

        $input = new StringInput($input.' --quiet --working-dir=.');
        $output = Factory::createOutput();

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
