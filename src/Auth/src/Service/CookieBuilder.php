<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Config\OAuthConfig;
use Auth\DTO\TokenPair;

readonly class CookieBuilder
{
    public function __construct(private OAuthConfig $oauthConfig)
    {
    }

    private function buildAccessTokenCookie(string $tokenValue, int $maxAge): string
    {
        return $this->buildCookie('access_token', $tokenValue, $maxAge, false);
    }

    private function buildRefreshTokenCookie(string $tokenValue, int $maxAge): string
    {
        return $this->buildCookie('refresh_token', $tokenValue, $maxAge, $this->oauthConfig->cookieConfig->httpOnly);
    }

    public function buildTokenPairCookies(TokenPair $tokenPair): array
    {
        return [
            $this->buildAccessTokenCookie($tokenPair->accessToken, $tokenPair->expiresIn),
            $this->buildRefreshTokenCookie($tokenPair->refreshToken, $tokenPair->refreshTokenTtl)
        ];
    }

    private function buildCookie(string $name, string $value, int $maxAge, bool $httpOnly): string
    {
        $config = $this->oauthConfig->cookieConfig;

        $attributes = array_filter([
            sprintf('Path=%s', $config->cookiePath),
            sprintf('SameSite=%s', $config->refreshCookieSameSite),
            $config->secure ? 'Secure' : null,
            $httpOnly ? 'HttpOnly' : null,
            sprintf('Max-Age=%d', $maxAge),
        ]);

        return sprintf(
            '%s=%s; %s',
            rawurlencode($name),
            rawurlencode($value),
            implode('; ', $attributes)
        );
    }
}
