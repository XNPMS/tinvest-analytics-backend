<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class PortfolioHistorySummary
{
    public function __construct(
        public float $twrPercent,
        public float $firstValue,
        public float $lastValue,
    ) {
    }
}
