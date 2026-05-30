<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\TinvestSplit;

/**
 * TODO: подумать как добавлять сплиты в базу
 */
readonly class TinvestSplitRepository extends AbstractEloquentRepository
{
    private const MAX_LIMIT_SPLIT = 1000;

    public function getEntityClass(): string
    {
        return TinvestSplit::class;
    }

    /**
     * Возвращает сплиты в диапазоне дат, сгруппированные по дате.
     *
     * @return array<string, array<string, int>> [split_date => [ticker => ratio]]
     */
    public function findGroupedByDate(string $from, string $to): array
    {
        /** @var TinvestSplit[] $splits */
        $splits = $this->createQueryBuilder()
            ->where('split_date', '>=', $from)
            ->where('split_date', '<=', $to)
            ->limit(self::MAX_LIMIT_SPLIT)
            ->get()
            ->all();

        $result = [];
        foreach ($splits as $split) {
            $result[$split->getSplitDate()][$split->getTicker()] = $split->getRatio();
        }

        return $result;
    }

    /**
     * Возвращает сплиты для указанных тикеров, сгруппированные по тикеру, отсортированные по дате.
     *
     * @param string[] $tickers
     * @return array<string, list<array{date: string, ratio: int}>>
     */
    public function findByTickersGroupedByTicker(array $tickers): array
    {
        if (!$tickers) {
            return [];
        }

        $splits = $this->createQueryBuilder()
            ->whereIn('ticker', $tickers)
            ->orderBy('split_date', 'asc')
            ->limit(count($tickers))
            ->get();

        $result = [];
        foreach ($splits as $split) {
            $result[$split->getTicker()][] = ['date' => $split->getSplitDate(), 'ratio' => $split->getRatio()];
        }

        return $result;
    }

    public function upsert(string $ticker, string $splitDate, int $ratio): void
    {
        $this->createQueryBuilder()->upsert(
            [['ticker' => $ticker, 'split_date' => $splitDate, 'ratio' => $ratio]],
            ['ticker', 'split_date'],
            ['ratio'],
        );
    }
}
