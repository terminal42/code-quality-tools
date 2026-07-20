<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

if (!file_exists(getcwd().'/composer.json')) {
    throw new RuntimeException('No composer.json found.');
}

$config = null;

if (file_exists('./composer-dependency-analyser.php')) {
    trigger_error('Using config '.getcwd().'/composer-dependency-analyser.php');
    $config = require './composer-dependency-analyser.php';
} elseif (file_exists('./depcheck.php')) {
    trigger_error('Using config '.getcwd().'/depcheck.php');
    @trigger_error('Please rename your "depcheck.php" file to "composer-dependency-analyser.php".', E_USER_DEPRECATED);
    $config = require './depcheck.php';
}

if (!$config instanceof Configuration) {
    $config = new Configuration();
}

$config
    ->enableAnalysisOfUnusedDevDependencies()
    ->disableReportingUnmatchedIgnores()
    ->ignoreErrorsOnPackage('terminal42/code-quality-tools', [ErrorType::UNUSED_DEPENDENCY])
;

$composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

return $config;
