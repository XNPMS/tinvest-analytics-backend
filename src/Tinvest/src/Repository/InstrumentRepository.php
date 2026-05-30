<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use Illuminate\Support\Collection;
use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\Instrument;
use Tinvest\Entity\TinvestOperation;

readonly class InstrumentRepository extends AbstractEloquentRepository
{
    /**
     * Поля при первичном сохранении из операций (sector/exchange/isin/lot_size не трогаем)
     */
    private const UPSERT_UPDATE_COLUMNS = ['name', 'ticker', 'asset_type'];

    /**
     * Поля, которые заполняются при обогащении через GetInstrumentBy / ShareBy / BondBy / EtfBy
     */
    private const ENRICH_UPDATE_COLUMNS = [
        'name',
        'ticker',
        'isin',
        'currency',
        'sector',
        'exchange',
        'lot_size',
        'nominal',
    ];

    public function getEntityClass(): string
    {
        return Instrument::class;
    }

    public function upsertBatch(array $rows): void
    {
        $this->createQueryBuilder()->upsert($rows, ['figi'], self::UPSERT_UPDATE_COLUMNS);
    }

    public function enrichBatch(array $rows): void
    {
        $this->createQueryBuilder()->upsert($rows, ['figi'], self::ENRICH_UPDATE_COLUMNS);
    }

    /**
     * @param string[] $tickers
     * @return array<string, array{lot_size:int, nominal:float|null, asset_type:string}> [ticker => [...]]
     */
    public function findByTickers(array $tickers): array
    {
        if (!$tickers) {
            return [];
        }

        $rows = $this->createQueryBuilder()
            ->select(['ticker', 'lot_size', 'nominal', 'asset_type', 'currency'])
            ->whereIn('ticker', $tickers)
            ->limit(count($tickers))
            ->get()
            ->all();

        $result = [];
        foreach ($rows as $instrument) {
            $result[$instrument->getTicker()] = [
                'lot_size' => $instrument->getLotSize(),
                'nominal' => $instrument->getNominal(),
                'asset_type' => $instrument->getAssetType(),
                'currency' => $instrument->getCurrency(),
            ];
        }

        return $result;
    }

    /**
     * Возвращает необогащённые инструменты (exchange IS NULL) для операций по указанным счетам.
     * exchange = null означает, что инструмент ещё не проходил GetInstrumentBy.
     */
    public function findUnenrichedByAccountIds(array $accountIds): Collection
    {
        if (!$accountIds) {
            return new Collection();
        }

        return $this->createQueryBuilder()
            ->select([
                sprintf('%s.figi', Instrument::TABLE),
                sprintf('%s.asset_type', Instrument::TABLE),
            ])
            ->join(
                TinvestOperation::TABLE,
                sprintf('%s.figi', TinvestOperation::TABLE),
                '=',
                sprintf('%s.figi', Instrument::TABLE)
            )
            ->whereIn(sprintf('%s.account_id', TinvestOperation::TABLE), $accountIds)
            ->whereNull(sprintf('%s.exchange', Instrument::TABLE))
            ->distinct()
            ->limit(count($accountIds))
            ->get()
            ->map(static fn(Instrument $i) => [
                'figi' => $i->getFigi(),
                'asset_type' => $i->getAssetType(),
            ]);
    }

    /**
     * @param string[] $tickers
     * @return int[]
     */
    public function findIdsByTickers(array $tickers): array
    {
        if (!$tickers) {
            return [];
        }

        return $this->createQueryBuilder()
            ->select(['id'])
            ->whereIn('ticker', $tickers)
            ->limit(count($tickers))
            ->get()
            ->map(static fn(Instrument $i) => $i->getId())
            ->toArray();
    }

    /**
     * @param string[] $tickers
     * @return array<string, string|null> [ticker => sector]
     */
    public function findSectorsByTickers(array $tickers): array
    {
        if (!$tickers) {
            return [];
        }

        $rows = $this->createQueryBuilder()
            ->select(['ticker', 'sector'])
            ->whereIn('ticker', $tickers)
            ->limit(count($tickers))
            ->get();

        $result = [];
        foreach ($rows as $instrument) {
            $result[$instrument->getTicker()] = $instrument->getSector();
        }

        return $result;
    }

    public function findDistinctNominalCurrenciesByAccountIds(array $accountIds): array
    {
        if (!$accountIds) {
            return [];
        }

        return $this->createQueryBuilder()
            ->join('tinvest_operations', 'instruments.figi', '=', 'tinvest_operations.figi')
            ->whereIn('tinvest_operations.account_id', $accountIds)
            ->whereNotNull('instruments.currency')
            ->where('instruments.currency', '!=', 'rub')
            ->distinct()
            ->pluck('instruments.currency')
            ->map(static fn(string $c) => strtolower($c))
            ->toArray();
    }
}
