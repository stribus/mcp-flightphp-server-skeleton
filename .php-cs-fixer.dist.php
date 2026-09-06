<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/commands',
        __DIR__ . '/public',
    ])
    ->append([
        __DIR__ . '/mcp-server.php',
        __FILE__,
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'trailing_comma_in_multiline' => true,
        // Core code uses Yoda comparisons; keep them.
        'yoda_style' => true,
    ])
    ->setIndent('    ')
    // Matches `* text=auto eol=lf` in .gitattributes, so the fixer does not
    // fight core.autocrlf on Windows checkouts.
    ->setLineEnding("\n")
    ->setFinder($finder);
