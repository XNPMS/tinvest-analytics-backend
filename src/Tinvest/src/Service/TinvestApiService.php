<?php

declare(strict_types=1);

namespace Tinvest\Service;

use DateTimeImmutable;
use Exception;
use Google\Protobuf\Timestamp;
use JsonException;
use Metaseller\TinkoffInvestApi2\TinkoffClientsFactory;
use stdClass;
use Tinkoff\Invest\V1\Account;
use Tinkoff\Invest\V1\AccountStatus;
use Tinkoff\Invest\V1\CandleInterval;
use Tinkoff\Invest\V1\GetAccountsRequest;
use Tinkoff\Invest\V1\GetAccountsResponse;
use Tinkoff\Invest\V1\GetCandlesRequest;
use Tinkoff\Invest\V1\GetCandlesResponse;
use Tinkoff\Invest\V1\GetLastPricesRequest;
use Tinkoff\Invest\V1\GetLastPricesResponse;
use Tinkoff\Invest\V1\GetOperationsByCursorRequest;
use Tinkoff\Invest\V1\GetOperationsByCursorResponse;
use Tinkoff\Invest\V1\HistoricCandle;
use Tinkoff\Invest\V1\InstrumentIdType;
use Tinkoff\Invest\V1\InstrumentRequest;
use Tinkoff\Invest\V1\LastPrice;
use Tinkoff\Invest\V1\OperationItem;
use Tinkoff\Invest\V1\OperationState;
use Tinkoff\Invest\V1\PortfolioPosition;
use Tinkoff\Invest\V1\PortfolioRequest;
use Tinkoff\Invest\V1\PortfolioResponse;
use Tinvest\DTO\TinvestOperation;
use Tinvest\Entity\TinvestAccount;
use Tinvest\Enum\LimitTokens;
use Tinvest\Exception\TinvestGrpcException;

