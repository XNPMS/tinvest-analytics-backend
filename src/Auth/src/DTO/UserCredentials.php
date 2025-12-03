<?php

declare(strict_types=1);

namespace Auth\DTO;

final readonly class UserCredentials
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['email'],
            $data['password'],
        );
    }
}
