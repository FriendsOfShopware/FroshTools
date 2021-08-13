<?php
/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0.0-rc.1|configurator
 * you can change this configuration by importing this file.
 */
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        // Ensure there is no code on the same line as the PHP open tag and it is followed by a blank line.
        'blank_line_after_opening_tag' => false,
        // Using `isset($var) &&` multiple times should be done in one call.
        'combine_consecutive_issets' => false,
        // Calling `unset` on multiple items should be done in one call.
        'combine_consecutive_unsets' => false,
        // Concatenation should be spaced according configuration.
        'concat_space' => ['spacing'=>'one'],
        // Pre- or post-increment and decrement operators should be used if possible.
        'increment_style' => ['style'=>'post'],
        // Ensure there is no code on the same line as the PHP open tag.
        'linebreak_after_opening_tag' => false,
        // Replace non multibyte-safe functions with corresponding mb function.
        'mb_str_functions' => true,
        // Add leading `\` before function invocation to speed up resolving.
        'native_function_invocation' => false,
        // Adds or removes `?` before type declarations for parameters with a default `null` value.
        'nullable_type_declaration_for_default_null_value' => true,
        // All items of the given phpdoc tags must be either left-aligned or (by default) aligned vertically.
        'phpdoc_align' => ['align'=>'left'],
        // PHPDoc summary should end in either a full stop, exclamation mark, or question mark.
        'phpdoc_summary' => false,
        // Throwing exception must be done in single line.
        'single_line_throw' => false,
        // Comparisons should be strict.
        'strict_comparison' => true,
        // Functions should be used with `$strict` param set to `true`.
        'strict_param' => true,
        // Anonymous functions with one-liner return statement must use arrow functions.
        'use_arrow_functions' => false,
        // Write conditions in Yoda style (`true`), non-Yoda style (`['equal' => false, 'identical' => false, 'less_and_greater' => false]`) or ignore those conditions (`null`) based on configuration.
        'yoda_style' => false,
        // Currently waiting for https://github.com/FriendsOfPHP/PHP-CS-Fixer/pull/5572 to be implemented to ignore @var (needed for LSP)
        'phpdoc_to_comment' => false,
        'php_unit_test_class_requires_covers' => false
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__)
    )
    ;
