<?php

declare(strict_types=1);

namespace Auth\DTO;

use Lcobucci\JWT\Token\DataSet;

final readonly class Identity
{
    public function __construct(
        public ?string $email = null,
        public ?DataSet $claims = null
    ) {
    }
}
