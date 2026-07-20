<?php

declare(strict_types=1);

$includes = [__DIR__.'/config.neon'];
$files = [
    'phpstan.neon',
    'phpstan.neon.dist',
    'phpstan.dist.neon',
    'phpstan-baseline.neon',
];

foreach ($files as $file) {
    if (file_exists(getcwd().'/'.$file)) {
        $includes[] = getcwd().'/'.$file;
        break;
    }
}

$config = [];
$config['includes'] = $includes;

if ($cacheDirectory = getenv('CODE_QUALITY_CACHE_DIR')) {
    $config['parameters']['tmpDir'] = $cacheDirectory.'/phpstan';
}

return $config;
