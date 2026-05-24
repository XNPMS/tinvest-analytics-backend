<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use Illuminate\Support\Collection;
use System\Exception\InvalidArgumentRepositoryException;
use System\Repository\AbstractEloquentRepository;
use Tinvest\Entity\PortfolioSnapshot;

readonly class PortfolioSnapshotRepository extends AbstractEloquentRepository
{
    private const UPSERT_UPDATE_COLUMNS = [
        'total_value_rub',
        'expected_yield_rub',
        'cash_flow_rub',
        'twr_factor',
        'cumulative_twr',
        'positions_json',
    ];

    public function getEntityClass(): string
    {
        return PortfolioSnapshot::class;
    }

    public function upsert(array $row): void
    {
        $this->createQueryBuilder()->upsert([$row], ['account_id', 'snapshot_date'], self::UPSERT_UPDATE_COLUMNS);
    }

    public function findLatestByAccountId(int $accountId): ?PortfolioSnapshot
    {
        /** @var PortfolioSnapshot|null */
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->orderBy('snapshot_date', 'desc')
            ->first();
    }

    /**
     * Возвращает последний снэпшот строго раньше указанной даты.
     * Используется для TWR: нужен предыдущий день, а не текущий.
     */
    public function findLatestBeforeDate(int $accountId, string $date): ?PortfolioSnapshot
    {
        /** @var PortfolioSnapshot|null */
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->where('snapshot_date', '<', $date)
            ->orderBy('snapshot_date', 'desc')
            ->first();
    }

    /**
     * Возвращает последний снэпшот для каждого из указанных счетов.
     * Если передана $before - берётся последний снэпшот СТРОГО раньше этой даты.
     *
     * @param int[] $accountIds
     * @param string|null $before Y-m-d граница (не включается)
     */
    public function findLatestForAccounts(array $accountIds, ?string $before = null): Collection
    {
        if (!$accountIds) {
            return new Collection();
        }

        $sub = $this->createQueryBuilder()
            ->selectRaw('account_id, MAX(snapshot_date) as max_date')
            ->whereIn('account_id', $accountIds);

        if ($before !== null) {
            $sub->where('snapshot_date', '<', $before);
        }

        $sub->groupBy('account_id');

        return $this->createQueryBuilder()
            ->joinSub($sub, 'latest', function ($join) {
                $join->on(sprintf('%s.account_id', PortfolioSnapshot::TABLE), '=', 'latest.account_id')
                    ->on(sprintf('%s.snapshot_date', PortfolioSnapshot::TABLE), '=', 'latest.max_date');
            })
            ->get();
    }

    private const MAX_DAYS_PER_ACCOUNT = 3650;

    /**
     * Возвращает список дат (Y-m-d), для которых уже есть снэпшоты по счёту.
     *
     * @return string[]
     */
    public function findExistingDatesByAccountId(int $accountId): array
    {
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->limit(self::MAX_DAYS_PER_ACCOUNT)
            ->pluck('snapshot_date')
            ->toArray();
    }

    /**
     * @throws InvalidArgumentRepositoryException
     */
    public function findByAccountIdAndPeriod(
        int $brokerAccountId,
        string $userId,
        string $from,
        string $to,
        callable $callback,
    ): void {
        if ($brokerAccountId < 0) {
            throw InvalidArgumentRepositoryException::invalidBrokerAccountId($brokerAccountId);
        }

        $this->createQueryBuilder()
            ->where('account_id', '=', $brokerAccountId)
            ->where('user_id', '=', $userId)
            ->whereBetween('snapshot_date', [$from, $to])
            ->orderBy('snapshot_date', 'asc')
            ->chunkById(self::MAX_LIMIT, $callback);
    }

    public function findByAccountIdsAndPeriod(
        array $accountIds,
        string $from,
        string $to,
        callable $callback,
    ): void {
        if (!$accountIds) {
            return;
        }

        $this->createQueryBuilder()
            ->whereIn('account_id', $accountIds)
            ->whereBetween('snapshot_date', [$from, $to])
            ->orderBy('snapshot_date', 'asc')
            ->chunkById(self::MAX_LIMIT, $callback);
    }

    public function findLatestSnapshotDate(int $accountId): ?string
    {
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->orderBy('snapshot_date', 'desc')
            ->value('snapshot_date');
    }

    public function deleteByAccountIdAndDateRange(int $accountId, string $from, string $to): void
    {
        $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->whereBetween('snapshot_date', [$from, $to])
            ->delete();
    }
}
