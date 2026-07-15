<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $versionParser = new VersionParser();
    $composerJson = file_exists(getcwd().'/composer.json') ? json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR) : null;

    if ($composerJson && ($phpConstraint = $composerJson['config']['platform']['php'] ?? $composerJson['require']['php'] ?? null)) {
        $parsedConstraints = $versionParser->parseConstraints($phpConstraint);

        $setList = match (true) {
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.1')) => [],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.2')) => [LevelSetList::UP_TO_PHP_71],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.3')) => [LevelSetList::UP_TO_PHP_72],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.4')) => [LevelSetList::UP_TO_PHP_73],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.0')) => [LevelSetList::UP_TO_PHP_74],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.1')) => [LevelSetList::UP_TO_PHP_80],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.2')) => [LevelSetList::UP_TO_PHP_81, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.3')) => [LevelSetList::UP_TO_PHP_82, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.4')) => [LevelSetList::UP_TO_PHP_83, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.5')) => [LevelSetList::UP_TO_PHP_84, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $parsedConstraints->matches($versionParser->parseConstraints('^8.5')) => [LevelSetList::UP_TO_PHP_85, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }
    }

    if ($composerJson && ($phpunitConstraint = $composerJson['require-dev']['phpunit/phpunit'] ?? null)) {
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

    if (file_exists(getcwd().'/rector.php')) {
        $rectorConfig->import(getcwd().'/rector.php');
    }
};
