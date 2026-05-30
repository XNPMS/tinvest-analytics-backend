<?php

declare(strict_types=1);

namespace Tinvest\Model\Api;

use Tinvest\DTO\AssetAllocation;
use Tinvest\DTO\AssetAllocationBreakdownItem;
use Tinvest\DTO\AssetAllocationItem;

use function array_map;

final readonly class AssetAllocationApiResponse
{
    private function __construct(
        private float $totalValueRub,
        private array $items,
        private array $byType,
        private array $byCurrency,
        private array $bySector,
    ) {
    }

    public static function fromAssetAllocation(AssetAllocation $result): self
    {
        return new self(
            $result->totalValueRub,
            array_map(
                static fn(AssetAllocationItem $item): array => [
                    'ticker' => $item->ticker,
                    'instrument_type' => $item->instrumentType,
                    'value_rub' => $item->valueRub,
                    'percent' => $item->percent,
                ],
                $result->items,
            ),
            self::mapBreakdown($result->byType),
            self::mapBreakdown($result->byCurrency),
            self::mapBreakdown($result->bySector),
        );
    }

    public function toApi(): array
    {
        return [
            'total_value_rub' => $this->totalValueRub,
            'items' => $this->items,
            'by_type' => $this->byType,
            'by_currency' => $this->byCurrency,
            'by_sector' => $this->bySector,
        ];
    }

    /** @param AssetAllocationBreakdownItem[] $items */
    private static function mapBreakdown(array $items): array
    {
        return array_map(
            static fn(AssetAllocationBreakdownItem $item): array => [
                'key' => $item->key,
                'value_rub' => $item->valueRub,
                'percent' => $item->percent,
            ],
            $items,
        );
    }
}
