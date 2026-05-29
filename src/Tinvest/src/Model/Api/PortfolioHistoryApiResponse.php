<?php

declare(strict_types=1);

namespace Tinvest\Model\Api;

use Tinvest\DTO\PortfolioHistory;
use Tinvest\DTO\PortfolioHistoryPoint;

use function array_map;

final readonly class PortfolioHistoryApiResponse
{
    private function __construct(
        private array $data,
        private array $summary,
    ) {
    }

    public static function fromPortfolioHistory(PortfolioHistory $result): self
    {
        return new self(
            array_map(
                static fn(PortfolioHistoryPoint $point): array => [
                    'date' => $point->date,
                    'total_value_rub' => $point->totalValueRub,
                    'cash_flow_rub' => $point->cashFlowRub,
                    'twr_factor' => $point->twrFactor,
                ],
                $result->data,
            ),
            [
                'twr_percent' => $result->summary->twrPercent,
                'first_value' => $result->summary->firstValue,
                'last_value' => $result->summary->lastValue,
            ],
        );
    }

    public function toApi(): array
    {
        return [
            'data' => $this->data,
            'summary' => $this->summary,
        ];
    }
}
