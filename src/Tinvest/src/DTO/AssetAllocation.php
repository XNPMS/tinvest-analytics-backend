<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class AssetAllocation
{
    /**
     * @param AssetAllocationItem[] $items
     * @param AssetAllocationBreakdownItem[] $byType
     * @param AssetAllocationBreakdownItem[] $byCurrency
     * @param AssetAllocationBreakdownItem[] $bySector
     */
    public function __construct(
        public float $totalValueRub,
        public array $items,
        public array $byType,
        public array $byCurrency,
        public array $bySector,
    ) {
    }
}
