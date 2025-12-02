<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Config\CookieConfig;
use Auth\DTO\TokenPair;
use Laminas\Http\Header\SetCookie;
use Psr\Http\Message\ResponseInterface;

readonly class CookieManager
{
    public const ACCESS_TOKEN = 'access_token';
    public const REFRESH_TOKEN = 'refresh_token';
    public const SET_COOKIE = 'Set-Cookie';
    public const STRICT = 'Strict';


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
            sameSite: self::STRICT,
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
            sameSite: self::STRICT,
        );
    }

    public function buildTokenPairCookies(TokenPair $tokenPair): array
    {
        return [
            $this->buildAccessTokenCookie($tokenPair->accessToken, $tokenPair->expiresIn),
            $this->buildRefreshTokenCookie($tokenPair->refreshToken, $tokenPair->refreshTokenTtl),
        ];
    }

    public function addTokenPairToResponse(TokenPair $tokenPair, ResponseInterface $response): ResponseInterface
    {
        /** @var SetCookie $cookie */
        foreach ($this->buildTokenPairCookies($tokenPair) as $cookie) {
            $response = $response->withAddedHeader(self::SET_COOKIE, $cookie->getFieldValue());
        }

        return $response;
    }

    public function clearTokenCookies(ResponseInterface $response): ResponseInterface
    {
        foreach ([self::ACCESS_TOKEN, self::REFRESH_TOKEN] as $cookieName) {
            $expired = new SetCookie(
                name: $cookieName,
                value: '',
                expires: 1,
                path: $this->cookieConfig->cookiePath,
                secure: $this->cookieConfig->secure,
                httponly: $this->cookieConfig->httpOnly,
                maxAge: 0,
                sameSite: self::STRICT,
            );

            $response = $response->withAddedHeader(self::SET_COOKIE, $expired->getFieldValue());
        }

        return $response;
    }
}
