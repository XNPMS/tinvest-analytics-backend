<?php

declare(strict_types=1);

namespace Auth\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Identical;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;

final class RegisterUserInputFilter extends InputFilter
{
    private const MAX_LENGTH_EMAIL = 255;
    private const MAX_LENGTH_PASSWORD = 128;
    private const MIN_LENGTH_PASSWORD = 8;

    public function init(): void
    {
        $this->add([
            'name' => 'email',
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
                ['name' => StripTags::class],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                ],
                [
                    'name' => EmailAddress::class,
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'max' => self::MAX_LENGTH_EMAIL,
                    ],
                ],
            ],
        ]);

        $this->add([
            'name' => 'password',
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                ],
                [
                    'name' => StringLength::class,
                    'options' => [
                        'min' => self::MIN_LENGTH_PASSWORD,
                        'max' => self::MAX_LENGTH_PASSWORD,
                    ],
                ],
                [
                    'name' => Regex::class,
                    'options' => [
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                    ],
                ],
            ],
        ]);

        $this->add([
            'name' => 'password_confirmation',
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => NotEmpty::class,
                    'break_chain_on_failure' => true,
                ],
                [
                    'name' => Identical::class,
                    'options' => [
                        'token' => 'password',
                    ],
                ],
            ],
        ]);
    }
}
