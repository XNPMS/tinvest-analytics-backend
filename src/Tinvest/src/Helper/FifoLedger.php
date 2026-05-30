<?php

declare(strict_types=1);

namespace Tinvest\Helper;

use Tinkoff\Invest\V1\OperationType;
use Tinvest\Collection\OperationsCollection;
use Tinvest\Entity\TinvestOperation;

/**
 * Накапливает FIFO-позиции из потока операций с учётом сплитов.
 *
 * Использование:
 *   $ledger = new FifoLedger($splitsByTicker);
 *   $ledger->feed($chunk); // можно вызывать несколько раз для чанков
 *   $positions = $ledger->positions(); // финализирует и возвращает результат
 */
class FifoLedger
{
    private const BUY_TYPES = [
        OperationType::OPERATION_TYPE_BUY,
        OperationType::OPERATION_TYPE_BUY_CARD,
        OperationType::OPERATION_TYPE_BUY_MARGIN,
    ];

    private const SELL_TYPES = [
        OperationType::OPERATION_TYPE_SELL,
        OperationType::OPERATION_TYPE_SELL_CARD,
        OperationType::OPERATION_TYPE_SELL_MARGIN,
        OperationType::OPERATION_TYPE_BOND_REPAYMENT,
        OperationType::OPERATION_TYPE_BOND_REPAYMENT_FULL,
    ];

    private const INCOME_TYPES = [
        OperationType::OPERATION_TYPE_DIVIDEND,
        OperationType::OPERATION_TYPE_COUPON,
        OperationType::OPERATION_TYPE_DIVIDEND_TRANSFER,
    ];

    private const QTY_EPSILON = 0.001;

    /** @var array<string, array<string, mixed>> */
    private array $instruments = [];

    /**
     * @param array<string, list<array{date: string, ratio: int}>> $splitsByTicker
     */
    public function __construct(
        private readonly array $splitsByTicker,
    ) {
    }

    /**
     * Принимает чанк операций и обновляет внутреннее FIFO-состояние.
     * Операции должны поступать в хронологическом порядке.
     */
    public function feed(OperationsCollection $chunk): void
    {
        /** @var TinvestOperation $operation */
        foreach ($chunk as $operation) {
            if (!$ticker = $operation->getTicker()) {
                continue;
            }

            if (($paymentRub = $this->resolvePaymentRub($operation)) === null) {
                continue;
            }

            if (!isset($this->instruments[$ticker])) {
                $this->instruments[$ticker] = $this->makeInstrumentRow($operation);
            }

            $operationType = (int)$operation->getOperationType();

            if (in_array($operationType, self::INCOME_TYPES, true)) {
                $this->instruments[$ticker]['realized_pnl_rub'] += $paymentRub;

                continue;
            }

            if (($quantity = $operation->getActualQuantity()) <= 0.0) {
                continue;
            }

            // Применяем сплиты: каждый сплит после даты операции умножает количество
            $operationDate = substr((string)$operation->getDate(), 0, 10);
            $splitFactor = 1;
            foreach ($this->splitsByTicker[$ticker] ?? [] as $split) {
                if ($split['date'] > $operationDate) {
                    $splitFactor *= $split['ratio'];
                }
            }

            if ($splitFactor !== 1) {
                $quantity *= $splitFactor;
            }

            if (in_array($operationType, self::BUY_TYPES, true)) {
                $pricePerUnit = abs($paymentRub) / $quantity;
                $this->instruments[$ticker]['_fifo'][] = ['price' => $pricePerUnit, 'qty' => $quantity];
                $this->instruments[$ticker]['quantity'] += $quantity;

                if ($this->instruments[$ticker]['_first_buy_date'] === null) {
                    $this->instruments[$ticker]['_first_buy_date'] = $operation->getDate();
                }
            } elseif (in_array($operationType, self::SELL_TYPES, true)) {
                $sellPricePerUnit = $paymentRub / $quantity;
                $remainingQty = $quantity;

                while ($remainingQty > self::QTY_EPSILON && !empty($this->instruments[$ticker]['_fifo'])) {
                    $lot = &$this->instruments[$ticker]['_fifo'][0];
                    $consumed = min($lot['qty'], $remainingQty);

                    $this->instruments[$ticker]['realized_pnl_rub'] += ($sellPricePerUnit - $lot['price']) * $consumed;
                    $this->instruments[$ticker]['_realized_cost'] += $lot['price'] * $consumed;

                    $lot['qty'] -= $consumed;
                    $remainingQty -= $consumed;

                    if ($lot['qty'] <= self::QTY_EPSILON) {
                        array_shift($this->instruments[$ticker]['_fifo']);
                    }
                }

                $this->instruments[$ticker]['quantity'] -= $quantity;
                $this->instruments[$ticker]['_last_sell_date'] = $operation->getDate();
            }
        }
    }

