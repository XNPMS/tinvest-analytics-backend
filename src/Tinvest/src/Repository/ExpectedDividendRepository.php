<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use Illuminate\Support\Collection;
use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\ExpectedDividend;

readonly class ExpectedDividendRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return ExpectedDividend::class;
    }

    /**
     * Ожидаемые дивиденды (record_date >= сегодня) для указанных инструментов.
     *
     * @param int[] $instrumentIds
     */
    public function findUpcomingByInstrumentIds(array $instrumentIds): Collection
    {
        if (!$instrumentIds) {
            return new Collection();
        }

        return $this->createQueryBuilder()
            ->join('instruments', 'instruments.id', '=', 'expected_dividends.instrument_id')
            ->whereIn('expected_dividends.instrument_id', $instrumentIds)
            ->where('expected_dividends.record_date', '>=', date('Y-m-d'))
            ->select([
                'expected_dividends.instrument_id',
                'expected_dividends.record_date',
                'expected_dividends.payment_date',
                'expected_dividends.amount_per_share',
                'expected_dividends.currency',
                'expected_dividends.amount_rub',
                'expected_dividends.is_confirmed',
                'instruments.ticker',
                'instruments.name',
            ])
            ->orderBy('expected_dividends.record_date', 'asc')
            ->get();
    }
}