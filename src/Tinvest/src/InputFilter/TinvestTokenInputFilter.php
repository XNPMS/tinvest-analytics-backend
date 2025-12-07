<?php

declare(strict_types=1);

namespace Tinvest\InputFilter;

use Laminas\InputFilter\InputFilter;
use Tinvest\InputFilter\Input\TokenInput;

final class TinvestTokenInputFilter extends InputFilter
{
    public function init(): void
    {
        $this->add(new TokenInput('token'));
    }
}
