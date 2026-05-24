<?php

declare(strict_types=1);

namespace Tinvest\Enum;

enum AssetType: string
{
    case STOCK = 'stock';
    case BOND = 'bond';
    case ETF = 'etf';
    case CURRENCY = 'currency';
    case FUTURE = 'future';
    case OPTION = 'option';
    case OTHER = 'other';

    /**
     * Тинькофф передаёт строковые типы, которые не совпадают с нашим ENUM
     */
    public static function fromTinkoff(string $type): self
    {
        return match (strtolower($type)) {
            'share' => self::STOCK,
            'bond' => self::BOND,
            'etf' => self::ETF,
            'currency', 'fx' => self::CURRENCY,
            'futures', 'future' => self::FUTURE,
            'option', 'options' => self::OPTION,
            default => self::OTHER,
        };
    }
}
