<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use DateTimeImmutable;
use Exception;
use JsonException;
use Psr\Log\LoggerInterface;
use Tinkoff\Invest\V1\OperationType;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Entity\TinvestOperation;
use Tinvest\Helper\PortfolioMath;
use Tinvest\Repository\InstrumentRepository;
use Tinvest\Repository\PortfolioSnapshotRepository;
use Tinvest\Repository\TinvestOperationRepository;
use Tinvest\Repository\TinvestSplitRepository;
use Tinvest\Service\CurrencyRateService;
use Tinvest\Service\TinvestApiService;

readonly class BackfillPortfolioHistoryUseCase
{
    /**
     * Тикер позиции свободного рублевого остатка - так его возвращает GetPortfolio
     */
    private const RUB_CASH_TICKER = 'RUB000UTSTOM';

    /**
     * Типы операций, изменяющие количество инструментов в портфеле
     */
    private const QTY_BUY_TYPES = [
        OperationType::OPERATION_TYPE_BUY,
        OperationType::OPERATION_TYPE_BUY_CARD,
        OperationType::OPERATION_TYPE_BUY_MARGIN,
    ];

    private const QTY_SELL_TYPES = [
        OperationType::OPERATION_TYPE_SELL,
        OperationType::OPERATION_TYPE_SELL_CARD,
        OperationType::OPERATION_TYPE_SELL_MARGIN,
        OperationType::OPERATION_TYPE_BOND_REPAYMENT,
        OperationType::OPERATION_TYPE_BOND_REPAYMENT_FULL,
        OperationType::OPERATION_TYPE_OUTPUT_SECURITIES,
    ];

    public function __construct(
        private TinvestApiService $apiService,
        private PortfolioSnapshotRepository $snapshotRepository,
        private TinvestOperationRepository $operationRepository,
        private InstrumentRepository $instrumentRepository,
        private TinvestSplitRepository $splitRepository,
        private CurrencyRateService $rateService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Строит исторические снэпшоты портфеля с даты открытия счета по вчерашний день.
     * Пропускает даты, для которых снэпшот уже существует.
     *
     * @throws Exception
     * @throws JsonException
     */
    public function execute(TinvestAccount $account, string $token, ?callable $onProgress = null): void
    {
        $accountId = $account->getId();
        $openedDate = (new DateTimeImmutable($account->getOpenedDate()))->format(CurrencyRateService::DATE_FORMAT);
        $yesterday = (new DateTimeImmutable())->modify('-1 day')->format(CurrencyRateService::DATE_FORMAT);
        $latestSnapshotDate = $this->snapshotRepository->findLatestSnapshotDate($accountId);

        $this->logger->info('Starting', [
            'account_id' => $account->getAccountId(),
            'opened_date' => $openedDate,
            'to' => $yesterday,
            'latest_snapshot' => $latestSnapshotDate,
        ]);

        if ($openedDate > $yesterday) {
            $this->logger->warning('The account opening date has not yet occurred.');

            return;
        }

        if ($latestSnapshotDate) {
            $cleanFrom = (new DateTimeImmutable($latestSnapshotDate))
                ->modify('-3 days')
                ->format(CurrencyRateService::DATE_FORMAT);
            $cleanTo = (new DateTimeImmutable($latestSnapshotDate))
                ->modify('-1 day')
                ->format(CurrencyRateService::DATE_FORMAT);

            $this->snapshotRepository->deleteByAccountIdAndDateRange($accountId, $cleanFrom, $cleanTo);
        }

        $operations = $this->operationRepository->findByAccountIdSortedByDate($accountId);
        if ($operations->isEmpty()) {
            $this->logger->warning('BackfillPortfolioHistory: No operations found.');

            return;
        }

        $operations = $operations->all();
        $opsByDate = $this->groupOperationsByDate($operations);
        $instruments = $this->collectInstruments($operations);

        // Загружаем lot_size и nominal из БД
        $instrumentData = $this->instrumentRepository->findByTickers(array_keys($instruments));
        // Загружаем исторические курсы для валютных инструментов
        $this->fetchHistoricalCurrencyRates($token, [$accountId], $openedDate, $yesterday);

        // Загружаем свечи. Облигации котируются в % от номинала, конвертируем в рубли.
        $priceMap = [];
        $totalInstruments = count($instruments);
        $fetchedInstruments = 0;
        foreach ($instruments as $ticker => $meta) {
            $instrumentType = $meta['type'];
            $instrumentId = empty($meta['figi']) ? $ticker : $meta['figi'];
            $candles = $this->apiService->getHistoricalCandles($token, $instrumentId, $openedDate, $yesterday);

            if ($instrumentType === 'bond') {
                $nominal = $instrumentData[$ticker]['nominal'] ?? null;
                $currency = strtolower($instrumentData[$ticker]['currency'] ?? 'rub');

                // GetCandles возвращает цену как % от номинала: 88.728 → factor = nominal/100
                $factor = $nominal !== null && $nominal > 0 ? $nominal / 100.0 : 10.0;

                if ($currency !== 'rub') {
                    $historicalRates = $this->rateService->getHistoricalRates($currency, $openedDate, $yesterday);

                    $candles = array_combine(
                        array_keys($candles),
                        array_map(function (float $price, string $date) use ($factor, $historicalRates) {
                            $fxRate = $historicalRates[$date] ?? $this->findNearestRate($historicalRates, $date) ?? 1.0;
                            return $price * $factor * $fxRate;
                        }, $candles, array_keys($candles))
                    );
                } else {
                    $candles = array_map(static fn(float $p) => $p * $factor, $candles);
                }
            }
            $priceMap[$ticker] = $candles;

            $fetchedInstruments++;
            if ($onProgress !== null && $totalInstruments > 0) {
                $onProgress($fetchedInstruments, $totalInstruments);
            }
        }

        $dates = $this->buildDateRange($openedDate, $yesterday);
        $existingDates = array_flip($this->snapshotRepository->findExistingDatesByAccountId($accountId));
        // Загружаем после авто-детекции, чтобы включить только что найденные сплиты
        $splitsMap = $this->splitRepository->findGroupedByDate($openedDate, $yesterday);
        $this->logger->info('BackfillPortfolioHistory: range loaded', [
            'account_id' => $account->getAccountId(),
            'dates_count' => count($dates),
            'existing_count' => count($existingDates),
            'first_date' => $dates[0] ?? null,
            'last_date' => end($dates) ?: null,
        ]);

        $holdings = []; // [ticker => float qty]
        $avgPrices = []; // [ticker => float avg_buy_price_rub]
        $cumulativeCash = 0.0;
        $prevTotalValue = 0.0;
        $prevCumulativeTwr = 1.0;
        $lastKnownPrices = []; // [ticker => float] — форвард-заполнение цен
        $savedCount = 0;

        foreach ($dates as $date) {
            // Применяем сплиты до обработки операций этого дня
            foreach ($splitsMap[$date] ?? [] as $splitTicker => $ratio) {
                if (isset($holdings[$splitTicker]) && $holdings[$splitTicker] > 0.0) {
                    $prevQty = $holdings[$splitTicker];
                    $newQty = $prevQty * $ratio;
                    $avgPrices[$splitTicker] = ($prevQty * ($avgPrices[$splitTicker] ?? 0.0)) / $newQty;
                    $holdings[$splitTicker] = $newQty;
                }
            }

            $ops = $opsByDate[$date] ?? [];
            $dailyCashFlow = 0.0;

            foreach ($ops as $op) {
                $type = (int)$op->getOperationType();
                $ticker = $op->getTicker();
                // GetOperationsByCursor возвращает quantity в штуках (units), не в лотах
                $qty = $op->getQuantity() - $op->getQuantityRest();
                $paymentRub = (float)($op->getPaymentRub() ?? 0);
                $commissionRub = $op->getCommissionRub();

                if ($ticker && $qty > 0 && in_array($type, self::QTY_BUY_TYPES, true)) {
                    $buyPrice = abs($paymentRub) / $qty;
                    $prevQty = $holdings[$ticker] ?? 0.0;
                    $prevAvg = $avgPrices[$ticker] ?? 0.0;
                    $newQty = $prevQty + $qty;
                    $avgPrices[$ticker] = $newQty > 0
                        ? ($prevQty * $prevAvg + $qty * $buyPrice) / $newQty
                        : 0.0;
                    $holdings[$ticker] = $newQty;
                } elseif ($ticker && $qty > 0 && $type === OperationType::OPERATION_TYPE_INPUT_SECURITIES) {
                    // Корпоративное действие (сплит, бонусные акции): бумаги поступают без оплаты.
                    // Суммарная стоимость покупки не меняется — средняя цена пересчитывается.
                    $prevQty = $holdings[$ticker] ?? 0.0;
                    $totalCost = $prevQty * ($avgPrices[$ticker] ?? 0.0);
                    $newQty = $prevQty + $qty;
                    $avgPrices[$ticker] = $newQty > 0 ? $totalCost / $newQty : 0.0;
                    $holdings[$ticker] = $newQty;
                } elseif ($ticker && $qty > 0 && in_array($type, self::QTY_SELL_TYPES, true)) {
                    $holdings[$ticker] = max(0.0, ($holdings[$ticker] ?? 0.0) - $qty);
                    if ($holdings[$ticker] < 0.0001) {
                        unset($holdings[$ticker], $avgPrices[$ticker]);
                    }
                }

                // Весь payment влияет на кассовый баланс (покупка/продажа/пополнение/вывод/купоны)
                $cumulativeCash += $paymentRub + $commissionRub;

                // Только INPUT/OUTPUT — внешний денежный поток для TWR
                if (in_array($type, PortfolioMath::CASH_FLOW_TYPES, true)) {
                    $dailyCashFlow += $paymentRub;
                }
            }

            // Обновляем известные цены из свечей
            foreach ($priceMap as $ticker => $dailyPrices) {
                if (isset($dailyPrices[$date])) {
                    $lastKnownPrices[$ticker] = $dailyPrices[$date];
                }
            }

            // Стоимость позиций по ценам закрытия (форвард-заполнение для выходных)
            $positionsValue = 0.0;
            $positionsJson = [];
            foreach ($holdings as $ticker => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                $price = $lastKnownPrices[$ticker] ?? 0.0;
                $positionsValue += $qty * $price;
                $avgPrice = $avgPrices[$ticker] ?? 0.0;
                $positionsJson[] = [
                    'ticker' => $ticker,
                    'instrument_type' => $instruments[$ticker]['type'],
                    'quantity' => $qty,
                    'current_price' => $price,
                    'current_price_currency' => $instrumentData[$ticker]['currency'] ?? 'rub',
                    'avg_price' => $avgPrice,
                    'avg_price_currency' => $instrumentData[$ticker]['currency'] ?? 'rub',
                    'expected_yield' => round(($price - $avgPrice) * $qty, 2),
                ];
            }

            // Добавляем свободный остаток в рублях как currency-позицию
            $positionsJson[] = [
                'ticker' => self::RUB_CASH_TICKER,
                'instrument_type' => 'currency',
                'quantity' => round($cumulativeCash, 2),
                'current_price' => 1.0,
                'current_price_currency' => 'rub',
                'avg_price' => 1.0,
                'avg_price_currency' => 'rub',
                'expected_yield' => 0.0,
            ];

            $totalValue = $positionsValue + $cumulativeCash;

            // Пропускаем дни до первого пополнения (портфель пустой)
            if ($totalValue <= 0) {
                $prevTotalValue = 0.0;
                $this->logger->debug('BackfillPortfolioHistory: skip zero-value date', [
                    'account_id' => $accountId,
                    'date' => $date,
                ]);

                continue;
            }

            $twrFactor = PortfolioMath::twrFactor($prevTotalValue, $totalValue, $dailyCashFlow);
            $cumulativeTwr = $prevCumulativeTwr * $twrFactor;

            // Не перезаписываем уже существующие снэпшоты (например, сегодняшний из CaptureCurrentPortfolioUseCase)
            if (!isset($existingDates[$date])) {
                $this->logger->debug('BackfillPortfolioHistory: saving snapshot', [
                    'account_id' => $accountId,
                    'date' => $date,
                    'total_value' => round($totalValue, 2),
                ]);
                $this->snapshotRepository->upsert([
                    'account_id' => $accountId,
                    'snapshot_date' => $date,
                    'total_value_rub' => round($totalValue, 2),
                    'expected_yield_rub' => round(array_sum(array_column($positionsJson, 'expected_yield')), 2),
                    'cash_flow_rub' => round($dailyCashFlow, 2),
                    'twr_factor' => round($twrFactor, 8),
                    'cumulative_twr' => round($cumulativeTwr, 8),
                    'positions_json' => json_encode($positionsJson, JSON_THROW_ON_ERROR),
                ]);
                $savedCount++;
            }

            $prevTotalValue = $totalValue;
            $prevCumulativeTwr = $cumulativeTwr;
        }

        $this->logger->info('BackfillPortfolioHistory: completed', [
            'account_id' => $accountId,
            'saved_count' => $savedCount,
        ]);
    }

    /**
     * @param TinvestOperation[] $operations
     * @return array<string, TinvestOperation[]>
     */
    private function groupOperationsByDate(array $operations): array
    {
        $result = [];
        foreach ($operations as $op) {
            $date = substr((string)$op->getDate(), 0, 10);
            $result[$date][] = $op;
        }

        return $result;
    }

    /**
     * @param TinvestOperation[] $operations
     * @return array<string, array{type: string, instrument_id: string}> [ticker => {type, instrument_id}]
     */
    private function collectInstruments(array $operations): array
    {
        $instruments = [];
        foreach ($operations as $op) {
            $ticker = $op->getTicker();
            $type = $op->getInstrumentType();
            if ($ticker && !empty($type)) {
                $instruments[$ticker] = [
                    'figi' => $op->getFigi(),
                    'type' => $type,
                    'instrument_id' => $op->getInstrumentId(),
                ];
            }
        }

        return $instruments;
    }

    /**
     * @return string[] список дат Y-m-d от $from до $to включительно
     * @throws Exception
     */
    private function buildDateRange(string $from, string $to): array
    {
        $dates = [];
        $current = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);

        while ($current <= $end) {
            $dates[] = $current->format(CurrencyRateService::DATE_FORMAT);
            $current = $current->modify('+1 day');
        }

        return $dates;
    }

    private function fetchHistoricalCurrencyRates(string $token, array $accountIds, string $from, string $to): void
    {
        $currencies = $this->instrumentRepository->findDistinctNominalCurrenciesByAccountIds($accountIds);

        foreach ($currencies as $currency) {
            if ($currency === 'rub') {
                continue;
            }

            $existing = $this->rateService->getHistoricalRates($currency, $from, $to);
            $allDates = $this->buildDateRange($from, $to);
            $missingDates = array_diff($allDates, array_keys($existing));

            if (!$missingDates) {
                continue;
            }

            $rates = $this->apiService->getHistoricalCurrencyRates(
                $token,
                $currency,
                min($missingDates),
                max($missingDates)
            );

            if ($rates) {
                $this->rateService->saveHistoricalRates($currency, $rates);
            }
        }
    }

    private function findNearestRate(array $rates, string $date): ?float
    {
        if (!$rates) {
            return null;
        }

        $available = array_filter(array_keys($rates), static fn(string $d) => $d <= $date);

        if (!$available) {
            return reset($rates);
        }

        return $rates[max($available)];
    }
}
