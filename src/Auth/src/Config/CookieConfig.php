<?php

declare(strict_types=1);

namespace Auth\Config;

final readonly class CookieConfig
{
    public function __construct(
        public string $cookiePath,
        public bool $secure,
        public bool $httpOnly,
        public string $refreshCookieSameSite,
    ) {
    }
}
