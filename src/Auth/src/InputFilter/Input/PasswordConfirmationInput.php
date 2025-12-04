<?php

declare(strict_types=1);

namespace Auth\InputFilter\Input;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\Input;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Identical;

class PasswordConfirmationInput extends Input
{
    public function __construct(?string $name = null, bool $isRequired = true)
    {
        parent::__construct($name);

        $this->setRequired($isRequired);

        $this->getFilterChain()
            ->attach(new StringTrim());

        $this->getValidatorChain()
            ->attach(new NotEmpty(), true)
            ->attach(new Identical([
                'token' => 'password',
            ]));
    }
}
