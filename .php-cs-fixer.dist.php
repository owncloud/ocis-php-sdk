<?php
$finder = PhpCsFixer\Finder::create()
	->in(__DIR__);

$config = new PhpCsFixer\Config();
$config->setFinder($finder);
$config->setRules(['@PSR12' => true]);
return $config;
