<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Collection;
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
        int $period,
    ): ?array {
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
    ): ?array {
        $account = $this->accountRepository->getTinvestAccountByIdAndUserId($brokerAccountId, $userId);
        if ($account === null) {
            throw new EntityNotFountException('Account not found');
        }

        $from = $this->resolveFrom($from, $period, $account);

        $data = [];
        $this->snapshotRepository->findByAccountIdAndPeriod(
            $account->getId(),
            $userId,
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
    private function executeAllAccounts(string $userId, string $to, ?string $from, int $period): ?array
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
                foreach ($chunk as $s) {
                    /** @var PortfolioSnapshot $s */
                    $date = $s->getSnapshotDate();
                    if (!isset($grouped[$date])) {
                        $grouped[$date] = ['total_value_rub' => 0.0, 'cash_flow_rub' => 0.0];
                    }
                    $grouped[$date]['total_value_rub'] += $s->getTotalValueRub();
                    $grouped[$date]['cash_flow_rub'] += $s->getCashFlowRub();
                }
            }
        );

        if (!$grouped) {
            return null;
        }

        ksort($grouped);

        // TWR для агрегированного мультисчётного портфеля не вычисляется пословно:
        // снэпшоты разных счетов на одну дату имеют разные twr_factor.
        // Используем нейтральный множитель - итоговый TWR будет 0%.
        $data = array_map(
            static fn(string $date, array $row) => [
                'date' => $date,
                'total_value_rub' => $row['total_value_rub'],
                'cash_flow_rub' => $row['cash_flow_rub'],
                'twr_factor' => PortfolioMath::NEUTRAL_TWR_FACTOR,
            ],
            array_keys($grouped),
            array_values($grouped),
        );

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

    private function buildResult(array $data): array
    {
        $periodTwr = array_reduce(
            $data,
            static fn(float $carry, array $d) => $carry * $d['twr_factor'],
            PortfolioMath::NEUTRAL_TWR_FACTOR,
        );

        $first = reset($data);
        $last = $data[count($data) - 1] ?? null;

        return [
            'data' => $data,
            'summary' => [
                'twr_percent' => round(($periodTwr - PortfolioMath::NEUTRAL_TWR_FACTOR) * 100, 2),
                'first_value' => $first !== false ? $first['total_value_rub'] : PortfolioMath::ZERO,
                'last_value' => $last !== null ? $last['total_value_rub'] : PortfolioMath::ZERO,
            ],
        ];
    }
}
