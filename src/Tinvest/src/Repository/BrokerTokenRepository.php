<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\BrokerToken;

readonly class BrokerTokenRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return BrokerToken::class;
    }

    public function findByUserId(string $userId): ?BrokerToken
    {
        /** @var BrokerToken */
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function findActiveByUserId(string $userId): ?BrokerToken
    {
        /** @var BrokerToken */
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->where('status', '=', 'active')
            ->first();
    }
}
