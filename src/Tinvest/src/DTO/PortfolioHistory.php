<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class PortfolioHistory
{
    /** @param PortfolioHistoryPoint[] $data */
    public function __construct(
        public array $data,
        public PortfolioHistorySummary $summary,
    ) {
    }
}
