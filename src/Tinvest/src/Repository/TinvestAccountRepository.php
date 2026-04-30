<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\TinvestAccount;

readonly class TinvestAccountRepository extends AbstractEloquentRepository
{
    private const SAVE_BATCH_SIZE = 500;

    public function getEntityClass(): string
    {
        return TinvestAccount::class;
    }

    public function createTinvestAccounts(int $userId, array $accounts): void
    {
        $accounts = array_map(
            static fn(array $account): array => $account + ['user_id' => $userId],
            $accounts
        );

        foreach (array_chunk($accounts, self::SAVE_BATCH_SIZE) as $chunkAccounts) {
            $this->createQueryBuilder()
                ->upsert(
                    $chunkAccounts,
                    'account_id',
                    ['user_id', 'account_id', 'type', 'status', 'name', 'opened_date', 'access_level'],
                );
        }
    }

    public function getTinvestAccountByUserIdAndAccountId(int $userId, string $accountId): ?TinvestAccount
    {
        /** @var TinvestAccount */
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->where('account_id', '=', $accountId)
            ->first();
    }

    public function getTinvestAccountByIdAndUserId(int $id, int $userId): ?TinvestAccount
    {
        /** @var TinvestAccount */
        return $this->createQueryBuilder()
            ->where('id', '=', $id)
            ->where('user_id', '=', $userId)
            ->first();
    }
}
