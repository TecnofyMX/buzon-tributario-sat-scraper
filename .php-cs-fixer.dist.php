<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        '@PHP8x2Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => true,
        'function_declaration' => false,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'single_line_empty_body' => false,
    ])
    ->setFinder($finder);
