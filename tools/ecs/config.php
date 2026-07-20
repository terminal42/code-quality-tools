<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Contao\EasyCodingStandard\Fixer\CommentLengthFixer;
use Contao\EasyCodingStandard\Fixer\TypeHintOrderFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Terminal42\CodeQualityTools\Composer\RootComposerJson;

require_once __DIR__.'/../../src/Composer/RootComposerJson.php';

$skip = [
    CommentLengthFixer::class,
    MethodChainingIndentationFixer::class => [
        '*/DependencyInjection/Configuration.php',
        '*/*Bundle.php',
    ],
];

$composerJson = RootComposerJson::fromCurrentWorkingDirectory();
$cacheDirectory = getenv('CODE_QUALITY_CACHE_DIR') ?: sys_get_temp_dir();
$versionParser = new VersionParser();

if ($phpConstraint = $composerJson->platformRequirement('php') ?? $composerJson->requirement('php')) {
    $parsedConstraints = $versionParser->parseConstraints($phpConstraint);

    if ($parsedConstraints->matches($versionParser->parseConstraints('< 8'))) {
        $skip[] = TypeHintOrderFixer::class;
    }
}

$builder = ECSConfig::configure()
    ->withSets([__DIR__.'/vendor/contao/easy-coding-standard/config/contao.php'])
    ->withConfiguredRule(HeaderCommentFixer::class, ['header' => ''])
    ->withSkip($skip)
    ->withParallel()
    ->withSpacing(null, "\n")
    ->withCache($cacheDirectory.'/ecs')
;

return new class($builder) {
    public function __construct(private $builder)
    {
    }

    public function __invoke(ECSConfig $ecsConfig): void
    {
        ($this->builder)($ecsConfig);

        $rootConfigFile = getcwd().'/ecs.php';
        if (!file_exists($rootConfigFile)) {
            return;
        }

        $rootConfig = require $rootConfigFile;
        if (is_callable($rootConfig)) {
            $rootConfig($ecsConfig);
        }
    }
};
