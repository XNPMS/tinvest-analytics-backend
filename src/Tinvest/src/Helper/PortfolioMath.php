<?php

declare(strict_types=1);

namespace Tinvest\Helper;

use Tinkoff\Invest\V1\OperationType;

/**
 * Чистые функции для расчёта доходности портфеля.
 *
 * TWR (Time-Weighted Return) - метод оценки доходности, не зависящей от внешних денежных потоков.
 * CASH_FLOW_TYPES - исчерпывающий список типов операций, являющихся внешним денежным потоком
 * (пополнения и выводы). Используется при расчёте TWR и агрегации cash flow по счёту.
 */
final class PortfolioMath
{
    /**
     * Нейтральный TWR-множитель: портфель не изменился в цене за период
     */
    public const NEUTRAL_TWR_FACTOR = 1.0;

    /**
     * Нижняя граница для сравнений с плавающей точкой: значение считается нулевым
     */
    public const ZERO = 0.0;

    /**
     * Типы операций, представляющих внешний денежный поток портфеля (пополнения и выводы).
     * Не включает покупки/продажи и купоны - это внутренние перемещения капитала.
     */
    public const CASH_FLOW_TYPES = [
        OperationType::OPERATION_TYPE_INPUT,
        OperationType::OPERATION_TYPE_INPUT_SWIFT,
        OperationType::OPERATION_TYPE_INPUT_ACQUIRING,
        OperationType::OPERATION_TYPE_INP_MULTI,
        OperationType::OPERATION_TYPE_OUTPUT,
        OperationType::OPERATION_TYPE_OUTPUT_SWIFT,
        OperationType::OPERATION_TYPE_OUTPUT_ACQUIRING,
        OperationType::OPERATION_TYPE_OUT_MULTI,
    ];

    /**
     * HPR (Holding Period Return) - множитель доходности за один период.
     * Формула: end_value / (prev_value + cash_flow)
     *
     * Возвращает 1.0 (нейтральный множитель) если:
     * - prevValue <= 0 (первый ненулевой день, нет базы для сравнения)
     * - знаменатель <= 0 (аномальное состояние)
     */
    public static function twrFactor(float $prevValue, float $endValue, float $cashFlow): float
    {
        if ($prevValue <= self::ZERO) {
            return self::NEUTRAL_TWR_FACTOR;
        }

        $denominator = $prevValue + $cashFlow;
        if ($denominator <= self::ZERO) {
            return self::NEUTRAL_TWR_FACTOR;
        }

        return $endValue / $denominator;
    }
}
