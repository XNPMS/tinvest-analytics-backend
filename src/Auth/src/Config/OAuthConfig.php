<?php

declare(strict_types=1);

namespace Auth\Config;

final readonly class OAuthConfig
{
    public function __construct(
        public int $accessTokenTtl,
        public int $refreshTokenTtl,
        public JwtConfig $jwtConfig,
        public CookieConfig $cookieConfig,
    ) {
    }
}