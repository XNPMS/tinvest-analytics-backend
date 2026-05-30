<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Collection;
use Tinvest\DTO\PortfolioHistory;
use Tinvest\DTO\PortfolioHistoryPoint;
use Tinvest\DTO\PortfolioHistorySummary;
use Tinvest\Entity\PortfolioSnapshot;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Exception\EntityNotFountException;
use Tinvest\Helper\PortfolioMath;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Service\CurrencyRateService;

readonly class GetPortfolioHistoryUseCase
{
    public function __construct(
        private PortfolioSnapshotRepository $snapshotRepository,
        private TinvestAccountRepository $accountRepository,
    ) {
    }

    /**
     * @throws EntityNotFountException
     * @throws Exception
     */
    public function execute(
        string $userId,
        ?int $brokerAccountId,
        string $to,
        ?string $from,
        int $period
    ): ?PortfolioHistory {
        if ($brokerAccountId !== null) {
            return $this->executeSingleAccount($userId, $brokerAccountId, $to, $from, $period);
        }

        return $this->executeAllAccounts($userId, $to, $from, $period);
    }

    /**
     * @throws EntityNotFountException
     * @throws Exception
     */
    private function executeSingleAccount(
        string $userId,
        int $brokerAccountId,
        string $to,
        ?string $from,
        int $period,
    ): ?PortfolioHistory {
        $account = $this->accountRepository->getTinvestAccountByIdAndUserId($brokerAccountId, $userId);
        if ($account === null) {
            throw new EntityNotFountException('Account not found');
        }

        $from = $this->resolveFrom($from, $period, $account);

        $data = [];
        $this->snapshotRepository->findByAccountIdAndPeriod(
            $account->getId(),
            $from,
            $to,
            static function (Collection $chunk) use (&$data): void {
                foreach ($chunk as $snapshot) {
                    /** @var PortfolioSnapshot $snapshot */
                    $data[] = [
                        'date' => $snapshot->getSnapshotDate(),
                        'total_value_rub' => $snapshot->getTotalValueRub(),
                        'cash_flow_rub' => $snapshot->getCashFlowRub(),
                        'twr_factor' => $snapshot->getTwrFactor(),
                    ];
                }
            }
        );

        if (!$data) {
            return null;
        }

        return $this->buildResult($data);
    }

    /**
     * @throws Exception
     */
    private function executeAllAccounts(string $userId, string $to, ?string $from, int $period): ?PortfolioHistory
    {
        $accounts = $this->accountRepository->findSyncedByUserId($userId);
        if ($accounts->isEmpty()) {
            return null;
        }

        $accountIds = $accounts->map(fn(TinvestAccount $a) => $a->getId())->toArray();
        $from = $this->resolveFromMultiple($from, $period, $accounts);

        $grouped = [];
        $this->snapshotRepository->findByAccountIdsAndPeriod(
            $accountIds,
            $from,
            $to,
            static function (Collection $chunk) use (&$grouped): void {
                foreach ($chunk as $snapshot) {
                    /** @var PortfolioSnapshot $snapshot */
                    $date = $snapshot->getSnapshotDate();
                    if (!isset($grouped[$date])) {
                        $grouped[$date] = ['total_value_rub' => 0.0, 'cash_flow_rub' => 0.0];
                    }
                    $grouped[$date]['total_value_rub'] += $snapshot->getTotalValueRub();
                    $grouped[$date]['cash_flow_rub'] += $snapshot->getCashFlowRub();
                }
            }
        );

        if (!$grouped) {
            return null;
        }

        ksort($grouped);

        $data = [];
        $prevValue = 0.0;
        foreach ($grouped as $date => $row) {
            $totalValue = (float)$row['total_value_rub'];
            $cashFlow = (float)$row['cash_flow_rub'];
            $twrFactor = PortfolioMath::twrFactor($prevValue, $totalValue, $cashFlow);
            $data[] = [
                'date' => $date,
                'total_value_rub' => $totalValue,
                'cash_flow_rub' => $cashFlow,
                'twr_factor' => $twrFactor,
            ];
            $prevValue = $totalValue;
        }

        return $this->buildResult($data);
    }

    /**
     * @throws Exception
     */
    private function resolveFrom(?string $fromRaw, int $period, TinvestAccount $account): string
    {
        return match (true) {
            $fromRaw !== null && $fromRaw !== '' => $fromRaw,
            $period > 0 => (new DateTimeImmutable())
                ->modify(sprintf('-%d days', $period))
                ->format(CurrencyRateService::DATE_FORMAT),
            default => (new DateTimeImmutable($account->getOpenedDate()))
                ->format(CurrencyRateService::DATE_FORMAT),
        };
    }

    /**
     * @throws Exception
     */
    private function resolveFromMultiple(?string $fromRaw, int $period, Collection $accounts): string
    {
        if ($fromRaw !== null && $fromRaw !== '') {
            return $fromRaw;
        }

        if ($period > 0) {
            return (new DateTimeImmutable())
                ->modify(sprintf('-%d days', $period))
                ->format(CurrencyRateService::DATE_FORMAT);
        }

        $earliest = $accounts->min(fn(TinvestAccount $a) => $a->getOpenedDate());

        return (new DateTimeImmutable($earliest))->format(CurrencyRateService::DATE_FORMAT);
    }

    private function buildResult(array $data): PortfolioHistory
    {
        $periodTwr = array_reduce(
            $data,
            static fn(float $carry, array $d) => $carry * $d['twr_factor'],
            PortfolioMath::NEUTRAL_TWR_FACTOR,
        );

        $first = reset($data);
        $last = $data[count($data) - 1] ?? null;

        $points = array_map(
            static fn(array $d) => new PortfolioHistoryPoint(
                $d['date'],
                $d['total_value_rub'],
                $d['cash_flow_rub'],
                $d['twr_factor'],
            ),
            $data,
        );

        return new PortfolioHistory(
            $points,
            new PortfolioHistorySummary(
                round(($periodTwr - PortfolioMath::NEUTRAL_TWR_FACTOR) * 100, 2),
                $first !== false ? $first['total_value_rub'] : PortfolioMath::ZERO,
                $last !== null ? $last['total_value_rub'] : PortfolioMath::ZERO,
            ),
        );
    }
}
