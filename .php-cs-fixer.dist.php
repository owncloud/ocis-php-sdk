<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('tests/integration/ocis');

$config = new PhpCsFixer\Config();
$config->setFinder($finder);
$config->setRules(['@PER-CS2.0' => true]);
return $config;
