<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use JsonException;
use Psr\Log\LoggerInterface;
use Tinvest\Entity\PortfolioSnapshot;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Exception\TinvestGrpcException;
use Tinvest\Helper\PortfolioMath;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Repository\TinvestOperationRepository;
use Tinvest\Service\CurrencyRateService;
use Tinvest\Service\TinvestApiService;

readonly class CaptureCurrentPortfolioUseCase
{
    public function __construct(
        private TinvestApiService $apiService,
        private PortfolioSnapshotRepository $snapshotRepository,
        private TinvestOperationRepository $operationRepository,
        private TinvestAccountRepository $accountRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Строит снэпшот текущего состояния портфеля для каждого счёта.
     * Вызывается после завершения синхронизации операций и пересчёта payment_rub.
     *
     * @param int[] $accountIds DB-идентификаторы счетов
     * @throws JsonException
     */
    public function execute(string $userId, string $token, array $accountIds): void
    {
        $accounts = $this->accountRepository->findByIds($userId, $accountIds);
        if ($accounts->isEmpty()) {
            $this->logger->info('Accounts not found', ['ids' => $accountIds]);

            return;
        }

        $today = date(CurrencyRateService::DATE_FORMAT);
        /** @var TinvestAccount $account */
        foreach ($accounts as $account) {
            $brokerAccountId = $account->getAccountId();

            try {
                $portfolio = $this->apiService->getPortfolio($token, $brokerAccountId);
            } catch (TinvestGrpcException $e) {
                $this->logger->warning(sprintf('%s::getPortfolio failed', self::class), [
                    'account_id' => $brokerAccountId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $accountId = $account->getId();
            $totalValueRub = $portfolio['total_value_rub'];
            $expectedYieldRub = $portfolio['expected_yield_rub'];
            $cashFlowRub = $this->operationRepository->getDailyCashFlowRubByAccountId($accountId, $today);

            $prev = $this->snapshotRepository->findLatestBeforeDate($accountId, $today);
            $twrFactor = PortfolioMath::twrFactor($prev?->getTotalValueRub() ?? 0.0, $totalValueRub, $cashFlowRub);
            $cumulativeTwr = $this->calculateCumulativeTwr($prev, $twrFactor);

            $this->snapshotRepository->upsert([
                'account_id' => $accountId,
                'snapshot_date' => $today,
                'total_value_rub' => $totalValueRub,
                'expected_yield_rub' => $expectedYieldRub,
                'cash_flow_rub' => $cashFlowRub,
                'twr_factor' => $twrFactor,
                'cumulative_twr' => $cumulativeTwr,
                'positions_json' => json_encode($portfolio['positions'], JSON_THROW_ON_ERROR),
            ]);

            $this->logger->info(sprintf('%s: snapshot saved', self::class), [
                'account_id' => $brokerAccountId,
                'snapshot_date' => $today,
                'total_value' => $totalValueRub,
            ]);
        }
    }

    private function calculateCumulativeTwr(?PortfolioSnapshot $prev, float $twrFactor): float
    {
        return ($prev?->getCumulativeTwr() ?? 1.0) * $twrFactor;
    }
}
