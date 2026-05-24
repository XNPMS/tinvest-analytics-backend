<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Entity\RefreshToken;
use Auth\Repository\RefreshTokenRepository;

readonly class RefreshTokenService
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function createRefreshToken(string $userId, string $refreshToken, int $refreshTokenTtl): RefreshToken
    {
        $refreshTokenEntity = new RefreshToken();

        $refreshTokenEntity->setUserId($userId);
        $refreshTokenEntity->setRefreshTokenHash(hash('sha256', $refreshToken, true));
        $refreshTokenEntity->setExpiresAt((new \DateTime())->setTimestamp(time() + $refreshTokenTtl));
        $refreshTokenEntity->save();

        return $refreshTokenEntity;
    }

    public function getRefreshTokenByRefreshToken(string $refreshToken): ?RefreshToken
    {
        return $this->refreshTokenRepository->getRefreshTokenByHash(hash('sha256', $refreshToken, true));
    }

    public function revokeAllForUser(string $userId): int
    {
        return $this->refreshTokenRepository->revokeAllRefreshTokensForUser($userId);
    }

    public function invalidateRefreshToken(string $token): void
    {
        if ($refreshToken = $this->getRefreshTokenByRefreshToken($token)) {
            $refreshToken->revoke();
            $refreshToken->save();
        }
    }
}
