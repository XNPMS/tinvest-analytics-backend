<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Tinvest\DTO\AssetAllocation;
use Tinvest\DTO\AssetAllocationBreakdownItem;
use Tinvest\DTO\AssetAllocationItem;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Repository\InstrumentRepository;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Service\CurrencyRateService;

readonly class GetAssetAllocationUseCase
{
    public function __construct(
        private TinvestAccountRepository $accountRepository,
        private PortfolioSnapshotRepository $snapshotRepository,
        private CurrencyRateService $currencyRateService,
        private InstrumentRepository $instrumentRepository,
    ) {
    }

    public function execute(string $userId, array $accountIds): ?AssetAllocation
    {
        if (empty($accountIds)) {
            $accounts = $this->accountRepository->findSyncedByUserId($userId);
            if ($accounts->isEmpty()) {
                return null;
            }

            $accountIds = array_map(static fn(TinvestAccount $account) => $account->getId(), $accounts->all());
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
                    'currency' => $position->currentPriceCurrency,
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

            $items[] = new AssetAllocationItem(
                $item['ticker'],
                $item['instrument_type'],
                round($item['value_rub'], 2),
                $percent,
            );
        }

        usort($items, static fn(AssetAllocationItem $a, AssetAllocationItem $b) => $b->valueRub <=> $a->valueRub);

        $sectors = $this->instrumentRepository->findSectorsByTickers(array_keys($aggregated));

        $byType = [];
        $byCurrency = [];
        $bySector = [];

        foreach ($aggregated as $ticker => $item) {
            $valueRub = $item['value_rub'];

            $type = $item['instrument_type'];
            $byType[$type] = ($byType[$type] ?? 0.0) + $valueRub;

            $currency = $item['currency'];
            $byCurrency[$currency] = ($byCurrency[$currency] ?? 0.0) + $valueRub;

            $sector = $sectors[$ticker] ?? null;
            if ($sector !== null) {
                $bySector[$sector] = ($bySector[$sector] ?? 0.0) + $valueRub;
            }
        }

        return new AssetAllocation(
            round($totalValueRub, 2),
            $items,
            $this->buildBreakdown($byType, $totalValueRub),
            $this->buildBreakdown($byCurrency, $totalValueRub),
            $this->buildBreakdown($bySector, $totalValueRub),
        );
    }

    /**
     * @param array<string, float> $grouped
     * @return AssetAllocationBreakdownItem[]
     */
    private function buildBreakdown(array $grouped, float $totalValueRub): array
    {
        $result = [];
        foreach ($grouped as $key => $valueRub) {
            $result[] = new AssetAllocationBreakdownItem(
                $key,
                round($valueRub, 2),
                $totalValueRub > 0.0 ? round($valueRub / $totalValueRub * 100, 2) : 0.0,
            );
        }
        usort(
            $result,
            static fn(
                AssetAllocationBreakdownItem $a,
                AssetAllocationBreakdownItem $b,
            ) => $b->valueRub <=> $a->valueRub,
        );

        return $result;
    }
}
