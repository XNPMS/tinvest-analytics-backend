<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Entity\RefreshToken;
use Auth\Repository\RefreshTokenRepository;
use DateTime;

readonly class RefreshTokenService
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function createRefreshToken(int $userId, string $refreshToken, int $refreshTokenTtl): RefreshToken
    {
        $refreshTokenEntity = new RefreshToken();

        $refreshTokenEntity->setUserId($userId);
        $refreshTokenEntity->setRefreshToken(hash('sha256', $refreshToken));
        $refreshTokenEntity->setExpiresAt((new \DateTime())->modify(sprintf('+%d seconds', $refreshTokenTtl)));
        $refreshTokenEntity->save();

        return $refreshTokenEntity;
    }

    public function getRefreshTokenByRefreshToken(string $refreshToken): ?RefreshToken
    {
        return $this->refreshTokenRepository->getRefreshTokenByHash(hash('sha256', $refreshToken));
    }

    public function revoke(RefreshToken $token): void
    {
        $token->revoke();
    }

    public function revokeAllForUser(int $userId): int
    {
        return $this->refreshTokenRepository->revokeAllRefreshTokensForUser($userId);
    }
}
