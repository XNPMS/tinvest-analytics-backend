<?php

declare(strict_types=1);

namespace Tinvest\Repository;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use System\Repository\AbstractEloquentRepository;
use Tinkoff\Invest\V1\OperationType;
use Tinvest\Entity\TinvestOperation;
use Tinvest\Helper\PortfolioMath;

readonly class TinvestOperationRepository extends AbstractEloquentRepository
{
    public function getEntityClass(): string
    {
        return TinvestOperation::class;
    }

    public function insertBatch(array $rows): int
    {
        $now = date(self::DATETIME_FORMAT);
        $rows = array_map(static fn(array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $rows);

        return $this->createQueryBuilder()->insertOrIgnore($rows);
    }

    /**
     * Возвращает уникальные коды валют (нижний регистр) для операций по набору счетов.
     *
     * @param int[] $accountIds
     * @return string[]
     */
    public function findDistinctCurrenciesByAccountIds(array $accountIds): array
    {
        if (!$accountIds) {
            return [];
        }

        return $this->createQueryBuilder()
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('payment_currency')
            ->distinct()
            ->pluck('payment_currency')
            ->map(static fn(string $c) => strtolower($c))
            ->toArray();
    }

    /**
     * Суммарные дивиденды и купоны в рублях по набору счетов.
     *
     * @param int[] $accountIds
     */
    public function getTotalDividendsRubByAccountIds(array $accountIds): float
    {
        if (!$accountIds) {
            return 0.0;
        }

        return (float)$this->createQueryBuilder()
            ->whereIn('account_id', $accountIds)
            ->whereIn('operation_type', [
                OperationType::OPERATION_TYPE_DIVIDEND,
                OperationType::OPERATION_TYPE_COUPON,
                OperationType::OPERATION_TYPE_DIVIDEND_TRANSFER,
            ])
            ->whereNotNull('payment_rub')
            ->sum('payment_rub');
    }

    /**
     * Возвращает денежный поток (пополнения - выводы) в рублях по счёту за указанную дату.
     * Используется для расчёта TWR сегодняшнего снэпшота.
     */
    public function getDailyCashFlowRubByAccountId(int $accountId, string $date): float
    {
        $result = $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->whereIn('operation_type', PortfolioMath::CASH_FLOW_TYPES)
            ->whereNotNull('payment_rub')
            ->whereRaw('DATE(date) = ?', [$date])
            ->sum('payment_rub');

        return (float)$result;
    }

    /**
     * Возвращает все операции по счёту, отсортированные по дате (ASC).
     * TODO: надо чанками
     */
    public function findByAccountIdSortedByDate(int $accountId): Collection
    {
        return $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Вызывает $callback для каждого чанка из 500 инструментальных операций по набору счетов,
     * отсортированных по дате (ASC). Используется для расчёта FIFO по портфелю.
     *
     * @param int[] $accountIds
     */
    public function findInstrumentOperationsByAccountIds(string $userId, array $accountIds, callable $callback): void
    {
        if (!$accountIds) {
            return;
        }

        $this->createQueryBuilder()
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('ticker')
            ->where('ticker', '!=', '')
            ->where(static function ($q) {
                // исключаем дочерние операции (отдельные сделки-части заявки):
                // они дублируют количество родительской агрегированной операции
                $q->whereNull('parent_operation_id')
                  ->orWhere('parent_operation_id', '=', '');
            })
            ->chunkById(500, $callback);
    }

    /**
     * Уникальные тикеры инструментов по набору счетов.
     *
     * @param int[] $accountIds
     * @return string[]
     */
    public function findDistinctTickersByAccountIds(array $accountIds): array
    {
        if (!$accountIds) {
            return [];
        }

        return $this->createQueryBuilder()
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('ticker')
            ->where('ticker', '!=', '')
            ->distinct()
            ->pluck('ticker')
            ->toArray();
    }

    /**
     * История дивидендных операций по набору счетов, свежие первыми.
     *
     * @param int[] $accountIds
     */
    public function findDividendsByAccountIds(array $accountIds, int $limit = 50): Collection
    {
        if (!$accountIds) {
            return new Collection();
        }

        return $this->createQueryBuilder()
            ->whereIn('account_id', $accountIds)
            ->whereIn('operation_type', [
                OperationType::OPERATION_TYPE_DIVIDEND,
                OperationType::OPERATION_TYPE_COUPON,
                OperationType::OPERATION_TYPE_DIVIDEND_TRANSFER,
            ])
            ->whereNotNull('payment_rub')
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Заполняет payment_rub / commission_rub / fx_rate для операций в указанной валюте.
     * Обрабатывает только строки, где payment_units заполнен, а payment_rub ещё нет.
     */
    public function updatePaymentRubForAccount(int $accountId, string $currency, float $rate): void
    {
        $this->createQueryBuilder()
            ->where('account_id', '=', $accountId)
            ->where('payment_currency', '=', $currency)
            ->whereNull('payment_rub')
            ->whereNotNull('payment_units')
            ->update([
                'payment_rub' => new Expression(sprintf(
                    '(payment_units + payment_nano / 1000000000.0) * %.6f',
                    $rate
                )),
                'commission_rub' => new Expression(sprintf('commission * %.6f', $rate)),
                'fx_rate' => $rate,
            ]);
    }
}
