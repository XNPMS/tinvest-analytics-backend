<?php

declare(strict_types=1);

namespace Tinvest\InputFilter\Input;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;

class TokenInput extends Input
{
    private const MAX_LENGTH = 255;
    private const MIN_LENGTH = 20;
    // без пробелов
    private const PATTERN = '/^[^\s]+$/';

    public function __construct(?string $name = null, bool $isRequired = true)
    {
        parent::__construct($name);

        $this->setRequired($isRequired);

        $this->getFilterChain()
            ?->attach(new StringTrim())
            ->attach(new StripTags());

        $this->getValidatorChain()
            ?->attach(new NotEmpty(), true)
            ->attach(new StringLength([
                'min' => self::MIN_LENGTH,
                'max' => self::MAX_LENGTH,
            ]))
            ->attach(new Regex([
                'pattern' => self::PATTERN,
            ]));
    }
}
