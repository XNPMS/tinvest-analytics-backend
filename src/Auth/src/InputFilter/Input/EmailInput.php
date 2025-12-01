<?php

declare(strict_types=1);

namespace Auth\InputFilter\Input;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

class EmailInput extends Input
{
    private const MAX_LENGTH_EMAIL = 255;

    public function __construct(?string $name = null, bool $isRequired = true)
    {
        parent::__construct($name);

        $this->setRequired($isRequired);

        $this->getFilterChain()
            ->attach(new StringTrim())
            ->attach(new StripTags());

        $this->getValidatorChain()
            ->attach(new NotEmpty(), true)
            ->attach(new EmailAddress())
            ->attach(new StringLength(['max' => self::MAX_LENGTH_EMAIL]));
    }
}