readonly class TinvestApiService
{
    private const MAX_LIMIT_OPERATIONS = 1_000;
    private const NANO_DIVISOR = 1_000_000_000;

    // Диспатч по asset_type: [метод клиента, есть ли getSector() в ответе]
    // Все типы используют одинаковый InstrumentRequest и возвращают $response->getInstrument()
    private const INSTRUMENT_FETCH_CONFIG = [
        'stock' => ['ShareBy', true],
        'bond' => ['BondBy', true],
        'etf' => ['EtfBy', true],
        'future' => ['FutureBy', false],
        'currency' => ['CurrencyBy', false],
        'option' => ['OptionBy', false],
        // other → GetInstrumentBy (generic, без сектора)
    ];

    // Maps ISO-код валюты → идентификатор инструмента для GetLastPrices (формат ticker_CLASSCODE)
    // CETS - секция валютного рынка Московской биржи
    private const CURRENCY_INSTRUMENT_IDS = [
        'usd' => 'USD000UTSTOM_CETS',
        'eur' => 'EUR_RUB__TOM_CETS',
        'gbp' => 'GBPRUB_TOM_CETS',
        'chf' => 'CHFRUB_TOM_CETS',
        'cny' => 'CNYRUB_TOM_CETS',
        'hkd' => 'HKDRUB_TOM_CETS',
        'try' => 'TRYRUB_TOM_CETS',
    ];

    private function getApiClient(string $token): TinkoffClientsFactory
    {
        return TinkoffClientsFactory::create($token);
    }

    /**
     * Исходя из открытах источников, у юзеров может быть до 10 открытых счетов
     *
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    public function getAllAccountsTinvest(string $token): array
    {
        $accounts = [];
        /** @var GetAccountsResponse $response */
        [$response, $status] = $this->getApiClient($token)->usersServiceClient
            ->GetAccounts((new GetAccountsRequest())->setStatus(AccountStatus::ACCOUNT_STATUS_ALL))
            ->wait();

        $this->handleGrpcError($status);
        /** @var Account $account */
        foreach ($response->getAccounts() as $account) {
            $accounts[] = [
                'account_id' => $account->getId(),
                'name' => $account->getName(),
                'status' => $account->getStatus(),
                'type' => $account->getType(),
                'opened_date' => $account->getOpenedDate()
                    ? $account->getOpenedDate()->toDateTime()->format(CurrencyRateService::TIME_FORMAT)
                    : null,
                'access_level' => $account->getAccessLevel(),
            ];
        }

        return $accounts;
    }

    /**
     * Считает общее количество операций по счёту без создания DTO.
     * Используется для расчёта прогресса до запуска полной синхронизации.
     *
     * @throws TinvestGrpcException
     * @throws JsonException
     * @throws Exception
     */
    public function countOperations(string $token, TinvestAccount $tinvestAccount): int
    {
        $apiClient = $this->getApiClient($token);
        $rateLimiter = new RateLimiter(LimitTokens::MAX_TOKENS_SERVICE_OPERATIONS);

        $total = 0;
        $hasNext = true;
        $cursor = '';

        while ($hasNext) {
            $rateLimiter->consume();

            /** @var GetOperationsByCursorResponse $response */
            [$response, $status] = $apiClient->operationsServiceClient
                ->GetOperationsByCursor(
                    (new GetOperationsByCursorRequest())
                        ->setCursor($cursor)
                        ->setLimit(self::MAX_LIMIT_OPERATIONS)
                        ->setAccountId($tinvestAccount->getAccountId())
                        ->setFrom($this->createTimestamp($tinvestAccount->getOpenedDate()))
                        ->setTo($this->createTimestamp())
                        ->setState(OperationState::OPERATION_STATE_EXECUTED)
                )
                ->wait();

            $this->handleGrpcError($status);

            $total += count($response->getItems());
            $hasNext = $response->getHasNext();
            $cursor = $response->getNextCursor();
        }

        return $total;
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     * @throws Exception
     */
    public function streamOperations(
        string $token,
        TinvestAccount $tinvestAccount,
        callable $callable,
        int $batchSize,
        string $cursor = '',
    ): void {
        $apiClient = $this->getApiClient($token);
        $rateLimiter = new RateLimiter(LimitTokens::MAX_TOKENS_SERVICE_OPERATIONS);

        $hasNext = true;
        $batch = [];

        while ($hasNext) {
            $rateLimiter->consume();
            /** @var GetOperationsByCursorResponse $response */
            [$response, $status] = $apiClient->operationsServiceClient
                ->GetOperationsByCursor(
                    (new GetOperationsByCursorRequest())
                        ->setCursor($cursor)
                        ->setLimit(self::MAX_LIMIT_OPERATIONS)
                        ->setAccountId($tinvestAccount->getAccountId())
                        ->setFrom($this->createTimestamp($tinvestAccount->getOpenedDate()))
                        ->setTo($this->createTimestamp())
                        ->setState(OperationState::OPERATION_STATE_EXECUTED)
                )
                ->wait();

            $this->handleGrpcError($status);
            /** @var OperationItem $operation */
            foreach ($response->getItems() as $operation) {
                $commissionValue = $operation->getCommission();
                $commission = $commissionValue !== null
                    ? (int)$commissionValue->getUnits() + $commissionValue->getNano() / self::NANO_DIVISOR
                    : 0.0;

                // есть свойство brokerAccountId
                $batch[] = new TinvestOperation(
                    $operation->getId(),
                    $operation->getParentOperationId(),
                    $operation->getName(),
                    $operation->getPayment()?->getCurrency(),
                    $operation->getPayment()?->getUnits(),
                    $operation->getPayment()?->getNano(),
                    $operation->getPrice()?->serializeToJsonString(),
                    $operation->getState(),
                    (float)$operation->getQuantity(),
                    (float)$operation->getQuantityRest(),
                    $operation->getFigi(),
                    $operation->getInstrumentType(),
                    date(CurrencyRateService::TIME_FORMAT, $operation->getDate()?->getSeconds()),
                    $operation->getType(),
                    $operation->getTradesInfo()?->serializeToJsonString(),
                    $operation->getAssetUid(),
                    $operation->getPositionUid(),
                    $operation->getTicker(),
                    $operation->getClassCode(),
                    $operation->getInstrumentUid(),
                    $operation->getDescription(),
                    json_encode(
                        iterator_to_array($operation->getChildOperations()?->getIterator()),
                        JSON_THROW_ON_ERROR
                    ),
                    $commission,
                );

                // Если батч достиг размера, вызываем callback
                if (count($batch) >= $batchSize) {
                    $callable($batch);
                    $batch = [];
                }
            }

            $hasNext = $response->getHasNext();
            $cursor = $response->getNextCursor();
        }

        // Обработка оставшегося батча
        if ($batch) {
            $callable($batch);
        }
    }

    /**
     * Получает полные данные инструмента через тип-специфичный эндпоинт.
     * ShareBy/BondBy/EtfBy возвращают sector; остальные — только базовые поля.
     * Возвращает null, если инструмент не найден или gRPC вернул ошибку.
     *
     * @return array{figi:string,name:string|null,ticker:string|null,isin:string|null,sector:string|null,exchange:string,lot_size:int,nominal:float|null}|null
     * @throws JsonException
     * @throws TinvestGrpcException
     */
    public function getInstrumentDetails(string $token, string $figi, string $assetType): ?array
    {
        [$method, $hasSector] = self::INSTRUMENT_FETCH_CONFIG[$assetType] ?? ['GetInstrumentBy', false];

        [$response, $status] = $this->getApiClient($token)->instrumentsServiceClient
            ->$method(
                (new InstrumentRequest())
                ->setIdType(InstrumentIdType::INSTRUMENT_ID_TYPE_FIGI)
                ->setId($figi)
            )
            ->wait();

        $this->handleGrpcError($status);

        $instrument = $response->getInstrument();
        if ($instrument === null) {
            return null;
        }

        $nominal = null;
        if ($assetType === 'bond' && method_exists($instrument, 'getNominal')) {
            $nominalValue = $instrument->getNominal();
            if ($nominalValue !== null) {
                $nominal = $nominalValue->getUnits() + $nominalValue->getNano() / self::NANO_DIVISOR;
                if ($nominal <= 0.0) {
                    $nominal = null;
                }
            }
        }

        return [
            'figi' => $figi,
            'name' => $instrument->getName() ?: null,
            'ticker' => $instrument->getTicker() ?: null,
            'isin' => method_exists($instrument, 'getIsin') ? $instrument->getIsin() : null,
            'currency' => method_exists($instrument, 'getNominal') && $instrument->getNominal() !== null
                ? strtolower($instrument->getNominal()->getCurrency() ?: 'rub')
                : strtolower($instrument->getCurrency() ?: 'rub'),
            'sector' => $hasSector && method_exists($instrument, 'getSector')
                ? $instrument->getSector()
                : null,
            // Пустая строка вместо null: маркер того что инструмент уже обогащался
            'exchange' => $instrument->getExchange() ?: '',
            'lot_size' => max(1, (int)$instrument->getLot()),
            'nominal' => $nominal,
        ];
    }

    /**
     * Возвращает текущие курсы переданных валют к рублю через GetLastPrices.
     * Цена в ответе — за 1 единицу валюты (не лот).
     *
     * @param string[] $currencies ISO-коды в нижнем регистре, например ['usd', 'eur']
     * @return array<string, float> [currency => rate_to_rub]
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    public function getCurrencyRatesToRub(string $token, array $currencies): array
    {
        $instrumentIds = [];
        $instrumentIdToCurrency = [];

        foreach ($currencies as $currency) {
            $currency = strtolower($currency);
            $instrumentId = self::CURRENCY_INSTRUMENT_IDS[$currency] ?? null;
            if ($instrumentId === null) {
                continue;
            }

            $instrumentIds[] = $instrumentId;
            $instrumentIdToCurrency[$instrumentId] = $currency;
        }

        if (!$instrumentIds) {
            return [];
        }

        /** @var GetLastPricesResponse $response */
        [$response, $status] = $this->getApiClient($token)->marketDataServiceClient
            ->GetLastPrices((new GetLastPricesRequest())->setInstrumentId($instrumentIds))
            ->wait();

        $this->handleGrpcError($status);

        $rates = [];
        /** @var LastPrice $lastPrice */
        foreach ($response->getLastPrices() as $lastPrice) {
            // Ответ содержит ticker и class_code — собираем обратно в instrument_id формат
            $key = sprintf('%s_%s', $lastPrice->getTicker(), $lastPrice->getClassCode());
            $currency = $instrumentIdToCurrency[$key] ?? null;
            $price = $lastPrice->getPrice();

            if ($currency !== null && $price !== null) {
                $rates[$currency] = $price->getUnits() + $price->getNano() / self::NANO_DIVISOR;
            }
        }

        return $rates;
    }

    /**
     * Возвращает последние цены для переданных инструментов.
     *
     * @param string[] $instrumentIds формат ticker_classCode
     * @return array<string, float> [ticker_classCode => last_price]
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    public function getLastPricesByInstrumentIds(string $token, array $instrumentIds): array
    {
        if (!$instrumentIds) {
            return [];
        }

        /** @var GetLastPricesResponse $response */
        [$response, $status] = $this->getApiClient($token)->marketDataServiceClient
            ->GetLastPrices((new GetLastPricesRequest())->setInstrumentId($instrumentIds))
            ->wait();

        $this->handleGrpcError($status);

        $prices = [];
        /** @var LastPrice $lastPrice */
        foreach ($response->getLastPrices() as $lastPrice) {
            $key = sprintf('%s_%s', $lastPrice->getTicker(), $lastPrice->getClassCode());
            $price = $lastPrice->getPrice();
            if ($price !== null) {
                $prices[$key] = $price->getUnits() + $price->getNano() / self::NANO_DIVISOR;
            }
        }

        return $prices;
    }

    /**
     * Возвращает исторические дневные свечи для инструмента.
     * Разбивает запрос на годовые чанки (лимит T-API — 1 год на запрос).
     *
     * @return array<string, float> [Y-m-d => close_price]
     * @throws Exception
     */
    public function getHistoricalCandles(string $token, string $instrumentId, string $from, string $to): array
    {
        $apiClient = $this->getApiClient($token);
        $rateLimiter = new RateLimiter(LimitTokens::MAX_TOKENS_MARKET_DATA);
        $result = [];

        $fromDt = new DateTimeImmutable($from);
        $toDt = new DateTimeImmutable($to);
        $chunkFrom = $fromDt;

        while ($chunkFrom <= $toDt) {
            $chunkTo = $chunkFrom->modify('+1 year');
            if ($chunkTo > $toDt) {
                $chunkTo = $toDt;
            }

            $rateLimiter->consume();

            /** @var GetCandlesResponse $response */
            [$response, $status] = $apiClient->marketDataServiceClient
                ->GetCandles(
                    (new GetCandlesRequest())
                        ->setInstrumentId($instrumentId)
                        ->setFrom($this->createTimestamp($chunkFrom->format(CurrencyRateService::DATE_FORMAT)))
                        ->setTo($this->createTimestamp(
                            $chunkTo->modify('+1 day')->format(CurrencyRateService::DATE_FORMAT)
                        ))
                        ->setInterval(CandleInterval::CANDLE_INTERVAL_DAY)
                )
                ->wait();

            // Rate limit - ждём сброса и повторяем тот же чанк
            if (($status->code ?? null) === 8) {
                $resetSeconds = (int)($status->metadata['x-ratelimit-reset'][0] ?? 30) + 2;
                sleep($resetSeconds);
                continue;
            }

            $this->handleGrpcError($status);

            if ($response === null) {
                break;
            }

            /** @var HistoricCandle $candle */
            foreach ($response->getCandles() as $candle) {
                $close = $candle->getClose();
                $time = $candle->getTime();
                if ($close === null || $time === null) {
                    continue;
                }
                $date = date(CurrencyRateService::DATE_FORMAT, $time->getSeconds());
                $result[$date] = $close->getUnits() + $close->getNano() / self::NANO_DIVISOR;
            }

            $chunkFrom = $chunkTo->modify('+1 day');
        }

        return $result;
    }

    /**
     * Возвращает исторические дневные курсы валюты к рублю через свечи валютного инструмента.
     *
     * @return array<string, float> [Y-m-d => rate]
     * @throws Exception
     */
    public function getHistoricalCurrencyRates(string $token, string $currency, string $from, string $to): array
    {
        $instrumentId = self::CURRENCY_INSTRUMENT_IDS[strtolower($currency)] ?? null;
        if ($instrumentId === null) {
            return [];
        }

        return $this->getHistoricalCandles($token, $instrumentId, $from, $to);
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    public function getPortfolio(string $token, string $accountId): array
    {
        /** @var PortfolioResponse $response */
        [$response, $status] = $this->getApiClient($token)->operationsServiceClient
            ->GetPortfolio((new PortfolioRequest())->setAccountId($accountId))
            ->wait();

        $this->handleGrpcError($status);

        $positions = [];
        $expectedYieldRub = 0.0;
        /** @var PortfolioPosition $position */
        foreach ($response->getPositions() as $position) {
            $qty = $position->getQuantity();
            $currentPrice = $position->getCurrentPrice();
            $avgPrice = $position->getAveragePositionPrice();
            $expectedYield = $position->getExpectedYield();

            $expectedYieldValue = $expectedYield !== null
                ? $expectedYield->getUnits() + $expectedYield->getNano() / self::NANO_DIVISOR
                : 0.0;

            $expectedYieldRub += $expectedYieldValue;

            $positions[] = [
                'ticker' => $position->getTicker(),
                'instrument_type' => $position->getInstrumentType(),
                'quantity' => $qty !== null
                    ? $qty->getUnits() + $qty->getNano() / self::NANO_DIVISOR
                    : 0.0,
                'current_price' => $currentPrice !== null
                    ? $currentPrice->getUnits() + $currentPrice->getNano() / self::NANO_DIVISOR
                    : 0.0,
                'current_price_currency' => $currentPrice?->getCurrency(),
                'avg_price' => $avgPrice !== null
                    ? $avgPrice->getUnits() + $avgPrice->getNano() / self::NANO_DIVISOR
                    : 0.0,
                'avg_price_currency' => $avgPrice?->getCurrency(),
                'expected_yield' => $expectedYieldValue,
            ];
        }

        $totalAmountPortfolio = $response->getTotalAmountPortfolio();

        return [
            'total_value_rub' => $totalAmountPortfolio !== null
                ? $totalAmountPortfolio->getUnits() + $totalAmountPortfolio->getNano() / self::NANO_DIVISOR
                : 0.0,
            'expected_yield_rub' => $expectedYieldRub,
            'positions' => $positions,
        ];
    }

    /**
     * @throws TinvestGrpcException
     * @throws JsonException
     */
    private function handleGrpcError(stdClass $status): void
    {
        if (($status->code ?? null) !== 0) {
            throw new TinvestGrpcException(
                sprintf(
                    '[%s] Tinvest gRPC error: code=%s details=%s metadata=%s',
                    date(CurrencyRateService::TIME_FORMAT),
                    $status->code ?? 'unknown',
                    $status->details ?? '',
                    json_encode($status->metadata ?? [], JSON_THROW_ON_ERROR)
                )
            );
        }
    }

    /**
     * @throws Exception
     */
    private function createTimestamp(string $dateTime = 'now'): Timestamp
    {
        return (new Timestamp())->setSeconds((new DateTimeImmutable($dateTime))->getTimestamp());
    }
}
