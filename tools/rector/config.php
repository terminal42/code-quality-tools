<?php

declare(strict_types=1);

use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Terminal42\CodeQualityTools\Composer\RootComposerJson;

require_once __DIR__.'/../../src/Composer/RootComposerJson.php';

return static function (RectorConfig $rectorConfig): void {
    $cacheDirectory = getenv('CODE_QUALITY_CACHE_DIR') ?: sys_get_temp_dir();
    $annotationToAttributesSets = class_exists(ContaoSetList::class)
        ? [ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES]
        : [DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES];

    $composerJson = RootComposerJson::fromCurrentWorkingDirectory();

    $rectorConfigBuilder = RectorConfig::configure()
        ->withComposerBased(phpunit: true)
        // https://getrector.com/blog/5-common-mistakes-in-rector-config-and-how-to-avoid-them
        ->withSets([
            PHPUnitSetList::PHPUNIT_CODE_QUALITY,
            SetList::DEAD_CODE,
            // SetList::CODE_QUALITY,
            // SetList::CODING_STYLE,
            // SetList::NAMING,
            // SetList::TYPE_DECLARATION,
            // SetList::PRIVATIZATION,
            // SetList::EARLY_RETURN,
            // SetList::INSTANCEOF,
        ])
        ->withSkip([
            ClassPropertyAssignToConstructorPromotionRector::class => [
                '*/Entity/',
            ],

            // Allow $this->addToAssertionCount(1)
            AddDoesNotPerformAssertionToNonAssertingTestRector::class => [
                '*/',
            ],
        ])
        ->withFileExtensions(['php'])
        ->withParallel()
        ->withCache($cacheDirectory.'/rector')
    ;

    if ($composerJson->platformRequirement('php') ?? $composerJson->requirement('php')) {
        $rectorConfigBuilder
            ->withPhpSets()
            ->withSets($annotationToAttributesSets)
        ;
    }

    $rectorConfigBuilder($rectorConfig);

    if (file_exists(getcwd().'/rector.php')) {
        $rectorConfig->import(getcwd().'/rector.php');
    }
};
