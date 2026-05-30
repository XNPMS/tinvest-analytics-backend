<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Tinvest\DTO\DashboardSummary;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Repository\TinvestOperationRepository;

readonly class GetDashboardSummaryUseCase
{
    public function __construct(
        private TinvestAccountRepository $accountRepository,
        private PortfolioSnapshotRepository $snapshotRepository,
        private TinvestOperationRepository $operationRepository,
    ) {
    }

    public function execute(string $userId): ?DashboardSummary
    {
        $accounts = $this->accountRepository->findSyncedByUserId($userId);
        if ($accounts->isEmpty()) {
            return null;
        }

        $accountIds = array_map(static fn(TinvestAccount $account) => $account->getId(), $accounts->all());
        $snapshots = $this->snapshotRepository->findLatestForAccounts($accountIds);
        if ($snapshots->isEmpty()) {
            return null;
        }

        $seen = [];
        $totalValueRub = 0.0;
        $unrealizedPnlRub = 0.0;
        $cumulativeTwr = 1.0;
        foreach ($snapshots as $snapshot) {
            $accountId = $snapshot->getAccountId();
            if (isset($seen[$accountId])) {
                continue;
            }
            $seen[$accountId] = true;

            $totalValueRub += $snapshot->getTotalValueRub();
            $unrealizedPnlRub += $snapshot->getExpectedYieldRub();
            $cumulativeTwr *= $snapshot->getCumulativeTwr();
        }

        $dividendsRub = $this->operationRepository->getTotalDividendsRubByAccountIds($accountIds);

        return new DashboardSummary(
            round($totalValueRub, 2),
            round($unrealizedPnlRub, 2),
            round(($cumulativeTwr - 1.0) * 100, 2),
            round($dividendsRub, 2),
        );
    }
}
