<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\Repository\TinvestAccountRepository;

readonly class TinvestAccountService
{
    private const MAX_CHUNK_SIZE = 50;

    public function __construct(private TinvestAccountRepository $tinvestAccountRepository)
    {
    }

    public function createTinvestAccounts(int $userId, array $accounts): void
    {
        $accounts = array_map(
            static fn(array $account): array => $account + ['user_id' => $userId],
            $accounts
        );

        foreach (array_chunk($accounts, self::MAX_CHUNK_SIZE) as $chunkAccounts) {
            $this->tinvestAccountRepository->createQueryBuilder()
                ->upsert(
                    $chunkAccounts,
                    'account_id',
                    ['user_id', 'account_id', 'type', 'status', 'name', 'opened_date', 'access_level'],
                );
        }
    }
}
