<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        'tests/fixtures',
        'vendor',
    ])
    ->in(__DIR__)
    ->append([__DIR__.'/bin/robuust-agents']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR1' => true,
        '@PSR2' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        'yoda_style' => false,
        'no_superfluous_phpdoc_tags' => false,
        'multiline_whitespace_before_semicolons' => false,
        'php_unit_test_class_requires_covers' => false,
        'global_namespace_import' => false,
        'single_line_empty_body' => false,
    ])
    ->setCacheFile(__DIR__.'/.php_cs.cache')
    ->setFinder($finder);
