<?php

declare(strict_types=1);

return [
    'validators' => [
        \Laminas\Validator\NotEmpty::class => [
            \Laminas\Validator\NotEmpty::IS_EMPTY => 'The field %s is required',
        ],
        \Laminas\Validator\EmailAddress::class => [
            \Laminas\Validator\EmailAddress::INVALID_FORMAT => 'Invalid email format',
        ],
        \Laminas\Validator\StringLength::class => [
            \Laminas\Validator\StringLength::TOO_SHORT => 'Field %s must be at least %d characters long',
            \Laminas\Validator\StringLength::TOO_LONG => 'Field %s must not exceed %d characters',
        ],
        \Laminas\Validator\Regex::class => [
            \Laminas\Validator\Regex::NOT_MATCH => 'The field %s does not match the required format',
        ],
        \Laminas\Validator\Identical::class => [
            \Laminas\Validator\Identical::NOT_SAME => 'The field %s must match %s',
        ],
    ],
];
