<?php

declare(strict_types=1);

namespace Tinvest\InputFilter;

use Laminas\InputFilter\InputFilter;
use Tinvest\InputFilter\Input\AccountIdsInput;

final class TinvestAccountIdsInputFilter extends InputFilter
{
    public function init(): void
    {
        $this->add(new AccountIdsInput('account_ids'));
    }
}
