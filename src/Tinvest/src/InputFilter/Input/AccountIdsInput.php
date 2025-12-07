<?php

declare(strict_types=1);

namespace Tinvest\InputFilter\Input;

use Laminas\InputFilter\Input;
use Laminas\Validator\Digits;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;

class AccountIdsInput extends Input
{
    public function __construct(?string $name = null, bool $isRequired = true)
    {
        parent::__construct($name);

        $this->setRequired($isRequired);

        $this->getValidatorChain()
            ?->attach(new NotEmpty(), true);
    }
}
