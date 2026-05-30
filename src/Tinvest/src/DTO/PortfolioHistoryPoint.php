<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class PortfolioHistoryPoint
{
    public function __construct(
        public string $date,
        public float $totalValueRub,
        public float $cashFlowRub,
        public float $twrFactor,
    ) {
    }
}
