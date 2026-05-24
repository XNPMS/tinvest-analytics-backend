<?php

declare(strict_types=1);

namespace Auth\Repository;

use Auth\Entity\RefreshToken;
use System\Repository\AbstractEloquentRepository;

readonly class RefreshTokenRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return RefreshToken::class;
    }

    public function getRefreshTokenByHash(string $hashToken): ?RefreshToken
    {
        /** @var RefreshToken */
        return $this->createQueryBuilder()
            ->where('refresh_token_hash', '=', $hashToken)
            ?->first();
    }

    public function revokeAllRefreshTokensForUser(string $userId): int
    {
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->update(['revoked' => true]);
    }
}
