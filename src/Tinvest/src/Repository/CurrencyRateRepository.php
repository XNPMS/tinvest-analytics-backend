<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\CurrencyRate;

readonly class CurrencyRateRepository extends AbstractEloquentRepository
{
    /**
     * updated_at включаем в update-список: MySQL обновляет его через ON UPDATE CURRENT_TIMESTAMP,
     * но явная передача гарантирует корректное значение при upsert через Eloquent
     */
    private const UPSERT_UPDATE_COLUMNS = ['rate_to_rub', 'updated_at'];

    public function getEntityClass(): string
    {
        return CurrencyRate::class;
    }

    public function upsertBatch(array $rows): void
    {
        $this->createQueryBuilder()->upsert($rows, ['rate_date', 'currency'], self::UPSERT_UPDATE_COLUMNS);
    }

    /**
     * Для каждой валюты возвращает самый свежий известный курс.
     *
     * @param string[] $currencies ISO-коды в нижнем регистре
     * @return array<string, float> [currency => rate_to_rub]
     */
    public function findLatestRatesByCurrencies(array $currencies): array
    {
        $rates = [];
        foreach ($currencies as $currency) {
            /** @var CurrencyRate|null $row */
            $row = $this->createQueryBuilder()
                ->where('currency', '=', $currency)
                ->orderBy('rate_date', 'desc')
                ->first();

            if ($row !== null) {
                $rates[$currency] = $row->getRateToRub();
            }
        }

        return $rates;
    }

    /**
     * @return array<string, float> [Y-m-d => rate_to_rub]
     */
    public function findRatesByCurrencyAndDateRange(string $currency, string $from, string $to): array
    {
        return $this->createQueryBuilder()
            ->where('currency', $currency)
            ->whereBetween('rate_date', [$from, $to])
            ->orderBy('rate_date')
            ->pluck('rate_to_rub', 'rate_date')
            ->map(static fn($rate) => (float)$rate)
            ->toArray();
    }
}
