<?php

declare(strict_types=1);

namespace Auth\Config;

final readonly class JwtConfig
{
    public function __construct(
        public string $privateKeyPath,
        public string $publicKeyPath,
        public string $issuer,
        public string $audience,
    ) {
    }
}