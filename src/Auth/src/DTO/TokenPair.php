<?php

declare(strict_types=1);

namespace Auth\DTO;

final readonly class TokenPair implements \JsonSerializable
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public int $refreshTokenTtl,
    ) {
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
