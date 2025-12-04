<?php

declare(strict_types=1);

namespace Auth\InputFilter;

use Auth\InputFilter\Input\EmailInput;
use Auth\InputFilter\Input\PasswordInput;
use Laminas\InputFilter\InputFilter;

final class LoginUserInputFilter extends InputFilter
{
    public function init(): void
    {
        $this->add(new EmailInput('email'));
        $this->add(new PasswordInput('password'));
    }
}
