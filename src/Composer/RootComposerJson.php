<?php

declare(strict_types=1);

namespace Terminal42\CodeQualityTools\Composer;

final class RootComposerJson
{
    /**
     * @param array<string, mixed> $composerJson
     */
    private function __construct(private array $composerJson)
    {
    }

    public static function fromCurrentWorkingDirectory(): self
    {
        $workingDirectory = getcwd();

        if (false === $workingDirectory) {
            throw new \RuntimeException('Could not determine the current working directory.');
        }

        $composerFile = $workingDirectory.'/composer.json';

        if (!file_exists($composerFile)) {
            return new self([]);
        }

        $contents = file_get_contents($composerFile);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Could not read "%s".', $composerFile));
        }

        $composerJson = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($composerJson)) {
            throw new \UnexpectedValueException(\sprintf('"%s" must contain a JSON object.', $composerFile));
        }

        return new self($composerJson);
    }

    public function requirement(string $package): string|null
    {
        $constraint = $this->composerJson['require'][$package]
            ?? $this->composerJson['require-dev'][$package]
            ?? null;

        return \is_string($constraint) ? $constraint : null;
    }

    public function platformRequirement(string $package): string|null
    {
        $constraint = $this->composerJson['config']['platform'][$package] ?? null;

        return \is_string($constraint) ? $constraint : null;
    }

    public function hasRequirement(string $package): bool
    {
        return null !== $this->requirement($package);
    }
}
