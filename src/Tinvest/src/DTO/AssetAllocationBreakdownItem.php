<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class AssetAllocationBreakdownItem
{
    public function __construct(
        public string $key,
        public float $valueRub,
        public float $percent,
    ) {
    }
}
