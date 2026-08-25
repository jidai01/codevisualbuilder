<?php

$path = $argv[1];
$slug = basename($path);

$json = [
    'name' => "app/{$slug}",
    'type' => 'project',
    'license' => 'MIT',
    'require' => [
        'php' => '^8.2',
        'filament/filament' => '^3.0',
        'laravel/framework' => '^13.0',
        'laravel/tinker' => '^3.0',
    ],
    'require-dev' => [
        'fakerphp/faker' => '^1.23',
        'laravel/pint' => '^1.27',
        'mockery/mockery' => '^1.6',
        'nunomaduro/collision' => '^8.6',
        'phpunit/phpunit' => '^12.5.12',
    ],
    'autoload' => [
        'psr-4' => [
            'App\\' => 'app/',
            'Database\\Factories\\' => 'database/factories/',
            'Database\\Seeders\\' => 'database/seeders/',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => ['Tests\\' => 'tests/'],
    ],
    'scripts' => [
        'post-autoload-dump' => [
            'Illuminate\\Foundation\\ComposerScripts::postAutoloadDump',
            '@php artisan package:discover --ansi',
        ],
    ],
    'extra' => ['laravel' => ['dont-discover' => []]],
    'config' => [
        'optimize-autoloader' => true,
        'preferred-install' => 'dist',
        'sort-packages' => true,
        'allow-plugins' => [
            'pestphp/pest-plugin' => true,
            'php-http/discovery' => true,
        ],
        'policy' => ['advisories' => ['block' => false]],
    ],
    'minimum-stability' => 'stable',
    'prefer-stable' => true,
];

file_put_contents("{$path}/composer.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Done\n";
