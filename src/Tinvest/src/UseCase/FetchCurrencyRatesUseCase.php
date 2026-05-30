<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Tinvest\Repository\InstrumentRepository;
use Tinvest\Repository\TinvestOperationRepository;
use Tinvest\Service\CurrencyRateService;
use Tinvest\Service\TinvestApiService;

readonly class FetchCurrencyRatesUseCase
{
    public function __construct(
        private TinvestApiService $apiService,
        private CurrencyRateService $rateService,
        private TinvestOperationRepository $operationRepository,
        private InstrumentRepository $instrumentRepository,
    ) {
    }

    /**
     * Запрашивает актуальные курсы валют через T-API и сохраняет в currency_rates.
     * RUB пропускается — курс к рублю по определению равен 1.
     * Валюты, отсутствующие в CURRENCY_INSTRUMENT_IDS, логируются и пропускаются в TinvestApiService.
     */
    public function execute(string $token, array $accountIds): void
    {
        $allCurrencies = $this->operationRepository->findDistinctCurrenciesByAccountIds($accountIds);
        // Добавляем валюты номиналов инструментов (замещающие облигации: currency = usd)
        $nominalCurrencies = $this->instrumentRepository->findDistinctNominalCurrenciesByAccountIds($accountIds);
        $allCurrencies = array_unique(array_merge($allCurrencies, $nominalCurrencies));

        $foreignCurrencies = array_values(
            array_filter($allCurrencies, static fn(string $c) => $c !== 'rub')
        );

        if (!$foreignCurrencies) {
            return;
        }

        $rates = $this->apiService->getCurrencyRatesToRub($token, $foreignCurrencies);
        if (!$rates) {
            return;
        }

        $this->rateService->saveTodayRates($rates);
    }
}
