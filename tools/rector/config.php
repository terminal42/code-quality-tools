<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Contao\Rector\Set\ContaoSetList;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Terminal42\CodeQualityTools\Composer\RootComposerJson;

require_once __DIR__.'/../../src/Composer/RootComposerJson.php';

return static function (RectorConfig $rectorConfig): void {
    $cacheDirectory = getenv('CODE_QUALITY_CACHE_DIR') ?: sys_get_temp_dir();
    $versionParser = new VersionParser();
    $annotationToAttributesSets = class_exists(ContaoSetList::class)
        ? [ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES]
        : [DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES];

    $composerJson = RootComposerJson::fromCurrentWorkingDirectory();

    if ($phpConstraint = $composerJson->platformRequirement('php') ?? $composerJson->requirement('php')) {
        $parsedConstraints = $versionParser->parseConstraints($phpConstraint);

        $setList = match (true) {
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.1')) => [],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.2')) => [LevelSetList::UP_TO_PHP_71],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.3')) => [LevelSetList::UP_TO_PHP_72],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.4')) => [LevelSetList::UP_TO_PHP_73],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.0')) => [LevelSetList::UP_TO_PHP_74],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.1')) => [LevelSetList::UP_TO_PHP_80],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.2')) => [LevelSetList::UP_TO_PHP_81, ...$annotationToAttributesSets],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.3')) => [LevelSetList::UP_TO_PHP_82, ...$annotationToAttributesSets],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.4')) => [LevelSetList::UP_TO_PHP_83, ...$annotationToAttributesSets],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.5')) => [LevelSetList::UP_TO_PHP_84, ...$annotationToAttributesSets],
            $parsedConstraints->matches($versionParser->parseConstraints('^8.5')) => [LevelSetList::UP_TO_PHP_85, ...$annotationToAttributesSets],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }
    }

    if ($phpunitConstraint = $composerJson->requirement('phpunit/phpunit')) {
        $lowerBound = $versionParser->parseConstraints($phpunitConstraint)->getLowerBound();

        $setList = [
            '>= 4.0' => [PHPUnitSetList::PHPUNIT_40],
            '>= 5.0' => [PHPUnitSetList::PHPUNIT_50],
            '>= 6.0' => [PHPUnitSetList::PHPUNIT_60],
            '>= 7.0' => [PHPUnitSetList::PHPUNIT_70],
            '>= 8.0' => [PHPUnitSetList::PHPUNIT_80],
            '>= 9.0' => [PHPUnitSetList::PHPUNIT_90],
            '>= 10.0' => [PHPUnitSetList::PHPUNIT_100, PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES],
            '>= 11.0' => [PHPUnitSetList::PHPUNIT_110],
            '>= 12.0' => [PHPUnitSetList::PHPUNIT_120],
        ];

        $setList = array_filter(
            $setList,
            static fn ($constraint) => $lowerBound->compareTo($versionParser->parseConstraints($constraint)->getLowerBound(), '>'),
            ARRAY_FILTER_USE_KEY,
        );

        if (!empty($setList)) {
            $rectorConfig->sets(array_merge([PHPUnitSetList::PHPUNIT_CODE_QUALITY], ...array_values($setList)));
        }
    }

    // https://getrector.com/blog/5-common-mistakes-in-rector-config-and-how-to-avoid-them
    $rectorConfig->sets([
        SetList::DEAD_CODE,
        // SetList::CODE_QUALITY,
        // SetList::CODING_STYLE,
        // SetList::NAMING,
        // SetList::TYPE_DECLARATION,
        // SetList::PRIVATIZATION,
        // SetList::EARLY_RETURN,
        // SetList::INSTANCEOF,
    ]);

    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class => [
            '*/Entity/',
        ],

        // Allow $this->addToAssertionCount(1);
        AddDoesNotPerformAssertionToNonAssertingTestRector::class => [
            '*/',
        ],
    ]);

    $rectorConfig->fileExtensions(['php']);
    $rectorConfig->parallel();
    $rectorConfig->cacheDirectory($cacheDirectory.'/rector');
    $rectorConfig->cacheClass(FileCacheStorage::class);

    if (file_exists(getcwd().'/rector.php')) {
        $rectorConfig->import(getcwd().'/rector.php');
    }
};
