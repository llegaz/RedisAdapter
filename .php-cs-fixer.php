<?php
$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in('src')
    ->in('tests')
    ->name('*.php')
;

return (new PhpCsFixer\Config())
        ->setRules([
            '@PSR12' => true,
            'blank_line_before_statement' => true,
            'declare_strict_types' => true,
            'no_empty_comment' => true,
            'no_useless_return' => true,
            'cast_spaces' => true,
            'single_quote' => true,
            'ordered_imports' => true,
            'no_unused_imports' => true,
            'concat_space' => ['spacing' => 'one'],
            'array_syntax' => ['syntax' => 'short'],
        ])
        ->setRiskyAllowed(true)
        ->setFinder($finder)
    ;
