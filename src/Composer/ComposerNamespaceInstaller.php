<?php

declare(strict_types=1);

namespace Terminal42\CodeQualityTools\Composer;

use Composer\Composer;
use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Package\Link;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Filesystem\Filesystem;

final class ComposerNamespaceInstaller
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

    private function __construct(
        private Filesystem $filesystem,
        private RootComposerJson $rootComposerJson,
    ) {
    }

    public static function fromCurrentWorkingDirectory(): self
    {
        return new self(new Filesystem(), RootComposerJson::fromCurrentWorkingDirectory());
    }

    public function hasDynamicRequirements(string $namespace): bool
    {
        return [] !== $this->resolveToolRequirements($namespace);
    }

    public function run(string $namespace, string $command): int
    {
        $application = new Application();
        chdir($namespace);

        // some plugins require access to composer file e.g. Symfony Flex
        if (!$this->filesystem->exists(Factory::getComposerFile())) {
            $this->filesystem->dumpFile(Factory::getComposerFile(), '{}');
        }

        $this->addToolRequirements($application, $namespace);
        $input = new StringInput($command.' --quiet --working-dir=.');
        $output = Factory::createOutput();
        $output->write('<info>Run with <comment>'.$input->__toString().'</comment></info>', true, IOInterface::VERBOSE);

        return $application->doRun($input, $output);
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
            if (!$this->rootComposerJson->hasRequirement($rootPackage)) {
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
        $this->addStabilityFlag($composer, $packageName, $constraints);
    }

    /**
     * @param non-empty-list<string> $constraints
     */
    private function addStabilityFlag(Composer $composer, string $packageName, array $constraints): void
    {
        $package = $composer->getPackage();
        $stabilityFlags = $package->getStabilityFlags();
        $stabilityFlag = $stabilityFlags[$packageName] ?? BasePackage::STABILITY_STABLE;
        $minimumStability = BasePackage::STABILITIES[$package->getMinimumStability()];

        foreach ($constraints as $constraint) {
            $stability = $this->extractStability($constraint);

            if ($minimumStability <= $stability) {
                $stabilityFlag = max($stabilityFlag, $stability);
            }
        }

        $stabilityFlags[$packageName] = $stabilityFlag;
        $package->setStabilityFlags($stabilityFlags);
    }

    private function extractStability(string $constraint): int
    {
        $orConstraints = preg_split('{\s*\|\|?\s*}', trim($constraint)) ?: [];
        $constraints = [];

        foreach ($orConstraints as $orConstraint) {
            $andConstraints = preg_split(
                '{(?<!^|as|[=>< ,]) *(?<!-)[, ](?!-) *(?!,|as|$)}',
                $orConstraint,
            ) ?: [];
            array_push($constraints, ...$andConstraints);
        }

        $inferredStability = BasePackage::STABILITY_STABLE;
        $explicitStability = null;

        foreach ($constraints as $constraint) {
            if (preg_match('{@(?<stability>stable|RC|beta|alpha|dev)$}i', $constraint, $match)) {
                $stability = VersionParser::normalizeStability($match['stability']);
                $explicitStability = max(
                    $explicitStability ?? BasePackage::STABILITY_STABLE,
                    BasePackage::STABILITIES[$stability],
                );

                continue;
            }

            $normalizedConstraint = preg_replace('{^([^,\s@]+) as .+$}', '$1', $constraint) ?? $constraint;
            $stability = VersionParser::parseStability($normalizedConstraint);
            $inferredStability = max($inferredStability, BasePackage::STABILITIES[$stability]);
        }

        return $explicitStability ?? $inferredStability;
    }
}
