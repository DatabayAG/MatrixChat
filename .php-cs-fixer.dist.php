<?php

require_once __DIR__ . "/vendor/autoload.php";

$finder = PhpCsFixer\Finder::create()
                           ->exclude([__DIR__ . "/vendor"])
                           ->in([__DIR__]);

return (new PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setFinder($finder)
    ->setRules([
        "@PSR12" => true,
        "strict_param" => false,
        "cast_spaces" => true,
        "concat_space" => ["spacing" => "one"],
        "unary_operator_spaces" => true,
        "function_typehint_space" => true,
        "return_type_declaration" => ["space_before" => "one"],
        "binary_operator_spaces" => true,
    ]);
