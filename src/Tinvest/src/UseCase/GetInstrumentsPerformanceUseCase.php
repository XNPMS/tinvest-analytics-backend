<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Illuminate\Support\Collection;
use JsonException;
use Tinvest\Collection\OperationsCollection;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Entity\TinvestOperation;
use Tinvest\Helper\FifoLedger;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestAccountRepository;
use Tinvest\Repository\TinvestOperationRepository;
use Tinvest\Repository\TinvestSplitRepository;
use Tinvest\Service\CurrencyRateService;
use User\Entity\User;

readonly class GetInstrumentsPerformanceUseCase
{
    public function __construct(
        private TinvestAccountRepository $accountRepository,
        private TinvestOperationRepository $operationRepository,
        private PortfolioSnapshotRepository $snapshotRepository,
        private TinvestSplitRepository $splitRepository,
        private CurrencyRateService $currencyRateService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(User $user, bool $openOnly): array
    {
        $accounts = $this->accountRepository->findSyncedByUserId($user->getId());
        if ($accounts->isEmpty()) {
            return [];
        }

        $operations = [];
        $accountIds = $accounts->map(static fn(TinvestAccount $account) => $account->getId())->toArray();
        $this->operationRepository->findInstrumentOperationsByAccountIds(
            $user->getId(),
            $accountIds,
            static function (Collection $chunk) use (&$operations): void {
                foreach ($chunk as $operation) {
                    $operations[] = $operation;
                }
            }
        );

        usort(
            $operations,
            static fn(TinvestOperation $a, TinvestOperation $b) => strcmp($a->getDate(), $b->getDate())
        );

        $tickers = array_values(array_unique(array_filter(
            array_map(static fn(TinvestOperation $op) => $op->getTicker(), $operations)
        )));
        $splitsByTicker = $this->splitRepository->findByTickersGroupedByTicker($tickers);

        $ledger = new FifoLedger($splitsByTicker);
        $ledger->feed(new OperationsCollection($operations));
        $instruments = $ledger->positions();

        if ($openOnly) {
            $instruments = array_filter($instruments, static fn(array $instrument) => $instrument['is_open']);
        }

        $openKeys = array_keys(array_filter($instruments, static fn(array $instrument) => $instrument['is_open']));
        if ($openKeys) {
            try {
                $currentPricesRub = $this->buildPrices(
                    $this->snapshotRepository->findLatestForAccounts($accountIds)
                );
                $yesterdayPricesRub = $this->buildPrices(
                    $this->snapshotRepository->findLatestForAccounts($accountIds, date('Y-m-d'))
                );
                $this->enrichOpenPositions($instruments, $openKeys, $currentPricesRub, $yesterdayPricesRub);
            } catch (JsonException) {
            }
        }

        $this->finalizeAnnualizedReturn($instruments);

        return array_values($instruments);
    }

    /**
     * @param array<string, array<string, mixed>> $instruments
     * @param string[] $openKeys
     * @param array<string, float> $currentPricesRub  [ticker => price]
     * @param array<string, float> $yesterdayPricesRub [ticker => price]
     */
    private function enrichOpenPositions(
        array &$instruments,
        array $openKeys,
        array $currentPricesRub,
        array $yesterdayPricesRub,
    ): void {
        foreach ($openKeys as $key) {
            $instrument = &$instruments[$key];
            $currentPrice = $currentPricesRub[$instrument['ticker']] ?? null;
            if ($currentPrice === null) {
                continue;
            }

            $qty = $instrument['quantity'];
            $avgPrice = $instrument['avg_price_rub'];

            $instrument['current_price_rub'] = round($currentPrice, 4);
            $instrument['unrealized_pnl_rub'] = round(($currentPrice - $avgPrice) * $qty, 2);
            $instrument['total_pnl_rub'] = round(
                $instrument['realized_pnl_rub'] + $instrument['unrealized_pnl_rub'],
                2
            );

            $totalCostRub = $avgPrice * $qty;
            if ($totalCostRub > 0.0) {
                $instrument['total_pnl_percent'] = round($instrument['total_pnl_rub'] / $totalCostRub * 100, 2);
            }

            $yesterdayPrice = $yesterdayPricesRub[$instrument['ticker']] ?? null;
            if ($yesterdayPrice !== null && $yesterdayPrice > 0.0) {
                $instrument['today_pnl_rub'] = round(($currentPrice - $yesterdayPrice) * $qty, 2);
                $instrument['today_pnl_percent'] = round(
                    ($currentPrice - $yesterdayPrice) / $yesterdayPrice * 100,
                    2
                );
            }
        }
    }

    /**
     * Рассчитывает годовую доходность для всех инструментов.
     * Вызывается после enrichOpenPositions, когда total_pnl_percent уже заполнен.
     * Формула: ((1 + totalPnl%) ^ (365 / holdingDays)) - 1
     *
     * @param array<string, array<string, mixed>> $instruments
     */
    private function finalizeAnnualizedReturn(array &$instruments): void
    {
        $today = date(CurrencyRateService::DATE_FORMAT);

        foreach ($instruments as &$instrument) {
            $firstBuyDate = $instrument['_first_buy_date'];
            $isOpen = $instrument['is_open'];

            // Для открытых позиций считаем только если получили текущую цену
            if ($firstBuyDate !== null && (!$isOpen || $instrument['current_price_rub'] !== null)) {
                $startDate = substr($firstBuyDate, 0, 10);
                $endDate = $isOpen
                    ? $today
                    : substr($instrument['_last_sell_date'] ?? $today, 0, 10);

                $holdingDays = max(1, (int)round((strtotime($endDate) - strtotime($startDate)) / 86400));
                $simpleReturn = $instrument['total_pnl_percent'] / 100;

                // (1 + r) должно быть > 0, иначе дробная степень не определена
                if ($simpleReturn > -1.0) {
                    $instrument['annualized_pnl_percent'] = round(
                        (((1.0 + $simpleReturn) ** (365.0 / $holdingDays)) - 1.0) * 100,
                        2
                    );
                }
            }

            unset($instrument['_first_buy_date'], $instrument['_last_sell_date']);
        }
    }

    /**
     * Строит карту [ticker => price_rub] из коллекции снэпшотов.
     * Конвертирует валютные цены в рубли через CurrencyRateService.
     *
     * @return array<string, float>
     * @throws JsonException
     */
    private function buildPrices(Collection $snapshots): array
    {
        if ($snapshots->isEmpty()) {
            return [];
        }

        $currenciesNeeded = [];
        $positionsByTicker = [];
        foreach ($snapshots as $snapshot) {
            foreach ($snapshot->getPositions() as $position) {
                if (!isset($positionsByTicker[$position->ticker])) {
                    $positionsByTicker[$position->ticker] = $position;
                    if ($position->currentPriceCurrency !== 'rub') {
                        $currenciesNeeded[$position->currentPriceCurrency] = true;
                    }
                }
            }
        }

        $fxRates = ['rub' => 1.0];
        if ($currenciesNeeded) {
            $fxRates += $this->currencyRateService->getRatesByCurrencies(array_keys($currenciesNeeded));
        }

        $result = [];
        foreach ($positionsByTicker as $ticker => $position) {
            $rate = $fxRates[$position->currentPriceCurrency] ?? 1.0;
            $result[$ticker] = $position->currentPrice * $rate;
        }

        return $result;
    }
}
