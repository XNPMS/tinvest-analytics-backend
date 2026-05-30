<?php

declare(strict_types=1);

namespace Tinvest\InputFilter\Input;

use Laminas\InputFilter\Input;
use Laminas\Validator\Callback;
use Laminas\Validator\IsArray;
use Laminas\Validator\NotEmpty;

class AccountIdsInput extends Input
{
    private const MIN_ACCOUNT_IDS = 1;
    private const MAX_ACCOUNT_IDS = 50;

    public function __construct(?string $name = null, bool $isRequired = true)
    {
        parent::__construct($name);
        $this->setRequired($isRequired);

        $this->getValidatorChain()
            ->attach(new NotEmpty(), true)
            ->attach(new IsArray(), true)
            ->attach(
                (new Callback(static fn(array $value): bool => count($value) >= self::MIN_ACCOUNT_IDS
                    && count($value) <= self::MAX_ACCOUNT_IDS)
                )->setMessage(
                    sprintf(
                        'account_ids must contain between %d and %d items',
                        self::MIN_ACCOUNT_IDS,
                        self::MAX_ACCOUNT_IDS
                    ),
                    Callback::INVALID_VALUE
                ),
                true
            );
    }
}