    /**
     * Финализирует накопленное состояние и возвращает карту позиций.
     * Внутренние служебные поля (_fifo, _realized_cost) удаляются.
     * Поля _first_buy_date и _last_sell_date сохраняются для дальнейших расчётов.
     *
     * @return array<string, array<string, mixed>>
     */
    public function positions(): array
    {
        $instruments = $this->instruments;

        foreach ($instruments as &$instrument) {
            $remainingQty = max(0.0, $instrument['quantity']);
            $instrument['is_open'] = $remainingQty > self::QTY_EPSILON;
            $instrument['quantity'] = $remainingQty;
            $instrument['realized_pnl_rub'] = round($instrument['realized_pnl_rub'], 2);

            // Доходность реализованной части (актуальна как для закрытых, так и для частично проданных)
            if ($instrument['_realized_cost'] > 0) {
                $instrument['realized_pnl_percent'] = round(
                    $instrument['realized_pnl_rub'] / $instrument['_realized_cost'] * 100,
                    2
                );
            }

            if ($instrument['is_open'] && !empty($instrument['_fifo'])) {
                $remainingCost = array_sum(array_map(
                    static fn(array $lot) => $lot['price'] * $lot['qty'],
                    $instrument['_fifo']
                ));
                $instrument['avg_price_rub'] = $remainingQty > 0
                    ? round($remainingCost / $remainingQty, 4)
                    : 0.0;
            }

            if (!$instrument['is_open']) {
                $instrument['total_pnl_rub'] = $instrument['realized_pnl_rub'];
                $instrument['total_pnl_percent'] = $instrument['_realized_cost'] > 0
                    ? round($instrument['realized_pnl_rub'] / $instrument['_realized_cost'] * 100, 2)
                    : 0.0;
            }

            unset($instrument['_fifo'], $instrument['_realized_cost']);
        }

        return $instruments;
    }

    private function makeInstrumentRow(TinvestOperation $operation): array
    {
        return [
            'ticker' => $operation->getTicker(),
            'name' => $operation->getName() ?: $operation->getTicker(),
            'figi' => $operation->getFigi(),
            'instrument_type' => $operation->getInstrumentType(),
            'is_open' => false,
            'quantity' => 0.0,
            'avg_price_rub' => 0.0,
            'current_price_rub' => null,
            'realized_pnl_rub' => 0.0,
            'realized_pnl_percent' => 0.0,
            'unrealized_pnl_rub' => 0.0,
            'total_pnl_rub' => 0.0,
            'total_pnl_percent' => 0.0,
            'annualized_pnl_percent' => null,
            'today_pnl_rub' => null,
            'today_pnl_percent' => null,
            '_fifo' => [],
            '_realized_cost' => 0.0,
            '_first_buy_date' => null,
            '_last_sell_date' => null,
        ];
    }

    private function resolvePaymentRub(TinvestOperation $operation): ?float
    {
        $paymentRub = $operation->getPaymentRub();
        if ($paymentRub !== null) {
            return $paymentRub;
        }

        $payment = $operation->getPayment();
        $fxRate = $operation->getFxRate();
        if ($payment !== null && $fxRate > 0.0) {
            return $payment * $fxRate;
        }

        return null;
    }
}
