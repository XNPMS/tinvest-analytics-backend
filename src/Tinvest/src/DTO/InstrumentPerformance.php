<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class InstrumentPerformance
{
    public function __construct(
        public string $ticker,
        public string $name,
        public ?string $figi,
        public ?string $instrumentType,
        public bool $isOpen,
        public float $quantity,
        public float $avgPriceRub,
        public ?float $currentPriceRub,
        public float $realizedPnlRub,
        public float $realizedPnlPercent,
        public float $unrealizedPnlRub,
        public float $totalPnlRub,
        public float $totalPnlPercent,
        public ?float $annualizedPnlPercent,
        public ?float $todayPnlRub,
        public ?float $todayPnlPercent,
    ) {
    }
}
