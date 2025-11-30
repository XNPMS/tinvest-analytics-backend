<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Config\CookieConfig;
use Auth\DTO\TokenPair;
use Laminas\Http\Header\SetCookie;
use Psr\Http\Message\ResponseInterface;

readonly class CookieBuilder
{
    public const ACCESS_TOKEN = 'access_token';
    public const REFRESH_TOKEN = 'refresh_token';
    public const SET_COOKIE = 'Set-Cookie';

    public function __construct(private CookieConfig $cookieConfig)
    {
    }

    public function buildAccessTokenCookie(string $value, int $maxAge): SetCookie
    {
        return new SetCookie(
            name: self::ACCESS_TOKEN,
            value: $value,
            expires: time() + $maxAge,
            path: $this->cookieConfig->cookiePath,
            secure: $this->cookieConfig->secure,
            maxAge: $maxAge,
            sameSite: $this->cookieConfig->refreshCookieSameSite
        );
    }

    public function buildRefreshTokenCookie(string $value, int $maxAge): SetCookie
    {
        return new SetCookie(
            name: self::REFRESH_TOKEN,
            value: $value,
            expires: time() + $maxAge,
            path: $this->cookieConfig->cookiePath,
            secure: $this->cookieConfig->secure,
            httponly: $this->cookieConfig->httpOnly,
            maxAge: $maxAge,
            sameSite: $this->cookieConfig->refreshCookieSameSite
        );
    }

    public function buildTokenPairCookies(TokenPair $tokenPair): array
    {
        return [
            $this->buildAccessTokenCookie($tokenPair->accessToken, $tokenPair->expiresIn),
            $this->buildRefreshTokenCookie($tokenPair->refreshToken, $tokenPair->refreshTokenTtl),
        ];
    }

    public function addToResponse(ResponseInterface $response, TokenPair $tokenPair): ResponseInterface
    {
        foreach ($this->buildTokenPairCookies($tokenPair) as $cookie) {
            $response = $response->withAddedHeader(self::SET_COOKIE, $cookie->getFieldValue());
        }

        return $response;
    }
}
