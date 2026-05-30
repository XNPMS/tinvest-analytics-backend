<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Tinvest\Repository\CurrencyRateRepository;

readonly class CurrencyRateService
{
    public const DATE_FORMAT = 'Y-m-d';
    public const TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private CurrencyRateRepository $repository)
    {
    }

    /**
     * Сохраняет курсы на сегодняшнюю дату (upsert).
     *
     * @param array<string, float> $rates [currency => rate_to_rub]
     */
    public function saveTodayRates(array $rates): void
    {
        if (!$rates) {
            return;
        }

        $today = date(self::DATE_FORMAT);
        $now = date(self::TIME_FORMAT);

        $rows = [];
        foreach ($rates as $currency => $rate) {
            $rows[] = [
                'rate_date' => $today,
                'currency' => strtolower($currency),
                'rate_to_rub' => $rate,
                'updated_at' => $now,
            ];
        }

        $this->repository->upsertBatch($rows);
    }

    /**
     * @param string[] $currencies ISO-коды в нижнем регистре
     * @return array<string, float> [currency => rate_to_rub]
     */
    public function getRatesByCurrencies(array $currencies): array
    {
        return $this->repository->findLatestRatesByCurrencies($currencies);
    }

    /**
     * Сохраняет исторические курсы за диапазон дат.
     *
     * @param array<string, float> $rates [Y-m-d => rate]
     */
    public function saveHistoricalRates(string $currency, array $rates): void
    {
        if (!$rates) {
            return;
        }

        $now = date(self::TIME_FORMAT);
        $rows = [];

        foreach ($rates as $date => $rate) {
            $rows[] = [
                'rate_date' => $date,
                'currency' => strtolower($currency),
                'rate_to_rub' => $rate,
                'updated_at' => $now,
            ];
        }

        $this->repository->upsertBatch($rows);
    }

    /**
     * @return array<string, float> [Y-m-d => rate]
     */
    public function getHistoricalRates(string $currency, string $from, string $to): array
    {
        return $this->repository->findRatesByCurrencyAndDateRange(strtolower($currency), $from, $to);
    }
}
