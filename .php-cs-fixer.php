<?php
declare(strict_types=1);

use PhpCsFixer\Config;

$rules = [
    // Sets
    '@DoctrineAnnotation' => true,
    '@PER-CS' => true,

    // Fight-Rule Club [TYPO3: ['space' => 'none']]
    'cast_spaces' => ['space' => 'single'],

    // Faktor-E custom: [TYPO3: true]
    'single_line_empty_body' => false,
    // Faktor-E custom: [TYPO3: import_classes=false]
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
    // Faktor-E custom: [TYPO3: true]
    'array_indentation' => false,


    // Rules, from TYPO3 core (2024-03-22):
    'array_syntax' => ['syntax' => 'short'],
    'concat_space' => ['spacing' => 'one'],
    'declare_equal_normalize' => ['space' => 'none'],
    'declare_parentheses' => true,
    'dir_constant' => true,
    'function_declaration' => [
        'closure_fn_spacing' => 'none',
    ],
    'function_to_constant' => ['functions' => ['get_called_class', 'get_class', 'get_class_this', 'php_sapi_name', 'phpversion', 'pi']],
    'type_declaration_spaces' => true,
    'list_syntax' => ['syntax' => 'short'],
    'method_argument_space' => true,
    'modernize_strpos' => true,
    'modernize_types_casting' => true,
    'native_function_casing' => true,
    'no_alias_functions' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => true,
    'no_leading_namespace_whitespace' => true,
    'no_null_property_initialization' => true,
    'no_short_bool_cast' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_superfluous_elseif' => true,
    'no_trailing_comma_in_singleline' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unused_imports' => true,
    'no_useless_else' => true,
    'no_useless_nullsafe_operator' => true,
    'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
    'php_unit_construct' => ['assertions' => ['assertEquals', 'assertSame', 'assertNotEquals', 'assertNotSame']],
    'php_unit_mock_short_will_return' => true,
    'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
    'phpdoc_no_access' => true,
    'phpdoc_no_empty_return' => true,
    'phpdoc_no_package' => true,
    'phpdoc_scalar' => true,
    'phpdoc_trim' => true,
    'phpdoc_types' => true,
    'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
    'return_type_declaration' => ['space_before' => 'none'],
    'single_quote' => true,
    'single_space_around_construct' => true,
    'single_line_comment_style' => ['comment_types' => ['hash']],
    'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
    'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],

    // nullable parameters, PHP 8.4
    'nullable_type_declaration' => [
        'syntax' => 'question_mark',
    ],
    'nullable_type_declaration_for_default_null_value' => true,
];

$config = new Config('FaktorE');
$config
    ->setRiskyAllowed(true)
    ->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules($rules);
$config->getFinder()->in('src');

return $config;
