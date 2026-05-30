<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class AssetAllocationItem
{
    public function __construct(
        public string $ticker,
        public string $instrumentType,
        public float $valueRub,
        public float $percent,
    ) {
    }
}
