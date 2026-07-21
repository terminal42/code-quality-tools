<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\Rules\File\FileExtensionRule;
use TwigCsFixer\Rules\Literal\CompactHashRule;
use TwigCsFixer\Rules\Node\ValidConstantFunctionRule;
use TwigCsFixer\Rules\Variable\VariableNameRule;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

require_once __DIR__.'/vendor/autoload.php';

$ruleset = new Ruleset();
$ruleset->addStandard(new TwigCsFixer());

$ruleset->overrideRule(new CompactHashRule(true));
$ruleset->overrideRule(new VariableNameRule(optionalPrefix: '_'));

$ruleset->addRule(new FileExtensionRule());
$ruleset->addRule(new ValidConstantFunctionRule());

$config = new Config();
$config->allowNonFixableRules();

$config->setRuleset($ruleset);
$config->setCacheFile(sys_get_temp_dir().'/twig-cs-fixer');

return $config;
