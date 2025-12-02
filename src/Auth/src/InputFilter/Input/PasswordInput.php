<?php

declare(strict_types=1);

namespace Auth\InputFilter\Input;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\Input;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Laminas\Validator\Regex;

class PasswordInput extends Input
{
    private const MAX_LENGTH_PASSWORD = 128;
    private const MIN_LENGTH_PASSWORD = 8;
    private const PATTERN_PASSWORD = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/';

    public function __construct(?string $name = null, bool $isRequired = true)
    {
        parent::__construct($name);

        $this->setRequired($isRequired);

        $this->getFilterChain()
            ->attach(new StringTrim());

        $this->getValidatorChain()
            ->attach(new NotEmpty(), true)
            ->attach(new StringLength([
                'min' => self::MIN_LENGTH_PASSWORD,
                'max' => self::MAX_LENGTH_PASSWORD,
            ]))
            ->attach(new Regex([
                'pattern' => self::PATTERN_PASSWORD,
            ]));
    }
}
