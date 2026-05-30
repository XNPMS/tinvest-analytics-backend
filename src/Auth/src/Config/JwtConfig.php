<?php

declare(strict_types=1);

namespace Auth\Config;

final readonly class JwtConfig
{
    public function __construct(
        public string $privateKey,
        public string $publicKey,
        public string $issuer,
        public string $audience,
    ) {
    }
}
