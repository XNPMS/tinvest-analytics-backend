<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Tinvest\Entity\TinvestAccount;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Service\CurrencyRateService;

readonly class GetAssetAllocationUseCase
{
    public function __construct(
        private TinvestAccountRepository $accountRepository,
        private PortfolioSnapshotRepository $snapshotRepository,
        private CurrencyRateService $currencyRateService,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function execute(string $userId, array $accountIds): ?array
    {
        if (empty($accountIds)) {
            $accounts = $this->accountRepository->findSyncedByUserId($userId);
            if ($accounts->isEmpty()) {
                return null;
            }

            $accountIds = array_map(static fn(TinvestAccount $a) => $a->getId(), $accounts->all());
        }

        $allPositions = [];
        $foreignCurrencies = [];

        foreach ($accountIds as $accountId) {
            $snapshot = $this->snapshotRepository->findLatestByAccountId($accountId);
            if ($snapshot === null) {
                continue;
            }

            foreach ($snapshot->getPositions() as $position) {
                if ($position->currentPriceCurrency !== 'rub') {
                    $foreignCurrencies[$position->currentPriceCurrency] = true;
                }
                $allPositions[] = $position;
            }
        }

        if (!$allPositions) {
            return null;
        }

        $rates = $this->currencyRateService->getRatesByCurrencies(array_keys($foreignCurrencies));
        $rates['rub'] = 1.0;

        $aggregated = [];
        foreach ($allPositions as $position) {
            $rate = $rates[$position->currentPriceCurrency] ?? 1.0;
            $valueRub = $position->quantity * $position->currentPrice * $rate;

            if (!isset($aggregated[$position->ticker])) {
                $aggregated[$position->ticker] = [
                    'ticker' => $position->ticker,
                    'instrument_type' => $position->instrumentType,
                    'value_rub' => 0.0,
                ];
            }

            $aggregated[$position->ticker]['value_rub'] += $valueRub;
        }

        $totalValueRub = array_sum(array_column($aggregated, 'value_rub'));

        $items = [];
        foreach ($aggregated as $item) {
            $percent = $totalValueRub > 0.0
                ? round($item['value_rub'] / $totalValueRub * 100, 2)
                : 0.0;

            $items[] = [
                'ticker' => $item['ticker'],
                'instrument_type' => $item['instrument_type'],
                'value_rub' => round($item['value_rub'], 2),
                'percent' => $percent,
            ];
        }

        usort($items, static fn(array $a, array $b) => $b['value_rub'] <=> $a['value_rub']);

        return [
            'total_value_rub' => round($totalValueRub, 2),
            'items' => $items,
        ];
    }
}
