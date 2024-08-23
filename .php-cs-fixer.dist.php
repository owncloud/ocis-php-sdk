<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('tests/integration/ocis');

$config = new PhpCsFixer\Config();
$config->setFinder($finder);
$config->setRules(
    [
        '@PSR12' => true,
        'single_space_around_construct' => true,
    ]
);
return $config;
