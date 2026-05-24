<?php

declare(strict_types=1);

namespace Tinvest\DTO;

use Tinvest\Entity\TinvestAccount;

/**
 * Контекст выполнения пост-синхронизации.
 * Создаётся после успешного завершения SyncAccountOperationsUseCase для набора счетов.
 */
final readonly class SyncContext
{
    /**
     * @param TinvestAccount[] $syncedAccounts Счета, для которых синк операций прошёл успешно
     * @param int[] $accountIds DB PK счетов (tinvest_accounts.id)
     * @param int[] $brokerAccountIds Числовые Tinkoff-идентификаторы счетов (account_id колонка)
     */
    public function __construct(
        public string $jobId,
        public string $token,
        public string $userId,
        public array $syncedAccounts,
        public array $accountIds,
        public array $brokerAccountIds,
    ) {
    }
}
