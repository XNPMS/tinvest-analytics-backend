<?php

declare(strict_types=1);

namespace Tinvest\Model\Api;

use Tinvest\DTO\DashboardSummary;

final readonly class DashboardSummaryApiResponse
{
    private function __construct(
        private float $totalValueRub,
        private float $unrealizedPnlRub,
        private float $twrPercent,
        private float $dividendsRub,
    ) {
    }

    public static function fromDashboardSummary(DashboardSummary $summary): self
    {
        return new self(
            $summary->totalValueRub,
            $summary->unrealizedPnlRub,
            $summary->twrPercent,
            $summary->dividendsRub,
        );
    }

    public function toApi(): array
    {
        return [
            'total_value_rub' => $this->totalValueRub,
            'unrealized_pnl_rub' => $this->unrealizedPnlRub,
            'twr_percent' => $this->twrPercent,
            'dividends_rub' => $this->dividendsRub,
        ];
    }
}
