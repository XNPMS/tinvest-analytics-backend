<?php

declare(strict_types=1);

namespace Tinvest\DTO;

final readonly class UpcomingDividend
{
    public function __construct(
        public string $ticker,
        public string $name,
        public string $recordDate,
        public string $paymentDate,
        public float $amountPerShare,
        public string $currency,
        public ?float $amountRub,
        public bool $isConfirmed,
    ) {
    }
}
