<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class DashboardSummary
{
    public function __construct(
        public float $totalValueRub,
        public float $unrealizedPnlRub,
        public float $twrPercent,
        public float $dividendsRub,
    ) {
    }

    public function toArray(): array
    {
        return [
            'total_value_rub' => $this->totalValueRub,
            'unrealized_pnl_rub' => $this->unrealizedPnlRub,
            'twr_percent' => $this->twrPercent,
            'dividends_rub' => $this->dividendsRub,
        ];
    }
}
